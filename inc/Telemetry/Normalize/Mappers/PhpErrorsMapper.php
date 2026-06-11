<?php
/**
 * Normalizes QM's php_errors collector data.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize\Mappers;

use rtCamp\WPFramework\Telemetry\Normalize\QmData;

/**
 * QM 4.x stores a flat hash-keyed map: errors[hash] =
 * {errno, level, suppressed, message, trace, count} — file/line come from
 * the trace. Older QM grouped by level: errors[level][hash] = {...}.
 * Both shapes are handled.
 */
final class PhpErrorsMapper {
	/**
	 * PHP errno => normalized level.
	 */
	private const ERRNO_LEVEL = [
		E_ERROR             => 'error',
		E_WARNING           => 'warning',
		E_NOTICE            => 'notice',
		E_USER_ERROR        => 'error',
		E_USER_WARNING      => 'warning',
		E_USER_NOTICE       => 'notice',
		E_DEPRECATED        => 'deprecated',
		E_USER_DEPRECATED   => 'deprecated',
		E_RECOVERABLE_ERROR => 'error',
	];

	/**
	 * Maps to {count, errors[]}.
	 *
	 * @param array<string, mixed> $raw Collector id => QM_Data.
	 *
	 * @return array<string, mixed>
	 */
	public function map( array $raw ): array {
		$data   = QmData::collector( $raw, 'php_errors' );
		$groups = QmData::get( $data, 'errors', [] );

		$errors = [];
		foreach ( (array) $groups as $key => $entry ) {
			if ( null !== QmData::get( $entry, 'message' ) ) {
				// Flat QM 4.x shape: each entry IS an error item.
				$errors[] = $this->map_item( $entry, null );
				continue;
			}
			// Legacy nested shape: outer key is the level.
			foreach ( (array) $entry as $item ) {
				$errors[] = $this->map_item( $item, (string) $key );
			}
		}

		return [
			'count'  => count( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Maps one QM error item to the normalized shape.
	 *
	 * @param mixed       $item           QM error entry.
	 * @param string|null $level_fallback Level implied by a legacy group key.
	 *
	 * @return array<string, mixed>
	 */
	private function map_item( mixed $item, ?string $level_fallback ): array {
		$frames = QmData::frames( QmData::get( $item, 'trace', QmData::get( $item, 'filtered_trace' ) ) );
		$site   = QmData::call_site( $frames );

		$level = QmData::get( $item, 'level' );
		if ( ! is_string( $level ) || '' === $level ) {
			$errno = (int) QmData::get( $item, 'errno', 0 );
			$level = self::ERRNO_LEVEL[ $errno ] ?? ( $level_fallback ?? 'notice' );
		}

		$file = QmData::get( $item, 'file', $site['file'] );
		$line = QmData::get( $item, 'line', $site['line'] );

		return [
			'level'      => $level,
			'message'    => (string) QmData::get( $item, 'message', '' ),
			'suppressed' => (bool) QmData::get( $item, 'suppressed', false ),
			'file'       => $file ? (string) $file : null,
			'line'       => $line ? (int) $line : null,
			'count'      => (int) QmData::get( $item, 'count', QmData::get( $item, 'calls', 1 ) ),
			'component'  => QmData::component_name( QmData::get( $item, 'component' ) ),
			'stack'      => $frames,
		];
	}
}
