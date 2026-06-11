<?php
/**
 * Facts about the request currently being captured.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Capture;

use rtCamp\WPFramework\Telemetry\Config;

/**
 * Read-only view of the current request used by the capture pipeline.
 */
final class RequestContext {
	/**
	 * Memoized public id for this request.
	 *
	 * @var string|null
	 */
	private ?string $uuid = null;

	/**
	 * Public id of this captured request.
	 */
	public function uuid(): string {
		if ( null === $this->uuid ) {
			$this->uuid = wp_generate_uuid4();
		}

		return $this->uuid;
	}

	/**
	 * Full request URL as the client addressed it.
	 */
	public function url(): string {
		$host = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uri  = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		return ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri;
	}

	/**
	 * HTTP method.
	 */
	public function method(): string {
		return isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';
	}

	/**
	 * Request type bucket: cron, ajax, rest, admin or frontend.
	 */
	public function type(): string {
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( is_admin() ) {
			return 'admin';
		}

		return 'frontend';
	}

	/**
	 * Response status code (best effort at shutdown).
	 */
	public function status(): ?int {
		$code = http_response_code();

		return is_int( $code ) ? $code : null;
	}

	/**
	 * Batch id when this request was triggered by the profile-url ability.
	 */
	public function profile_batch(): ?string {
		if ( empty( $_SERVER[ Config::SERVER_PROFILE ] ) ) {
			return null;
		}

		return sanitize_text_field( (string) wp_unslash( $_SERVER[ Config::SERVER_PROFILE ] ) );
	}

	/**
	 * Label stored with the capture (used to find profile batches later).
	 */
	public function label(): ?string {
		$batch = $this->profile_batch();

		return null !== $batch ? 'profile:' . $batch : null;
	}

	/**
	 * Whether this request asked not to be captured (internal sub-requests).
	 */
	public function should_ignore(): bool {
		return ! empty( $_SERVER[ Config::SERVER_IGNORE ] );
	}
}
