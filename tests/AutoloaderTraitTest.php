<?php
/**
 * Tests for AutoloaderTrait.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteAutoloader;

/**
 * @covers \WPFramework\AutoloaderTrait
 */
class AutoloaderTraitTest extends TestCase {

	public function test_require_autoloader_returns_true_for_existing_file(): void {
		// Use the actual composer autoloader as a readable file.
		$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
		$result     = ConcreteAutoloader::load( $autoloader );

		$this->assertTrue( $result );
	}

	public function test_require_autoloader_returns_false_for_missing_file(): void {
		$result = ConcreteAutoloader::load( '/nonexistent/path/autoload.php' );

		$this->assertFalse( $result );
	}

	public function test_require_autoloader_caches_result(): void {
		$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

		// First call.
		$result1 = ConcreteAutoloader::load( $autoloader );
		// Second call should use cache.
		$result2 = ConcreteAutoloader::load( $autoloader );

		$this->assertTrue( $result1 );
		$this->assertTrue( $result2 );
	}

	public function test_get_autoloader_error_message_returns_string(): void {
		$message = ConcreteAutoloader::get_error_message();

		$this->assertIsString( $message );
		$this->assertNotEmpty( $message );
	}
}
