<?php
/**
 * API client tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Api_Client;
use FraudNova\WordPress\Settings;
use Mockery;

/**
 * Tests for the API client and response mapping.
 */
class ApiClientTest extends TestCase {

	/**
	 * Stub the WordPress HTTP helpers and URL building.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'add_query_arg' )->alias(
			function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( '7.1' );
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
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			function ( $r, $name ) {
				return $r['headers'][ $name ] ?? '';
			}
		);
	}

	/**
	 * Build a client with a key (or without).
	 *
	 * @param string $key API key.
	 * @return Api_Client
	 */
	private function client( string $key = 'fn_test' ): Api_Client {
		$settings = Mockery::mock( Settings::class );
		$settings->shouldReceive( 'api_key' )->andReturn( $key );
		$settings->shouldReceive( 'has_api_key' )->andReturn( '' !== $key );
		return new Api_Client( $settings );
	}

	/**
	 * Build a fake wp_remote_get response.
	 *
	 * @param int    $code    HTTP status.
	 * @param string $body    Body.
	 * @param array  $headers Headers.
	 * @return array
	 */
	private function response( int $code, string $body, array $headers = array() ): array {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => $body,
			'headers'  => $headers,
		);
	}

	public function test_check_ip_sends_bearer_header_and_query() {
		Functions\expect( 'wp_remote_get' )->once()->with(
			Mockery::on(
				function ( $url ) {
					return 0 === strpos( $url, 'https://fraudnova.com/api/v1/check?' )
						&& false !== strpos( $url, 'ip=8.8.8.8&strictness=1' );
				}
			),
			Mockery::on(
				function ( $args ) {
					return 'Bearer fn_test' === $args['headers']['Authorization']
						&& 'application/json' === $args['headers']['Accept']
						&& 1 === preg_match( '/^FraudNova-WordPress\/1\.0\.0 \(WordPress\/7\.1; WooCommerce\/none\)$/', $args['headers']['User-Agent'] )
						&& 5 === $args['timeout']
						&& 0 === $args['redirection']
						&& true === $args['sslverify'];
				}
			)
		)->andReturn( $this->response( 200, '{"success":true,"data":{},"credits_remaining":4}' ) );

		$this->assertTrue( $this->client()->check_ip( '8.8.8.8', array( 'strictness' => 1 ) )->ok );
	}

	public function test_success_envelope_is_parsed() {
		Functions\when( 'wp_remote_get' )->justReturn(
			$this->response( 200, '{"success":true,"data":{"fraud_score":20},"credits_remaining":4}' )
		);
		$r = $this->client()->check_email( 'a@b.com' );
		$this->assertTrue( $r->ok );
		$this->assertSame( 200, $r->status );
		$this->assertSame( 20, $r->data['fraud_score'] );
		$this->assertSame( 4, $r->credits_remaining );
		$this->assertSame( '', $r->error_code );
	}

	/**
	 * @return array
	 */
	public function status_provider(): array {
		return array(
			array( 401, 'unauthorized' ),
			array( 402, 'insufficient_credits' ),
			array( 403, 'forbidden' ),
			array( 422, 'invalid_input' ),
			array( 429, 'rate_limited' ),
			array( 503, 'upstream' ),
			array( 500, 'upstream' ),
		);
	}

	/**
	 * @dataProvider status_provider
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Expected code.
	 */
	public function test_each_error_status_maps_to_a_code( int $status, string $code ) {
		Functions\when( 'wp_remote_get' )->justReturn(
			$this->response( $status, '{"success":false,"message":"Nope."}' )
		);
		$r = $this->client()->balance();
		$this->assertFalse( $r->ok );
		$this->assertSame( $status, $r->status );
		$this->assertSame( $code, $r->error_code );
		$this->assertSame( 'Nope.', $r->message );
	}

	public function test_html_error_pages_still_map_by_status() {
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 429, '<html>slow down</html>' ) );
		$this->assertSame( 'rate_limited', $this->client()->balance()->error_code );
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 502, '<html>bad</html>' ) );
		$this->assertSame( 'upstream', $this->client()->balance()->error_code );
	}

	public function test_retry_after_header_is_read_on_429() {
		Functions\when( 'wp_remote_get' )->justReturn(
			$this->response( 429, '{"success":false,"message":"Slow"}', array( 'retry-after' => '7' ) )
		);
		$r = $this->client()->balance();
		$this->assertSame( 7, $r->retry_after );
	}

	public function test_transport_error_maps_to_transport() {
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http_request_failed', 'timed out' ) );
		$r = $this->client()->balance();
		$this->assertFalse( $r->ok );
		$this->assertSame( 'transport', $r->error_code );
		$this->assertSame( 0, $r->status );
		$this->assertSame( 'timed out', $r->message );
	}

	/**
	 * @return array
	 */
	public function non_envelope_provider(): array {
		return array(
			array( '[]' ),
			array( '"ok"' ),
			array( '{"data":{"fraud_score":1}}' ),
		);
	}

	/**
	 * @dataProvider non_envelope_provider
	 *
	 * @param string $body Body.
	 */
	public function test_non_envelope_json_is_unexpected( string $body ) {
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 200, $body ) );
		$r = $this->client()->balance();
		$this->assertFalse( $r->ok );
		$this->assertSame( 'unexpected', $r->error_code );
		$this->assertSame( array(), $r->data );
	}

	public function test_html_body_is_unexpected() {
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 200, '<html>hi</html>' ) );
		$this->assertSame( 'unexpected', $this->client()->balance()->error_code );
	}

	public function test_no_api_key_short_circuits() {
		Functions\expect( 'wp_remote_get' )->never();
		$r = $this->client( '' )->check_ip( '8.8.8.8' );
		$this->assertFalse( $r->ok );
		$this->assertSame( 'no_api_key', $r->error_code );
	}

	public function test_phone_country_is_uppercased_and_omitted_when_empty() {
		$urls = array();
		Functions\when( 'wp_remote_get' )->alias(
			function ( $url ) use ( &$urls ) {
				$urls[] = $url;
				return $this->response( 200, '{"success":true,"data":{}}' );
			}
		);
		$client = $this->client();
		$client->check_phone( '+15551234567', ' us ' );
		$client->check_phone( '+15551234567' );
		$client->check_phone( '+15551234567', 'usa' );
		$this->assertStringContainsString( 'country=US', $urls[0] );
		$this->assertStringNotContainsString( 'country', $urls[1] );
		$this->assertStringNotContainsString( 'country', $urls[2] );
	}

	public function test_balance_hits_balance_path() {
		Functions\expect( 'wp_remote_get' )->once()->with(
			Mockery::on(
				function ( $url ) {
					return 0 === strpos( $url, 'https://fraudnova.com/api/v1/balance' );
				}
			),
			Mockery::any()
		)->andReturn( $this->response( 200, '{"success":true,"credits_remaining":9}' ) );
		$r = $this->client()->balance();
		$this->assertSame( 9, $r->credits_remaining );
	}
}
