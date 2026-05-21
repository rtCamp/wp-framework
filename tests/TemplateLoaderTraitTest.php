<?php
/**
 * Tests for TemplateLoaderTrait.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteTemplateLoader;

/**
 * @covers \WPFramework\TemplateLoaderTrait
 */
class TemplateLoaderTraitTest extends TestCase {

	private string $fixtures_dir;
	private string $theme_dir;
	private ConcreteTemplateLoader $loader;

	protected function setUp(): void {
		global $wp_is_child_theme, $wp_template_dir, $wp_stylesheet_dir;

		$this->fixtures_dir = __DIR__ . '/Fixtures/templates';
		$this->theme_dir    = __DIR__ . '/Fixtures/theme-templates';

		// Ensure fixture dirs exist.
		if ( ! is_dir( $this->fixtures_dir ) ) {
			mkdir( $this->fixtures_dir, 0755, true );
		}
		if ( ! is_dir( $this->theme_dir ) ) {
			mkdir( $this->theme_dir, 0755, true );
		}

		$wp_is_child_theme = false;
		$wp_template_dir   = $this->theme_dir;
		$wp_stylesheet_dir = $this->theme_dir;

		$this->loader = new ConcreteTemplateLoader( $this->fixtures_dir );

		// Clear the static template cache.
		ConcreteTemplateLoader::clear_cache();
	}

	protected function tearDown(): void {
		// Clean up fixture files.
		$this->remove_dir( $this->fixtures_dir );
		$this->remove_dir( $this->theme_dir );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = glob( $dir . '/*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					$this->remove_dir( $file );
				} else {
					unlink( $file );
				}
			}
		}
		rmdir( $dir );
	}

	public function test_get_template_part_returns_path_when_found(): void {
		file_put_contents( $this->fixtures_dir . '/content.php', '<?php // template' );

		$result = $this->loader->get_template_part( 'content' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'content.php', $result );
	}

	public function test_get_template_part_returns_false_when_not_found(): void {
		$result = $this->loader->get_template_part( 'nonexistent' );

		$this->assertFalse( $result );
	}

	public function test_get_template_part_with_name_variation(): void {
		file_put_contents( $this->fixtures_dir . '/content-card.php', '<?php // card template' );

		$result = $this->loader->get_template_part( 'content', 'card' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'content-card.php', $result );
	}

	public function test_get_template_part_falls_back_to_slug_without_name(): void {
		file_put_contents( $this->fixtures_dir . '/content.php', '<?php // base template' );

		// Request content-missing but only content.php exists.
		$result = $this->loader->get_template_part( 'content', 'missing' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'content.php', $result );
	}

	public function test_theme_overrides_plugin_template(): void {
		global $wp_template_dir;

		// Create theme template dir.
		$theme_template_dir = $this->theme_dir . '/test-plugin';
		if ( ! is_dir( $theme_template_dir ) ) {
			mkdir( $theme_template_dir, 0755, true );
		}

		file_put_contents( $this->fixtures_dir . '/content.php', '<?php // plugin template' );
		file_put_contents( $theme_template_dir . '/content.php', '<?php // theme template' );

		$result = $this->loader->get_template_part( 'content' );

		$this->assertIsString( $result );
		// Theme should win over plugin.
		$this->assertStringContainsString( 'theme-templates/test-plugin/content.php', $result );
	}

	public function test_get_template_part_with_load_true(): void {
		file_put_contents( $this->fixtures_dir . '/loaded.php', '<?php // loaded template' );

		// load_template is a no-op stub so no output expected.
		$result = $this->loader->get_template_part( 'loaded', null, [], true );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'loaded.php', $result );
	}

	public function test_template_cache_returns_same_path(): void {
		file_put_contents( $this->fixtures_dir . '/cached.php', '<?php // cached' );

		$result1 = $this->loader->get_template_part( 'cached' );
		$result2 = $this->loader->get_template_part( 'cached' );

		$this->assertSame( $result1, $result2 );
	}

	public function test_empty_templates_returns_false(): void {
		// Force empty templates by using a slug that sanitize_file_name would empty.
		ConcreteTemplateLoader::clear_cache();

		// The method handles this: empty array after filter returns false.
		$result = $this->loader->get_template_part( '' );

		$this->assertFalse( $result );
	}
}
