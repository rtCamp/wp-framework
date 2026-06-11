<?php
/**
 * Minimal WP_Error stand-in for unit tests (global namespace, like core).
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

// phpcs:disable -- test fixture mirroring a WordPress core class name.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		public function __construct(
			private string|int $code = '',
			private string $message = '',
			private mixed $data = null,
		) {
		}

		public function get_error_code(): string|int {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}
// phpcs:enable
