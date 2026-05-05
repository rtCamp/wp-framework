<?php
/**
 * Timer utility.
 *
 * @package RtCamp\WPToolkit\Utilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Utilities;

use RtCamp\WPToolkit\Traits\Singleton;

/**
 * Named timing segments that persist across scopes within a single request.
 *
 * Start a timer in one hook/file, stop it in another — no globals needed.
 * Dev Monitor's Timing panel consumes these via get_all().
 *
 * Returns seconds as float (consistent with $wpdb->queries timing).
 *
 * @since 1.0.0
 */
class Timer {

	use Singleton;

	/**
	 * Active and completed timers, keyed by label.
	 *
	 * @var array<string, array{start: float, end: float|null, laps: array<string, float>}>
	 */
	private array $timers = array();

	/**
	 * Setup hook — required stub for the Singleton boot sequence.
	 *
	 * @return void
	 */
	public function setup(): void {}

	/**
	 * Start a named timer. No-op if label is empty or already started.
	 *
	 * @param string $label Unique identifier for this timer.
	 *
	 * @return void
	 */
	public function start( string $label ): void {
		if ( '' === $label ) {
			return;
		}

		if ( isset( $this->timers[ $label ] ) ) {
			return;
		}

		$this->timers[ $label ] = array(
			'start' => microtime( true ),
			'end'   => null,
			'laps'  => array(),
		);
	}

	/**
	 * Stop a named timer.
	 *
	 * Return paths:
	 *   - empty label → 0.0 silently,
	 *   - never-started label → 0.0 plus _doing_it_wrong(),
	 *   - already-stopped → cached elapsed (no re-computation),
	 *   - running → fresh elapsed and marks the timer stopped.
	 *
	 * @param string $label Timer to stop.
	 *
	 * @return float Elapsed seconds (0.0 if never started or label is empty).
	 */
	public function stop( string $label ): float {
		if ( '' === $label ) {
			return 0.0;
		}

		if ( ! isset( $this->timers[ $label ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Timer "%s" was never started.', esc_html( $label ) ),
				'1.0.0'
			);
			return 0.0;
		}

		if ( null !== $this->timers[ $label ]['end'] ) {
			return $this->timers[ $label ]['end'] - $this->timers[ $label ]['start'];
		}

		$this->timers[ $label ]['end'] = microtime( true );

		return $this->timers[ $label ]['end'] - $this->timers[ $label ]['start'];
	}

	/**
	 * Record an intermediate split on a running timer.
	 *
	 * Stores elapsed seconds since start at the moment lap() is called.
	 * No-op if either argument is empty. Triggers _doing_it_wrong() if
	 * the timer does not exist or has already been stopped.
	 *
	 * @param string $label Timer label.
	 * @param string $name  Lap name.
	 *
	 * @return void
	 */
	public function lap( string $label, string $name ): void {
		if ( '' === $label || '' === $name ) {
			return;
		}

		if ( ! isset( $this->timers[ $label ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Timer "%s" was never started.', esc_html( $label ) ),
				'1.0.0'
			);
			return;
		}

		if ( null !== $this->timers[ $label ]['end'] ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Timer "%s" has already been stopped.', esc_html( $label ) ),
				'1.0.0'
			);
			return;
		}

		$this->timers[ $label ]['laps'][ $name ] = microtime( true ) - $this->timers[ $label ]['start'];
	}

	/**
	 * Get timing data for a single timer.
	 *
	 * If the timer is still running, 'elapsed' returns time since start
	 * without stopping it. Returns null for empty or unknown labels.
	 *
	 * @param string $label Timer label.
	 *
	 * @return array{start: float, end: float|null, elapsed: float, laps: array<string, float>}|null
	 */
	public function get( string $label ): ?array {
		if ( '' === $label || ! isset( $this->timers[ $label ] ) ) {
			return null;
		}

		$timer = $this->timers[ $label ];

		$elapsed = null !== $timer['end']
			? $timer['end'] - $timer['start']
			: microtime( true ) - $timer['start'];

		return array(
			'start'   => $timer['start'],
			'end'     => $timer['end'],
			'elapsed' => $elapsed,
			'laps'    => $timer['laps'],
		);
	}

	/**
	 * Get all recorded timers.
	 *
	 * Each entry includes a computed 'elapsed' — for running timers this is
	 * the time-since-start at the moment get_all() is called.
	 *
	 * @return array<string, array{start: float, end: float|null, elapsed: float, laps: array<string, float>}>
	 */
	public function get_all(): array {
		$result = array();

		foreach ( $this->timers as $label => $timer ) {
			$elapsed = null !== $timer['end']
				? $timer['end'] - $timer['start']
				: microtime( true ) - $timer['start'];

			$result[ $label ] = array(
				'start'   => $timer['start'],
				'end'     => $timer['end'],
				'elapsed' => $elapsed,
				'laps'    => $timer['laps'],
			);
		}

		return $result;
	}
}
