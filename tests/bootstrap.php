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

// Dev mode on so Telemetry gating is testable along its other axes
// (environment type + filter); the constant-off path is a one-line
// defined() check that cannot be toggled within a PHP process.
if ( ! defined( 'RT_FRAMEWORK_DEV_MODE' ) ) {
	define( 'RT_FRAMEWORK_DEV_MODE', true );
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
		$GLOBALS['wp_framework_test_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {
		foreach ( $GLOBALS['wp_framework_test_filters'][ $hook ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore
		$GLOBALS['wp_framework_test_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		foreach ( $GLOBALS['wp_framework_test_filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
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

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $filename ): string {
		return preg_replace( '/[^A-Za-z0-9_.\- ]/', '', $filename ) ?? '';
	}
}

if ( ! function_exists( 'load_template' ) ) {
	function load_template( string $template_file, bool $load_once = true, array $args = [] ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_loaded_templates']   ??= [];
		$GLOBALS['wp_framework_test_loaded_templates'][] = [
			'file' => $template_file,
			'args' => $args,
		];

		// Execute the template so its output can be captured, mirroring core's
		// load_template(); $args is available to the template.
		if ( is_file( $template_file ) ) {
			require $template_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable, WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath
		}
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( array $args, array $defaults = [] ): array {
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all' ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_enqueued_styles']   ??= [];
		$GLOBALS['wp_framework_test_enqueued_styles'][] = $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool $in_footer = false ): void { // phpcs:ignore
		$GLOBALS['wp_framework_test_enqueued_scripts']   ??= [];
		$GLOBALS['wp_framework_test_enqueued_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'wp_dequeue_style' ) ) {
	function wp_dequeue_style( string $handle ): void {
		$GLOBALS['wp_framework_test_enqueued_styles'] = array_values(
			array_filter(
				$GLOBALS['wp_framework_test_enqueued_styles'] ?? [],
				static fn ( string $enqueued ): bool => $handle !== $enqueued
			)
		);
	}
}

if ( ! function_exists( 'wp_dequeue_script' ) ) {
	function wp_dequeue_script( string $handle ): void {
		$GLOBALS['wp_framework_test_enqueued_scripts'] = array_values(
			array_filter(
				$GLOBALS['wp_framework_test_enqueued_scripts'] ?? [],
				static fn ( string $enqueued ): bool => $handle !== $enqueued
			)
		);
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

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type(): string {
		return (string) ( $GLOBALS['wp_framework_test_environment_type'] ?? 'production' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '', ?string $scheme = null ): string { // phpcs:ignore
		return ( $GLOBALS['wp_framework_test_home_url'] ?? 'https://example.test' ) . $path;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags_for_tests( $str ) ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags_for_tests' ) ) {
	function wp_strip_all_tags_for_tests( string $str ): string {
		return strip_tags( $str ); // phpcs:ignore
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ): mixed {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component ); // phpcs:ignore
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl(): bool {
		return (bool) ( $GLOBALS['wp_framework_test_is_ssl'] ?? false );
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron(): bool {
		return (bool) ( $GLOBALS['wp_framework_test_doing_cron'] ?? false );
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool {
		return (bool) ( $GLOBALS['wp_framework_test_doing_ajax'] ?? false );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return (bool) ( $GLOBALS['wp_framework_test_is_admin'] ?? false );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, mixed ...$args ): bool { // phpcs:ignore
		return (bool) ( $GLOBALS['wp_framework_test_user_can'][ $capability ] ?? false );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default_value = false ): mixed {
		return $GLOBALS['wp_framework_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value, bool|null $autoload = null ): bool { // phpcs:ignore
		$GLOBALS['wp_framework_test_options'][ $option ] = $value;

		return true;
	}
}

// Load fixtures that contain multiple classes per file (PSR-4 only autoloads
// single-class files matching the class name).
require_once __DIR__ . '/Fixtures/LoaderFixtures.php';
require_once __DIR__ . '/Fixtures/WpError.php';
require_once __DIR__ . '/Fixtures/WpdbStub.php';
require_once __DIR__ . '/Fixtures/QmCollectorsStub.php';

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = [] ): mixed {
		$GLOBALS['wp_framework_test_http_requests']   ??= [];
		$GLOBALS['wp_framework_test_http_requests'][] = [
			'url'  => $url,
			'args' => $args,
		];

		$handler = $GLOBALS['wp_framework_test_http_handler'] ?? null;
		if ( is_callable( $handler ) ) {
			return $handler( $url, $args );
		}

		return [ 'response' => [ 'code' => 200 ] ];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( mixed $response ): int|string {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return '';
	}
}

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( string $slug, array $args ): bool {
		$GLOBALS['wp_framework_test_ability_categories'][ $slug ] = $args;

		return true;
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( string $name, array $args ): ?object {
		$GLOBALS['wp_framework_test_abilities'][ $name ] = $args;

		return null;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( string $queries ): array { // phpcs:ignore
		$GLOBALS['wp_framework_test_dbdelta']   ??= [];
		$GLOBALS['wp_framework_test_dbdelta'][] = $queries;

		return [];
	}
}
