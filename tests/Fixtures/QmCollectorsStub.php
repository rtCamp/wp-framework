<?php
/**
 * Configurable QM_Collectors stand-in for capture-pipeline tests
 * (global namespace, like the Query Monitor plugin).
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

// phpcs:disable -- test fixture mirroring a Query Monitor class name.
if ( ! class_exists( 'QM_Collectors' ) ) {
	/**
	 * @implements \IteratorAggregate<string, object>
	 */
	class QM_Collectors implements IteratorAggregate {

		public static ?QM_Collectors $instance = null;

		/** @var array<string, object> */
		public array $collectors = [];

		public bool $processed = false;

		public static function init(): QM_Collectors {
			self::$instance ??= new QM_Collectors();

			return self::$instance;
		}

		public function process(): void {
			$this->processed = true;
		}

		public function getIterator(): Traversable {
			return new ArrayIterator( $this->collectors );
		}
	}
}
// phpcs:enable
