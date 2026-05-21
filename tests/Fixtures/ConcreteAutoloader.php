<?php
/**
 * Concrete class using AutoloaderTrait for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\AutoloaderTrait;

class ConcreteAutoloader {
	use AutoloaderTrait;

	protected static function get_autoloader_error_message(): string {
		return 'Autoloader is missing. Please run composer install.';
	}

	/**
	 * Public wrapper for testing require_autoloader.
	 */
	public static function load( string $path ): bool {
		return self::require_autoloader( $path );
	}

	/**
	 * Public wrapper for testing get_autoloader_error_message.
	 */
	public static function get_error_message(): string {
		return self::get_autoloader_error_message();
	}
}
