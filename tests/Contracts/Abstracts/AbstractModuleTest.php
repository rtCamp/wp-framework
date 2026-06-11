<?php
/**
 * AbstractModule tests.
 *
 * The module is an orchestrator: it has no WordPress registration of its own,
 * it delegates to the Registrable classes from get_classes() via the Loader
 * trait. Verified with the shared Loader fixtures.
 *
 * @package rtCamp\WPFramework\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Contracts\Abstracts;

use rtCamp\WPFramework\Contracts\Abstracts\AbstractModule;
use rtCamp\WPFramework\Tests\Fixtures\PlainRegistrable;
use rtCamp\WPFramework\Tests\Fixtures\ShareableRegistrable;
use rtCamp\WPFramework\Tests\TestCase;

final class AbstractModuleTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		PlainRegistrable::$registered     = false;
		ShareableRegistrable::$registered = false;
	}

	public function test_register_hooks_registers_each_managed_class(): void {
		$module = new class() extends AbstractModule {
			protected function get_classes(): array {
				return [ PlainRegistrable::class ];
			}
		};

		$module->register_hooks();

		$this->assertTrue( PlainRegistrable::$registered );
	}

	public function test_shareable_managed_class_is_cached_and_retrievable(): void {
		$module = new class() extends AbstractModule {
			protected function get_classes(): array {
				return [ ShareableRegistrable::class ];
			}
		};

		$module->register_hooks();

		$this->assertInstanceOf(
			ShareableRegistrable::class,
			$module->get_shared( ShareableRegistrable::class )
		);
	}
}
