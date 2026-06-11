<?php
/**
 * Normalizes raw QM collector data into the stable telemetry schema.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Normalize;

use rtCamp\WPFramework\Telemetry\Capture\RequestContext;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\AssetsMapper;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\DbQueriesMapper;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\HttpMapper;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\PhpErrorsMapper;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\TimingMapper;

/**
 * Class - Normalizer
 */
final class Normalizer {
	/**
	 * Schema version stored with every capture.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Maps raw collector data to the normalized telemetry array.
	 *
	 * @param array<string, mixed> $raw     Collector id => QM_Data.
	 * @param RequestContext       $context Current request facts.
	 *
	 * @return array<string, mixed>
	 */
	public function normalize( array $raw, RequestContext $context ): array {
		$collectors = [
			'db_queries' => ( new DbQueriesMapper() )->map( $raw ),
			'http'       => ( new HttpMapper() )->map( $raw ),
			'php_errors' => ( new PhpErrorsMapper() )->map( $raw ),
			'assets'     => ( new AssetsMapper() )->map( $raw ),
		];

		return [
			'schema_version' => self::SCHEMA_VERSION,
			'context'        => [
				'uuid'        => $context->uuid(),
				'url'         => $context->url(),
				'method'      => $context->method(),
				'type'        => $context->type(),
				'status'      => $context->status(),
				'label'       => $context->label(),
				'captured_at' => gmdate( 'c' ),
			],
			'metrics'        => ( new TimingMapper() )->map( $raw, $collectors ),
			'collectors'     => $collectors,
		];
	}
}
