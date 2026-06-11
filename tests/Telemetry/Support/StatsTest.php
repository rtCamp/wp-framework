<?php
/**
 * Telemetry Stats helper tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Support;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Support\Stats;

final class StatsTest extends TestCase {

	public function test_median_of_odd_count_list(): void {
		$this->assertSame( 3.0, Stats::median( [ 5, 1, 3 ] ) );
	}

	public function test_median_of_even_count_list_averages_middle_pair(): void {
		$this->assertSame( 2.5, Stats::median( [ 4, 1, 2, 3 ] ) );
	}

	public function test_median_of_empty_list_is_null(): void {
		$this->assertNull( Stats::median( [] ) );
	}

	public function test_median_drops_non_numeric_values(): void {
		$this->assertSame( 2.0, Stats::median( [ 1, null, 'nope', 2, 3 ] ) );
	}

	public function test_median_all_non_numeric_is_null(): void {
		$this->assertNull( Stats::median( [ null, 'nope', false ] ) );
	}
}
