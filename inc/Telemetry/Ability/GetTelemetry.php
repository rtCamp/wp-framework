<?php
/**
 * Ability: wp-framework/get-telemetry.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Ability;

use rtCamp\WPFramework\Telemetry\Path\PathTranslator;

/**
 * Returns full normalized telemetry with host-translated file paths —
 * the bridge that lets an MCP client open the exact offending source file.
 */
final class GetTelemetry extends AbstractAbility {
	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return self::CATEGORY . '/get-telemetry';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get raw request telemetry' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Returns the full normalized Query Monitor telemetry for a captured request: '
			. 'all DB queries with backtraces and host-translated file paths, HTTP calls, '
			. 'PHP errors, asset handles, and timing metrics. Use this for AI-driven '
			. 'performance analysis.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'request_id' => [
					'type'        => 'string',
					'description' => 'Captured request to inspect; defaults to the most recent capture.',
				],
				'url'        => [
					'type'        => 'string',
					'description' => 'Alternative: inspect the latest capture of this URL.',
				],
			],
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
				'request'   => [ 'type' => 'object' ],
				'telemetry' => [ 'type' => 'object' ],
				'truncated' => [ 'type' => 'boolean' ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input.
	 */
	public function execute( mixed $input ): array|\WP_Error {
		$input = (array) $input;

		if ( ! empty( $input['request_id'] ) ) {
			$request = $this->requests()->find( (string) $input['request_id'] );
		} elseif ( ! empty( $input['url'] ) ) {
			$request = $this->requests()->latest( (string) $input['url'], null );
		} else {
			$request = $this->requests()->latest();
		}

		if ( null === $request ) {
			return new \WP_Error(
				'rt_framework_telemetry_no_captures',
				'No captured requests match. Load a page (curl the site) or call wp-framework/profile-url first, then retry.'
			);
		}

		$paths      = $this->paths();
		$collectors = (array) ( $request['collectors'] ?? [] );

		return [
			'request'   => $this->public_request_summary( $request ),
			'telemetry' => [
				'db_queries' => $this->format_db_queries( (array) ( $collectors['db_queries'] ?? [] ), $paths ),
				'http'       => $this->format_http( (array) ( $collectors['http'] ?? [] ), $paths ),
				'php_errors' => $this->format_php_errors( (array) ( $collectors['php_errors'] ?? [] ), $paths ),
				'assets'     => $collectors['assets'] ?? [],
				'metrics'    => [
					'total_time_ms'   => $request['total_time_ms'] ?? null,
					'query_count'     => $request['query_count'] ?? null,
					'query_time_ms'   => $request['query_time_ms'] ?? null,
					'peak_memory_mb'  => $request['peak_memory_mb'] ?? null,
					'php_error_count' => $request['php_error_count'] ?? null,
					'http_call_count' => $request['http_call_count'] ?? null,
				],
			],
			'truncated' => false,
		];
	}

	/**
	 * Adds host paths to stored db_queries payloads.
	 *
	 * @param array<string, mixed> $db_queries Stored db_queries collector payload.
	 * @param PathTranslator       $paths      Translator.
	 *
	 * @return array<string, mixed>
	 */
	private function format_db_queries( array $db_queries, PathTranslator $paths ): array {
		$queries = [];
		foreach ( (array) ( $db_queries['queries'] ?? [] ) as $query ) {
			$file      = $query['file'] ?? null;
			$queries[] = [
				'sql'             => $query['sql'] ?? '',
				'sql_fingerprint' => $query['sql_fingerprint'] ?? null,
				'time_ms'         => $query['time_ms'] ?? null,
				'caller'          => $query['caller'] ?? null,
				'component'       => $query['component'] ?? null,
				'file'            => $file,
				'host_file'       => $file ? $paths->to_host( (string) $file ) : null,
				'line'            => $query['line'] ?? null,
				'stack'           => $this->format_stack( (array) ( $query['stack'] ?? [] ), $paths ),
			];
		}

		return [
			'count'         => $db_queries['count'] ?? count( $queries ),
			'total_time_ms' => $db_queries['total_time_ms'] ?? null,
			'queries'       => $queries,
		];
	}

	/**
	 * Adds host paths to stored php_errors payloads.
	 *
	 * @param array<string, mixed> $php_errors Stored php_errors collector payload.
	 * @param PathTranslator       $paths      Translator.
	 *
	 * @return array<string, mixed>
	 */
	private function format_php_errors( array $php_errors, PathTranslator $paths ): array {
		$errors = [];
		foreach ( (array) ( $php_errors['errors'] ?? [] ) as $error ) {
			$file = $error['file'] ?? null;

			$errors[] = array_merge(
				(array) $error,
				[
					'host_file' => $file ? $paths->to_host( (string) $file ) : null,
					'stack'     => $this->format_stack( (array) ( $error['stack'] ?? [] ), $paths ),
				]
			);
		}

		return [
			'count'  => $php_errors['count'] ?? count( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Adds host paths to stored http payloads.
	 *
	 * @param array<string, mixed> $http  Stored http collector payload.
	 * @param PathTranslator       $paths Translator.
	 *
	 * @return array<string, mixed>
	 */
	private function format_http( array $http, PathTranslator $paths ): array {
		$calls = [];
		foreach ( (array) ( $http['calls'] ?? [] ) as $call ) {
			$file    = $call['file'] ?? null;
			$calls[] = [
				'url'           => $call['url'] ?? '',
				'method'        => $call['method'] ?? 'GET',
				'time_ms'       => $call['time_ms'] ?? null,
				'response_code' => $call['response_code'] ?? null,
				'error'         => $call['error'] ?? null,
				'component'     => $call['component'] ?? null,
				'file'          => $file,
				'host_file'     => $file ? $paths->to_host( (string) $file ) : null,
				'line'          => $call['line'] ?? null,
				'stack'         => $this->format_stack( (array) ( $call['stack'] ?? [] ), $paths ),
			];
		}

		return [
			'count'         => $http['count'] ?? count( $calls ),
			'total_time_ms' => $http['total_time_ms'] ?? null,
			'calls'         => $calls,
		];
	}
}
