<?php
/**
 * SQL normalization helpers.
 *
 * Normalizes SQL for duplicate fingerprinting and truncates it for
 * context-window-friendly telemetry payloads.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Support;

/**
 * Class - Sql
 */
final class Sql {
	/**
	 * Returns a fingerprint of the query with literals replaced by
	 * placeholders, e.g. "SELECT * FROM wp_posts WHERE ID = ?".
	 *
	 * Identical fingerprints across multiple queries indicate duplicates.
	 *
	 * @param string $sql Raw SQL.
	 */
	public static function fingerprint( string $sql ): string {
		$sql = preg_replace( '/\s+/', ' ', trim( $sql ) );
		// String literals.
		$sql = preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/s", '?', (string) $sql );
		$sql = preg_replace( '/"(?:[^"\\\\]|\\\\.)*"/s', '?', (string) $sql );
		// Numeric literals.
		$sql = preg_replace( '/\b\d+(\.\d+)?\b/', '?', (string) $sql );
		// Collapse IN-lists of placeholders down to a single placeholder.
		$sql = preg_replace( '/IN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?)', (string) $sql );

		return self::truncate( (string) $sql, 300 );
	}

	/**
	 * Truncates SQL for display: collapses whitespace, summarizes giant
	 * IN() lists, and caps length with an explicit marker.
	 *
	 * @param string $sql Raw SQL.
	 * @param int    $max Maximum length. Default 500.
	 */
	public static function truncate( string $sql, int $max = 500 ): string {
		$sql = (string) preg_replace( '/\s+/', ' ', trim( $sql ) );
		if ( strlen( $sql ) <= $max ) {
			return $sql;
		}

		// Summarize large IN(...) lists before hard-truncating.
		$sql = (string) preg_replace_callback(
			'/IN\s*\(([^()]{40,})\)/i',
			static function ( array $matches ): string {
				$count = substr_count( $matches[1], ',' ) + 1;
				return sprintf( 'IN (<%d values>)', $count );
			},
			$sql
		);

		if ( strlen( $sql ) <= $max ) {
			return $sql;
		}

		$overflow = strlen( $sql ) - $max;

		return substr( $sql, 0, $max ) . sprintf( ' …[+%d chars]', $overflow );
	}
}
