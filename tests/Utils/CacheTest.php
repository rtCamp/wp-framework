<?php
/**
 * Cache utility tests.
 *
 * @package rtCamp\WPFramework\Tests\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Utils\Cache;

/**
 * Tests for Cache.
 *
 * The wp_cache_* functions are stubbed in tests/bootstrap.php as a functional
 * in-memory store (with $found out-parameter support). setUp()/tearDown()
 * reset the store between tests.
 */
final class CacheTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_wp_cache']                      = [];
		$GLOBALS['_wp_cache_supports_flush_group'] = true;
		Cache::flush_runtime();
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_cache']                      = [];
		$GLOBALS['_wp_cache_supports_flush_group'] = true;
		Cache::flush_runtime();
	}

	// --- get / set / delete / flush_group ------------------------------------

	public function test_set_then_get_returns_stored_value(): void {
		Cache::set( 'k1', 'hello', 'demo' );

		$this->assertSame( 'hello', Cache::get( 'k1', 'demo' ) );
	}

	public function test_get_returns_false_for_missing_key(): void {
		$this->assertFalse( Cache::get( 'missing', 'demo' ) );
	}

	public function test_get_reports_found_for_stored_false(): void {
		Cache::set( 'k4', false, 'demo' );

		$found = null;
		$value = Cache::get( 'k4', 'demo', false, $found );

		$this->assertFalse( $value );
		$this->assertTrue( $found ); // stored false is a hit, not a miss
	}

	public function test_get_reports_not_found_for_missing_key(): void {
		$found = null;
		Cache::get( 'nope', 'demo', false, $found );

		$this->assertFalse( $found );
	}

	public function test_delete_removes_entry(): void {
		Cache::set( 'k2', 'value', 'demo' );
		Cache::delete( 'k2', 'demo' );

		$this->assertFalse( Cache::get( 'k2', 'demo' ) );
	}

	public function test_flush_group_clears_all_keys_in_group(): void {
		Cache::set( 'k3', 'value', 'demo' );

		$result = Cache::flush_group( 'demo' );

		$this->assertTrue( $result );
		$this->assertFalse( Cache::get( 'k3', 'demo' ) );
	}

	public function test_flush_group_returns_false_when_backend_lacks_support(): void {
		Cache::set( 'k5', 'value', 'demo' );
		$GLOBALS['_wp_cache_supports_flush_group'] = false; // backend cannot flush groups

		$result = Cache::flush_group( 'demo' );

		$this->assertFalse( $result );                            // capability gate trips
		$this->assertSame( 'value', Cache::get( 'k5', 'demo' ) ); // group left intact
	}

	// --- request-level (runtime) cache ---------------------------------------

	public function test_get_prefers_runtime_over_object_cache(): void {
		Cache::set( 'rk', 'runtime-val', 'demo' );
		$GLOBALS['_wp_cache']['demo']['rk'] = 'oc-val'; // diverge the backend behind the runtime layer

		$this->assertSame( 'runtime-val', Cache::get( 'rk', 'demo' ) );
	}

	public function test_force_bypasses_runtime_cache(): void {
		Cache::set( 'rk', 'runtime-val', 'demo' );
		$GLOBALS['_wp_cache']['demo']['rk'] = 'oc-val';

		$this->assertSame( 'oc-val', Cache::get( 'rk', 'demo', true ) );
	}

	public function test_object_cache_hit_promotes_into_runtime(): void {
		$GLOBALS['_wp_cache']['demo']['rk'] = 'oc-val'; // present in the backend, not yet in the runtime layer

		$this->assertSame( 'oc-val', Cache::get( 'rk', 'demo' ) ); // first read promotes it

		unset( $GLOBALS['_wp_cache']['demo']['rk'] );               // backend loses it

		$this->assertSame( 'oc-val', Cache::get( 'rk', 'demo' ) );  // still served from the runtime layer
	}

	public function test_flush_runtime_drops_request_layer(): void {
		Cache::set( 'rk', 'runtime-val', 'demo' );
		$GLOBALS['_wp_cache']['demo']['rk'] = 'oc-val';

		Cache::flush_runtime();

		$this->assertSame( 'oc-val', Cache::get( 'rk', 'demo' ) ); // falls through to the backend now
	}

	public function test_delete_clears_runtime_layer(): void {
		Cache::set( 'rk', 'A', 'demo' );
		Cache::delete( 'rk', 'demo' );
		$GLOBALS['_wp_cache']['demo']['rk'] = 'B'; // backend repopulated by another process

		// 'A' would be returned if delete() had left the runtime entry behind.
		$this->assertSame( 'B', Cache::get( 'rk', 'demo' ) );
	}

	public function test_flush_group_clears_runtime_layer(): void {
		Cache::set( 'rk', 'A', 'demo' );
		$GLOBALS['_wp_cache']['demo']['rk'] = 'A'; // ensure backend matches before the flush

		Cache::flush_group( 'demo' );
		$GLOBALS['_wp_cache']['demo']['rk'] = 'B'; // backend repopulated after the flush

		// 'A' would be returned if flush_group() had left the runtime group behind.
		$this->assertSame( 'B', Cache::get( 'rk', 'demo' ) );
	}

	public function test_class_is_extendable(): void {
		// Cache is deliberately non-final so downstream packages can extend it
		// (e.g. to override the lock/stale constants). Guard that invariant.
		$this->assertFalse( ( new \ReflectionClass( Cache::class ) )->isFinal() );
	}

	// --- remember(): hot path ------------------------------------------------

	public function test_remember_returns_cached_value_without_calling_callback(): void {
		Cache::set( 'r1', 'cached', 'demo' );

		$calls  = 0;
		$result = Cache::remember(
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

	#[DataProvider( 'falsy_values_provider' )]
	public function test_remember_caches_falsy_callback_results( string $key, mixed $falsy ): void {
		$calls = 0;
		$make  = function () use ( &$calls, $falsy ): mixed {
			++$calls;
			return $falsy;
		};

		$first  = Cache::remember( $key, $make, 'demo', 300 );
		$second = Cache::remember( $key, $make, 'demo', 300 );

		$this->assertSame( $falsy, $first );
		$this->assertSame( $falsy, $second );
		$this->assertSame( 1, $calls ); // stored falsy value is a hit, not a miss — no regeneration
	}

	/**
	 * Falsy return values that must be cached rather than treated as a miss.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function falsy_values_provider(): array {
		return [
			'false'        => [ 'r5_false', false ],
			'zero int'     => [ 'r5_zero', 0 ],
			'empty string' => [ 'r5_empty', '' ],
		];
	}

	// --- remember(): cold start (both fresh and stale absent) ----------------

	public function test_remember_calls_callback_on_miss_and_stores_both_keys(): void {
		$result = Cache::remember( 'r2', fn(): string => 'generated', 'demo', 300 );

		$this->assertSame( 'generated', $result );
		$this->assertSame( 'generated', Cache::get( 'r2', 'demo' ) );
		$this->assertSame( 'generated', Cache::get( 'r2_stale', 'demo' ) );
	}

	public function test_remember_calls_callback_only_once_for_sequential_calls(): void {
		$calls = 0;
		$make  = function () use ( &$calls ): string {
			++$calls;
			return 'once';
		};

		Cache::remember( 'r3', $make, 'demo', 300 );
		Cache::remember( 'r3', $make, 'demo', 300 );

		$this->assertSame( 1, $calls );
	}

	public function test_remember_releases_lock_and_caches_nothing_when_callback_throws(): void {
		try {
			Cache::remember(
				'r6',
				static function (): never {
					throw new \RuntimeException( 'boom' );
				},
				'demo',
				300
			);
			$this->fail( 'Expected RuntimeException was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}

		// Lock released immediately — not left to expire over LOCK_TTL.
		$found = null;
		Cache::get( 'r6_lock', 'demo', false, $found );
		$this->assertFalse( $found );

		// Nothing was cached for the failed regeneration.
		$this->assertFalse( Cache::get( 'r6', 'demo' ) );
		$this->assertFalse( Cache::get( 'r6_stale', 'demo' ) );

		// Next call can acquire the lock and regenerate straight away.
		$this->assertSame( 'ok', Cache::remember( 'r6', fn(): string => 'ok', 'demo', 300 ) );
	}

	// --- remember(): SWR path (fresh expired, stale still alive) -------------

	public function test_remember_serves_stale_immediately_when_fresh_key_is_absent(): void {
		Cache::remember( 'r4', fn(): string => 'initial', 'demo', 300 );
		Cache::delete( 'r4', 'demo' ); // simulate fresh-key expiry

		$calls  = 0;
		$result = Cache::remember(
			'r4',
			function () use ( &$calls ): string {
				++$calls;
				return 'regenerated';
			},
			'demo',
			300
		);

		$this->assertSame( 'initial', $result );  // stale served, not the fresh value
		$this->assertSame( 1, $calls );            // regeneration triggered exactly once
	}

	public function test_remember_serves_stale_and_skips_callback_when_lock_held(): void {
		Cache::remember( 'r_lock_stale', fn(): string => 'original', 'demo', 300 );
		Cache::delete( 'r_lock_stale', 'demo' ); // expire fresh

		// Pre-seed lock so wp_cache_add() returns false.
		$GLOBALS['_wp_cache']['demo']['r_lock_stale_lock'] = 1;

		$calls  = 0;
		$result = Cache::remember(
			'r_lock_stale',
			function () use ( &$calls ): string {
				++$calls;
				return 'regenerated';
			},
			'demo',
			300
		);

		$this->assertSame( 'original', $result ); // stale served
		$this->assertSame( 0, $calls );            // callback never invoked

		// Lock remains held by the other process.
		$found = null;
		Cache::get( 'r_lock_stale_lock', 'demo', false, $found );
		$this->assertTrue( $found );
	}

	public function test_remember_fallback_when_lock_held_on_cold_start(): void {
		// No fresh, no stale — only a pre-existing lock (key . '_lock').
		$GLOBALS['_wp_cache']['demo']['r_cold_lock_lock'] = 1;

		$calls  = 0;
		$result = Cache::remember(
			'r_cold_lock',
			function () use ( &$calls ): string {
				++$calls;
				return 'cold-generated';
			},
			'demo',
			300
		);

		$this->assertSame( 'cold-generated', $result );
		$this->assertSame( 1, $calls );

		// Lock belongs to another process, so the fallback must not release it.
		$found = null;
		Cache::get( 'r_cold_lock_lock', 'demo', false, $found );
		$this->assertTrue( $found );

		// Both fresh and stale were written.
		$this->assertSame( 'cold-generated', Cache::get( 'r_cold_lock', 'demo' ) );
		$this->assertSame( 'cold-generated', Cache::get( 'r_cold_lock_stale', 'demo' ) );
	}

	public function test_remember_fallback_does_not_release_existing_lock_when_callback_throws(): void {
		$GLOBALS['_wp_cache']['demo']['r_cold_throw_lock'] = 1;

		try {
			Cache::remember(
				'r_cold_throw',
				static function (): never {
					throw new \RuntimeException( 'fallback boom' );
				},
				'demo',
				300
			);
			$this->fail( 'Expected RuntimeException was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'fallback boom', $e->getMessage() );
		}

		$found = null;
		Cache::get( 'r_cold_throw_lock', 'demo', false, $found );

		$this->assertTrue( $found );
		$this->assertFalse( Cache::get( 'r_cold_throw', 'demo' ) );
		$this->assertFalse( Cache::get( 'r_cold_throw_stale', 'demo' ) );
	}
}
