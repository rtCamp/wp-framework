<?php
/**
 * A class that is only Shareable (not Registrable).
 *
 * @package WPFramework\Tests\Fixtures
 */

declare( strict_types=1 );

namespace WPFramework\Tests\Fixtures;

use WPFramework\Contracts\Interfaces\Shareable;

class ShareableOnly implements Shareable {

	public string $value = 'shared';
}
