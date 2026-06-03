<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the composer autoloader and defines minimal no-op stubs for the
 * handful of WordPress functions the framework calls. Tests that need
 * to assert against WP integration behavior should use Brain Monkey or
 * the WP test suite — these stubs only exist so unit tests of pure-PHP
 * logic don't crash on `function_exists` checks.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

require_once __DIR__ . '/../vendor/autoload.php';

// A deterministic 32-byte key so Encryptor tests are reproducible.
if ( ! defined( 'RT_FRAMEWORK_ENCRYPTION_KEY' ) ) {
	define( 'RT_FRAMEWORK_ENCRYPTION_KEY', str_repeat( 'k', 32 ) );
}

// --- WordPress function stubs ------------------------------------------------
// Only no-op stubs sufficient for the framework's runtime calls. No assertions
// hang off these — tests that need to verify hook side-effects use their own
// fixtures (see tests/Fixtures/LoaderFixtures.php).

if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( string $function_name, string $message, string $version ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_doing_it_wrong']   ??= [];
		$GLOBALS['wp_framework_test_doing_it_wrong'][] = [
			'function_name' => $function_name,
			'message'       => $message,
			'version'       => $version,
		];
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( string $text, string $context, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore
		return true;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( string $tag, $callback ): void {} // phpcs:ignore
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( array $pairs, array $atts, string $shortcode = '' ): array {
		return array_merge( $pairs, $atts );
	}
}

if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( string $post_type, array $args = [] ): array {
		return [ 'post_type' => $post_type, 'args' => $args ];
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	function register_taxonomy( string $taxonomy, $object_type, array $args = [] ): array {
		return [ 'taxonomy' => $taxonomy, 'object_type' => $object_type, 'args' => $args ];
	}
}

if ( ! function_exists( 'register_taxonomy_for_object_type' ) ) {
	function register_taxonomy_for_object_type( string $taxonomy, string $object_type ): bool {
		return true;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( string $handle, string $src, array $deps = [], string|bool|null $ver = false, string $media = 'all' ): bool {
		if ( isset( $GLOBALS['wp_framework_test_registered_styles'][ $handle ] ) ) {
			return false;
		}

		$GLOBALS['wp_framework_test_registered_styles'][ $handle ] = compact( 'src', 'deps', 'ver', 'media' );

		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( string $handle, string $src, array $deps = [], string|bool|null $ver = false, bool $in_footer = false ): bool {
		if ( isset( $GLOBALS['wp_framework_test_registered_scripts'][ $handle ] ) ) {
			return false;
		}

		$GLOBALS['wp_framework_test_registered_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );

		return true;
	}
}

if ( ! function_exists( 'wp_register_script_module' ) ) {
	function wp_register_script_module( string $handle, string $src, array $deps = [], string|bool|null $ver = false ): void {
		$GLOBALS['wp_framework_test_registered_modules'][ $handle ] = compact( 'src', 'deps', 'ver' );
	}
}

if ( ! function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
	function wp_register_block_types_from_metadata_collection( string $path, string $manifest ): void {
		$GLOBALS['wp_framework_test_registered_block_collections']   ??= [];
		$GLOBALS['wp_framework_test_registered_block_collections'][] = compact( 'path', 'manifest' );
	}
}

// Load fixtures that contain multiple classes per file (PSR-4 only autoloads
// single-class files matching the class name).
require_once __DIR__ . '/Fixtures/LoaderFixtures.php';
