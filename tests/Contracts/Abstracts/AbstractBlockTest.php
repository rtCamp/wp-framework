<?php
/**
 * AbstractBlock tests.
 *
 * Integration tests against real WordPress (wp-env): asserts the block is
 * registered with WP_Block_Type_Registry and wired to the instance's render
 * callback.
 *
 * @package rtCamp\WPFramework\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Contracts\Abstracts;

use rtCamp\WPFramework\Contracts\Abstracts\AbstractBlock;
use rtCamp\WPFramework\Tests\TestCase;
use WP_Block_Type_Registry;

final class AbstractBlockTest extends TestCase {

	private const BLOCK_NAME = 'wp-framework-test/block';

	private function block(): AbstractBlock {
		return new class() extends AbstractBlock {
			public static function get_name(): string {
				return 'wp-framework-test/block';
			}

			public function render( array $attributes, string $content, \WP_Block $block ): string {
				return '<div class="wpf-block">rendered</div>';
			}
		};
	}

	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			unregister_block_type( self::BLOCK_NAME );
		}

		parent::tear_down();
	}

	public function test_register_hooks_registers_block_on_init(): void {
		$block = $this->block();
		$block->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $block, 'register_block' ] ) );
	}

	public function test_register_block_registers_the_block_type(): void {
		$this->block()->register_block();

		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME )
		);
	}

	public function test_registered_block_uses_the_instance_render_callback(): void {
		$block = $this->block();
		$block->register_block();

		$type = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );

		$this->assertSame( [ $block, 'render' ], $type->render_callback );
	}
}
