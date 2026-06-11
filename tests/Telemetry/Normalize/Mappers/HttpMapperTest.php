<?php
/**
 * HttpMapper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize\Mappers;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\HttpMapper;
use WP_Error;

final class HttpMapperTest extends TestCase {

	public function test_maps_successful_call(): void {
		$raw = [
			'http' => [
				'http' => [
					[
						'url'    => 'https://api.example.com/v1/items',
						'args'   => [
							'method'  => 'post',
							'timeout' => 5,
						],
						'result' => [ 'response' => [ 'code' => 201 ] ],
						'ltime'  => 0.85,
						'trace'  => [
							[
								'id'   => 'my_sync_job',
								'file' => '/var/www/html/wp-content/plugins/my-plugin/inc/Sync.php',
								'line' => 33,
							],
						],
					],
				],
			],
		];

		$mapped = ( new HttpMapper() )->map( $raw );
		$call   = $mapped['calls'][0];

		$this->assertSame( 1, $mapped['count'] );
		$this->assertSame( 850.0, $mapped['total_time_ms'] );
		$this->assertSame( 'POST', $call['method'] );
		$this->assertSame( 5, $call['timeout'] );
		$this->assertSame( 201, $call['response_code'] );
		$this->assertNull( $call['error'] );
		$this->assertSame( '/var/www/html/wp-content/plugins/my-plugin/inc/Sync.php', $call['file'] );
	}

	public function test_maps_wp_error_result(): void {
		$raw = [
			'http' => [
				'http' => [
					[
						'url'    => 'https://api.example.com/down',
						'result' => new WP_Error( 'http_request_failed', 'cURL error 28: timeout' ),
						'ltime'  => 5.0,
					],
				],
			],
		];

		$call = ( new HttpMapper() )->map( $raw )['calls'][0];

		$this->assertSame( 'http_request_failed: cURL error 28: timeout', $call['error'] );
		$this->assertNull( $call['response_code'] );
		$this->assertSame( 'GET', $call['method'] );
	}

	public function test_missing_collector_degrades_to_zeroes(): void {
		$mapped = ( new HttpMapper() )->map( [] );

		$this->assertSame( 0, $mapped['count'] );
		$this->assertSame( [], $mapped['calls'] );
	}
}
