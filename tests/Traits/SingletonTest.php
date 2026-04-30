<?php
/**
 * Tests for the Singleton trait.
 *
 * @package RtCamp\WPToolkit\Tests\Traits
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Tests\Traits;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use RtCamp\WPToolkit\Traits\Singleton;

/**
 * Local fixture — exercises the trait without polluting the public surface.
 *
 * @since   1.0.0
 */
class ConcreteClass {
	use Singleton;

	/**
	 * Empty initialisation stub — required by the Singleton trait's setup() contract.
	 *
	 * @return void
	 */
	public function setup(): void {}
}

/**
 * Subclass fixture — used to verify each class receives its own instance.
 *
 * @since   1.0.0
 */
class ChildClass extends ConcreteClass {}

/**
 * Singleton trait tests.
 *
 * Verifies the three runtime guarantees the trait promises:
 *   - get_instance() returns the same instance on repeat calls,
 *   - cloning a singleton throws RuntimeException,
 *   - subclasses receive their own instance via late static binding.
 *
 * @since   1.0.0
 */
class SingletonTest extends TestCase {

	/**
	 * Repeat calls to get_instance() must return the identical object.
	 */
	public function test_returns_same_instance_on_repeat_calls(): void {
		$first  = ConcreteClass::get_instance();
		$second = ConcreteClass::get_instance();

		$this->assertSame( $first, $second );
	}

	/**
	 * Cloning a singleton must throw RuntimeException.
	 */
	public function test_clone_throws_runtime_exception(): void {
		$this->expectException( RuntimeException::class );

		clone ConcreteClass::get_instance();
	}

	/**
	 * A subclass must get its own instance, not share its parent's.
	 */
	public function test_subclass_receives_its_own_instance(): void {
		$parent = ConcreteClass::get_instance();
		$child  = ChildClass::get_instance();

		$this->assertNotSame( $parent, $child );
		$this->assertInstanceOf( ConcreteClass::class, $parent );
		$this->assertInstanceOf( ChildClass::class, $child );
		$this->assertSame( $child, ChildClass::get_instance() );
	}
}
