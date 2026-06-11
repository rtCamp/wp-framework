<?php
/**
 * RequestContext tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Telemetry\Capture;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Telemetry\Capture\RequestContext;
use rtCamp\WPFramework\Telemetry\Config;

final class RequestContextTest extends TestCase {

	protected function tearDown(): void {
		unset(
			$_SERVER['HTTP_HOST'],
			$_SERVER['REQUEST_URI'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER[ Config::SERVER_PROFILE ],
			$_SERVER[ Config::SERVER_IGNORE ],
			$GLOBALS['wp_framework_test_is_ssl'],
			$GLOBALS['wp_framework_test_doing_cron'],
			$GLOBALS['wp_framework_test_doing_ajax'],
			$GLOBALS['wp_framework_test_is_admin']
		);

		parent::tearDown();
	}

	public function test_uuid_is_memoized(): void {
		$context = new RequestContext();

		$uuid = $context->uuid();

		$this->assertSame( $uuid, $context->uuid() );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $uuid );
	}

	public function test_url_is_built_from_server_vars(): void {
		$_SERVER['HTTP_HOST']   = 'example.test';
		$_SERVER['REQUEST_URI'] = '/shop/?orderby=price';

		$this->assertSame( 'http://example.test/shop/?orderby=price', ( new RequestContext() )->url() );
	}

	public function test_url_uses_https_when_ssl(): void {
		$_SERVER['HTTP_HOST']                  = 'example.test';
		$_SERVER['REQUEST_URI']                = '/';
		$GLOBALS['wp_framework_test_is_ssl']   = true;

		$this->assertSame( 'https://example.test/', ( new RequestContext() )->url() );
	}

	public function test_method_uppercases_and_defaults_to_get(): void {
		$this->assertSame( 'GET', ( new RequestContext() )->method() );

		$_SERVER['REQUEST_METHOD'] = 'post';

		$this->assertSame( 'POST', ( new RequestContext() )->method() );
	}

	public function test_type_classification_precedence(): void {
		$this->assertSame( 'frontend', ( new RequestContext() )->type() );

		$GLOBALS['wp_framework_test_is_admin'] = true;
		$this->assertSame( 'admin', ( new RequestContext() )->type() );

		$GLOBALS['wp_framework_test_doing_ajax'] = true;
		$this->assertSame( 'ajax', ( new RequestContext() )->type() );

		$GLOBALS['wp_framework_test_doing_cron'] = true;
		$this->assertSame( 'cron', ( new RequestContext() )->type() );
	}

	public function test_profile_batch_and_label_from_header(): void {
		$context = new RequestContext();

		$this->assertNull( $context->profile_batch() );
		$this->assertNull( $context->label() );

		$_SERVER[ Config::SERVER_PROFILE ] = 'abc-123';

		$this->assertSame( 'abc-123', $context->profile_batch() );
		$this->assertSame( 'profile:abc-123', $context->label() );
	}

	public function test_should_ignore_reflects_header(): void {
		$this->assertFalse( ( new RequestContext() )->should_ignore() );

		$_SERVER[ Config::SERVER_IGNORE ] = '1';

		$this->assertTrue( ( new RequestContext() )->should_ignore() );
	}
}
