<?php
/**
 * Order meta box tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Order_Meta_Box;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Order_Assessment;
use FraudNova\WordPress\Order_Meta;

/**
 * Tests for Admin\Order_Meta_Box.
 */
class OrderMetaBoxTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'admin_url' )->alias(
			function ( $p = '' ) {
				return 'https://example.test/wp-admin/' . $p;
			}
		);
		Functions\when( 'wp_date' )->alias(
			function ( $f, $ts = null ) {
				return 'DATE ' . gmdate( 'Y-m-d H:i', (int) $ts );
			}
		);
		Functions\when( 'wp_nonce_field' )->alias(
			function ( $action ) {
				echo '<input type="hidden" name="_wpnonce" value="NONCE:' . $action . '" />';
			}
		);
	}

	private function box(): Order_Meta_Box {
		return new Order_Meta_Box();
	}

	private function html( $order ): string {
		ob_start();
		$this->box()->render( $order );
		return (string) ob_get_clean();
	}

	public function test_pill_renders_each_level_escaped() {
		$this->assertSame( '<span class="fraudnova-pill is-clean">Clean · 12</span>', Order_Meta_Box::pill( 'clean', 12 ) );
		$this->assertSame( '<span class="fraudnova-pill is-review">Review</span>', Order_Meta_Box::pill( 'review', null ) );
		$this->assertSame( '<span class="fraudnova-pill is-high">High · 91</span>', Order_Meta_Box::pill( 'high', 91 ) );
		$this->assertSame( '<span class="fraudnova-pill is-unknown">Unknown</span>', Order_Meta_Box::pill( 'unknown', null ) );
		$this->assertSame( '<span class="fraudnova-pill is-none">Not checked</span>', Order_Meta_Box::pill( 'none', null ) );
		$this->assertStringNotContainsString( '<script', Order_Meta_Box::pill( '"><script>', 1 ) );
	}

	public function test_unchecked_order_shows_not_checked_nonce_and_check_now() {
		$html = $this->html( new \WC_Order( 7 ) );
		$this->assertStringContainsString( 'Not checked', $html );
		$this->assertStringContainsString( 'NONCE:fraudnova_recheck_7', $html );
		$this->assertStringContainsString( 'Check now (uses up to 3 credits)', $html );
		$this->assertStringContainsString( 'value="fraudnova_recheck"', $html );
		$this->assertStringContainsString( 'name="order_id" value="7"', $html );
		$this->assertStringContainsString( 'admin-post.php', $html );
	}

	public function test_checked_order_shows_score_reasons_flags_and_no_subject() {
		$order = new \WC_Order( 9, array( 'billing_email' => 'secret@example.com', 'billing_phone' => '+15551234567', 'customer_ip_address' => '203.0.113.9' ) );
		$ip    = Check_Result::from_api( 'ip', array( 'success' => true, 'fraud_score' => 88, 'proxy' => true, 'vpn' => false, 'active_vpn' => true, 'country_code' => 'US', 'city' => 'Austin' ) );
		$email = Check_Result::from_api( 'email', array( 'success' => true, 'fraud_score' => 10, 'valid' => false, 'disposable' => false ) );
		$phone = Check_Result::skipped( 'phone', 'private_address' );
		$bad   = Check_Result::error( 'phone', 'x', 'Upstream <b>down</b>' );

		$a = Order_Assessment::from_meta( array( 'level' => 'high', 'score' => 88, 'reasons' => array( 'ip_proxy', 'score_88' ), 'action' => 'flag' ) );
		Order_Meta::write( $order, $a, array( 'ip' => $ip, 'email' => $email, 'phone' => $phone ) );

		$html = $this->html( $order );
		$this->assertStringContainsString( 'is-high', $html );
		$this->assertStringContainsString( 'High · 88', $html );
		$this->assertStringContainsString( 'Checked', $html );
		$this->assertStringContainsString( 'Proxy, VPN or Tor', $html );
		$this->assertStringContainsString( 'Fraud score 88', $html );
		$this->assertStringContainsString( 'Proxy', $html );
		$this->assertStringContainsString( 'Active VPN', $html );
		$this->assertStringContainsString( 'Invalid', $html );
		$this->assertStringContainsString( 'US, Austin', $html );
		$this->assertStringContainsString( 'Skipped: private address', $html );
		$this->assertStringContainsString( 'Check again (uses up to 3 credits)', $html );
		$this->assertStringNotContainsString( 'secret@example.com', $html );
		$this->assertStringNotContainsString( '+15551234567', $html );
		$this->assertStringNotContainsString( '203.0.113.9', $html );

		$order->update_meta_data( Order_Meta::RESULT_PHONE, $bad->to_array() );
		$html = $this->html( $order );
		$this->assertStringContainsString( 'Error: Upstream &lt;b&gt;down&lt;/b&gt;', $html );
	}

	public function test_render_ignores_objects_without_order() {
		Functions\when( 'wc_get_order' )->justReturn( false );
		$this->assertSame( '', $this->html( (object) array( 'ID' => 5 ) ) );
		$this->assertSame( '', $this->html( (object) array() ) );
	}

	public function test_render_resolves_post_to_order() {
		Functions\when( 'wc_get_order' )->alias(
			function ( $id ) {
				return new \WC_Order( (int) $id );
			}
		);
		$this->assertStringContainsString( 'NONCE:fraudnova_recheck_5', $this->html( (object) array( 'ID' => 5 ) ) );
	}

	public function test_register_adds_meta_box_on_both_screens_deduped() {
		$calls = array();
		Functions\when( 'add_meta_box' )->alias(
			function () use ( &$calls ) {
				$calls[] = func_get_args();
			}
		);
		Functions\when( 'wc_get_page_screen_id' )->justReturn( 'woocommerce_page_wc-orders' );
		$box = $this->box();
		$box->add_meta_boxes();
		$this->assertCount( 1, $calls );
		$this->assertSame( 'fraudnova', $calls[0][0] );
		$this->assertSame( array( 'shop_order', 'woocommerce_page_wc-orders' ), $calls[0][3] );
		$this->assertSame( 'side', $calls[0][4] );
	}
}
