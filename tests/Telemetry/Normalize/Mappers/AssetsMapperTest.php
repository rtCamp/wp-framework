<?php
/**
 * AssetsMapper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Normalize\Mappers;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Normalize\Mappers\AssetsMapper;

final class AssetsMapperTest extends TestCase {

	public function test_collects_handles_from_position_buckets(): void {
		$raw = [
			'assets_scripts' => [
				'assets' => [
					'header' => [ 'jquery-core', 'my-plugin-app' ],
					'footer' => [ 'my-plugin-lazy' ],
				],
			],
			'assets_styles'  => [
				'assets' => [
					'header' => [ 'my-plugin-style' ],
				],
			],
		];

		$mapped = ( new AssetsMapper() )->map( $raw );

		$this->assertSame( [ 'jquery-core', 'my-plugin-app', 'my-plugin-lazy' ], $mapped['scripts']['handles'] );
		$this->assertSame( [ 'my-plugin-style' ], $mapped['styles']['handles'] );
	}

	public function test_collects_flat_header_footer_lists_and_dedupes(): void {
		$raw = [
			'assets_scripts' => [
				'header' => [ 'jquery-core', 'jquery-core' ],
				'footer' => [ 'app' ],
			],
		];

		$mapped = ( new AssetsMapper() )->map( $raw );

		$this->assertSame( [ 'jquery-core', 'app' ], $mapped['scripts']['handles'] );
		$this->assertSame( [], $mapped['styles']['handles'] );
	}

	public function test_missing_collectors_degrade_to_empty_lists(): void {
		$mapped = ( new AssetsMapper() )->map( [] );

		$this->assertSame( [], $mapped['scripts']['handles'] );
		$this->assertSame( [], $mapped['styles']['handles'] );
	}
}
