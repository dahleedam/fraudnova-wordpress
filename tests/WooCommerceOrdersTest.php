<?php
/**
 * WooCommerce order integration tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FraudNova\WordPress\Activity_Log;
use FraudNova\WordPress\Api_Client;
use FraudNova\WordPress\Cache;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Error_State;
use FraudNova\WordPress\Integrations\Woocommerce_Orders;
use FraudNova\WordPress\Order_Meta;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Scorer;
use FraudNova\WordPress\Settings;
use FraudNova\WordPress\Time_Budget;
use Mockery;

/**
 * Tests for Woocommerce_Orders.
 */
class WooCommerceOrdersTest extends TestCase {

	/**
	 * Settings option value.
	 *
	 * @var array
	 */
	private $settings_option = array();

	/**
	 * Other options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Mocked scorer.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $scorer;

	/**
	 * Integration under test.
	 *
	 * @var Woocommerce_Orders
	 */
	private $orders;

	protected function setUp(): void {
		parent::setUp();
		$this->settings_option = array(
			'test_mode' => false,
			'api_key'   => 'fn_test',
		);
		$this->options         = array();
		$settings              = &$this->settings_option;
		$options               = &$this->options;
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$settings, &$options ) {
				if ( Settings::OPTION === $k ) {
					return $settings;
				}
				return array_key_exists( $k, $options ) ? $options[ $k ] : $d;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $k, $v ) use ( &$options ) {
				$options[ $k ] = $v;
				return true;
			}
		);

		$this->scorer = Mockery::mock( Scorer::class );
		$this->orders = new Woocommerce_Orders( Plugin::instance() );
		$this->orders->set_scorer( $this->scorer );
	}

	/**
	 * A COD order with full billing data.
	 *
	 * @param array $props Overrides.
	 * @return \WC_Order
	 */
	private function order( array $props = array() ): \WC_Order {
		return new \WC_Order(
			42,
			array_merge(
				array(
					'payment_method'       => 'cod',
					'payment_method_title' => 'Cash on delivery',
					'customer_ip_address'  => '8.8.8.8',
					'billing_phone'        => '+923001234567',
					'billing_country'      => 'PK',
					'billing_email'        => 'buyer@example.com',
				),
				$props
			)
		);
	}

	/**
	 * Expect the three standard checks with a risky phone.
	 *
	 * @param bool $bypass Expected bypass_cache option.
	 * @return void
	 */
	private function expect_risky_checks( bool $bypass = false ): void {
		$opts = Mockery::on(
			function ( $o ) use ( $bypass ) {
				return isset( $o['budget'] ) && $o['budget'] instanceof Time_Budget && $bypass === $o['bypass_cache'];
			}
		);
		$this->scorer->shouldReceive( 'score' )->once()->ordered()->with( 'ip', '8.8.8.8', $opts )
			->andReturn( Check_Result::from_api( 'ip', array( 'fraud_score' => 20 ) ) );
		$this->scorer->shouldReceive( 'score' )->once()->ordered()->with(
			'phone',
			'+923001234567',
			Mockery::on(
				function ( $o ) use ( $bypass ) {
					return 'PK' === $o['country'] && $bypass === $o['bypass_cache'];
				}
			)
		)->andReturn( Check_Result::from_api( 'phone', array( 'fraud_score' => 30, 'valid' => false ) ) );
		$this->scorer->shouldReceive( 'score' )->once()->ordered()->with( 'email', 'buyer@example.com', $opts )
			->andReturn( Check_Result::from_api( 'email', array( 'fraud_score' => 10 ) ) );
	}

	public function test_registers_the_five_hooks() {
		Actions\expectAdded( 'woocommerce_checkout_order_processed' )->once()->with( array( $this->orders, 'handle_classic' ), 10, 3 );
		Actions\expectAdded( 'woocommerce_store_api_checkout_order_processed' )->once()->with( array( $this->orders, 'handle_store_api' ), 10, 1 );
		Filters\expectAdded( 'woocommerce_cod_process_payment_order_status' )->once()->with( array( $this->orders, 'cod_status' ), 10, 2 );
		Actions\expectAdded( 'woocommerce_order_status_changed' )->once()->with( array( $this->orders, 'on_status_changed' ), 10, 4 );
		Filters\expectAdded( 'woocommerce_email_enabled_customer_on_hold_order' )->once()->with( array( $this->orders, 'hold_email_enabled' ), 10, 2 );
		$this->orders->register();
		$this->assertNotFalse( has_action( 'woocommerce_order_status_changed', array( $this->orders, 'on_status_changed' ) ) );
	}

	public function test_scores_cod_order_and_writes_meta_note_and_log() {
		$this->expect_risky_checks();
		$order = $this->order();
		$a     = $this->orders->score_order( $order );

		$this->assertSame( 'high', $a->level );
		$this->assertSame( 'hold', $a->action );
		$this->assertSame( 'hold', $order->get_meta( Order_Meta::ACTION_PENDING ) );
		$this->assertSame( 30, $order->get_meta( Order_Meta::SCORE ) );
		$this->assertCount( 1, $order->notes );
		$this->assertStringContainsString( 'Phone number invalid', $order->notes[0][0] );
		$this->assertGreaterThanOrEqual( 2, $order->saves );
		$this->assertSame( array(), $order->status_changes );

		$log = ( new Activity_Log() )->all();
		$this->assertCount( 1, $log );
		$this->assertSame( 'order', $log[0]['source'] );
		$this->assertSame( 'b***@example.com', $log[0]['subject'] );
		$this->assertSame( 30, $log[0]['score'] );
		$this->assertSame( 'high', $log[0]['level'] );
		$this->assertSame( 'hold', $log[0]['action'] );
		$this->assertSame( 42, $log[0]['order_id'] );
	}

	public function test_second_hook_firing_does_not_rescore() {
		$this->expect_risky_checks();
		$order = $this->order();
		$this->orders->handle_classic( 42, array(), $order );
		$this->orders->handle_store_api( $order );
		$this->assertCount( 1, $order->notes );
	}

	public function test_non_cod_order_is_ignored_by_default() {
		$this->scorer->shouldNotReceive( 'score' );
		$order = $this->order(
			array(
				'payment_method'       => 'stripe',
				'payment_method_title' => 'Credit card',
			)
		);
		$this->assertNull( $this->orders->score_order( $order ) );
		$this->assertSame( array(), $order->meta );
		$this->assertSame( 0, $order->saves );
	}

	public function test_third_party_cod_gateway_matches_by_title() {
		$this->expect_risky_checks();
		$order = $this->order(
			array(
				'payment_method'       => 'advanced_cod',
				'payment_method_title' => 'Cash on Delivery (+fee)',
			)
		);
		$this->assertNotNull( $this->orders->score_order( $order ) );
		$this->assertTrue( Woocommerce_Orders::looks_like_cod( 'pay_later', 'Cash-on-delivery' ) );
		$this->assertFalse( Woocommerce_Orders::looks_like_cod( 'stripe', 'Credit card' ) );
		$this->assertTrue( Woocommerce_Orders::looks_like_cod( 'cod', '' ) );
		$this->assertTrue( Woocommerce_Orders::looks_like_cod( 'cod_fee', 'Pay later' ) );
		$this->assertFalse( Woocommerce_Orders::looks_like_cod( 'qr_pay', 'QR code payment' ) );
		$this->assertFalse( Woocommerce_Orders::looks_like_cod( 'barcode', 'Barcode' ) );
		$this->assertFalse( Woocommerce_Orders::looks_like_cod( 'promo', 'Promo code' ) );
	}

	public function test_all_setting_scores_any_gateway() {
		$this->settings_option['order_payment_methods'] = 'all';
		$this->expect_risky_checks();
		$order = $this->order(
			array(
				'payment_method'       => 'stripe',
				'payment_method_title' => 'Credit card',
			)
		);
		$this->assertNotNull( $this->orders->score_order( $order ) );

		$this->settings_option['order_payment_methods'] = array( 'bacs' );
		$this->assertFalse( $this->orders->applies_to( $this->order() ) );
		$this->assertTrue( $this->orders->applies_to( $this->order( array( 'payment_method' => 'bacs' ) ) ) );

		$this->settings_option['orders_enabled'] = false;
		$this->assertFalse( $this->orders->applies_to( $this->order( array( 'payment_method' => 'bacs' ) ) ) );
	}

	public function test_private_ip_and_empty_contacts_spend_nothing() {
		$client = Mockery::mock( Api_Client::class );
		$client->shouldNotReceive( 'check_ip', 'check_email', 'check_phone', 'balance', 'timeout' );
		$plugin = Plugin::instance();
		$this->orders->set_scorer( new Scorer( $client, new Cache(), $plugin->settings(), new Activity_Log(), new Error_State() ) );

		$order = $this->order(
			array(
				'customer_ip_address' => '192.168.1.10',
				'billing_phone'       => '',
				'billing_email'       => '',
			)
		);
		$a = $this->orders->score_order( $order );

		$this->assertSame( 'unknown', $a->level );
		$this->assertSame( 'none', $a->action );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertSame( 'skipped', $order->get_meta( Order_Meta::RESULT_IP )['status'] );
		$this->assertCount( 1, $order->notes );
	}

	public function test_cod_status_filter_returns_hold_and_marks_applied() {
		$order = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'hold' ) ) );
		$this->assertSame( 'on-hold', $this->orders->cod_status( 'processing', $order ) );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertNotSame( '', $order->get_meta( Order_Meta::ACTION_APPLIED ) );
		$this->assertSame( 1, $order->saves );

		$cancel = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'cancel' ) ) );
		$this->assertSame( 'cancelled', $this->orders->cod_status( 'processing', $cancel ) );
	}

	public function test_cod_status_filter_leaves_clean_orders() {
		$order = $this->order();
		$this->assertSame( 'processing', $this->orders->cod_status( 'processing', $order ) );
		$this->assertSame( 'on-hold', $this->orders->cod_status( 'on-hold', $order ) );
		$this->assertSame( array(), $order->meta );
		$this->assertSame( 0, $order->saves );
	}

	public function test_status_changed_applies_pending_hold_once() {
		$order = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'hold' ) ) );
		$this->orders->on_status_changed( 42, 'pending', 'processing', $order );
		$this->assertCount( 1, $order->status_changes );
		$this->assertSame( 'on-hold', $order->status_changes[0][0] );
		$this->assertNotSame( '', $order->get_meta( Order_Meta::ACTION_APPLIED ) );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );

		$this->orders->on_status_changed( 42, 'on-hold', 'processing', $order );
		$this->assertCount( 1, $order->status_changes );
	}

	public function test_status_changed_loads_missing_order_and_cancels() {
		$order = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'cancel' ) ) );
		Functions\expect( 'wc_get_order' )->once()->with( 42 )->andReturn( $order );
		$this->orders->on_status_changed( 42, 'pending', 'completed', null );
		$this->assertCount( 1, $order->status_changes );
		$this->assertSame( 'cancelled', $order->status_changes[0][0] );
	}

	public function test_gateway_on_hold_consumes_pending_cancel_and_later_approval_is_untouched() {
		$order = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'cancel', Order_Meta::ACTION => 'cancel' ) ) );
		$this->orders->on_status_changed( 42, 'pending', 'on-hold', $order );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertSame( 'flag', $order->get_meta( Order_Meta::ACTION ) );
		$this->assertStringContainsString( 'hold not applied', $order->notes[0][0] );
		$this->assertStringContainsString( 'on-hold', $order->notes[0][0] );

		$this->orders->on_status_changed( 42, 'on-hold', 'processing', $order );
		$this->assertSame( array(), $order->status_changes );
	}

	public function test_gateway_setting_the_target_marks_applied() {
		$order = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'hold', Order_Meta::ACTION => 'hold' ) ) );
		$this->orders->on_status_changed( 42, 'pending', 'on-hold', $order );
		$this->assertSame( array(), $order->status_changes );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertNotSame( '', $order->get_meta( Order_Meta::ACTION_APPLIED ) );
		$this->assertSame( 'hold', $order->get_meta( Order_Meta::ACTION ) );
		$this->assertSame( array(), $order->notes );
	}

	public function test_later_manual_transition_never_applies_pending() {
		$order = $this->order( array( 'meta' => array( Order_Meta::ACTION_PENDING => 'hold' ) ) );
		$this->orders->on_status_changed( 42, 'on-hold', 'processing', $order );
		$this->assertSame( array(), $order->status_changes );
	}

	public function test_card_payment_at_apply_time_is_flagged_only() {
		$order = $this->order(
			array(
				'transaction_id' => 'ch_9',
				'meta'           => array(
					Order_Meta::ACTION_PENDING => 'hold',
					Order_Meta::ACTION         => 'hold',
				),
			)
		);
		$this->orders->on_status_changed( 42, 'pending', 'processing', $order );
		$this->assertSame( array(), $order->status_changes );
		$this->assertSame( 'flag', $order->get_meta( Order_Meta::ACTION ) );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertStringContainsString( 'paid by card, flagged only', $order->notes[0][0] );
		$this->assertSame( 1, $order->saves );
	}

	public function test_scoring_error_adds_retry_note_and_never_throws() {
		$this->scorer->shouldReceive( 'score' )->andThrow( new \RuntimeException( 'boom' ) );
		$order = $this->order();
		$this->orders->handle_store_api( $order );
		$this->assertCount( 1, $order->notes );
		$this->assertStringContainsString( 'check failed (boom)', $order->notes[0][0] );
		$this->assertSame( 2, $order->saves );
	}

	public function test_paid_by_card_order_is_flagged_not_held() {
		$this->settings_option['order_payment_methods'] = 'all';
		$this->expect_risky_checks();
		$order = $this->order( array( 'transaction_id' => 'ch_1' ) );
		$a     = $this->orders->score_order( $order );
		$this->assertSame( 'flag', $a->action );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
	}

	public function test_recheck_bypasses_cache_and_never_changes_status() {
		$this->expect_risky_checks( true );
		$order = $this->order(
			array(
				'payment_method' => 'stripe',
				'status'         => 'processing',
				'meta'           => array(
					Order_Meta::CHECKED_AT     => '2026-11-01T00:00:00+00:00',
					Order_Meta::ACTION_APPLIED => '2026-11-01T00:00:01+00:00',
				),
			)
		);
		$a = $this->orders->score_order( $order, true );

		$this->assertSame( 'hold', $a->action );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertSame( '2026-11-01T00:00:01+00:00', $order->get_meta( Order_Meta::ACTION_APPLIED ) );
		$this->assertNotSame( '2026-11-01T00:00:00+00:00', $order->get_meta( Order_Meta::CHECKED_AT ) );
		$this->assertSame( array(), $order->status_changes );
		$this->assertSame( 'recheck', ( new Activity_Log() )->all()[0]['source'] );

		$this->orders->on_status_changed( 42, 'processing', 'completed', $order );
		$this->assertSame( array(), $order->status_changes );
	}

	public function test_hold_email_suppressed_only_for_held_orders() {
		$held = $this->order(
			array(
				'meta' => array(
					Order_Meta::ACTION         => 'hold',
					Order_Meta::ACTION_APPLIED => '2026-11-01T00:00:00+00:00',
				),
			)
		);
		$this->assertFalse( $this->orders->hold_email_enabled( true, $held ) );

		$not_applied = $this->order( array( 'meta' => array( Order_Meta::ACTION => 'hold' ) ) );
		$this->assertTrue( $this->orders->hold_email_enabled( true, $not_applied ) );
		$this->assertTrue( $this->orders->hold_email_enabled( true, $this->order() ) );
		$this->assertTrue( $this->orders->hold_email_enabled( true, null ) );

		$this->settings_option['suppress_hold_email'] = false;
		$this->assertTrue( $this->orders->hold_email_enabled( true, $held ) );
	}

	public function test_test_mode_writes_flag_and_no_pending_action() {
		$this->settings_option['test_mode'] = true;
		$this->expect_risky_checks();
		$order = $this->order();
		$a     = $this->orders->score_order( $order );

		$this->assertSame( 'flag', $a->action );
		$this->assertSame( 'hold', $order->get_meta( Order_Meta::REQUESTED_ACTION ) );
		$this->assertTrue( $order->get_meta( Order_Meta::TEST_MODE ) );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
		$this->assertStringContainsString( 'test mode', $order->notes[0][0] );
		$this->assertSame( 'processing', $this->orders->cod_status( 'processing', $order ) );
	}

	public function test_disabled_checks_are_absent() {
		$this->settings_option['check_ip']    = false;
		$this->settings_option['check_email'] = false;
		$this->scorer->shouldReceive( 'score' )->once()->with( 'phone', '+923001234567', Mockery::any() )
			->andReturn( Check_Result::from_api( 'phone', array( 'fraud_score' => 5 ) ) );
		$order = $this->order();
		$this->orders->score_order( $order );
		$this->assertArrayNotHasKey( Order_Meta::RESULT_IP, $order->meta );
		$this->assertArrayNotHasKey( Order_Meta::RESULT_EMAIL, $order->meta );
	}
}
