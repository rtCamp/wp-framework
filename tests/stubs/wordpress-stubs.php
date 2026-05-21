<?php
/**
 * WordPress function stubs for unit testing.
 *
 * These stubs provide minimal implementations of WordPress functions
 * so the framework classes can be tested without a full WP environment.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

// Track hook calls for assertions.
global $wp_actions, $wp_filters, $wp_scripts, $wp_styles, $wp_scheduled_events, $wp_options, $wp_roles;
$wp_actions          = [];
$wp_filters          = [];
$wp_scripts          = [];
$wp_styles           = [];
$wp_scheduled_events = [];
$wp_options          = [];
$wp_roles            = [];

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub for add_action.
	 */
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $wp_actions;
		$wp_actions[] = [
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		];
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub for add_filter.
	 */
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $wp_filters;
		$wp_filters[] = [
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		];
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Stub for do_action — no-op.
	 */
	function do_action( string $hook_name, ...$args ): void {
		// No-op for tests.
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub for apply_filters — returns first arg unchanged.
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Stub for add_shortcode.
	 */
	function add_shortcode( string $tag, callable $callback ): void {
		global $wp_filters;
		$wp_filters[] = [
			'hook'     => "shortcode_$tag",
			'callback' => $callback,
		];
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Stub for shortcode_atts.
	 */
	function shortcode_atts( array $pairs, array $atts, string $shortcode = '' ): array {
		$out = [];
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	/**
	 * Stub for register_setting.
	 *
	 * @var array<string, array{group: string, args: array<string, mixed>}> $wp_registered_settings
	 */
	function register_setting( string $option_group, string $option_name, array $args = [] ): void {
		global $wp_registered_settings;
		$wp_registered_settings[ $option_name ] = [
			'group' => $option_group,
			'args'  => $args,
		];
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * Stub for add_menu_page.
	 */
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', string $icon_url = '', $position = null ): string {
		return $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Stub for add_submenu_page.
	 */
	function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', $position = null ): string|false {
		return $menu_slug;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Stub for wp_next_scheduled.
	 */
	function wp_next_scheduled( string $hook, array $args = [] ): int|false {
		global $wp_scheduled_events;
		return $wp_scheduled_events[ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Stub for wp_schedule_event.
	 */
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [] ): bool {
		global $wp_scheduled_events;
		$wp_scheduled_events[ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	/**
	 * Stub for wp_unschedule_event.
	 */
	function wp_unschedule_event( int $timestamp, string $hook, array $args = [] ): bool {
		global $wp_scheduled_events;
		unset( $wp_scheduled_events[ $hook ] );
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Stub for wp_clear_scheduled_hook.
	 */
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int {
		global $wp_scheduled_events;
		unset( $wp_scheduled_events[ $hook ] );
		return 1;
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	/**
	 * Stub for _doing_it_wrong — no-op.
	 */
	function _doing_it_wrong( string $function_name, string $message, string $version ): void {
		// No-op for tests.
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Stub for esc_html__.
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stub for esc_html.
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	/**
	 * Stub for wp_admin_notice.
	 */
	function wp_admin_notice( string $message, array $args = [] ): void {
		// No-op.
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Stub for trailingslashit.
	 */
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Stub for untrailingslashit.
	 */
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * Stub for wp_register_script.
	 */
	function wp_register_script( string $handle, string $src, array $deps = [], $ver = false, $args = [] ): bool {
		global $wp_scripts;
		$wp_scripts[ $handle ] = [
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		];
		return true;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	/**
	 * Stub for wp_register_style.
	 */
	function wp_register_style( string $handle, string $src, array $deps = [], $ver = false, string $media = 'all' ): bool {
		global $wp_styles;
		$wp_styles[ $handle ] = [
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		];
		return true;
	}
}

if ( ! function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
	/**
	 * Stub for wp_register_block_types_from_metadata_collection.
	 */
	function wp_register_block_types_from_metadata_collection( string $path, string $manifest ): void {
		// No-op.
	}
}

if ( ! function_exists( 'is_child_theme' ) ) {
	/**
	 * Stub for is_child_theme.
	 */
	function is_child_theme(): bool {
		global $wp_is_child_theme;
		return $wp_is_child_theme ?? false;
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	/**
	 * Stub for get_stylesheet_directory.
	 */
	function get_stylesheet_directory(): string {
		global $wp_stylesheet_dir;
		return $wp_stylesheet_dir ?? '/tmp/child-theme';
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	/**
	 * Stub for get_template_directory.
	 */
	function get_template_directory(): string {
		global $wp_template_dir;
		return $wp_template_dir ?? '/tmp/parent-theme';
	}
}

if ( ! function_exists( 'load_template' ) ) {
	/**
	 * Stub for load_template.
	 */
	function load_template( string $_template_file, bool $load_once = true, array $args = [] ): void {
		// No-op.
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * Stub for sanitize_file_name.
	 */
	function sanitize_file_name( string $filename ): string {
		return $filename;
	}
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	/**
	 * Minimal stub for WP_REST_Controller.
	 */
	class WP_REST_Controller {
		protected $namespace = '';
		protected $rest_base = '';

		public function register_routes(): void {}
	}
}

if ( ! class_exists( 'WP_Block' ) ) {
	/**
	 * Minimal stub for WP_Block.
	 */
	class WP_Block {
		public array $attributes = [];
		public string $inner_html = '';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub for get_option.
	 */
	function get_option( string $option, $default_value = false ) {
		global $wp_options;
		return $wp_options[ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stub for update_option.
	 */
	function update_option( string $option, $value, $autoload = null ): bool {
		global $wp_options;
		$wp_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Stub for delete_option.
	 */
	function delete_option( string $option ): bool {
		global $wp_options;
		unset( $wp_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'add_role' ) ) {
	/**
	 * Stub for add_role.
	 */
	function add_role( string $role, string $display_name, array $capabilities = [] ): ?\WP_Role {
		global $wp_roles;
		$wp_roles[ $role ] = [
			'name'         => $display_name,
			'capabilities' => $capabilities,
		];
		return null;
	}
}

if ( ! function_exists( 'remove_role' ) ) {
	/**
	 * Stub for remove_role.
	 */
	function remove_role( string $role ): void {
		global $wp_roles;
		unset( $wp_roles[ $role ] );
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	/**
	 * Stub for register_block_type.
	 */
	function register_block_type( $block_type, array $args = [] ) {
		global $wp_actions;
		$wp_actions[] = [
			'hook'     => 'register_block_type',
			'callback' => $block_type,
			'args'     => $args,
		];
		return true;
	}
}
