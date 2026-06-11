<?php
/**
 * Telemetry Sql helper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Support;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Support\Sql;

final class SqlTest extends TestCase {

	public function test_fingerprint_replaces_string_literals(): void {
		$this->assertSame(
			'SELECT * FROM wp_posts WHERE post_status = ?',
			Sql::fingerprint( "SELECT * FROM wp_posts WHERE post_status = 'publish'" )
		);
	}

	public function test_fingerprint_replaces_numeric_literals(): void {
		$this->assertSame(
			'SELECT * FROM wp_posts WHERE ID = ? LIMIT ?',
			Sql::fingerprint( 'SELECT * FROM wp_posts WHERE ID = 42 LIMIT 10' )
		);
	}

	public function test_fingerprint_collapses_in_lists(): void {
		$this->assertSame(
			'SELECT * FROM wp_posts WHERE ID IN (?)',
			Sql::fingerprint( 'SELECT * FROM wp_posts WHERE ID IN (1, 2, 3, 4, 5)' )
		);
	}

	public function test_fingerprint_makes_duplicates_identical(): void {
		$first  = Sql::fingerprint( "SELECT * FROM wp_postmeta WHERE post_id = 7 AND meta_key = 'color'" );
		$second = Sql::fingerprint( "SELECT * FROM wp_postmeta WHERE post_id = 99 AND meta_key = 'size'" );

		$this->assertSame( $first, $second );
	}

	public function test_truncate_collapses_whitespace(): void {
		$this->assertSame(
			'SELECT * FROM wp_posts',
			Sql::truncate( "SELECT   *\n\tFROM   wp_posts" )
		);
	}

	public function test_truncate_returns_short_sql_unchanged(): void {
		$this->assertSame( 'SELECT 1', Sql::truncate( 'SELECT 1' ) );
	}

	public function test_truncate_summarizes_large_in_lists(): void {
		$ids = implode( ', ', range( 1, 100 ) );
		$sql = 'SELECT * FROM wp_posts WHERE ID IN (' . $ids . ')';

		$truncated = Sql::truncate( $sql, 100 );

		$this->assertStringContainsString( 'IN (<100 values>)', $truncated );
	}

	public function test_truncate_caps_length_with_marker(): void {
		$sql = 'SELECT ' . str_repeat( 'a', 600 );

		$truncated = Sql::truncate( $sql, 100 );

		$this->assertStringContainsString( '…[+', $truncated );
		// 100 chars + marker suffix only.
		$this->assertLessThan( 130, strlen( $truncated ) );
	}
}
