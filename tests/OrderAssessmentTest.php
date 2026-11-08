<?php
/**
 * Order assessment tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Order_Assessment;
use FraudNova\WordPress\Settings;

/**
 * Tests for Order_Assessment.
 */
class OrderAssessmentTest extends TestCase {

	/**
	 * Settings over a stubbed option.
	 *
	 * @param array $overrides Overrides (test_mode defaults to off here).
	 * @return Settings
	 */
	private function settings( array $overrides = array() ): Settings {
		Functions\when( 'get_option' )->justReturn( array_merge( array( 'test_mode' => false ), $overrides ) );
		return new Settings();
	}

	private function phone( int $score, array $flags = array() ): Check_Result {
		return Check_Result::from_api( 'phone', array_merge( array( 'fraud_score' => $score ), $flags ) );
	}

	private function email( int $score, array $flags = array() ): Check_Result {
		return Check_Result::from_api( 'email', array_merge( array( 'fraud_score' => $score ), $flags ) );
	}

	private function ip( int $score, array $flags = array() ): Check_Result {
		return Check_Result::from_api( 'ip', array_merge( array( 'fraud_score' => $score ), $flags ) );
	}

	public function test_low_score_is_clean() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 12 ) ), $this->settings() );
		$this->assertSame( 'clean', $a->level );
		$this->assertSame( 'none', $a->action );
		$this->assertSame( 12, $a->score );
		$this->assertSame( array(), $a->reasons );
		$this->assertSame( 'FraudNova: score 12 (clean).', $a->note() );
	}

	public function test_review_band_flags() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 60 ) ), $this->settings() );
		$this->assertSame( 'review', $a->level );
		$this->assertSame( 'flag', $a->action );
		$this->assertSame( array( 'score_60' ), $a->reasons );
		$this->assertSame( 'FraudNova: score 60 (review) — Fraud score 60. Flagged for review.', $a->note() );
	}

	public function test_high_band_holds_and_score_is_max() {
		$a = Order_Assessment::evaluate(
			array(
				'phone' => $this->phone( 20 ),
				'ip'    => $this->ip( 85 ),
			),
			$this->settings()
		);
		$this->assertSame( 'high', $a->level );
		$this->assertSame( 'hold', $a->action );
		$this->assertSame( 85, $a->score );
		$this->assertSame( 'FraudNova: score 85 (high) — Fraud score 85. Held for review.', $a->note() );
	}

	public function test_invalid_phone_is_high_even_with_low_score() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 10, array( 'valid' => false ) ) ), $this->settings() );
		$this->assertSame( 'high', $a->level );
		$this->assertSame( 'hold', $a->action );
		$this->assertSame( array( 'phone_invalid' ), $a->reasons );
		$this->assertSame( 'FraudNova: score 10 (high) — Phone number invalid. Held for review.', $a->note() );
	}

	public function test_note_with_several_reasons() {
		$a = Order_Assessment::evaluate(
			array(
				'phone' => $this->phone( 30, array( 'valid' => false ) ),
				'ip'    => $this->ip( 30, array( 'proxy' => true ) ),
			),
			$this->settings()
		);
		$this->assertSame( 'FraudNova: score 30 (high) — Phone number invalid, Proxy, VPN or Tor. Held for review.', $a->note() );
	}

	public function test_voip_is_review() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 10, array( 'VOIP' => true ) ) ), $this->settings() );
		$this->assertSame( 'review', $a->level );
		$this->assertSame( array( 'phone_voip' ), $a->reasons );
		$b = Order_Assessment::evaluate( array( 'phone' => $this->phone( 10, array( 'risky' => true ) ) ), $this->settings() );
		$this->assertSame( array( 'phone_voip' ), $b->reasons );
	}

	public function test_rule_off_has_no_effect() {
		$a = Order_Assessment::evaluate(
			array( 'phone' => $this->phone( 10, array( 'valid' => false ) ) ),
			$this->settings( array( 'rule_phone_invalid' => 'off' ) )
		);
		$this->assertSame( 'clean', $a->level );
		$this->assertSame( array(), $a->reasons );
	}

	public function test_other_rules_fire() {
		$s = $this->settings();
		$this->assertSame( array( 'email_disposable' ), Order_Assessment::evaluate( array( 'email' => $this->email( 5, array( 'disposable' => true ) ) ), $s )->reasons );
		$this->assertSame( array( 'email_disposable' ), Order_Assessment::evaluate( array( 'email' => $this->email( 5, array( 'valid' => false ) ) ), $s )->reasons );
		$this->assertSame( array( 'email_abuse' ), Order_Assessment::evaluate( array( 'email' => $this->email( 5, array( 'honeypot' => true ) ) ), $s )->reasons );
		$this->assertSame( array( 'ip_proxy' ), Order_Assessment::evaluate( array( 'ip' => $this->ip( 5, array( 'active_vpn' => true ) ) ), $s )->reasons );
		$this->assertSame( array( 'ip_abuse' ), Order_Assessment::evaluate( array( 'ip' => $this->ip( 5, array( 'bot_status' => true ) ) ), $s )->reasons );
	}

	public function test_test_mode_degrades_hold_to_flag() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 90 ) ), $this->settings( array( 'test_mode' => true ) ) );
		$this->assertSame( 'flag', $a->action );
		$this->assertSame( 'hold', $a->requested_action );
		$this->assertTrue( $a->test_mode );
		$this->assertSame( 'FraudNova: score 90 (high) — Fraud score 90. Flagged for review. (test mode, no action taken)', $a->note() );
	}

	public function test_paid_by_card_degrades_cancel_to_flag() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 90 ) ), $this->settings( array( 'high_action' => 'cancel' ) ), true );
		$this->assertSame( 'flag', $a->action );
		$this->assertSame( 'cancel', $a->requested_action );
		$this->assertTrue( $a->paid_by_card );
		$this->assertSame( 'FraudNova: score 90 (high) — Fraud score 90. Flagged for review. (paid by card, flagged only)', $a->note() );
	}

	public function test_both_suffixes_in_order() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 90 ) ), $this->settings( array( 'test_mode' => true ) ), true );
		$this->assertSame(
			'FraudNova: score 90 (high) — Fraud score 90. Flagged for review. (test mode, no action taken) (paid by card, flagged only)',
			$a->note()
		);
	}

	public function test_high_action_cancel() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 90 ) ), $this->settings( array( 'high_action' => 'cancel' ) ) );
		$this->assertSame( 'cancel', $a->action );
		$this->assertSame( 'FraudNova: score 90 (high) — Fraud score 90. Cancelled.', $a->note() );
	}

	public function test_review_action_hold() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 70 ) ), $this->settings( array( 'review_action' => 'hold' ) ) );
		$this->assertSame( 'hold', $a->action );
	}

	public function test_flag_action_is_not_degraded() {
		$a = Order_Assessment::evaluate( array( 'phone' => $this->phone( 70 ) ), $this->settings( array( 'test_mode' => true ) ), true );
		$this->assertSame( 'flag', $a->action );
		$this->assertStringNotContainsString( 'test mode', $a->note() );
		$this->assertStringNotContainsString( 'paid by card', $a->note() );
	}

	public function test_all_skipped_is_unknown() {
		$a = Order_Assessment::evaluate(
			array(
				'ip'    => Check_Result::skipped( 'ip', 'private' ),
				'email' => Check_Result::skipped( 'email', 'empty' ),
				'phone' => Check_Result::skipped( 'phone', 'empty' ),
			),
			$this->settings()
		);
		$this->assertSame( 'unknown', $a->level );
		$this->assertNull( $a->score );
		$this->assertSame( 'none', $a->action );
		$this->assertSame( 'FraudNova: no check could be completed.', $a->note() );
	}

	public function test_error_with_on_error_review() {
		$a = Order_Assessment::evaluate(
			array( 'phone' => Check_Result::error( 'phone', 'http', 'boom' ) ),
			$this->settings( array( 'on_error' => 'review' ) )
		);
		$this->assertSame( 'review', $a->level );
		$this->assertNull( $a->score );
		$this->assertSame( array( 'api_error' ), $a->reasons );
		$this->assertSame( 'flag', $a->action );
		$this->assertSame( 'FraudNova: no check could be completed (review) — A check could not be completed. Flagged for review.', $a->note() );
	}

	public function test_error_with_on_error_review_lifts_clean() {
		$a = Order_Assessment::evaluate(
			array(
				'phone' => $this->phone( 5 ),
				'ip'    => Check_Result::error( 'ip', 'http', 'boom' ),
			),
			$this->settings( array( 'on_error' => 'review' ) )
		);
		$this->assertSame( 'review', $a->level );
		$this->assertSame( array( 'api_error' ), $a->reasons );
		$this->assertSame( 5, $a->score );
	}

	public function test_error_with_on_error_allow_stays_clean() {
		$a = Order_Assessment::evaluate(
			array(
				'phone' => $this->phone( 5 ),
				'ip'    => Check_Result::error( 'ip', 'http', 'boom' ),
			),
			$this->settings( array( 'on_error' => 'allow' ) )
		);
		$this->assertSame( 'clean', $a->level );
		$this->assertSame( array(), $a->reasons );
	}

	public function test_error_only_with_allow_is_unknown() {
		$a = Order_Assessment::evaluate( array( 'ip' => Check_Result::error( 'ip', 'http', 'boom' ) ), $this->settings() );
		$this->assertSame( 'unknown', $a->level );
		$this->assertSame( 'none', $a->action );
	}

	public function test_meta_round_trip() {
		$a    = Order_Assessment::evaluate( array( 'phone' => $this->phone( 10, array( 'valid' => false ) ) ), $this->settings( array( 'test_mode' => true ) ), true );
		$meta = $a->to_meta();
		$this->assertSame(
			array( 'level', 'score', 'reasons', 'action', 'requested_action', 'test_mode', 'paid_by_card' ),
			array_keys( $meta )
		);
		$b = Order_Assessment::from_meta( $meta );
		$this->assertSame( $meta, $b->to_meta() );
		$this->assertSame( $a->note(), $b->note() );
	}

	public function test_recheck_note_never_claims_an_action() {
		$clean = Order_Assessment::evaluate( array( 'phone' => $this->phone( 12 ) ), $this->settings() );
		$this->assertSame( 'FraudNova re-check: score 12 (clean). Status not changed.', $clean->recheck_note() );

		$high = Order_Assessment::evaluate( array( 'ip' => $this->ip( 85 ) ), $this->settings() );
		$this->assertSame( 'FraudNova re-check: score 85 (high) — Fraud score 85. Status not changed.', $high->recheck_note() );
		$this->assertStringNotContainsString( 'Held', $high->recheck_note() );

		$none = Order_Assessment::evaluate( array( 'phone' => Check_Result::skipped( 'phone', 'empty' ) ), $this->settings() );
		$this->assertSame( 'FraudNova re-check: no check could be completed. Status not changed.', $none->recheck_note() );

		$error = Order_Assessment::evaluate(
			array( 'phone' => Check_Result::error( 'phone', 'http', 'boom' ) ),
			$this->settings( array( 'on_error' => 'review' ) )
		);
		$this->assertSame( 'FraudNova re-check: no check could be completed (review) — A check could not be completed. Status not changed.', $error->recheck_note() );
	}

	public function test_from_meta_tolerates_missing_keys() {
		$b = Order_Assessment::from_meta( array() );
		$this->assertSame( 'unknown', $b->level );
		$this->assertNull( $b->score );
		$this->assertSame( array(), $b->reasons );
		$this->assertSame( 'none', $b->action );
		$this->assertFalse( $b->test_mode );
	}
}
