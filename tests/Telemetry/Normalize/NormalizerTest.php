<?php
/**
 * Normalizer tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Capture\RequestContext;
use rtCamp\WPFramework\Telemetry\Normalize\Normalizer;

final class NormalizerTest extends TestCase {

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'] );

		parent::tearDown();
	}

	public function test_normalized_shape_has_all_top_level_blocks(): void {
		$_SERVER['HTTP_HOST']   = 'example.test';
		$_SERVER['REQUEST_URI'] = '/about/';

		$raw = [
			'db_queries' => [
				'rows' => [
					[
						'sql'   => 'SELECT 1',
						'ltime' => 0.001,
					],
				],
			],
		];

		$normalized = ( new Normalizer() )->normalize( $raw, new RequestContext() );

		$this->assertSame( Normalizer::SCHEMA_VERSION, $normalized['schema_version'] );
		$this->assertSame(
			[ 'uuid', 'url', 'method', 'type', 'status', 'label', 'captured_at' ],
			array_keys( $normalized['context'] )
		);
		$this->assertSame(
			[ 'db_queries', 'http', 'php_errors', 'assets' ],
			array_keys( $normalized['collectors'] )
		);
		$this->assertSame( 'http://example.test/about/', $normalized['context']['url'] );
		$this->assertSame( 'frontend', $normalized['context']['type'] );
	}

	public function test_metrics_reflect_collector_counts(): void {
		$raw = [
			'db_queries' => [
				'rows' => [
					[
						'sql'   => 'SELECT 1',
						'ltime' => 0.002,
					],
					[
						'sql'   => 'SELECT 2',
						'ltime' => 0.003,
					],
				],
			],
		];

		$normalized = ( new Normalizer() )->normalize( $raw, new RequestContext() );

		$this->assertSame( 2, $normalized['metrics']['query_count'] );
		$this->assertSame( 5.0, $normalized['metrics']['query_time_ms'] );
		$this->assertSame( 0, $normalized['metrics']['http_call_count'] );
	}

	public function test_captured_at_is_iso8601_utc(): void {
		$normalized = ( new Normalizer() )->normalize( [], new RequestContext() );

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
			$normalized['context']['captured_at']
		);
	}
}
