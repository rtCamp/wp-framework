<?php
/**
 * Feature_Selector utility.
 *
 * @package RtCamp\WPToolkit\Utilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Utilities;

use RtCamp\WPToolkit\Traits\Singleton;

/**
 * Registry + per-feature toggle storage.
 *
 * Lookup precedence for `is_feature_enabled()`:
 *   1. PHP constant `RTCAMP_FEATURE_<UPPER_SLUG>` — instant override (e.g. for
 *      tests or emergency disables via wp-config.php),
 *   2. WP option `rtcamp_feature_<lower_slug>` — persisted toggle from the
 *      settings page,
 *   3. default `false`.
 *
 * Hyphens in flag slugs are normalised to underscores for both the constant
 * and option-key derivations. The constant and option prefixes are
 * symmetrical (`RTCAMP_FEATURE_` / `rtcamp_feature_`) so a flag named
 * `demo-flag` produces option `rtcamp_feature_demo_flag` and constant
 * `RTCAMP_FEATURE_DEMO_FLAG`.
 *
 * @since 1.0.0
 */
class Feature_Selector {

	use Singleton;

	/**
	 * Registered features keyed by slug, with display metadata.
	 *
	 * @var array<string, array{slug: string, name: string, description: string}>
	 */
	private array $registered = array();

	/**
	 * Setup hook — required stub for the Singleton boot sequence.
	 *
	 * @return void
	 */
	public function setup(): void {}

	/**
	 * Register feature flags. Accepts a list of slug strings, a map of
	 * slug => metadata, or any mix of the two. Re-registering an existing
	 * slug overwrites its metadata. Missing `name` falls back to the slug.
	 *
	 * @param array<int|string, string|array{name?: string, description?: string}> $features Features to register.
	 *
	 * @return void
	 */
	public function has_features( array $features ): void {
		foreach ( $features as $key => $value ) {
			if ( is_int( $key ) && is_string( $value ) ) {
				$slug = $value;
				$meta = array();
			} elseif ( is_string( $key ) && is_array( $value ) ) {
				$slug = $key;
				$meta = $value;
			} else {
				continue;
			}

			$this->registered[ $slug ] = array(
				'slug'        => $slug,
				'name'        => $meta['name'] ?? $slug,
				'description' => $meta['description'] ?? '',
			);
		}
	}

	/**
	 * Check whether a feature flag is enabled.
	 *
	 * Precedence: PHP constant → WP option → default `false`.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_feature_enabled( string $flag ): bool {
		$constant = $this->constant_name( $flag );

		if ( defined( $constant ) ) {
			return (bool) constant( $constant );
		}

		return (bool) get_option( $this->option_key( $flag ), false );
	}

	/**
	 * Programmatically enable a flag — persists to the WP option.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return void
	 */
	public function enable( string $flag ): void {
		update_option( $this->option_key( $flag ), true );
	}

	/**
	 * Programmatically disable a flag — persists to the WP option.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return void
	 */
	public function disable( string $flag ): void {
		update_option( $this->option_key( $flag ), false );
	}

	/**
	 * Get the list of registered feature-flag slugs.
	 *
	 * @return array<int, string> Registered flag slugs in registration order.
	 */
	public function get_registered(): array {
		return array_keys( $this->registered );
	}

	/**
	 * Get the full metadata map for every registered feature.
	 *
	 * @return array<string, array{slug: string, name: string, description: string}> Slug => metadata.
	 */
	public function get_features(): array {
		return $this->registered;
	}

	/**
	 * Build the WP option key for a flag (hyphens become underscores).
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return string Fully qualified option key.
	 */
	public function option_key( string $flag ): string {
		return 'rtcamp_feature_' . str_replace( '-', '_', $flag );
	}

	/**
	 * Build the override-constant name for a flag (uppercased; hyphens become
	 * underscores). Defining this constant in `wp-config.php` overrides the
	 * persisted option.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return string Fully qualified constant name.
	 */
	public function constant_name( string $flag ): string {
		return 'RTCAMP_FEATURE_' . strtoupper( str_replace( '-', '_', $flag ) );
	}
}
