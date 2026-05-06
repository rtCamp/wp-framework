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
 * as a checkbox. Persisted via the WordPress Settings API; no custom
 * form handling. The page lives under `Settings → rtCamp Features`.
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
	 * Register one boolean setting per registered feature flag.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$features = Feature_Selector::get_instance()->get_registered();

		foreach ( $features as $flag ) {
			register_setting(
				$this->settings_group,
				Feature_Selector::get_instance()->option_key( $flag ),
				array(
					'type'              => 'boolean',
					'sanitize_callback' => static fn( $value ): bool => (bool) $value,
					'default'           => false,
				)
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

		$selector = Feature_Selector::get_instance();
		$features = $selector->get_features();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'rtCamp Features', 'rtcamp-toolkit' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( $this->settings_group ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<?php
						foreach ( $features as $slug => $meta ) :
							$option_key    = $selector->option_key( $slug );
							$constant_name = $selector->constant_name( $slug );
							$is_overridden = defined( $constant_name );
							$enabled       = $is_overridden
								? (bool) constant( $constant_name )
								: (bool) get_option( $option_key, false );
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $meta['name'] ); ?></th>
								<td>
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
									<?php if ( '' !== $meta['description'] ) : ?>
										<p class="description"><?php echo esc_html( $meta['description'] ); ?></p>
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
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
