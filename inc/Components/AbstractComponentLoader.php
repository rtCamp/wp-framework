<?php
/**
 * Component loader for resolving and rendering PHP component partials.
 *
 * Resolves components from child theme, parent theme, then plugin paths.
 * Components are render-only PHP files that receive data as arguments and output HTML.
 *
 * @package rtCamp\WPFramework\Components
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Components;

use rtCamp\WPFramework\Contracts\Traits\AssetLoaderTrait;

/**
 * Class AbstractComponentLoader
 *
 * @since 0.0.1
 */
abstract class AbstractComponentLoader {
	use AssetLoaderTrait {
		register_script as private register_asset_loader_script;
		register_style as private register_asset_loader_style;
	}

	/**
	 * Default component path configuration for this instance.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $default_paths = [
		'theme' => [
			'php'    => 'src/components',
			'style'  => 'assets/build/css/components',
			'script' => 'assets/build/js/components',
		],
	];

	/**
	 * Default enqueue settings for components.
	 *
	 * @var array<string, bool>
	 */
	protected array $default_enqueue_settings = [
		'script' => true,
		'style'  => true,
	];

	/**
	 * Resolved component metadata cache.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $component_data_cache = [];

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Clear request-level lookup caches.
	 */
	public function clear_cache(): void {
		$this->component_data_cache = [];
	}

	/**
	 * Render a component by name.
	 *
	 * Resolves the component file from child theme, parent theme, or plugin paths,
	 * then includes it with the provided arguments available in scope.
	 *
	 * @param string               $name    Component name (e.g. 'Button', 'Card').
	 * @param array<string, mixed> $args    Arguments to pass to the component.
	 * @param array<string, mixed> $options {
	 *     Optional. Resolution and asset enqueue options.
	 *
	 *     @type bool   $script   Whether to enqueue the component's script. Default determined by filter.
	 *     @type bool   $style    Whether to enqueue the component's style. Default determined by filter.
	 * }
	 *
	 * @return void
	 */
	public function render( string $name, array $args = [], array $options = [] ): void {
		$this->render_component( $name, $args, $options, static::class . '::render' );
	}

	/**
	 * Render a component by name.
	 *
	 * @param string               $name    Component name (e.g. 'Button', 'Card').
	 * @param array<string, mixed> $args    Arguments to pass to the component.
	 * @param array<string, mixed> $options Resolution and asset enqueue options.
	 * @param string|null          $caller  Optional caller name for incorrect-usage notices.
	 *
	 * @return void
	 */
	private function render_component( string $name, array $args = [], array $options = [], ?string $caller = null ): void {

		$options   = $this->get_render_options( $options );
		$component = $this->get_component_data( $name, $options );

		if ( false === $component ) {
			_doing_it_wrong(
				$caller ?? __METHOD__, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a function name, not rendered output.
				sprintf(
					/* translators: %s: Component name. */
					esc_html__( 'Component "%s" could not be resolved.', 'wp-framework' ),
					esc_html( $name )
				),
				'0.0.1'
			);

			return;
		}

		$options['component'] = $component;

		$this->before_get_component( $name, $args, $options );

		// Delegate to a private static method to ensure the component file
		// is loaded in an isolated scope without access to $this.
		self::load_template( (string) $component['file'], $args, $name );

		$this->enqueue_component_assets( $component, $options );

		$this->after_get_component( $name, $args, $options );
	}

	/**
	 * Get default enqueue settings for components.
	 *
	 * @return array<string, bool> Default enqueue settings.
	 */
	protected function get_enqueue_settings(): array {
		return $this->default_enqueue_settings;
	}

	/**
	 * Get normalized render options.
	 *
	 * @param array<string, mixed> $options Render options.
	 *
	 * @return array<string, mixed> Render options with enqueue settings resolved.
	 */
	private function get_render_options( array $options ): array {
		$enqueue = $this->get_enqueue_settings();

		$enqueue = wp_parse_args(
			$options,
			$enqueue
		);

		$options['script'] = ! empty( $enqueue['script'] );
		$options['style']  = ! empty( $enqueue['style'] );

		return $options;
	}

