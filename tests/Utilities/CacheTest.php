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

	/**
	 * Returns the cached value without invoking the callback when the key already exists.
	 */
	public function test_remember_returns_cached_value_on_hit(): void {
		Cache::get_instance()->set( 'r1', 'cached', 'demo' );

		$calls  = 0;
		$result = Cache::get_instance()->remember(
			'r1',
			function () use ( &$calls ): string {
				++$calls;
				return 'generated';
			},
			'demo',
			300
		);

		$this->assertSame( 'cached', $result );
		$this->assertSame( 0, $calls );
	}

	/**
	 * Calls the callback and stores its return value at both the fresh and stale keys on a cache miss.
	 */
	public function test_remember_calls_callback_and_stores_on_miss(): void {
		$result = Cache::get_instance()->remember(
			'r2',
			fn(): string => 'generated',
			'demo',
			300
		);

		$this->assertSame( 'generated', $result );
		$this->assertSame( 'generated', Cache::get_instance()->get( 'r2', 'demo' ) );
		$this->assertSame( 'generated', Cache::get_instance()->get( 'r2_stale', 'demo' ) );
	}

	/**
	 * Calls the callback only once for sequential calls targeting the same key.
	 *
	 * Verifies the stored-value path: the second call hits the cache populated
	 * by the first call and does not invoke the callback again.
	 */
	public function test_remember_calls_callback_only_once_for_sequential_calls(): void {
		$calls = 0;
		$make  = function () use ( &$calls ): string {
			++$calls;
			return 'once';
		};

		Cache::get_instance()->remember( 'r3', $make, 'demo', 300 );
		Cache::get_instance()->remember( 'r3', $make, 'demo', 300 );

		$this->assertSame( 1, $calls );
	}

	/**
	 * Serves stale data immediately when the fresh key has expired.
	 *
	 * Simulates expiry by deleting the fresh key while the stale key remains.
	 * Verifies that the stale value is returned and the callback is invoked
	 * exactly once (background regeneration in the same request).
	 */
	public function test_remember_serves_stale_on_fresh_key_miss(): void {
		Cache::get_instance()->remember( 'r4', fn(): string => 'initial', 'demo', 300 );
		Cache::get_instance()->delete( 'r4', 'demo' );

		$calls  = 0;
		$result = Cache::get_instance()->remember(
			'r4',
			function () use ( &$calls ): string {
				++$calls;
				return 'regenerated';
			},
			'demo',
			300
		);

		$this->assertSame( 'initial', $result );
		$this->assertSame( 1, $calls );
	}
}
