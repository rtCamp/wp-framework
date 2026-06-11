<?php
/**
 * Hooks the capture pipeline into WordPress.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Capture;

use rtCamp\WPFramework\Contracts\Interfaces\Registrable;
use rtCamp\WPFramework\Telemetry\Config;
use rtCamp\WPFramework\Telemetry\Normalize\Normalizer;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;

/**
 * Class - CaptureService
 *
 * Grants `view_query_monitor` to every user: QM kills collection for
 * non-viewers, which would silently empty db_queries/http for anonymous
 * and loopback requests. This is safe ONLY because TelemetryModule
 * registers exclusively on gated local environments (see Config::is_enabled()).
 */
final class CaptureService implements Registrable {
	/**
	 * Constructor.
	 *
	 * @param CollectorBridge|null $bridge Bridge override for tests; built lazily otherwise.
	 */
	public function __construct( private ?CollectorBridge $bridge = null ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'user_has_cap', [ $this, 'grant_qm_view_cap' ] );
		add_action( 'shutdown', [ $this, 'capture' ], 20 );
	}

	/**
	 * Grants the QM viewing capability so collection runs for every request.
	 *
	 * @param array<string, bool> $allcaps Current capabilities.
	 *
	 * @return array<string, bool>
	 */
	public function grant_qm_view_cap( array $allcaps ): array {
		$allcaps['view_query_monitor'] = true;

		return $allcaps;
	}

	/**
	 * Captures the current request at shutdown.
	 */
	public function capture(): void {
		$bridge = $this->bridge ?? new CollectorBridge(
			new RequestContext(),
			new Normalizer(),
			new RequestRepository(),
			Config::retention()
		);

		$bridge->capture();
	}
}
