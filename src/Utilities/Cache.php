<?php
/**
 * Cache utility.
 *
 * @package RtCamp\WPToolkit\Utilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Utilities;

use RtCamp\WPToolkit\Traits\Singleton;

/**
 * Thin, typed wrapper over WordPress's object-cache functions.
 *
 * Centralises calls to `wp_cache_get`, `wp_cache_set`, `wp_cache_delete`, and
 * `wp_cache_flush_group` so consumers have a single typed API and a single
 * place to layer cross-cutting behaviour later (logging, telemetry, fallbacks).
 *
 * `wp_cache_flush_group()` was added in WordPress 6.1; the wrapper's
 * `flush_group()` checks for it and gracefully returns `false` on older
 * cores so consumers don't have to.
 *
 * `remember()` adds stale-while-revalidate (SWR) stampede prevention: on
 * expiry, stale data is served immediately while one process regenerates.
 *
 * @since 1.0.0
 */
class Cache {

	use Singleton;

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
	 * Setup hook — required stub for the Singleton boot sequence.
	 *
	 * @return void
	 */
	public function setup(): void {}

	/**
	 * Get a value from cache.
	 *
	 * Returns `mixed` because object-cache entries can hold any serialisable
	 * value (mirrors WordPress's own `wp_cache_get()` signature).
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Defaults to the global group.
	 * @param bool   $force Bypass any in-process memoisation layer (relevant
	 *                      for persistent backends like Memcached).
	 *
	 * @return mixed Stored value, or `false` if missing / expired.
	 */
	public function get( string $key, string $group = '', bool $force = false ): mixed {
		return wp_cache_get( $key, $group, $force );
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
	public function set( string $key, mixed $value, string $group = '', int $expiration = 0 ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- Cache::set is a thin pass-through wrapper; the expiry originates with the caller. The VIP rule's "≥300s" guidance is enforced at call sites, not inside the wrapper.
		return wp_cache_set( $key, $value, $group, $expiration );
	}

	/**
	 * Delete a single key from cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Defaults to the global group.
	 *
	 * @return bool True if the entry was deleted, false otherwise.
	 */
	public function delete( string $key, string $group = '' ): bool {
		return wp_cache_delete( $key, $group );
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
	 *                        directly as a last resort.
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
	public function remember( string $key, callable $callback, string $group = '', int $expiration = 0 ): mixed {
		$cached = wp_cache_get( $key, $group );
		if ( false !== $cached ) {
			return $cached;
		}

		$stale_key = $key . '_stale';
		$lock_key  = $key . '_lock';
		$stale     = wp_cache_get( $stale_key, $group );

		if ( false !== $stale ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
			if ( wp_cache_add( $lock_key, 1, $group, self::LOCK_TTL ) ) {
				$this->store_with_stale( $key, $stale_key, $lock_key, $callback, $group, $expiration );
			}
			return $stale;
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
		if ( wp_cache_add( $lock_key, 1, $group, self::LOCK_TTL ) ) {
			return $this->store_with_stale( $key, $stale_key, $lock_key, $callback, $group, $expiration );
		}

		for ( $i = 0; $i < self::LOCK_RETRIES; $i++ ) {
			usleep( self::LOCK_WAIT_US );
			$cached = wp_cache_get( $key, $group );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		return $this->store_with_stale( $key, $stale_key, $lock_key, $callback, $group, $expiration );
	}

	/**
	 * Invoke `$callback`, write both cache entries, release the lock, and return the value.
	 *
	 * Returns `mixed` because the callback can return any serialisable type.
	 *
	 * @param string   $key        Fresh cache key.
	 * @param string   $stale_key  Stale cache key (grace-period copy).
	 * @param string   $lock_key   Lock key to delete after regeneration.
	 * @param callable $callback   Invoked to produce the fresh value.
	 * @param string   $group      Cache group.
	 * @param int      $expiration TTL for the fresh entry; stale TTL = expiration × {@see STALE_MULTIPLIER}.
	 *
	 * @return mixed The freshly-generated value.
	 */
	private function store_with_stale( string $key, string $stale_key, string $lock_key, callable $callback, string $group, int $expiration ): mixed {
		$value            = $callback();
		$stale_expiration = $expiration > 0 ? $expiration * self::STALE_MULTIPLIER : 0;
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- expiry originates with the remember() caller.
		wp_cache_set( $key, $value, $group, $expiration );
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- stale TTL is expiration × STALE_MULTIPLIER; caller controls the base TTL.
		wp_cache_set( $stale_key, $value, $group, $stale_expiration );
		wp_cache_delete( $lock_key, $group );
		return $value;
	}

	/**
	 * Flush an entire cache group. Requires WordPress 6.1+.
	 *
	 * @param string $group Cache group to flush.
	 *
	 * @return bool True on success; false if the function is unavailable or the flush itself failed.
	 */
	public function flush_group( string $group ): bool {
		if ( ! function_exists( 'wp_cache_flush_group' ) ) {
			return false;
		}

		return wp_cache_flush_group( $group );
	}
}
