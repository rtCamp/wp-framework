<?php
/**
 * AbstractSettingsPage tests.
 *
 * Asserts the menu-slug contract of register_page(): the get_menu_slug()
 * seam defaults to static::get_slug() and an override redirects the slug
 * registered with the admin menu. Settings/rendering behaviour is covered
 * by concrete-subclass tests (see FeatureSelectorSettingsPageTest).
 *
 * @package rtCamp\WPFramework\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Contracts\Abstracts;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Contracts\Abstracts\AbstractSettingsPage;

final class AbstractSettingsPageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_framework_test_admin_pages'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['wp_framework_test_admin_pages'] = [];
	}

	/**
	 * Build a minimal concrete AbstractSettingsPage.
	 */
	private function basic_page(): AbstractSettingsPage {
		return new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'demo-settings';
			}
			protected function get_page_title(): string {
				return 'Demo Settings';
			}
			protected function get_menu_title(): string {
				return 'Demo';
			}
			protected function get_settings(): array {
				return [];
			}
			public function render(): void {}
		};
	}

	public function test_register_page_menu_slug_defaults_to_get_slug(): void {
		$this->basic_page()->register_page();

		$pages = $GLOBALS['wp_framework_test_admin_pages'];

		$this->assertCount( 1, $pages );
		$this->assertSame( 'demo-settings', $pages[0]['menu_slug'] );
	}

	public function test_register_page_uses_overridden_menu_slug(): void {
		$page = new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'demo-settings';
			}
			protected function get_menu_slug(): string {
				return 'instance-derived-slug';
			}
			protected function get_page_title(): string {
				return 'Demo Settings';
			}
			protected function get_menu_title(): string {
				return 'Demo';
			}
			protected function get_settings(): array {
				return [];
			}
			public function render(): void {}
		};

		$page->register_page();

		$this->assertSame(
			'instance-derived-slug',
			$GLOBALS['wp_framework_test_admin_pages'][0]['menu_slug']
		);
	}
}
