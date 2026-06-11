<?php
/**
 * Local-dev telemetry module: Query Monitor capture → MySQL store →
 * WordPress Abilities (auto-exposed to MCP clients by the MCP Adapter).
 *
 * Add this module to your skeleton's Loader class list; it is safe to
 * ship permanently because it no-ops everywhere except a 'local'
 * environment with RT_FRAMEWORK_DEV_MODE defined (see Config).
 *
 * Runtime companions (the developer's own plugin installs, never
 * Composer dependencies): Query Monitor supplies the telemetry; the
 * official MCP Adapter exposes the abilities over MCP.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry;

use rtCamp\WPFramework\Contracts\Abstracts\AbstractModule;
use rtCamp\WPFramework\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPFramework\Telemetry\Capture\CaptureService;

/**
 * Class - TelemetryModule
 */
final class TelemetryModule extends AbstractModule implements ConditionallyRegistrable {
	/**
	 * {@inheritDoc}
	 *
	 * Hard gate: dev-mode constant AND local environment (filter can
	 * further disable, never enable). See Config::is_enabled().
	 */
	public function can_register(): bool {
		return Config::is_enabled();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_classes(): array {
		return [
			CaptureService::class,
		];
	}
}
