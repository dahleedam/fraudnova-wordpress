<?php
/**
 * Settings tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Settings;

/**
 * Tests for the Settings class.
 */
class SettingsTest extends TestCase {

	/**
	 * Stub the option store.
	 *
	 * @param mixed $stored Stored option value.
	 */
	private function stub_option( $stored ): void {
		Functions\when( 'get_option' )->justReturn( $stored );
	}

	/**
	 * Stub tag stripping.
	 */
	private function stub_strip(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
	}

	public function test_defaults_match_the_spec() {
		$expected = array(
			'api_key'                    => '',
			'test_mode'                  => true,
			'low_credit_email'           => true,
			'delete_on_uninstall'        => false,
			'orders_enabled'             => true,
			'order_payment_methods'      => null,
			'ip_source'                  => 'woocommerce',
			'check_ip'                   => true,
			'check_phone'                => true,
			'check_email'                => true,
			'strictness'                 => 1,
			'review_threshold'           => 60,
			'hold_threshold'             => 85,
			'rule_phone_invalid'         => 'high',
			'rule_phone_voip'            => 'review',
			'rule_email_disposable'      => 'review',
			'rule_ip_proxy'              => 'review',
			'rule_ip_abuse'              => 'review',
			'rule_email_abuse'           => 'review',
			'review_action'              => 'flag',
			'high_action'                => 'hold',
			'suppress_hold_email'        => true,
			'on_error'                   => 'allow',
			'form_wp_registration'       => true,
			'form_wc_registration'       => true,
			'form_cf7'                   => true,
			'form_check_ip'              => true,
			'form_check_email'           => true,
			'form_block_threshold'       => 85,
			'form_rule_email_disposable' => true,
			'form_rule_email_invalid'    => false,
			'form_rule_ip_proxy'         => false,
			'form_rule_ip_abuse'         => true,
			'form_block_message'         => 'Registration could not be completed. Please contact us if you believe this is a mistake.',
		);
		$this->assertSame( $expected, Settings::defaults() );
	}

	public function test_unknown_keys_are_dropped_and_missing_keys_default() {
		$this->stub_option(
			array(
				'strictness' => 2,
				'bogus'      => 'x',
			)
		);
		$all = ( new Settings() )->all();
		$this->assertSame( 2, $all['strictness'] );
		$this->assertArrayNotHasKey( 'bogus', $all );
		$this->assertSame( 60, $all['review_threshold'] );
		$this->assertSame( array_keys( Settings::defaults() ), array_keys( $all ) );
		$this->assertNull( ( new Settings() )->get( 'bogus' ) );
	}

	public function test_non_array_option_falls_back_to_defaults() {
		$this->stub_option( 'garbage' );
		$this->assertSame( Settings::defaults(), ( new Settings() )->all() );
	}

	public function test_sanitize_coerces_types_and_ranges() {
		$this->stub_strip();
		$out = ( new Settings() )->sanitize(
			array(
				'strictness'            => '7',
				'review_threshold'      => '150',
				'hold_threshold'        => '10',
				'review_action'         => 'cancel',
				'ip_source'             => 'evil',
				'api_key'               => ' fn_abc ',
				'order_payment_methods' => array( 'cod', 'bad<id>', '' ),
				'test_mode'             => '1',
				'form_block_message'    => '<b>x</b>',
			)
		);
		$this->assertSame( 3, $out['strictness'] );
		$this->assertSame( 100, $out['review_threshold'] );
		$this->assertSame( 100, $out['hold_threshold'] );
		$this->assertSame( 'flag', $out['review_action'] );
		$this->assertSame( 'woocommerce', $out['ip_source'] );
		$this->assertSame( 'fn_abc', $out['api_key'] );
		$this->assertSame( array( 'cod', 'badid' ), $out['order_payment_methods'] );
		$this->assertTrue( $out['test_mode'] );
		$this->assertSame( 'x', $out['form_block_message'] );
		$this->assertSame( array_keys( Settings::defaults() ), array_keys( $out ) );
	}

