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
 *     `assertContains` (not `assertEquals`) and unique flag slugs per test
 *     so the assertions remain order-independent.
 *   - PHP constants are immutable once defined. The constant-precedence test
 *     uses a flag slug (`forced-on`) that no other test references, and
 *     guards the `define()` so re-running the test in the same process is
 *     idempotent.
 *
 * @since 1.0.0
 */
class Feature_SelectorTest extends WP_UnitTestCase {

	/**
	 * Registering features adds each slug to the registry exactly once.
	 */
	public function test_has_features_registers_flags(): void {
		Feature_Selector::get_instance()->has_features( array( 'feature-a', 'feature-b' ) );

		$registered = Feature_Selector::get_instance()->get_registered();

		$this->assertContains( 'feature-a', $registered );
		$this->assertContains( 'feature-b', $registered );
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
	 * A defined PHP constant overrides the persisted option.
	 */
	public function test_constant_overrides_option(): void {
		if ( ! defined( 'FEATURE_FORCED_ON' ) ) {
			define( 'FEATURE_FORCED_ON', true );
		}

		Feature_Selector::get_instance()->has_features( array( 'forced-on' ) );

		// Even with the option explicitly set to false, the constant wins.
		Feature_Selector::get_instance()->disable( 'forced-on' );

		$this->assertTrue( Feature_Selector::get_instance()->is_feature_enabled( 'forced-on' ) );
	}
}
