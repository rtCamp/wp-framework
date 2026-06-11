<?php
/**
 * GetTelemetry ability tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Ability;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Ability\GetTelemetry;
use rtCamp\WPFramework\Telemetry\Path\PathTranslator;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;
use rtCamp\WPFramework\Tests\Telemetry\NormalizedCaptures;
use WP_Error;

final class GetTelemetryTest extends TestCase {

	use NormalizedCaptures;

	private RequestRepository $repository;

	private GetTelemetry $ability;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new RequestRepository( new FakeWpdb() );
		$this->ability    = new GetTelemetry(
			$this->repository,
			new PathTranslator( [ '/var/www/html/wp-content/plugins/my-plugin' => '/Users/dev/my-plugin' ] )
		);
	}

	public function test_execute_returns_telemetry_with_host_paths(): void {
		$uuid = wp_generate_uuid4();
		$this->repository->save( $this->normalized_capture( [ 'uuid' => $uuid ] ) );

		$result = $this->ability->execute( [ 'request_id' => $uuid ] );

		$this->assertIsArray( $result );
		$this->assertSame( $uuid, $result['request']['id'] );
		$this->assertArrayNotHasKey( 'collectors', $result['request'] );

		$query = $result['telemetry']['db_queries']['queries'][0];
		$this->assertSame( '/Users/dev/my-plugin/inc/Query.php', $query['host_file'] );
		$this->assertSame( '/Users/dev/my-plugin/inc/Query.php', $query['stack'][0]['host_file'] );

		$this->assertSame( 150.0, $result['telemetry']['metrics']['total_time_ms'] );
		$this->assertFalse( $result['truncated'] );
	}

	public function test_execute_defaults_to_latest_capture(): void {
		$this->repository->save( $this->normalized_capture() );
		$latest = wp_generate_uuid4();
		$this->repository->save( $this->normalized_capture( [ 'uuid' => $latest ] ) );

		$result = $this->ability->execute( [] );

		$this->assertIsArray( $result );
		$this->assertSame( $latest, $result['request']['id'] );
	}

	public function test_execute_by_url_picks_latest_of_that_url(): void {
		$target = wp_generate_uuid4();
		$this->repository->save(
			$this->normalized_capture(
				[
					'uuid' => $target,
					'url'  => 'http://example.test/slow-page/',
				]
			)
		);
		$this->repository->save( $this->normalized_capture( [ 'url' => 'http://example.test/other/' ] ) );

		$result = $this->ability->execute( [ 'url' => 'http://example.test/slow-page/' ] );

		$this->assertIsArray( $result );
		$this->assertSame( $target, $result['request']['id'] );
	}

	public function test_execute_translates_php_error_paths(): void {
		$uuid    = wp_generate_uuid4();
		$capture = $this->normalized_capture( [ 'uuid' => $uuid ] );

		$capture['collectors']['php_errors'] = [
			'count'  => 1,
			'errors' => [
				[
					'level'      => 'notice',
					'message'    => 'Something looked off',
					'suppressed' => false,
					'file'       => '/var/www/html/wp-content/plugins/my-plugin/inc/Demo.php',
					'line'       => 9,
					'count'      => 1,
					'component'  => 'Plugin: my-plugin',
					'stack'      => [
						[
							'display' => 'demo()',
							'file'    => '/var/www/html/wp-content/plugins/my-plugin/inc/Demo.php',
							'line'    => 9,
						],
					],
				],
			],
		];
		$this->repository->save( $capture );

		$result = $this->ability->execute( [ 'request_id' => $uuid ] );

		$this->assertIsArray( $result );
		$error = $result['telemetry']['php_errors']['errors'][0];
		$this->assertSame( '/Users/dev/my-plugin/inc/Demo.php', $error['host_file'] );
		$this->assertSame( '/Users/dev/my-plugin/inc/Demo.php', $error['stack'][0]['host_file'] );
	}

	public function test_execute_with_no_captures_returns_wp_error(): void {
		$result = $this->ability->execute( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rt_framework_telemetry_no_captures', $result->get_error_code() );
	}
}