	public function test_hold_threshold_is_raised_to_review_threshold() {
		$this->stub_strip();
		$out = ( new Settings() )->sanitize(
			array(
				'review_threshold' => '70',
				'hold_threshold'   => '40',
			)
		);
		$this->assertSame( 70, $out['hold_threshold'] );
	}

	public function test_sanitize_unchecked_checkbox_is_false() {
		$this->stub_strip();
		$out = ( new Settings() )->sanitize( array( 'strictness' => '1' ) );
		$this->assertFalse( $out['test_mode'] );
		$this->assertFalse( $out['form_cf7'] );
		$out = ( new Settings() )->sanitize( array( 'form_cf7' => '0' ) );
		$this->assertFalse( $out['form_cf7'] );
	}

	public function test_sanitize_handles_non_array_input() {
		$this->stub_strip();
		$out = ( new Settings() )->sanitize( null );
		$this->assertFalse( $out['test_mode'] );
		$this->assertSame( 60, $out['review_threshold'] );
	}

	public function test_payment_methods_all_and_null() {
		$this->stub_strip();
		$s = new Settings();
		$this->assertSame( 'all', $s->sanitize( array( 'order_payment_methods' => 'all' ) )['order_payment_methods'] );
		$this->assertNull( $s->sanitize( array() )['order_payment_methods'] );
		$this->assertNull( $s->sanitize( array( 'order_payment_methods' => array() ) )['order_payment_methods'] );
		$this->assertSame( 'all', $s->sanitize( array( 'order_payment_methods' => array( 'cod', 'all' ) ) )['order_payment_methods'] );
	}

	public function test_api_key_constant_overrides_option() {
		$this->stub_option( array( 'api_key' => 'fn_option' ) );
		$plain = new Settings();
		$this->assertSame( 'fn_option', $plain->api_key() );
		$this->assertFalse( $plain->api_key_from_constant() );
		$this->assertTrue( $plain->has_api_key() );

		$over = new class() extends Settings {
			/**
			 * Pretend the constant is defined.
			 *
			 * @return string|null
			 */
			protected function constant_api_key(): ?string {
				return 'fn_const';
			}
		};
		$this->assertSame( 'fn_const', $over->api_key() );
		$this->assertTrue( $over->api_key_from_constant() );
	}

	public function test_has_api_key_false_when_empty_and_test_mode() {
		$this->stub_option( array() );
		$s = new Settings();
		$this->assertFalse( $s->has_api_key() );
		$this->assertTrue( $s->test_mode() );
	}

	public function test_update_merges_and_saves() {
		$this->stub_option( array( 'strictness' => 2 ) );
		$this->stub_strip();
		$saved = null;
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$saved ) {
				$saved = array( $name, $value );
				return true;
			}
		);
		( new Settings() )->update( array( 'strictness' => 3 ) );
		$this->assertSame( 'fraudnova_settings', $saved[0] );
		$this->assertSame( 3, $saved[1]['strictness'] );
		$this->assertSame( 60, $saved[1]['review_threshold'] );
	}

	public function test_enum_values() {
		$s = new Settings();
		$this->assertSame( array( 'woocommerce', 'remote_addr', 'cloudflare' ), $s->enum_values( 'ip_source' ) );
		$this->assertSame( array( 'off', 'review', 'high' ), $s->enum_values( 'rule_ip_proxy' ) );
		$this->assertSame( array( 'flag', 'hold' ), $s->enum_values( 'review_action' ) );
		$this->assertSame( array( 'flag', 'hold', 'cancel' ), $s->enum_values( 'high_action' ) );
		$this->assertSame( array( 'allow', 'review' ), $s->enum_values( 'on_error' ) );
		$this->assertSame( array(), $s->enum_values( 'strictness' ) );
	}
}
