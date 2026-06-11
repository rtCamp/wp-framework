<?php
/**
 * PhpErrorsMapper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize\Mappers;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\PhpErrorsMapper;

final class PhpErrorsMapperTest extends TestCase {

	public function test_maps_flat_qm4_shape_with_errno_level(): void {
		$raw = [
			'php_errors' => [
				'errors' => [
					'abc123' => [
						'errno'      => E_WARNING,
						'message'    => 'Undefined array key "color"',
						'suppressed' => false,
						'count'      => 3,
						'trace'      => [
							[
								'id'   => 'my_render',
								'file' => '/var/www/html/wp-content/themes/my-theme/parts/card.php',
								'line' => 21,
							],
						],
					],
				],
			],
		];

		$mapped = ( new PhpErrorsMapper() )->map( $raw );
		$error  = $mapped['errors'][0];

		$this->assertSame( 1, $mapped['count'] );
		$this->assertSame( 'warning', $error['level'] );
		$this->assertSame( 'Undefined array key "color"', $error['message'] );
		$this->assertSame( 3, $error['count'] );
		$this->assertSame( '/var/www/html/wp-content/themes/my-theme/parts/card.php', $error['file'] );
		$this->assertSame( 21, $error['line'] );
	}

	public function test_maps_legacy_nested_shape_using_group_key_as_level(): void {
		$raw = [
			'php_errors' => [
				'errors' => [
					'deprecated' => [
						'hash1' => [
							'message' => 'Function foo() is deprecated',
							'file'    => '/srv/plugin/legacy.php',
							'line'    => 8,
							'calls'   => 2,
						],
					],
				],
			],
		];

		$error = ( new PhpErrorsMapper() )->map( $raw )['errors'][0];

		$this->assertSame( 'deprecated', $error['level'] );
		$this->assertSame( 'Function foo() is deprecated', $error['message'] );
		$this->assertSame( 2, $error['count'] );
	}

	public function test_explicit_level_string_wins_over_errno(): void {
		$raw = [
			'php_errors' => [
				'errors' => [
					'h' => [
						'errno'   => E_NOTICE,
						'level'   => 'warning',
						'message' => 'mixed signals',
					],
				],
			],
		];

		$this->assertSame( 'warning', ( new PhpErrorsMapper() )->map( $raw )['errors'][0]['level'] );
	}

	public function test_missing_collector_degrades_to_zeroes(): void {
		$mapped = ( new PhpErrorsMapper() )->map( [] );

		$this->assertSame( 0, $mapped['count'] );
		$this->assertSame( [], $mapped['errors'] );
	}
}
