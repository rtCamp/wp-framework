<?php
/**
 * Tests for Abstract_Shortcode.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteShortcode;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Shortcode
 */
class AbstractShortcodeTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions, $wp_filters;
		$wp_actions = [];
		$wp_filters = [];
	}

	public function test_register_hooks_adds_init_action(): void {
		global $wp_actions;

		$shortcode = new ConcreteShortcode();
		$shortcode->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'init', $wp_actions[0]['hook'] );
		$this->assertSame( [ $shortcode, 'register_shortcode' ], $wp_actions[0]['callback'] );
	}

	public function test_register_shortcode_registers_tag(): void {
		global $wp_filters;

		$shortcode = new ConcreteShortcode();
		$shortcode->register_shortcode();

		$this->assertCount( 1, $wp_filters );
		$this->assertSame( 'shortcode_test-shortcode', $wp_filters[0]['hook'] );
	}

	public function test_shortcode_callback_returns_rendered_output(): void {
		$shortcode = new ConcreteShortcode();
		$output    = $shortcode->shortcode_callback( [ 'title' => 'Hello' ], null );

		$this->assertSame( 'Hello', $output );
	}

	public function test_shortcode_callback_uses_defaults(): void {
		$shortcode = new ConcreteShortcode();
		$output    = $shortcode->shortcode_callback( [], null );

		$this->assertSame( 'Default Title', $output );
	}

	public function test_shortcode_callback_with_content(): void {
		$shortcode = new ConcreteShortcode();
		$output    = $shortcode->shortcode_callback( [ 'title' => 'Hi' ], 'enclosed content' );

		$this->assertSame( 'Hi', $output );
	}

	public function test_get_tag_returns_expected(): void {
		$this->assertSame( 'test-shortcode', ConcreteShortcode::get_tag() );
	}
}
