<?php
/**
 * Telemetry Config tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Config;

final class ConfigTest extends TestCase {

	protected function tearDown(): void {
		unset(
			$GLOBALS['wp_framework_test_environment_type'],
			$GLOBALS['wp_framework_test_home_url'],
			$GLOBALS['wp_framework_test_filters']['rt_framework_telemetry_enabled'],
			$GLOBALS['wp_framework_test_filters']['rt_framework_telemetry_retention'],
			$GLOBALS['wp_framework_test_filters']['rt_framework_telemetry_path_map']
		);

		parent::tearDown();
	}

	public function test_is_enabled_false_outside_local_environment(): void {
		$GLOBALS['wp_framework_test_environment_type'] = 'production';

		$this->assertFalse( Config::is_enabled() );
	}

	public function test_is_enabled_true_on_local_with_dev_mode(): void {
		$GLOBALS['wp_framework_test_environment_type'] = 'local';

		$this->assertTrue( Config::is_enabled() );
	}

	public function test_is_enabled_filter_can_disable(): void {
		$GLOBALS['wp_framework_test_environment_type'] = 'local';

		add_filter( 'rt_framework_telemetry_enabled', static fn (): bool => false );

		$this->assertFalse( Config::is_enabled() );
	}

	public function test_retention_defaults_to_200(): void {
		$this->assertSame( 200, Config::retention() );
	}

	public function test_retention_filter_applies(): void {
		add_filter( 'rt_framework_telemetry_retention', static fn (): int => 50 );

		$this->assertSame( 50, Config::retention() );
	}

	public function test_retention_never_drops_below_floor(): void {
		add_filter( 'rt_framework_telemetry_retention', static fn (): int => 3 );

		$this->assertSame( 10, Config::retention() );
	}

	public function test_path_map_empty_by_default(): void {
		$this->assertSame( [], Config::path_map() );
	}

	public function test_path_map_filter_can_add_entries(): void {
		add_filter(
			'rt_framework_telemetry_path_map',
			static fn ( array $map ): array => $map + [ '/var/www/html/wp-content/plugins/my-plugin' => '/Users/dev/my-plugin' ]
		);

		$this->assertSame(
			[ '/var/www/html/wp-content/plugins/my-plugin' => '/Users/dev/my-plugin' ],
			Config::path_map()
		);
	}

	public function test_loopback_base_falls_back_to_home_url_untrailingslashed(): void {
		$GLOBALS['wp_framework_test_home_url'] = 'http://localhost:8888/';

		$this->assertSame( 'http://localhost:8888', Config::loopback_base() );
	}

	public function test_header_constants_match_server_keys(): void {
		$this->assertSame( 'X-WP-Framework-Profile', Config::HEADER_PROFILE );
		$this->assertSame( 'X-WP-Framework-Ignore', Config::HEADER_IGNORE );
		$this->assertSame( 'HTTP_X_WP_FRAMEWORK_PROFILE', Config::SERVER_PROFILE );
		$this->assertSame( 'HTTP_X_WP_FRAMEWORK_IGNORE', Config::SERVER_IGNORE );
	}
}
