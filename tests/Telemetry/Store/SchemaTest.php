<?php
/**
 * Schema tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Store;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Store\Schema;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;

final class SchemaTest extends TestCase {

	protected function tearDown(): void {
		unset(
			$GLOBALS['wp_framework_test_options'][ Schema::OPTION ],
			$GLOBALS['wp_framework_test_dbdelta'],
			$GLOBALS['wpdb']
		);

		parent::tearDown();
	}

	public function test_statements_create_both_tables_with_prefix(): void {
		$statements = Schema::statements( 'wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci' );

		$this->assertCount( 2, $statements );
		$this->assertStringContainsString( 'CREATE TABLE wp_rt_framework_telemetry_requests', $statements[0] );
		$this->assertStringContainsString( 'CREATE TABLE wp_rt_framework_telemetry_request_data', $statements[1] );
		$this->assertStringContainsString( 'utf8mb4_unicode_520_ci', $statements[0] );
	}

	public function test_statements_honor_dbdelta_quirks(): void {
		$statements = Schema::statements( 'wp_', '' );

		foreach ( $statements as $statement ) {
			// dbDelta requires exactly two spaces after PRIMARY KEY.
			$this->assertStringContainsString( 'PRIMARY KEY  (', $statement );
			// dbDelta silently mangles FOREIGN KEY lines — none allowed.
			$this->assertStringNotContainsString( 'FOREIGN KEY', $statement );
		}
	}

	public function test_statements_respect_utf8mb4_index_prefix_limits(): void {
		$requests = Schema::statements( 'wp_', '' )[0];

		// 191 chars * 4 bytes = 764, under the 767-byte InnoDB COMPACT limit.
		$this->assertStringContainsString( 'KEY url (url(191))', $requests );
		$this->assertStringContainsString( 'label varchar(191)', $requests );
		$this->assertStringContainsString( 'uuid char(36)', $requests );
	}

	public function test_statements_use_longtext_payload(): void {
		$this->assertStringContainsString( 'payload longtext NOT NULL', Schema::statements( 'wp_', '' )[1] );
	}

	public function test_ensure_runs_dbdelta_once_then_short_circuits(): void {
		$GLOBALS['wpdb'] = new FakeWpdb();

		Schema::ensure();

		$this->assertCount( 2, $GLOBALS['wp_framework_test_dbdelta'] ?? [] );
		$this->assertSame( Schema::VERSION, get_option( Schema::OPTION ) );

		Schema::ensure();

		// Option matched — no second dbDelta run.
		$this->assertCount( 2, $GLOBALS['wp_framework_test_dbdelta'] );
	}
}
