<?php
/**
 * Concrete class that uses the Loader trait for testing.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Traits\Loader;

class LoaderHost {

	use Loader;

	/**
	 * Expose load() for testing.
	 *
	 * @param class-string[] $classes Classes to load.
	 */
	public function do_load( array $classes ): void {
		$this->load( $classes );
	}
}
