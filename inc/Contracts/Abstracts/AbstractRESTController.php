<?php
/**
 * Abstract REST controller class.
 *
 * Includes the shared namespace, version and hook registration.
 *
 * @package rtCamp\WPFramework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Abstracts;

use WP_REST_Controller;
use rtCamp\WPFramework\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractRESTController
 */
abstract class AbstractRESTController extends WP_REST_Controller implements Registrable {
	/**
	 * Route namespace for the REST API routes.
	 *
	 * Usually at /wp-json/{namespace}/{route}
	 * Override in child class with your plugin/theme slug.
	 *
	 * @var string
	 */
	protected $namespace = '';

	/**
	 * Version number for the REST API routes.
	 *
	 * @var string
	 */
	protected string $version = '1';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * We throw an exception here to force the child class to implement this method.
	 *
	 * @throws \Exception If method not implemented.
	 *
	 * @codeCoverageIgnore
	 */
	public function register_routes(): void {
		throw new \Exception( __FUNCTION__ . ' Method not implemented.' );
	}
}
