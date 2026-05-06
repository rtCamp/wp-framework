<?php
/**
 * Tests for the Feature_Selector utility.
 *
 * @package RtCamp\WPToolkit\Tests\Utilities
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Tests\Utilities;

use RtCamp\WPToolkit\Utilities\Feature_Selector;
use WP_UnitTestCase;

/**
 * Feature_Selector utility tests.
 *
 * Runs against the real WordPress test suite (wp-tests-lib) so
 * `get_option` / `update_option` are real — no stubs.
 *
 * Notes on test isolation:
 *
 *   - `WP_UnitTestCase::tear_down()` rolls back the per-test transaction, so
 *     options written by one test do not leak to the next.
 *   - The Singleton's `$registered` array is *process* state, not DB state,
 *     so registered flags accumulate across tests within a run. Tests use
 *     `assertContains` / `assertArrayHasKey` (not `assertEquals`) and unique
 *     flag slugs per test so the assertions remain order-independent.
 *   - PHP constants are immutable once defined. The constant-precedence test
 *     uses a flag slug (`forced-on`) that no other test references, and
 *     guards the `define()` so re-running the test in the same process is
 *     idempotent.
 *
 * @since 1.0.0
 */
class Feature_SelectorTest extends WP_UnitTestCase {

	/**
	 * Registering features via the slug-list shorthand adds each slug to the
	 * registry exactly once.
	 */
	public function test_has_features_registers_flags(): void {
		Feature_Selector::get_instance()->has_features( array( 'feature-a', 'feature-b' ) );

		$registered = Feature_Selector::get_instance()->get_registered();

		$this->assertContains( 'feature-a', $registered );
		$this->assertContains( 'feature-b', $registered );
	}

	/**
	 * Registering features with metadata stores the name and description for
	 * later UI rendering. Slug-only entries fall back to the slug as the name.
	 */
	public function test_has_features_stores_metadata(): void {
		Feature_Selector::get_instance()->has_features(
			array(
				'rich-flag'   => array(
					'name'        => 'Rich Flag',
					'description' => 'A flag with full metadata.',
				),
				'simple-flag' => array(
					'name' => 'Simple Flag',
				),
				'bare-flag',
			)
		);

		$features = Feature_Selector::get_instance()->get_features();

		$this->assertArrayHasKey( 'rich-flag', $features );
		$this->assertSame( 'Rich Flag', $features['rich-flag']['name'] );
		$this->assertSame( 'A flag with full metadata.', $features['rich-flag']['description'] );

		$this->assertArrayHasKey( 'simple-flag', $features );
		$this->assertSame( 'Simple Flag', $features['simple-flag']['name'] );
		$this->assertSame( '', $features['simple-flag']['description'] );

		$this->assertArrayHasKey( 'bare-flag', $features );
		$this->assertSame( 'bare-flag', $features['bare-flag']['name'] );
		$this->assertSame( '', $features['bare-flag']['description'] );
	}

	/**
	 * A registered flag with no option set and no constant defined reads as false.
	 */
	public function test_disabled_by_default(): void {
		Feature_Selector::get_instance()->has_features( array( 'feature-default-off' ) );

		$this->assertFalse( Feature_Selector::get_instance()->is_feature_enabled( 'feature-default-off' ) );
	}

	/**
	 * Enabling a flag persists `true`; disabling it persists `false`.
	 */
	public function test_enable_persists_to_option(): void {
		Feature_Selector::get_instance()->has_features( array( 'feature-toggle' ) );

		Feature_Selector::get_instance()->enable( 'feature-toggle' );
		$this->assertTrue( Feature_Selector::get_instance()->is_feature_enabled( 'feature-toggle' ) );

		Feature_Selector::get_instance()->disable( 'feature-toggle' );
		$this->assertFalse( Feature_Selector::get_instance()->is_feature_enabled( 'feature-toggle' ) );
	}

	/**
	 * A defined PHP constant (`RTCAMP_FEATURE_<UPPER_SLUG>`) overrides the
	 * persisted option.
	 */
	public function test_constant_overrides_option(): void {
		if ( ! defined( 'RTCAMP_FEATURE_FORCED_ON' ) ) {
			define( 'RTCAMP_FEATURE_FORCED_ON', true );
		}

		Feature_Selector::get_instance()->has_features( array( 'forced-on' ) );

		// Even with the option explicitly set to false, the constant wins.
		Feature_Selector::get_instance()->disable( 'forced-on' );

		$this->assertTrue( Feature_Selector::get_instance()->is_feature_enabled( 'forced-on' ) );
	}

	/**
	 * `constant_name()` and `option_key()` produce the symmetrical
	 * `RTCAMP_FEATURE_` / `rtcamp_feature_` prefix pair, with hyphens
	 * normalised to underscores in both.
	 */
	public function test_key_derivation_is_symmetrical(): void {
		$selector = Feature_Selector::get_instance();

		$this->assertSame( 'RTCAMP_FEATURE_DEMO_FLAG', $selector->constant_name( 'demo-flag' ) );
		$this->assertSame( 'rtcamp_feature_demo_flag', $selector->option_key( 'demo-flag' ) );
	}
}
