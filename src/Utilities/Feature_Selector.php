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
 *   1. PHP constant `FEATURE_<UPPER_SLUG>` — instant override (e.g. for tests
 *      or emergency disables via wp-config.php),
 *   2. WP option `rtcamp_feature_<lower_slug>` — persisted toggle from the
 *      settings page,
 *   3. default `false`.
 *
 * Hyphens in flag slugs are normalised to underscores for both the constant
 * and option-key derivations.
 *
 * @since 1.0.0
 */
class Feature_Selector {

	use Singleton;

	/**
	 * Registered feature-flag slugs (in registration order).
	 *
	 * @var array<int, string>
	 */
	private array $registered = array();

	/**
	 * Setup hook — required stub for the Singleton boot sequence.
	 *
	 * @return void
	 */
	public function setup(): void {}

	/**
	 * Register a list of feature flags. Idempotent — re-registering an
	 * already-known flag is a no-op.
	 *
	 * @param array<int, string> $flags Feature-flag slugs.
	 *
	 * @return void
	 */
	public function has_features( array $flags ): void {
		foreach ( $flags as $flag ) {
			if ( ! in_array( $flag, $this->registered, true ) ) {
				$this->registered[] = $flag;
			}
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
		$constant = 'FEATURE_' . strtoupper( str_replace( '-', '_', $flag ) );

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
}
