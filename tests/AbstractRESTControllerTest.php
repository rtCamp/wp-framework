<?php
/**
 * Tests for Abstract_REST_Controller.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Tests\Fixtures\ConcreteRESTController;

/**
 * @covers \WPFramework\Contracts\Abstracts\Abstract_REST_Controller
 */
class AbstractRESTControllerTest extends TestCase {

	protected function setUp(): void {
		global $wp_actions;
		$wp_actions = [];
	}

	public function test_register_hooks_adds_rest_api_init_action(): void {
		global $wp_actions;

		$controller = new ConcreteRESTController();
		$controller->register_hooks();

		$this->assertCount( 1, $wp_actions );
		$this->assertSame( 'rest_api_init', $wp_actions[0]['hook'] );
		$this->assertSame( [ $controller, 'register_routes' ], $wp_actions[0]['callback'] );
	}

	public function test_namespace_and_version_are_set(): void {
		$controller = new ConcreteRESTController();

		$this->assertSame( 'test/v1', $controller->get_namespace_value() );
		$this->assertSame( '1', $controller->get_version_value() );
	}
}
