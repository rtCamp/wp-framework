<?php
/**
 * Normalizes QM's assets_scripts / assets_styles collector data.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize\Mappers;

use rtCamp\WPFramework\Telemetry\Normalize\QmData;

/**
 * Minimal mapping: flat handle lists per asset type. Richer
 * source/dependency data can be added to the schema when needed.
 */
final class AssetsMapper {
	/**
	 * Maps to {scripts: {handles[]}, styles: {handles[]}}.
	 *
	 * @param array<string, mixed> $raw Collector id => QM_Data.
	 *
	 * @return array<string, array{handles: string[]}>
	 */
	public function map( array $raw ): array {
		return [
			'scripts' => [ 'handles' => $this->handles( QmData::collector( $raw, 'assets_scripts' ) ) ],
			'styles'  => [ 'handles' => $this->handles( QmData::collector( $raw, 'assets_styles' ) ) ],
		];
	}

	/**
	 * Collects printed asset handles from whatever grouping QM used.
	 *
	 * @param mixed $data QM assets data carrier.
	 *
	 * @return string[]
	 */
	private function handles( mixed $data ): array {
		$handles = [];

		// QM groups handles into position buckets (header/footer/...) under 'assets'.
		// array_walk_recursive() needs a real variable — a `(array)` cast
		// cannot be passed by reference.
		$assets = (array) QmData::get( $data, 'assets', [] );
		array_walk_recursive(
			$assets,
			static function ( mixed $value ) use ( &$handles ): void {
				if ( is_string( $value ) && '' !== $value ) {
					$handles[] = $value;
				}
			}
		);

		// Older/simpler shapes: flat lists under header/footer.
		foreach ( [ 'header', 'footer' ] as $bucket ) {
			foreach ( (array) QmData::get( $data, $bucket, [] ) as $handle ) {
				if ( is_string( $handle ) && '' !== $handle ) {
					$handles[] = $handle;
				}
			}
		}

		return array_values( array_unique( $handles ) );
	}
}
