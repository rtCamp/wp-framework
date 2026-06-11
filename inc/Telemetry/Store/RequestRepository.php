<?php
/**
 * Persistence for captured requests.
 *
 * Direct $wpdb queries against the module's own local-dev telemetry
 * tables — never reachable in production (module is hard-gated to
 * local + RT_FRAMEWORK_DEV_MODE), hence the targeted phpcs ignores.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Store;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom local-dev telemetry tables, gated off production; no core API covers them.

/**
 * Stores normalized request captures and serves summaries back to abilities.
 */
final class RequestRepository {
	/**
	 * WordPress database access layer.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Database, defaults to the global $wpdb.
	 */
	public function __construct( ?\wpdb $db = null ) {
		if ( null === $db ) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
	}

	/**
	 * Persists a normalized capture. Returns the internal request id.
	 *
	 * The requests row is inserted before its request_data rows so a crash
	 * mid-save can only leave a metrics-only row, never invisible orphans.
	 *
	 * @param array<string, mixed> $normalized Output of Normalizer::normalize().
	 * @param int                  $retention  Ring-buffer size; older requests are pruned.
	 */
	public function save( array $normalized, int $retention = 200 ): int {
		$context = (array) ( $normalized['context'] ?? [] );
		$metrics = (array) ( $normalized['metrics'] ?? [] );

		$this->db->insert(
			$this->requests_table(),
			[
				'uuid'              => (string) ( $context['uuid'] ?? wp_generate_uuid4() ),
				'url'               => (string) ( $context['url'] ?? '' ),
				'method'            => (string) ( $context['method'] ?? 'GET' ),
				'type'              => (string) ( $context['type'] ?? 'frontend' ),
				'status'            => $context['status'] ?? null,
				'label'             => $context['label'] ?? null,
				'captured_at'       => self::to_datetime( (string) ( $context['captured_at'] ?? gmdate( 'c' ) ) ),
				'schema_version'    => (int) ( $normalized['schema_version'] ?? 0 ),
				'total_time_ms'     => $metrics['total_time_ms'] ?? null,
				'query_count'       => $metrics['query_count'] ?? null,
				'query_time_ms'     => $metrics['query_time_ms'] ?? null,
				'peak_memory_bytes' => $metrics['peak_memory_bytes'] ?? null,
				'php_error_count'   => $metrics['php_error_count'] ?? null,
				'http_call_count'   => $metrics['http_call_count'] ?? null,
			]
		);
		$request_id = (int) $this->db->insert_id;

		foreach ( (array) ( $normalized['collectors'] ?? [] ) as $collector => $payload ) {
			$encoded = wp_json_encode( $payload );
			if ( false === $encoded ) {
				// Never abort the save over one unencodable payload (e.g.
				// invalid UTF-8 in SQL literals) — the metrics row is still useful.
				$encoded = '{"_encode_error":true}';
			}

			$this->db->insert(
				$this->request_data_table(),
				[
					'request_id' => $request_id,
					'collector'  => (string) $collector,
					'payload'    => $encoded,
				]
			);
		}

		$this->prune( $retention );

		return $request_id;
	}

