<?php
/**
 * Defensive accessors for Query Monitor data structures.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize;

/**
 * QM's QM_Data objects are typed in V4 but historically behaved like arrays
 * (ArrayAccess kept for back-compat). These helpers read either shape without
 * fataling when a key/property is missing — schema drift in QM must degrade
 * to missing fields, never to a broken capture.
 */
final class QmData {
	/**
	 * Reads a key from an array, object property, or ArrayAccess offset.
	 *
	 * @param mixed  $data        QM data carrier.
	 * @param string $key         Key/property name.
	 * @param mixed  $default_val Default when absent.
	 */
	public static function get( mixed $data, string $key, mixed $default_val = null ): mixed {
		if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
			return $data[ $key ];
		}

		if ( is_object( $data ) ) {
			if ( isset( $data->$key ) ) {
				return $data->$key;
			}
			if ( $data instanceof \ArrayAccess && $data->offsetExists( $key ) ) {
				return $data[ $key ];
			}
		}

		return $default_val;
	}

	/**
	 * Looks up a collector's data by id, tolerating the "qm-" prefix.
	 *
	 * @param array<string, mixed> $raw Collector id => QM_Data map from CollectorBridge.
	 * @param string               $id  Bare collector id, e.g. "db_queries".
	 */
	public static function collector( array $raw, string $id ): mixed {
		return $raw[ $id ] ?? $raw[ 'qm-' . $id ] ?? null;
	}

	/**
	 * Normalizes a QM trace into plain frames {display, function, file, line}.
	 *
	 * Accepts a QM_Backtrace object, an array of frame arrays, an array of
	 * display strings, or null.
	 *
	 * @param mixed $trace Trace carrier.
	 *
	 * @return array<int, array{display: string, function: ?string, file: ?string, line: ?int}>
	 */
	public static function frames( mixed $trace ): array {
		if ( is_object( $trace ) && is_callable( [ $trace, 'get_filtered_trace' ] ) ) {
			$trace = $trace->get_filtered_trace();
		}

		if ( ! is_array( $trace ) ) {
			return [];
		}

		$frames = [];
		foreach ( $trace as $frame ) {
			if ( is_string( $frame ) ) {
				$frames[] = [
					'display'  => $frame,
					'function' => null,
					'file'     => null,
					'line'     => null,
				];
				continue;
			}

			$file = self::get( $frame, 'file', self::get( $frame, 'calling_file' ) );
			$line = self::get( $frame, 'line', self::get( $frame, 'calling_line' ) );

			// QM 4.x frames carry the callable name under 'id'
			// (e.g. "wp_load_alloptions"); older shapes used 'function'/'display'.
			$name    = (string) self::get( $frame, 'function', (string) self::get( $frame, 'id', '' ) );
			$display = (string) self::get( $frame, 'display', '' );
			if ( '' === $display && '' !== $name ) {
				$display = $name . '()';
			}

			$frames[] = [
				'display'  => $display,
				'function' => '' !== $name ? $name : null,
				'file'     => $file ? (string) $file : null,
				'line'     => $line ? (int) $line : null,
			];
		}

		return $frames;
	}

	/**
	 * First frame that carries a file and line (the likely call site).
	 *
	 * @param array<int, array{display: string, function: ?string, file: ?string, line: ?int}> $frames Frames from self::frames().
	 *
	 * @return array{display: string, function: ?string, file: ?string, line: ?int}
	 */
	public static function call_site( array $frames ): array {
		foreach ( $frames as $frame ) {
			if ( ! empty( $frame['file'] ) && ! empty( $frame['line'] ) ) {
				return $frame;
			}
		}

		return [
			'display'  => '',
			'function' => null,
			'file'     => null,
			'line'     => null,
		];
	}

	/**
	 * Human-readable component name from a QM_Component, string, or null.
	 *
	 * @param mixed $component Component carrier.
	 */
	public static function component_name( mixed $component ): ?string {
		if ( is_string( $component ) && '' !== $component ) {
			return $component;
		}

		if ( is_object( $component ) ) {
			$name = self::get( $component, 'name' );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}

		return null;
	}
}
