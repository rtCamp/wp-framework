<?php
/**
 * TimingMapper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize\Mappers;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\TimingMapper;

final class TimingMapperTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['timestart'] );

		parent::tearDown();
	}

	public function test_derives_counts_from_normalized_collectors(): void {
		$collectors = [
			'db_queries' => [
				'count'         => 12,
				'total_time_ms' => 34.5,
			],
			'php_errors' => [ 'count' => 2 ],
			'http'       => [ 'count' => 1 ],
		];

		$metrics = ( new TimingMapper() )->map( [], $collectors );

		$this->assertSame( 12, $metrics['query_count'] );
		$this->assertSame( 34.5, $metrics['query_time_ms'] );
		$this->assertSame( 2, $metrics['php_error_count'] );
		$this->assertSame( 1, $metrics['http_call_count'] );
		$this->assertGreaterThan( 0, $metrics['peak_memory_bytes'] );
	}

	public function test_total_time_from_wp_timestart_global(): void {
		$GLOBALS['timestart'] = microtime( true ) - 0.25;

		$metrics = ( new TimingMapper() )->map( [], [] );

		$this->assertIsFloat( $metrics['total_time_ms'] );
		$this->assertGreaterThan( 200, $metrics['total_time_ms'] );
		$this->assertLessThan( 5000, $metrics['total_time_ms'] );
	}

	public function test_total_time_null_without_timestart(): void {
		$this->assertNull( ( new TimingMapper() )->map( [], [] )['total_time_ms'] );
	}
}
