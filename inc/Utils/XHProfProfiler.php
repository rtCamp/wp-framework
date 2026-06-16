<?php
/**
 * Standalone XHProf profiler.
 *
 * @package rtCamp\WPFramework\Utils
 * @since   0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Utils;

/**
 * Profiles arbitrary code blocks with XHProf, independent of any dev-monitor stack.
 *
 * Instance-based: construct one per consumer rather than reaching for a single global
 * instance, so a theme and a plugin sharing the PHP process each own a decoupled
 * profiler with its own session state. Register an instance as Shareable in a
 * consumer's container, or extend it to override {@see summarize()}:
 *
 *     $top = ( new XHProfProfiler() )->profile( fn() => expensive(), 10, 'expensive' );
 *
 * Silently no-ops when neither the `xhprof` nor the `tideways_xhprof` extension is
 * loaded, so it is safe to call in production and CI. Supports both backends — they
 * share the same `parent==>child => {ct,wt,cpu,mu,pmu}` data shape, so only the
 * enable/disable calls and the default flag constants differ.
 *
 * XHProf profiles the whole PHP process, not a single object, so the active session is
 * inherently process-global even though instances are decoupled: only one session can
 * run at a time across every instance. {@see start()} guards on that shared state and
 * fails closed, so a second instance starting mid-session no-ops rather than corrupting
 * the first instance's data or stopping its session early.
 *
 * Not `final`: downstream packages may extend it (e.g. to override {@see summarize()}).
 * Internal references use late static binding (`static::`) so overrides take effect.
 *
 * @package rtCamp\WPFramework\Utils
 * @since   0.0.1
 */
class XHProfProfiler {

	/**
	 * Action fired after each completed run, with the top-N summary and the run label.
	 *
	 * Consumers hook this to route results to logs, Query Monitor, an APM, or a file —
	 * the framework ships no viewer of its own.
	 */
	public const PROFILE_HOOK = 'rt_framework_xhprof_profile';

	/**
	 * Whether a session started by this instance is currently active.
	 *
	 * @var bool
	 */
	private bool $running = false;

	/**
	 * Whether any instance in this process currently holds an active session.
	 *
	 * XHProf is process-global, so this latch — not the per-instance {@see $running}
	 * flag — is what enforces the "one session at a time" invariant across decoupled
	 * instances. Set by the instance that wins {@see start()}, cleared by the matching
	 * {@see stop()}.
	 *
	 * @var bool
	 */
	private static bool $session_active = false;

	/**
	 * Backend in use for the active session: `xhprof`, `tideways`, or null when idle.
	 *
	 * @var string|null
	 */
	private ?string $active_backend = null;

	/**
	 * Start profiling.
	 *
	 * Returns false (silently) when a session is already running — XHProf is
	 * process-global, so sessions cannot nest, whether the running session belongs to
	 * this instance or another one in the same process — or when no backend is
	 * available.
	 *
	 * @param int|null $flags Backend profiling flags. Null resolves to CPU|MEMORY for
	 *                        the detected backend. The default is resolved here, after
	 *                        the backend guard, so the extension's flag constants are
	 *                        only referenced when the extension is actually loaded.
	 *
	 * @return bool True if profiling started, false otherwise.
	 */
	public function start( ?int $flags = null ): bool {
		if ( self::$session_active ) {
			return false;
		}

		$backend = $this->detect_backend();
		if ( null === $backend ) {
			return false;
		}

		if ( 'tideways' === $backend ) {
			tideways_xhprof_enable( $flags ?? ( TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY ) );
		} else {
			xhprof_enable( $flags ?? ( XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY ) );
		}

		$this->active_backend = $backend;
		$this->running        = true;
		self::$session_active = true;

		return true;
	}

