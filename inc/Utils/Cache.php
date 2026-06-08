<?php
/**
 * Cache utility.
 *
 * @package rtCamp\WPFramework\Utils
 * @since   0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Utils;

/**
 * Typed wrapper over WordPress's object-cache functions with a request-level
 * cache layer and stale-while-revalidate (SWR) stampede prevention.
 *
 * Static helper (like {@see Encryptor}) — callable from anywhere, no wiring
 * required:
 *
 *     $nav = Cache::remember( 'nav_items', fn() => build_nav(), 'theme', 300 );
 *
 * Centralises calls to `wp_cache_get`, `wp_cache_set`, `wp_cache_delete`, and
 * `wp_cache_flush_group` so consumers have a single typed API and one place to
 * layer cross-cutting behaviour (logging, telemetry, fallbacks).
 *
 * **Request-level cache.** `set()`/`get()`/`delete()` maintain an in-process
 * copy keyed by `[group][key]`, sitting in front of the object cache. Repeated
 * reads of the same key within a single request are served from this layer
 * without a further object-cache round-trip (which, for persistent backends
 * such as Redis or Memcached, is a network call). The layer lives for the
 * duration of the PHP request — in PHP-FPM/mod_php it resets naturally between
 * requests; in long-running processes (WP-CLI, queue workers) clear it
 * explicitly with {@see flush_runtime()}. Pass `$force = true` to `get()` to
 * bypass it and re-read from the backend. Because it is request-scoped it has
 * no TTL — a value is served for the rest of the request regardless of its
 * `$expiration`, matching WordPress's own runtime cache.
 *
 * Miss detection uses `wp_cache_get()`'s `$found` out-parameter (and
 * `array_key_exists()` for the runtime layer), so falsy values such as `false`
 * or `0` are cached and served like any other value — a callback returning
 * `false` is not regenerated on every call.
 *
 * `remember()` adds SWR stampede prevention: on expiry, stale data is served
 * immediately while one process regenerates — no workers block waiting for
 * fresh data.
 *
 * `wp_cache_flush_group()` was added in WordPress 6.1; `flush_group()` checks
 * `wp_cache_supports( 'flush_group' )` so it gracefully returns `false` when
 * the persistent cache backend does not support group flushing.
 *
 * Not `final`: downstream packages may extend it (e.g. to tune the constants
 * or wrap a method). Internal references use late static binding (`static::`)
 * so overrides take effect.
 *
 * @package rtCamp\WPFramework\Utils
 * @since   0.0.1
 */
class Cache {

	/**
	 * TTL (seconds) for the regeneration lock key.
	 *
	 * Dead-man switch: if the regenerating process crashes before releasing the
	 * lock, it auto-expires so subsequent requests are not permanently blocked.
	 */
	protected const LOCK_TTL = 30;

	/**
	 * Microseconds to sleep between cold-start lock-wait retries.
	 */
	protected const LOCK_WAIT_US = 50_000;

	/**
	 * Maximum retries while waiting for another process's cold-start regeneration.
	 */
	protected const LOCK_RETRIES = 2;

	/**
	 * Multiplier applied to `$expiration` when storing the stale entry.
	 * Stale data must outlive the fresh entry long enough for at least one
	 * regeneration cycle to complete.
	 */
	protected const STALE_MULTIPLIER = 2;

	/**
	 * Request-level (runtime) cache, keyed by `[group][key]`.
	 *
	 * Lives for the duration of the PHP request. Sits in front of the object
	 * cache so repeated reads within one request avoid the backend round-trip.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected static array $runtime = [];

	/**
	 * Static helper — not instantiable.
	 */
	protected function __construct() {}

	/**
	 * Get a value from cache.
	 *
	 * Checks the request-level cache first; on a miss it falls through to the
	 * object cache and, on a hit there, promotes the value into the request
	 * layer. Pass `$force = true` to skip the request layer and re-read from the
	 * backend (the fresh value is promoted back into the request layer).
	 *
	 * Returns `mixed` because object-cache entries can hold any serialisable
	 * value (mirrors WordPress's own `wp_cache_get()` signature).
	 *
	 * @param string    $key   Cache key.
	 * @param string    $group Cache group. Defaults to the global group.
	 * @param bool      $force Bypass the request-level cache and re-read from the
	 *                         object cache (relevant for persistent backends).
	 * @param bool|null $found Set to `true` if the key was found, `false` if not.
	 *                         Disambiguates a stored falsy value from a miss.
	 *
	 * @return mixed Stored value, or `false` if missing or expired.
	 */
	public static function get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		if ( ! $force && isset( static::$runtime[ $group ] ) && array_key_exists( $key, static::$runtime[ $group ] ) ) {
			$found = true;
			return static::$runtime[ $group ][ $key ];
		}

