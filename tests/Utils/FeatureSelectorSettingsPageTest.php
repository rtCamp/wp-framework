<?php
/**
 * FeatureSelectorSettingsPage utility tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Utils;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Utils\FeatureSelector;
use rtCamp\WPFramework\Utils\FeatureSelectorSettingsPage;

/**
 * Tests for FeatureSelectorSettingsPage.
 *
 * The Settings API and admin functions are stubbed in tests/bootstrap.php as
 * recording stores (with a functional do_settings_sections); setUp()/tearDown()
 * reset them between tests.
 */
final class FeatureSelectorSettingsPageTest extends TestCase {

	/**
	 * Selector backing the page under test (context `my-plugin`).
	 */
	private FeatureSelector $selector;

	/**
	 * Page under test.
	 */
	private FeatureSelectorSettingsPage $page;

	protected function setUp(): void {
		$this->reset_globals();

		$this->selector = new FeatureSelector( 'my-plugin' );
		$this->page     = new FeatureSelectorSettingsPage( $this->selector );
	}

	protected function tearDown(): void {
		$this->reset_globals();
	}

	private function reset_globals(): void {
		$GLOBALS['_wp_options']                              = [];
		$GLOBALS['wp_framework_test_filters']                = [];
		$GLOBALS['wp_framework_test_registered_settings']    = [];
		$GLOBALS['wp_framework_test_settings_sections']      = [];
		$GLOBALS['wp_framework_test_settings_fields']        = [];
		$GLOBALS['wp_framework_test_settings_fields_calls']  = [];
		$GLOBALS['wp_framework_test_admin_pages']            = [];
		$GLOBALS['wp_framework_test_current_user_can']       = true;
	}

	// --- hooks ---------------------------------------------------------------

	public function test_register_hooks_keeps_field_registration_off_rest_api_init(): void {
		$this->page->register_hooks();

		$hooks = $GLOBALS['wp_framework_test_filters'];

		$this->assertArrayHasKey( 'admin_menu', $hooks );
		$this->assertArrayHasKey( 'admin_init', $hooks );
		$this->assertArrayHasKey( 'rest_api_init', $hooks );

		// add_settings_section/field are not defined during REST requests —
		// register_fields must be wired to admin_init only.
		$this->assertContains( [ $this->page, 'register_fields' ], $hooks['admin_init'] );
		$this->assertNotContains( [ $this->page, 'register_fields' ], $hooks['rest_api_init'] );
	}

	// --- page registration -----------------------------------------------------

	public function test_register_page_uses_context_derived_slug(): void {
		$this->page->register_page();

		$pages = $GLOBALS['wp_framework_test_admin_pages'];

		$this->assertCount( 1, $pages );
		$this->assertSame( 'options-general.php', $pages[0]['parent_slug'] );
		$this->assertSame( 'manage_options', $pages[0]['capability'] );
		$this->assertSame( 'my-plugin-features', $pages[0]['menu_slug'] );
		$this->assertSame( 'My Plugin Features', $pages[0]['page_title'] );
	}

	public function test_menu_slug_slugifies_a_non_slug_context(): void {
		$page = new FeatureSelectorSettingsPage( new FeatureSelector( 'My Plugin v2.0' ) );

		$page->register_page();

		$this->assertSame( 'my-plugin-v2-0-features', $GLOBALS['wp_framework_test_admin_pages'][0]['menu_slug'] );
	}

	public function test_empty_context_page_slug_is_bare_features(): void {
		$page = new FeatureSelectorSettingsPage( new FeatureSelector() );

		$page->register_page();

		$this->assertSame( 'features', $GLOBALS['wp_framework_test_admin_pages'][0]['menu_slug'] );
		$this->assertSame( 'Features', $GLOBALS['wp_framework_test_admin_pages'][0]['page_title'] );
	}

	// --- settings registration ---------------------------------------------------

	public function test_admin_init_registers_one_boolean_setting_per_flag(): void {
		$this->selector->register( [ 'dark-mode', 'beta-search' ] );
		$this->page->register_hooks();

		do_action( 'admin_init' );

		$settings = $GLOBALS['wp_framework_test_registered_settings']['my_plugin_features'];

		$this->assertSame(
			[ 'my_plugin_feature_dark_mode', 'my_plugin_feature_beta_search' ],
			array_keys( $settings )
		);

		$args = $settings['my_plugin_feature_dark_mode'];

		$this->assertSame( 'boolean', $args['type'] );
		$this->assertTrue( $args['default'] );
		$this->assertTrue( $args['sanitize_callback']( '1' ) );
		$this->assertFalse( $args['sanitize_callback']( '' ) );
	}

	public function test_admin_init_skips_registering_constant_locked_flags(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_REG_LOCKED' ) ) {
			define( 'MY_PLUGIN_FEATURE_REG_LOCKED', true );
		}

