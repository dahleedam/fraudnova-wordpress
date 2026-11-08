<?php
/**
 * Request context tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Request_Context;

/**
 * Checkout / REST detection.
 */
final class RequestContextTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_unslash' )->alias( 'stripslashes' );
	}

	public function test_classic_checkout_action(): void {
		Functions\when( 'did_action' )->justReturn( 1 );
		$this->assertTrue( Request_Context::is_checkout() );
	}

	public function test_plain_request_is_not_checkout(): void {
		Functions\when( 'did_action' )->justReturn( 0 );
		$this->assertFalse( Request_Context::is_checkout() );
		$this->assertFalse( Request_Context::is_rest() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rest_store_api_uri_is_checkout(): void {
		define( 'REST_REQUEST', true );
		Functions\when( 'did_action' )->justReturn( 0 );
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
		$this->assertTrue( Request_Context::is_rest() );
		$this->assertTrue( Request_Context::is_checkout() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rest_route_query_var_is_checkout(): void {
		define( 'REST_REQUEST', true );
		Functions\when( 'did_action' )->justReturn( 0 );
		unset( $_SERVER['REQUEST_URI'] );
		$GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => '/wc/store/v1/checkout' ) );
		$this->assertTrue( Request_Context::is_checkout() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_other_rest_request_is_not_checkout(): void {
		define( 'REST_REQUEST', true );
		Functions\when( 'did_action' )->justReturn( 0 );
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertTrue( Request_Context::is_rest() );
		$this->assertFalse( Request_Context::is_checkout() );
	}
}
