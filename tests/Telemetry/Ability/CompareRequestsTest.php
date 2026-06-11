<?php
/**
 * CompareRequests ability tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Ability;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Ability\CompareRequests;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;
use rtCamp\WPFramework\Tests\Telemetry\NormalizedCaptures;
use WP_Error;

final class CompareRequestsTest extends TestCase {

	use NormalizedCaptures;

	private RequestRepository $repository;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new RequestRepository( new FakeWpdb() );
	}

	public function test_execute_computes_deltas_with_percentages(): void {
		$before = wp_generate_uuid4();
		$after  = wp_generate_uuid4();

		$capture                                = $this->normalized_capture( [ 'uuid' => $before ] );
		$capture['metrics']['total_time_ms']    = 200.0;
		$capture['metrics']['query_count']      = 40;
		$this->repository->save( $capture );

		$improved                             = $this->normalized_capture( [ 'uuid' => $after ] );
		$improved['metrics']['total_time_ms'] = 100.0;
		$improved['metrics']['query_count']   = 10;
		$this->repository->save( $improved );

		$result = ( new CompareRequests( $this->repository ) )->execute(
			[
				'before_request_id' => $before,
				'after_request_id'  => $after,
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( $before, $result['before']['id'] );
		$this->assertSame( $after, $result['after']['id'] );

		$this->assertSame( -100.0, $result['deltas']['total_time_ms']['delta'] );
		$this->assertSame( -50.0, $result['deltas']['total_time_ms']['delta_pct'] );
		$this->assertSame( -30.0, $result['deltas']['query_count']['delta'] );
		$this->assertSame( -75.0, $result['deltas']['query_count']['delta_pct'] );
	}

	public function test_execute_with_unknown_id_returns_wp_error(): void {
		$known = wp_generate_uuid4();
		$this->repository->save( $this->normalized_capture( [ 'uuid' => $known ] ) );

		$result = ( new CompareRequests( $this->repository ) )->execute(
			[
				'before_request_id' => $known,
				'after_request_id'  => 'nope',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rt_framework_telemetry_not_found', $result->get_error_code() );
	}
}
