<?php
/**
 * Privacy tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Privacy;

/**
 * Tests for the Privacy module.
 */
class PrivacyTest extends TestCase {

	public function test_hooks_are_added() {
		$added = array();
		Functions\when( 'add_action' )->alias(
			function ( $hook, $cb, $prio = 10, $args = 1 ) use ( &$added ) {
				$added[ $hook ] = array( $prio, $args );
			}
		);
		( new Privacy( Plugin::instance() ) )->register();
		$this->assertArrayHasKey( 'admin_init', $added );
		$this->assertSame( array( 10, 1 ), $added['woocommerce_privacy_remove_order_personal_data'] );
	}

	public function test_erasure_deletes_meta_and_saves() {
		$order = new \WC_Order( 5, array( 'meta' => array( '_fraudnova_score' => 80, 'other' => 'x' ) ) );
		( new Privacy( Plugin::instance() ) )->erase_order( $order );
		$this->assertArrayNotHasKey( '_fraudnova_score', $order->meta );
		$this->assertSame( 1, $order->saves );
	}

	public function test_erasure_ignores_non_orders() {
		( new Privacy( Plugin::instance() ) )->erase_order( 'nope' );
		$this->addToAssertionCount( 1 );
	}

	public function test_registers_policy_content_when_available() {
		$captured = array();
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'wp_add_privacy_policy_content' )->alias(
			function ( $name, $text ) use ( &$captured ) {
				$captured = array( $name, $text );
			}
		);
		( new Privacy( Plugin::instance() ) )->add_policy_content();
		$this->assertSame( 'FraudNova', $captured[0] );
		$this->assertStringContainsString( 'fraudnova.com', $captured[1] );
	}

	public function test_policy_text_content() {
		$text = Privacy::policy_text();
		$this->assertStringContainsString( 'https://fraudnova.com/privacy', $text );
		$this->assertStringContainsString( 'https://fraudnova.com/terms', $text );
		$this->assertStringContainsString( 'IP address', $text );
		$this->assertStringContainsString( 'phone number', $text );
		$this->assertStringContainsString( 'activity log', $text );
	}
}
