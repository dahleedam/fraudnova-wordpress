<?php
/**
 * Order meta tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Order_Assessment;
use FraudNova\WordPress\Order_Meta;
use FraudNova\WordPress\Settings;

/**
 * Tests for Order_Meta.
 */
class OrderMetaTest extends TestCase {

	/**
	 * A high-risk assessment with phone and email results.
	 *
	 * @param array $overrides Settings overrides.
	 * @return array{0: Order_Assessment, 1: array}
	 */
	private function assessed( array $overrides = array() ): array {
		Functions\when( 'get_option' )->justReturn( array_merge( array( 'test_mode' => false ), $overrides ) );
		$results = array(
			'phone' => Check_Result::from_api( 'phone', array( 'fraud_score' => 20, 'valid' => false ) ),
			'email' => Check_Result::from_api( 'email', array( 'fraud_score' => 10 ) ),
		);
		return array( Order_Assessment::evaluate( $results, new Settings() ), $results );
	}

	public function test_all_keys_lists_every_meta_key() {
		$keys = Order_Meta::all_keys();
		$this->assertCount( 13, $keys );
		foreach ( $keys as $key ) {
			$this->assertStringStartsWith( '_fraudnova_', $key );
		}
		$this->assertContains( Order_Meta::ACTION_PENDING, $keys );
		$this->assertContains( Order_Meta::RESULT_PHONE, $keys );
	}

	public function test_write_stores_assessment_and_results_without_saving() {
		list( $a, $results ) = $this->assessed();
		$order               = new \WC_Order( 7 );
		Order_Meta::write( $order, $a, $results );

		$this->assertSame( 0, $order->saves );
		$this->assertNotSame( '', $order->get_meta( Order_Meta::CHECKED_AT ) );
		$this->assertSame( FRAUDNOVA_VERSION, $order->get_meta( Order_Meta::VERSION ) );
		$this->assertSame( 20, $order->get_meta( Order_Meta::SCORE ) );
		$this->assertSame( 'high', $order->get_meta( Order_Meta::LEVEL ) );
		$this->assertSame( array( 'phone_invalid' ), $order->get_meta( Order_Meta::REASONS ) );
		$this->assertSame( 'hold', $order->get_meta( Order_Meta::ACTION ) );
		$this->assertSame( 'hold', $order->get_meta( Order_Meta::REQUESTED_ACTION ) );
		$this->assertSame( 'hold', $order->get_meta( Order_Meta::ACTION_PENDING ) );
		$this->assertSame( $results['phone']->to_array(), $order->get_meta( Order_Meta::RESULT_PHONE ) );
		$this->assertSame( '', $order->get_meta( Order_Meta::RESULT_IP ) );
	}

	public function test_write_clears_pending_for_non_destructive_actions() {
		list( $a, $results ) = $this->assessed( array( 'test_mode' => true ) );
		$order               = new \WC_Order( 7, array( 'meta' => array( Order_Meta::ACTION_PENDING => 'hold' ) ) );
		Order_Meta::write( $order, $a, $results );
		$this->assertSame( 'flag', $order->get_meta( Order_Meta::ACTION ) );
		$this->assertTrue( $order->get_meta( Order_Meta::TEST_MODE ) );
		$this->assertArrayNotHasKey( Order_Meta::ACTION_PENDING, $order->meta );
	}

	public function test_read_round_trips() {
		list( $a, $results ) = $this->assessed();
		$order               = new \WC_Order( 7 );
		$this->assertNull( Order_Meta::read( $order ) );
		Order_Meta::write( $order, $a, $results );

		$read = Order_Meta::read( $order );
		$this->assertSame( 'high', $read['assessment']->level );
		$this->assertSame( 20, $read['assessment']->score );
		$this->assertSame( array( 'phone_invalid' ), $read['assessment']->reasons );
		$this->assertSame( 'hold', $read['assessment']->action );
		$this->assertSame( array( 'phone', 'email' ), array_keys( $read['results'] ) );
		$this->assertSame( $results['email']->to_array(), $read['results']['email']->to_array() );
		$this->assertSame( $order->get_meta( Order_Meta::CHECKED_AT ), $read['checked_at'] );
	}

	public function test_mark_checking_sets_checked_and_saves() {
		$order = new \WC_Order( 7 );
		$this->assertFalse( Order_Meta::is_checked( $order ) );
		Order_Meta::mark_checking( $order );
		$this->assertTrue( Order_Meta::is_checked( $order ) );
		$this->assertSame( 1, $order->saves );
	}

	public function test_delete_all_removes_every_key() {
		list( $a, $results ) = $this->assessed();
		$order               = new \WC_Order( 7, array( 'meta' => array( '_other' => 'x' ) ) );
		Order_Meta::write( $order, $a, $results );
		Order_Meta::delete_all( $order );
		$this->assertSame( array( '_other' => 'x' ), $order->meta );
	}
}
