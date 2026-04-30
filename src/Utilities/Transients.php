<?php
/**
 * Transients utility.
 *
 * @package RtCamp\WPToolkit\Utilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Utilities;

/**
 * Wraps WordPress transients with a per-instance prefix.
 *
 * Two modules using the same logical key won't collide because each
 * module instantiates its own Transients with a different prefix.
 *
 * @since 1.0.0
 */
class Transients {

	/**
	 * Construct a Transients wrapper bound to a specific key prefix.
	 *
	 * @param string $prefix Per-instance namespace prepended to every key.
	 */
	public function __construct(
		private readonly string $prefix,
	) {}

	/**
	 * Get a transient value.
	 *
	 * Returns `mixed` because transients can hold any serialisable value
	 * (mirrors WordPress's own `get_transient()` signature).
	 *
	 * @param string $key Logical key (will be prefixed before lookup).
	 * 
	 * @return mixed Stored value, or `false` if missing or expired.
	 */
	public function get( string $key ): mixed {
		return get_transient( $this->prefixed( $key ) );
	}

	/**
	 * Set a transient with optional expiration.
	 *
	 * @param string $key        Logical key (will be prefixed before write).
	 * @param mixed  $value      Value to store. Must be serialisable.
	 * @param int    $expiration TTL in seconds. Defaults to one day.
	 * 
	 * @return bool  True on success, false on failure.
	 */
	public function set( string $key, mixed $value, int $expiration = DAY_IN_SECONDS ): bool {
		return set_transient( $this->prefixed( $key ), $value, $expiration );
	}

	/**
	 * Delete a transient.
	 *
	 * @param string $key Logical key (will be prefixed before delete).
	 * 
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete( string $key ): bool {
		return delete_transient( $this->prefixed( $key ) );
	}

	/**
	 * Prefix a logical key with this instance's namespace.
	 *
	 * @param string $key Logical key supplied by the caller.
	 * 
	 * @return string Fully prefixed key passed to WordPress's transient API.
	 */
	private function prefixed( string $key ): string {
		return $this->prefix . '_' . $key;
	}
}
