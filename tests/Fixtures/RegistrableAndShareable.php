<?php
/**
 * A class that is both Registrable and Shareable.
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Interfaces\Registrable;
use WPFramework\Contracts\Interfaces\Shareable;

class RegistrableAndShareable implements Registrable, Shareable {

	public static bool $hooks_registered = false;

	public function register_hooks(): void {
		self::$hooks_registered = true;
	}
}
