<?php
/**
 * Trait for WordPress asset loading.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Traits;

/**
 * Trait - AssetLoaderTrait
 */
trait AssetLoaderTrait {
	/**
	 * The path to the built assets directory, relative to the base directory.
	 * No preceding or trailing slashes.
	 *
	 * @var string
	 */
	private string $assets_dir;

	/**
	 * Base directory path (plugin or theme root).
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Base URL (plugin or theme root URL).
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Register data from the block manifest file.
	 *
	 * @param string $block_path The relative path to the block collection. E.g. `build/blocks`.
	 * @param string $manifest_file Path to the manifest file, relative to the plugin directory. E.g. `build/blocks-manifest.php`.
	 */
	private function register_block_manifest( string $block_path, string $manifest_file ): void {
		$manifest_path = trailingslashit( $this->base_dir ) . $manifest_file;
		if ( ! file_exists( $manifest_path ) ) {
			_doing_it_wrong(
				self::class,
				esc_html__( 'Block manifest file is missing. Blocks will not be registered.', 'wp-framework' ),
				'0.0.1'
			);
			return;
		}

		wp_register_block_types_from_metadata_collection( trailingslashit( $this->base_dir ) . $block_path, $manifest_path );
	}

	/**
	 * Register a script.
	 *
	 * @param string   $handle    Name of the script. Should be unique.
	 * @param string   $filename  Path of the script relative to js directory, excluding the .js extension.
	 * @param string[] $deps      Optional. An array of registered script handles this script depends on. If not set, the dependencies will be inherited from the asset file.
	 * @param ?string  $ver       Optional. String specifying script version number. If not set, the version will be inherited from the asset file or fall back to the script's filemtime.
	 * @param bool     $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 */
	private function register_script( string $handle, string $filename, array $deps = [], ?string $ver = null, bool $in_footer = true ): bool {
		$asset = $this->get_asset_file( $filename, 'js' );

		if ( null === $asset ) {
			return false;
		}

		$asset_src = sprintf( '%s/%s.js', trailingslashit( $this->base_url ) . untrailingslashit( $this->assets_dir ), $filename );
		$deps      = $deps ?: ( $asset['dependencies'] ?? [] );
		$version   = $ver ?? $asset['version'];

		return wp_register_script(
			$handle,
			$asset_src,
			$deps,
			$version ?: false,
			$in_footer
		);
	}

	/**
	 * Register a CSS stylesheet.
	 *
	 * @param string   $handle   Name of the stylesheet. Should be unique.
	 * @param string   $filename Path of the stylesheet relative to the css directory, excluding the .css extension.
	 * @param string[] $deps     Optional. An array of registered stylesheet handles this stylesheet depends on. If not set, the dependencies will be inherited from the asset file.
	 * @param ?string  $ver      Optional. String specifying style version number. If not set, the version will be inherited from the asset file or fall back to the stylesheet's filemtime.
	 * @param string   $media    Optional. The media for which this stylesheet has been defined.
	 *                           Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                           '(orientation: portrait)' and '(max-width: 640px)'.
	 */
	private function register_style( string $handle, string $filename, array $deps = [], ?string $ver = null, string $media = 'all' ): bool {
		$asset = $this->get_asset_file( $filename, 'css' );

		if ( null === $asset ) {
			return false;
		}

		$asset_src = sprintf( '%s/%s.css', trailingslashit( $this->base_url ) . untrailingslashit( $this->assets_dir ), $filename );
		$deps      = $deps ?: ( $asset['dependencies'] ?? [] );
		$version   = $ver ?? $asset['version'];

		return wp_register_style(
			$handle,
			$asset_src,
			$deps,
			$version ?: false,
			$media
		);
	}

	/**
	 * Register a script module.
	 *
	 * @param string  $handle   Name of the script module. Should be unique.
	 * @param string  $filename Path of the module relative to the assets directory, excluding the .js extension.
	 * @param array   $deps     Optional. An array of module dependencies. Each can be a string
	 *                          (module ID) or an array with 'id' and 'import' keys.
	 * @param ?string $ver      Optional. String specifying module version number. If not set, the version will be inherited from the asset file or fall back to the module's filemtime.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function register_script_module( string $handle, string $filename, array $deps = [], ?string $ver = null ): bool {
		$asset = $this->get_asset_file( $filename, 'js' );

		if ( null === $asset ) {
			return false;
		}

		$asset_src = sprintf( '%s/%s.js', trailingslashit( $this->base_url ) . untrailingslashit( $this->assets_dir ), $filename );
		$deps      = $deps ?: ( $asset['dependencies'] ?? [] );
		$version   = $ver ?? $asset['version'];

		wp_register_script_module( $handle, $asset_src, $deps, $version ?: false );

		return true;
	}

	/**
	 * Resolve the dependency + version metadata for an asset.
	 *
	 * If `.asset.php` exists, it provides `dependencies` and (optionally) `version`.
	 * If `.asset.php` is missing, the asset is still registerable — dependencies
	 * default to empty and the version falls back to the asset file's mtime.
	 *
	 * @param string $filename  Path of the asset relative to the assets directory, excluding the file extension.
	 * @param string $extension Extension of the actual asset file (e.g. 'js', 'css').
	 *
	 * @return array{version: string, dependencies?: array<int, string>}|null Asset metadata, or null if the asset file itself is missing or the manifest is invalid.
	 */
	private function get_asset_file( string $filename, string $extension ): ?array {
		$base       = trailingslashit( $this->base_dir ) . untrailingslashit( $this->assets_dir );
		$asset_file = sprintf( '%s/%s.asset.php', $base, $filename );
		$real_file  = sprintf( '%s/%s.%s', $base, $filename, $extension );

		// The actual asset file is required — if it's missing, there is nothing to register.
		if ( ! file_exists( $real_file ) ) {
			_doing_it_wrong(
				self::class,
				sprintf(
					/* translators: 1: The asset filename. 2: The asset extension. */
					esc_html__( 'Asset file "%1$s.%2$s" is missing. The asset will not be registered.', 'wp-framework' ),
					esc_html( $filename ),
					esc_html( $extension )
				),
				'0.0.1'
			);
			return null;
		}

		// The .asset.php manifest is optional — when present, it provides deps + version.
		if ( file_exists( $asset_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- The file is checked for existence above.
			$asset = require $asset_file;

			if ( ! is_array( $asset ) ) {
				_doing_it_wrong(
					self::class,
					sprintf(
						/* translators: %s: The asset filename. */
						esc_html__( 'Asset manifest for "%s" is invalid. The asset will not be registered.', 'wp-framework' ),
						esc_html( $filename )
					),
					'0.0.1'
				);
				return null;
			}
		} else {
			$asset = [ 'dependencies' => [] ];
		}

		if ( ! isset( $asset['version'] ) ) {
			$asset['version'] = (string) filemtime( $real_file );
		}

		return $asset;
	}
}
