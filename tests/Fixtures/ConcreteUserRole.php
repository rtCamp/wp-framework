<?php
/**
 * Concrete user role for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_User_Role;

class ConcreteUserRole extends Abstract_User_Role {

	public static function get_slug(): string {
		return 'test-editor';
	}

	protected function get_display_name(): string {
		return 'Test Editor';
	}

	protected function get_capabilities(): array {
		return [
			'read'       => true,
			'edit_posts' => true,
		];
	}

	protected function get_version(): int {
		return 2;
	}
}
