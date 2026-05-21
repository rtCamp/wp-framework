<?php
/**
 * Tests for Abstract_Block.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteBlock;
use WPFramework\Tests\Fixtures\ConcreteBlockWithDir;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Block
 */
class AbstractBlockTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions;
		$wp_actions = [];
	}

	public function test_register_hooks_adds_init_action(): void {
		global $wp_actions;

		$block = new ConcreteBlock();
		$block->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'init', $wp_actions[0]['hook'] );
		$this->assertSame( [ $block, 'register_block' ], $wp_actions[0]['callback'] );
	}

	public function test_register_block_without_dir(): void {
		global $wp_actions;

		$block = new ConcreteBlock();
		$block->register_block();

		// register_block_type stub stores calls in $wp_actions.
		$registered = end( $wp_actions );
		$this->assertSame( 'register_block_type', $registered['hook'] );
		$this->assertSame( 'test-plugin/hero', $registered['callback'] );
		$this->assertArrayHasKey( 'render_callback', $registered['args'] );
		$this->assertSame( [ $block, 'render' ], $registered['args']['render_callback'] );
	}

	public function test_register_block_with_dir(): void {
		global $wp_actions;

		$block = new ConcreteBlockWithDir();
		$block->register_block();

		$registered = end( $wp_actions );
		$this->assertSame( 'register_block_type', $registered['hook'] );
		$this->assertSame( '/path/to/build/blocks/hero', $registered['callback'] );
		$this->assertArrayHasKey( 'render_callback', $registered['args'] );
	}

	public function test_get_name_returns_expected(): void {
		$this->assertSame( 'test-plugin/hero', ConcreteBlock::get_name() );
	}

	public function test_render_returns_html(): void {
		$block  = new ConcreteBlock();
		$wp_block = new \WP_Block();
		$output = $block->render( [ 'title' => 'Hello' ], '', $wp_block );

		$this->assertSame( '<h1>Hello</h1>', $output );
	}
}
