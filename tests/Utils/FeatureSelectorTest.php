<?php
/**
 * FeatureSelector utility tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Utils;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Utils\FeatureSelector;

/**
 * Tests for FeatureSelector.
 *
 * get_option/update_option are stubbed in tests/bootstrap.php as a functional
 * in-memory store; setUp()/tearDown() reset it between tests.
 *
 * Constants are process-global and immutable, so every constant-precedence
 * test uses a flag slug unique to that test and a guarded define() to stay
 * idempotent across re-runs in the same process.
 */
final class FeatureSelectorTest extends TestCase {

	/**
	 * Instance under test, constructed with the `my-plugin` context.
	 */
	private FeatureSelector $selector;

	protected function setUp(): void {
		$GLOBALS['_wp_options'] = [];

		$this->selector = new FeatureSelector( 'my-plugin' );
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_options'] = [];
	}

	// --- register / registry ---------------------------------------------------

	public function test_register_registers_flags_from_slug_list(): void {
		$this->selector->register( [ 'feature-a', 'feature-b' ] );

		$this->assertSame( [ 'feature-a', 'feature-b' ], $this->selector->get_registered() );
	}

	public function test_register_stores_metadata_with_fallbacks(): void {
		$this->selector->register(
			[
				'rich-flag'   => [
					'name'        => 'Rich Flag',
					'description' => 'A flag with full metadata.',
				],
				'simple-flag' => [
					'name' => 'Simple Flag',
				],
				'bare-flag',
			]
		);

		$features = $this->selector->get_features();

		$this->assertSame( 'Rich Flag', $features['rich-flag']['name'] );
		$this->assertSame( 'A flag with full metadata.', $features['rich-flag']['description'] );

		$this->assertSame( 'Simple Flag', $features['simple-flag']['name'] );
		$this->assertSame( '', $features['simple-flag']['description'] );

		$this->assertSame( 'bare-flag', $features['bare-flag']['name'] );
		$this->assertSame( '', $features['bare-flag']['description'] );
	}

	public function test_register_overwrites_existing_slug_and_skips_malformed_entries(): void {
		$this->selector->register( [ 'my-flag' => [ 'name' => 'Original' ] ] );
		$this->selector->register( [ 'my-flag' => [ 'name' => 'Updated' ] ] );

		// Malformed: int key with array value, string key with string value.
		$this->selector->register(
			[
				0          => [ 'name' => 'Nameless' ],
				'odd-pair' => 'not-an-array',
			]
		);

		$features = $this->selector->get_features();

		$this->assertSame( 'Updated', $features['my-flag']['name'] );
		$this->assertSame( [ 'my-flag' ], $this->selector->get_registered() );
	}

	// --- is_enabled / enable / disable ------------------------------------------

	public function test_disabled_by_default(): void {
		$this->selector->register( [ 'feature-default-off' ] );

		$this->assertFalse( $this->selector->is_enabled( 'feature-default-off' ) );
	}

	public function test_enable_and_disable_persist_to_option(): void {
		$this->selector->register( [ 'feature-toggle' ] );

		$this->selector->enable( 'feature-toggle' );
		$this->assertTrue( $this->selector->is_enabled( 'feature-toggle' ) );
		$this->assertTrue( $GLOBALS['_wp_options']['my_plugin_feature_feature_toggle'] );

		$this->selector->disable( 'feature-toggle' );
		$this->assertFalse( $this->selector->is_enabled( 'feature-toggle' ) );
		$this->assertFalse( $GLOBALS['_wp_options']['my_plugin_feature_feature_toggle'] );
	}

	public function test_true_constant_overrides_disabled_option(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_FORCED_ON' ) ) {
			define( 'MY_PLUGIN_FEATURE_FORCED_ON', true );
		}

		// Even with the option explicitly set to false, the constant wins.
		$this->selector->disable( 'forced-on' );

		$this->assertTrue( $this->selector->is_enabled( 'forced-on' ) );
	}

	public function test_false_constant_overrides_enabled_option(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_FORCED_OFF' ) ) {
			define( 'MY_PLUGIN_FEATURE_FORCED_OFF', false );
		}

		$this->selector->enable( 'forced-off' );

		$this->assertFalse( $this->selector->is_enabled( 'forced-off' ) );
	}

	// --- key derivation ----------------------------------------------------------

	public function test_key_derivation_is_symmetrical(): void {
		$this->assertSame( 'my_plugin_feature_demo_flag', $this->selector->option_key( 'demo-flag' ) );
		$this->assertSame( 'MY_PLUGIN_FEATURE_DEMO_FLAG', $this->selector->constant_name( 'demo-flag' ) );
	}

	public function test_empty_context_uses_bare_feature_prefix(): void {
		$selector = new FeatureSelector();

		$this->assertSame( 'feature_demo_flag', $selector->option_key( 'demo-flag' ) );
		$this->assertSame( 'FEATURE_DEMO_FLAG', $selector->constant_name( 'demo-flag' ) );
	}

	public function test_context_is_normalized(): void {
		$this->assertSame(
			'my_plugin_feature_x',
			( new FeatureSelector( 'My-Plugin' ) )->option_key( 'x' )
		);

		// Runs of spaces/dots and other invalid characters collapse to one underscore.
		$this->assertSame(
			'my_plugin_v2_0_feature_x',
			( new FeatureSelector( 'My Plugin v2.0' ) )->option_key( 'x' )
		);
	}

	public function test_same_flag_in_different_contexts_does_not_collide(): void {
		$other = new FeatureSelector( 'other-plugin' );

		$this->selector->enable( 'shared-flag' );

		$this->assertTrue( $this->selector->is_enabled( 'shared-flag' ) );
		$this->assertFalse( $other->is_enabled( 'shared-flag' ) );
	}

	public function test_get_context_returns_constructor_context(): void {
		$this->assertSame( 'my-plugin', $this->selector->get_context() );
		$this->assertSame( '', ( new FeatureSelector() )->get_context() );
	}
}
