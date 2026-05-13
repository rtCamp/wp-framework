<?php
/**
 * Tests for the Timer utility.
 *
 * @package RtCamp\WPToolkit\Tests\Utilities
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Tests\Utilities;

use RtCamp\WPToolkit\Utilities\Timer;
use WP_UnitTestCase;

/**
 * Timer utility tests.
 *
 * Runs against the real WordPress test suite (wp-tests-lib) so
 * _doing_it_wrong() routes through WP's real handler — the polyfilled
 * setExpectedIncorrectUsage() picks them up without stubbing.
 *
 * Notes on test isolation:
 *
 *   - The Singleton's $timers array is *process* state, not DB state, so
 *     timers accumulate across tests within a run. Tests use unique labels
 *     per test and assert via assertArrayHasKey rather than assertEquals
 *     against the full set.
 *   - usleep() may sleep slightly longer than requested depending on scheduler
 *     load, so timing assertions only check > 0.0 (with < 1.0 as a runaway
 *     guard) — never an exact value.
 *
 * @since 1.0.0
 */
class TimerTest extends WP_UnitTestCase {

	/**
	 * Start then stop returns a positive elapsed in seconds.
	 */
	public function test_start_stop_returns_positive_elapsed(): void {
		Timer::get_instance()->start( 'test_basic' );
		usleep( 10000 );
		$elapsed = Timer::get_instance()->stop( 'test_basic' );

		$this->assertGreaterThan( 0.0, $elapsed );
		$this->assertLessThan( 1.0, $elapsed );
	}

	/**
	 * Stopping a never-started label returns 0.0 and triggers `_doing_it_wrong()`.
	 */
	public function test_stop_without_start_returns_zero_and_warns(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::stop' );

		$this->assertSame( 0.0, Timer::get_instance()->stop( 'never_started' ) );
	}

	/**
	 * Calling start() twice with the same label triggers `_doing_it_wrong()`
	 * and keeps the first start time.
	 */
	public function test_start_twice_is_noop(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::start' );

		Timer::get_instance()->start( 'double_start' );
		$first = Timer::get_instance()->get( 'double_start' );

		usleep( 5000 );
		Timer::get_instance()->start( 'double_start' );
		$second = Timer::get_instance()->get( 'double_start' );

		$this->assertSame( $first['start'], $second['start'] );
	}

	/**
	 * Empty-string start triggers `_doing_it_wrong()` and is a no-op; reading
	 * the empty label returns null and no empty-string key is created.
	 */
	public function test_empty_label_start_is_noop(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::start' );

		Timer::get_instance()->start( '' );

		$this->assertNull( Timer::get_instance()->get( '' ) );
		$this->assertArrayNotHasKey( '', Timer::get_instance()->get_all() );
	}

	/**
	 * Stopping the empty label returns 0.0 and triggers `_doing_it_wrong()`.
	 */
	public function test_stop_empty_label_returns_zero(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::stop' );

		$this->assertSame( 0.0, Timer::get_instance()->stop( '' ) );
	}

	/**
	 * Stopping an already-stopped timer triggers `_doing_it_wrong()` and
	 * returns the cached elapsed.
	 */
	public function test_stop_already_stopped_returns_cached_elapsed(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::stop' );

		Timer::get_instance()->start( 'stop_twice' );
		usleep( 5000 );
		$first = Timer::get_instance()->stop( 'stop_twice' );

		usleep( 5000 );
		$second = Timer::get_instance()->stop( 'stop_twice' );

		$this->assertSame( $first, $second );
	}

