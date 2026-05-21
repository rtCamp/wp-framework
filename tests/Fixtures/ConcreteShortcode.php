<?php
/**
 * Concrete shortcode for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Shortcode;

class ConcreteShortcode extends Abstract_Shortcode {

	public static function get_tag(): string {
		return 'test-shortcode';
	}

	protected function render( array $atts, ?string $content ): string {
		return $atts['title'] ?? '';
	}

	protected function default_atts(): array {
		return [
			'title' => 'Default Title',
		];
	}
}
