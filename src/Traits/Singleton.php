<?php
/**
 * Singleton trait.
 *
 * @package RtCamp\WPToolkit\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Traits;

/**
 * Provides a standard get_instance() pattern. The constructor is intentionally
 * empty — all initialisation happens inside setup() on the consuming class.
 *
 * Uses late static binding (static::) so subclasses receive their own instance.
 *
 * @since 1.0.0
 */
trait Singleton {

	/**
	 * Return the singleton instance, instantiating it on first access.
	 *
	 * @return static Singleton instance of the class.
	 */
	final public static function get_instance(): static {
		/**
		 * Per-class instance map. An array keyed by class name is required
		 * because a trait-level static property would be shared across the
		 * inheritance chain — Parent::get_instance() and Child::get_instance()
		 * would return the same object. Keying by static::class ensures each
		 * class in the hierarchy gets its own isolated instance.
		 *
		 * @var array<class-string, static>
		 */
		static $instances = array();

		$class = static::class;

		if ( ! isset( $instances[ $class ] ) ) {
			$instances[ $class ] = new static();
		}

		return $instances[ $class ];
	}

	/**
	 * Constructor — intentionally empty. Use setup() for initialisation.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning to preserve the single-instance contract.
	 *
	 * @throws \RuntimeException Always.
	 */
	final public function __clone(): void {
		throw new \RuntimeException( 'Singleton instances cannot be cloned.' );
	}
}
