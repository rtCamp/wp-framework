<?php
/**
 * AbstractAbility tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Ability;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Ability\AbstractAbility;
use rtCamp\WPFramework\Telemetry\Ability\ListRequests;
use rtCamp\WPFramework\Telemetry\Path\PathTranslator;

final class AbstractAbilityTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wp_framework_test_user_can'] );

		parent::tearDown();
	}

	/**
	 * Minimal concrete ability exposing the protected helpers.
	 */
	private function ability(): AbstractAbility {
		return new class() extends AbstractAbility {
			public function name(): string {
				return self::CATEGORY . '/test-ability';
			}

			protected function label(): string {
				return 'Test ability';
			}

			protected function description(): string {
				return 'A test double.';
			}

			protected function input_schema(): array {
				return [ 'type' => 'object' ];
			}

			protected function output_schema(): array {
				return [ 'type' => 'object' ];
			}

			public function execute( mixed $input ): array|\WP_Error {
				return [];
			}

			/**
			 * Exposes the protected helper.
			 *
			 * @param array<string, mixed> $request Request record.
			 *
			 * @return array<string, mixed>
			 */
			public function expose_summary( array $request ): array {
				return $this->public_request_summary( $request );
			}

			/**
			 * Exposes the protected helper.
			 *
			 * @param array<int, array<string, mixed>> $frames Frames.
			 * @param PathTranslator                   $paths  Translator.
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function expose_stack( array $frames, PathTranslator $paths ): array {
				return $this->format_stack( $frames, $paths );
			}
		};
	}

	public function test_args_flag_the_ability_public_for_mcp(): void {
		$args = $this->ability()->args();

		$this->assertTrue( $args['meta']['mcp']['public'] );
		$this->assertSame( 'wp-framework', $args['category'] );
		$this->assertIsArray( $args['input_schema'] );
		$this->assertIsArray( $args['output_schema'] );
		$this->assertIsCallable( $args['execute_callback'] );
		$this->assertIsCallable( $args['permission_callback'] );
		$this->assertNotSame( '', $args['label'] );
		$this->assertNotSame( '', $args['description'] );
	}

	public function test_permission_callback_requires_manage_options(): void {
		$permission = $this->ability()->args()['permission_callback'];

		$this->assertFalse( $permission() );

		$GLOBALS['wp_framework_test_user_can']['manage_options'] = true;

		$this->assertTrue( $permission() );
	}

	public function test_public_request_summary_strips_internal_fields(): void {
		$summary = $this->ability()->expose_summary(
			[
				'id'           => 'uuid-1',
				'_internal_id' => 7,
				'collectors'   => [ 'db_queries' => [] ],
				'url'          => 'http://example.test/',
			]
		);

		$this->assertSame(
			[
				'id'  => 'uuid-1',
				'url' => 'http://example.test/',
			],
			$summary
		);
	}

	public function test_format_stack_adds_host_file_or_null(): void {
		$paths = new PathTranslator( [ '/var/www/html/wp-content/plugins/my-plugin' => '/Users/dev/my-plugin' ] );

		$stack = $this->ability()->expose_stack(
			[
				[
					'display' => 'my_callback()',
					'file'    => '/var/www/html/wp-content/plugins/my-plugin/inc/Hooks.php',
					'line'    => 5,
				],
				[
					'display' => 'wp_load()',
					'file'    => '/wordpress/wp-load.php',
					'line'    => 1,
				],
			],
			$paths
		);

		$this->assertSame( '/Users/dev/my-plugin/inc/Hooks.php', $stack[0]['host_file'] );
		$this->assertNull( $stack[1]['host_file'] );
	}

	public function test_ability_names_share_the_category_prefix(): void {
		$this->assertSame( AbstractAbility::CATEGORY . '/list-requests', ( new ListRequests() )->name() );
	}
}
