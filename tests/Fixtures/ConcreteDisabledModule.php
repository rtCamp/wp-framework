<?php
/**
 * Concrete module for testing (empty).
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Abstracts\Abstract_Module;

class ConcreteDisabledModule extends Abstract_Module {

	protected function get_classes(): array {
		return [];
	}
}
