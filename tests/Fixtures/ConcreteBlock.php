<?php
/**
 * Concrete block for testing (no block.json directory).
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Block;

class ConcreteBlock extends Abstract_Block {

	public static function get_name(): string {
		return 'test-plugin/hero';
	}

	public function render( array $attributes, string $content, \WP_Block $block ): string {
		return '<h1>' . ( $attributes['title'] ?? '' ) . '</h1>';
	}
}
