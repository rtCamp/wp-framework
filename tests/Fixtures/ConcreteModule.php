<?php
/**
 * Concrete module for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Module;

class ConcreteModule extends Abstract_Module {

	protected function get_classes(): array {
		return [
			ConcretePostType::class,
			ConcreteTaxonomy::class,
		];
	}
}
