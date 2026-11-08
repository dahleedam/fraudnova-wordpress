<?php
/**
 * Scorer tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Activity_Log;
use FraudNova\WordPress\Api_Client;
use FraudNova\WordPress\Api_Response;
use FraudNova\WordPress\Cache;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Error_State;
use FraudNova\WordPress\Low_Credit_Alert;
use FraudNova\WordPress\Scorer;
use FraudNova\WordPress\Settings;
use FraudNova\WordPress\Time_Budget;
use Mockery;

/**
 * Scorer behaviour.
 */
final class ScorerTest extends TestCase {

	/**
	 * Transient store.
	 *
	 * @var array
	 */
	private $store = array();

	/**
	 * Option store.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Mocked client.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $client;

	/**
	 * Mocked settings.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $settings;

	/**
	 * Scorer under test.
	 *
	 * @var Scorer
	 */
	private $scorer;

	protected function setUp(): void {
		parent::setUp();
		$this->store   = array();
		$this->options = array();
		$store         = &$this->store;
		$options       = &$this->options;
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}
		Functions\when( 'get_transient' )->alias(
			function ( $k ) use ( &$store ) {
				return isset( $store[ $k ] ) ? $store[ $k ] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $k, $v ) use ( &$store ) {
				$store[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( $k ) use ( &$store ) {
				unset( $store[ $k ] );
				return true;
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$options ) {
				return array_key_exists( $k, $options ) ? $options[ $k ] : $d;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $k, $v ) use ( &$options ) {
				$options[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $k ) use ( &$options ) {
				unset( $options[ $k ] );
				return true;
			}
		);
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );

		$this->client   = Mockery::mock( Api_Client::class );
		$this->settings = Mockery::mock( Settings::class );
		$this->settings->shouldReceive( 'has_api_key' )->andReturn( true )->byDefault();
		$this->settings->shouldReceive( 'get' )->with( 'strictness' )->andReturn( 1 )->byDefault();
		$this->client->shouldReceive( 'timeout' )->andReturn( 5 )->byDefault();
		$this->scorer = new Scorer( $this->client, new Cache(), $this->settings, new Activity_Log(), new Error_State() );
	}

	/**
	 * Successful response.
	 *
	 * @param array $data Data.
	 * @return Api_Response
	 */
	private function ok( array $data = array( 'fraud_score' => 40 ) ): Api_Response {
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( wp_json_encode( array( 'success' => true, 'data' => $data, 'credits_remaining' => 9 ) ) );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( '' );
		return Api_Response::from_wp( array() );
	}

	/**
	 * Error response by status.
	 *
	 * @param int $status Status.
	 * @param int $retry  Retry-After.
	 * @return Api_Response
	 */
	private function failing( int $status, int $retry = 0 ): Api_Response {
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $status );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( wp_json_encode( array( 'success' => false, 'message' => 'nope' ) ) );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( $retry ? (string) $retry : '' );
		return Api_Response::from_wp( array() );
	}

	public function test_empty_value_is_skipped_without_http(): void {
		$this->client->shouldNotReceive( 'check_ip' );
		$r = $this->scorer->score( 'ip', '  ' );
		$this->assertSame( Check_Result::STATUS_SKIPPED, $r->status );
		$this->assertSame( 'empty', $r->message );
	}

	public function test_private_ip_is_skipped(): void {
		$this->client->shouldNotReceive( 'check_ip' );
		$r = $this->scorer->score( 'ip', '192.168.1.5' );
		$this->assertSame( 'private_address', $r->message );
	}

	public function test_missing_api_key_is_an_error_without_http(): void {
		$this->settings->shouldReceive( 'has_api_key' )->andReturn( false );
		$this->client->shouldNotReceive( 'check_ip' );
		$r = $this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertSame( 'no_api_key', $r->error_code );
	}

