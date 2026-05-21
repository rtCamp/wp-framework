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
	 * Register the controller's REST routes.
	 *
	 * Subclasses must implement this — making it abstract here surfaces
	 * the missing implementation at load time rather than at request time.
	 */
	abstract public function register_routes(): void;
}
