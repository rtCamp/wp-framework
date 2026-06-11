<?php
/**
 * Shared builder for normalized capture fixtures.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry;

/**
 * Trait - NormalizedCaptures
 */
trait NormalizedCaptures {

	/**
	 * A normalized capture in the schema Normalizer::normalize() produces.
	 *
	 * @param array<string, mixed> $context_overrides Context overrides.
	 *
	 * @return array<string, mixed>
	 */
	private function normalized_capture( array $context_overrides = [] ): array {
		return [
			'schema_version' => 1,
			'context'        => $context_overrides + [
				'uuid'        => wp_generate_uuid4(),
				'url'         => 'http://example.test/',
				'method'      => 'GET',
				'type'        => 'frontend',
				'status'      => 200,
				'label'       => null,
				'captured_at' => '2026-06-11T10:00:00+00:00',
			],
			'metrics'        => [
				'total_time_ms'     => 150.0,
				'peak_memory_bytes' => 8388608,
				'query_count'       => 2,
				'query_time_ms'     => 12.5,
				'php_error_count'   => 0,
				'http_call_count'   => 0,
			],
			'collectors'     => [
				'db_queries' => [
					'count'         => 2,
					'total_time_ms' => 12.5,
					'queries'       => [
						[
							'sql'             => 'SELECT 1',
							'sql_fingerprint' => 'SELECT ?',
							'time_ms'         => 10.0,
							'caller'          => 'get_posts()',
							'component'       => 'Plugin: my-plugin',
							'file'            => '/var/www/html/wp-content/plugins/my-plugin/inc/Query.php',
							'line'            => 14,
							'stack'           => [
								[
									'display' => 'get_posts()',
									'file'    => '/var/www/html/wp-content/plugins/my-plugin/inc/Query.php',
									'line'    => 14,
								],
							],
						],
					],
				],
				'http'       => [
					'count'         => 0,
					'total_time_ms' => 0.0,
					'calls'         => [],
				],
				'php_errors' => [
					'count'  => 0,
					'errors' => [],
				],
				'assets'     => [
					'scripts' => [ 'handles' => [] ],
					'styles'  => [ 'handles' => [] ],
				],
			],
		];
	}
}
