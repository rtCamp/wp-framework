<?php
/**
 * Telemetry table schema, created via dbDelta.
 *
 * A Composer library has no activation hook, so the schema is ensured
 * lazily: callers invoke ensure() before touching the tables and an
 * autoloaded option short-circuits the steady state to one array lookup.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Store;

/**
 * Class - Schema
 */
final class Schema {
	/**
	 * Current schema version. Bump when statements() changes.
	 */
	public const VERSION = 1;

	/**
	 * Autoloaded option holding the installed schema version.
	 */
	public const OPTION = 'rt_framework_telemetry_schema';

	/**
	 * Unprefixed requests table name.
	 */
	public const REQUESTS = 'rt_framework_telemetry_requests';

	/**
	 * Unprefixed request_data table name.
	 */
	public const REQUEST_DATA = 'rt_framework_telemetry_request_data';

	/**
	 * Creates/updates the tables when the stored schema version is stale.
	 *
	 * Safe under concurrency: dbDelta is idempotent, so a race between
	 * two first requests is harmless.
	 */
	public static function ensure(): void {
		if ( self::VERSION === (int) get_option( self::OPTION, 0 ) ) {
			return;
		}

		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		foreach ( self::statements( $wpdb->prefix, $wpdb->get_charset_collate() ) as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * The dbDelta CREATE TABLE statements.
	 *
	 * Quirks of dbDelta honored here: exactly two spaces after PRIMARY KEY,
	 * every KEY named, lowercase types, and NO foreign keys (dbDelta
	 * mangles them silently — pruning deletes from both tables explicitly).
	 * Index widths stay within the 767-byte InnoDB COMPACT worst case for
	 * utf8mb4 (the "191 rule" WordPress core itself follows).
	 *
	 * @param string $prefix          Table prefix ($wpdb->prefix).
	 * @param string $charset_collate Charset/collation clause from $wpdb->get_charset_collate().
	 *
	 * @return string[]
	 */
	public static function statements( string $prefix, string $charset_collate ): array {
		$requests     = $prefix . self::REQUESTS;
		$request_data = $prefix . self::REQUEST_DATA;

		return [
			"CREATE TABLE {$requests} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				url varchar(2048) NOT NULL,
				method varchar(10) NOT NULL DEFAULT 'GET',
				type varchar(20) NOT NULL DEFAULT 'frontend',
				status smallint(5) unsigned DEFAULT NULL,
				label varchar(191) DEFAULT NULL,
				captured_at datetime NOT NULL,
				schema_version smallint(5) unsigned NOT NULL DEFAULT 1,
				total_time_ms double DEFAULT NULL,
				query_count int(10) unsigned DEFAULT NULL,
				query_time_ms double DEFAULT NULL,
				peak_memory_bytes bigint(20) unsigned DEFAULT NULL,
				php_error_count int(10) unsigned DEFAULT NULL,
				http_call_count int(10) unsigned DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				KEY url (url(191)),
				KEY label (label),
				KEY type_captured (type,captured_at)
			) {$charset_collate};",
			"CREATE TABLE {$request_data} (
				request_id bigint(20) unsigned NOT NULL,
				collector varchar(64) NOT NULL,
				payload longtext NOT NULL,
				PRIMARY KEY  (request_id,collector)
			) {$charset_collate};",
		];
	}
}
