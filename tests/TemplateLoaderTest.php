<?php
/**
 * Tests for the TemplateLoader class.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\TemplateLoader;

/**
 * Class TemplateLoaderTest
 */
final class TemplateLoaderTest extends TestCase {

	/**
	 * Temp root holding the child-theme / parent-theme / package fixtures.
	 *
	 * @var string
	 */
	private string $tmp;

	/**
	 * Set up theme/package fixture directories and reset the hook registry.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->tmp = sys_get_temp_dir() . '/wpf-tpl-' . uniqid( '', true );

		$GLOBALS['wp_framework_test_stylesheet_directory'] = $this->tmp . '/child';
		$GLOBALS['wp_framework_test_template_directory']   = $this->tmp . '/parent';

		mkdir( $this->tmp . '/child/my-plugin', 0777, true );
		mkdir( $this->tmp . '/parent/my-plugin', 0777, true );
		mkdir( $this->tmp . '/plugin/templates', 0777, true );

		$GLOBALS['wp_framework_test_filters']          = [];
		$GLOBALS['wp_framework_test_loaded_templates'] = [];
	}

	/**
	 * Remove the fixture tree.
	 */
	protected function tearDown(): void {
		$this->rrmdir( $this->tmp );
		unset(
			$GLOBALS['wp_framework_test_stylesheet_directory'],
			$GLOBALS['wp_framework_test_template_directory'],
			$GLOBALS['wp_framework_test_filters'],
			$GLOBALS['wp_framework_test_loaded_templates']
		);

		parent::tearDown();
	}

	/**
	 * Build a loader against the fixture package dir.
	 */
	private function loader(): TemplateLoader {
		return new TemplateLoader( 'my_plugin', $this->tmp . '/plugin/templates', 'my-plugin' );
	}

	/**
	 * Make this a child-theme setup (child != parent) or single-theme (child == parent).
	 */
	private function set_child_theme( bool $enabled ): void {
		$GLOBALS['wp_framework_test_stylesheet_directory'] = $enabled
			? $this->tmp . '/child'
			: $this->tmp . '/parent';
	}

	private function write( string $abs, string $body = '' ): void {
		$dir = dirname( $abs );

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		file_put_contents( $abs, $body );
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}