	public function test_cache_hit_skips_http_and_reports_cached(): void {
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->ok() );
		$first = $this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertSame( Check_Result::STATUS_OK, $first->status );
		$second = $this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertSame( Check_Result::STATUS_CACHED, $second->status );
		$this->assertSame( 40, $second->score );
	}

	public function test_bypass_cache_calls_api(): void {
		$this->client->shouldReceive( 'check_ip' )->twice()->andReturn( $this->ok() );
		$this->scorer->score( 'ip', '8.8.8.8' );
		$r = $this->scorer->score( 'ip', '8.8.8.8', array( 'bypass_cache' => true ) );
		$this->assertSame( Check_Result::STATUS_OK, $r->status );
	}

	public function test_success_is_cached(): void {
		$this->client->shouldReceive( 'check_email' )->once()->with( 'a@b.com' )->andReturn( $this->ok() );
		$this->scorer->score( 'email', 'a@b.com' );
		$this->assertNotNull( ( new Cache() )->get( 'email', 'a@b.com' ) );
	}

	public function test_strictness_option_is_passed(): void {
		$this->client->shouldReceive( 'check_ip' )->once()->with( '8.8.8.8', array( 'strictness' => 2 ) )->andReturn( $this->ok() );
		$r = $this->scorer->score( 'ip', '8.8.8.8', array( 'strictness' => 2 ) );
		$this->assertSame( Check_Result::STATUS_OK, $r->status );
	}

	public function test_time_budget_exhausted_skips(): void {
		$this->client->shouldNotReceive( 'check_ip' );
		$budget = new Time_Budget(
			1.0,
			function () {
				static $n = 0;
				return $n++ ? 10.0 : 0.0;
			}
		);
		$r = $this->scorer->score( 'ip', '8.8.8.8', array( 'budget' => $budget ) );
		$this->assertSame( 'time_budget', $r->message );
	}

	public function test_429_sets_backoff_from_retry_after_and_later_calls_skip_http(): void {
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->failing( 429, 120 ) );
		$first = $this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertSame( 'rate_limited', $first->error_code );
		$this->assertLessThanOrEqual( 60, ( new Cache() )->backoff_remaining() );
		$this->assertGreaterThan( 0, ( new Cache() )->backoff_remaining() );
		$second = $this->scorer->score( 'ip', '1.1.1.1' );
		$this->assertSame( 'rate_limited', $second->error_code );
	}

	public function test_401_records_error_state(): void {
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->failing( 401 ) );
		$r = $this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertSame( 'unauthorized', $r->error_code );
		$this->assertTrue( ( new Error_State() )->is( 'unauthorized' ) );
	}

	public function test_402_records_error_state(): void {
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->failing( 402 ) );
		$this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertTrue( ( new Error_State() )->is( 'insufficient_credits' ) );
	}

	/**
	 * A scorer that emails on an empty balance.
	 *
	 * @return Scorer
	 */
	private function alerting_scorer(): Scorer {
		$this->options['admin_email'] = 'owner@shop.test';
		return new Scorer( $this->client, new Cache(), $this->settings, new Activity_Log(), new Error_State(), new Low_Credit_Alert( $this->settings ) );
	}

	public function test_402_emails_the_admin_once_a_day(): void {
		$this->settings->shouldReceive( 'get' )->with( 'low_credit_email' )->andReturn( true );
		$this->client->shouldReceive( 'check_ip' )->twice()->andReturn( $this->failing( 402 ) );
		Functions\expect( 'wp_mail' )->once()->with(
			'owner@shop.test',
			'FraudNova: your account is out of credits',
			Mockery::on(
				function ( $body ) {
					return false !== strpos( $body, 'https://fraudnova.com/dashboard/billing' );
				}
			)
		)->andReturn( true );

		$scorer = $this->alerting_scorer();
		$scorer->score( 'ip', '8.8.8.8' );
		$scorer->score( 'ip', '8.8.4.4' );

		$this->assertArrayHasKey( 'fraudnova_low_credit_mailed', $this->store );
	}

	public function test_402_sends_no_email_when_the_setting_is_off(): void {
		$this->settings->shouldReceive( 'get' )->with( 'low_credit_email' )->andReturn( false );
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->failing( 402 ) );
		Functions\expect( 'wp_mail' )->never();
		$this->alerting_scorer()->score( 'ip', '8.8.8.8' );
		$this->assertArrayNotHasKey( 'fraudnova_low_credit_mailed', $this->store );
	}

	public function test_other_errors_send_no_email(): void {
		$this->settings->shouldReceive( 'get' )->with( 'low_credit_email' )->andReturn( true );
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->failing( 401 ) );
		Functions\expect( 'wp_mail' )->never();
		$this->assertSame( 'unauthorized', $this->alerting_scorer()->score( 'ip', '8.8.8.8' )->error_code );
		$this->assertArrayNotHasKey( 'fraudnova_low_credit_mailed', $this->store );
	}

	public function test_success_clears_credential_error_state(): void {
		( new Error_State() )->set( 'unauthorized', 'x' );
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->ok() );
		$this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertNull( ( new Error_State() )->get() );
	}

	public function test_success_keeps_unrelated_error_state(): void {
		( new Error_State() )->set( 'something_else', 'x' );
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->ok() );
		$this->scorer->score( 'ip', '8.8.8.8' );
		$this->assertTrue( ( new Error_State() )->is( 'something_else' ) );
	}

	public function test_phone_country_is_uppercased(): void {
		$this->client->shouldReceive( 'check_phone' )->once()->with( '03001234567', 'PK' )->andReturn( $this->ok() );
		$r = $this->scorer->score( 'phone', '0300 1234567', array( 'country' => 'pk' ) );
		$this->assertSame( Check_Result::STATUS_OK, $r->status );
	}

	public function test_phone_is_sent_as_digits_with_one_leading_plus(): void {
		$this->client->shouldReceive( 'check_phone' )->once()->with( '+923001234567', '' )->andReturn( $this->ok() );
		$this->client->shouldReceive( 'check_phone' )->once()->with( '18176791808', '' )->andReturn( $this->ok() );
		$this->assertSame( Check_Result::STATUS_OK, $this->scorer->score( 'phone', ' +92 (300) 123.45-67 ' )->status );
		$this->assertSame( Check_Result::STATUS_OK, $this->scorer->score( 'phone', '1 (817) +679.1808' )->status );
	}

	public function test_phone_without_digits_is_skipped_without_http(): void {
		$this->client->shouldNotReceive( 'check_phone' );
		$r = $this->scorer->score( 'phone', '(...)' );
		$this->assertSame( Check_Result::STATUS_SKIPPED, $r->status );
	}

	public function test_other_errors_carry_response_code(): void {
		$this->client->shouldReceive( 'check_email' )->once()->andReturn( $this->failing( 503 ) );
		$r = $this->scorer->score( 'email', 'a@b.com' );
		$this->assertSame( 'upstream', $r->error_code );
	}

	public function test_timeout_cap_is_applied_and_removed(): void {
		$added = null;
		Functions\when( 'add_filter' )->alias(
			function ( $tag, $cb ) use ( &$added ) {
				$added = $cb;
				return true;
			}
		);
		$removed = false;
		Functions\when( 'remove_filter' )->alias(
			function () use ( &$removed ) {
				$removed = true;
				return true;
			}
		);
		$this->client->shouldReceive( 'check_ip' )->once()->andReturn( $this->ok() );
		$now    = 0.0;
		$budget = new Time_Budget(
			2.5,
			function () use ( &$now ) {
				return $now;
			}
		);
		$this->scorer->score( 'ip', '8.8.8.8', array( 'budget' => $budget ) );
		$this->assertSame( 2, $added() );
		$this->assertTrue( $removed );
	}

	public function test_balance_ignores_backoff(): void {
		( new Cache() )->set_backoff( 30 );
		$this->client->shouldReceive( 'balance' )->once()->andReturn( $this->ok( array() ) );
		$r = $this->scorer->balance();
		$this->assertTrue( $r->ok );
		$this->assertSame( 9, $r->credits_remaining );
	}

	public function test_balance_401_records_error_state(): void {
		$this->client->shouldReceive( 'balance' )->once()->andReturn( $this->failing( 401 ) );
		$this->scorer->balance();
		$this->assertTrue( ( new Error_State() )->is( 'unauthorized' ) );
	}
}
