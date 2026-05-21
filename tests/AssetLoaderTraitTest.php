<?php
/**
 * Tests for AssetLoaderTrait.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteAssetLoader;

/**
 * @covers \WPFramework\AssetLoaderTrait
 */
class AssetLoaderTraitTest extends TestCase {

	private string $fixtures_dir;
	private ConcreteAssetLoader $loader;

	protected function setUp(): void {
		global $wp_scripts, $wp_styles;
		$wp_scripts = [];
		$wp_styles  = [];

		$this->fixtures_dir = __DIR__ . '/Fixtures/assets';

		// Ensure fixture directory exists.
		if ( ! is_dir( $this->fixtures_dir ) ) {
			mkdir( $this->fixtures_dir, 0755, true );
		}

		$this->loader = new ConcreteAssetLoader(
			$this->fixtures_dir, // plugin_dir (no trailing slash)
			'http://example.com/plugins/test', // plugin_url (no trailing slash)
			'/' // assets_dir
		);
	}

	protected function tearDown(): void {
		// Clean up fixture files.
		$files = glob( $this->fixtures_dir . '/*.asset.php' );
		if ( $files ) {
			foreach ( $files as $file ) {
				unlink( $file );
			}
		}
	}

	public function test_register_script_success(): void {
		global $wp_scripts;

		// Create asset file.
		$asset_content = "<?php\nreturn ['dependencies' => ['jquery'], 'version' => '1.0.0'];";
		file_put_contents( $this->fixtures_dir . '/app.asset.php', $asset_content );

		$result = $this->loader->do_register_script( 'test-script', 'app' );

		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'test-script', $wp_scripts );
		$this->assertSame( 'http://example.com/plugins/test/app.js', $wp_scripts['test-script']['src'] );
		$this->assertSame( [ 'jquery' ], $wp_scripts['test-script']['deps'] );
		$this->assertSame( '1.0.0', $wp_scripts['test-script']['ver'] );
	}

	public function test_register_script_missing_asset_file(): void {
		$result = $this->loader->do_register_script( 'missing-script', 'nonexistent' );

		$this->assertFalse( $result );
	}

	public function test_register_script_with_custom_deps(): void {
		global $wp_scripts;

		$asset_content = "<?php\nreturn ['dependencies' => ['jquery'], 'version' => '2.0.0'];";
		file_put_contents( $this->fixtures_dir . '/custom.asset.php', $asset_content );

		$result = $this->loader->do_register_script( 'custom-script', 'custom', [ 'wp-element' ] );

		$this->assertTrue( $result );
		$this->assertSame( [ 'wp-element' ], $wp_scripts['custom-script']['deps'] );
	}

	public function test_register_script_with_custom_version(): void {
		global $wp_scripts;

		$asset_content = "<?php\nreturn ['dependencies' => [], 'version' => '2.0.0'];";
		file_put_contents( $this->fixtures_dir . '/versioned.asset.php', $asset_content );

		$result = $this->loader->do_register_script( 'versioned-script', 'versioned', [], '9.9.9' );

		$this->assertTrue( $result );
		$this->assertSame( '9.9.9', $wp_scripts['versioned-script']['ver'] );
	}

	public function test_register_style_success(): void {
		global $wp_styles;

		$asset_content = "<?php\nreturn ['dependencies' => [], 'version' => '1.0.0'];";
		file_put_contents( $this->fixtures_dir . '/style.asset.php', $asset_content );

		$result = $this->loader->do_register_style( 'test-style', 'style' );

		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'test-style', $wp_styles );
		$this->assertSame( 'http://example.com/plugins/test/style.css', $wp_styles['test-style']['src'] );
		$this->assertSame( '1.0.0', $wp_styles['test-style']['ver'] );
		$this->assertSame( 'all', $wp_styles['test-style']['media'] );
	}

	public function test_register_style_missing_asset_file(): void {
		$result = $this->loader->do_register_style( 'missing-style', 'nonexistent' );

		$this->assertFalse( $result );
	}

	public function test_register_style_with_custom_media(): void {
		global $wp_styles;

		$asset_content = "<?php\nreturn ['dependencies' => [], 'version' => '1.0.0'];";
		file_put_contents( $this->fixtures_dir . '/print.asset.php', $asset_content );

		$result = $this->loader->do_register_style( 'print-style', 'print', [], null, 'print' );

		$this->assertTrue( $result );
		$this->assertSame( 'print', $wp_styles['print-style']['media'] );
	}

	public function test_get_asset_file_returns_null_for_invalid_format(): void {
		// Create an asset file that returns a non-array.
		$asset_content = "<?php\nreturn 'not an array';";
		file_put_contents( $this->fixtures_dir . '/invalid.asset.php', $asset_content );

		$result = $this->loader->do_register_script( 'invalid-script', 'invalid' );

		$this->assertFalse( $result );
	}

	public function test_get_asset_file_adds_filemtime_when_version_missing(): void {
		global $wp_scripts;

		$asset_content = "<?php\nreturn ['dependencies' => []];";
		file_put_contents( $this->fixtures_dir . '/noversion.asset.php', $asset_content );

		$result = $this->loader->do_register_script( 'noversion-script', 'noversion' );

		$this->assertTrue( $result );
		// Version should be a timestamp (int cast to string or int).
		$this->assertNotEmpty( $wp_scripts['noversion-script']['ver'] );
	}

	public function test_register_block_manifest_missing_file(): void {
		// Should not throw — just calls _doing_it_wrong (no-op stub).
		$this->loader->do_register_block_manifest( 'build/blocks', 'build/blocks-manifest.php' );

		// If we get here without error, test passes.
		$this->assertTrue( true );
	}

	public function test_register_block_manifest_with_existing_file(): void {
		$manifest = $this->fixtures_dir . '/blocks-manifest.php';
		file_put_contents( $manifest, "<?php\nreturn [];" );

		$loader = new ConcreteAssetLoader(
			$this->fixtures_dir,
			'http://example.com/plugins/test',
			'/'
		);

		$loader->do_register_block_manifest( '', 'blocks-manifest.php' );

		// Clean up.
		unlink( $manifest );

		$this->assertTrue( true );
	}
}
