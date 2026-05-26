<?php
/**
 * Loader trait tests.
 *
 * Verifies the load() contract:
 *   - Plain Registrable → register_hooks() called
 *   - ConditionallyRegistrable + can_register()=true → register_hooks() called
 *   - ConditionallyRegistrable + can_register()=false → register_hooks() skipped
 *   - Shareable → instance cached, retrievable via get_shared()
 *   - A class that is both Shareable and Registrable gets both treatments
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use rtCamp\WPFramework\Tests\Fixtures\ConditionalAllowed;
use rtCamp\WPFramework\Tests\Fixtures\ConditionalDenied;
use rtCamp\WPFramework\Tests\Fixtures\LoaderRunner;
use rtCamp\WPFramework\Tests\Fixtures\PlainRegistrable;
use rtCamp\WPFramework\Tests\Fixtures\ShareableRegistrable;

final class LoaderTest extends TestCase {

	protected function setUp(): void {
		PlainRegistrable::$registered      = false;
		ConditionalAllowed::$registered    = false;
		ConditionalDenied::$registered     = false;
		ShareableRegistrable::$registered  = false;
	}

	public function test_plain_registrable_has_hooks_registered(): void {
		( new LoaderRunner() )->run( [ PlainRegistrable::class ] );

		$this->assertTrue( PlainRegistrable::$registered );
	}

	public function test_conditional_registrable_is_registered_when_can_register_is_true(): void {
		( new LoaderRunner() )->run( [ ConditionalAllowed::class ] );

		$this->assertTrue( ConditionalAllowed::$registered );
	}

	public function test_conditional_registrable_is_skipped_when_can_register_is_false(): void {
		( new LoaderRunner() )->run( [ ConditionalDenied::class ] );

		$this->assertFalse( ConditionalDenied::$registered );
	}

	public function test_shareable_instance_is_retrievable(): void {
		$loader = new LoaderRunner();
		$loader->run( [ ShareableRegistrable::class ] );

		$this->assertInstanceOf(
			ShareableRegistrable::class,
			$loader->get_shared( ShareableRegistrable::class )
		);
	}

	public function test_class_that_is_both_shareable_and_registrable_gets_both_treatments(): void {
		$loader = new LoaderRunner();
		$loader->run( [ ShareableRegistrable::class ] );

		$this->assertTrue( ShareableRegistrable::$registered );
		$this->assertTrue( $loader->get_shared( ShareableRegistrable::class ) instanceof ShareableRegistrable );
	}

	public function test_get_shared_throws_for_unregistered_class(): void {
		$loader = new LoaderRunner();
		$loader->run( [ PlainRegistrable::class ] );

		$this->expectException( RuntimeException::class );
		$loader->get_shared( PlainRegistrable::class );
	}
}
