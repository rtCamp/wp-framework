<?php
/**
 * Registers Telemetry abilities with the WordPress Abilities API.
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Telemetry\Ability;

use rtCamp\WPFramework\Contracts\Interfaces\Registrable;
use rtCamp\WPFramework\Telemetry\Store\Schema;

/**
 * Class - AbilityRegistrar
 *
 * The wp_abilities_api_* hooks only exist on WordPress 6.9+; on older
 * cores they never fire and this class is inert. The function_exists
 * guards inside the callbacks are belt-and-braces for partial backports.
 */
final class AbilityRegistrar implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Registers the shared ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			AbstractAbility::CATEGORY,
			[
				'label'       => __( 'WP Framework dev telemetry' ),
				'description' => __( 'Query Monitor telemetry captured per request, exposed for AI performance analysis.' ),
			]
		);
	}

	/**
	 * Registers every Telemetry ability.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Abilities read the store from a separate request than the captures
		// they serve — make sure the tables exist before the first read.
		Schema::ensure();

		foreach ( $this->abilities() as $ability ) {
			wp_register_ability( $ability->name(), $ability->args() );
		}
	}

	/**
	 * The abilities this module ships.
	 *
	 * @return AbstractAbility[]
	 */
	private function abilities(): array {
		return [
			new ListRequests(),
			new GetTelemetry(),
			new CompareRequests(),
			new ProfileUrl(),
		];
	}
}
