<?php
/**
 * Singleton fixture class for SingletonTest.
 *
 * @package rtCamp\WPFramework\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Fixtures;

use rtCamp\WPFramework\Contracts\Traits\Singleton;

/**
 * Class - SingletonExample
 */
class SingletonExample {
	use Singleton;

	/**
	 * Tracks how many times the constructor ran — a true singleton runs it once.
	 */
	public static int $construct_count = 0;

	protected function __construct() {
		++self::$construct_count;
	}
}
