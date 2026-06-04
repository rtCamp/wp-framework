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
 * Thin, typed wrapper over WordPress's object-cache functions with
 * stale-while-revalidate (SWR) stampede prevention.
 *
 * Stateless static helper (like {@see Encryptor}) — callable from anywhere,
 * no wiring required:
 *
 *     $nav = Cache::remember( 'nav_items', fn() => build_nav(), 'theme', 300 );
 *
 * Centralises calls to `wp_cache_get`, `wp_cache_set`, `wp_cache_delete`, and
 * `wp_cache_flush_group` so consumers have a single typed API and one place to
 * layer cross-cutting behaviour (logging, telemetry, fallbacks).
 *
 * Miss detection uses `wp_cache_get()`'s `$found` out-parameter, so falsy
 * values such as `false` or `0` are cached and served like any other value —
 * a callback returning `false` is not regenerated on every call.
 *
 * `remember()` adds SWR stampede prevention: on expiry, stale data is served
 * immediately while one process regenerates — no workers block waiting for
 * fresh data.
 *
 * `wp_cache_flush_group()` was added in WordPress 6.1; `flush_group()` checks
 * `wp_cache_supports( 'flush_group' )` so it gracefully returns `false` when
 * the persistent cache backend does not support group flushing.
 *
 * @package rtCamp\WPFramework\Utils
 * @since   0.0.1
 */
final class Cache {

	/**
	 * TTL (seconds) for the regeneration lock key.
	 *
	 * Dead-man switch: if the regenerating process crashes before releasing the
	 * lock, it auto-expires so subsequent requests are not permanently blocked.
	 */
	private const LOCK_TTL = 30;

	/**
	 * Microseconds to sleep between cold-start lock-wait retries.
	 */
	private const LOCK_WAIT_US = 50_000;

	/**
	 * Maximum retries while waiting for another process's cold-start regeneration.
	 */
	private const LOCK_RETRIES = 2;

	/**
	 * Multiplier applied to `$expiration` when storing the stale entry.
	 * Stale data must outlive the fresh entry long enough for at least one
	 * regeneration cycle to complete.
	 */
	private const STALE_MULTIPLIER = 2;

	/**
	 * Stateless helper — not instantiable.
	 */
	private function __construct() {}

	/**
	 * Get a value from cache.
	 *
	 * Returns `mixed` because object-cache entries can hold any serialisable
	 * value (mirrors WordPress's own `wp_cache_get()` signature).
	 *
	 * @param string    $key   Cache key.
	 * @param string    $group Cache group. Defaults to the global group.
	 * @param bool      $force Bypass any in-process memoisation layer (relevant
	 *                         for persistent backends like Memcached).
	 * @param bool|null $found Set to `true` if the key was found, `false` if not.
	 *                         Disambiguates a stored falsy value from a miss.
	 *
	 * @return mixed Stored value, or `false` if missing or expired.
	 */
	public static function get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		return wp_cache_get( $key, $group, $force, $found );
	}

	/**
	 * Set a value in cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to store. Must be serialisable.
	 * @param string $group      Cache group. Defaults to the global group.
	 * @param int    $expiration TTL in seconds. `0` means no expiry.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, mixed $value, string $group = '', int $expiration = 0 ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- expiry originates with the caller; enforced at call sites, not inside the wrapper.
		return wp_cache_set( $key, $value, $group, $expiration );
	}

	/**
	 * Delete a single key from cache.
	 *
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
		return wp_cache_delete( $key, $group );
	}

	/**
	 * Flush an entire cache group. Requires WordPress 6.1+.
	 *
	 * Gates on `wp_cache_supports( 'flush_group' )` rather than a bare
	 * `function_exists()` check: `wp_cache_flush_group()` exists on every WP 6.1+
	 * core, but the active object-cache drop-in may not implement group flushing
	 * (e.g. some Memcached or Redis configurations). Calling it there would
	 * report success while flushing nothing. The `function_exists()` guard on
	 * `wp_cache_supports` itself keeps this safe on pre-6.1 cores, where neither
	 * function exists.
	 *
	 * @param string $group Cache group to flush.
	 *
	 * @return bool True on success; false if the WordPress version or cache
	 *              backend does not support group flushing.
	 */
	public static function flush_group( string $group ): bool {
		if ( ! function_exists( 'wp_cache_supports' ) || ! wp_cache_supports( 'flush_group' ) ) {
			return false;
		}

		return wp_cache_flush_group( $group );
	}

	/**
	 * Return a cached value, generating and storing it if absent.
	 *
	 * Implements stale-while-revalidate (SWR) stampede prevention. On expiry,
	 * stale data (stored at `{key}_stale` with a 2× TTL) is returned immediately
	 * while one process regenerates in the foreground. No workers are blocked
	 * waiting for fresh data in the normal case.
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
	 * Hits are detected via `wp_cache_get()`'s `$found` out-parameter, so a
	 * callback that returns a falsy value (`false`, `0`, `''`) is cached
	 * normally and not re-invoked on every call.
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
		$cached = wp_cache_get( $key, $group, false, $found );
		if ( $found ) {
			return $cached;
		}

		$stale_key   = $key . '_stale';
		$lock_key    = $key . '_lock';
		$stale_found = false;
		$stale       = wp_cache_get( $stale_key, $group, false, $stale_found );

		if ( $stale_found ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
			if ( wp_cache_add( $lock_key, 1, $group, self::LOCK_TTL ) ) {
				self::store_with_stale( $key, $stale_key, $callback, $group, $expiration, $lock_key );
			}
			return $stale;
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
		if ( wp_cache_add( $lock_key, 1, $group, self::LOCK_TTL ) ) {
			return self::store_with_stale( $key, $stale_key, $callback, $group, $expiration, $lock_key );
		}

		for ( $i = 0; $i < self::LOCK_RETRIES; $i++ ) {
			usleep( self::LOCK_WAIT_US );
			$cached = wp_cache_get( $key, $group, false, $found );
			if ( $found ) {
				return $cached;
			}
		}

		return self::store_with_stale( $key, $stale_key, $callback, $group, $expiration );
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
	private static function store_with_stale( string $key, string $stale_key, callable $callback, string $group, int $expiration, string $lock_key = '' ): mixed {
		try {
			$value            = $callback();
			$stale_expiration = $expiration > 0 ? $expiration * self::STALE_MULTIPLIER : 0;
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- expiry originates with the remember() caller.
			wp_cache_set( $key, $value, $group, $expiration );
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- stale TTL is expiration × STALE_MULTIPLIER; caller controls the base TTL.
			wp_cache_set( $stale_key, $value, $group, $stale_expiration );
			return $value;
		} finally {
			if ( '' !== $lock_key ) {
				wp_cache_delete( $lock_key, $group );
			}
		}
	}
}
