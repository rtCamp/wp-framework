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
 * Feature-flag registry with per-context toggle storage.
 *
 * Only registered flags resolve: an unregistered or mistyped slug always
 * returns false from {@see FeatureSelector::is_enabled()} (fails closed), so a
 * typo can't run a feature that doesn't exist. For a registered flag the lookup
 * precedence is:
 *   1. PHP constant — instant override (e.g. for tests or emergency disables
 *      via wp-config.php),
 *   2. stored toggle — the flag's entry in the per-context feature option (e.g.
 *      written by a settings page),
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
 * key scheme by overriding the {@see FeatureSelector::storage_key()} (where
 * toggles live) or {@see FeatureSelector::option_prefix()} (constant and
 * collision naming) seams — the same pattern as
 * {@see \rtCamp\WPFramework\Utils\Cache::resolve_group()}.
 *
 * Storage: every flag for a context lives in one option — `{context}_features`,
 * an array of `slug => bool` — so a consumer adds one autoloaded row no matter
 * how many flags it registers. The per-flag key `{context}_feature_{flag}` names
 * no stored option; it exists only to derive the override constant (its
 * uppercase, `MY_PLUGIN_FEATURE_DARK_MODE` for context `my-plugin`, flag
 * `dark-mode`) and to detect slugs that collide once normalized. Slugs are
 * normalized for derivation (lowercase; runs of characters outside `[a-z0-9_]`
 * collapse to one underscore). With an empty context the structural prefix
 * remains (`features` / `FEATURE_DARK_MODE`) — options and constants are global,
 * so bare names would risk colliding with unrelated code.
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
	 * First registration wins: a slug that collides with an already-registered
	 * one — an exact duplicate, or a different slug that normalizes to the same
	 * key (e.g. `beta-search` and `beta search`) — is ignored and flagged via
	 * `_doing_it_wrong()`, since the two would otherwise share a single override
	 * constant and be indistinguishable.
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

			// Collisions are detected on the normalized per-flag key, not the raw
			// slug: two slugs that normalize alike (e.g. `beta-search` and `beta
			// search`) would share one override constant, so the first wins.
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
	 * Unregistered flags fail closed: a never-registered slug (usually a typo)
	 * returns false rather than inheriting the default-`true`. The check is
	 * silent — it's a hot-path read, and a bad slug was already flagged at
	 * register() time. For a registered flag the precedence is: PHP constant →
	 * stored toggle → default `true`.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled( string $flag ): bool {
		if ( ! isset( $this->registered[ $flag ] ) ) {
			return false;
		}

		$constant = $this->constant_name( $flag );

		if ( defined( $constant ) ) {
			// Parse with WP's boolean rules: a string 'false' constant (a common
			// wp-config typo) must disable the flag — `(bool) 'false'` is true.
			return wp_validate_boolean( constant( $constant ) );
		}

		$features = (array) get_option( $this->storage_key(), [] );

		// Default-on: a flag absent from the stored bag has never been turned off.
		return ! array_key_exists( $flag, $features ) || (bool) $features[ $flag ];
	}

	/**
	 * Programmatically enable a flag — sets its entry in the context's feature
	 * option (see {@see FeatureSelector::storage_key()}).
	 *
	 * Refuses unregistered flags (flagged via `_doing_it_wrong()`): toggling a
	 * slug that was never registered — usually a typo — would write an entry that
	 * {@see FeatureSelector::is_enabled()} ignores, so the caller would believe
	 * it switched a feature on while nothing actually changed.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True if the option changed; false if the flag is unregistered,
	 *              or it already held `true` (the latter mirrors `update_option()`).
	 */
	public function enable( string $flag ): bool {
		if ( ! $this->assert_registered( $flag, __METHOD__ ) ) {
			return false;
		}

		return $this->set( $flag, true );
	}

	/**
	 * Programmatically disable a flag — sets its entry to `false` in the context's
	 * feature option.
	 *
	 * Refuses unregistered flags the same way {@see FeatureSelector::enable()}
	 * does, so a typo'd toggle fails loudly instead of silently no-op'ing.
	 *
	 * Note: a defined override constant still wins at read time.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return bool True if the option changed; false if the flag is unregistered
	 *              or it already held `false`.
	 */
	public function disable( string $flag ): bool {
		if ( ! $this->assert_registered( $flag, __METHOD__ ) ) {
			return false;
		}

		return $this->set( $flag, false );
	}

	/**
	 * Write a flag's toggle into the context's feature option (read-modify-write).
	 * A missing option reads as `[]`, so the first toggle creates the row; the
	 * shared array means one stored row per context, not one per flag.
	 *
	 * @param string $flag    Feature-flag slug (assumed registered).
	 * @param bool   $enabled Target state.
	 *
	 * @return bool Whether the option changed (false when it already held this value).
	 */
	private function set( string $flag, bool $enabled ): bool {
		$storage_key       = $this->storage_key();
		$features          = (array) get_option( $storage_key, [] );
		$features[ $flag ] = $enabled;

		return update_option( $storage_key, $features );
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
	 * Build the per-flag key `{context}_feature_{flag}`. This names no stored
	 * option — flags live together in {@see FeatureSelector::storage_key()} — and
	 * exists only to derive the override constant ({@see FeatureSelector::constant_name()})
	 * and to detect slugs that collide once normalized.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return string Fully qualified per-flag key.
	 */
	public function option_key( string $flag ): string {
		return $this->option_prefix() . $this->normalize( $flag );
	}

	/**
	 * Build the option key that stores every flag's toggle for this context — a
	 * single `{context}_features` array (`slug => bool`), so all flags share one
	 * autoloaded row. Override this seam to relocate storage; distinct from
	 * {@see FeatureSelector::option_key()}, which names no stored option.
	 *
	 * @return string Option key for the per-context feature bag.
	 */
	public function storage_key(): string {
		$context = $this->normalize( $this->context );

		return '' === $context ? 'features' : "{$context}_features";
	}

	/**
	 * Build the override-constant name for a flag — the uppercase of its
	 * per-flag key, so the pair stays symmetrical by construction. Defining
	 * this constant (typically in `wp-config.php`) overrides the stored toggle;
	 * its value is read with WordPress's boolean rules, so `false`, `'false'`,
	 * `'0'`, `0`, and `''` all disable the flag.
	 *
	 * @param string $flag Feature-flag slug.
	 *
	 * @return string Fully qualified constant name.
	 */
	public function constant_name( string $flag ): string {
		return strtoupper( $this->option_key( $flag ) );
	}

	/**
	 * Resolve the per-flag key prefix from the context. Override this seam to
	 * change the constant/collision scheme ({@see FeatureSelector::option_key()}
	 * and {@see FeatureSelector::constant_name()} follow automatically); override
	 * {@see FeatureSelector::storage_key()} to change where toggles are stored.
	 *
	 * @return string Per-flag key prefix, ending in `_`.
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

	/**
	 * Guard a programmatic toggle ({@see FeatureSelector::enable()}/{@see FeatureSelector::disable()})
	 * against unregistered flags: returns true if registered, otherwise flags the
	 * caller via `_doing_it_wrong()` and returns false so the toggle refuses the
	 * write. A typo'd toggle would otherwise write an entry that is never read
	 * back, fooling the caller into thinking a feature changed.
	 *
	 * The read path ({@see FeatureSelector::is_enabled()}) gates inline instead —
	 * a mistyped read just fails closed silently, avoiding notice spam on a hot
	 * path and a double notice when register() already rejected the slug.
	 *
	 * @param string $flag   Feature-flag slug.
	 * @param string $method Calling method, for the notice (pass `__METHOD__`).
	 *
	 * @return bool True if the flag is registered.
	 */
	private function assert_registered( string $flag, string $method ): bool {
		if ( isset( $this->registered[ $flag ] ) ) {
			return true;
		}

		_doing_it_wrong(
			esc_html( $method ),
			sprintf(
				/* translators: %s: feature-flag slug. */
				esc_html__( 'Feature flag "%s" is not registered; register it before use.', 'wp-framework' ),
				esc_html( $flag )
			),
			'0.0.1'
		);

		return false;
	}
}
