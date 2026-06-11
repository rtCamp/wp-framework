<?php
/**
 * Ability: wp-framework/compare-requests.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Ability;

/**
 * Before/after metric deltas — proof a fix actually worked.
 */
final class CompareRequests extends AbstractAbility {
	/**
	 * Metrics compared between the two captures.
	 */
	private const METRIC_KEYS = [
		'total_time_ms',
		'query_count',
		'query_time_ms',
		'peak_memory_mb',
		'php_error_count',
		'http_call_count',
	];

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return self::CATEGORY . '/compare-requests';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Compare two captured requests' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Compares headline metrics between two captured requests (before/after a refactor). '
			. 'Returns per-metric deltas with percentage change. Use after wp-framework/profile-url '
			. 'to prove a fix actually worked.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'before_request_id' => [ 'type' => 'string' ],
				'after_request_id'  => [ 'type' => 'string' ],
			],
			'required'             => [ 'before_request_id', 'after_request_id' ],
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
				'before' => [ 'type' => 'object' ],
				'after'  => [ 'type' => 'object' ],
				'deltas' => [ 'type' => 'object' ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input.
	 */
	public function execute( mixed $input ): array|\WP_Error {
		$input  = (array) $input;
		$before = $this->requests()->find( (string) ( $input['before_request_id'] ?? '' ) );
		$after  = $this->requests()->find( (string) ( $input['after_request_id'] ?? '' ) );

		if ( null === $before || null === $after ) {
			return new \WP_Error(
				'rt_framework_telemetry_not_found',
				'One or both request ids were not found; ids come from wp-framework/list-requests.'
			);
		}

		$deltas = [];
		foreach ( self::METRIC_KEYS as $key ) {
			$before_value = $before[ $key ] ?? null;
			$after_value  = $after[ $key ] ?? null;
			if ( null === $before_value || null === $after_value ) {
				continue;
			}

			$delta          = round( $after_value - $before_value, 2 );
			$deltas[ $key ] = [
				'before'    => $before_value,
				'after'     => $after_value,
				'delta'     => $delta,
				'delta_pct' => $before_value > 0 ? round( ( $delta / $before_value ) * 100, 1 ) : null,
			];
		}

		return [
			'before' => $this->public_request_summary( $before ),
			'after'  => $this->public_request_summary( $after ),
			'deltas' => $deltas,
		];
	}
}
