<?php
/**
 * AbilityRegistrar tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Ability;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Ability\AbilityRegistrar;
use rtCamp\WPFramework\Telemetry\Store\Schema;

final class AbilityRegistrarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Schema already installed — Schema::ensure() short-circuits on the option.
		$GLOBALS['wp_framework_test_options'][ Schema::OPTION ] = Schema::VERSION;
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['wp_framework_test_options'][ Schema::OPTION ],
			$GLOBALS['wp_framework_test_abilities'],
			$GLOBALS['wp_framework_test_ability_categories'],
			$GLOBALS['wp_framework_test_filters']['wp_abilities_api_categories_init'],
			$GLOBALS['wp_framework_test_filters']['wp_abilities_api_init']
		);

		parent::tearDown();
	}

	public function test_register_hooks_targets_the_abilities_api_hooks(): void {
		( new AbilityRegistrar() )->register_hooks();

		$this->assertNotEmpty( $GLOBALS['wp_framework_test_filters']['wp_abilities_api_categories_init'] ?? [] );
		$this->assertNotEmpty( $GLOBALS['wp_framework_test_filters']['wp_abilities_api_init'] ?? [] );
	}

	public function test_register_category_registers_wp_framework_slug(): void {
		( new AbilityRegistrar() )->register_category();

		$this->assertArrayHasKey( 'wp-framework', $GLOBALS['wp_framework_test_ability_categories'] ?? [] );
	}

	public function test_register_abilities_registers_every_ability(): void {
		( new AbilityRegistrar() )->register_abilities();

		$registered = array_keys( $GLOBALS['wp_framework_test_abilities'] ?? [] );

		$this->assertContains( 'wp-framework/list-requests', $registered );
		$this->assertContains( 'wp-framework/get-telemetry', $registered );
		$this->assertContains( 'wp-framework/compare-requests', $registered );
	}

	public function test_registered_abilities_are_mcp_public(): void {
		( new AbilityRegistrar() )->register_abilities();

		foreach ( $GLOBALS['wp_framework_test_abilities'] as $name => $args ) {
			$this->assertTrue( $args['meta']['mcp']['public'], $name . ' must be MCP-public' );
		}
	}
}