		rmdir( $dir );
	}

	public function test_resolves_from_package_when_no_theme_override(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$this->assertSame(
			$this->tmp . '/plugin/templates/card.php',
			$this->loader()->locate( 'card' )
		);
	}

	public function test_parent_theme_overrides_package(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );
		$this->write( $this->tmp . '/parent/my-plugin/card.php' );

		$this->assertSame(
			$this->tmp . '/parent/my-plugin/card.php',
			$this->loader()->locate( 'card' )
		);
	}

	public function test_child_theme_overrides_parent(): void {
		$this->set_child_theme( true );
		$this->write( $this->tmp . '/parent/my-plugin/card.php' );
		$this->write( $this->tmp . '/child/my-plugin/card.php' );

		$this->assertSame(
			$this->tmp . '/child/my-plugin/card.php',
			$this->loader()->locate( 'card' )
		);
	}

	public function test_theme_package_layer_is_deduplicated(): void {
		// Theme consumer: its own templates ARE the parent-theme override path,
		// so the package layer must collapse rather than be searched twice.
		$this->set_child_theme( false );
		$theme_loader = new TemplateLoader( 'thm', $this->tmp . '/parent/templates', 'templates' );

		$method = new \ReflectionMethod( $theme_loader, 'get_template_paths' );
		$method->setAccessible( true );
		$paths = $method->invoke( $theme_loader );

		$this->assertSame( [ $this->tmp . '/parent/templates/' ], $paths );
	}

	public function test_theme_child_overrides_the_theme_package(): void {
		// Theme ships card.php; a child theme overrides it. Same loader, no plugin layer.
		$this->set_child_theme( true );
		$theme_loader = new TemplateLoader( 'thm', $this->tmp . '/parent/templates', 'templates' );

		$this->write( $this->tmp . '/parent/templates/card.php' );
		$this->assertSame(
			$this->tmp . '/parent/templates/card.php',
			$theme_loader->locate( 'card' )
		);

		$this->write( $this->tmp . '/child/templates/card.php' );
		$theme_loader->clear_cache();
		$this->assertSame(
			$this->tmp . '/child/templates/card.php',
			$theme_loader->locate( 'card' )
		);
	}

	public function test_child_theme_package_has_no_parent_fallback(): void {
		// A loader owned by the child theme: nothing sits above it, so the parent
		// theme is NOT searched as a fallback (mirrors ComponentLoader).
		$this->set_child_theme( true );
		$child_loader = new TemplateLoader( 'ct', $this->tmp . '/child/templates', 'templates' );

		$method = new \ReflectionMethod( $child_loader, 'get_template_paths' );
		$method->setAccessible( true );

		$this->assertSame( [ $this->tmp . '/child/templates/' ], $method->invoke( $child_loader ) );
	}

	public function test_name_variant_is_preferred_over_base_slug(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );
		$this->write( $this->tmp . '/plugin/templates/card-featured.php' );

		$this->assertSame(
			$this->tmp . '/plugin/templates/card-featured.php',
			$this->loader()->locate( 'card', 'featured' )
		);
	}

	public function test_name_variant_wins_across_layers_over_base_slug(): void {
		// WP locate_template precedence: a more specific name dominates location.
		// The parent's card-featured.php must beat the child's card.php.
		$this->set_child_theme( true );
		$this->write( $this->tmp . '/child/my-plugin/card.php' );
		$this->write( $this->tmp . '/parent/my-plugin/card-featured.php' );

		$this->assertSame(
			$this->tmp . '/parent/my-plugin/card-featured.php',
			$this->loader()->locate( 'card', 'featured' )
		);
	}

	public function test_falls_back_to_base_slug_when_variant_missing(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$this->assertSame(
			$this->tmp . '/plugin/templates/card.php',
			$this->loader()->locate( 'card', 'featured' )
		);
	}

	public function test_locate_returns_false_when_not_found(): void {
		$this->set_child_theme( false );

		$this->assertFalse( $this->loader()->locate( 'missing' ) );
	}

	public function test_render_loads_with_filtered_args(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		add_filter(
			'my_plugin/template_args',
			static function ( array $args ): array {
				$args['injected'] = true;
				return $args;
			}
		);

		$this->loader()->render( 'card', null, [ 'title' => 'Hi' ] );

		$loaded = $GLOBALS['wp_framework_test_loaded_templates'];
		$this->assertCount( 1, $loaded );
		$this->assertSame( $this->tmp . '/plugin/templates/card.php', $loaded[0]['file'] );
		$this->assertSame( [ 'title' => 'Hi', 'injected' => true ], $loaded[0]['args'] );
	}

	public function test_locate_does_not_render(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$this->loader()->locate( 'card' );

		$this->assertSame( [], $GLOBALS['wp_framework_test_loaded_templates'] );
	}

	public function test_get_returns_rendered_output(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/greeting.php', '<?php echo "Hi " . $args["who"]; ?>' );

		$this->assertSame(
			'Hi World',
			$this->loader()->get( 'greeting', null, [ 'who' => 'World' ] )
		);
	}

	public function test_get_returns_empty_when_not_found(): void {
		$this->set_child_theme( false );

		$this->assertSame( '', $this->loader()->get( 'missing' ) );
	}

	public function test_located_template_filter_can_override_result(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		add_filter( 'my_plugin/located_template', static fn(): string => '/forced/path.php' );

		$this->assertSame( '/forced/path.php', $this->loader()->locate( 'card' ) );
	}

	public function test_template_paths_filter_can_add_a_source(): void {
		$this->set_child_theme( false );
		$extra = $this->tmp . '/extra';
		$this->write( $extra . '/card.php' );

		add_filter(
			'my_plugin/template_paths',
			function ( array $paths ) use ( $extra ): array {
				$paths[5] = $extra; // Higher precedence than the package (100).
				return $paths;
			}
		);

		$this->assertSame( $extra . '/card.php', $this->loader()->locate( 'card' ) );
	}

	public function test_result_is_cached_until_cleared(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$loader = $this->loader();
		$this->assertSame( $this->tmp . '/plugin/templates/card.php', $loader->locate( 'card' ) );

		// Remove the file; the cached path is still returned.
		unlink( $this->tmp . '/plugin/templates/card.php' );
		$this->assertSame( $this->tmp . '/plugin/templates/card.php', $loader->locate( 'card' ) );

		// After clearing, the now-missing template resolves to false.
		$loader->clear_cache();
		$this->assertFalse( $loader->locate( 'card' ) );
	}

	public function test_traversal_segments_are_stripped(): void {
		$this->set_child_theme( false );
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		// '../' segments are removed, so this cannot escape the search roots.
		$this->assertFalse( $this->loader()->locate( '../../etc/passwd' ) );
	}
}
