<?php
/**
 * Reasons tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use FraudNova\WordPress\Reasons;

/**
 * Tests for the Reasons class.
 */
class ReasonsTest extends TestCase {

	public function test_labels() {
		$this->assertSame( 'Phone number invalid', Reasons::label( 'phone_invalid' ) );
		$this->assertSame( 'VOIP or risky phone number', Reasons::label( 'phone_voip' ) );
		$this->assertSame( 'Disposable or invalid email', Reasons::label( 'email_disposable' ) );
		$this->assertSame( 'Email linked to recent abuse', Reasons::label( 'email_abuse' ) );
		$this->assertSame( 'Proxy, VPN or Tor', Reasons::label( 'ip_proxy' ) );
		$this->assertSame( 'IP linked to recent abuse or bots', Reasons::label( 'ip_abuse' ) );
		$this->assertSame( 'A check could not be completed', Reasons::label( 'api_error' ) );
	}

	public function test_score_label() {
		$this->assertSame( 'Fraud score 91', Reasons::label( 'score_91' ) );
	}

	public function test_unknown_key_returns_itself() {
		$this->assertSame( 'whatever', Reasons::label( 'whatever' ) );
		$this->assertSame( 'score_x', Reasons::label( 'score_x' ) );
	}
}
