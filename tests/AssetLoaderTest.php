<?php
/**
 * Asset loader tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use rtCamp\WPFramework\AssetLoader;

final class AssetLoaderTest extends TestCase {

	private string $temp_dir;

	private string $base_url = 'https://example.test/theme';

	private AssetLoader $loader;

	protected function setUp(): void {
		$this->temp_dir = sys_get_temp_dir() . '/wp-framework-asset-loader-' . str_replace( '.', '', uniqid( '', true ) );
		mkdir( $this->temp_dir, 0777, true );

		$GLOBALS['wp_framework_test_doing_it_wrong']                = [];
		$GLOBALS['wp_framework_test_registered_styles']             = [];
		$GLOBALS['wp_framework_test_registered_scripts']            = [];
		$GLOBALS['wp_framework_test_registered_modules']            = [];
		$GLOBALS['wp_framework_test_registered_block_collections']  = [];

		$this->loader = new AssetLoader( $this->temp_dir, $this->base_url, 'assets/build' );
	}

	protected function tearDown(): void {
		if ( ! is_dir( $this->temp_dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->temp_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}

		rmdir( $this->temp_dir );
	}

	public function test_register_script_builds_src_and_reads_manifest(): void {
		$this->write_asset( 'assets/build/js/app.js', 'console.log(1);' );
		$this->write_asset(
			'assets/build/js/app.asset.php',
			'<?php return ["dependencies" => ["wp-dom"], "version" => "v1"];'
		);

		$this->assertTrue( $this->loader->register_script( 'app', 'js/app' ) );

		$this->assertSame(
			[
				'src'       => 'https://example.test/theme/assets/build/js/app.js',
				'deps'      => [ 'wp-dom' ],
				'ver'       => 'v1',
				'in_footer' => true,
			],
			$GLOBALS['wp_framework_test_registered_scripts']['app']
		);
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_register_style_builds_src_and_reads_manifest(): void {
		$this->write_asset( 'assets/build/css/app.css', '.a{}' );
		$this->write_asset(
			'assets/build/css/app.asset.php',
			'<?php return ["dependencies" => ["wp-components"], "version" => "v2"];'
		);

		$this->assertTrue( $this->loader->register_style( 'app', 'css/app' ) );

		$this->assertSame(
			[
				'src'   => 'https://example.test/theme/assets/build/css/app.css',
				'deps'  => [ 'wp-components' ],
				'ver'   => 'v2',
				'media' => 'all',
			],
			$GLOBALS['wp_framework_test_registered_styles']['app']
		);
	}

	public function test_register_script_module_maps_dependencies_to_id_form(): void {
		$this->write_asset( 'assets/build/js/module.js', 'export default 1;' );
		$this->write_asset(
			'assets/build/js/module.asset.php',
			'<?php return ["dependencies" => ["@wordpress/interactivity"], "version" => "v3"];'
		);

		$this->assertTrue( $this->loader->register_script_module( '@my/module', 'js/module' ) );

		$this->assertSame(
			[
				'src'  => 'https://example.test/theme/assets/build/js/module.js',
				'deps' => [ [ 'id' => '@wordpress/interactivity' ] ],
				'ver'  => 'v3',
			],
			$GLOBALS['wp_framework_test_registered_modules']['@my/module']
		);
	}

	public function test_explicit_deps_and_version_override_manifest(): void {
		$this->write_asset( 'assets/build/js/app.js', 'console.log(1);' );
		$this->write_asset(
			'assets/build/js/app.asset.php',
			'<?php return ["dependencies" => ["wp-dom"], "version" => "v1"];'
		);

		$this->loader->register_script( 'app', 'js/app', [ 'jquery' ], 'custom-ver' );

		$this->assertSame( [ 'jquery' ], $GLOBALS['wp_framework_test_registered_scripts']['app']['deps'] );
		$this->assertSame( 'custom-ver', $GLOBALS['wp_framework_test_registered_scripts']['app']['ver'] );
	}

	public function test_missing_asset_file_warns_and_does_not_register(): void {
		$this->assertFalse( $this->loader->register_script( 'missing', 'js/missing' ) );

		$this->assertSame( [], $GLOBALS['wp_framework_test_registered_scripts'] );
		$this->assertCount( 1, $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_manifest_is_optional_and_version_falls_back_to_filemtime(): void {
		$this->write_asset( 'assets/build/css/app.css', '.a{}' );

		$this->assertTrue( $this->loader->register_style( 'app', 'css/app' ) );

		$registered = $GLOBALS['wp_framework_test_registered_styles']['app'];
		$this->assertSame( [], $registered['deps'] );
		$this->assertIsString( $registered['ver'] );
		$this->assertNotSame( '', $registered['ver'] );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_register_block_manifest_registers_collection(): void {
		$this->write_asset( 'build/blocks-manifest.php', '<?php return [];' );

		$this->loader->register_block_manifest( 'build/blocks', 'build/blocks-manifest.php' );

		$this->assertSame(
			[
				[
					'path'     => trailingslashit( $this->temp_dir ) . 'build/blocks',
					'manifest' => trailingslashit( $this->temp_dir ) . 'build/blocks-manifest.php',
				],
			],
			$GLOBALS['wp_framework_test_registered_block_collections']
		);
	}

	public function test_missing_block_manifest_warns_and_skips_registration(): void {
		$this->loader->register_block_manifest( 'build/blocks', 'build/blocks-manifest.php' );

		$this->assertSame( [], $GLOBALS['wp_framework_test_registered_block_collections'] );
		$this->assertCount( 1, $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	private function write_asset( string $relative_path, string $contents ): void {
		$file      = trailingslashit( $this->temp_dir ) . $relative_path;
		$directory = dirname( $file );

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}

		file_put_contents( $file, $contents );
	}
}
