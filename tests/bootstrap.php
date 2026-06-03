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
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore
		$GLOBALS['wp_framework_test_filters'][ $hook ][ $priority ][] = [
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		];

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) { // phpcs:ignore
		if ( empty( $GLOBALS['wp_framework_test_filters'][ $hook ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['wp_framework_test_filters'][ $hook ] );

		foreach ( $GLOBALS['wp_framework_test_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = call_user_func_array(
					$callback['callback'],
					array_slice( array_merge( [ $value ], $args ), 0, $callback['accepted_args'] )
				);
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, ...$args ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_actions'][] = [
			'hook' => $hook,
			'args' => $args,
		];

		if ( empty( $GLOBALS['wp_framework_test_filters'][ $hook ] ) ) {
			return;
		}

		ksort( $GLOBALS['wp_framework_test_filters'][ $hook ] );

		foreach ( $GLOBALS['wp_framework_test_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				call_user_func_array(
					$callback['callback'],
					array_slice( $args, 0, $callback['accepted_args'] )
				);
			}
		}
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, array $defaults = [] ): array { // phpcs:ignore
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}

		if ( ! is_array( $args ) ) {
			parse_str( (string) $args, $args );
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, int $flags = 0, int $depth = 512 ): string|false { // phpcs:ignore
		return json_encode( $value, $flags, $depth );
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

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( array $template_names, bool $load = false, bool $load_once = true ): string { // phpcs:ignore
		$directories = array_filter(
			[
				$GLOBALS['wp_framework_test_stylesheet_directory'] ?? '',
				$GLOBALS['wp_framework_test_template_directory'] ?? '',
			],
			'is_string'
		);

		foreach ( $template_names as $template_name ) {
			foreach ( $directories as $directory ) {
				$file = trailingslashit( $directory ) . ltrim( $template_name, '/\\' );

				if ( is_readable( $file ) ) {
					return $file;
				}
			}
		}

		return '';
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory(): string {
		return (string) ( $GLOBALS['wp_framework_test_stylesheet_directory'] ?? '' );
	}
}

if ( ! function_exists( 'get_stylesheet_directory_uri' ) ) {
	function get_stylesheet_directory_uri(): string {
		return (string) ( $GLOBALS['wp_framework_test_stylesheet_directory_uri'] ?? '' );
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory(): string {
		return (string) ( $GLOBALS['wp_framework_test_template_directory'] ?? '' );
	}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
	function get_template_directory_uri(): string {
		return (string) ( $GLOBALS['wp_framework_test_template_directory_uri'] ?? '' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( string $handle, string $status = 'registered' ): bool {
		if ( 'enqueued' === $status ) {
			return in_array( $handle, $GLOBALS['wp_framework_test_enqueued_styles'] ?? [], true );
		}

		return isset( $GLOBALS['wp_framework_test_registered_styles'][ $handle ] );
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	function wp_script_is( string $handle, string $status = 'registered' ): bool {
		if ( 'enqueued' === $status ) {
			return in_array( $handle, $GLOBALS['wp_framework_test_enqueued_scripts'] ?? [], true );
		}

		return isset( $GLOBALS['wp_framework_test_registered_scripts'][ $handle ] );
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

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle ): void {
		$GLOBALS['wp_framework_test_enqueued_styles'][] = $handle;
	}
}

if ( ! function_exists( 'wp_dequeue_style' ) ) {
	function wp_dequeue_style( string $handle ): void {
		$GLOBALS['wp_framework_test_enqueued_styles'] = array_values(
			array_filter(
				$GLOBALS['wp_framework_test_enqueued_styles'] ?? [],
				static fn ( string $enqueued_handle ): bool => $handle !== $enqueued_handle
			)
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle ): void {
		$GLOBALS['wp_framework_test_enqueued_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'wp_dequeue_script' ) ) {
	function wp_dequeue_script( string $handle ): void {
		$GLOBALS['wp_framework_test_enqueued_scripts'] = array_values(
			array_filter(
				$GLOBALS['wp_framework_test_enqueued_scripts'] ?? [],
				static fn ( string $enqueued_handle ): bool => $handle !== $enqueued_handle
			)
		);
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

// Load fixtures that contain multiple classes per file (PSR-4 only autoloads
// single-class files matching the class name).
require_once __DIR__ . '/Fixtures/LoaderFixtures.php';
