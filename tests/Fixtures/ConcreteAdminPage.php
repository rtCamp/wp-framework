<?php
/**
 * Concrete admin page for testing (top-level).
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Admin_Page;

class ConcreteAdminPage extends Abstract_Admin_Page {

	public static function get_slug(): string {
		return 'test-admin-page';
	}

	protected function get_page_title(): string {
		return 'Test Admin Page';
	}

	protected function get_menu_title(): string {
		return 'Test Page';
	}

	public function render(): void {
		echo 'rendered';
	}
}
