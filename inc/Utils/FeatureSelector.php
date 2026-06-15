<?php
/**
 * FeatureSelector utility.
 *
 * @package rtCamp\WPFramework
 * @since   0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Utils;

/**
 * Class - FeatureSelector
 *
 * Feature-flag registry with per-flag toggle storage.
 *
 * Lookup precedence for {@see FeatureSelector::is_enabled()}:
 *   1. PHP constant — instant override (e.g. for tests or emergency disables
 *      via wp-config.php),
 *   2. WP option — persisted toggle (e.g. from a settings page),
 *   3. default `true` — features ship on; the selector exists to turn things
 *      off, not on.
 *
 * Instance-based, configured with a context slug at construction — so each
 * consumer's option keys and override constants are namespaced and cannot
 * collide with another plugin or theme using the same flag name:
 *
 *     $features = new FeatureSelector( 'my-plugin' );
 *     $features->register( [ 'dark-mode', 'beta-search' => [ 'name' => 'Beta search' ] ] );
 *
 *     if ( $features->is_enabled( 'dark-mode' ) ) { ... }
 *
 * Designed to be a service: construct it with the package's slug, register an
 * instance as Shareable in a consumer's container, or extend it to change the
 * key scheme by overriding the {@see FeatureSelector::option_prefix()} seam
 * (the same pattern as {@see \rtCamp\WPFramework\Utils\Cache::resolve_group()}).
 *
 * Key derivation: context and flag slugs are normalized (lowercase, runs of
 * characters outside `[a-z0-9_]` collapse to one underscore), the option key
 * is `{context}_feature_{flag}`, and the override constant is its uppercase
 * (`MY_PLUGIN_FEATURE_DARK_MODE` for context `my-plugin`, flag `dark-mode`).
 * With an empty context the structural prefix remains (`feature_dark_mode` /
 * `FEATURE_DARK_MODE`) — options and constants are global, so bare flag names
 * would risk colliding with unrelated code.
 *
 * Pair with {@see FeatureSelectorSettingsPage} for an admin UI listing every
 * registered flag as a checkbox.
 *
 * @since 0.0.1
 */
class FeatureSelector {

	/**
	 * Registered features keyed by slug, with display metadata.
	 *
	 * @var array<string, array{slug: string, name: string, description: string}>
	 */
	protected array $registered = [];

	/**
	 * Constructor.
	 *
	 * @param string $context Context slug identifying the package that owns this
	 *                        instance (e.g. a theme/plugin slug). Prefixed onto
	 *                        every option key and override constant so consumers
	 *                        cannot collide. Empty string means no package
	 *                        namespacing — keys use the bare `feature_` prefix.
	 */
	public function __construct(
		protected string $context = '',
	) {}

	/**
	 * Get the context slug this instance was constructed with.
	 *
	 * @return string Context slug (verbatim, not normalized).
	 */
	public function get_context(): string {
		return $this->context;
	}

	/**
	 * Register feature flags. Accepts a single slug string, a list of slug
	 * strings, a map of slug => metadata, or any mix of the two. Missing
	 * `name` falls back to the slug; malformed entries are skipped.
	 *
	 * First registration wins: a slug that resolves to an already-registered
	 * option key — an exact duplicate, or a different slug that normalizes to
	 * the same key (e.g. `beta-search` and `beta search`) — is ignored and
	 * flagged via `_doing_it_wrong()`, since the two would otherwise silently
	 * share storage.
	 *
	 * @param array<int|string, string|array{name?: string, description?: string}>|string $features Feature(s) to register.
	 */
	public function register( array|string $features ): void {
		foreach ( (array) $features as $key => $value ) {
			if ( is_int( $key ) && is_string( $value ) ) {
				$slug = $value;
				$meta = [];
			} elseif ( is_string( $key ) && is_array( $value ) ) {
				$slug = $key;
				$meta = $value;
			} else {
				continue;
			}

			// Collisions are detected on the option key, not the raw slug: two
			// slugs that normalize to the same key (e.g. `beta-search` and
			// `beta search`) would share storage, so the first registration wins.
			$option_key = $this->option_key( $slug );

			foreach ( array_keys( $this->registered ) as $registered_slug ) {
				if ( $this->option_key( $registered_slug ) === $option_key ) {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: 1: slug being registered. 2: already-registered slug it collides with. */
							esc_html__( 'Feature flag "%1$s" collides with already-registered "%2$s"; keeping the first registration.', 'wp-framework' ),
							esc_html( $slug ),
							esc_html( $registered_slug )
						),
						'0.0.1'
					);

					continue 2;
				}
			}

			$this->registered[ $slug ] = [
				'slug'        => $slug,
				'name'        => $meta['name'] ?? $slug,
				'description' => $meta['description'] ?? '',
			];
		}
	}

	/**
	 * Check whether a feature flag is enabled.
	 *
	 * Precedence: PHP constant → WP option → default `true`.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled( string $flag ): bool {
		$constant = $this->constant_name( $flag );

		if ( defined( $constant ) ) {
			return (bool) constant( $constant );
		}

		return (bool) get_option( $this->option_key( $flag ), true );
	}

	/**
	 * Programmatically enable a flag — persists to the WP option.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True on success; false if the option already held `true`
	 *              (mirrors `update_option()`).
	 */
	public function enable( string $flag ): bool {
		return update_option( $this->option_key( $flag ), true );
	}

	/**
	 * Programmatically disable a flag — persists to the WP option.
	 *
	 * `update_option( $key, false )` alone is a silent no-op when the option has
	 * never been stored: core reads the missing option's implicit old value as
	 * `false`, sees it already equals the new `false`, and skips the write — so
	 * the row is never created and the flag stays at its default-`true` (enabled)
	 * state. Add the row explicitly in that case so disabling always sticks.
	 *
	 * Note: a defined override constant still wins at read time.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True if the option was written; false if it already held `false`.
	 */
	public function disable( string $flag ): bool {
		$option_key = $this->option_key( $flag );

		return null === get_option( $option_key, null )
			? add_option( $option_key, false )
			: update_option( $option_key, false );
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
	 * Build the WP option key for a flag.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return string Fully qualified option key.
	 */
	public function option_key( string $flag ): string {
		return $this->option_prefix() . $this->normalize( $flag );
	}

	/**
	 * Build the override-constant name for a flag — the uppercase of its
	 * option key, so the pair stays symmetrical by construction. Defining
	 * this constant (typically in `wp-config.php`) overrides the persisted
	 * option.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return string Fully qualified constant name.
	 */
	public function constant_name( string $flag ): string {
		return strtoupper( $this->option_key( $flag ) );
	}

	/**
	 * Resolve the option-key prefix from the context. Override this seam to
	 * change the key scheme ({@see FeatureSelector::constant_name()} follows
	 * automatically).
	 *
	 * @return string Option-key prefix, ending in `_`.
	 */
	protected function option_prefix(): string {
		$context = $this->normalize( $this->context );

		return '' === $context ? 'feature_' : $context . '_feature_';
	}

	/**
	 * Normalize a context or flag slug for key derivation: lowercase, with
	 * every run of characters outside `[a-z0-9_]` collapsed to a single
	 * underscore. Pure PHP (not `sanitize_key()`, which drops invalid
	 * characters — `my.plugin` and `myplugin` would silently collide) so
	 * derivation is deterministic and usable before WordPress loads.
	 *
	 * @param string $value Context or flag slug.
	 *
	 * @return string Normalized slug.
	 */
	protected function normalize( string $value ): string {
		return trim( (string) preg_replace( '/[^a-z0-9_]+/', '_', strtolower( $value ) ), '_' );
	}
}
