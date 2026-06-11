<?php
/**
 * DbQueriesMapper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize\Mappers;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\DbQueriesMapper;

final class DbQueriesMapperTest extends TestCase {

	/**
	 * Canned QM 4.x-shaped db_queries collector data.
	 *
	 * @return array<string, mixed>
	 */
	private function raw(): array {
		return [
			'db_queries' => [
				'total_time' => 0.0421,
				'total_qs'   => 2,
				'rows'       => [
					[
						'sql'       => "SELECT * FROM wp_posts WHERE ID = 7 AND post_status = 'publish'",
						'ltime'     => 0.0301,
						'caller'    => 'get_post()',
						'component' => 'Plugin: my-plugin',
						'trace'     => [
							[
								'id'   => 'get_post',
								'file' => '/var/www/html/wp-content/plugins/my-plugin/inc/Posts.php',
								'line' => 12,
							],
						],
					],
					[
						'sql'   => 'SELECT option_value FROM wp_options WHERE option_name = "siteurl"',
						'ltime' => 0.012,
					],
				],
			],
		];
	}

	public function test_maps_count_total_time_and_rows(): void {
		$mapped = ( new DbQueriesMapper() )->map( $this->raw() );

		$this->assertSame( 2, $mapped['count'] );
		$this->assertSame( 42.1, $mapped['total_time_ms'] );
		$this->assertCount( 2, $mapped['queries'] );
	}

	public function test_maps_query_fields_with_fingerprint_and_call_site(): void {
		$mapped = ( new DbQueriesMapper() )->map( $this->raw() );
		$query  = $mapped['queries'][0];

		$this->assertSame( 'SELECT * FROM wp_posts WHERE ID = ? AND post_status = ?', $query['sql_fingerprint'] );
		$this->assertSame( 30.1, $query['time_ms'] );
		$this->assertSame( 'get_post()', $query['caller'] );
		$this->assertSame( 'Plugin: my-plugin', $query['component'] );
		$this->assertSame( '/var/www/html/wp-content/plugins/my-plugin/inc/Posts.php', $query['file'] );
		$this->assertSame( 12, $query['line'] );
		$this->assertNotEmpty( $query['stack'] );
	}

	public function test_falls_back_to_total_qs_when_rows_absent(): void {
		$mapped = ( new DbQueriesMapper() )->map( [ 'db_queries' => [ 'total_qs' => 31 ] ] );

		$this->assertSame( 31, $mapped['count'] );
		$this->assertSame( [], $mapped['queries'] );
	}

	public function test_missing_collector_degrades_to_zeroes(): void {
		$mapped = ( new DbQueriesMapper() )->map( [] );

		$this->assertSame( 0, $mapped['count'] );
		$this->assertSame( 0.0, $mapped['total_time_ms'] );
		$this->assertSame( [], $mapped['queries'] );
	}
}