	/**
	 * Stop profiling and return the top-N functions by wall time.
	 *
	 * Returns an empty array (silently) when no session is running. On a real run it
	 * also fires {@see PROFILE_HOOK} so consumers can route the summary wherever they
	 * like without the framework shipping a viewer.
	 *
	 * @param int    $limit Maximum number of functions to return.
	 * @param string $label Optional label identifying this run, passed through to the hook.
	 *
	 * @return array<string, array{ct: int, wt: int, cpu: int, mu: int, pmu: int}>
	 */
	public function stop( int $limit = 10, string $label = '' ): array {
		if ( ! $this->running ) {
			return [];
		}

		$raw = 'tideways' === $this->active_backend ? tideways_xhprof_disable() : xhprof_disable();

		$this->running        = false;
		$this->active_backend = null;
		self::$session_active = false;

		$summary = static::summarize( $raw, $limit );

		/**
		 * Fires after an XHProf run completes.
		 *
		 * @since 0.0.1
		 *
		 * @param array<string, array{ct: int, wt: int, cpu: int, mu: int, pmu: int}> $summary Top-N functions by wall time.
		 * @param string                                                              $label   Caller-supplied run label.
		 */
		do_action( static::PROFILE_HOOK, $summary, $label );

		return $summary;
	}

	/**
	 * Convenience: profile a callable and return the top-N summary.
	 *
	 * The callback is **always** invoked — profiling never changes whether the wrapped
	 * code runs. When profiling cannot start (no backend available, or a session is
	 * already running) the callback still runs and an empty array is returned, so it is
	 * safe to leave a `profile()` call in production or CI. When profiling does start,
	 * `stop()` runs via `finally`, so a throwing callable cannot leave the profiler stuck
	 * in the running state; either way the exception propagates to the caller.
	 *
	 * @param callable $callback The code to profile.
	 * @param int      $limit    Maximum number of functions to return.
	 * @param string   $label    Optional label identifying this run.
	 *
	 * @return array<string, array{ct: int, wt: int, cpu: int, mu: int, pmu: int}>
	 */
	public function profile( callable $callback, int $limit = 10, string $label = '' ): array {
		if ( ! $this->start() ) {
			$callback(); // Profiling unavailable — still run the work, just don't profile it.
			return [];
		}

		try {
			$callback();
		} finally {
			$summary = $this->stop( $limit, $label );
		}

		return $summary;
	}

	/**
	 * Whether a session started by this instance is currently active.
	 *
	 * Reflects this instance only — not another instance's session elsewhere in the
	 * process. Useful for conditional teardown in long-running processes (WP-CLI, queue
	 * workers).
	 *
	 * @return bool True while a session started by this instance is running.
	 */
	public function is_running(): bool {
		return $this->running;
	}

	/**
	 * Aggregate raw XHProf edge data into per-function totals, sorted by wall time descending.
	 *
	 * Pure transform — no extension required — so it is unit-testable directly with a
	 * fixture. Works for both backends, which emit the identical edge-keyed shape. Root
	 * frames (those without a `==>` separator) bucket under their own name.
	 *
	 * @param array<string, array<string, int>> $raw   Edge-keyed data, e.g. `main()==>WP_Query::get_posts`.
	 * @param int                               $limit Maximum number of functions to return.
	 *
	 * @return array<string, array{ct: int, wt: int, cpu: int, mu: int, pmu: int}>
	 */
	public static function summarize( array $raw, int $limit = 10 ): array {
		$flat = [];

		foreach ( $raw as $edge => $metrics ) {
			$parts  = explode( '==>', $edge, 2 );
			$callee = $parts[1] ?? $parts[0]; // Root frames have no "==>" separator.

			$flat[ $callee ] ??= [
				'ct'  => 0,
				'wt'  => 0,
				'cpu' => 0,
				'mu'  => 0,
				'pmu' => 0,
			];

			foreach ( [ 'ct', 'wt', 'cpu', 'mu', 'pmu' ] as $metric ) {
				$flat[ $callee ][ $metric ] += $metrics[ $metric ] ?? 0;
			}
		}

		uasort( $flat, static fn ( array $a, array $b ): int => $b['wt'] <=> $a['wt'] );

		return array_slice( $flat, 0, $limit, true );
	}

	/**
	 * Detect an available XHProf backend by its enable function. Prefers classic xhprof.
	 *
	 * @return string|null `xhprof`, `tideways`, or null when neither extension is loaded.
	 */
	private function detect_backend(): ?string {
		if ( function_exists( 'xhprof_enable' ) ) {
			return 'xhprof';
		}

		if ( function_exists( 'tideways_xhprof_enable' ) ) {
			return 'tideways';
		}

		return null;
	}
}
