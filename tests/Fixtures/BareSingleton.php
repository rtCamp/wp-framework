<?php
/**
 * Singleton fixture that does NOT override the trait constructor.
 *
 * Exercises the trait's default protected __construct (SingletonExample
 * overrides it, so the default body would otherwise never run).
 *
 * @package rtCamp\WPFramework\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Fixtures;

use rtCamp\WPFramework\Contracts\Traits\Singleton;

/**
 * Class - BareSingleton
 */
class BareSingleton {
	use Singleton;
}
