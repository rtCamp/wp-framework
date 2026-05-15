<?php
/**
 * Logger utility.
 *
 * @package RtCamp\WPToolkit\Utilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Utilities;

use RtCamp\WPToolkit\Traits\Singleton;

/**
 * PSR-3-style logger that writes to error_log() when WP_DEBUG is true.
 *
 * Format: [LEVEL] [prefix] message {"context":"json"}
 *
 * @since 1.0.0
 */
class Logger {

	use Singleton;

	/**
	 * Log prefix shown in every line. Override via set_prefix().
	 *
	 * @var string
	 */
	private string $prefix = 'rtcamp';

	/**
	 * Setup hook — called by consumer plugin during boot.
	 *
	 * @return void
	 */
	public function setup(): void {}

	/**
	 * Set the log prefix shown in every entry.
	 *
	 * @param string $prefix Prefix to display in log lines.
	 *
	 * @return void
	 */
	public function set_prefix( string $prefix ): void {
		$this->prefix = $prefix;
	}

	/**
	 * Generic log method.
	 *
	 * @param string               $level   PSR-3 level: debug, info, warning, error.
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional context data.
	 *
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		$context_part = empty( $context ) ? '' : ' ' . (string) wp_json_encode( $context );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logger IS the centralised wrapper around error_log(); callers route through this class instead of calling error_log() directly. The sniff's intent (no debug code in production) is enforced at call sites, not here.
		error_log(
			sprintf( '[%s] [%s] %s%s', strtoupper( $level ), $this->prefix, $message, $context_part )
		);
	}

	/**
	 * Log a debug-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional context data.
	 *
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log an info-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional context data.
	 *
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log a warning-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional context data.
	 *
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log an error-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional context data.
	 *
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}
}
