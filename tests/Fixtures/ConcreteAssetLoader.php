<?php
/**
 * Concrete class using AssetLoaderTrait for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\AssetLoaderTrait;

class ConcreteAssetLoader {
	use AssetLoaderTrait;

	public function __construct( string $plugin_dir, string $plugin_url, string $assets_dir ) {
		$this->plugin_dir = $plugin_dir;
		$this->plugin_url = $plugin_url;
		$this->assets_dir = $assets_dir;
	}

	/**
	 * Public wrapper for register_script.
	 */
	public function do_register_script( string $handle, string $filename, array $deps = [], $ver = null, bool $in_footer = true ): bool {
		return $this->register_script( $handle, $filename, $deps, $ver, $in_footer );
	}

	/**
	 * Public wrapper for register_style.
	 */
	public function do_register_style( string $handle, string $filename, array $deps = [], $ver = null, string $media = 'all' ): bool {
		return $this->register_style( $handle, $filename, $deps, $ver, $media );
	}

	/**
	 * Public wrapper for register_block_manifest.
	 */
	public function do_register_block_manifest( string $block_path, string $manifest_file ): void {
		$this->register_block_manifest( $block_path, $manifest_file );
	}
}
