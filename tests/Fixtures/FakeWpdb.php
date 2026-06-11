<?php
/**
 * SQLite-backed wpdb fake for repository round-trip tests.
 *
 * Implements only the wpdb surface RequestRepository uses. The runtime SQL
 * the repository emits (INSERT/SELECT/DELETE/LIMIT/OFFSET) is dialect-neutral;
 * MySQL-specific DDL correctness is covered by the Schema string tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Fixtures;

use PDO;
use rtCamp\WPFramework\Telemetry\Store\Schema;

// phpcs:disable WordPress.DB, WordPress.NamingConventions -- test fixture mimicking wpdb's API.

final class FakeWpdb extends \wpdb {

	public string $prefix = 'wp_';

	public int $insert_id = 0;

	private PDO $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec(
			'CREATE TABLE ' . $this->prefix . Schema::REQUESTS . ' (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				uuid TEXT NOT NULL UNIQUE,
				url TEXT NOT NULL,
				method TEXT NOT NULL DEFAULT "GET",
				type TEXT NOT NULL DEFAULT "frontend",
				status INTEGER,
				label TEXT,
				captured_at TEXT NOT NULL,
				schema_version INTEGER NOT NULL DEFAULT 1,
				total_time_ms REAL,
				query_count INTEGER,
				query_time_ms REAL,
				peak_memory_bytes INTEGER,
				php_error_count INTEGER,
				http_call_count INTEGER
			)'
		);
		$this->pdo->exec(
			'CREATE TABLE ' . $this->prefix . Schema::REQUEST_DATA . ' (
				request_id INTEGER NOT NULL,
				collector TEXT NOT NULL,
				payload TEXT NOT NULL,
				PRIMARY KEY (request_id, collector)
			)'
		);
	}

	/**
	 * Mimics wpdb::prepare() for %s/%d/%f placeholders.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( [] === $args ) {
			return $query;
		}
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$index = 0;

		return (string) preg_replace_callback(
			'/%[sdf]/',
			function ( array $matches ) use ( &$index, $args ): string {
				$value = $args[ $index++ ] ?? null;

				return match ( $matches[0] ) {
					'%d' => (string) (int) $value,
					'%f' => (string) (float) $value,
					default => "'" . str_replace( "'", "''", (string) $value ) . "'",
				};
			},
			$query
		);
	}

	public function query( string $sql ): int|bool {
		if ( 'START TRANSACTION' === $sql ) {
			$sql = 'BEGIN';
		}

		return $this->pdo->exec( $sql );
	}

	/**
	 * Mimics wpdb::insert() (format args ignored; PDO binds values).
	 *
	 * @param string               $table  Table name.
	 * @param array<string, mixed> $data   Column => value.
	 * @param mixed                $format Ignored.
	 */
	public function insert( string $table, array $data, mixed $format = null ): int|bool {
		$columns      = array_keys( $data );
		$placeholders = implode( ', ', array_fill( 0, count( $columns ), '?' ) );

		$statement = $this->pdo->prepare(
			'INSERT INTO ' . $table . ' (' . implode( ', ', $columns ) . ') VALUES (' . $placeholders . ')'
		);
		$statement->execute( array_values( $data ) );

		$this->insert_id = (int) $this->pdo->lastInsertId();

		return 1;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( string $sql, string $output = ARRAY_A ): array {
		$statement = $this->pdo->query( $sql );

		return false === $statement ? [] : $statement->fetchAll( PDO::FETCH_ASSOC );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $sql, string $output = ARRAY_A ): ?array {
		$statement = $this->pdo->query( $sql );
		if ( false === $statement ) {
			return null;
		}
		$row = $statement->fetch( PDO::FETCH_ASSOC );

		return false === $row ? null : $row;
	}

	public function get_var( string $sql ): mixed {
		$statement = $this->pdo->query( $sql );
		if ( false === $statement ) {
			return null;
		}
		$value = $statement->fetchColumn();

		return false === $value ? null : $value;
	}

	public function get_charset_collate(): string {
		return '';
	}

	/**
	 * Direct row count helper for assertions.
	 */
	public function count_rows( string $table ): int {
		return (int) $this->pdo->query( 'SELECT COUNT(*) FROM ' . $table )->fetchColumn();
	}
}
// phpcs:enable
