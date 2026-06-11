<?php
/**
 * Ability: wp-framework/profile-url.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Ability;

use rtCamp\WPFramework\Telemetry\Config;
use rtCamp\WPFramework\Telemetry\Support\Stats;

/**
 * Captures fresh telemetry by issuing real HTTP loopback requests.
 *
 * Why loopback: WP-CLI never runs QM's collection pipeline, and the STDIO
 * MCP server IS a WP-CLI process — so it cannot profile in-process. Each
 * sample is a real front-end request whose shutdown capture stores a row
 * labeled with this batch's id. Multi-sample medians defeat cold-cache noise.
 */
final class ProfileUrl extends AbstractAbility {
	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return self::CATEGORY . '/profile-url';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Profile a URL' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Fetches a URL of this site several times to capture fresh telemetry, and returns '
			. 'the captured request ids plus median metrics. Use before and after a refactor, '
			. 'then wp-framework/compare-requests to prove the improvement. Only same-site URLs are allowed.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'url'     => [
					'type'        => 'string',
					'description' => 'Absolute URL on this site, e.g. the home page or a specific post.',
				],
				'samples' => [
					'type'    => 'integer',
					'default' => 3,
					'minimum' => 1,
					'maximum' => 5,
				],
			],
			'required'             => [ 'url' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'batch'       => [ 'type' => 'string' ],
				'request_ids' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'samples'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'medians'     => [ 'type' => 'object' ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input.
	 */
	public function execute( mixed $input ): array|\WP_Error {
		// Refuse to profile from within a profiled request (recursion guard).
		if ( ! empty( $_SERVER[ Config::SERVER_PROFILE ] ) ) {
			return new \WP_Error(
				'rt_framework_telemetry_recursion',
				'profile-url cannot run inside a request it is itself profiling.'
			);
		}

		$input = (array) $input;
		$url   = (string) ( $input['url'] ?? '' );

		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$url_parts = wp_parse_url( $url );
		if ( ! is_array( $url_parts ) || empty( $url_parts['host'] ) || strtolower( (string) $url_parts['host'] ) !== strtolower( $site_host ) ) {
			return new \WP_Error(
				'rt_framework_telemetry_invalid_url',
				sprintf( 'Only URLs on this site (%s) can be profiled.', $site_host )
			);
		}

		// Rewrite to the loopback base: inside Docker/wp-env containers the
		// public localhost:port address is unreachable, while the configured
		// loopback base works from every container. The original Host header
		// keeps WordPress routing/canonicals correct.
		$site_port = wp_parse_url( home_url(), PHP_URL_PORT );
		$host_head = $site_host . ( $site_port ? ':' . $site_port : '' );
		$target    = Config::loopback_base()
			. ( $url_parts['path'] ?? '/' )
			. ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );

		$batch   = wp_generate_uuid4();
		$count   = min( 5, max( 1, (int) ( $input['samples'] ?? 3 ) ) );
		$samples = [];

		for ( $i = 0; $i < $count; $i++ ) {
			// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get, WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- deliberate local loopback on a gated dev environment; slow pages are exactly what gets profiled, hence the generous timeout.
			$response = wp_remote_get(
				$target,
				[
					'timeout'     => 60,
					'redirection' => 2,
					'sslverify'   => false,
					'headers'     => [
						'Host'                 => $host_head,
						Config::HEADER_PROFILE => $batch,
					],
				]
			);
			// phpcs:enable

			if ( is_wp_error( $response ) ) {
				$samples[] = [ 'error' => $response->get_error_message() ];
			} else {
				$samples[] = [ 'status' => (int) wp_remote_retrieve_response_code( $response ) ];
			}
		}

		$captured = $this->requests()->by_label( 'profile:' . $batch );
		if ( [] === $captured ) {
			return new \WP_Error(
				'rt_framework_telemetry_capture_failed',
				'Requests were sent but nothing was captured. Is Query Monitor active, and does the '
				. 'loopback base resolve from this server (set RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE '
				. 'for Docker/wp-env)? Target was: ' . $target
			);
		}

		return [
			'batch'       => $batch,
			'request_ids' => array_column( $captured, 'id' ),
			'samples'     => $samples,
			'medians'     => [
				'total_time_ms' => Stats::median( array_column( $captured, 'total_time_ms' ) ),
				'query_count'   => Stats::median( array_column( $captured, 'query_count' ) ),
				'query_time_ms' => Stats::median( array_column( $captured, 'query_time_ms' ) ),
			],
		];
	}
}
