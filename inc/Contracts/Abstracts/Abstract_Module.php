<?php
/**
 * Abstract Module class.
 *
 * A module groups related Registrable classes together, acting as an
 * intermediary between the plugin's Main class and individual services.
 *
 * @package WPFramework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace WPFramework\Contracts\Abstracts;

use WPFramework\Contracts\Interfaces\Registrable;

/**
 * Class - Abstract_Module
 */
abstract class Abstract_Module implements Registrable {

	/**
	 * Get the Registrable class-strings this module manages.
	 *
	 * @return class-string<Registrable>[]
	 */
	abstract protected function get_classes(): array;

	/**
	 * {@inheritDoc}
	 *
	 * Instantiates each class from get_classes() and calls register_hooks().
	 */
	public function register_hooks(): void {
		foreach ( $this->get_classes() as $class_name ) {
			$instance = new $class_name();
			$instance->register_hooks();
		}
	}
}
