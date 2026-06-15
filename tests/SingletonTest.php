<?php
/**
 * Singleton trait tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use rtCamp\WPFramework\Tests\Fixtures\BareSingleton;
use rtCamp\WPFramework\Tests\Fixtures\SingletonExample;
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
}
