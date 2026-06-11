<?php
/**
 * Reads Query Monitor's collectors at shutdown and persists the capture.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Capture;

use rtCamp\WPFramework\Telemetry\Normalize\Normalizer;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Telemetry\Store\Schema;

/**
 * Runs on shutdown at priority 20 — after QM's own dispatch at 9 — so
 * re-invoking QM_Collectors::init()->process() is safe. QM only ever
 * holds the current request; this bridge is what gets the data out.
 */
final class CollectorBridge {
	/**
	 * Constructor.
	 *
	 * @param RequestContext    $context    Current request facts.
	 * @param Normalizer        $normalizer Raw-to-schema mapper.
	 * @param RequestRepository $requests   Persistence.
	 * @param int               $retention  Ring-buffer size.
	 */
	public function __construct(
		private RequestContext $context,
		private Normalizer $normalizer,
		private RequestRepository $requests,
		private int $retention = 200,
	) {
	}

	/**
	 * Captures the current request's QM data, if eligible.
	 *
	 * Skips: WP-CLI processes (QM never collects there), requests with the
	 * ignore header (internal sub-requests), favicon noise, and anything
	 * when Query Monitor isn't active. A capture failure must never break
	 * the developer's request — errors are logged and swallowed.
	 */
	public function capture(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		if ( ! class_exists( 'QM_Collectors' ) ) {
			return;
		}
		if ( $this->context->should_ignore() ) {
			return;
		}
		$path = (string) wp_parse_url( $this->context->url(), PHP_URL_PATH );
		if ( '/favicon.ico' === $path ) {
			return;
		}

		try {
			$collectors = \QM_Collectors::init();
			$collectors->process();

			$raw = [];
			foreach ( $collectors as $id => $collector ) {
				$key = is_string( $id ) && '' !== $id ? $id : $this->collector_id( $collector );
				if ( null === $key ) {
					continue;
				}
				$raw[ $key ] = is_callable( [ $collector, 'get_data' ] ) ? $collector->get_data() : null;
			}

			$normalized = $this->normalizer->normalize( $raw, $this->context );

			Schema::ensure();
			$this->requests->save( $normalized, $this->retention );
		} catch ( \Throwable $e ) {
			error_log( '[wp-framework telemetry] capture failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- local-dev-only module; surfacing capture failures in the debug log is the point.
		}
	}

	/**
	 * Collector id from the object, tolerating the "qm-" prefix.
	 *
	 * @param object $collector QM collector instance.
	 */
	private function collector_id( object $collector ): ?string {
		if ( isset( $collector->id ) && is_string( $collector->id ) ) {
			return $collector->id;
		}
		if ( is_callable( [ $collector, 'get_id' ] ) ) {
			return (string) preg_replace( '/^qm-/', '', (string) $collector->get_id() );
		}

		return null;
	}
}
