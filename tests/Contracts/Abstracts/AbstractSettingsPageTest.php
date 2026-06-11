<?php
/**
 * AbstractSettingsPage tests.
 *
 * Integration tests against real WordPress (wp-env): asserts the three hooks
 * are wired, settings register with the Settings API, and the page is added as
 * a submenu under its parent.
 *
 * @package rtCamp\WPFramework\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Contracts\Abstracts;

use rtCamp\WPFramework\Contracts\Abstracts\AbstractSettingsPage;
use rtCamp\WPFramework\Tests\TestCase;

final class AbstractSettingsPageTest extends TestCase {

	private const SLUG   = 'wpf-test-settings';
	private const OPTION = 'wpf_test_option';

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$GLOBALS['menu']    = [];
		$GLOBALS['submenu'] = [];
	}

	public function tear_down(): void {
		unregister_setting( self::SLUG, self::OPTION );

		parent::tear_down();
	}

	private function settings_page(): AbstractSettingsPage {
		return new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'wpf-test-settings';
			}

			protected function get_page_title(): string {
				return 'WPF Settings';
			}

			protected function get_menu_title(): string {
				return 'WPF Settings';
			}

			protected function get_settings(): array {
				return [
					'wpf_test_option' => [
						'type'    => 'string',
						'default' => 'x',
					],
				];
			}

			public function render(): void {
				echo 'form';
			}
		};
	}

	public function test_register_hooks_wires_menu_settings_and_rest(): void {
		$page = $this->settings_page();
		$page->register_hooks();

		$this->assertNotFalse( has_action( 'admin_menu', [ $page, 'register_page' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $page, 'register_settings' ] ) );
		$this->assertNotFalse( has_action( 'rest_api_init', [ $page, 'register_settings' ] ) );
	}

	public function test_register_settings_registers_the_setting(): void {
		$this->settings_page()->register_settings();

		$this->assertArrayHasKey( self::OPTION, get_registered_settings() );
	}

	public function test_register_page_adds_submenu_under_settings(): void {
		$this->settings_page()->register_page();

		$slugs = wp_list_pluck( $GLOBALS['submenu']['options-general.php'] ?? [], 2 );
		$this->assertContains( self::SLUG, $slugs );
	}
}