	/**
	 * Get the rendered HTML of a component as a string.
	 *
	 * Uses output buffering to capture the component output instead of
	 * sending it directly to the browser.
	 *
	 * @param string               $name    Component name (e.g. 'Button', 'Card').
	 * @param array<string, mixed> $args    Arguments to pass to the component.
	 * @param array<string, mixed> $options {
	 *     Optional. Resolution options.
	 *
	 *     @type bool   $script   Whether to enqueue the component's script. Default determined by filter.
	 *     @type bool   $style    Whether to enqueue the component's style. Default determined by filter.
	 * }
	 *
	 * @return string Rendered component HTML, or empty string if not found.
	 */
	public function get( string $name, array $args = [], array $options = [] ): string {
		ob_start();
		$this->render_component( $name, $args, $options, static::class . '::get' );

		return (string) ob_get_clean();
	}

	/**
	 * Resolve the component file path.
	 *
	 * Checks the theme path first, then the plugin path, and returns the first match.
	 * Theme path format: {relative_theme_path}/{Name}/{Name}.php.
	 * Plugin path format: {absolute_source_path}/{Name}/{Name}.php.
	 *
	 * @param string               $name    Component name.
	 * @param array<string, mixed> $options Resolution options.
	 *
	 * @return array<string, mixed>|false Component metadata on success, false if not found.
	 */
	private function get_component_data( string $name, array $options = [] ): array|false {

		$component_name = $this->normalize_component_name( $name );

		if ( false === $component_name ) {
			return false;
		}

		$paths = $this->get_component_paths( $component_name, $options );

		if ( empty( $paths ) ) {
			return false;
		}

		$cache_key = $this->get_cache_key(
			[
				$component_name,
				$paths,
				$options['script'] ?? false,
				$options['style'] ?? false,
			]
		);

		if ( isset( $this->component_data_cache[ $cache_key ] ) ) {
			return $this->component_data_cache[ $cache_key ];
		}

		if ( ! empty( $paths['theme'] ) ) {
			$component = $this->get_theme_component_data( $component_name, $paths['theme'], $paths, $options );

			if ( false !== $component ) {
				$this->component_data_cache[ $cache_key ] = $component;

				return $component;
			}
		}

		if ( ! empty( $paths['plugin'] ) ) {
			$component = $this->get_plugin_component_data( $component_name, $paths['plugin'], $paths, $options );

			if ( false !== $component ) {
				$this->component_data_cache[ $cache_key ] = $component;

				return $component;
			}
		}

		return false;
	}

