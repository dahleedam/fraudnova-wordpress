<?php
/**
 * Form assessment tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Form_Assessment;
use FraudNova\WordPress\Settings;

/**
 * Tests for Form_Assessment.
 */
class FormAssessmentTest extends TestCase {

	private function settings( array $overrides = array() ): Settings {
		Functions\when( 'get_option' )->justReturn( array_merge( array( 'test_mode' => false ), $overrides ) );
		return new Settings();
	}

	private function email( int $score, array $flags = array() ): Check_Result {
		return Check_Result::from_api( 'email', array_merge( array( 'fraud_score' => $score ), $flags ) );
	}

	private function ip( int $score, array $flags = array() ): Check_Result {
		return Check_Result::from_api( 'ip', array_merge( array( 'fraud_score' => $score ), $flags ) );
	}

	public function test_clean_is_allowed() {
		$a = Form_Assessment::evaluate( array( 'email' => $this->email( 10 ), 'ip' => $this->ip( 20 ) ), $this->settings() );
		$this->assertFalse( $a->blocked );
		$this->assertFalse( $a->would_block );
		$this->assertSame( 20, $a->score );
		$this->assertSame( array(), $a->reasons );
	}

	public function test_threshold_blocks() {
		$a = Form_Assessment::evaluate( array( 'ip' => $this->ip( 85 ) ), $this->settings() );
		$this->assertTrue( $a->blocked );
		$this->assertSame( array( 'score_85' ), $a->reasons );
		$b = Form_Assessment::evaluate( array( 'ip' => $this->ip( 84 ) ), $this->settings() );
		$this->assertFalse( $b->blocked );
	}

	public function test_custom_threshold() {
		$a = Form_Assessment::evaluate( array( 'ip' => $this->ip( 50 ) ), $this->settings( array( 'form_block_threshold' => 50 ) ) );
		$this->assertTrue( $a->blocked );
	}

	public function test_disposable_blocks_by_default() {
		$a = Form_Assessment::evaluate( array( 'email' => $this->email( 5, array( 'disposable' => true ) ) ), $this->settings() );
		$this->assertTrue( $a->blocked );
		$this->assertSame( array( 'email_disposable' ), $a->reasons );
	}

	public function test_disposable_rule_can_be_turned_off() {
		$a = Form_Assessment::evaluate(
			array( 'email' => $this->email( 5, array( 'disposable' => true ) ) ),
			$this->settings( array( 'form_rule_email_disposable' => false ) )
		);
		$this->assertFalse( $a->blocked );
	}

	public function test_invalid_email_does_not_block_by_default_but_can() {
		$results = array( 'email' => $this->email( 5, array( 'valid' => false ) ) );
		$this->assertFalse( Form_Assessment::evaluate( $results, $this->settings() )->blocked );
		$a = Form_Assessment::evaluate( $results, $this->settings( array( 'form_rule_email_invalid' => true ) ) );
		$this->assertTrue( $a->blocked );
		$this->assertSame( array( 'email_disposable' ), $a->reasons );
	}

	public function test_proxy_does_not_block_by_default_but_can() {
		$results = array( 'ip' => $this->ip( 5, array( 'vpn' => true ) ) );
		$this->assertFalse( Form_Assessment::evaluate( $results, $this->settings() )->blocked );
		$a = Form_Assessment::evaluate( $results, $this->settings( array( 'form_rule_ip_proxy' => true ) ) );
		$this->assertTrue( $a->blocked );
		$this->assertSame( array( 'ip_proxy' ), $a->reasons );
	}

	public function test_abuse_blocks_by_default() {
		$a = Form_Assessment::evaluate( array( 'ip' => $this->ip( 5, array( 'recent_abuse' => true ) ) ), $this->settings() );
		$this->assertTrue( $a->blocked );
		$this->assertSame( array( 'ip_abuse' ), $a->reasons );
		$b = Form_Assessment::evaluate( array( 'ip' => $this->ip( 5, array( 'bot_status' => true ) ) ), $this->settings() );
		$this->assertTrue( $b->blocked );
	}

	public function test_test_mode_never_blocks() {
		$a = Form_Assessment::evaluate( array( 'ip' => $this->ip( 95 ) ), $this->settings( array( 'test_mode' => true ) ) );
		$this->assertFalse( $a->blocked );
		$this->assertTrue( $a->would_block );
		$this->assertTrue( $a->test_mode );
	}

	public function test_errors_and_skips_never_block() {
		$a = Form_Assessment::evaluate(
			array(
				'ip'    => Check_Result::error( 'ip', 'http', 'boom' ),
				'email' => Check_Result::skipped( 'email', 'empty' ),
			),
			$this->settings()
		);
		$this->assertFalse( $a->blocked );
		$this->assertFalse( $a->would_block );
		$this->assertNull( $a->score );
	}
}
