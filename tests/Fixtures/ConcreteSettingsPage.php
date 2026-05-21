<?php
/**
 * Concrete settings page for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Settings_Page;

class ConcreteSettingsPage extends Abstract_Settings_Page {

	public static function get_slug(): string {
		return 'test-settings';
	}

	protected function get_page_title(): string {
		return 'Test Settings';
	}

	protected function get_menu_title(): string {
		return 'Test';
	}

	protected function get_settings(): array {
		return [
			'test_option_one' => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'test_option_two' => [
				'type'    => 'string',
				'default' => 'hello',
			],
		];
	}

	public function render(): void {
		echo '<div>Settings Page</div>';
	}
}
