<?php
/**
 * Singleton fixture used as the parent half of the inheritance pair.
 *
 * Paired with SingletonChild to prove that a parent and a subclass each get
 * their own instance rather than sharing the first one constructed.
 *
 * @package rtCamp\WPFramework\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Fixtures;

use rtCamp\WPFramework\Contracts\Traits\Singleton;

/**
 * Class - SingletonParent
 */
class SingletonParent {
	use Singleton;

	/**
	 * Tracks constructions per concrete class, keyed by class-string.
	 *
	 * Declared on the parent so the subclass shares the tally and a leak
	 * between the two would show up here.
	 *
	 * @var array<class-string, int>
	 */
	public static array $construct_counts = [];

	protected function __construct() {
		$class = static::class;

		self::$construct_counts[ $class ] = ( self::$construct_counts[ $class ] ?? 0 ) + 1;
	}
}
