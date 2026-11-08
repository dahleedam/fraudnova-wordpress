<?php
/**
 * Orders list column tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Order_List_Column;
use FraudNova\WordPress\Order_Assessment;
use FraudNova\WordPress\Order_Meta;

/**
 * Tests for Admin\Order_List_Column.
 */
class OrderListColumnTest extends TestCase {

	private function render( $column, $order ): string {
		ob_start();
		( new Order_List_Column() )->render_column( $column, $order );
		return (string) ob_get_clean();
	}

	public function test_add_column_inserts_after_order_status() {
		$cols = ( new Order_List_Column() )->add_column( array( 'cb' => '', 'order_status' => 'Status', 'order_total' => 'Total' ) );
		$this->assertSame( array( 'cb', 'order_status', 'fraudnova_risk', 'order_total' ), array_keys( $cols ) );
		$this->assertSame( 'Risk', $cols['fraudnova_risk'] );
	}

	public function test_add_column_appends_when_status_missing() {
		$cols = ( new Order_List_Column() )->add_column( array( 'cb' => '', 'order_total' => 'Total' ) );
		$this->assertSame( array( 'cb', 'order_total', 'fraudnova_risk' ), array_keys( $cols ) );
	}

	public function test_render_ignores_other_columns() {
		$this->assertSame( '', $this->render( 'order_total', new \WC_Order( 1 ) ) );
	}

	public function test_render_unchecked_order_object() {
		$this->assertStringContainsString( 'is-none', $this->render( 'fraudnova_risk', new \WC_Order( 1 ) ) );
	}

	public function test_render_checked_order_by_id() {
		$order = new \WC_Order( 4 );
		Order_Meta::write( $order, Order_Assessment::from_meta( array( 'level' => 'review', 'score' => 55 ) ), array() );
		Functions\when( 'wc_get_order' )->justReturn( $order );
		$html = $this->render( 'fraudnova_risk', 4 );
		$this->assertStringContainsString( 'Review · 55', $html );
	}

	public function test_render_missing_order_prints_nothing() {
		Functions\when( 'wc_get_order' )->justReturn( false );
		$this->assertSame( '', $this->render( 'fraudnova_risk', 99 ) );
	}
}
