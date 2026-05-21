<?php
/**
 * Loader trait.
 *
 * Loads a list of classes: instantiates each, registers hooks for any
 * Registrable, and caches any Shareable instance in a per-host Container.
 * Plain classes (neither Registrable nor Shareable) are just instantiated.
 *
 * @package rtCamp\WPFramework\Contracts\Traits
 * @since 0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Traits;

use rtCamp\WPFramework\Container;
use rtCamp\WPFramework\Contracts\Interfaces\Registrable;
use rtCamp\WPFramework\Contracts\Interfaces\Shareable;

/**
 * Loader trait.
 */
trait Loader {
	/**
	 * Shared instances populated during load.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Instantiate each class; register hooks if Registrable; cache if Shareable.
	 *
	 * The Registrable and Shareable checks are independent — a class may be
	 * both, in which case its hooks are registered and its instance is cached.
	 *
	 * @param class-string[] $classes Classes to load.
	 */
	protected function load( array $classes ): void {
		$this->container = new Container();

		foreach ( $classes as $class_name ) {
			$instance = new $class_name();

			if ( $instance instanceof Registrable ) {
				$instance->register_hooks();
			}

			if ( $instance instanceof Shareable ) {
				$this->container->set( $class_name, $instance );
			}
		}
	}

	/**
	 * Fetch a shared instance populated during load.
	 *
	 * @template T of object
	 *
	 * @param string $id Class name.
	 *
	 * @return T
	 *
	 * @throws \RuntimeException If the class was not registered as Shareable.
	 */
	public function get_shared( string $id ): object {
		/** 
		 * Instance of the requested class.
		 * */
		$instance = $this->container->get( $id );

		return $instance;
	}
}
