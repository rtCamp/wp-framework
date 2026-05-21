<?php
/**
 * Tests for Abstract_Taxonomy.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteTaxonomy;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Taxonomy
 */
class AbstractTaxonomyTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions;
		$wp_actions = [];
	}

	public function test_register_hooks_adds_init_action(): void {
		global $wp_actions;

		$taxonomy = new ConcreteTaxonomy();
		$taxonomy->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'init', $wp_actions[0]['hook'] );
		$this->assertSame( [ $taxonomy, 'register_taxonomy' ], $wp_actions[0]['callback'] );
	}

	public function test_default_args_returns_expected_structure(): void {
		$taxonomy = new ConcreteTaxonomy();
		$defaults = $taxonomy->get_default_args();

		$this->assertFalse( $defaults['hierarchical'] );
		$this->assertTrue( $defaults['query_var'] );
		$this->assertTrue( $defaults['show_admin_column'] );
		$this->assertTrue( $defaults['show_in_rest'] );
		$this->assertTrue( $defaults['show_ui'] );
	}

	public function test_get_slug_returns_expected_value(): void {
		$this->assertSame( 'test-taxonomy', ConcreteTaxonomy::get_slug() );
	}

	public function test_get_object_types_returns_array(): void {
		$this->assertSame( [ 'test-post-type' ], ConcreteTaxonomy::get_object_types() );
	}
}
