<?php
/**
 * PHPUnit bootstrap — wp-php-toolkit.
 *
 * Boots the WordPress test suite so utilities that call WP functions
 * are tested against real WordPress.
 *
 * First-time setup:
 *
 *     bin/install-wp-tests.sh wordpress_test root '' localhost latest
 *
 * @package RtCamp\WPToolkit\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure Yoast's PHPUnit polyfills are loaded — these keep tests portable
// between PHPUnit 9, 10, 11, and 12 assertion signatures.
require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
