<?php
/**
 * Feature_Selector_Settings_Page utility.
 *
 * @package RtCamp\WPToolkit\Utilities
 * @since   1.0.0
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Utilities;

use RtCamp\WPToolkit\Traits\Singleton;

/**
 * Minimal admin settings page that lists every registered feature flag
 * as a checkbox. Uses the WordPress Settings API end-to-end; form posts
 * to `options.php` (no custom handler). The page lives under
 * `Settings → rtCamp Features`.
 *
 * When a feature is overridden by its `RTCAMP_FEATURE_<UPPER_SLUG>`
 * constant (typically defined in `wp-config.php`), the checkbox is
 * disabled and reflects the constant's value, with a help message
 * naming the constant — same UX as WPVIP's "Search engine visibility"
 * lock when `VIP_JETPACK_IS_PRIVATE` is defined.
 *
 * Boot order: consumers must register features via
 * `Feature_Selector::get_instance()->has_features( [...] )` before
 * `admin_init` fires (typically during `plugins_loaded` or `init`).
 *
 * @since 1.0.0
 */
class Feature_Selector_Settings_Page {

	use Singleton;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private string $page_slug = 'rtcamp-features';

	/**
	 * Settings group used by `register_setting()` and `settings_fields()`.
	 *
	 * @var string
	 */
	private string $settings_group = 'rtcamp_features_group';

	/**
	 * Settings section ID — every flag's field is attached to this section.
	 *
	 * @var string
	 */
	private string $settings_section = 'rtcamp_features_section';

	/**
	 * Wire admin hooks. Call once at plugin boot.
	 *
	 * @return void
	 */
	public function setup(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the page under Settings.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'rtCamp Features', 'rtcamp-toolkit' ),
			__( 'rtCamp Features', 'rtcamp-toolkit' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render' )
		);
	}

	/**
	 * Register one boolean setting and one settings field per registered
	 * feature flag, all attached to a single settings section.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$selector = Feature_Selector::get_instance();
		$features = $selector->get_features();

		add_settings_section(
			$this->settings_section,
			'',
			'__return_null',
			$this->page_slug
		);

		foreach ( $features as $slug => $meta ) {
			register_setting(
				$this->settings_group,
				$selector->option_key( $slug ),
				array(
					'type'              => 'boolean',
					'sanitize_callback' => static fn( $value ): bool => (bool) $value,
					'default'           => false,
				)
			);

			add_settings_field(
				$slug,
				esc_html( $meta['name'] ),
				array( $this, 'render_field' ),
				$this->page_slug,
				$this->settings_section,
				$meta
			);
		}
	}

	/**
	 * Render the settings page. Capability-guarded.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_group );
				do_settings_sections( $this->page_slug );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single feature flag field — invoked by the Settings API
	 * for each registered field. `$args` is the feature's metadata bag
	 * passed through from `add_settings_field()`.
	 *
	 * @param array{slug: string, name: string, description: string} $args Feature metadata.
	 *
	 * @return void
	 */
	public function render_field( array $args ): void {
		$selector      = Feature_Selector::get_instance();
		$option_key    = $selector->option_key( $args['slug'] );
		$constant_name = $selector->constant_name( $args['slug'] );
		$is_overridden = defined( $constant_name );
		$enabled       = $is_overridden
			? (bool) constant( $constant_name )
			: (bool) get_option( $option_key, false );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $option_key ); ?>"
				value="1"
				<?php checked( $enabled, true ); ?>
				<?php disabled( $is_overridden, true ); ?>
			/>
			<?php esc_html_e( 'Enable', 'rtcamp-toolkit' ); ?>
		</label>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $is_overridden ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: PHP constant name. */
					esc_html__( 'This option is disabled when the constant %s is defined.', 'rtcamp-toolkit' ),
					esc_html( $constant_name )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}
}
