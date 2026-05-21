<?php
/**
 * Tests for Abstract_Post_Type.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcretePostType;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Post_Type
 */
class AbstractPostTypeTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions;
		$wp_actions = [];
	}

	public function test_register_hooks_adds_init_action(): void {
		global $wp_actions;

		$post_type = new ConcretePostType();
		$post_type->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'init', $wp_actions[0]['hook'] );
		$this->assertSame( [ $post_type, 'register_post_type' ], $wp_actions[0]['callback'] );
	}

	public function test_default_args_returns_expected_structure(): void {
		$post_type = new ConcretePostType();
		$defaults  = $post_type->get_default_args();

		$this->assertTrue( $defaults['show_in_rest'] );
		$this->assertTrue( $defaults['public'] );
		$this->assertTrue( $defaults['has_archive'] );
		$this->assertSame( 6, $defaults['menu_position'] );
		$this->assertContains( 'title', $defaults['supports'] );
		$this->assertContains( 'editor', $defaults['supports'] );
		$this->assertContains( 'thumbnail', $defaults['supports'] );
	}

	public function test_get_slug_returns_expected_value(): void {
		$this->assertSame( 'test-post-type', ConcretePostType::get_slug() );
	}
}