	/**
	 * Laps record intermediate seconds relative to start, in order.
	 */
	public function test_lap_records_intermediate_time(): void {
		Timer::get_instance()->start( 'with_laps' );
		usleep( 5000 );
		Timer::get_instance()->lap( 'with_laps', 'checkpoint_1' );
		usleep( 5000 );
		Timer::get_instance()->lap( 'with_laps', 'checkpoint_2' );
		Timer::get_instance()->stop( 'with_laps' );

		$data = Timer::get_instance()->get( 'with_laps' );

		$this->assertArrayHasKey( 'checkpoint_1', $data['laps'] );
		$this->assertArrayHasKey( 'checkpoint_2', $data['laps'] );
		$this->assertGreaterThan( 0.0, $data['laps']['checkpoint_1'] );
		$this->assertGreaterThan( $data['laps']['checkpoint_1'], $data['laps']['checkpoint_2'] );
	}

	/**
	 * Lapping a never-started timer triggers `_doing_it_wrong()`.
	 */
	public function test_lap_without_start_warns(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::lap' );

		Timer::get_instance()->lap( 'ghost', 'nope' );
	}

	/**
	 * Lapping after stop triggers `_doing_it_wrong()` and does not record the lap.
	 */
	public function test_lap_after_stop_warns(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::lap' );

		Timer::get_instance()->start( 'stopped_lap' );
		Timer::get_instance()->stop( 'stopped_lap' );
		Timer::get_instance()->lap( 'stopped_lap', 'too_late' );

		$data = Timer::get_instance()->get( 'stopped_lap' );
		$this->assertArrayNotHasKey( 'too_late', $data['laps'] );
	}

	/**
	 * An empty lap name triggers `_doing_it_wrong()` and is a no-op.
	 */
	public function test_lap_empty_name_is_noop(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::lap' );

		Timer::get_instance()->start( 'empty_lap_name' );
		Timer::get_instance()->lap( 'empty_lap_name', '' );

		$data = Timer::get_instance()->get( 'empty_lap_name' );
		$this->assertEmpty( $data['laps'] );
	}

	/**
	 * An empty timer label on lap() triggers `_doing_it_wrong()` and is a
	 * no-op — no empty-label timer is created.
	 */
	public function test_lap_empty_label_is_noop(): void {
		$this->setExpectedIncorrectUsage( 'RtCamp\WPToolkit\Utilities\Timer::lap' );

		Timer::get_instance()->lap( '', 'checkpoint' );

		$this->assertArrayNotHasKey( '', Timer::get_instance()->get_all() );
	}

	/**
	 * Reading a still-running timer returns elapsed-so-far without stopping it.
	 */
	public function test_get_running_timer_returns_elapsed_so_far(): void {
		Timer::get_instance()->start( 'still_running' );
		usleep( 10000 );

		$data = Timer::get_instance()->get( 'still_running' );

		$this->assertNull( $data['end'] );
		$this->assertGreaterThan( 0.0, $data['elapsed'] );
	}

	/**
	 * Reading an unknown label returns null.
	 */
	public function test_get_unknown_label_returns_null(): void {
		$this->assertNull( Timer::get_instance()->get( 'does_not_exist' ) );
	}

	/**
	 * All recorded timers are exposed with a computed elapsed — stopped timers
	 * report their final elapsed, running timers report a live time-since-start.
	 */
	public function test_get_all_includes_running_and_stopped(): void {
		Timer::get_instance()->start( 'all_one' );
		Timer::get_instance()->start( 'all_two' );
		usleep( 5000 );
		Timer::get_instance()->stop( 'all_one' );

		$all = Timer::get_instance()->get_all();

		$this->assertArrayHasKey( 'all_one', $all );
		$this->assertArrayHasKey( 'all_two', $all );
		$this->assertNotNull( $all['all_one']['end'] );
		$this->assertNull( $all['all_two']['end'] );

		$this->assertArrayHasKey( 'elapsed', $all['all_one'] );
		$this->assertArrayHasKey( 'elapsed', $all['all_two'] );
		$this->assertGreaterThan( 0.0, $all['all_one']['elapsed'] );
		$this->assertGreaterThan( 0.0, $all['all_two']['elapsed'] );
	}
}
