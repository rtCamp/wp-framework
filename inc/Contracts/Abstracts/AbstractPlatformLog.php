<?php
/**
 * Abstract Platform Log.
 *
 * @package rtCamp\WPFramework\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Abstracts;

/**
 * Class AbstractPlatformLog
 *
 * Reads recent host-level log entries — PHP errors, warnings, fatals — and
 * normalizes each one to a message, level, timestamp and, where the source
 * line permits it, a file and line number. The concrete subclass owns the
 * source: a local `debug.log`, a hosting provider's CLI bridge, a log API.
 *
 * Unlike most abstracts here this one is deliberately not `Registrable`: it
 * registers no WordPress hook. It is constructed and queried on demand by
 * whatever consumes it.
 *
 * @since 1.0.0
 */
abstract class AbstractPlatformLog {

	/**
	 * Return the most recent log entries, newest first.
	 *
	 * Implementations should return an empty array rather than throw when the
	 * source is unreadable, even if the caller skipped {@see is_available()}.
	 *
	 * @param int $limit Maximum number of entries to return.
	 *
	 * @return array<int, array{timestamp: ?string, level: string, message: string, file: ?string, line: ?int, raw: string}> Entries, newest first.
	 */
	abstract public function get_recent_entries( int $limit = 100 ): array;

	/**
	 * Whether this log source can be read right now.
	 *
	 * Defaults to true. Override to probe whatever the source depends on — a
	 * readable file, CLI credentials, a reachable endpoint.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Parse one standard PHP error-log line into an entry.
	 *
	 * Handles both formats PHP emits — `[timestamp] PHP Level:  message in
	 * FILE:LINE` and `... in FILE on line LINE` — and returns null for a line
	 * that is neither (a stack-trace frame, a blank line), so callers can
	 * `array_filter()` the result of mapping over a file.
	 *
	 * This is generic PHP log formatting rather than anything host-specific,
	 * so every file-backed subclass gets it for free.
	 *
	 * @param string $line One raw log line, without its trailing newline.
	 *
	 * @return array{timestamp: ?string, level: string, message: string, file: ?string, line: ?int, raw: string}|null Parsed entry, or null when the line is not a log entry.
	 */
	protected function parse_error_log_line( string $line ): ?array {
		$rest      = trim( $line );
		$timestamp = null;
		$level     = 'unknown';

		if ( preg_match( '/^\[([^\]]+)\]\s*(.*)$/', $rest, $matches ) ) {
			$timestamp = $matches[1];
			$rest      = $matches[2];
		}

		if ( preg_match( '/^PHP\s+([A-Za-z][A-Za-z ]*?)\s*:\s*(.*)$/', $rest, $matches ) ) {
			$level = strtolower( $matches[1] );
			$rest  = $matches[2];
		}

		// Neither marker present — a stack-trace frame or unrelated output.
		if ( null === $timestamp && 'unknown' === $level ) {
			return null;
		}

		// Xdebug timestamps the trace it prints under a fatal, so those lines
		// survive the check above. They belong to the entry before them.
		if ( 'stack trace' === $level || preg_match( '/^(?:PHP\s+\d+\.|#\d+)\s/', $rest ) ) {
			return null;
		}

		$file        = null;
		$line_number = null;

		// Greedy leading group so the *last* " in " wins: a message may contain its own.
		if ( preg_match( '/^(.*) in (.+) on line (\d+)$/', $rest, $matches ) ) {
			$rest        = $matches[1];
			$file        = $matches[2];
			$line_number = (int) $matches[3];
		} elseif ( preg_match( '/^(.*) in (.+):(\d+)$/', $rest, $matches ) ) {
			$rest        = $matches[1];
			$file        = $matches[2];
			$line_number = (int) $matches[3];
		}

		return [
			'timestamp' => $timestamp,
			'level'     => $level,
			'message'   => trim( $rest ),
			'file'      => $file,
			'line'      => $line_number,
			'raw'       => $line,
		];
	}
}
