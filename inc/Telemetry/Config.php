<?php
/**
 * Telemetry runtime configuration.
 *
 * Central place for the dev-mode gate, wp-config constants, defaults and
 * filterable settings used by the Telemetry module.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry;

/**
 * Class - Config
 */
final class Config {
	/**
	 * HTTP header that tags loopback requests issued by the profile-url
	 * ability. Its presence labels the capture and guards against recursion.
	 */
	public const HEADER_PROFILE = 'X-WP-Framework-Profile';

	/**
	 * HTTP header that suppresses capture of a request entirely.
	 */
	public const HEADER_IGNORE = 'X-WP-Framework-Ignore';

	/**
	 * $_SERVER key for the profile header.
	 */
	public const SERVER_PROFILE = 'HTTP_X_WP_FRAMEWORK_PROFILE';

	/**
	 * $_SERVER key for the ignore header.
	 */
	public const SERVER_IGNORE = 'HTTP_X_WP_FRAMEWORK_IGNORE';

	/**
	 * Whether the Telemetry module may run at all.
	 *
	 * Hard-gated: requires the RT_FRAMEWORK_DEV_MODE constant AND a 'local'
	 * environment type. The filter is consulted only after both hard gates
	 * pass, so it can disable the module but never force-enable it on a
	 * non-local environment.
	 */
	public static function is_enabled(): bool {
		if ( ! defined( 'RT_FRAMEWORK_DEV_MODE' ) || ! RT_FRAMEWORK_DEV_MODE ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_environment_type' ) || 'local' !== wp_get_environment_type() ) {
			return false;
		}

		/**
		 * Filters whether the Telemetry module is enabled.
		 *
		 * Runs only after the dev-mode constant and local-environment gates
		 * pass — it can only disable, never enable.
		 *
		 * @param bool $enabled Whether telemetry is enabled. Default true.
		 */
		return (bool) apply_filters( 'rt_framework_telemetry_enabled', true );
	}

	/**
	 * Number of captured requests to retain (ring buffer).
	 *
	 * Sourced from RT_FRAMEWORK_TELEMETRY_RETENTION when defined, then
	 * filtered. Never below 10.
	 */
	public static function retention(): int {
		$retention = 200;
		if ( defined( 'RT_FRAMEWORK_TELEMETRY_RETENTION' ) ) {
			$retention = (int) RT_FRAMEWORK_TELEMETRY_RETENTION;
		}

		/**
		 * Filters how many captured requests are kept in the telemetry store.
		 *
		 * @param int $retention Number of requests to keep. Default 200.
		 */
		return max( 10, (int) apply_filters( 'rt_framework_telemetry_retention', $retention ) );
	}

	/**
	 * Container-path => host-path prefix map used to translate backtrace
	 * file paths into paths an MCP client (Claude Code / Opencode) can open.
	 *
	 * @return array<string, string>
	 */
	public static function path_map(): array {
		$map = [];
		if ( defined( 'RT_FRAMEWORK_TELEMETRY_CONTAINER_ROOT' ) && defined( 'RT_FRAMEWORK_TELEMETRY_HOST_ROOT' ) ) {
			$map[ (string) RT_FRAMEWORK_TELEMETRY_CONTAINER_ROOT ] = (string) RT_FRAMEWORK_TELEMETRY_HOST_ROOT;
		}

		/**
		 * Filters the container => host path prefix map. Add entries when
		 * profiling additional locally-mounted plugins or themes.
		 *
		 * @param array<string, string> $map Container path prefix => host path prefix.
		 */
		return (array) apply_filters( 'rt_framework_telemetry_path_map', $map );
	}

	/**
	 * Base URL used for internal loopback requests (profile-url ability).
	 *
	 * Inside Docker/wp-env containers the site's public localhost:port
	 * address is unreachable; set RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE to a
	 * base reachable from within the container (e.g. the docker-compose
	 * service name). Defaults to home_url() for bare-metal local setups.
	 */
	public static function loopback_base(): string {
		if ( defined( 'RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE' ) && RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE ) {
			return untrailingslashit( (string) RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE );
		}

		return untrailingslashit( home_url() );
	}
}
