<?php
/**
 * Minimal global wpdb guard-stub so fakes can extend it without WordPress.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

// phpcs:disable -- test fixture mirroring WordPress core symbols.
if ( ! class_exists( 'wpdb' ) ) {
	#[\AllowDynamicProperties]
	class wpdb {
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
// phpcs:enable
