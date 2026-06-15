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
		// Mirror core: a missing option's implicit old value is `false`, so
		// writing `false` to an option that does not exist yet is a no-op.
		$old_value = array_key_exists( $option, $GLOBALS['_wp_options'] )
			? $GLOBALS['_wp_options'][ $option ]
			: false;

		if ( $old_value === $value ) {
			return false;
		}

		$GLOBALS['_wp_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $option, mixed $value = '', string $deprecated = '', bool|string|null $autoload = null ): bool { // phpcs:ignore
		// Mirror core: adding an option that already exists fails; otherwise the
		// row is created verbatim (even when the value is `false`).
		if ( array_key_exists( $option, $GLOBALS['_wp_options'] ) ) {
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
