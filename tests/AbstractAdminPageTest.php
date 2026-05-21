<?php
/**
 * Tests for Abstract_Admin_Page.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteAdminPage;
use WPFramework\Tests\Fixtures\ConcreteSubmenuPage;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Admin_Page
 */
class AbstractAdminPageTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions;
		$wp_actions = [];
	}

	public function test_register_hooks_adds_admin_menu_action(): void {
		global $wp_actions;

		$page = new ConcreteAdminPage();
		$page->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'admin_menu', $wp_actions[0]['hook'] );
		$this->assertSame( [ $page, 'register_page' ], $wp_actions[0]['callback'] );
	}

	public function test_register_page_as_top_level_menu(): void {
		$page = new ConcreteAdminPage();

		// register_page is void — just ensure it doesn't throw.
		$page->register_page();
		$this->assertTrue( true );
	}

	public function test_register_page_as_submenu(): void {
		$page = new ConcreteSubmenuPage();

		// register_page is void — just ensure it doesn't throw.
		$page->register_page();
		$this->assertTrue( true );
	}

	public function test_get_slug_returns_expected(): void {
		$this->assertSame( 'test-admin-page', ConcreteAdminPage::get_slug() );
	}

	public function test_get_slug_submenu_returns_expected(): void {
		$this->assertSame( 'test-submenu-page', ConcreteSubmenuPage::get_slug() );
	}
}
