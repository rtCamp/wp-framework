<?php
/**
 * Component loader tests.
 *
 * Runs against a real WordPress instance (wp-env): the theme hierarchy is
 * pointed at fixture directories via TestCase::set_theme_dirs, assets are
 * asserted by reading the real wp_scripts()/wp_styles() registries, render
 * hooks are captured with a real add_action() recorder, and incorrect usage
 * via WP_UnitTestCase::setExpectedIncorrectUsage().
 *
 * @package rtCamp\WPFramework\Tests\Components
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Components;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use rtCamp\WPFramework\AssetLoader;
use rtCamp\WPFramework\ComponentLoader;
use rtCamp\WPFramework\Tests\TestCase;

final class ComponentLoaderTest extends TestCase {

	private const STYLE_HANDLE  = 'wp-framework-component-alert-style';
	private const SCRIPT_HANDLE = 'wp-framework-component-alert-script';

	private string $temp_dir;

	private string $parent_dir;

	private string $child_dir;

	private string $parent_uri = 'https://example.test/parent-theme';

	private string $child_uri = 'https://example.test/child-theme';

	private TestComponentLoader $loader;

	/**
	 * Render hooks fired during a test, in order.
	 *
	 * @var array<int, string>
	 */
	private array $rendered_hooks = [];

	public function set_up(): void {
		parent::set_up();

		$this->temp_dir   = sys_get_temp_dir() . '/wp-framework-component-loader-' . str_replace( '.', '', uniqid( '', true ) );
		$this->parent_dir = $this->temp_dir . '/parent-theme';
		$this->child_dir  = $this->temp_dir . '/child-theme';

		mkdir( $this->parent_dir, 0777, true );
		mkdir( $this->child_dir, 0777, true );

		// A distinct child theme is active by default.
		$this->set_theme_dirs( $this->parent_dir, $this->parent_uri, $this->child_dir, $this->child_uri );

		// Start each test from clean dependency registries (WP_UnitTestCase does
		// not reset these between tests).
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;

		TestComponentLoader::$test_context = 'wp-framework';

		// Record the render actions so tests can assert they fired.
		$this->rendered_hooks = [];
		foreach ( [ 'before', 'after' ] as $phase ) {
			$hook = "wp_framework_component_{$phase}_render";
			add_action(
				$hook,
				function () use ( $hook ): void {
					$this->rendered_hooks[] = $hook;
				}
			);
		}

		// Default loader: its own (self) base is the parent (template) theme.
		$this->loader = new TestComponentLoader( $this->theme_asset_loader() );
		$this->loader->clear_cache();
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

	/**
	 * Single-theme setup: stylesheet directory equals template directory.
	 */
	private function use_single_theme(): void {
		$this->set_theme_dirs( $this->parent_dir, $this->parent_uri );
	}

	public function test_renders_component_with_arguments(): void {
		$this->write_parent_component( 'alert', '<?php echo "<p>" . esc_html( (string) $args["message"] ) . "</p>";' );

		$this->assertSame(
			'<p>Hello world</p>',
			$this->loader->get( 'alert', [ 'message' => 'Hello world' ], [ 'script' => false, 'style' => false ] )
		);

		$this->assertSame(
			[ 'wp_framework_component_before_render', 'wp_framework_component_after_render' ],
			$this->rendered_hooks
		);
	}

	public function test_render_outputs_component_with_arguments(): void {
		$this->write_parent_component( 'banner', '<?php echo "<h2>" . esc_html( (string) $args["title"] ) . "</h2>";' );

		ob_start();
		$this->loader->render( 'banner', [ 'title' => 'Featured' ], [ 'script' => false, 'style' => false ] );

		$this->assertSame( '<h2>Featured</h2>', (string) ob_get_clean() );
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
		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'registered' ) );
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

		$style = $this->registered_style( self::STYLE_HANDLE );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/parent-theme/assets/build/css/components/alert.css', $style->src );
		$this->assertSame( [ 'wp-components' ], $style->deps );
		$this->assertSame( 'style-version', $style->ver );
		$this->assertSame( 'all', $style->args );
		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );

		$script = $this->registered_script( self::SCRIPT_HANDLE );
		$this->assertNotNull( $script );
		$this->assertSame( 'https://example.test/parent-theme/assets/build/js/components/alert.js', $script->src );
		$this->assertSame( [ 'wp-element' ], $script->deps );
		$this->assertSame( 'script-version', $script->ver );
		$this->assertSame( 1, $script->extra['group'] ?? null );
		$this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
	}

	public function test_child_theme_overrides_a_parent_component_asset(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert { color: red; }' );
		$this->write_child_asset( 'css/components/alert.css', '.alert { color: blue; }' );

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$style = $this->registered_style( self::STYLE_HANDLE );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/child-theme/assets/build/css/components/alert.css', $style->src );
	}

	public function test_resolves_asset_when_no_child_theme_is_active(): void {
		// No child theme: stylesheet directory equals template directory.
		$this->use_single_theme();

		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$style = $this->registered_style( self::STYLE_HANDLE );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/parent-theme/assets/build/css/components/alert.css', $style->src );
	}

	public function test_parent_theme_overrides_a_plugin_component_asset(): void {
		$plugin_dir = $this->temp_dir . '/plugin';
		$loader     = new TestComponentLoader( $this->plugin_asset_loader( $plugin_dir ) );

		// PHP + its own style ship in the plugin; the style is overridden in the parent theme.
		$this->write_plugin_component( $plugin_dir, 'alert', '<?php echo "alert";' );
		$this->write_plugin_asset( $plugin_dir, 'css/components/alert.css', '.plugin{}' );
		$this->write_parent_asset( 'css/components/alert.css', '.parent{}' );

		$loader->get( 'alert', [], [ 'script' => false ] );

		$style = $this->registered_style( self::STYLE_HANDLE );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/parent-theme/assets/build/css/components/alert.css', $style->src );
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

		$style = $this->registered_style( self::STYLE_HANDLE );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/plugin/assets/build/css/components/alert.css', $style->src );
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

		$style = $this->registered_style( self::STYLE_HANDLE );
		$this->assertNotNull( $style );
		$this->assertSame( 'https://example.test/parent-theme/assets/build/css/components/alert.css', $style->src );

		$script = $this->registered_script( self::SCRIPT_HANDLE );
		$this->assertNotNull( $script );
		$this->assertSame( 'https://example.test/child-theme/assets/build/js/components/alert.js', $script->src );
	}

	public function test_context_namespaces_the_asset_handle(): void {
		TestComponentLoader::$test_context = 'elementary';

		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertTrue( wp_style_is( 'elementary-component-alert-style', 'registered' ) );
		$this->assertTrue( wp_style_is( 'elementary-component-alert-style', 'enqueued' ) );
	}

	public function test_should_enqueue_filter_can_suppress_a_component_asset(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		add_filter(
			'wp_framework_component_should_enqueue',
			static fn ( bool $enqueue, string $name, string $type ): bool => 'style' === $type ? false : $enqueue,
			10,
			3
		);

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'registered' ) );
		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_asset_handle_filter_overrides_the_handle(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		add_filter(
			'wp_framework_component_asset_handle',
			static fn ( string $handle, string $name, string $type ): string => "custom-{$name}-{$type}",
			10,
			3
		);

		$this->loader->get( 'alert', [], [ 'script' => false ] );

		$this->assertTrue( wp_style_is( 'custom-alert-style', 'registered' ) );
		$this->assertTrue( wp_style_is( 'custom-alert-style', 'enqueued' ) );
	}

	public function test_registered_component_assets_are_enqueued_again_after_dequeue(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );

		wp_dequeue_style( self::STYLE_HANDLE );
		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );
		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_render_options_can_disable_individual_asset_types(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );
		$this->write_parent_asset( 'js/components/alert.js', 'window.alert = true;' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'style' => false ] ) );

		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) );
	}

	public function test_component_asset_is_registered_without_manifest_fallback(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );
		$this->write_parent_asset( 'css/components/alert.css', '.alert{}' );

		$this->assertSame( 'alert', $this->loader->get( 'alert', [], [ 'script' => false ] ) );

		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'registered' ) );
		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_render_without_an_asset_loader_throws(): void {
		$this->write_parent_component( 'alert', '<?php echo "alert";' );

		$this->expectException( \RuntimeException::class );

		// No loader injected and get_asset_loader() not overridden.
		( new ComponentLoader() )->get( 'alert' );
	}

	public function test_invalid_component_name_returns_empty_string_and_records_incorrect_usage(): void {
		$this->setExpectedIncorrectUsage( TestComponentLoader::class . '::get' );

		$this->assertSame( '', $this->loader->get( '../Alert' ) );
	}

	private function theme_asset_loader(): AssetLoader {
		return new AssetLoader( $this->parent_dir, $this->parent_uri, 'assets/build' );
	}

	private function plugin_asset_loader( string $plugin_dir ): AssetLoader {
		return new AssetLoader( $plugin_dir, 'https://example.test/plugin', 'assets/build' );
	}

	private function write_parent_component( string $name, string $contents ): void {
		$this->write_component( $this->parent_dir . '/src/components', $name, $contents );
	}

	private function write_plugin_component( string $plugin_dir, string $name, string $contents ): void {
		$this->write_component( $plugin_dir . '/src/components', $name, $contents );
	}

	private function write_component( string $components_root, string $name, string $contents ): void {
		$this->write_file( $components_root . '/' . $name . '/' . $name . '.php', $contents );
	}

	private function write_parent_asset( string $relative_path, string $contents ): void {
		$this->write_file( $this->parent_dir . '/assets/build/' . $relative_path, $contents );
	}

	private function write_child_asset( string $relative_path, string $contents ): void {
		$this->write_file( $this->child_dir . '/assets/build/' . $relative_path, $contents );
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
