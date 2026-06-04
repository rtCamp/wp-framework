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
use rtCamp\WPFramework\AssetLoader;
use rtCamp\WPFramework\ComponentLoader;

final class ComponentLoaderTest extends TestCase {

	private string $temp_dir;

	private TestComponentLoader $loader;

	protected function setUp(): void {
		$this->temp_dir = sys_get_temp_dir() . '/wp-framework-component-loader-' . str_replace( '.', '', uniqid( '', true ) );
		mkdir( $this->temp_dir, 0777, true );

		$GLOBALS['wp_framework_test_actions']                  = [];
		$GLOBALS['wp_framework_test_doing_it_wrong']           = [];
		$GLOBALS['wp_framework_test_enqueued_scripts']         = [];
		$GLOBALS['wp_framework_test_enqueued_styles']          = [];
		$GLOBALS['wp_framework_test_filters']                  = [];
		$GLOBALS['wp_framework_test_registered_scripts']       = [];
		$GLOBALS['wp_framework_test_registered_styles']        = [];
		$GLOBALS['wp_framework_test_stylesheet_directory']     = $this->temp_dir . '/child-theme';
		$GLOBALS['wp_framework_test_stylesheet_directory_uri'] = 'https://example.test/child-theme';
		$GLOBALS['wp_framework_test_template_directory']       = $this->temp_dir . '/parent-theme';
		$GLOBALS['wp_framework_test_template_directory_uri']   = 'https://example.test/parent-theme';

		mkdir( $GLOBALS['wp_framework_test_stylesheet_directory'], 0777, true );
		mkdir( $GLOBALS['wp_framework_test_template_directory'], 0777, true );

		TestComponentLoader::$test_context = 'wp-framework';

		// Record the render actions so tests can assert they fired.
		foreach ( [ 'before', 'after' ] as $phase ) {
			$hook = "wp_framework_component_{$phase}_render";
			add_action(
				$hook,
				static function () use ( $hook ): void {
					$GLOBALS['wp_framework_test_actions'][] = [ 'hook' => $hook ];
				}
			);
		}

		// Default loader: its own (self) base is the parent (template) theme.
		$this->loader = new TestComponentLoader( $this->theme_asset_loader() );
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

	public function test_renders_component_with_arguments(): void {
		$this->write_parent_component( 'alert', '<?php echo "<p>" . esc_html( (string) $args["message"] ) . "</p>";' );

		$this->assertSame(
			'<p>Hello world</p>',
			$this->loader->get( 'alert', [ 'message' => 'Hello world' ], [ 'script' => false, 'style' => false ] )
		);

		$this->assertSame( 'wp_framework_component_before_render', $GLOBALS['wp_framework_test_actions'][0]['hook'] );
		$this->assertSame( 'wp_framework_component_after_render', $GLOBALS['wp_framework_test_actions'][1]['hook'] );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_render_outputs_component_with_arguments(): void {
		$this->write_parent_component( 'banner', '<?php echo "<h2>" . esc_html( (string) $args["title"] ) . "</h2>";' );

		ob_start();
		$this->loader->render( 'banner', [ 'title' => 'Featured' ], [ 'script' => false, 'style' => false ] );

		$this->assertSame( '<h2>Featured</h2>', (string) ob_get_clean() );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_resolves_component_by_exact_name(): void {
		$this->write_parent_component( 'alert', '<?php echo "exact name";' );

		$this->assertSame(
			'exact name',
			$this->loader->get( 'alert', [], [ 'script' => false, 'style' => false ] )
		);
	}

	public function test_parent_theme_takes_precedence_over_a_plugin_component(): void {
		$plugin_dir = $this->temp_dir . '/plugin';
		$loader     = new TestComponentLoader( $this->plugin_asset_loader( $plugin_dir ) );

		$this->write_plugin_component( $plugin_dir, 'card', '<?php echo "plugin";' );
		$this->write_parent_component( 'card', '<?php echo "theme";' );

		$this->assertSame(
			'theme',
			$loader->get( 'card', [], [ 'script' => false, 'style' => false ] )
		);
	}

	public function test_resolves_component_and_registers_its_style(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert { color: red; }' );
		$this->write_parent_asset( 'css/components/alert.asset.php', '<?php return ["version" => "v1"];' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );
		$this->assertArrayHasKey( 'wp-framework-component-alert-style', $GLOBALS['wp_framework_test_registered_styles'] );
	}

	public function test_parent_theme_component_assets_are_registered_with_metadata(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert { color: red; }' );
		$this->write_parent_asset(
			'css/components/alert.asset.php',
			'<?php return ["dependencies" => ["wp-components"], "version" => "style-version"];'
		);
		$this->write_parent_asset( 'js/components/alert.js', 'window.alertComponent = true;' );
		$this->write_parent_asset(
			'js/components/alert.asset.php',
			'<?php return ["dependencies" => ["wp-element"], "version" => "script-version"];'
		);

		$this->assertSame( 'alert', $this->loader->get( 'alert' ) );

		$this->assertSame(
			[
				'src'   => 'https://example.test/parent-theme/assets/build/css/components/alert.css',
				'deps'  => [ 'wp-components' ],
				'ver'   => 'style-version',
				'media' => 'all',
			],
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']
		);
		$this->assertSame( [ 'wp-framework-component-alert-style' ], $GLOBALS['wp_framework_test_enqueued_styles'] );

		$this->assertSame(
			[
				'src'       => 'https://example.test/parent-theme/assets/build/js/components/alert.js',
				'deps'      => [ 'wp-element' ],
				'ver'       => 'script-version',
				'in_footer' => true,
			],
			$GLOBALS['wp_framework_test_registered_scripts']['wp-framework-component-alert-script']
		);
		$this->assertSame( [ 'wp-framework-component-alert-script' ], $GLOBALS['wp_framework_test_enqueued_scripts'] );
	}

	public function test_child_theme_overrides_a_parent_component_asset(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert { color: red; }' );
		$this->write_child_asset( 'css/components/alert.css', '.alert { color: blue; }' );

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertSame(
			'https://example.test/child-theme/assets/build/css/components/alert.css',
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']['src']
		);
	}

	public function test_resolves_asset_when_no_child_theme_is_active(): void {
		// No child theme: stylesheet directory equals template directory.
		$GLOBALS['wp_framework_test_stylesheet_directory']     = $GLOBALS['wp_framework_test_template_directory'];
		$GLOBALS['wp_framework_test_stylesheet_directory_uri'] = $GLOBALS['wp_framework_test_template_directory_uri'];

		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertSame(
			'https://example.test/parent-theme/assets/build/css/components/alert.css',
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']['src']
		);
	}

	public function test_parent_theme_overrides_a_plugin_component_asset(): void {
		$plugin_dir = $this->temp_dir . '/plugin';
		$loader     = new TestComponentLoader( $this->plugin_asset_loader( $plugin_dir ) );

		// PHP + its own style ship in the plugin; the style is overridden in the parent theme.
		$this->write_plugin_component( $plugin_dir, 'alert', '<?php echo "alert";' );
		$this->write_plugin_asset( $plugin_dir, 'css/components/alert.css', '.plugin{}' );
		$this->write_parent_asset( 'css/components/alert.css', '.parent{}' );

		$loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertSame(
			'https://example.test/parent-theme/assets/build/css/components/alert.css',
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']['src']
		);
	}

	public function test_allow_override_false_resolves_from_the_package_only(): void {
		$plugin_dir = $this->temp_dir . '/plugin';
		$loader     = new TestComponentLoader( $this->plugin_asset_loader( $plugin_dir ) );

		// Component PHP + style in the plugin, and overridden in the parent theme.
		$this->write_plugin_component( $plugin_dir, 'alert', '<?php echo "plugin";' );
		$this->write_plugin_asset( $plugin_dir, 'css/components/alert.css', '.plugin{}' );
		$this->write_parent_component( 'alert', '<?php echo "theme";' );
		$this->write_parent_asset( 'css/components/alert.css', '.theme{}' );

		// allow_override = false → resolve PHP + assets from the plugin (self) only.
		$html = $loader->get( 'alert', [], [ 'allow_override' => false, 'script' => false ] );

		$this->assertSame( 'plugin', $html );
		$this->assertSame(
			'https://example.test/plugin/assets/build/css/components/alert.css',
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']['src']
		);
	}

	public function test_each_asset_type_resolves_independently_across_the_hierarchy(): void {
		$plugin_dir = $this->temp_dir . '/plugin';
		$loader     = new TestComponentLoader( $this->plugin_asset_loader( $plugin_dir ) );

		// PHP + both assets in the plugin; CSS overridden in parent, JS in child.
		$this->write_plugin_component( $plugin_dir, 'alert', '<?php echo "alert";' );
		$this->write_plugin_asset( $plugin_dir, 'css/components/alert.css', '.plugin{}' );
		$this->write_plugin_asset( $plugin_dir, 'js/components/alert.js', 'plugin' );
		$this->write_parent_asset( 'css/components/alert.css', '.parent{}' );
		$this->write_child_asset( 'js/components/alert.js', 'child' );

		$loader->get( 'alert' );

		$this->assertSame(
			'https://example.test/parent-theme/assets/build/css/components/alert.css',
			$GLOBALS['wp_framework_test_registered_styles']['wp-framework-component-alert-style']['src']
		);
		$this->assertSame(
			'https://example.test/child-theme/assets/build/js/components/alert.js',
			$GLOBALS['wp_framework_test_registered_scripts']['wp-framework-component-alert-script']['src']
		);
	}

	public function test_context_namespaces_the_asset_handle(): void {
		TestComponentLoader::$test_context = 'elementary';

		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertArrayHasKey( 'elementary-component-alert-style', $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertSame( [ 'elementary-component-alert-style' ], $GLOBALS['wp_framework_test_enqueued_styles'] );
	}

	public function test_should_enqueue_filter_can_suppress_a_component_asset(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		add_filter(
			'wp_framework_component_should_enqueue',
			static fn ( bool $enqueue, string $name, string $type ): bool => 'style' === $type ? false : $enqueue
		);

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertSame( [], $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertSame( [], $GLOBALS['wp_framework_test_enqueued_styles'] );
	}

	public function test_asset_handle_filter_overrides_the_handle(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		add_filter(
			'wp_framework_component_asset_handle',
			static fn ( string $handle, string $name, string $type ): string => "custom-{$name}-{$type}"
		);

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertArrayHasKey( 'custom-alert-style', $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertSame( [ 'custom-alert-style' ], $GLOBALS['wp_framework_test_enqueued_styles'] );
	}

	public function test_registered_component_assets_are_enqueued_again_after_dequeue(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );

		wp_dequeue_style( 'wp-framework-component-alert-style' );
		$this->assertFalse( wp_style_is( 'wp-framework-component-alert-style', 'enqueued' ) );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );
		$this->assertTrue( wp_style_is( 'wp-framework-component-alert-style', 'enqueued' ) );
	}

	public function test_render_options_can_disable_individual_asset_types(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );
		$this->write_parent_asset( 'js/components/alert.js', 'window.alert = true;' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'style' => false ] ) );

		$this->assertSame( [], $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertArrayHasKey( 'wp-framework-component-alert-script', $GLOBALS['wp_framework_test_registered_scripts'] );
	}

	public function test_component_asset_is_registered_without_manifest_fallback(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );

		$this->assertArrayHasKey( 'wp-framework-component-alert-style', $GLOBALS['wp_framework_test_registered_styles'] );
		$this->assertSame( [ 'wp-framework-component-alert-style' ], $GLOBALS['wp_framework_test_enqueued_styles'] );
		$this->assertSame( [], $GLOBALS['wp_framework_test_doing_it_wrong'] );
	}

	public function test_render_without_an_asset_loader_throws(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );

		$this->expectException( \RuntimeException::class );

		// No loader injected and get_asset_loader() not overridden.
		( new ComponentLoader() )->get( 'alert' );
	}

