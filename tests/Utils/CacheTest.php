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

	/**
	 * Context-free instance — groups pass through verbatim.
	 */
	private Cache $cache;

	protected function setUp(): void {
		$GLOBALS['_wp_cache']                      = [];
		$GLOBALS['_wp_cache_supports_flush_group'] = true;

		$this->cache = new Cache();
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_cache']                      = [];
		$GLOBALS['_wp_cache_supports_flush_group'] = true;
	}

	// --- get / set / delete / flush_group ------------------------------------

	public function test_set_then_get_returns_stored_value(): void {
		$this->cache->set( 'k1', 'hello', 'demo' );

		$this->assertSame( 'hello', $this->cache->get( 'k1', 'demo' ) );
	}

	public function test_get_returns_false_for_missing_key(): void {
		$this->assertFalse( $this->cache->get( 'missing', 'demo' ) );
	}

	public function test_get_reports_found_for_stored_false(): void {
		$this->cache->set( 'k4', false, 'demo' );

		$found = null;
		$value = $this->cache->get( 'k4', 'demo', false, $found );

		$this->assertFalse( $value );
		$this->assertTrue( $found ); // stored false is a hit, not a miss
	}

	public function test_get_reports_not_found_for_missing_key(): void {
		$found = null;
		$this->cache->get( 'nope', 'demo', false, $found );

		$this->assertFalse( $found );
	}

	public function test_delete_removes_entry(): void {
		$this->cache->set( 'k2', 'value', 'demo' );
		$this->cache->delete( 'k2', 'demo' );

		$this->assertFalse( $this->cache->get( 'k2', 'demo' ) );
	}

	public function test_flush_group_clears_all_keys_in_group(): void {
		$this->cache->set( 'k3', 'value', 'demo' );

		$result = $this->cache->flush_group( 'demo' );

		$this->assertTrue( $result );
		$this->assertFalse( $this->cache->get( 'k3', 'demo' ) );
	}

	public function test_flush_group_returns_false_when_backend_lacks_support(): void {
		$this->cache->set( 'k5', 'value', 'demo' );
		$GLOBALS['_wp_cache_supports_flush_group'] = false; // backend cannot flush groups

		$result = $this->cache->flush_group( 'demo' );

		$this->assertFalse( $result );                                   // capability gate trips
		$this->assertSame( 'value', $this->cache->get( 'k5', 'demo' ) ); // group left intact
	}

	// --- context namespacing --------------------------------------------------

	public function test_context_prefixes_the_group_in_the_object_cache(): void {
		$cache = new Cache( 'my-plugin' );
		$cache->set( 'k', 'value', 'posts' );

		// Stored under the namespaced group, not the raw one.
		$this->assertArrayHasKey( 'my-plugin:posts', $GLOBALS['_wp_cache'] );
		$this->assertArrayNotHasKey( 'posts', $GLOBALS['_wp_cache'] );
		$this->assertSame( 'value', $cache->get( 'k', 'posts' ) );
	}

	public function test_context_namespaces_the_default_group(): void {
		$cache = new Cache( 'my-plugin' );
		$cache->set( 'k', 'value' );

		$this->assertArrayHasKey( 'my-plugin', $GLOBALS['_wp_cache'] );
		$this->assertSame( 'value', $cache->get( 'k' ) );
	}

	public function test_same_group_in_different_contexts_does_not_collide(): void {
		$plugin_a = new Cache( 'plugin-a' );
		$plugin_b = new Cache( 'plugin-b' );

		$plugin_a->set( 'k', 'from-a', 'posts' );
		$plugin_b->set( 'k', 'from-b', 'posts' );

		$this->assertSame( 'from-a', $plugin_a->get( 'k', 'posts' ) );
		$this->assertSame( 'from-b', $plugin_b->get( 'k', 'posts' ) );
	}

	public function test_empty_context_passes_groups_through_verbatim(): void {
		$this->cache->set( 'k', 'value', 'posts' );

		$this->assertArrayHasKey( 'posts', $GLOBALS['_wp_cache'] );
	}

	public function test_flush_group_only_flushes_own_context(): void {
		$plugin_a = new Cache( 'plugin-a' );
		$plugin_b = new Cache( 'plugin-b' );

		$plugin_a->set( 'k', 'from-a', 'posts' );
		$plugin_b->set( 'k', 'from-b', 'posts' );

		$this->assertTrue( $plugin_a->flush_group( 'posts' ) );

		$this->assertFalse( $plugin_a->get( 'k', 'posts' ) );
		$this->assertSame( 'from-b', $plugin_b->get( 'k', 'posts' ) );
	}

	public function test_class_is_extendable(): void {
		// Cache is deliberately non-final so consumers can extend it (e.g. to
		// override the resolve_group() seam or the SWR tunables). Guard that.
		$this->assertFalse( ( new \ReflectionClass( Cache::class ) )->isFinal() );
	}

	// --- remember(): plain get-or-set ----------------------------------------

	public function test_remember_returns_cached_value_without_calling_callback(): void {
		$this->cache->set( 'r1', 'cached', 'demo' );

		$calls  = 0;
		$result = $this->cache->remember(
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

	public function test_remember_calls_callback_on_miss_and_stores_value(): void {
		$result = $this->cache->remember( 'r2', fn(): string => 'generated', 'demo', 300 );

		$this->assertSame( 'generated', $result );
		$this->assertSame( 'generated', $this->cache->get( 'r2', 'demo' ) );
	}

	public function test_remember_writes_no_companion_keys(): void {
		$this->cache->remember( 'r2', fn(): string => 'generated', 'demo', 300 );

		// Plain get-or-set is single-key: no _stale / _lock entries.
		$this->assertSame( [ 'r2' ], array_keys( $GLOBALS['_wp_cache']['demo'] ) );
	}

	public function test_remember_calls_callback_only_once_for_sequential_calls(): void {
		$calls = 0;
		$make  = function () use ( &$calls ): string {
			++$calls;
			return 'once';
		};

		$this->cache->remember( 'r3', $make, 'demo', 300 );
		$this->cache->remember( 'r3', $make, 'demo', 300 );

		$this->assertSame( 1, $calls );
	}

	#[DataProvider( 'falsy_values_provider' )]
	public function test_remember_caches_falsy_callback_results( string $key, mixed $falsy ): void {
		$calls = 0;
		$make  = function () use ( &$calls, $falsy ): mixed {
			++$calls;
			return $falsy;
		};

		$first  = $this->cache->remember( $key, $make, 'demo', 300 );
		$second = $this->cache->remember( $key, $make, 'demo', 300 );

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

	public function test_remember_caches_nothing_when_callback_throws(): void {
		try {
			$this->cache->remember(
				'r_throw',
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

		$found = null;
		$this->cache->get( 'r_throw', 'demo', false, $found );
		$this->assertFalse( $found );
	}

	// --- remember_swr(): hot path ---------------------------------------------

	public function test_swr_returns_cached_value_without_calling_callback(): void {
		$this->cache->set( 's1', 'cached', 'demo' );

		$calls  = 0;
		$result = $this->cache->remember_swr(
			's1',
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

	// --- remember_swr(): cold start (both fresh and stale absent) --------------

	public function test_swr_calls_callback_on_miss_and_stores_both_keys(): void {
		$result = $this->cache->remember_swr( 's2', fn(): string => 'generated', 'demo', 300 );

		$this->assertSame( 'generated', $result );
		$this->assertSame( 'generated', $this->cache->get( 's2', 'demo' ) );
		$this->assertSame( 'generated', $this->cache->get( 's2_stale', 'demo' ) );
	}

	public function test_swr_releases_lock_and_caches_nothing_when_callback_throws(): void {
		try {
			$this->cache->remember_swr(
				's6',
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
		$this->cache->get( 's6_lock', 'demo', false, $found );
		$this->assertFalse( $found );

		// Nothing was cached for the failed regeneration.
		$this->assertFalse( $this->cache->get( 's6', 'demo' ) );
		$this->assertFalse( $this->cache->get( 's6_stale', 'demo' ) );

		// Next call can acquire the lock and regenerate straight away.
		$this->assertSame( 'ok', $this->cache->remember_swr( 's6', fn(): string => 'ok', 'demo', 300 ) );
	}

	// --- remember_swr(): SWR path (fresh expired, stale still alive) -----------

	public function test_swr_serves_stale_immediately_when_fresh_key_is_absent(): void {
		$this->cache->remember_swr( 's4', fn(): string => 'initial', 'demo', 300 );
		$this->cache->delete( 's4', 'demo' ); // simulate fresh-key expiry

		$calls  = 0;
		$result = $this->cache->remember_swr(
			's4',
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

	public function test_swr_serves_stale_and_skips_callback_when_lock_held(): void {
		$this->cache->remember_swr( 's_lock_stale', fn(): string => 'original', 'demo', 300 );
		$this->cache->delete( 's_lock_stale', 'demo' ); // expire fresh

		// Pre-seed lock so wp_cache_add() returns false.
		$GLOBALS['_wp_cache']['demo']['s_lock_stale_lock'] = 1;

		$calls  = 0;
		$result = $this->cache->remember_swr(
			's_lock_stale',
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
		$this->cache->get( 's_lock_stale_lock', 'demo', false, $found );
		$this->assertTrue( $found );
	}

	public function test_swr_fallback_when_lock_held_on_cold_start(): void {
		// No fresh, no stale — only a pre-existing lock (key . '_lock').
		$GLOBALS['_wp_cache']['demo']['s_cold_lock_lock'] = 1;

		$calls  = 0;
		$result = $this->cache->remember_swr(
			's_cold_lock',
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
		$this->cache->get( 's_cold_lock_lock', 'demo', false, $found );
		$this->assertTrue( $found );

		// Both fresh and stale were written.
		$this->assertSame( 'cold-generated', $this->cache->get( 's_cold_lock', 'demo' ) );
		$this->assertSame( 'cold-generated', $this->cache->get( 's_cold_lock_stale', 'demo' ) );
	}

	public function test_swr_fallback_does_not_release_existing_lock_when_callback_throws(): void {
		$GLOBALS['_wp_cache']['demo']['s_cold_throw_lock'] = 1;

		try {
			$this->cache->remember_swr(
				's_cold_throw',
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
		$this->cache->get( 's_cold_throw_lock', 'demo', false, $found );

		$this->assertTrue( $found );
		$this->assertFalse( $this->cache->get( 's_cold_throw', 'demo' ) );
		$this->assertFalse( $this->cache->get( 's_cold_throw_stale', 'demo' ) );
	}

	// --- remember_swr(): no-expiry entries skip the stale companion ------------

	public function test_swr_with_zero_expiration_writes_no_stale_companion(): void {
		$result = $this->cache->remember_swr( 's_forever', fn(): string => 'permanent', 'demo', 0 );

		$this->assertSame( 'permanent', $result );
		$this->assertSame( 'permanent', $this->cache->get( 's_forever', 'demo' ) );

		// A never-expiring entry has nothing to revalidate: only the fresh key
		// is written (and the cold-start lock has been released).
		$this->assertSame( [ 's_forever' ], array_keys( $GLOBALS['_wp_cache']['demo'] ) );
	}

	// --- remember_swr(): context namespacing covers companions -----------------

	public function test_swr_companion_keys_live_in_the_namespaced_group(): void {
		$cache = new Cache( 'my-plugin' );
		$cache->remember_swr( 'swr_ns', fn(): string => 'v', 'posts', 300 );

		// Fresh + stale both written to the namespaced group; lock released.
		$this->assertSame(
			[ 'swr_ns', 'swr_ns_stale' ],
			array_keys( $GLOBALS['_wp_cache']['my-plugin:posts'] )
		);
	}
}
