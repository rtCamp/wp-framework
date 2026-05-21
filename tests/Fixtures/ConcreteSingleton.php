<?php
/**
 * Concrete class using Singleton trait for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Traits\Singleton;

class ConcreteSingleton {
	use Singleton;
}
