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

// --- WordPress options stubs --------------------------------------------------
// Functional in-memory store backing get_option/update_option. Tests reset
// $GLOBALS['_wp_options'] in setUp()/tearDown().

$GLOBALS['_wp_options'] = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default_value = false ): mixed { // phpcs:ignore
		return array_key_exists( $option, $GLOBALS['_wp_options'] )
			? $GLOBALS['_wp_options'][ $option ]
			: $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value, bool|null $autoload = null ): bool { // phpcs:ignore
		// Mirror core: updating to the already-stored value returns false.
		if ( array_key_exists( $option, $GLOBALS['_wp_options'] )
			&& $GLOBALS['_wp_options'][ $option ] === $value ) {
			return false;
		}

		$GLOBALS['_wp_options'][ $option ] = $value;

		return true;
	}
}

// --- WordPress Settings API / admin stubs --------------------------------------
// Recording stubs (plus a functional do_settings_sections) sufficient to unit
// test settings pages. Tests reset the $GLOBALS stores in setUp()/tearDown().

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $option_group, string $option_name, array $args = [] ): void {
		$GLOBALS['wp_framework_test_registered_settings'][ $option_group ][ $option_name ] = $args;
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, $callback, string $page, array $args = [] ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_settings_sections'][ $page ][ $id ] = compact( 'title', 'callback', 'args' );
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, $callback, string $page, string $section = 'default', array $args = [] ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_settings_fields'][ $page ][ $section ][ $id ] = compact( 'title', 'callback', 'args' );
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( string $page ): void {
		foreach ( $GLOBALS['wp_framework_test_settings_sections'][ $page ] ?? [] as $section_id => $section ) {
			foreach ( $GLOBALS['wp_framework_test_settings_fields'][ $page ][ $section_id ] ?? [] as $field ) {
				( $field['callback'] )( $field['args'] );
			}
		}
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {
		$GLOBALS['wp_framework_test_settings_fields_calls']   ??= [];
		$GLOBALS['wp_framework_test_settings_fields_calls'][] = $option_group;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', int|float|null $position = null ): string { // phpcs:ignore
		$GLOBALS['wp_framework_test_admin_pages']   ??= [];
		$GLOBALS['wp_framework_test_admin_pages'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );

		return $menu_slug;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', string $icon_url = '', int|float|null $position = null ): string { // phpcs:ignore
		$GLOBALS['wp_framework_test_admin_pages']   ??= [];
		$GLOBALS['wp_framework_test_admin_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url' );

		return $menu_slug;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( ?string $text = null ): void {
		echo '<input type="submit" />';
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
		$result = (string) $checked === (string) $current ? " checked='checked'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'disabled' ) ) {
	function disabled( mixed $disabled, mixed $current = true, bool $display = true ): string {
		$result = (string) $disabled === (string) $current ? " disabled='disabled'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void { // phpcs:ignore
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, mixed ...$args ): bool { // phpcs:ignore
		return (bool) ( $GLOBALS['wp_framework_test_current_user_can'] ?? true );
	}
}

if ( ! function_exists( 'get_admin_page_title' ) ) {
	function get_admin_page_title(): string {
		return (string) ( $GLOBALS['wp_framework_test_admin_page_title'] ?? '' );
	}
}

// Load fixtures that contain multiple classes per file (PSR-4 only autoloads
// single-class files matching the class name).
require_once __DIR__ . '/Fixtures/LoaderFixtures.php';

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
