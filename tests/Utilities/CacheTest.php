<?php
/**
 * Tests for the Cache utility.
 *
 * @package RtCamp\WPToolkit\Tests\Utilities
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Tests\Utilities;

use RtCamp\WPToolkit\Utilities\Cache;
use WP_UnitTestCase;

/**
 * Cache utility tests.
 *
 * Mirrors the three coverage scenarios from the issue snippet, adapted to
 * run against wp-tests-lib (so `wp_cache_*` are real, not stubbed):
 *
 *   1. set / get round-trip,
 *   2. delete removes the entry,
 *   3. flush_group clears keys in the named group.
 *
 * The issue's third test asserts `flush_group()` returns `false` when the
 * underlying `wp_cache_flush_group()` is unavailable. wp-tests-lib runs
 * WP latest (6.1+), where the function always exists, so the assertion is
 * inverted here: the function IS available and a flushed key reads back
 * as `false`. The "function missing" branch (a single-line `function_exists`
 * gate in `Cache::flush_group()`) is verified by code review — see
 * `.claude/issues/03-cache-utility.md` "Open questions".
 *
 * `WP_UnitTestCase::tear_down()` flushes WP's object cache between tests,
 * so each test starts with a clean slate.
 *
 * @since 1.0.0
 */
class CacheTest extends WP_UnitTestCase {

	/**
	 * Round-trip: set then get returns the stored value.
	 */
	public function test_set_then_get_returns_value(): void {
		Cache::get_instance()->set( 'k1', 'value-1', 'demo' );

		$this->assertSame( 'value-1', Cache::get_instance()->get( 'k1', 'demo' ) );
	}

	/**
	 * Delete removes the entry from the group.
	 */
	public function test_delete_removes_entry(): void {
		Cache::get_instance()->set( 'k2', 'value-2', 'demo' );
		Cache::get_instance()->delete( 'k2', 'demo' );

		$this->assertFalse( Cache::get_instance()->get( 'k2', 'demo' ) );
	}

	/**
	 * Flush group clears keys in the named group when wp_cache_flush_group is available.
	 */
	public function test_flush_group_clears_keys_when_function_is_available(): void {
		Cache::get_instance()->set( 'k3', 'value-3', 'demo' );

		$result = Cache::get_instance()->flush_group( 'demo' );

		$this->assertTrue( $result );
		$this->assertFalse( Cache::get_instance()->get( 'k3', 'demo' ) );
	}
}
