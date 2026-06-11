<?php
/**
 * PathTranslator tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Path;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Path\PathTranslator;

final class PathTranslatorTest extends TestCase {

	public function test_translates_prefixed_path(): void {
		$translator = new PathTranslator( [ '/var/www/html/wp-content/plugins/my-plugin' => '/Users/dev/my-plugin' ] );

		$this->assertSame(
			'/Users/dev/my-plugin/inc/Hooks.php',
			$translator->to_host( '/var/www/html/wp-content/plugins/my-plugin/inc/Hooks.php' )
		);
	}

	public function test_exact_prefix_match_returns_host_prefix(): void {
		$translator = new PathTranslator( [ '/var/www/html/app' => '/Users/dev/app' ] );

		$this->assertSame( '/Users/dev/app', $translator->to_host( '/var/www/html/app' ) );
	}

	public function test_longest_prefix_wins(): void {
		$translator = new PathTranslator(
			[
				'/var/www/html'                    => '/Users/dev/site',
				'/var/www/html/wp-content/themes' => '/Users/dev/themes',
			]
		);

		$this->assertSame(
			'/Users/dev/themes/my-theme/functions.php',
			$translator->to_host( '/var/www/html/wp-content/themes/my-theme/functions.php' )
		);
	}

	public function test_unmappable_path_returns_null_never_a_guess(): void {
		$translator = new PathTranslator( [ '/var/www/html/wp-content/plugins/my-plugin' => '/Users/dev/my-plugin' ] );

		$this->assertNull( $translator->to_host( '/wordpress/wp-includes/option.php' ) );
	}

	public function test_partial_directory_name_does_not_match(): void {
		$translator = new PathTranslator( [ '/var/www/html/app' => '/Users/dev/app' ] );

		$this->assertNull( $translator->to_host( '/var/www/html/application/file.php' ) );
	}

	public function test_null_and_empty_input_return_null(): void {
		$translator = new PathTranslator( [ '/a' => '/b' ] );

		$this->assertNull( $translator->to_host( null ) );
		$this->assertNull( $translator->to_host( '' ) );
	}

	public function test_empty_map_returns_null(): void {
		$translator = new PathTranslator( [] );

		$this->assertNull( $translator->to_host( '/var/www/html/file.php' ) );
	}
}
