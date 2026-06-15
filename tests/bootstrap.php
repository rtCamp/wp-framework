<?php
/**
 * PHPUnit bootstrap.
 *
 * Boots a real WordPress test environment (provided by wp-env) so the framework
 * is exercised against actual WordPress APIs rather than hand-written stubs.
 * The framework is a library — there is no plugin or theme to activate; tests
 * instantiate its classes directly once WordPress is loaded.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

define( 'TESTS_FRAMEWORK_DIR', dirname( __DIR__ ) );

// Load Composer dependencies (the framework's PSR-4 classes + dev tooling).
if ( file_exists( TESTS_FRAMEWORK_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_FRAMEWORK_DIR . '/vendor/autoload.php';
}

// When run in the wp-env context, point at the test config it provides.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && false !== getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) );
}

/*
 * Locate the WordPress `tests/phpunit/` directory, in order of preference:
 *  - WP_TESTS_DIR        env var (a WordPress clone's tests/phpunit).
 *  - WP_DEVELOP_DIR      env var (a WordPress clone root) + /tests/phpunit.
 *  - WP_PHPUNIT__DIR     env var (the wp-phpunit composer package).
 *  - wp-env fallback path relative to this package.
 *  - /tmp/wordpress-tests-lib (WP-CLI scaffold default).
 */
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_PHPUNIT__DIR' ) ) {
	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
} elseif ( file_exists( TESTS_FRAMEWORK_DIR . '/../../../../tests/phpunit/includes/functions.php' ) ) {
	$_test_root = TESTS_FRAMEWORK_DIR . '/../../../../tests/phpunit';
} else { // Fallback.
	$_test_root = '/tmp/wordpress-tests-lib';
}

// Fail with an actionable message instead of a raw "failed to open stream" if
// none of the resolution paths above found the WordPress test suite (e.g. when
// phpunit is run directly without the wp-env environment up).
if ( ! file_exists( $_test_root . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"WordPress test suite not found at {$_test_root}.\n" .
		"Start the environment with `npm run wp-env start`, or set WP_TESTS_DIR to a WordPress tests/phpunit directory.\n"
	);
	exit( 1 );
}

require_once $_test_root . '/includes/functions.php';

// --- WordPress object-cache stubs -------------------------------------------
// Functional in-memory implementations so Cache unit tests can exercise the
// full SWR flow without a real WordPress install. wp_cache_add() preserves
// atomicity (returns false if the key already exists) so the lock acquire /
// release logic is exercised correctly. wp_cache_get() supports the $found
// out-parameter (array_key_exists, not isset) so stored falsy values are
// distinguishable from misses — matching WP_Object_Cache behaviour.

$GLOBALS['_wp_cache'] = [];

if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed { // phpcs:ignore
		$found = isset( $GLOBALS['_wp_cache'][ $group ] ) && array_key_exists( $key, $GLOBALS['_wp_cache'][ $group ] );
		return $found ? $GLOBALS['_wp_cache'][ $group ][ $key ] : false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( string $key, mixed $value, string $group = '', int $expiration = 0 ): bool { // phpcs:ignore
		$GLOBALS['_wp_cache'][ $group ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( string $key, string $group = '' ): bool { // phpcs:ignore
		unset( $GLOBALS['_wp_cache'][ $group ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_cache_add' ) ) {
	function wp_cache_add( string $key, mixed $data, string $group = '', int $expiration = 0 ): bool { // phpcs:ignore
		if ( isset( $GLOBALS['_wp_cache'][ $group ] ) && array_key_exists( $key, $GLOBALS['_wp_cache'][ $group ] ) ) {
			return false;
		}
		$GLOBALS['_wp_cache'][ $group ][ $key ] = $data;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_flush_group' ) ) {
	function wp_cache_flush_group( string $group ): bool { // phpcs:ignore
		unset( $GLOBALS['_wp_cache'][ $group ] );
		return true;
	}
}

if ( ! function_exists( 'wp_cache_supports' ) ) {
	function wp_cache_supports( string $feature ): bool { // phpcs:ignore
		// Group flushing support is toggleable so tests can exercise both the
		// supported and unsupported branches of Cache::flush_group().
		if ( 'flush_group' === $feature ) {
			return $GLOBALS['_wp_cache_supports_flush_group'] ?? true;
		}
		return false;
	}
}

// Load fixtures that contain multiple classes per file (PSR-4 only autoloads
// single-class files matching the class name).
require_once __DIR__ . '/Fixtures/LoaderFixtures.php';

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
