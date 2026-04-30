<?php
/**
 * Tests for the Transients utility.
 *
 * @package RtCamp\WPToolkit\Tests\Utilities
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Tests\Utilities;

use RtCamp\WPToolkit\Utilities\Transients;
use WP_UnitTestCase;

/**
 * Transients utility tests.
 *
 * Runs against the real WordPress test suite (wp-tests-lib) so the wrapper
 * is exercised end-to-end through `set_transient` / `get_transient` /
 * `delete_transient`. Verifies the three runtime guarantees:
 *
 *   - set/get round-trips a value,
 *   - two instances with different prefixes do not collide,
 *   - delete removes only the issuing instance's entry.
 *
 * @since 1.0.0
 */
class TransientsTest extends WP_UnitTestCase {

	/**
	 * Round-trip: set then get returns the stored value.
	 */
	public function test_set_then_get_returns_value(): void {
		$store = new Transients( 'mod_a' );
		$store->set( 'thing', 42 );

		$this->assertSame( 42, $store->get( 'thing' ) );
	}

	/**
	 * Two instances with different prefixes must not see each other's keys.
	 */
	public function test_two_instances_with_different_prefixes_do_not_collide(): void {
		$store_a = new Transients( 'mod_a' );
		$store_b = new Transients( 'mod_b' );

		$store_a->set( 'shared_key', 'value-from-a' );
		$store_b->set( 'shared_key', 'value-from-b' );

		$this->assertSame( 'value-from-a', $store_a->get( 'shared_key' ) );
		$this->assertSame( 'value-from-b', $store_b->get( 'shared_key' ) );
	}

	/**
	 * Delete must remove only the issuing instance's entry.
	 */
	public function test_delete_removes_only_this_instances_entry(): void {
		$store_a = new Transients( 'mod_a' );
		$store_b = new Transients( 'mod_b' );

		$store_a->set( 'k', 'a' );
		$store_b->set( 'k', 'b' );

		$store_a->delete( 'k' );

		$this->assertFalse( $store_a->get( 'k' ) );
		$this->assertSame( 'b', $store_b->get( 'k' ) );
	}
}
