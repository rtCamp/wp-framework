<?php
/**
 * Normalizes QM's http collector data (outbound WP HTTP API calls).
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize\Mappers;

use rtCamp\WPFramework\Telemetry\Normalize\QmData;

/**
 * QM 4.x http rows look like:
 * {url, args, result (array|WP_Error), type, host, ltime (seconds), trace,
 * redirected_to, intercepted}. Older shapes used 'response' for the result.
 */
final class HttpMapper {
	/**
	 * Maps to {count, total_time_ms, calls[]}.
	 *
	 * @param array<string, mixed> $raw Collector id => QM_Data.
	 *
	 * @return array<string, mixed>
	 */
	public function map( array $raw ): array {
		$data = QmData::collector( $raw, 'http' );
		$rows = QmData::get( $data, 'http', [] );

		$calls = [];
		foreach ( (array) $rows as $row ) {
			$url = (string) QmData::get( $row, 'url', '' );
			if ( '' === $url ) {
				continue;
			}

			$args     = QmData::get( $row, 'args', [] );
			$response = QmData::get( $row, 'result', QmData::get( $row, 'response' ) );
			$frames   = QmData::frames( QmData::get( $row, 'trace', QmData::get( $row, 'filtered_trace' ) ) );
			$site     = QmData::call_site( $frames );

			$error         = null;
			$response_code = null;
			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_code() . ': ' . $response->get_error_message();
			} elseif ( is_array( $response ) ) {
				$response_code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : null;
			}

			$calls[] = [
				'url'           => $url,
				'method'        => strtoupper( (string) QmData::get( $args, 'method', 'GET' ) ),
				'timeout'       => QmData::get( $args, 'timeout' ),
				'response_code' => $response_code,
				'error'         => $error,
				'time_ms'       => round( (float) QmData::get( $row, 'ltime', 0 ) * 1000, 2 ),
				'component'     => QmData::component_name( QmData::get( $row, 'component' ) ),
				'file'          => $site['file'],
				'line'          => $site['line'],
				'stack'         => $frames,
			];
		}

		return [
			'count'         => count( $calls ),
			'total_time_ms' => round( array_sum( array_column( $calls, 'time_ms' ) ), 2 ),
			'calls'         => $calls,
		];
	}
}
