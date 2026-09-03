<?php
/**
 * AbstractJob tests.
 *
 * Action Scheduler is not a dependency of this package, so these exercise
 * AbstractJob against the fakes in tests/Fixtures/ActionSchedulerFakes.php
 * rather than the real library — register_hooks(), schedule_*(), and the
 * guarded no-op paths, including a deterministic "unavailable" case via the
 * fake's toggleable ActionScheduler::$initialized flag. Tests use one
 * anonymous job class throughout; WordPress resets $wp_filter between tests
 * and setUp() resets the fakes, so reusing one hook name across test methods
 * is safe.
 *
 * @package rtCamp\WPFramework\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Contracts\Abstracts;

use rtCamp\WPFramework\Contracts\Abstracts\AbstractJob;
use rtCamp\WPFramework\Contracts\Interfaces\Registrable;
use rtCamp\WPFramework\Tests\TestCase;

/**
 * Tests for AbstractJob.
 *
 * @since 1.0.0
 */
final class AbstractJobTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! AS_FAKES_ACTIVE ) {
			$this->markTestSkipped( 'The real Action Scheduler is loaded, so the fakes these tests drive are not.' );
		}

		as_fakes_reset();
	}

	/**
	 * Minimal concrete AbstractJob with injectable behavior/config seams.
	 *
	 * @param \ArrayObject|null $log            Appended to with every handle() call, if given.
	 * @param string            $group          get_group() override.
	 * @param int               $hook_priority  get_hook_priority() override.
	 * @param bool              $unique         is_unique() override.
	 * @param int               $queue_priority get_queue_priority() override.
	 */
	private function make_job( ?\ArrayObject $log = null, string $group = '', int $hook_priority = 10, bool $unique = false, int $queue_priority = 10 ): AbstractJob {
		return new class( $log, $group, $hook_priority, $unique, $queue_priority ) extends AbstractJob {
			public function __construct(
				private readonly ?\ArrayObject $log,
				private readonly string $group,
				private readonly int $hook_priority,
				private readonly bool $unique,
				private readonly int $queue_priority
			) {}

			public static function get_hook(): string {
				return 'test-plugin/do-job';
			}

			protected function handle( array $args ): void {
				$this->log?->append( $args );
			}

			protected function get_group(): string {
				return $this->group;
			}

			protected function get_hook_priority(): int {
				return $this->hook_priority;
			}

			protected function get_queue_priority(): int {
				return $this->queue_priority;
			}

			protected function is_unique(): bool {
				return $this->unique;
			}
		};
	}

	public function test_implements_registrable(): void {
		$this->assertInstanceOf( Registrable::class, $this->make_job() );
	}

	public function test_register_hooks_adds_action_on_get_hook_with_declared_hook_priority_and_one_accepted_arg(): void {
		global $wp_filter;

		$job = $this->make_job( null, '', 20 );
		$job->register_hooks();

		$hook = $job::get_hook();

		$this->assertArrayHasKey( $hook, $wp_filter );
		$this->assertArrayHasKey( 20, $wp_filter[ $hook ]->callbacks );

		$registered = array_values( $wp_filter[ $hook ]->callbacks[20] );
		$this->assertCount( 1, $registered );
		$this->assertSame( 1, $registered[0]['accepted_args'] );
	}

	public function test_firing_the_hook_invokes_handle_with_the_scheduled_args(): void {
		$log = new \ArrayObject();
		$job = $this->make_job( $log );
		$job->register_hooks();

		// A plain do_action() call — no Action Scheduler involved — proves handle()
		// runs synchronously off the registered hook regardless of whether
		// Action Scheduler is present at all.
		do_action( $job::get_hook(), [ 'foo' => 'bar' ] );

		$this->assertSame( [ [ 'foo' => 'bar' ] ], $log->getArrayCopy() );
	}

	public function test_is_available_true_by_default(): void {
		$this->assertTrue( AbstractJob::is_available() );
	}

	public function test_is_available_reflects_action_scheduler_initialization_state(): void {
		\ActionScheduler::$initialized = false;

		$this->assertFalse( AbstractJob::is_available() );
	}

	public function test_schedule_async_enqueues_and_returns_action_id(): void {
		$job = $this->make_job();
		$id  = $job->schedule_async( [ 'foo' => 'bar' ] );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		$this->assertTrue( $job->is_scheduled( [ 'foo' => 'bar' ] ) );
	}

	public function test_schedule_at_and_schedule_recurring_pass_timestamp_and_interval_through(): void {
		$job             = $this->make_job();
		$at_timestamp    = time() + HOUR_IN_SECONDS;
		$recurring_start = time() + 2 * HOUR_IN_SECONDS;

		$single_id = $job->schedule_at( $at_timestamp, [ 'x' => 1 ] );
		$this->assertIsInt( $single_id );
		$this->assertGreaterThan( 0, $single_id );
		$this->assertSame( $at_timestamp, as_next_scheduled_action( $job::get_hook(), [ [ 'x' => 1 ] ] ) );

		$recurring_id = $job->schedule_recurring( $recurring_start, HOUR_IN_SECONDS, [ 'y' => 2 ] );
		$this->assertIsInt( $recurring_id );
		$this->assertGreaterThan( 0, $recurring_id );
		$this->assertSame( $recurring_start, as_next_scheduled_action( $job::get_hook(), [ [ 'y' => 2 ] ] ) );
	}

	public function test_is_scheduled_and_unschedule_round_trip(): void {
		$job = $this->make_job();
		$this->assertFalse( $job->is_scheduled( [ 'z' => 3 ] ) );

		$id = $job->schedule_at( time() + HOUR_IN_SECONDS, [ 'z' => 3 ] );
		$this->assertIsInt( $id );
		$this->assertTrue( $job->is_scheduled( [ 'z' => 3 ] ) );

		$cancelled_id = $job->unschedule( [ 'z' => 3 ] );
		$this->assertSame( $id, $cancelled_id );
		$this->assertFalse( $job->is_scheduled( [ 'z' => 3 ] ) );
	}

	public function test_group_and_uniqueness_overrides_propagate(): void {
		$job = $this->make_job( null, 'test-group', 10, true );

		$first_id = $job->schedule_at( time() + HOUR_IN_SECONDS, [ 'w' => 1 ] );
		$this->assertIsInt( $first_id );
		$this->assertGreaterThan( 0, $first_id );

		// is_unique() = true: a second schedule call for the same hook/group/args
		// must not create a second pending action, and reports 0 rather than null —
		// null is reserved for "Action Scheduler was never asked".
		$this->assertSame( 0, $job->schedule_at( time() + 2 * HOUR_IN_SECONDS, [ 'w' => 1 ] ) );

		$ids = as_get_scheduled_actions(
			[
				'hook'  => $job::get_hook(),
				'args'  => [ [ 'w' => 1 ] ],
				'group' => 'test-group',
			],
			'ids'
		);
		$this->assertCount( 1, $ids );

		// The group scoped the lookup: querying a different group finds nothing.
		$this->assertFalse( as_has_scheduled_action( $job::get_hook(), [ [ 'w' => 1 ] ], 'other-group' ) );
	}

	public function test_queue_priority_reaches_every_scheduling_call_and_ignores_hook_priority(): void {
		$job = $this->make_job( null, '', PHP_INT_MAX, false, 30 );

		$job->schedule_async( [ 'a' => 1 ] );
		$job->schedule_at( time() + HOUR_IN_SECONDS, [ 'b' => 2 ] );
		$job->schedule_recurring( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, [ 'c' => 3 ] );

		// The out-of-range hook priority stays on the hook; only get_queue_priority() reaches the queue.
		$this->assertSame( [ 30, 30, 30 ], array_column( \ActionSchedulerFakeStore::$actions, 'priority' ) );
	}

	public function test_schedule_methods_return_null_when_action_scheduler_unavailable(): void {
		\ActionScheduler::$initialized = false;

		$job = $this->make_job();

		$this->assertNull( $job->schedule_async() );
		$this->assertNull( $job->schedule_at( time() + HOUR_IN_SECONDS ) );
		$this->assertNull( $job->schedule_recurring( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS ) );
		$this->assertFalse( $job->is_scheduled() );
		$this->assertNull( $job->unschedule() );
	}
}
