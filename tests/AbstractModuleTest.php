<?php
/**
 * Tests for Abstract_Module.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteModule;
use WPFramework\Tests\Fixtures\ConcreteDisabledModule;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_Module
 */
class AbstractModuleTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions;
		$wp_actions = [];
	}

	public function test_register_hooks_instantiates_and_registers_classes(): void {
		global $wp_actions;

		$module = new ConcreteModule();
		$module->register_hooks();

		// Both classes should have had register_hooks() called (adding 'init' actions).
		$init_actions = array_filter( $wp_actions, fn( $a ) => $a['hook'] === 'init' );
		$this->assertCount( 2, $init_actions );
	}

	public function test_register_hooks_with_empty_classes_does_nothing(): void {
		global $wp_actions;

		$module = new ConcreteDisabledModule();
		$module->register_hooks();

		$this->assertEmpty( $wp_actions );
	}
}
