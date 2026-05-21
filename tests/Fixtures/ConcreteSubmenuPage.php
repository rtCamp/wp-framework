<?php
/**
 * Concrete admin page for testing (submenu).
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Admin_Page;

class ConcreteSubmenuPage extends Abstract_Admin_Page {

	public static function get_slug(): string {
		return 'test-submenu-page';
	}

	protected function get_page_title(): string {
		return 'Test Submenu Page';
	}

	protected function get_menu_title(): string {
		return 'Test Submenu';
	}

	public function render(): void {
		echo 'submenu rendered';
	}

	protected function get_parent_slug(): ?string {
		return 'options-general.php';
	}
}
