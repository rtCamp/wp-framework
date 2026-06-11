<?php
/**
 * Ability: wp-framework/list-requests.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Ability;

/**
 * Tier 1 of progressive disclosure: tiny request summaries only.
 */
final class ListRequests extends AbstractAbility {
	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return self::CATEGORY . '/list-requests';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'List captured requests' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Lists recently captured WordPress requests with headline performance metrics '
			. '(wall time, query count, query time, PHP error count). Use this first to pick a '
			. 'request id, then call wp-framework/get-telemetry for its full telemetry.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'url'   => [
					'type'        => 'string',
					'description' => 'Filter to captures of this exact URL.',
				],
				'type'  => [
					'type' => 'string',
					'enum' => [ 'frontend', 'admin', 'rest', 'ajax', 'cron' ],
				],
				'limit' => [
					'type'    => 'integer',
					'default' => 10,
					'maximum' => 50,
				],
				'since' => [
					'type'        => 'string',
					'description' => 'ISO 8601 timestamp; only captures at or after this moment.',
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
				'requests' => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input.
	 *
	 * @return array<string, mixed>
	 */
	public function execute( mixed $input ): array {
		$input = (array) $input;

		return [
			'requests' => $this->requests()->list(
				[
					'url'   => $input['url'] ?? null,
					'type'  => $input['type'] ?? null,
					'since' => $input['since'] ?? null,
					'limit' => $input['limit'] ?? 10,
				]
			),
		];
	}
}
