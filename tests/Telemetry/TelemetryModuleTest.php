<?php
/**
 * TelemetryModule tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPFramework\Telemetry\TelemetryModule;

final class TelemetryModuleTest extends TestCase {

	protected function tearDown(): void {
		unset(
			$GLOBALS['wp_framework_test_environment_type'],
			$GLOBALS['wp_framework_test_filters']['rt_framework_telemetry_enabled'],
			$GLOBALS['wp_framework_test_filters']['user_has_cap'],
			$GLOBALS['wp_framework_test_filters']['shutdown']
		);

		parent::tearDown();
	}

	public function test_is_conditionally_registrable(): void {
		$this->assertInstanceOf( ConditionallyRegistrable::class, new TelemetryModule() );
	}

	public function test_cannot_register_outside_local_environment(): void {
		$GLOBALS['wp_framework_test_environment_type'] = 'production';

		$this->assertFalse( ( new TelemetryModule() )->can_register() );
	}

	public function test_can_register_on_gated_local(): void {
		$GLOBALS['wp_framework_test_environment_type'] = 'local';

		$this->assertTrue( ( new TelemetryModule() )->can_register() );
	}

	public function test_register_hooks_loads_capture_service(): void {
		$GLOBALS['wp_framework_test_environment_type'] = 'local';

		( new TelemetryModule() )->register_hooks();

		$this->assertNotEmpty( $GLOBALS['wp_framework_test_filters']['user_has_cap'] ?? [] );
		$this->assertNotEmpty( $GLOBALS['wp_framework_test_filters']['shutdown'] ?? [] );
	}
}
