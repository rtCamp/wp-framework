<?php
/**
 * FeatureSelectorSettingsPage utility.
 *
 * @package rtCamp\WPFramework
 * @since   0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Utils;

use rtCamp\WPFramework\Contracts\Abstracts\AbstractSettingsPage;

/**
 * Admin settings page for a FeatureSelector registry.
 *
 * Renders one checkbox per registered flag and wires the form to the WordPress
 * Settings API. All flag states are stored in the single shared option managed
 * by the injected {@see FeatureSelector}.
 *
 * The form posts to `options.php` with array-style field names:
 *   {shared_option_key}[{flag_key}]  e.g.  elementary_features[dark_mode]
 *
 * The sanitize callback writes only non-locked flags back to the stored array.
 * Flags overridden by a PHP constant are rendered as locked (disabled checkbox)
 * and are excluded from the sanitize pass so saving never overwrites the
 * constant's intent in the options table.
 *
 * Boot order: register flags on the selector before `admin_init` fires. The
 * flag list is read lazily when the Settings API hooks run, so flags registered
 * after {@see FeatureSelectorSettingsPage::register_hooks()} still appear.
 *
 * Override the protected seams (menu slug, option group, titles, capability via
 * the parent) or {@see FeatureSelectorSettingsPage::render_field()} to customise
 * the page chrome.
 *
 * @since 0.0.1
 */
abstract class FeatureSelectorSettingsPage extends AbstractSettingsPage {

	/**
	 * Return the FeatureSelector whose flags this page manages.
	 *
	 * Called lazily at hook time, so it's safe to resolve from a shared container
	 * here — the container is fully populated before any hook fires.
	 */
	abstract protected function get_selector(): FeatureSelector;

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'features';
	}

	/**
	 * {@inheritDoc}
	 *
	 * `register_settings()` stays on both `admin_init` and `rest_api_init`
	 * (it only calls the REST-safe `register_setting()`), but sections and
	 * fields use `add_settings_section()`/`add_settings_field()` from
	 * `wp-admin/includes/template.php`, which is not loaded during REST
	 * requests — so field registration hooks `admin_init` only.
	 */
	public function register_hooks(): void {
		parent::register_hooks();

		add_action( 'admin_init', [ $this, 'register_fields' ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Slugifies the selector's context onto the base slug so the page slug
	 * stays a clean, valid menu slug regardless of context casing/spacing:
	 * `my-plugin` → `my-plugin-features`, `My Plugin v2.0` →
	 * `my-plugin-v2-0-features`, empty context → `features`.
	 *
	 * @return non-empty-string
	 */
	protected function get_menu_slug(): string {
		$context = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $this->get_selector()->get_context() ) ), '-' );

		return '' === $context ? static::get_slug() : $context . '-' . static::get_slug();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Underscored menu slug — intentionally kept in sync with the selector's
	 * shared_option_key() so that settings_fields() and the registered option
	 * name always match (both normalize context the same way).
	 *
	 * @return non-empty-string
	 */
	protected function get_option_group(): string {
		return str_replace( '-', '_', $this->get_menu_slug() );
	}

	/**
	 * Return the settings-section ID every flag field is attached to.
	 *
	 * @return string
	 */
	protected function get_section_id(): string {
		return $this->get_option_group() . '_section';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Context `my-plugin` → `My Plugin Features`; empty context → `Features`.
	 */
	protected function get_page_title(): string {
		$context = $this->get_selector()->get_context();

		if ( '' === $context ) {
			return __( 'Features', 'wp-framework' );
		}

		$package = ucwords( str_replace( [ '-', '_' ], ' ', $context ) );

		/* translators: %s: package name derived from the selector's context. */
		return sprintf( __( '%s Features', 'wp-framework' ), $package );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_menu_title(): string {
		return $this->get_page_title();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Registers a single array option (the selector's shared option key) with
	 * a sanitize callback that validates each non-locked flag's value.
	 *
	 * Locked flags (constant-overridden) are excluded from the form but their
	 * previously stored value is preserved — see sanitize_settings().
	 */
	protected function get_settings(): array {
		return [
			$this->get_selector()->shared_option_key() => [
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [],
			],
		];
	}

	/**
	 * Sanitize the submitted feature-flag array before it's written to the DB.
	 *
	 * For every registered, non-locked flag: true if the checkbox was submitted,
	 * false otherwise (unchecked checkboxes are absent from the POST body).
	 *
	 * For locked flags (constant-overridden): the previously stored value is
	 * carried forward so saving the form never silently resets a locked flag's
	 * database record to false.
	 *
	 * @param mixed $input Raw value received from the Settings API.
	 * @return array<string, bool> Sanitized flag-key => bool map.
	 */
	public function sanitize_settings( mixed $input ): array {
		$submitted = is_array( $input ) ? $input : [];
		$stored    = (array) get_option( $this->get_selector()->shared_option_key(), [] );
		$sanitized = [];

		foreach ( $this->get_selector()->get_registered() as $slug ) {
			$key = $this->get_selector()->flag_key( $slug );

			if ( defined( $this->get_selector()->constant_name( $slug ) ) ) {
				// Preserve the stored value so the constant's intent in the DB is not overwritten.
				if ( isset( $stored[ $key ] ) ) {
					$sanitized[ $key ] = (bool) $stored[ $key ];
				}

				continue;
			}

			$sanitized[ $key ] = isset( $submitted[ $key ] ) && (bool) $submitted[ $key ];
		}

		return $sanitized;
	}

	/**
	 * Register one settings field per flag, all attached to a single section.
	 *
	 * Hooked to `admin_init` only — see {@see FeatureSelectorSettingsPage::register_hooks()}.
	 */
	public function register_fields(): void {
		add_settings_section(
			$this->get_section_id(),
			'',
			'__return_null',
			$this->get_menu_slug()
		);

		foreach ( $this->get_selector()->get_features() as $slug => $meta ) {
			add_settings_field(
				$slug,
				esc_html( $meta['name'] ),
				[ $this, 'render_field' ],
				$this->get_menu_slug(),
				$this->get_section_id(),
				$meta
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->get_option_group() );
				do_settings_sections( $this->get_menu_slug() );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one flag's checkbox.
	 *
	 * Uses array-style field names ({shared_option_key}[{flag_key}]) so all
	 * flags post as a single option array. Reuses {@see FeatureSelector::is_enabled()}
	 * so a defined constant is reflected automatically; the checkbox is then
	 * disabled and a help message names the constant.
	 *
	 * @param array{slug: string, name: string, description: string} $args Flag metadata.
	 */
	public function render_field( array $args ): void {
		$field_name    = $this->get_selector()->shared_option_key() . '[' . $this->get_selector()->flag_key( $args['slug'] ) . ']';
		$constant_name = $this->get_selector()->constant_name( $args['slug'] );
		$is_locked     = defined( $constant_name );

		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="1"
				<?php checked( $this->get_selector()->is_enabled( $args['slug'] ) ); ?>
				<?php disabled( $is_locked ); ?>
			/>
			<?php esc_html_e( 'Enable', 'wp-framework' ); ?>
		</label>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $is_locked ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: PHP constant name. */
					esc_html__( 'This setting is locked because the constant %s is defined.', 'wp-framework' ),
					esc_html( $constant_name )
				);
				?>
			</p>
			<?php
		endif;
	}
}
