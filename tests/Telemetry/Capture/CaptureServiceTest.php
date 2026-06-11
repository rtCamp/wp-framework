<?php
/**
 * CaptureService tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Capture;

use PHPUnit\Framework\TestCase;
use QM_Collectors;
use rtCamp\WPFramework\Telemetry\Capture\CaptureService;
use rtCamp\WPFramework\Telemetry\Capture\CollectorBridge;
use rtCamp\WPFramework\Telemetry\Capture\RequestContext;
use rtCamp\WPFramework\Telemetry\Normalize\Normalizer;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Telemetry\Store\Schema;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;

final class CaptureServiceTest extends TestCase {

	protected function tearDown(): void {
		QM_Collectors::$instance = null;
		unset(
			$GLOBALS['wp_framework_test_filters']['user_has_cap'],
			$GLOBALS['wp_framework_test_filters']['shutdown'],
			$GLOBALS['wp_framework_test_options'][ Schema::OPTION ],
			$_SERVER['HTTP_HOST'],
			$_SERVER['REQUEST_URI']
		);

		parent::tearDown();
	}

	public function test_register_hooks_wires_cap_grant_and_shutdown_capture(): void {
		( new CaptureService() )->register_hooks();

		$this->assertNotEmpty( $GLOBALS['wp_framework_test_filters']['user_has_cap'] ?? [] );
		$this->assertNotEmpty( $GLOBALS['wp_framework_test_filters']['shutdown'] ?? [] );
	}

	public function test_grant_qm_view_cap_adds_capability(): void {
		$allcaps = ( new CaptureService() )->grant_qm_view_cap( [ 'read' => true ] );

		$this->assertTrue( $allcaps['view_query_monitor'] );
		$this->assertTrue( $allcaps['read'] );
	}

	public function test_capture_delegates_to_bridge(): void {
		$GLOBALS['wp_framework_test_options'][ Schema::OPTION ] = Schema::VERSION;

		$_SERVER['HTTP_HOST']   = 'example.test';
		$_SERVER['REQUEST_URI'] = '/page/';

		QM_Collectors::init()->collectors = [];

		$repository = new RequestRepository( new FakeWpdb() );
		$bridge     = new CollectorBridge( new RequestContext(), new Normalizer(), $repository, 200 );

		( new CaptureService( $bridge ) )->capture();

		$this->assertCount( 1, $repository->list() );
	}
}
