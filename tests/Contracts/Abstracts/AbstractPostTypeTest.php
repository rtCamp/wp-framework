<?php
/**
 * AbstractPostType tests.
 *
 * Asserts the option-building contract: defaults, label assembly, and the
 * get_custom_options() override merge. WP integration (the actual
 * register_post_type() call) is out of scope — covered by stubs.
 *
 * @package rtCamp\WPFramework\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Contracts\Abstracts;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Contracts\Abstracts\AbstractPostType;

final class AbstractPostTypeTest extends TestCase {

	/**
	 * Build a basic concrete AbstractPostType.
	 */
	private function basic_post_type(): AbstractPostType {
		return new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'book';
			}
			public function get_singular_label(): string {
				return 'Book';
			}
			public function get_plural_label(): string {
				return 'Books';
			}
			public function get_menu_icon(): string {
				return 'dashicons-book';
			}
		};
	}

	public function test_get_options_contains_the_documented_defaults(): void {
		$options = $this->basic_post_type()->get_options();

		$this->assertTrue( $options['public'] );
		$this->assertTrue( $options['has_archive'] );
		$this->assertTrue( $options['show_in_rest'] );
		$this->assertTrue( $options['show_ui'] );
		$this->assertSame( 'dashicons-book', $options['menu_icon'] );
		$this->assertFalse( $options['hierarchical'] );
		$this->assertArrayHasKey( 'labels', $options );
	}

	public function test_get_labels_uses_singular_and_plural(): void {
		$labels = $this->basic_post_type()->get_labels();

		$this->assertSame( 'Books', $labels['name'] );
		$this->assertSame( 'Book', $labels['singular_name'] );
	}

	public function test_get_options_excludes_menu_position_when_null(): void {
		$options = $this->basic_post_type()->get_options();

		$this->assertArrayNotHasKey( 'menu_position', $options );
	}

	public function test_get_options_includes_menu_position_when_set(): void {
		$post_type = new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'book';
			}
			public function get_singular_label(): string {
				return 'Book';
			}
			public function get_plural_label(): string {
				return 'Books';
			}
			public function get_menu_icon(): string {
				return 'dashicons-book';
			}
			public function get_menu_position(): ?int {
				return 25;
			}
		};

		$this->assertSame( 25, $post_type->get_options()['menu_position'] );
	}

	public function test_custom_options_override_defaults_and_add_new_keys(): void {
		$post_type = new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'product';
			}
			public function get_singular_label(): string {
				return 'Product';
			}
			public function get_plural_label(): string {
				return 'Products';
			}
			public function get_menu_icon(): string {
				return 'dashicons-cart';
			}
			protected function get_custom_options(): array {
				return [
					'has_archive'     => false,         // Override.
					'capability_type' => 'product',     // New key.
				];
			}
		};

		$options = $post_type->get_options();

		$this->assertFalse( $options['has_archive'] );
		$this->assertSame( 'product', $options['capability_type'] );
		$this->assertTrue( $options['public'] ); // Untouched default.
	}

	public function test_is_hierarchical_defaults_to_false(): void {
		$this->assertFalse( $this->basic_post_type()->is_hierarchical() );
	}

	public function test_get_supported_taxonomies_defaults_to_empty_array(): void {
		$this->assertSame( [], $this->basic_post_type()->get_supported_taxonomies() );
	}

	public function test_get_editor_supports_returns_sane_defaults(): void {
		$supports = $this->basic_post_type()->get_editor_supports();

		$this->assertContains( 'title', $supports );
		$this->assertContains( 'editor', $supports );
		$this->assertContains( 'thumbnail', $supports );
	}
}
