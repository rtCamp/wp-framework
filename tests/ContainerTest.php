<?php
/**
 * Tests for Container class.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Container;

/**
 * @covers \WPFramework\Container
 */
class ContainerTest extends TestCase {

	private Container $container;

	protected function setUp(): void {
		$this->container = new Container();
	}

	public function test_set_and_get_returns_instance(): void {
		$obj = new \stdClass();
		$this->container->set( \stdClass::class, $obj );

		$this->assertSame( $obj, $this->container->get( \stdClass::class ) );
	}

	public function test_has_returns_true_for_registered_service(): void {
		$this->container->set( \stdClass::class, new \stdClass() );

		$this->assertTrue( $this->container->has( \stdClass::class ) );
	}

	public function test_has_returns_false_for_unregistered_service(): void {
		$this->assertFalse( $this->container->has( \stdClass::class ) );
	}

	public function test_get_throws_runtime_exception_for_unregistered_service(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Instance "stdClass" is not registered in the container.' );

		$this->container->get( \stdClass::class );
	}

	public function test_set_overwrites_existing_instance(): void {
		$first  = new \stdClass();
		$second = new \stdClass();

		$this->container->set( \stdClass::class, $first );
		$this->container->set( \stdClass::class, $second );

		$this->assertSame( $second, $this->container->get( \stdClass::class ) );
	}

	public function test_multiple_services_are_independent(): void {
		$obj1 = new \stdClass();
		$obj2 = new class() {};

		$this->container->set( \stdClass::class, $obj1 );
		$this->container->set( $obj2::class, $obj2 );

		$this->assertSame( $obj1, $this->container->get( \stdClass::class ) );
		$this->assertSame( $obj2, $this->container->get( $obj2::class ) );
	}
}
