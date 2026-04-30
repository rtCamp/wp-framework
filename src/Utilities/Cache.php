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
 * @since 1.0.0
 */
class Cache {

	use Singleton;

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
