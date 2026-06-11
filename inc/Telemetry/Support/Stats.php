<?php
/**
 * Small numeric helpers for telemetry summaries.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Support;

/**
 * Class - Stats
 */
final class Stats {
	/**
	 * Median of a numeric list, null when empty.
	 *
	 * Non-numeric values are dropped before computing.
	 *
	 * @param array<int|string, mixed> $values Values (non-numerics dropped).
	 */
	public static function median( array $values ): ?float {
		$values = array_values( array_filter( $values, 'is_numeric' ) );
		if ( [] === $values ) {
			return null;
		}

		sort( $values );
		$mid = (int) floor( count( $values ) / 2 );
		if ( count( $values ) % 2 ) {
			return (float) $values[ $mid ];
		}

		return round( ( (float) $values[ $mid - 1 ] + (float) $values[ $mid ] ) / 2, 2 );
	}
}
