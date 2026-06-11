<?php
/**
 * Container-to-host path translation.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Path;

/**
 * Backtraces report container paths (/var/www/html/...). MCP clients run on
 * the host and need host paths. Translation uses longest-prefix matching;
 * unmappable paths (e.g. WP core under wp-env's hidden install dir) return
 * null — never a fabricated path.
 */
final class PathTranslator {
	/**
	 * Container prefix => host prefix, sorted longest-prefix-first.
	 *
	 * @var array<string, string>
	 */
	private array $map;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $map Container path prefix => host path prefix.
	 */
	public function __construct( array $map ) {
		uksort(
			$map,
			static function ( string $a, string $b ): int {
				return strlen( $b ) <=> strlen( $a );
			}
		);
		$this->map = $map;
	}

	/**
	 * Translates a container path to a host path, or null when unmappable.
	 *
	 * @param string|null $container_path Absolute path inside the container.
	 */
	public function to_host( ?string $container_path ): ?string {
		if ( null === $container_path || '' === $container_path ) {
			return null;
		}

		foreach ( $this->map as $container_prefix => $host_prefix ) {
			$container_prefix = rtrim( $container_prefix, '/' );
			$host_prefix      = rtrim( $host_prefix, '/' );

			if ( $container_path === $container_prefix ) {
				return $host_prefix;
			}

			if ( str_starts_with( $container_path, $container_prefix . '/' ) ) {
				return $host_prefix . substr( $container_path, strlen( $container_prefix ) );
			}
		}

		return null;
	}
}
