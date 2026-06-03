<?php
/**
 * Component loader tests.
 *
 * @package rtCamp\WPFramework\Tests\Components
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Components;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use rtCamp\WPFramework\Components\AbstractComponentLoader;

final class AbstractComponentLoaderTest extends TestCase {

	private string $temp_dir;

	private TestComponentLoader $loader;

	protected function setUp(): void {
		$this->temp_dir = sys_get_temp_dir() . '/wp-framework-component-loader-' . str_replace( '.', '', uniqid( '', true ) );
		mkdir( $this->temp_dir, 0777, true );

		$GLOBALS['wp_framework_test_actions']               = [];
		$GLOBALS['wp_framework_test_doing_it_wrong']        = [];
		$GLOBALS['wp_framework_test_enqueued_scripts']      = [];
		$GLOBALS['wp_framework_test_enqueued_styles']       = [];
		$GLOBALS['wp_framework_test_filters']               = [];
		$GLOBALS['wp_framework_test_registered_scripts']    = [];
		$GLOBALS['wp_framework_test_registered_styles']     = [];
		$GLOBALS['wp_framework_test_stylesheet_directory']  = $this->temp_dir . '/child-theme';
		$GLOBALS['wp_framework_test_stylesheet_directory_uri'] = 'https://example.test/child-theme';
		$GLOBALS['wp_framework_test_template_directory']    = $this->temp_dir . '/parent-theme';
		$GLOBALS['wp_framework_test_template_directory_uri'] = 'https://example.test/parent-theme';

		mkdir( $GLOBALS['wp_framework_test_stylesheet_directory'], 0777, true );
		mkdir( $GLOBALS['wp_framework_test_template_directory'], 0777, true );

		TestComponentLoader::$test_paths = [];
		$this->loader                  = new TestComponentLoader();
		$this->loader->clear_cache();
	}

	protected function tearDown(): void {
		$this->loader->clear_cache();

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
	}

	public function test_get_renders_plugin_component_with_arguments(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';

		$this->write_component(
			$plugin_components,
			'alert',
			'<?php echo "<p>" . esc_html( (string) $args["message"] ) . "</p>";'
		);

		$this->use_component_paths(
			[
				'plugin' => [
					'php' => $plugin_components,
				],
			]
		);

		$this->assertSame(
			'<p>Hello world</p>',
			$this->loader->get( 'alert', [ 'message' => 'Hello world' ], [ 'script' => false, 'style' => false ] )
		);

		$this->assertSame( 'wp_framework_before_get_component', $GLOBALS['wp_framework_test_actions'][0]['hook'] );
		$this->assertSame( 'wp_framework_after_get_component', $GLOBALS['wp_framework_test_actions'][1]['hook'] );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_render_outputs_plugin_component_with_arguments(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';

		$this->write_component(
			$plugin_components,
			'banner',
			'<?php echo "<h2>" . esc_html( (string) $args["title"] ) . "</h2>";'
		);

		$this->use_component_paths(
			[
				'plugin' => [
					'php' => $plugin_components,
				],
			]
		);

		ob_start();
		$this->loader->render( 'banner', [ 'title' => 'Featured' ], [ 'script' => false, 'style' => false ] );

		$this->assertSame( '<h2>Featured</h2>', (string) ob_get_clean() );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_theme_component_takes_precedence_over_plugin_component(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';
		$child_components  = $GLOBALS['wp_framework_test_stylesheet_directory'] . '/components';

		$this->write_component( $plugin_components, 'card', '<?php echo "plugin";' );
		$this->write_component( $child_components, 'card', '<?php echo "theme";' );

		$this->use_component_paths(
			[
				'theme'  => [
					'php' => 'components',
				],
				'plugin' => [
					'php' => $plugin_components,
				],
			]
		);

		$this->assertSame(
			'theme',
			$this->loader->get( 'card', [], [ 'script' => false, 'style' => false ] )
		);
	}

	public function test_loader_resolves_theme_component_from_default_paths(): void {
		$theme_components = $GLOBALS['wp_framework_test_stylesheet_directory'] . '/src/components';
		$style_dir        = $GLOBALS['wp_framework_test_stylesheet_directory'] . '/assets/build/css/components';

		$this->write_component( $theme_components, 'alert', '<?php echo "theme default";' );
		$this->write_file( $style_dir . '/alert.css', '.alert { color: red; }' );
		$this->write_file(
			$style_dir . '/alert.asset.php',
			'<?php return ["version" => "style-version"];'
		);

		$this->assertSame( 'theme default', $this->loader->get( 'alert', [], [ 'script' => false ] ) );
		$this->assertArrayHasKey( 'wp-framework-component-alert-style', $GLOBALS['wp_framework_test_registered_styles'] );
	}

	public function test_loader_resolves_theme_component_by_exact_name(): void {
		$theme_components = $GLOBALS['wp_framework_test_stylesheet_directory'] . '/src/components';

		$this->write_file( $theme_components . '/alert/alert.php', '<?php echo "exact name theme";' );

		$this->assertSame(
			'exact name theme',
			$this->loader->get( 'alert', [], [ 'script' => false, 'style' => false ] )
		);
	}

	public function test_component_assets_are_registered_and_enqueued_with_asset_metadata(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';
		$style_dir         = $this->temp_dir . '/plugin/assets/css';
		$script_dir        = $this->temp_dir . '/plugin/assets/js';

		$this->write_component( $plugin_components, 'alert', '<?php echo "alert";' );
		$this->write_file( $style_dir . '/alert.css', '.alert { color: red; }' );
		$this->write_file(
			$style_dir . '/alert.asset.php',
			'<?php return ["dependencies" => ["wp-components"], "version" => "style-version"];'
		);
		$this->write_file( $script_dir . '/alert.js', 'window.alertComponent = true;' );
		$this->write_file(
			$script_dir . '/alert.asset.php',
			'<?php return ["dependencies" => ["wp-element"], "version" => "script-version"];'
		);

		$this->use_component_paths(
			[
				'plugin' => [
					'php'    => $plugin_components,
					'style'  => [
						'dir' => $style_dir,
						'url' => 'https://example.test/plugin/assets/css',
					],
					'script' => [
						'dir' => $script_dir,
						'url' => 'https://example.test/plugin/assets/js',
					],
				],
			]
		);

		$this->assertSame( 'alert', $this->loader->get( 'alert' ) );

		$this->assertSame(
			[
				'src'   => 'https://example.test/plugin/assets/css/alert.css',
				'deps'  => [ 'wp-components' ],
				'ver'   => 'style-version',
				'media' => 'all',
			],
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']
		);
		$this->assertSame( [ 'wp-framework-component-alert-style' ], $GLOBALS['wp_framework_test_enqueued_styles'] );

		$this->assertSame(
			[
				'src'       => 'https://example.test/plugin/assets/js/alert.js',
				'deps'      => [ 'wp-element' ],
				'ver'       => 'script-version',
				'in_footer' => true,
			],
			$GLOBALS['wp_framework_test_registered_scripts']['wp-framework-component-alert-script']
		);
		$this->assertSame( [ 'wp-framework-component-alert-script' ], $GLOBALS['wp_framework_test_enqueued_scripts'] );
	}

	public function test_registered_component_assets_are_enqueued_again_after_dequeue(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';
		$style_dir         = $this->temp_dir . '/plugin/assets/css';
		$script_dir        = $this->temp_dir . '/plugin/assets/js';

		$this->write_component( $plugin_components, 'alert', '<?php echo "alert";' );
		$this->write_file( $style_dir . '/alert.css', '.alert { color: red; }' );
		$this->write_file(
			$style_dir . '/alert.asset.php',
			'<?php return ["dependencies" => ["wp-components"], "version" => "style-version"];'
		);
		$this->write_file( $script_dir . '/alert.js', 'window.alertComponent = true;' );
		$this->write_file(
			$script_dir . '/alert.asset.php',
			'<?php return ["dependencies" => ["wp-element"], "version" => "script-version"];'
		);

		$this->use_component_paths(
			[
				'plugin' => [
					'php'    => $plugin_components,
					'style'  => [
						'dir' => $style_dir,
						'url' => 'https://example.test/plugin/assets/css',
					],
					'script' => [
						'dir' => $script_dir,
						'url' => 'https://example.test/plugin/assets/js',
					],
				],
			]
		);

		$this->assertSame( 'alert', $this->loader->get( 'alert' ) );

		wp_dequeue_style( 'wp-framework-component-alert-style' );
		wp_dequeue_script( 'wp-framework-component-alert-script' );

		$this->assertFalse( wp_style_is( 'wp-framework-component-alert-style', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wp-framework-component-alert-script', 'enqueued' ) );

		$this->assertSame( 'alert', $this->loader->get( 'alert' ) );

		$this->assertTrue( wp_style_is( 'wp-framework-component-alert-style', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wp-framework-component-alert-script', 'enqueued' ) );
	}

	public function test_render_options_can_disable_individual_asset_types(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';
		$style_dir         = $this->temp_dir . '/plugin/assets/css';
		$script_dir        = $this->temp_dir . '/plugin/assets/js';

		$this->write_component( $plugin_components, 'alert', '<?php echo "alert";' );
		$this->write_file( $style_dir . '/alert.css', '.alert { color: red; }' );
		$this->write_file(
			$style_dir . '/alert.asset.php',
			'<?php return ["dependencies" => ["wp-components"], "version" => "style-version"];'
		);
		$this->write_file( $script_dir . '/alert.js', 'window.alertComponent = true;' );
		$this->write_file(
			$script_dir . '/alert.asset.php',
			'<?php return ["dependencies" => ["wp-element"], "version" => "script-version"];'
		);

		$this->use_component_paths(
			[
				'plugin' => [
					'php'    => $plugin_components,
					'style'  => [
						'dir' => $style_dir,
						'url' => 'https://example.test/plugin/assets/css',
					],
					'script' => [
						'dir' => $script_dir,
						'url' => 'https://example.test/plugin/assets/js',
					],
				],
			]
		);

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'style' => false ] ) );

		$this->assertSame( [], $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertArrayHasKey( 'wp-framework-component-alert-script', $GLOBALS['wp_framework_test_registered_scripts'] );
	}

	public function test_component_asset_is_registered_without_manifest_fallback(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';
		$style_dir         = $this->temp_dir . '/plugin/assets/css';

		$this->write_component( $plugin_components, 'alert', '<?php echo "alert";' );
		$this->write_file( $style_dir . '/alert.css', '.alert { color: red; }' );

		$this->use_component_paths(
			[
				'plugin' => [
					'php'   => $plugin_components,
					'style' => [
						'dir' => $style_dir,
						'url' => 'https://example.test/plugin/assets/css',
					],
				],
			]
		);

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );

		// AssetLoaderTrait treats the .asset.php manifest as optional and falls
		// back to filemtime(), so the style should still be registered.
		$this->assertArrayHasKey( 'wp-framework-component-alert-style', $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertSame( [ 'wp-framework-component-alert-style' ], $GLOBALS['wp_framework_test_enqueued_styles'] );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_invalid_component_name_returns_empty_string_and_records_incorrect_usage(): void {
		$plugin_components = $this->temp_dir . '/plugin/components';

		$this->write_component( $plugin_components, 'alert', '<?php echo "alert";' );
		$this->use_component_paths(
			[
				'plugin' => [
					'php' => $plugin_components,
				],
			]
		);

		$this->assertSame( '', $this->loader->get( '../Alert' ) );
		$this->assertCount( 1, $GLOBALS['wp_framework_test_doing_it_wrong'] );
		$this->assertSame( 'Component "../Alert" could not be resolved.', $GLOBALS['wp_framework_test_doing_it_wrong'][0]['message'] );
	}

	private function write_component( string $base_dir, string $name, string $contents ): void {
		$this->write_file( $base_dir . '/' . $name . '/' . $name . '.php', $contents );
	}

	/**
	 * Use component paths for a single test through the public filter.
	 *
	 * @param array<string, array<string, mixed>> $paths Component paths.
	 */
	private function use_component_paths( array $paths ): void {
		TestComponentLoader::$test_paths = $paths;
	}

	private function write_file( string $file, string $contents ): void {
		$directory = dirname( $file );

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}

		file_put_contents( $file, $contents );
	}
}

class TestComponentLoader extends AbstractComponentLoader {
	public static array $test_paths = [];

	protected function get_component_paths( string $component_name, array $options ): array {
		if ( ! empty( self::$test_paths ) ) {
			return self::$test_paths;
		}
		return parent::get_component_paths( $component_name, $options );
	}

	protected function before_get_component( string $name, array $args, array $options ): void {
		$GLOBALS['wp_framework_test_actions'][] = [ 'hook' => 'wp_framework_before_get_component' ];
	}

	protected function after_get_component( string $name, array $args, array $options ): void {
		$GLOBALS['wp_framework_test_actions'][] = [ 'hook' => 'wp_framework_after_get_component' ];
	}
}
