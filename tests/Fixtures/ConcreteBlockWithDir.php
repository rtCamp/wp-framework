<?php
/**
 * Concrete block with directory for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Block;

class ConcreteBlockWithDir extends Abstract_Block {

	public static function get_name(): string {
		return 'test-plugin/hero';
	}

	public function render( array $attributes, string $content, \WP_Block $block ): string {
		return '<div>block</div>';
	}

	protected function get_block_dir(): ?string {
		return '/path/to/build/blocks/hero';
	}
}
