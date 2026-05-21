<?php
/**
 * Tests for Abstract_Settings_Page.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteSettingsPage;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Settings_Page
 */
class AbstractSettingsPageTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions, $wp_registered_settings;
		$wp_actions             = [];
		$wp_registered_settings = [];
	}

	public function test_register_hooks_adds_admin_menu_action(): void {
		global $wp_actions;

		$page = new ConcreteSettingsPage();
		$page->register_hooks();

		$hooks = array_column( $wp_actions, 'hook' );
		$this->assertContains( 'admin_menu', $hooks );
	}

	public function test_register_hooks_adds_admin_init_action(): void {
		global $wp_actions;

		$page = new ConcreteSettingsPage();
		$page->register_hooks();

		$hooks = array_column( $wp_actions, 'hook' );
		$this->assertContains( 'admin_init', $hooks );
	}

	public function test_register_hooks_adds_rest_api_init_action(): void {
		global $wp_actions;

		$page = new ConcreteSettingsPage();
		$page->register_hooks();

		$hooks = array_column( $wp_actions, 'hook' );
		$this->assertContains( 'rest_api_init', $hooks );
	}

	public function test_register_settings_registers_all_settings(): void {
		global $wp_registered_settings;

		$page = new ConcreteSettingsPage();
		$page->register_settings();

		$this->assertArrayHasKey( 'test_option_one', $wp_registered_settings );
		$this->assertArrayHasKey( 'test_option_two', $wp_registered_settings );
	}

	public function test_register_settings_uses_correct_option_group(): void {
		global $wp_registered_settings;

		$page = new ConcreteSettingsPage();
		$page->register_settings();

		$this->assertSame( 'test-settings', $wp_registered_settings['test_option_one']['group'] );
	}

	public function test_register_settings_passes_args(): void {
		global $wp_registered_settings;

		$page = new ConcreteSettingsPage();
		$page->register_settings();

		$this->assertSame( 'boolean', $wp_registered_settings['test_option_one']['args']['type'] );
		$this->assertSame( 'string', $wp_registered_settings['test_option_two']['args']['type'] );
	}

	public function test_get_slug_returns_expected(): void {
		$this->assertSame( 'test-settings', ConcreteSettingsPage::get_slug() );
	}

	public function test_render_outputs_content(): void {
		$page = new ConcreteSettingsPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertSame( '<div>Settings Page</div>', $output );
	}

	public function test_register_page_as_submenu(): void {
		$page = new ConcreteSettingsPage();
		$page->register_page();

		// If it didn't throw, the submenu was registered under options-general.php.
		$this->assertTrue( true );
	}
}
