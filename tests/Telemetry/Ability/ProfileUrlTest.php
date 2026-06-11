<?php
/**
 * ProfileUrl ability tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Ability;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Ability\ProfileUrl;
use rtCamp\WPFramework\Telemetry\Config;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;
use rtCamp\WPFramework\Tests\Telemetry\NormalizedCaptures;
use WP_Error;

final class ProfileUrlTest extends TestCase {

	use NormalizedCaptures;

	private RequestRepository $repository;

	private ProfileUrl $ability;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_framework_test_home_url'] = 'http://example.test';

		$this->repository = new RequestRepository( new FakeWpdb() );
		$this->ability    = new ProfileUrl( $this->repository );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['wp_framework_test_home_url'],
			$GLOBALS['wp_framework_test_http_requests'],
			$GLOBALS['wp_framework_test_http_handler'],
			$_SERVER[ Config::SERVER_PROFILE ]
		);

		parent::tearDown();
	}

	public function test_execute_refuses_recursion(): void {
		$_SERVER[ Config::SERVER_PROFILE ] = 'outer-batch';

		$result = $this->ability->execute( [ 'url' => 'http://example.test/' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rt_framework_telemetry_recursion', $result->get_error_code() );
	}

	public function test_execute_rejects_foreign_hosts(): void {
		$result = $this->ability->execute( [ 'url' => 'https://evil.example.com/' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rt_framework_telemetry_invalid_url', $result->get_error_code() );
	}

	public function test_execute_profiles_and_returns_medians(): void {
		// Simulate the server side: every loopback request triggers a shutdown
		// capture labeled with the batch carried by the profile header.
		$GLOBALS['wp_framework_test_http_handler'] = function ( string $url, array $args ): array {
			$batch   = $args['headers'][ Config::HEADER_PROFILE ];
			$capture = $this->normalized_capture( [ 'label' => 'profile:' . $batch ] );

			$capture['metrics']['total_time_ms'] = 100.0 + ( 10 * count( $GLOBALS['wp_framework_test_http_requests'] ) );
			$this->repository->save( $capture );

			return [ 'response' => [ 'code' => 200 ] ];
		};

		$result = $this->ability->execute(
			[
				'url'     => 'http://example.test/slow/?variant=b',
				'samples' => 3,
			]
		);

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result['request_ids'] );
		$this->assertSame( [ [ 'status' => 200 ], [ 'status' => 200 ], [ 'status' => 200 ] ], $result['samples'] );
		// Samples measured 110/120/130 — median 120.
		$this->assertSame( 120.0, $result['medians']['total_time_ms'] );
		$this->assertSame( 2.0, $result['medians']['query_count'] );

		// The loopback request hit the rewritten target with the original
		// Host header and the batch-labeled profile header.
		$request = $GLOBALS['wp_framework_test_http_requests'][0];
		$this->assertSame( 'http://example.test/slow/?variant=b', $request['url'] );
		$this->assertSame( 'example.test', $request['args']['headers']['Host'] );
		$this->assertSame( $result['batch'], $request['args']['headers'][ Config::HEADER_PROFILE ] );
	}

	public function test_execute_clamps_sample_count(): void {
		$GLOBALS['wp_framework_test_http_handler'] = function ( string $url, array $args ): array {
			$this->repository->save(
				$this->normalized_capture( [ 'label' => 'profile:' . $args['headers'][ Config::HEADER_PROFILE ] ] )
			);

			return [ 'response' => [ 'code' => 200 ] ];
		};

		$result = $this->ability->execute(
			[
				'url'     => 'http://example.test/',
				'samples' => 99,
			]
		);

		$this->assertIsArray( $result );
		$this->assertCount( 5, $result['samples'] );
	}

	public function test_execute_errors_when_nothing_captured(): void {
		// Default stub returns 200 but never stores a capture (QM inactive).
		$result = $this->ability->execute( [ 'url' => 'http://example.test/page/' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rt_framework_telemetry_capture_failed', $result->get_error_code() );
		// Misconfiguration must be self-diagnosing: the error echoes the target.
		$this->assertStringContainsString( 'http://example.test/page/', $result->get_error_message() );
	}
}