	public function test_invalid_component_name_returns_empty_string_and_records_incorrect_usage(): void {
		$this->assertSame( '', $this->loader->get( '../Alert' ) );
		$this->assertCount( 1, $GLOBALS['wp_framework_test_doing_it_wrong'] );
		$this->assertSame( 'Component "../Alert" could not be resolved.', $GLOBALS['wp_framework_test_doing_it_wrong'][0]['message'] );
	}

	private function theme_asset_loader(): AssetLoader {
		return new AssetLoader(
			$GLOBALS['wp_framework_test_template_directory'],
			$GLOBALS['wp_framework_test_template_directory_uri'],
			'assets/build'
		);
	}

	private function plugin_asset_loader( string $plugin_dir ): AssetLoader {
		return new AssetLoader( $plugin_dir, 'https://example.test/plugin', 'assets/build' );
	}

	private function write_parent_component( string $name, string $contents ): void {
		$this->write_component( $GLOBALS['wp_framework_test_template_directory'] . '/src/components', $name, $contents );
	}

	private function write_plugin_component( string $plugin_dir, string $name, string $contents ): void {
		$this->write_component( $plugin_dir . '/src/components', $name, $contents );
	}

	private function write_component( string $components_root, string $name, string $contents ): void {
		$this->write_file( $components_root . '/' . $name . '/' . $name . '.php', $contents );
	}

	private function write_parent_asset( string $relative_path, string $contents ): void {
		$this->write_file( $GLOBALS['wp_framework_test_template_directory'] . '/assets/build/' . $relative_path, $contents );
	}

	private function write_child_asset( string $relative_path, string $contents ): void {
		$this->write_file( $GLOBALS['wp_framework_test_stylesheet_directory'] . '/assets/build/' . $relative_path, $contents );
	}

	private function write_plugin_asset( string $plugin_dir, string $relative_path, string $contents ): void {
		$this->write_file( $plugin_dir . '/assets/build/' . $relative_path, $contents );
	}

	private function write_file( string $file, string $contents ): void {
		$directory = dirname( $file );

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}

		file_put_contents( $file, $contents );
	}
}

class TestComponentLoader extends ComponentLoader {
	public static string $test_context = 'wp-framework';

	protected function get_context(): string {
		return self::$test_context;
	}
}
