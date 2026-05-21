<?php
/**
 * Tests for Abstract_User_Role.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteUserRole;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_User_Role
 */
class AbstractUserRoleTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions, $wp_options, $wp_roles;
		$wp_actions = [];
		$wp_options = [];
		$wp_roles   = [];
	}

	public function test_register_hooks_adds_admin_init_action(): void {
		global $wp_actions;

		$role = new ConcreteUserRole();
		$role->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'admin_init', $wp_actions[0]['hook'] );
		$this->assertSame( [ $role, 'maybe_update_role' ], $wp_actions[0]['callback'] );
	}

	public function test_maybe_update_role_registers_when_no_stored_version(): void {
		global $wp_roles, $wp_options;

		$role = new ConcreteUserRole();
		$role->maybe_update_role();

		// Role should be registered.
		$this->assertArrayHasKey( 'test-editor', $wp_roles );
		$this->assertSame( 'Test Editor', $wp_roles['test-editor']['name'] );
		$this->assertTrue( $wp_roles['test-editor']['capabilities']['read'] );
		$this->assertTrue( $wp_roles['test-editor']['capabilities']['edit_posts'] );

		// Version should be stored.
		$this->assertSame( 2, $wp_options['test-editor_role_version'] );
	}

	public function test_maybe_update_role_skips_when_version_matches(): void {
		global $wp_roles, $wp_options;

		// Simulate already-stored version.
		$wp_options['test-editor_role_version'] = 2;

		$role = new ConcreteUserRole();
		$role->maybe_update_role();

		// Role should NOT be registered (version hasn't changed).
		$this->assertArrayNotHasKey( 'test-editor', $wp_roles );
	}

	public function test_maybe_update_role_updates_when_version_increases(): void {
		global $wp_roles, $wp_options;

		// Simulate older stored version.
		$wp_options['test-editor_role_version'] = 1;

		$role = new ConcreteUserRole();
		$role->maybe_update_role();

		// Role should be re-registered.
		$this->assertArrayHasKey( 'test-editor', $wp_roles );
		$this->assertSame( 2, $wp_options['test-editor_role_version'] );
	}

	public function test_remove_role_deletes_role_and_option(): void {
		global $wp_roles, $wp_options;

		$wp_roles['test-editor']                = [ 'name' => 'Test Editor', 'capabilities' => [] ];
		$wp_options['test-editor_role_version'] = 2;

		$role = new ConcreteUserRole();
		$role->remove_role();

		$this->assertArrayNotHasKey( 'test-editor', $wp_roles );
		$this->assertArrayNotHasKey( 'test-editor_role_version', $wp_options );
	}

	public function test_get_slug_returns_expected(): void {
		$this->assertSame( 'test-editor', ConcreteUserRole::get_slug() );
	}
}
