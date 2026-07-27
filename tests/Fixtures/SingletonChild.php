<?php
/**
 * Singleton fixture that inherits the trait from its parent.
 *
 * SingletonChild does not use the Singleton trait itself — it inherits
 * get_instance() from SingletonParent, which is the case where a shared
 * instance store would hand the parent's object back for the child.
 *
 * @package rtCamp\WPFramework\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Fixtures;

/**
 * Class - SingletonChild
 */
class SingletonChild extends SingletonParent {
}
