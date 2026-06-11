<?php
/**
 * Normalizes QM's db_queries collector data.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize\Mappers;

use rtCamp\WPFramework\Telemetry\Normalize\QmData;
use rtCamp\WPFramework\Telemetry\Support\Sql;

/**
 * QM db_queries rows historically look like:
 * {sql, ltime (seconds), caller, trace|filtered_trace, component, result}.
 */
final class DbQueriesMapper {
	/**
	 * Maps to {count, total_time_ms, queries[]}.
	 *
	 * @param array<string, mixed> $raw Collector id => QM_Data.
	 *
	 * @return array<string, mixed>
	 */
	public function map( array $raw ): array {
		$data = QmData::collector( $raw, 'db_queries' );
		$rows = QmData::get( $data, 'rows', [] );

		$queries = [];
		foreach ( (array) $rows as $row ) {
			$sql = (string) QmData::get( $row, 'sql', '' );
			if ( '' === $sql ) {
				continue;
			}

			$trace  = QmData::get( $row, 'trace', QmData::get( $row, 'filtered_trace' ) );
			$frames = QmData::frames( $trace );
			$site   = QmData::call_site( $frames );

			$time = (float) QmData::get( $row, 'ltime', QmData::get( $row, 'time', 0 ) );

			$queries[] = [
				'sql'             => Sql::truncate( $sql ),
				'sql_fingerprint' => Sql::fingerprint( $sql ),
				'time_ms'         => round( $time * 1000, 2 ),
				'caller'          => (string) QmData::get( $row, 'caller', $site['display'] ),
				'component'       => QmData::component_name( QmData::get( $row, 'component' ) ),
				'file'            => $site['file'],
				'line'            => $site['line'],
				'stack'           => $frames,
			];
		}

		$count      = count( $queries );
		$total_time = (float) QmData::get( $data, 'total_time', 0 );

		return [
			'count'         => $count > 0 ? $count : (int) QmData::get( $data, 'total_qs', 0 ),
			'total_time_ms' => $total_time > 0 ? round( $total_time * 1000, 2 ) : round( array_sum( array_column( $queries, 'time_ms' ) ), 2 ),
			'queries'       => $queries,
		];
	}
}
