<?php
/**
 * Query Monitor symbol stubs for PHPStan only — never loaded at runtime.
 *
 * The Telemetry module guards every QM access with class_exists(); these
 * stubs just teach PHPStan the shapes it cannot otherwise see (QM is the
 * developer's plugin install, not a Composer dependency).
 *
 * @package rtCamp\WPFramework
 */

// phpcs:ignoreFile -- analysis-only stubs, intentionally minimal.

/**
 * @implements \IteratorAggregate<int|string, object>
 */
class QM_Collectors implements \IteratorAggregate {

	public static function init(): self {
		return new self();
	}

	public function process(): void {
	}

	/**
	 * @return \Traversable<int|string, object>
	 */
	public function getIterator(): \Traversable {
		return new \ArrayIterator( [] );
	}
}
