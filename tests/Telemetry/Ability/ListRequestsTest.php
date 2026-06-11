<?php
/**
 * ListRequests ability tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Ability;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Ability\ListRequests;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;
use rtCamp\WPFramework\Tests\Telemetry\NormalizedCaptures;

final class ListRequestsTest extends TestCase {

	use NormalizedCaptures;

	private RequestRepository $repository;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new RequestRepository( new FakeWpdb() );
	}

	public function test_execute_lists_summaries_with_filters(): void {
		$this->repository->save( $this->normalized_capture( [ 'type' => 'frontend' ] ) );
		$this->repository->save( $this->normalized_capture( [ 'type' => 'admin' ] ) );

		$result = ( new ListRequests( $this->repository ) )->execute( [ 'type' => 'frontend' ] );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['requests'] );
		$this->assertSame( 'frontend', $result['requests'][0]['type'] );
		// Summaries only — no payloads at this tier.
		$this->assertArrayNotHasKey( 'collectors', $result['requests'][0] );
	}

	public function test_execute_with_empty_store_returns_empty_list(): void {
		$result = ( new ListRequests( $this->repository ) )->execute( [] );

		$this->assertSame( [ 'requests' => [] ], $result );
	}
}
