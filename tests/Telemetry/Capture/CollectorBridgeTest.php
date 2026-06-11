<?php
/**
 * CollectorBridge tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Capture;

use PHPUnit\Framework\TestCase;
use QM_Collectors;
use rtCamp\WPFramework\Telemetry\Capture\CollectorBridge;
use rtCamp\WPFramework\Telemetry\Capture\RequestContext;
use rtCamp\WPFramework\Telemetry\Config;
use rtCamp\WPFramework\Telemetry\Normalize\Normalizer;
use rtCamp\WPFramework\Telemetry\Store\RequestRepository;
use rtCamp\WPFramework\Telemetry\Store\Schema;
use rtCamp\WPFramework\Tests\Fixtures\FakeWpdb;

final class CollectorBridgeTest extends TestCase {

	private FakeWpdb $db;

	private RequestRepository $repository;

	protected function setUp(): void {
		parent::setUp();

		$this->db         = new FakeWpdb();
		$this->repository = new RequestRepository( $this->db );

		// Schema already installed — Schema::ensure() short-circuits on the option.
		$GLOBALS['wp_framework_test_options'][ Schema::OPTION ] = Schema::VERSION;

		$_SERVER['HTTP_HOST']   = 'example.test';
		$_SERVER['REQUEST_URI'] = '/';
	}

	protected function tearDown(): void {
		QM_Collectors::$instance = null;
		unset(
			$GLOBALS['wp_framework_test_options'][ Schema::OPTION ],
			$_SERVER['HTTP_HOST'],
			$_SERVER['REQUEST_URI'],
			$_SERVER[ Config::SERVER_IGNORE ]
		);

		parent::tearDown();
	}

	private function bridge(): CollectorBridge {
		return new CollectorBridge( new RequestContext(), new Normalizer(), $this->repository, 200 );
	}

	private function fake_collector( array $data ): object {
		return new class( $data ) {
			/**
			 * @param array<string, mixed> $data Collector payload.
			 */
			public function __construct( private array $data ) {
			}

			/**
			 * @return array<string, mixed>
			 */
			public function get_data(): array {
				return $this->data;
			}
		};
	}

	public function test_capture_processes_collectors_and_saves(): void {
		QM_Collectors::init()->collectors = [
			'db_queries' => $this->fake_collector(
				[
					'rows' => [
						[
							'sql'   => 'SELECT 1',
							'ltime' => 0.005,
						],
					],
				]
			),
		];

		$this->bridge()->capture();

		$this->assertTrue( QM_Collectors::init()->processed );

		$rows = $this->repository->list();
		$this->assertCount( 1, $rows );
		$this->assertSame( 1, $rows[0]['query_count'] );
		$this->assertSame( 'http://example.test/', $rows[0]['url'] );
	}

	public function test_capture_skips_when_ignore_header_present(): void {
		$_SERVER[ Config::SERVER_IGNORE ] = '1';

		QM_Collectors::init()->collectors = [];

		$this->bridge()->capture();

		$this->assertCount( 0, $this->repository->list() );
	}

	public function test_capture_skips_favicon(): void {
		$_SERVER['REQUEST_URI'] = '/favicon.ico';

		$this->bridge()->capture();

		$this->assertCount( 0, $this->repository->list() );
	}

	public function test_capture_swallows_collector_exceptions(): void {
		QM_Collectors::init()->collectors = [
			'db_queries' => new class() {
				public function get_data(): never {
					throw new \RuntimeException( 'QM shape drift' );
				}
			},
		];

		// Silence the expected error_log() write.
		$previous = ini_set( 'error_log', '/dev/null' );

		try {
			$this->bridge()->capture();
		} finally {
			ini_set( 'error_log', (string) $previous );
		}

		$this->assertCount( 0, $this->repository->list() );
	}
}
