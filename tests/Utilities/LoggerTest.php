<?php
/**
 * Tests for the Logger utility.
 *
 * @package RtCamp\WPToolkit\Tests\Utilities
 */

declare(strict_types=1);

namespace RtCamp\WPToolkit\Tests\Utilities;

use RtCamp\WPToolkit\Utilities\Logger;
use WP_UnitTestCase;

/**
 * Logger utility tests.
 *
 * Tests run against the real WordPress test suite so `wp_json_encode()`
 * and `WP_DEBUG` are available without stubs.
 *
 * @since 1.0.0
 */
class LoggerTest extends WP_UnitTestCase {

	/**
	 * Log file path used to capture error_log output.
	 *
	 * @var string
	 */
	private string $log_file = '';

	/**
	 * Original error_log ini value.
	 *
	 * @var string|false
	 */
	private $original_error_log;

	/**
	 * Set up — redirect error_log to a temp file, reset prefix.
	 */
	public function set_up(): void {
		parent::set_up();

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam -- Test scaffolding writes to a per-test temp file to capture error_log output; the VIP rule targets production filesystem use.
		$this->log_file = tempnam( sys_get_temp_dir(), 'logtest' );
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Redirecting error_log to a per-test file is the canonical way to capture log output in PHPUnit; reverted in tear_down().
		$this->original_error_log = ini_set( 'error_log', $this->log_file );

		Logger::get_instance()->set_prefix( 'rtcamp' );
	}

	/**
	 * Tear down — restore error_log and clean up temp file.
	 */
	public function tear_down(): void {
		if ( false !== $this->original_error_log ) {
			// phpcs:ignore WordPress.PHP.IniSet.Risky -- Symmetrically reverting the redirection set in set_up().
			ini_set( 'error_log', $this->original_error_log );
		}

		if ( file_exists( $this->log_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Cleaning up the per-test temp log file created in set_up().
			unlink( $this->log_file );
		}

		parent::tear_down();
	}

	/**
	 * Log writes the correct format with level, prefix, message, and context.
	 */
	public function test_log_writes_when_wp_debug_is_true(): void {
		Logger::get_instance()->set_prefix( 'test' );
		Logger::get_instance()->info( 'hello world', array( 'key' => 'value' ) );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading a local per-test file written by error_log; the VIP rule targets remote URLs.
		$contents = file_get_contents( $this->log_file );
		$this->assertStringContainsString( '[INFO]', $contents );
		$this->assertStringContainsString( '[test]', $contents );
		$this->assertStringContainsString( 'hello world', $contents );
		$this->assertStringContainsString( '"key":"value"', $contents );
	}

	/**
	 * Log format includes level tag and message without context JSON.
	 */
	public function test_log_format_includes_level_and_message(): void {
		Logger::get_instance()->error( 'something failed' );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading a local per-test file written by error_log; the VIP rule targets remote URLs.
		$contents = file_get_contents( $this->log_file );
		$this->assertStringContainsString( '[ERROR]', $contents );
		$this->assertStringContainsString( 'something failed', $contents );
	}
}
