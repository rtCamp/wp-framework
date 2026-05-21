<?php
/**
 * Concrete class using TemplateLoaderTrait for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\TemplateLoaderTrait;

class ConcreteTemplateLoader {
	use TemplateLoaderTrait;

	public function __construct( string $template_dir ) {
		$this->hook_prefix        = 'test_plugin';
		$this->template_theme_dir = 'test-plugin';
		$this->template_dir       = $template_dir;
	}

	/**
	 * Clear the static template location cache for testing.
	 */
	public static function clear_cache(): void {
		self::$template_location_cache = [];
	}
}
