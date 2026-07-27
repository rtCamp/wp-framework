<?php
/**
 * Singleton trait tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use rtCamp\WPFramework\Tests\Fixtures\BareSingleton;
use rtCamp\WPFramework\Tests\Fixtures\SingletonChild;
use rtCamp\WPFramework\Tests\Fixtures\SingletonExample;
use rtCamp\WPFramework\Tests\Fixtures\SingletonParent;
use rtCamp\WPFramework\Tests\TestCase;

final class SingletonTest extends TestCase {

	public function test_get_instance_returns_the_same_instance_each_call(): void {
		$first  = SingletonExample::get_instance();
		$second = SingletonExample::get_instance();

		$this->assertSame( $first, $second );
	}

	public function test_get_instance_returns_concrete_class(): void {
		$instance = SingletonExample::get_instance();

		$this->assertInstanceOf( SingletonExample::class, $instance );
	}

	public function test_constructor_runs_only_once_regardless_of_call_count(): void {
		// Multiple calls to get_instance() must never construct more than once.
		// (Order-independent: tests run in random order, but the count is
		// bounded at 1 for the lifetime of the process.)
		SingletonExample::get_instance();
		SingletonExample::get_instance();
		SingletonExample::get_instance();

		$this->assertSame( 1, SingletonExample::$construct_count );
	}

	public function test_cloning_is_disallowed(): void {
		$this->setExpectedIncorrectUsage( '__clone' );

		$instance = SingletonExample::get_instance();
		clone $instance;
	}

	public function test_deserializing_is_disallowed(): void {
		$this->setExpectedIncorrectUsage( '__wakeup' );

		$instance = SingletonExample::get_instance();
		unserialize( serialize( $instance ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	public function test_default_constructor_is_used_when_not_overridden(): void {
		$this->assertInstanceOf( BareSingleton::class, BareSingleton::get_instance() );
	}

	public function test_parent_and_subclass_get_separate_instances(): void {
		// Regression: the instance store is keyed by class-string, so a subclass
		// that inherits get_instance() must not be handed the parent's object
		// (or vice versa, depending on which one is resolved first).
		$parent = SingletonParent::get_instance();
		$child  = SingletonChild::get_instance();

		$this->assertNotSame( $parent, $child );
		$this->assertInstanceOf( SingletonParent::class, $parent );
		$this->assertInstanceOf( SingletonChild::class, $child );

		// The subclass must resolve to its own concrete type, not the parent's.
		$this->assertNotInstanceOf( SingletonChild::class, $parent );
	}

	public function test_parent_and_subclass_each_construct_once(): void {
		SingletonParent::get_instance();
		SingletonParent::get_instance();
		SingletonChild::get_instance();
		SingletonChild::get_instance();

		$this->assertSame( 1, SingletonParent::$construct_counts[ SingletonParent::class ] ?? 0 );
		$this->assertSame( 1, SingletonParent::$construct_counts[ SingletonChild::class ] ?? 0 );
	}

	public function test_subclass_instance_is_stable_across_calls(): void {
		$this->assertSame( SingletonChild::get_instance(), SingletonChild::get_instance() );
	}
}
