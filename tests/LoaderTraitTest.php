<?php
/**
 * Tests for the Loader trait.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\LoaderHost;
use WPFramework\Tests\Fixtures\RegistrableOnly;
use WPFramework\Tests\Fixtures\ShareableOnly;
use WPFramework\Tests\Fixtures\RegistrableAndShareable;
use WPFramework\Tests\Fixtures\PlainClass;

/**
 * @covers \WPFramework\Contracts\Traits\Loader
 */
class LoaderTraitTest extends TestCase {

	private LoaderHost $host;

	protected function setUp(): void {
		$this->host = new LoaderHost();

		// Reset static flags.
		RegistrableOnly::$hooks_registered        = false;
		RegistrableAndShareable::$hooks_registered = false;
	}

	public function test_registrable_class_has_hooks_registered(): void {
		$this->host->do_load( [ RegistrableOnly::class ] );

		$this->assertTrue( RegistrableOnly::$hooks_registered );
	}

	public function test_registrable_class_is_not_shared(): void {
		$this->host->do_load( [ RegistrableOnly::class ] );

		$this->expectException( \RuntimeException::class );
		$this->host->get_shared( RegistrableOnly::class );
	}

	public function test_shareable_class_is_cached_in_container(): void {
		$this->host->do_load( [ ShareableOnly::class ] );

		$instance = $this->host->get_shared( ShareableOnly::class );
		$this->assertInstanceOf( ShareableOnly::class, $instance );
		$this->assertSame( 'shared', $instance->value );
	}

	public function test_shareable_class_returns_same_instance(): void {
		$this->host->do_load( [ ShareableOnly::class ] );

		$first  = $this->host->get_shared( ShareableOnly::class );
		$second = $this->host->get_shared( ShareableOnly::class );
		$this->assertSame( $first, $second );
	}

	public function test_registrable_and_shareable_class_does_both(): void {
		$this->host->do_load( [ RegistrableAndShareable::class ] );

		$this->assertTrue( RegistrableAndShareable::$hooks_registered );

		$instance = $this->host->get_shared( RegistrableAndShareable::class );
		$this->assertInstanceOf( RegistrableAndShareable::class, $instance );
	}

	public function test_plain_class_is_not_registered_or_shared(): void {
		$this->host->do_load( [ PlainClass::class ] );

		$this->expectException( \RuntimeException::class );
		$this->host->get_shared( PlainClass::class );
	}

	public function test_load_handles_multiple_classes(): void {
		$this->host->do_load( [
			RegistrableOnly::class,
			ShareableOnly::class,
			RegistrableAndShareable::class,
			PlainClass::class,
		] );

		$this->assertTrue( RegistrableOnly::$hooks_registered );
		$this->assertTrue( RegistrableAndShareable::$hooks_registered );
		$this->assertInstanceOf( ShareableOnly::class, $this->host->get_shared( ShareableOnly::class ) );
		$this->assertInstanceOf( RegistrableAndShareable::class, $this->host->get_shared( RegistrableAndShareable::class ) );
	}

	public function test_load_with_empty_array_does_not_throw(): void {
		$this->host->do_load( [] );

		$this->expectException( \RuntimeException::class );
		$this->host->get_shared( PlainClass::class );
	}

	public function test_get_shared_throws_for_unknown_class(): void {
		$this->host->do_load( [ ShareableOnly::class ] );

		$this->expectException( \RuntimeException::class );
		$this->host->get_shared( \stdClass::class );
	}
}
