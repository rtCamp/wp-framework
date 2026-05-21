<?php
/**
 * Concrete REST controller for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_REST_Controller;

class ConcreteRESTController extends Abstract_REST_Controller {

	protected $namespace = 'test/v1';

	public function register_routes(): void {
		// No-op for testing.
	}

	/**
	 * Expose namespace for testing.
	 */
	public function get_namespace_value(): string {
		return $this->namespace;
	}

	/**
	 * Expose version for testing.
	 */
	public function get_version_value(): string {
		return $this->version;
	}
}
