<?php
/**
 * RequestRepository round-trip tests against the SQLite-backed wpdb fake.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Store;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Telemetry\Store\Schema;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;

final class RequestRepositoryTest extends TestCase {

	private FakeWpdb $db;

	private RequestRepository $repository;

	protected function setUp(): void {
		parent::setUp();

		$this->db         = new FakeWpdb();
		$this->repository = new RequestRepository( $this->db );
	}

	/**
	 * A minimal normalized capture.
	 *
	 * @param array<string, mixed> $context_overrides Context overrides.
	 *
	 * @return array<string, mixed>
	 */
	private function normalized( array $context_overrides = [] ): array {
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
				'total_time_ms'     => 120.5,
				'peak_memory_bytes' => 8388608,
				'query_count'       => 12,
				'query_time_ms'     => 34.5,
				'php_error_count'   => 0,
				'http_call_count'   => 1,
			],
			'collectors'     => [
				'db_queries' => [
					'count'   => 12,
					'queries' => [],
				],
				'http'       => [ 'count' => 1 ],
			],
		];
	}

	public function test_save_then_find_round_trips_summary_and_collectors(): void {
		$uuid = wp_generate_uuid4();
		$this->repository->save( $this->normalized( [ 'uuid' => $uuid ] ) );

		$found = $this->repository->find( $uuid );

		$this->assertNotNull( $found );
		$this->assertSame( $uuid, $found['id'] );
		$this->assertSame( 'http://example.test/', $found['url'] );
		$this->assertSame( 200, $found['status'] );
		$this->assertSame( 120.5, $found['total_time_ms'] );
		$this->assertSame( 8.0, $found['peak_memory_mb'] );
		$this->assertSame( '2026-06-11T10:00:00+00:00', $found['captured_at'] );
		$this->assertSame( 12, $found['collectors']['db_queries']['count'] );
		$this->assertArrayHasKey( '_internal_id', $found );
	}

	public function test_find_unknown_uuid_returns_null(): void {
		$this->assertNull( $this->repository->find( 'no-such-uuid' ) );
	}

	public function test_list_filters_by_type_and_respects_limit(): void {
		$this->repository->save( $this->normalized( [ 'type' => 'frontend' ] ) );
		$this->repository->save( $this->normalized( [ 'type' => 'admin' ] ) );
		$this->repository->save( $this->normalized( [ 'type' => 'frontend' ] ) );

		$frontend = $this->repository->list( [ 'type' => 'frontend' ] );
		$this->assertCount( 2, $frontend );

		$limited = $this->repository->list( [ 'limit' => 1 ] );
		$this->assertCount( 1, $limited );
	}

	public function test_list_orders_newest_first(): void {
		$first  = wp_generate_uuid4();
		$second = wp_generate_uuid4();
		$this->repository->save( $this->normalized( [ 'uuid' => $first ] ) );
		$this->repository->save( $this->normalized( [ 'uuid' => $second ] ) );

		$rows = $this->repository->list();

		$this->assertSame( $second, $rows[0]['id'] );
		$this->assertSame( $first, $rows[1]['id'] );
	}

	public function test_latest_returns_full_record_filtered_by_url(): void {
		$this->repository->save( $this->normalized( [ 'url' => 'http://example.test/a/' ] ) );
		$target = wp_generate_uuid4();
		$this->repository->save(
			$this->normalized(
				[
					'uuid' => $target,
					'url'  => 'http://example.test/b/',
				]
			)
		);

		$latest = $this->repository->latest( 'http://example.test/b/' );

		$this->assertNotNull( $latest );
		$this->assertSame( $target, $latest['id'] );
		$this->assertArrayHasKey( 'collectors', $latest );
	}

	public function test_by_label_finds_profile_batches(): void {
		$this->repository->save( $this->normalized( [ 'label' => 'profile:batch-1' ] ) );
		$this->repository->save( $this->normalized( [ 'label' => 'profile:batch-1' ] ) );
		$this->repository->save( $this->normalized() );

		$this->assertCount( 2, $this->repository->by_label( 'profile:batch-1' ) );
	}

	public function test_internal_id_maps_uuid(): void {
		$uuid = wp_generate_uuid4();
		$id   = $this->repository->save( $this->normalized( [ 'uuid' => $uuid ] ) );

		$this->assertSame( $id, $this->repository->internal_id( $uuid ) );
		$this->assertNull( $this->repository->internal_id( 'nope' ) );
	}

	public function test_prune_keeps_newest_n_in_both_tables(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->repository->save( $this->normalized(), 3 );
		}

		$this->assertSame( 3, $this->db->count_rows( 'wp_' . Schema::REQUESTS ) );
		// 2 collectors per request.
		$this->assertSame( 6, $this->db->count_rows( 'wp_' . Schema::REQUEST_DATA ) );

		// The survivors are the 3 newest.
		$this->assertCount( 3, $this->repository->list( [ 'limit' => 50 ] ) );
	}

	public function test_unencodable_payload_does_not_abort_save(): void {
		$normalized                            = $this->normalized();
		$normalized['collectors']['db_queries'] = [ 'sql' => "\xB1\x31" ]; // Invalid UTF-8.

		$uuid                          = wp_generate_uuid4();
		$normalized['context']['uuid'] = $uuid;

		$this->repository->save( $normalized );

		$found = $this->repository->find( $uuid );

		$this->assertNotNull( $found );
		$this->assertTrue( $found['collectors']['db_queries']['_encode_error'] );
		$this->assertSame( 1, $found['collectors']['http']['count'] );
	}
}
