<?php
/**
 * QmData defensive accessor tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Normalize\QmData;
use stdClass;

final class QmDataTest extends TestCase {

	public function test_get_reads_array_keys(): void {
		$this->assertSame( 'value', QmData::get( [ 'key' => 'value' ], 'key' ) );
	}

	public function test_get_reads_object_properties(): void {
		$data      = new stdClass();
		$data->sql = 'SELECT 1';

		$this->assertSame( 'SELECT 1', QmData::get( $data, 'sql' ) );
	}

	public function test_get_reads_array_access_offsets(): void {
		$data = new ArrayObject( [ 'ltime' => 0.5 ] );

		$this->assertSame( 0.5, QmData::get( $data, 'ltime' ) );
	}

	public function test_get_returns_default_when_absent(): void {
		$this->assertSame( 'fallback', QmData::get( [ 'other' => 1 ], 'missing', 'fallback' ) );
		$this->assertNull( QmData::get( 'not-a-carrier', 'missing' ) );
	}

	public function test_collector_tolerates_qm_prefix(): void {
		$raw = [ 'qm-db_queries' => [ 'count' => 3 ] ];

		$this->assertSame( [ 'count' => 3 ], QmData::collector( $raw, 'db_queries' ) );
		$this->assertNull( QmData::collector( $raw, 'http' ) );
	}

	public function test_frames_from_display_strings(): void {
		$frames = QmData::frames( [ 'wp_load_alloptions()' ] );

		$this->assertSame(
			[
				[
					'display'  => 'wp_load_alloptions()',
					'function' => null,
					'file'     => null,
					'line'     => null,
				],
			],
			$frames
		);
	}

	public function test_frames_from_qm4_frame_arrays(): void {
		$frames = QmData::frames(
			[
				[
					'id'   => 'get_option',
					'file' => '/var/www/html/wp-includes/option.php',
					'line' => 123,
				],
			]
		);

		$this->assertSame( 'get_option()', $frames[0]['display'] );
		$this->assertSame( 'get_option', $frames[0]['function'] );
		$this->assertSame( '/var/www/html/wp-includes/option.php', $frames[0]['file'] );
		$this->assertSame( 123, $frames[0]['line'] );
	}

	public function test_frames_unwraps_backtrace_objects(): void {
		$trace = new class() {
			/**
			 * Mimics QM_Backtrace::get_filtered_trace().
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function get_filtered_trace(): array {
				return [
					[
						'display' => 'MyPlugin\Hooks->run()',
						'file'    => '/srv/plugin/Hooks.php',
						'line'    => 9,
					],
				];
			}
		};

		$frames = QmData::frames( $trace );

		$this->assertSame( 'MyPlugin\Hooks->run()', $frames[0]['display'] );
		$this->assertSame( '/srv/plugin/Hooks.php', $frames[0]['file'] );
	}

	public function test_frames_of_non_trace_input_is_empty(): void {
		$this->assertSame( [], QmData::frames( null ) );
		$this->assertSame( [], QmData::frames( 'nope' ) );
	}

	public function test_call_site_returns_first_frame_with_file_and_line(): void {
		$frames = [
			[
				'display'  => 'apply_filters()',
				'function' => 'apply_filters',
				'file'     => null,
				'line'     => null,
			],
			[
				'display'  => 'my_callback()',
				'function' => 'my_callback',
				'file'     => '/srv/plugin/callbacks.php',
				'line'     => 42,
			],
		];

		$this->assertSame( '/srv/plugin/callbacks.php', QmData::call_site( $frames )['file'] );
	}

	public function test_call_site_of_fileless_frames_is_empty_shape(): void {
		$site = QmData::call_site( [] );

		$this->assertNull( $site['file'] );
		$this->assertNull( $site['line'] );
	}

	public function test_component_name_from_string_object_and_null(): void {
		$component       = new stdClass();
		$component->name = 'Plugin: my-plugin';

		$this->assertSame( 'Core', QmData::component_name( 'Core' ) );
		$this->assertSame( 'Plugin: my-plugin', QmData::component_name( $component ) );
		$this->assertNull( QmData::component_name( null ) );
		$this->assertNull( QmData::component_name( '' ) );
	}
}