		$value = wp_cache_get( $key, $group, $force, $found );
		if ( $found ) {
			static::$runtime[ $group ][ $key ] = $value;
		}

		return $value;
	}

	/**
	 * Set a value in cache.
	 *
	 * Writes through both layers: the request-level cache and the object cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to store. Must be serialisable.
	 * @param string $group      Cache group. Defaults to the global group.
	 * @param int    $expiration TTL in seconds. `0` means no expiry. Applies to
	 *                           the object cache only — the request layer is
	 *                           request-scoped and has no TTL.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, mixed $value, string $group = '', int $expiration = 0 ): bool {
		static::$runtime[ $group ][ $key ] = $value;

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- expiry originates with the caller; enforced at call sites, not inside the wrapper.
		return wp_cache_set( $key, $value, $group, $expiration );
	}

	/**
	 * Delete a single key from cache.
	 *
	 * Removes the key from both the request-level cache and the object cache.
	 * Deletes only `$key`. It does not remove the companion `{key}_stale` /
	 * `{key}_lock` entries written by {@see remember()} — to fully invalidate a
	 * remembered value, flush its group instead.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Defaults to the global group.
	 *
	 * @return bool True if the entry was deleted, false otherwise.
	 */
	public static function delete( string $key, string $group = '' ): bool {
		unset( static::$runtime[ $group ][ $key ] );

		return wp_cache_delete( $key, $group );
	}

	/**
	 * Flush an entire cache group. Requires WordPress 6.1+.
	 *
	 * Also drops the group's request-level entries so a subsequent read does not
	 * serve a value the object cache has just discarded.
	 *
	 * Gates on `wp_cache_supports( 'flush_group' )` rather than a bare
	 * `function_exists()` check: `wp_cache_flush_group()` exists on every WP 6.1+
	 * core, but the active object-cache drop-in may not implement group flushing
	 * (e.g. some Memcached or Redis configurations). Calling it there would
	 * report success while flushing nothing.
	 *
	 * Both functions are guarded with `function_exists()`: `wp_cache_supports`
	 * keeps this safe on pre-6.1 cores (where neither exists), and the explicit
	 * `wp_cache_flush_group` guard protects against an inconsistent drop-in that
	 * advertises support without defining the function.
	 *
	 * @param string $group Cache group to flush.
	 *
	 * @return bool True on success; false if the WordPress version or cache
	 *              backend does not support group flushing.
	 */
	public static function flush_group( string $group ): bool {
		if (
			! function_exists( 'wp_cache_flush_group' )
			|| ! function_exists( 'wp_cache_supports' )
			|| ! wp_cache_supports( 'flush_group' )
		) {
			return false;
		}

		unset( static::$runtime[ $group ] );

		return wp_cache_flush_group( $group );
	}

	/**
	 * Clear the request-level (runtime) cache.
	 *
	 * Only drops the in-process layer; the object cache is left untouched.
	 * Useful in long-running processes (WP-CLI, queue workers) and tests, where
	 * a single PHP process spans what would otherwise be many requests.
	 *
	 * @return void
	 */
	public static function flush_runtime(): void {
		static::$runtime = [];
	}

	/**
	 * Return a cached value, generating and storing it if absent.
	 *
	 * Implements stale-while-revalidate (SWR) stampede prevention. On expiry,
	 * stale data (stored at `{key}_stale` with a 2× TTL) is returned immediately
	 * while one process regenerates in the foreground. No workers are blocked
	 * waiting for fresh data in the normal case.
	 *
	 * Reads and writes flow through {@see get()} / {@see set()}, so a remembered
	 * value is served from the request-level cache on repeat calls within the
	 * same request. The regeneration lock uses `wp_cache_add()` directly — it is
	 * a cross-process coordination primitive and must not be request-cached.
	 *
	 * Flow:
	 * - Fresh hit          → return immediately.
	 * - Fresh miss + stale → serve stale now; if lock acquired, regenerate and
	 *                        write both keys; release lock; return stale.
	 * - Both absent        → cold start (first-ever load or full flush); acquire
	 *                        lock, regenerate, write both keys; if lock not
	 *                        acquired, spin-wait up to {@see LOCK_RETRIES} ×
	 *                        {@see LOCK_WAIT_US} µs, then call `$callback`
	 *                        directly as a last resort without releasing the
	 *                        other process's lock.
	 *
	 * Hits are detected via the `$found` out-parameter, so a callback that
	 * returns a falsy value (`false`, `0`, `''`) is cached normally and not
	 * re-invoked on every call.
	 *
	 * If `$callback` throws, the exception propagates to the caller and the
	 * regeneration lock is released immediately (no value is cached), so the
	 * next request can retry without waiting out {@see LOCK_TTL}.
	 *
	 * **Requires a persistent object cache (Redis, Memcached) for effective
	 * cross-process stampede prevention.** On the default WordPress in-memory
	 * cache each PHP-FPM worker has its own isolated cache, so locks and stale
	 * entries are not shared across processes. The method remains useful as an
	 * ergonomic get-or-set helper regardless.
	 *
	 * Returns `mixed` because the stored value can be any serialisable type
	 * (mirrors WordPress's own `wp_cache_get()` return type).
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Invoked when the fresh entry is absent; its
	 *                             return value is stored and returned.
	 * @param string   $group      Cache group. Defaults to the global group.
	 * @param int      $expiration TTL in seconds for the fresh entry. `0` means
	 *                             no expiry (stale-while-revalidate has no effect).
	 *
	 * @return mixed The cached or freshly-generated value.
	 */
	public static function remember( string $key, callable $callback, string $group = '', int $expiration = 0 ): mixed {
		$found  = false;
		$cached = static::get( $key, $group, false, $found );
		if ( $found ) {
			return $cached;
		}

		$stale_key   = $key . '_stale';
		$lock_key    = $key . '_lock';
		$stale_found = false;
		$stale       = static::get( $stale_key, $group, false, $stale_found );

		if ( $stale_found ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
			if ( wp_cache_add( $lock_key, 1, $group, static::LOCK_TTL ) ) {
				static::store_with_stale( $key, $stale_key, $callback, $group, $expiration, $lock_key );
			}
			return $stale;
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
		if ( wp_cache_add( $lock_key, 1, $group, static::LOCK_TTL ) ) {
			return static::store_with_stale( $key, $stale_key, $callback, $group, $expiration, $lock_key );
		}

		for ( $i = 0; $i < static::LOCK_RETRIES; $i++ ) {
			usleep( static::LOCK_WAIT_US );
			$cached = static::get( $key, $group, false, $found );
			if ( $found ) {
				return $cached;
			}
		}

		return static::store_with_stale( $key, $stale_key, $callback, $group, $expiration );
	}

	/**
	 * Invoke `$callback`, write both cache entries, optionally release the lock, and return the value.
	 *
	 * When this process acquired the lock, it is released in a `finally` block so
	 * a throwing callback cannot leave it held for the remainder of {@see LOCK_TTL}.
	 *
	 * Returns `mixed` because the callback can return any serialisable type.
	 *
	 * @param string   $key        Fresh cache key.
	 * @param string   $stale_key  Stale cache key (grace-period copy).
	 * @param callable $callback   Invoked to produce the fresh value.
	 * @param string   $group      Cache group.
	 * @param int      $expiration TTL for the fresh entry; stale TTL = expiration × {@see STALE_MULTIPLIER}.
	 * @param string   $lock_key   Optional lock key to delete after regeneration.
	 *
	 * @return mixed The freshly-generated value.
	 */
	protected static function store_with_stale( string $key, string $stale_key, callable $callback, string $group, int $expiration, string $lock_key = '' ): mixed {
		try {
			$value            = $callback();
			$stale_expiration = $expiration > 0 ? $expiration * static::STALE_MULTIPLIER : 0;
			static::set( $key, $value, $group, $expiration );
			static::set( $stale_key, $value, $group, $stale_expiration );
			return $value;
		} finally {
			if ( '' !== $lock_key ) {
				wp_cache_delete( $lock_key, $group );
			}
		}
	}
}
