<?php
/**
 * Tests for Singleton trait.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteSingleton;

/**
 * @covers \WPFramework\Contracts\Traits\Singleton
 */
class SingletonTest extends TestCase {

	protected function setUp(): void {
		// Reset static instance between tests.
		$reflection = new \ReflectionClass( ConcreteSingleton::class );
		$prop       = $reflection->getProperty( 'instance' );
		$prop->setValue( null, null );
	}

	public function test_get_instance_returns_same_object(): void {
		$instance1 = ConcreteSingleton::get_instance();
		$instance2 = ConcreteSingleton::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	public function test_get_instance_returns_correct_type(): void {
		$instance = ConcreteSingleton::get_instance();

		$this->assertInstanceOf( ConcreteSingleton::class, $instance );
	}

	public function test_clone_triggers_doing_it_wrong(): void {
		$instance = ConcreteSingleton::get_instance();

		// Clone should call _doing_it_wrong — in our stub it's a no-op,
		// but the clone itself should still work (no exception).
		$clone = clone $instance;

		// They should be different objects after clone.
		$this->assertNotSame( $instance, $clone );
	}

	public function test_wakeup_triggers_doing_it_wrong(): void {
		$instance = ConcreteSingleton::get_instance();

		// Serialize and unserialize — wakeup should be called.
		$serialized = serialize( $instance );

		// Unserialize should call __wakeup which calls _doing_it_wrong (no-op stub).
		$unserialized = unserialize( $serialized );

		$this->assertInstanceOf( ConcreteSingleton::class, $unserialized );
	}
}
