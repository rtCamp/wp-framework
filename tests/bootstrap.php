<?php
/**
 * PHPUnit bootstrap — wp-framework.
 *
 * Loads the Composer autoloader and stubs WordPress functions so unit tests
 * can run without a full WordPress environment.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure Yoast's PHPUnit polyfills are loaded — these keep tests portable
// between PHPUnit 9, 10, 11, and 12 assertion signatures.
require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Load WordPress function stubs for unit testing without WP runtime.
require_once __DIR__ . '/stubs/wordpress-stubs.php';
