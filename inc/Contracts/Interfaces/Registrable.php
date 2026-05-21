<?php
/**
 * Interface for Registrable classes.
 *
 * Registrable classes are those that register hooks (actions/filters) with WordPress.
 *
 * @package WPFramework\Contracts\Interfaces
 */

declare( strict_types = 1 );

namespace WPFramework\Contracts\Interfaces;

/**
 * Interface - Registrable
 */
interface Registrable {
	/**
	 * Registers class methods to WordPress.
	 *
	 * WordPress actions/filters should be included here.
	 */
	public function register_hooks(): void;
}
