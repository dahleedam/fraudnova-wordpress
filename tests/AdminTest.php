<?php
/**
 * Admin bootstrap tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Admin;
use FraudNova\WordPress\Plugin;

/**
 * Tests for Admin\Admin.
 */
class AdminTest extends TestCase {

	public function test_screen_ids_cover_settings_and_both_order_screens() {
		Functions\when( 'wc_get_page_screen_id' )->justReturn( 'woocommerce_page_wc-orders' );
		$ids = ( new Admin( Plugin::instance() ) )->screen_ids();
		$this->assertContains( 'settings_page_fraudnova', $ids );
		$this->assertContains( 'shop_order', $ids );
		$this->assertContains( 'edit-shop_order', $ids );
		$this->assertContains( 'woocommerce_page_wc-orders', $ids );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
	}
}
