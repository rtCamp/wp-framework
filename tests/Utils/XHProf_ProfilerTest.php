<?php
/**
 * XHProf_Profiler utility tests.
 *
 * @package rtCamp\WPFramework\Tests\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Utils;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Utils\XHProf_Profiler;

/**
 * Tests for XHProf_Profiler.
 *
 * The summarize() transform is pure and exercised directly with a raw-data fixture
 * (no extension required). Backend-dependent paths (start/stop/profile against a live
 * profiler) are guarded: when an XHProf backend happens to be loaded, those guard tests
 * skip so the suite stays deterministic and free of real profiling side effects.
 */
final class XHProf_ProfilerTest extends TestCase {

	/**
	 * Raw edge-keyed data shaped like xhprof_disable() / tideways_xhprof_disable() output.
	 *
	 * @var array<string, array<string, int>>
	 */
	private const RAW = [
		'main()'                            => [ 'ct' => 1, 'wt' => 100, 'cpu' => 90, 'mu' => 1000, 'pmu' => 1000 ],
		'main()==>WP_Query::get_posts'      => [ 'ct' => 1, 'wt' => 800, 'cpu' => 700, 'mu' => 5000, 'pmu' => 6000 ],
		'WP_Query::get_posts==>wpdb::query' => [ 'ct' => 3, 'wt' => 600, 'cpu' => 500, 'mu' => 2000, 'pmu' => 2500 ],
	];

	/**
	 * Whether a real XHProf backend is present in the test environment.
	 */
	private static function backend_loaded(): bool {
		return function_exists( 'xhprof_enable' ) || function_exists( 'tideways_xhprof_enable' );
	}

	// --- summarize(): the pure transform --------------------------------------

	public function test_summarize_aggregates_by_callee_and_sums_metrics(): void {
		$raw = [
			'a==>shared' => [ 'ct' => 1, 'wt' => 10, 'cpu' => 5, 'mu' => 100, 'pmu' => 100 ],
			'b==>shared' => [ 'ct' => 2, 'wt' => 20, 'cpu' => 7, 'mu' => 200, 'pmu' => 250 ],
		];

		$summary = XHProf_Profiler::summarize( $raw );

		$this->assertArrayHasKey( 'shared', $summary );
		$this->assertSame(
			[ 'ct' => 3, 'wt' => 30, 'cpu' => 12, 'mu' => 300, 'pmu' => 350 ],
			$summary['shared']
		);
	}

	public function test_summarize_sorts_by_wall_time_desc(): void {
		$summary = XHProf_Profiler::summarize( self::RAW );

		// WP_Query::get_posts (wt 800) > wpdb::query (wt 600) > main() (wt 100).
		$this->assertSame(
			[ 'WP_Query::get_posts', 'wpdb::query', 'main()' ],
			array_keys( $summary )
		);
	}

	public function test_summarize_respects_limit_and_preserves_keys(): void {
		$summary = XHProf_Profiler::summarize( self::RAW, 2 );

		$this->assertCount( 2, $summary );
		$this->assertSame( [ 'WP_Query::get_posts', 'wpdb::query' ], array_keys( $summary ) );
	}

	public function test_summarize_handles_root_frame_without_edge_separator(): void {
		$summary = XHProf_Profiler::summarize(
			[ 'main()' => [ 'ct' => 1, 'wt' => 42, 'cpu' => 1, 'mu' => 1, 'pmu' => 1 ] ]
		);

		$this->assertArrayHasKey( 'main()', $summary );
		$this->assertSame( 42, $summary['main()']['wt'] );
	}

	public function test_summarize_tolerates_missing_metric_keys(): void {
		// CPU-only run: edges carry ct/wt/cpu but no mu/pmu.
		$summary = XHProf_Profiler::summarize(
			[ 'main()==>foo' => [ 'ct' => 1, 'wt' => 10, 'cpu' => 8 ] ]
		);

		$this->assertSame(
			[ 'ct' => 1, 'wt' => 10, 'cpu' => 8, 'mu' => 0, 'pmu' => 0 ],
			$summary['foo']
		);
	}

	public function test_summarize_returns_empty_array_for_no_data(): void {
		$this->assertSame( [], XHProf_Profiler::summarize( [] ) );
	}

	// --- singleton ------------------------------------------------------------

	public function test_get_instance_returns_same_instance(): void {
		$this->assertSame(
			XHProf_Profiler::get_instance(),
			XHProf_Profiler::get_instance()
		);
	}

	public function test_class_is_extendable(): void {
		// Non-final so downstream packages can override summarize() etc. Guard the invariant.
		$this->assertFalse( ( new \ReflectionClass( XHProf_Profiler::class ) )->isFinal() );
	}

	// --- guards (no live profiling) -------------------------------------------

	public function test_stop_returns_empty_array_when_not_running(): void {
		$profiler = XHProf_Profiler::get_instance();

		$this->assertFalse( $profiler->is_running() );
		$this->assertSame( [], $profiler->stop() );
	}

	public function test_start_returns_false_when_no_backend(): void {
		if ( self::backend_loaded() ) {
			$this->markTestSkipped( 'An XHProf backend is loaded; the no-backend guard is environment-dependent.' );
		}

		$profiler = XHProf_Profiler::get_instance();

		$this->assertFalse( $profiler->start() );
		$this->assertFalse( $profiler->is_running() );
	}

	public function test_profile_returns_empty_array_and_skips_callable_when_no_backend(): void {
		if ( self::backend_loaded() ) {
			$this->markTestSkipped( 'An XHProf backend is loaded; the no-backend guard is environment-dependent.' );
		}

		$calls  = 0;
		$result = XHProf_Profiler::get_instance()->profile(
			function () use ( &$calls ): void {
				++$calls;
			}
		);

		$this->assertSame( [], $result );
		$this->assertSame( 0, $calls ); // start() failed, so the callable never ran.
	}
}
