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
 * Class - FeatureSelectorSettingsPage
 *
 * Admin settings page listing every flag registered with an injected
 * {@see FeatureSelector} as a checkbox. Uses the WordPress Settings API
 * end-to-end; the form posts to `options.php` (no custom handler). The page
 * lives under `Settings → {Context} Features` with slug, option group, and
 * titles all derived from the selector's context:
 *
 *     $features = new FeatureSelector( 'my-plugin' );
 *     $features->register( [ 'dark-mode' => [ 'name' => 'Dark Mode' ] ] );
 *
 *     ( new FeatureSelectorSettingsPage( $features ) )->register_hooks();
 *
 * When a flag is overridden by its constant (e.g. `MY_PLUGIN_FEATURE_DARK_MODE`
 * in `wp-config.php`), the checkbox is disabled and reflects the constant's
 * value, with a help message naming the constant. A disabled checkbox is not
 * submitted, so {@see FeatureSelectorSettingsPage::sanitize_settings()} skips
 * locked flags when rebuilding the stored array — their persisted value is left
 * untouched and reappears unchanged if the constant is later removed.
 *
 * Boot order: register flags on the selector before `admin_init` fires
 * (typically during `plugins_loaded` or `init`). The flag list is read lazily
 * when the Settings API hooks run, so flags registered after
 * {@see FeatureSelectorSettingsPage::register_hooks()} still appear.
 *
 * Override the protected seams (menu slug, option group, titles, capability
 * via the parent) or {@see FeatureSelectorSettingsPage::render_field()} to
 * customise the chrome.
 *
 * @since 0.0.1
 */
class FeatureSelectorSettingsPage extends AbstractSettingsPage {

	/**
	 * Constructor.
	 *
	 * @param FeatureSelector $selector Selector whose registered flags this
	 *                                  page manages; its context namespaces the
	 *                                  page slug and option group.
	 */
	public function __construct(
		protected FeatureSelector $selector,
	) {}

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
		$context = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $this->selector->get_context() ) ), '-' );

		return '' === $context ? static::get_slug() : $context . '-' . static::get_slug();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Underscored menu slug, matching the selector's option-key style
	 * (`my-plugin` → `my_plugin_features`).
	 *
	 * @return non-empty-string
	 */
	protected function get_option_group(): string {
		return str_replace( '-', '_', $this->get_menu_slug() );
	}

	/**
	 * Get the settings-section ID every flag's field is attached to.
	 *
	 * @return string Section ID.
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
		$context = $this->selector->get_context();

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
	 * A single array setting holding every flag's toggle (`slug => bool`). Read
	 * lazily at `admin_init`/`rest_api_init` time, after consumers have
	 * registered flags. Per-flag locking and the default-on rule are applied by
	 * {@see FeatureSelectorSettingsPage::sanitize_settings()}.
	 */
	protected function get_settings(): array {
		return [
			$this->selector->storage_key() => [
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [],
			],
		];
	}

	/**
	 * Sanitize the submitted toggles into the array stored under the selector's
	 * {@see FeatureSelector::storage_key()}.
	 *
	 * Rebuilds the `slug => bool` array from the posted checkboxes: every unlocked
	 * flag takes its submitted state — absent (unchecked) means `false`, so a
	 * default-on flag actually turns off. Locked flags carry no submittable field,
	 * so their stored value is preserved untouched; any unrelated keys already in
	 * the option are left as-is.
	 *
	 * @param mixed $value Raw submitted value (a `slug => '1'` map, or anything).
	 *
	 * @return array<string, mixed> Toggles to persist.
	 */
	public function sanitize_settings( mixed $value ): array {
		$submitted = is_array( $value ) ? $value : [];
		$stored    = (array) get_option( $this->selector->storage_key(), [] );

		foreach ( $this->selector->get_registered() as $slug ) {
			if ( defined( $this->selector->constant_name( $slug ) ) ) {
				continue;
			}

			$stored[ $slug ] = ! empty( $submitted[ $slug ] );
		}

		return $stored;
	}

	/**
	 * Register one settings field per flag, all attached to a single section.
	 * Hooked to `admin_init` only — see {@see FeatureSelectorSettingsPage::register_hooks()}.
	 */
	public function register_fields(): void {
		add_settings_section(
			$this->get_section_id(),
			'',
			'__return_null',
			$this->get_menu_slug()
		);

		foreach ( $this->selector->get_features() as $slug => $meta ) {
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
	 * Reuses {@see FeatureSelector::is_enabled()}, so a defined override
	 * constant is reflected automatically; the checkbox is then disabled and a
	 * help message names the constant.
	 *
	 * @param array{slug: string, name: string, description: string} $args Flag metadata.
	 */
	public function render_field( array $args ): void {
		$field_name    = $this->selector->storage_key() . '[' . $args['slug'] . ']';
		$constant_name = $this->selector->constant_name( $args['slug'] );
		$is_locked     = defined( $constant_name );

		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="1"
				<?php checked( $this->selector->is_enabled( $args['slug'] ) ); ?>
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
