<?php
/**
 * Action Scheduler API fakes for AbstractJob tests.
 *
 * wp-framework has no dependency on Action Scheduler — these stand-ins let
 * tests exercise AbstractJob's "available" branch without installing the
 * real library. Signatures mirror https://actionscheduler.org/api/.
 * Declared in the global namespace, exactly where the real library would put
 * them; explicitly required from bootstrap.php since PSR-4 doesn't autoload
 * plain functions.
 *
 * @package rtCamp\WPFramework\Tests\Fixtures
 */

declare( strict_types = 1 );

if ( ! class_exists( 'ActionScheduler', false ) ) {
	final class ActionScheduler {
		/**
		 * Toggle to simulate the library being unavailable; reset via as_fakes_reset().
		 */
		public static bool $initialized = true;

		public static function is_initialized( ?string $function_name = null ): bool {
			return self::$initialized;
		}
	}
}

if ( ! class_exists( 'ActionSchedulerFakeStore', false ) ) {
	/**
	 * In-memory scheduled-action store backing the as_*() fakes below.
	 */
	final class ActionSchedulerFakeStore {
		/** @var array<int, array{hook: string, args: array<mixed>, group: string, timestamp: ?int}> */
		public static array $actions = [];

		private static int $next_id = 1;

		public static function reset(): void {
			self::$actions = [];
			self::$next_id = 1;
		}

		/**
		 * @param array<mixed> $args
		 */
		public static function find( string $hook, array $args, string $group ): ?int {
			foreach ( self::$actions as $id => $action ) {
				if ( $action['hook'] === $hook && $action['args'] === $args && $action['group'] === $group ) {
					return $id;
				}
			}

			return null;
		}

		/**
		 * @param array<mixed> $args
		 */
		public static function add( string $hook, array $args, string $group, bool $unique, ?int $timestamp ): int {
			if ( $unique ) {
				$existing = self::find( $hook, $args, $group );
				if ( null !== $existing ) {
					return $existing;
				}
			}

			$id = self::$next_id++;

			self::$actions[ $id ] = [
				'hook'      => $hook,
				'args'      => $args,
				'group'     => $group,
				'timestamp' => $timestamp,
			];

			return $id;
		}
	}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( string $hook, array $args = [], string $group = '', bool $unique = false, int $priority = 10 ): int {
		return ActionSchedulerFakeStore::add( $hook, $args, $group, $unique, null );
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( int $timestamp, string $hook, array $args = [], string $group = '', bool $unique = false, int $priority = 10 ): int {
		return ActionSchedulerFakeStore::add( $hook, $args, $group, $unique, $timestamp );
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	function as_schedule_recurring_action( int $timestamp, int $interval_in_seconds, string $hook, array $args = [], string $group = '', bool $unique = false, int $priority = 10 ): int {
		return ActionSchedulerFakeStore::add( $hook, $args, $group, $unique, $timestamp );
	}
}

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	function as_has_scheduled_action( string $hook, ?array $args = null, string $group = '' ): bool {
		foreach ( ActionSchedulerFakeStore::$actions as $action ) {
			if ( $action['hook'] === $hook && $action['group'] === $group && ( null === $args || $action['args'] === $args ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	function as_next_scheduled_action( string $hook, ?array $args = null, string $group = '' ): int|bool {
		foreach ( ActionSchedulerFakeStore::$actions as $action ) {
			if ( $action['hook'] === $hook && $action['group'] === $group && ( null === $args || $action['args'] === $args ) ) {
				return $action['timestamp'] ?? true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'as_unschedule_action' ) ) {
	function as_unschedule_action( string $hook, array $args = [], string $group = '' ): ?int {
		$id = ActionSchedulerFakeStore::find( $hook, $args, $group );
		if ( null === $id ) {
			return null;
		}

		unset( ActionSchedulerFakeStore::$actions[ $id ] );

		return $id;
	}
}

if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
	/**
	 * @param array<string, mixed> $args
	 *
	 * @return array<int, mixed>
	 */
	function as_get_scheduled_actions( array $args = [], string $return_format = 'OBJECT' ): array {
		$matches = [];

		foreach ( ActionSchedulerFakeStore::$actions as $id => $action ) {
			if ( isset( $args['hook'] ) && $action['hook'] !== $args['hook'] ) {
				continue;
			}
			if ( isset( $args['group'] ) && $action['group'] !== $args['group'] ) {
				continue;
			}
			if ( isset( $args['args'] ) && $action['args'] !== $args['args'] ) {
				continue;
			}

			$matches[ $id ] = $action;
		}

		return 'ids' === $return_format ? array_keys( $matches ) : $matches;
	}
}

if ( ! function_exists( 'as_fakes_reset' ) ) {
	/**
	 * Reset both fakes to a clean, "available" state. Call from setUp().
	 */
	function as_fakes_reset(): void {
		ActionSchedulerFakeStore::reset();
		ActionScheduler::$initialized = true;
	}
}