	/**
	 * Drops requests beyond the retention window, from both tables.
	 *
	 * No foreign keys exist (dbDelta cannot create them), so both tables
	 * are deleted explicitly inside a transaction. On engines where the
	 * transaction silently no-ops, requests are deleted FIRST so a crash
	 * can only orphan request_data rows — invisible to every read path
	 * (reads start from requests) and swept by the next prune.
	 *
	 * @param int $keep Number of newest requests to keep.
	 */
	public function prune( int $keep ): void {
		$keep = max( 1, $keep );

		$cutoff = $this->db->get_var(
			$this->db->prepare(
				"SELECT id FROM {$this->requests_table()} ORDER BY id DESC LIMIT 1 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
				$keep - 1
			)
		);
		if ( null === $cutoff ) {
			return;
		}

		$this->db->query( 'START TRANSACTION' );
		$this->db->query(
			$this->db->prepare( "DELETE FROM {$this->requests_table()} WHERE id < %d", (int) $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
		);
		$this->db->query(
			$this->db->prepare( "DELETE FROM {$this->request_data_table()} WHERE request_id < %d", (int) $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
		);
		$this->db->query( 'COMMIT' );
	}

	/**
	 * Lists request summaries, newest first.
	 *
	 * @param array<string, mixed> $args {url, type, label, since, limit}.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list( array $args = [] ): array {
		$where  = [];
		$params = [];

		if ( ! empty( $args['url'] ) ) {
			$where[]  = 'url = %s';
			$params[] = (string) $args['url'];
		}
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = (string) $args['type'];
		}
		if ( ! empty( $args['label'] ) ) {
			$where[]  = 'label = %s';
			$params[] = (string) $args['label'];
		}
		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'captured_at >= %s';
			$params[] = self::to_datetime( (string) $args['since'] );
		}

		$sql = "SELECT * FROM {$this->requests_table()}";
		if ( [] !== $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql     .= ' ORDER BY id DESC LIMIT %d';
		$params[] = min( 50, max( 1, (int) ( $args['limit'] ?? 10 ) ) );

		$rows = $this->db->get_results(
			$this->db->prepare( $sql, $params ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built above from %s/%d placeholders only.
			ARRAY_A
		);

		return array_map( [ $this, 'summarize' ], (array) $rows );
	}

	/**
	 * Finds a request by public uuid. Includes decoded collector payloads.
	 *
	 * @param string $uuid Public request id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( string $uuid ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->requests_table()} WHERE uuid = %s", $uuid ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->with_collectors( $row );
	}

	/**
	 * Latest captured request, optionally filtered by URL and type.
	 *
	 * @param string|null $url  Exact URL filter.
	 * @param string|null $type Request type filter, e.g. 'frontend'.
	 *
	 * @return array<string, mixed>|null
	 */
	public function latest( ?string $url = null, ?string $type = 'frontend' ): ?array {
		$rows = $this->list(
			array_filter(
				[
					'url'   => $url,
					'type'  => $type,
					'limit' => 1,
				]
			)
		);
		if ( [] === $rows ) {
			return null;
		}

		return $this->find( (string) $rows[0]['id'] );
	}

	/**
	 * All requests captured under a label (profile batches).
	 *
	 * @param string $label Label, e.g. "profile:<uuid>".
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function by_label( string $label ): array {
		return $this->list(
			[
				'label' => $label,
				'limit' => 50,
			]
		);
	}

	/**
	 * Internal numeric id for a public uuid.
	 *
	 * @param string $uuid Public request id.
	 */
	public function internal_id( string $uuid ): ?int {
		$id = $this->db->get_var(
			$this->db->prepare( "SELECT id FROM {$this->requests_table()} WHERE uuid = %s", $uuid ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
		);

		return null === $id ? null : (int) $id;
	}

	/**
	 * Maps a DB row to the public summary shape (uuid exposed as id).
	 *
	 * @param array<string, mixed> $row Raw row.
	 *
	 * @return array<string, mixed>
	 */
	public function summarize( array $row ): array {
		return [
			'id'              => $row['uuid'],
			'url'             => $row['url'],
			'method'          => $row['method'],
			'type'            => $row['type'],
			'status'          => null !== $row['status'] ? (int) $row['status'] : null,
			'label'           => $row['label'],
			'captured_at'     => self::to_iso8601( (string) $row['captured_at'] ),
			'total_time_ms'   => null !== $row['total_time_ms'] ? (float) $row['total_time_ms'] : null,
			'query_count'     => null !== $row['query_count'] ? (int) $row['query_count'] : null,
			'query_time_ms'   => null !== $row['query_time_ms'] ? (float) $row['query_time_ms'] : null,
			'peak_memory_mb'  => null !== $row['peak_memory_bytes'] ? round( (float) $row['peak_memory_bytes'] / 1048576, 1 ) : null,
			'php_error_count' => null !== $row['php_error_count'] ? (int) $row['php_error_count'] : null,
			'http_call_count' => null !== $row['http_call_count'] ? (int) $row['http_call_count'] : null,
		];
	}

	/**
	 * Attaches decoded collector payloads and the internal id to a row.
	 *
	 * @param array<string, mixed> $row Raw request row.
	 *
	 * @return array<string, mixed>
	 */
	private function with_collectors( array $row ): array {
		$data_rows = $this->db->get_results(
			$this->db->prepare( "SELECT collector, payload FROM {$this->request_data_table()} WHERE request_id = %d", (int) $row['id'] ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
			ARRAY_A
		);

		$collectors = [];
		foreach ( (array) $data_rows as $data ) {
			$collectors[ (string) $data['collector'] ] = json_decode( (string) $data['payload'], true );
		}

		$summary                 = $this->summarize( $row );
		$summary['_internal_id'] = (int) $row['id'];
		$summary['collectors']   = $collectors;

		return $summary;
	}

	/**
	 * Prefixed requests table name.
	 */
	private function requests_table(): string {
		return $this->db->prefix . Schema::REQUESTS;
	}

	/**
	 * Prefixed request_data table name.
	 */
	private function request_data_table(): string {
		return $this->db->prefix . Schema::REQUEST_DATA;
	}

	/**
	 * ISO 8601 (API boundary) to UTC MySQL datetime (storage).
	 *
	 * @param string $value Timestamp in any strtotime-parsable form.
	 */
	private static function to_datetime( string $value ): string {
		$timestamp = strtotime( $value );

		return gmdate( 'Y-m-d H:i:s', false === $timestamp ? time() : $timestamp );
	}

	/**
	 * UTC MySQL datetime (storage) to ISO 8601 (API boundary).
	 *
	 * @param string $value Stored datetime.
	 */
	private static function to_iso8601( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );

		return gmdate( 'c', false === $timestamp ? time() : $timestamp );
	}
}