	/**
	 * Resolve theme component data through locate_template().
	 *
	 * @param string               $component_name Component name.
	 * @param array<string, mixed> $paths          Theme path config.
	 * @param array<string, mixed> $all_paths      All filtered path configs.
	 * @param array<string, mixed> $options        Component render options.
	 *
	 * @return array<string, mixed>|false Component metadata on success, false if not found.
	 */
	private function get_theme_component_data( string $component_name, array $paths, array $all_paths, array $options ): array|false {
		if ( empty( $paths['php'] ) || ! is_string( $paths['php'] ) ) {
			return false;
		}

		$component_slug = $component_name;
		$component_root = trim( $paths['php'], '/\\' );
		$file           = locate_template(
			[
				$component_root . '/' . $component_slug . '/' . $component_slug . '.php',
			],
			false,
			false
		);

		if ( empty( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		return [
			'name'   => $component_name,
			'source' => 'theme',
			'file'   => $file,
			'root'   => $paths['php'],
			'paths'  => $paths,
			'assets' => $this->get_component_assets( $component_name, $all_paths, $options ),
		];
	}

	/**
	 * Resolve plugin component data from an absolute source path.
	 *
	 * @param string               $component_name Component name.
	 * @param array<string, mixed> $paths          Plugin path config.
	 * @param array<string, mixed> $all_paths      All filtered path configs.
	 * @param array<string, mixed> $options        Component render options.
	 *
	 * @return array<string, mixed>|false Component metadata on success, false if not found.
	 */
	private function get_plugin_component_data( string $component_name, array $paths, array $all_paths, array $options ): array|false {
		if ( empty( $paths['php'] ) || ! is_string( $paths['php'] ) ) {
			return false;
		}

		$component_slug = $component_name;
		$file           = trailingslashit( $paths['php'] ) . $component_slug . '/' . $component_slug . '.php';

		if ( ! is_readable( $file ) ) {
			$file = trailingslashit( $paths['php'] ) . $component_name . '/' . $component_name . '.php';
		}

		if ( ! is_readable( $file ) ) {
			return false;
		}

		return [
			'name'   => $component_name,
			'source' => 'plugin',
			'file'   => $file,
			'root'   => $paths['php'],
			'paths'  => $paths,
			'assets' => $this->get_component_assets( $component_name, $all_paths, $options ),
		];
	}

	/**
	 * Get component asset metadata from child theme, parent theme, then plugin.
	 *
	 * @param string               $component_name Component name.
	 * @param array<string, mixed> $paths          All filtered path configs.
	 * @param array<string, mixed> $options        Component render options.
	 *
	 * @return array<string, array<string, string>> Asset metadata.
	 */
	private function get_component_assets( string $component_name, array $paths, array $options ): array {
		if ( empty( $options['style'] ) && empty( $options['script'] ) ) {
			return [];
		}

		$assets = [];

		foreach (
			[
				'style'  => 'css',
				'script' => 'js',
			] as $asset_type => $extension
		) {
			if ( empty( $options[ $asset_type ] ) ) {
				continue;
			}

			$asset_file_name = $component_name . '.' . $extension;

			if ( ! empty( $paths['theme'][ $asset_type ] ) && is_string( $paths['theme'][ $asset_type ] ) ) {
				$relative_asset_dir = trim( $paths['theme'][ $asset_type ], '/\\' );
				$child_asset_file   = trailingslashit( get_stylesheet_directory() ) . $relative_asset_dir . '/' . $asset_file_name;

				if ( is_readable( $child_asset_file ) ) {
					$assets[ $asset_type ] = [
						'file' => $child_asset_file,
						'url'  => trailingslashit( get_stylesheet_directory_uri() ) . $relative_asset_dir . '/' . $asset_file_name,
					];

					continue;
				}

				$theme_asset_file = trailingslashit( get_template_directory() ) . $relative_asset_dir . '/' . $asset_file_name;

				if ( is_readable( $theme_asset_file ) ) {
					$assets[ $asset_type ] = [
						'file' => $theme_asset_file,
						'url'  => trailingslashit( get_template_directory_uri() ) . $relative_asset_dir . '/' . $asset_file_name,
					];

					continue;
				}
			}

			if (
				! empty( $paths['plugin'][ $asset_type ] ) &&
				is_array( $paths['plugin'][ $asset_type ] ) &&
				! empty( $paths['plugin'][ $asset_type ]['dir'] ) &&
				! empty( $paths['plugin'][ $asset_type ]['url'] )
			) {
				$plugin_asset_file = trailingslashit( (string) $paths['plugin'][ $asset_type ]['dir'] ) . $asset_file_name;

				if ( is_readable( $plugin_asset_file ) ) {
					$assets[ $asset_type ] = [
						'file' => $plugin_asset_file,
						'url'  => trailingslashit( (string) $paths['plugin'][ $asset_type ]['url'] ) . $asset_file_name,
					];
				}
			}
		}

		return $assets;
	}

	/**
	 * Create a stable cache key for request-level lookup caches.
	 *
	 * @param array<mixed> $parts Cache key parts.
	 *
	 * @return string Cache key.
	 */
	private function get_cache_key( array $parts ): string {
		$encoded_parts = wp_json_encode( $parts );

		return md5( is_string( $encoded_parts ) ? $encoded_parts : '' );
	}

	/**
	 * Enqueue assets for a rendered component.
	 *
	 * @param array<string, mixed> $component Component metadata.
	 * @param array<string, mixed> $options   Component render options.
	 *
	 * @return void
	 */
	private function enqueue_component_assets( array $component, array $options ): void {
		if ( empty( $component['name'] ) || empty( $component['assets'] ) || ! is_array( $component['assets'] ) ) {
			return;
		}

		$slug = sanitize_key( (string) $component['name'] );

		if (
			! empty( $options['style'] ) &&
			! empty( $component['assets']['style'] ) &&
			is_array( $component['assets']['style'] )
		) {
			$handle = 'wp-framework-component-' . $slug . '-style';

			if ( $this->ensure_component_style_registered( $handle, $component['assets']['style'] ) ) {
				wp_enqueue_style( $handle );
			}
		}

		if (
			! empty( $options['script'] ) &&
			! empty( $component['assets']['script'] ) &&
			is_array( $component['assets']['script'] )
		) {
			$handle = 'wp-framework-component-' . $slug . '-script';

			if ( $this->ensure_component_script_registered( $handle, $component['assets']['script'] ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}

	/**
	 * Ensure a component script is registered.
	 *
	 * @param string               $handle    Name of the script. Should be unique.
	 * @param array<string, mixed> $asset     Component asset metadata.
	 * @param array<string>        $deps      Optional. An array of registered script handles this script depends on. Default empty array.
	 * @param string|bool|null     $ver       Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param bool                 $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *
	 * @return bool Whether the script is registered or registerable.
	 */
	private function ensure_component_script_registered( string $handle, array $asset, array $deps = [], string|bool|null $ver = false, bool $in_footer = true ): bool {
		if (
			empty( $asset['url'] ) ||
			empty( $asset['file'] ) ||
			! file_exists( $asset['file'] )
		) {
			return false;
		}

		if ( wp_script_is( $handle, 'registered' ) ) {
			return true;
		}

		$this->configure_asset_loader_context( (string) $asset['file'], (string) $asset['url'] );

		return $this->register_asset_loader_script(
			$handle,
			pathinfo( (string) $asset['file'], PATHINFO_FILENAME ),
			$deps,
			is_string( $ver ) ? $ver : null,
			$in_footer
		);
	}

	/**
	 * Ensure a component stylesheet is registered.
	 *
	 * @param string               $handle Name of the stylesheet. Should be unique.
	 * @param array<string, mixed> $asset  Component asset metadata.
	 * @param array<string>        $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param string|bool|null     $ver    Optional. String specifying style version number, if not set, filetime will be used as version number.
	 * @param string               $media  Optional. The media for which this stylesheet has been defined.
	 *
	 * @return bool Whether the style is registered or registerable.
	 */
	private function ensure_component_style_registered( string $handle, array $asset, array $deps = [], string|bool|null $ver = false, string $media = 'all' ): bool {
		if (
			empty( $asset['url'] ) ||
			empty( $asset['file'] ) ||
			! file_exists( $asset['file'] )
		) {
			return false;
		}

		if ( wp_style_is( $handle, 'registered' ) ) {
			return true;
		}

		$this->configure_asset_loader_context( (string) $asset['file'], (string) $asset['url'] );

		return $this->register_asset_loader_style(
			$handle,
			pathinfo( (string) $asset['file'], PATHINFO_FILENAME ),
			$deps,
			is_string( $ver ) ? $ver : null,
			$media
		);
	}

	/**
	 * Configure AssetLoaderTrait for a resolved component asset.
	 *
	 * @param string $file Absolute asset file path.
	 * @param string $url  Asset URL.
	 *
	 * @return void
	 */
	private function configure_asset_loader_context( string $file, string $url ): void {
		$asset_dir        = dirname( $file );
		$asset_url        = dirname( $url );
		$this->base_dir   = dirname( $asset_dir );
		$this->base_url   = dirname( $asset_url );
		$this->assets_dir = basename( $asset_dir );
	}

	/**
	 * Normalize and validate a component name before using it in filesystem paths.
	 *
	 * Normalization trims surrounding whitespace. Validation then enforces
	 * length bounds, blocks traversal and path separators, and allows only
	 * alphanumeric characters, underscores and dashes.
	 *
	 * @param string $name Component name to normalize and validate.
	 *
	 * @return string|false Normalized component name, or false when invalid.
	 */
	private function normalize_component_name( string $name ): string|false {
		$name = trim( $name );

		if (
			'' === $name ||
			strlen( $name ) > 128 ||
			str_contains( $name, '..' ) ||
			str_contains( $name, '/' ) ||
			str_contains( $name, '\\' ) ||
			1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $name )
		) {
			return false;
		}

		return $name;
	}

	/**
	 * Get the registered component paths.
	 *
	 * @param string               $component_name Component name being resolved.
	 * @param array<string, mixed> $options        Options passed to render().
	 *
	 * @return array<string, array<string, mixed>> Associative array of source => path config.
	 */
	protected function get_component_paths( string $component_name, array $options ): array {
		unset( $component_name, $options );

		return $this->default_paths;
	}

	/**
	 * Hook that fires before a component is rendered.
	 *
	 * @param string               $name    Component name.
	 * @param array<string, mixed> $args    Component arguments.
	 * @param array<string, mixed> $options Component options.
	 *
	 * @return void
	 */
	protected function before_get_component( string $name, array $args, array $options ): void {}

	/**
	 * Hook that fires after a component is rendered.
	 *
	 * @param string               $name    Component name.
	 * @param array<string, mixed> $args    Component arguments.
	 * @param array<string, mixed> $options Component options.
	 *
	 * @return void
	 */
	protected function after_get_component( string $name, array $args, array $options ): void {}

	/**
	 * Isolate the scope for the required component file.
	 *
	 * @param string               $__file Component file path.
	 * @param array<string, mixed> $args   Component arguments.
	 * @param string               $name   Component name.
	 *
	 * @return void
	 */
	private static function load_template( string $__file, array $args, string $name ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $name is available to templates.
		require $__file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable, WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Component file path is resolved and readability-checked before inclusion.
	}
}
