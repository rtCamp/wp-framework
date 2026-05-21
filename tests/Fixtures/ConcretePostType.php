<?php
/**
 * Concrete post type for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Post_Type;

class ConcretePostType extends Abstract_Post_Type {

	public static function get_slug(): string {
		return 'test-post-type';
	}

	public function register_post_type(): void {
		// No-op for testing.
	}

	/**
	 * Expose protected default_args for testing.
	 */
	public function get_default_args(): array {
		return $this->default_args();
	}
}
