<?php
/**
 * Admin AJAX tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Ajax;
use FraudNova\WordPress\Api_Response;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Scorer;
use FraudNova\WordPress\Settings;
use Mockery;

/**
 * Tests for Admin\Ajax.
 */
class AdminAjaxTest extends TestCase {

	/**
	 * Mocked scorer.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $scorer;

	/**
	 * Handler under test.
	 *
	 * @var Ajax
	 */
	private $ajax;

	/**
	 * Stored transients.
	 *
	 * @var array
	 */
	private $transients = array();

	protected function setUp(): void {
		parent::setUp();
		$this->transients = array();
		$transients       = &$this->transients;

		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) {
				return Settings::OPTION === $k ? array( 'api_key' => 'fn_test' ) : $d;
			}
		);
		Functions\when( 'number_format_i18n' )->alias(
			function ( $n ) {
				return number_format( (float) $n );
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $k, $v, $ttl ) use ( &$transients ) {
				$transients[ $k ] = array( $v, $ttl );
				return true;
			}
		);
		Functions\when( 'wp_send_json_success' )->alias(
			function ( $data = null, $status = null ) {
				throw new JsonResponseException( true, $data, $status );
			}
		);
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $data = null, $status = null ) {
				throw new JsonResponseException( false, $data, $status );
			}
		);

		$this->scorer = Mockery::mock( Scorer::class );
		$this->ajax   = new Ajax( Plugin::instance() );
		$this->ajax->set_scorer( $this->scorer );
	}

	/**
	 * Run the handler and return the JSON it sent.
	 *
	 * @return JsonResponseException
	 */
	private function run_test_connection(): JsonResponseException {
		try {
			$this->ajax->test_connection();
		} catch ( JsonResponseException $e ) {
			return $e;
		}
		$this->fail( 'No JSON response was sent.' );
	}

	/**
	 * A successful balance response.
	 *
	 * @param int $credits Credits.
	 * @return Api_Response
	 */
	private function ok_balance( int $credits ): Api_Response {
		$wp = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'success'           => true,
					'credits_remaining' => $credits,
				)
			),
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $r ) {
				return $r['response']['code'];
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $r ) {
				return $r['body'];
			}
		);
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( '' );
		return Api_Response::from_wp( $wp );
	}

	public function test_registers_ajax_and_clear_log_hooks() {
		Actions\expectAdded( 'wp_ajax_fraudnova_test_connection' )->once()->with( array( $this->ajax, 'test_connection' ) );
		Actions\expectAdded( 'admin_post_fraudnova_clear_log' )->once()->with( array( $this->ajax, 'clear_log' ) );
		$this->ajax->register();
		$this->addToAssertionCount( 1 );
	}

	public function test_nonce_failure_stops_the_handler() {
		Functions\expect( 'check_ajax_referer' )->once()->with( 'fraudnova_admin', 'nonce' )->andReturnUsing(
			function () {
				throw new \RuntimeException( 'bad nonce' );
			}
		);
		$this->scorer->shouldNotReceive( 'balance' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'bad nonce' );
		$this->ajax->test_connection();
	}

	public function test_missing_capability_returns_403() {
		Functions\when( 'check_ajax_referer' )->justReturn( 1 );
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		$this->scorer->shouldNotReceive( 'balance' );

		$sent = $this->run_test_connection();

		$this->assertFalse( $sent->success );
		$this->assertSame( 403, $sent->status );
	}

	public function test_success_caches_balance_and_reports_credits() {
		Functions\when( 'check_ajax_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->scorer->shouldReceive( 'balance' )->once()->andReturn( $this->ok_balance( 1240 ) );

		$sent = $this->run_test_connection();

		$this->assertTrue( $sent->success );
		$this->assertSame( 'Connected. 1,240 credits remaining.', $sent->data['message'] );
		$this->assertSame( 1240, $sent->data['credits'] );
		$this->assertSame( 10 * MINUTE_IN_SECONDS, $this->transients['fraudnova_balance'][1] );
		$this->assertSame( 1240, $this->transients['fraudnova_balance'][0]['credits'] );
		$this->assertIsInt( $this->transients['fraudnova_balance'][0]['time'] );
	}

	/**
	 * Failure codes and their friendly messages.
	 *
	 * @return array
	 */
	public function failures(): array {
		return array(
			'no key'       => array( 'no_api_key', 'Missing key.', 'Enter your API key first.' ),
			'unauthorized' => array( 'unauthorized', 'Unauthenticated.', 'The API key was rejected.' ),
			'transport'    => array( 'transport', 'cURL error 28', 'Could not reach fraudnova.com: cURL error 28' ),
			'other'        => array( 'rate_limited', 'Too many requests.', 'Too many requests.' ),
		);
	}

	/**
	 * Failures map to friendly messages.
	 *
	 * @dataProvider failures
	 *
	 * @param string $code     Error code.
	 * @param string $message  API message.
	 * @param string $expected Expected message.
	 */
	public function test_failure_messages( string $code, string $message, string $expected ) {
		Functions\when( 'check_ajax_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->scorer->shouldReceive( 'balance' )->once()->andReturn( Api_Response::failure( $code, $message ) );

		$sent = $this->run_test_connection();

		$this->assertFalse( $sent->success );
		$this->assertSame( $expected, $sent->data['message'] );
		$this->assertArrayNotHasKey( 'fraudnova_balance', $this->transients );
	}

	public function test_clear_log_checks_nonce_and_capability_then_redirects() {
		Functions\expect( 'check_admin_referer' )->once()->with( 'fraudnova_clear_log' )->andReturn( 1 );
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\expect( 'delete_option' )->once()->with( 'fraudnova_activity_log' );
		Functions\when( 'admin_url' )->alias(
			function ( $p = '' ) {
				return 'https://shop.test/wp-admin/' . $p;
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\expect( 'wp_safe_redirect' )->once()->with( 'https://shop.test/wp-admin/options-general.php?page=fraudnova&tab=activity&cleared=1' );

		$ajax = new class( Plugin::instance() ) extends Ajax {
			/**
			 * Whether terminate() was called.
			 *
			 * @var bool
			 */
			public $terminated = false;

			/**
			 * Stub exit.
			 *
			 * @return void
			 */
			protected function terminate(): void {
				$this->terminated = true;
			}
		};
		$ajax->clear_log();

		$this->assertTrue( $ajax->terminated );
	}

	public function test_clear_log_refuses_without_capability() {
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'delete_option' )->never();
		Functions\when( 'wp_die' )->alias(
			function () {
				throw new \RuntimeException( 'died' );
			}
		);

		$this->expectExceptionMessage( 'died' );
		$this->ajax->clear_log();
	}
}
