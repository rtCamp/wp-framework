<?php
/**
 * Asset loader tests.
 *
 * Runs against a real WordPress instance (wp-env): registrations are asserted
 * by reading the actual wp_scripts()/wp_styles()/wp_script_modules() registries
 * rather than stubbed globals, and incorrect-usage notices via
 * WP_UnitTestCase::setExpectedIncorrectUsage().
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use rtCamp\WPFramework\AssetLoader;

final class AssetLoaderTest extends TestCase {

	private string $temp_dir;

	private string $base_url = 'https://example.test/theme';

	private AssetLoader $loader;

	public function set_up(): void {
		parent::set_up();

		// Start each test from clean dependency registries so handles registered
		// in one test don't leak into the next (WP_UnitTestCase does not reset
		// these between tests).
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		$this->reset_script_modules();

		$this->temp_dir = sys_get_temp_dir() . '/wp-framework-asset-loader-' . str_replace( '.', '', uniqid( '', true ) );
		mkdir( $this->temp_dir, 0777, true );

		$this->loader = new AssetLoader( $this->temp_dir, $this->base_url, 'assets/build' );
	}

	public function tear_down(): void {
		if ( is_dir( $this->temp_dir ) ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $this->temp_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $file ) {
				$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
			}

			rmdir( $this->temp_dir );
		}

		parent::tear_down();
	}

	public function test_register_script_builds_src_and_reads_manifest(): void {
		$this->write_asset( 'assets/build/js/app.js', 'console.log(1);' );
		$this->write_asset(
			'assets/build/js/app.asset.php',
			'<?php return ["dependencies" => ["wp-dom"], "version" => "v1"];'
		);

		$this->assertTrue( $this->loader->register_script( 'app', 'js/app' ) );

		$script = $this->registered_script( 'app' );
		$this->assertNotNull( $script );
		$this->assertSame( 'https://example.test/theme/assets/build/js/app.js', $script->src );
		$this->assertSame( [ 'wp-dom' ], $script->deps );
		$this->assertSame( 'v1', $script->ver );
		// in_footer defaults to true → footer group.
		$this->assertSame( 1, $script->extra['group'] ?? null );
	}

	public function test_register_style_builds_src_and_reads_manifest(): void {
		$this->write_asset( 'assets/build/css/app.css', '.a{}' );
		$this->write_asset(
			'assets/build/css/app.asset.php',
			'<?php return ["dependencies" => ["wp-components"], "version" => "v2"];'
		);

		$this->assertTrue( $this->loader->register_style( 'app', 'css/app' ) );

		$style = $this->registered_style( 'app' );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/theme/assets/build/css/app.css', $style->src );
		$this->assertSame( [ 'wp-components' ], $style->deps );
		$this->assertSame( 'v2', $style->ver );
		// Media is stored on the dependency's args.
		$this->assertSame( 'all', $style->args );
	}

	public function test_register_script_module_maps_dependencies_to_id_form(): void {
		$this->write_asset( 'assets/build/js/module.js', 'export default 1;' );
		$this->write_asset(
			'assets/build/js/module.asset.php',
			'<?php return ["dependencies" => ["@wordpress/interactivity"], "version" => "v3"];'
		);

		$this->assertTrue( $this->loader->register_script_module( '@my/module', 'js/module' ) );

		$module = $this->registered_script_module( '@my/module' );
		$this->assertNotNull( $module );
		$this->assertSame( 'https://example.test/theme/assets/build/js/module.js', $module['src'] );
		$this->assertSame( 'v3', $module['version'] );
		// WordPress normalises each dependency to id/import form (import: static default).
		$this->assertSame( [ [ 'id' => '@wordpress/interactivity', 'import' => 'static' ] ], $module['dependencies'] );
	}

	public function test_register_script_module_accepts_string_and_array_dependencies(): void {
		$this->write_asset( 'assets/build/js/module.js', 'export default 1;' );

		$this->loader->register_script_module(
			'@my/module',
			'js/module',
			[ '@wordpress/interactivity', [ 'id' => '@wordpress/blocks', 'import' => 'dynamic' ] ]
		);

		$module = $this->registered_script_module( '@my/module' );
		$this->assertNotNull( $module );
		$this->assertSame(
			[
				[ 'id' => '@wordpress/interactivity', 'import' => 'static' ],
				[ 'id' => '@wordpress/blocks', 'import' => 'dynamic' ],
			],
			$module['dependencies']
		);
	}

	public function test_explicit_deps_and_version_override_manifest(): void {
		$this->write_asset( 'assets/build/js/app.js', 'console.log(1);' );
		$this->write_asset(
			'assets/build/js/app.asset.php',
			'<?php return ["dependencies" => ["wp-dom"], "version" => "v1"];'
		);

		$this->loader->register_script( 'app', 'js/app', [ 'jquery' ], 'custom-ver' );

		$script = $this->registered_script( 'app' );
		$this->assertNotNull( $script );
		$this->assertSame( [ 'jquery' ], $script->deps );
		$this->assertSame( 'custom-ver', $script->ver );
	}

	public function test_missing_asset_file_warns_and_does_not_register(): void {
		$this->setExpectedIncorrectUsage( AssetLoader::class );

		$this->assertFalse( $this->loader->register_script( 'missing', 'js/missing' ) );
		$this->assertNull( $this->registered_script( 'missing' ) );
	}

	public function test_manifest_is_optional_and_version_falls_back_to_filemtime(): void {
		$this->write_asset( 'assets/build/css/app.css', '.a{}' );

		$this->assertTrue( $this->loader->register_style( 'app', 'css/app' ) );

		$style = $this->registered_style( 'app' );
		$this->assertNotNull( $style );
		$this->assertSame( [], $style->deps );
		$this->assertIsString( $style->ver );
		$this->assertNotSame( '', $style->ver );
	}

	public function test_invalid_manifest_is_ignored_and_falls_back_to_filemtime(): void {
		$this->setExpectedIncorrectUsage( AssetLoader::class );

		$this->write_asset( 'assets/build/css/app.css', '.a{}' );
		$this->write_asset( 'assets/build/css/app.asset.php', '<?php return "not-an-array";' );

		$this->assertTrue( $this->loader->register_style( 'app', 'css/app' ) );

		$style = $this->registered_style( 'app' );
		$this->assertNotNull( $style );
		$this->assertSame( [], $style->deps );
		$this->assertIsString( $style->ver );
		$this->assertNotSame( '', $style->ver );
	}

	public function test_register_block_manifest_registers_collection(): void {
		// A real block.json plus a manifest describing it, both under the loader's
		// base. Spans the supported range: WP 6.8+ registers via the manifest
		// collection API; 6.5–6.7 falls back to per-block registration from disk
		// (which reads the block.json), so both paths register the same block.
		$this->write_asset(
			'build/blocks/example/block.json',
			'{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"wp-framework/example","title":"Example","category":"widgets"}'
		);
		$this->write_asset(
			'build/blocks-manifest.php',
			'<?php return ["example" => ["name" => "wp-framework/example", "title" => "Example", "category" => "widgets", "apiVersion" => 3]];'
		);

		$this->loader->register_block_manifest( 'build/blocks', 'build/blocks-manifest.php' );

		$this->assertTrue(
			\WP_Block_Type_Registry::get_instance()->is_registered( 'wp-framework/example' )
		);

		\WP_Block_Type_Registry::get_instance()->unregister( 'wp-framework/example' );
	}

	public function test_missing_block_manifest_warns_and_skips_registration(): void {
		$this->setExpectedIncorrectUsage( AssetLoader::class );

		$this->loader->register_block_manifest( 'build/blocks', 'build/blocks-manifest.php' );

		$this->assertFalse(
			\WP_Block_Type_Registry::get_instance()->is_registered( 'wp-framework/example' )
		);
	}

	/**
	 * Clear the script modules registry between tests.
	 */
	private function reset_script_modules(): void {
		$modules  = wp_script_modules();
		$property = new \ReflectionProperty( $modules, 'registered' );
		$property->setValue( $modules, [] );
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
