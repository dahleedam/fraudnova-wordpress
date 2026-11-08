<?php
/**
 * Client IP tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Client_Ip;
use FraudNova\WordPress\Settings;

/**
 * Client IP resolution.
 */
final class ClientIpTest extends TestCase {

	/**
	 * Saved $_SERVER.
	 *
	 * @var array
	 */
	private $server;

	protected function setUp(): void {
		parent::setUp();
		$this->server = $_SERVER;
		Functions\when( 'wp_unslash' )->alias( 'stripslashes' );
	}

	protected function tearDown(): void {
		$_SERVER = $this->server;
		parent::tearDown();
	}

	private function settings( string $source ): Settings {
		Functions\when( 'get_option' )->justReturn( array( 'ip_source' => $source ) );
		return new Settings();
	}

	public function test_remote_addr_ignores_forwarded_for(): void {
		$_SERVER['REMOTE_ADDR']          = '203.0.113.9';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
		$this->assertSame( '203.0.113.9', Client_Ip::resolve( $this->settings( 'remote_addr' ) ) );
	}

	public function test_woocommerce_prefers_order_ip(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$order                  = new \WC_Order( 1, array( 'customer_ip_address' => '198.51.100.7' ) );
		$this->assertSame( '198.51.100.7', Client_Ip::resolve( $this->settings( 'woocommerce' ), $order ) );
	}

	public function test_woocommerce_without_order_falls_back_to_remote_addr(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$this->assertSame( '203.0.113.9', Client_Ip::resolve( $this->settings( 'woocommerce' ) ) );
	}

	public function test_cloudflare_header_used_when_valid(): void {
		$_SERVER['REMOTE_ADDR']           = '172.16.0.1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8::5';
		$this->assertSame( '2001:db8::5', Client_Ip::resolve( $this->settings( 'cloudflare' ) ) );
	}

	public function test_cloudflare_invalid_header_falls_back(): void {
		$_SERVER['REMOTE_ADDR']           = '203.0.113.9';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
		$this->assertSame( '203.0.113.9', Client_Ip::resolve( $this->settings( 'cloudflare' ) ) );
	}

	public function test_invalid_everything_returns_empty(): void {
		$_SERVER['REMOTE_ADDR'] = 'garbage';
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		$this->assertSame( '', Client_Ip::resolve( $this->settings( 'remote_addr' ) ) );
		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertSame( '', Client_Ip::resolve( $this->settings( 'remote_addr' ) ) );
	}

	public function test_invalid_order_ip_returns_empty(): void {
		$order = new \WC_Order( 1, array( 'customer_ip_address' => 'nope' ) );
		$this->assertSame( '', Client_Ip::resolve( $this->settings( 'woocommerce' ), $order ) );
	}

	public function test_filter_can_override(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return 'fraudnova_client_ip' === $tag ? '192.0.2.1' : $value;
			}
		);
		$this->assertSame( '192.0.2.1', Client_Ip::resolve( $this->settings( 'remote_addr' ) ) );
	}
}
