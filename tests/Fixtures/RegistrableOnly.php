<?php
/**
 * A class that is only Registrable (not Shareable).
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Interfaces\Registrable;

class RegistrableOnly implements Registrable {

	public static bool $hooks_registered = false;

	public function register_hooks(): void {
		self::$hooks_registered = true;
	}
}
