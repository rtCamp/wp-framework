<?php
/**
 * Base class for Telemetry abilities.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Ability;

use rtCamp\WPFramework\Telemetry\Config;
use rtCamp\WPFramework\Telemetry\Path\PathTranslator;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;

/**
 * Class - AbstractAbility
 *
 * Each ability is registered with the WordPress Abilities API (Core 6.9)
 * and flagged meta.mcp.public so the official MCP Adapter's default
 * server exposes it to MCP clients with no further glue code.
 */
abstract class AbstractAbility {
	/**
	 * Ability category slug (also the ability name prefix).
	 */
	public const CATEGORY = 'wp-framework';

	/**
	 * Constructor.
	 *
	 * @param RequestRepository|null $requests Repository override for tests; built lazily otherwise.
	 * @param PathTranslator|null    $paths    Translator override for tests; built lazily otherwise.
	 */
	public function __construct(
		private ?RequestRepository $requests = null,
		private ?PathTranslator $paths = null,
	) {
	}

	/**
	 * Fully-qualified ability name, e.g. "wp-framework/list-requests".
	 */
	abstract public function name(): string;

	/**
	 * Human-readable label.
	 */
	abstract protected function label(): string;

	/**
	 * Description shown to MCP clients — written for an AI agent deciding
	 * which tool to call.
	 */
	abstract protected function description(): string;

	/**
	 * JSON Schema for the ability input.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function input_schema(): array;

	/**
	 * JSON Schema for the ability output.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function output_schema(): array;

	/**
	 * Executes the ability.
	 *
	 * @param mixed $input Validated input.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	abstract public function execute( mixed $input ): array|\WP_Error;

	/**
	 * Registration arguments for wp_register_ability().
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {
		return [
			'label'               => $this->label(),
			'description'         => $this->description(),
			'category'            => self::CATEGORY,
			'input_schema'        => $this->input_schema(),
			'output_schema'       => $this->output_schema(),
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => [
				'mcp' => [ 'public' => true ],
			],
		];
	}

	/**
	 * Lazily-built repository.
	 */
	protected function requests(): RequestRepository {
		$this->requests ??= new RequestRepository();

		return $this->requests;
	}

	/**
	 * Lazily-built path translator from the configured map.
	 */
	protected function paths(): PathTranslator {
		$this->paths ??= new PathTranslator( Config::path_map() );

		return $this->paths;
	}

	/**
	 * Strips internal fields from a full request record.
	 *
	 * @param array<string, mixed> $request Request with collectors attached.
	 *
	 * @return array<string, mixed>
	 */
	protected function public_request_summary( array $request ): array {
		unset( $request['_internal_id'], $request['collectors'] );

		return $request;
	}

	/**
	 * Adds host-translated file paths to normalized stack frames.
	 *
	 * @param array<int, array<string, mixed>> $frames Normalized frames.
	 * @param PathTranslator                   $paths  Translator.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function format_stack( array $frames, PathTranslator $paths ): array {
		$stack = [];
		foreach ( $frames as $frame ) {
			$file    = $frame['file'] ?? null;
			$stack[] = [
				'display'   => $frame['display'] ?? '',
				'file'      => $file,
				'host_file' => $file ? $paths->to_host( (string) $file ) : null,
				'line'      => $frame['line'] ?? null,
			];
		}

		return $stack;
	}
}
