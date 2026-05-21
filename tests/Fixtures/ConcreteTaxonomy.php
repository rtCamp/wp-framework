<?php
/**
 * Concrete taxonomy for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Taxonomy;

class ConcreteTaxonomy extends Abstract_Taxonomy {

	public static function get_slug(): string {
		return 'test-taxonomy';
	}

	public static function get_object_types(): array {
		return [ 'test-post-type' ];
	}

	public function register_taxonomy(): void {
		// No-op for testing.
	}

	/**
	 * Expose protected default_args for testing.
	 */
	public function get_default_args(): array {
		return $this->default_args();
	}
}
