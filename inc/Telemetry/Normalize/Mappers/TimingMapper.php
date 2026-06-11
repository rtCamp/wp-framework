<?php
/**
 * Request-level metrics.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize\Mappers;

/**
 * Wall time and memory are measured directly (not from QM) so they exist
 * even when individual QM collectors change shape or are absent.
 */
final class TimingMapper {
	/**
	 * Maps to the metrics block of the normalized schema.
	 *
	 * @param array<string, mixed> $raw        Collector id => QM_Data (unused for now; kept for signature stability).
	 * @param array<string, mixed> $collectors Already-normalized collector blocks.
	 *
	 * @return array<string, mixed>
	 */
	public function map( array $raw, array $collectors ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- signature stability.
		global $timestart;

		$total_ms = null;
		if ( ! empty( $timestart ) ) {
			$total_ms = round( ( microtime( true ) - (float) $timestart ) * 1000, 1 );
		}

		return [
			'total_time_ms'     => $total_ms,
			'peak_memory_bytes' => memory_get_peak_usage(),
			'query_count'       => (int) ( $collectors['db_queries']['count'] ?? 0 ),
			'query_time_ms'     => (float) ( $collectors['db_queries']['total_time_ms'] ?? 0 ),
			'php_error_count'   => (int) ( $collectors['php_errors']['count'] ?? 0 ),
			'http_call_count'   => (int) ( $collectors['http']['count'] ?? 0 ),
		];
	}
}