		$this->selector->register( [ 'reg-locked', 'free-flag' ] );
		$this->page->register_hooks();

		do_action( 'admin_init' );

		$settings = $GLOBALS['wp_framework_test_registered_settings']['my_plugin_features'];

		// The free flag is registered; the locked flag is not, so options.php
		// never overwrites its stored option on save.
		$this->assertArrayHasKey( 'my_plugin_feature_free_flag', $settings );
		$this->assertArrayNotHasKey( 'my_plugin_feature_reg_locked', $settings );

		// It still renders as a (disabled) field so the lock stays visible.
		$this->assertArrayHasKey(
			'reg-locked',
			$GLOBALS['wp_framework_test_settings_fields']['my-plugin-features']['my_plugin_features_section']
		);
	}

	public function test_flags_registered_after_hooks_still_appear(): void {
		// Consumers register flags at plugins_loaded/init — after register_hooks()
		// but before admin_init fires. get_settings() must evaluate lazily.
		$this->page->register_hooks();
		$this->selector->register( [ 'late-flag' ] );

		do_action( 'admin_init' );

		$this->assertArrayHasKey(
			'my_plugin_feature_late_flag',
			$GLOBALS['wp_framework_test_registered_settings']['my_plugin_features']
		);
	}

	public function test_register_fields_adds_section_and_one_field_per_flag(): void {
		$this->selector->register(
			[
				'dark-mode' => [
					'name'        => 'Dark Mode',
					'description' => 'Switch the UI to dark.',
				],
				'beta-search',
			]
		);

		$this->page->register_fields();

		$this->assertArrayHasKey(
			'my_plugin_features_section',
			$GLOBALS['wp_framework_test_settings_sections']['my-plugin-features']
		);

		$fields = $GLOBALS['wp_framework_test_settings_fields']['my-plugin-features']['my_plugin_features_section'];

		$this->assertSame( [ 'dark-mode', 'beta-search' ], array_keys( $fields ) );
		$this->assertSame( 'Dark Mode', $fields['dark-mode']['title'] );
		$this->assertSame( 'Switch the UI to dark.', $fields['dark-mode']['args']['description'] );
	}

	// --- rendering -----------------------------------------------------------------

	public function test_render_field_outputs_named_checkbox_checked_when_enabled(): void {
		$this->selector->register( [ 'dark-mode' ] );
		$this->selector->enable( 'dark-mode' );

		$output = $this->render_field_output( 'dark-mode' );

		$this->assertStringContainsString( 'name="my_plugin_feature_dark_mode"', $output );
		$this->assertStringContainsString( 'checked', $output );
		$this->assertStringNotContainsString( 'disabled', $output );
	}

	public function test_render_field_omits_description_paragraph_when_empty(): void {
		$this->selector->register( [ 'dark-mode' ] );

		$this->assertStringNotContainsString( 'class="description"', $this->render_field_output( 'dark-mode' ) );
	}

	public function test_render_field_prints_description_when_present(): void {
		$this->selector->register( [ 'dark-mode' => [ 'description' => 'Switch the UI to dark.' ] ] );
		$this->selector->disable( 'dark-mode' ); // Default is on; force off so "unchecked" is meaningful.

		$output = $this->render_field_output( 'dark-mode' );

		$this->assertStringContainsString( 'Switch the UI to dark.', $output );
		$this->assertStringNotContainsString( 'checked', $output );
	}

	public function test_render_field_locks_checkbox_when_constant_defined(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_LOCKED_FLAG' ) ) {
			define( 'MY_PLUGIN_FEATURE_LOCKED_FLAG', true );
		}

		$this->selector->register( [ 'locked-flag' ] );

		$output = $this->render_field_output( 'locked-flag' );

		$this->assertStringContainsString( 'disabled', $output );
		$this->assertStringContainsString( 'checked', $output ); // Constant value is reflected.
		$this->assertStringContainsString( 'MY_PLUGIN_FEATURE_LOCKED_FLAG', $output );
	}

	public function test_render_outputs_form_with_group_and_fields(): void {
		$this->selector->register( [ 'dark-mode' ] );
		$this->page->register_fields();

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'action="options.php"', $output );
		$this->assertStringContainsString( 'name="my_plugin_feature_dark_mode"', $output );
		$this->assertStringContainsString( 'type="submit"', $output );
		$this->assertSame( [ 'my_plugin_features' ], $GLOBALS['wp_framework_test_settings_fields_calls'] );
	}

	public function test_render_bails_for_user_without_capability(): void {
		$GLOBALS['wp_framework_test_current_user_can'] = false;

		ob_start();
		$this->page->render();

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Capture render_field() output for a registered flag.
	 */
	private function render_field_output( string $slug ): string {
		ob_start();
		$this->page->render_field( $this->selector->get_features()[ $slug ] );

		return (string) ob_get_clean();
	}
}
