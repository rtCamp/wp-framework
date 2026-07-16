<?php
/**
 * Singleton trait.
 *
 * Singletons are an ANTI-PATTERN. Use with caution and only when necessary.
 * In most cases, it's better to use dependency injection.
 *
 * @package rtCamp\WPFramework\Contracts\Traits
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Traits;

/**
 * Singleton trait.
 */
trait Singleton {
	/**
	 * Instances of the classes using this trait, keyed by concrete class name.
	 *
	 * A trait's `static` property is a single storage slot shared by a class and
	 * its subclasses, so a plain `static $instance` lets a parent and a child that
	 * both use this trait collide (the child would receive the parent's instance).
	 * Keying by class name gives each concrete class its own instance.
	 *
	 * @var array<class-string, static>
	 */
	private static array $instances = [];

	/**
	 * The single constructor.
	 *
	 * It's protected to prevent direct instantiation.
	 */
	protected function __construct() {
		// To be implemented by the class using the trait.
	}

	/**
	 * Get the instance of the class.
	 */
	public static function get_instance(): static {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
		}

		return self::$instances[ $class ];
	}

	/**
	 * Prevent the class from being cloned.
	 */
	final public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'The %s class should not be cloned.', 'wp-framework' ),
				esc_html( static::class ),
			),
			'1.0.0'
		);
	}

	/**
	 * Prevent the class from being deserialized.
	 */
	final public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'De-serializing instances of %s is not allowed.', 'wp-framework' ),
				esc_html( static::class ),
			),
			'1.0.0'
		);
	}
}
