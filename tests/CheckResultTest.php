<?php
/**
 * Check_Result tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use FraudNova\WordPress\Check_Result;

/**
 * Result normalization.
 */
final class CheckResultTest extends TestCase {

	public function test_whitelisting_drops_unknown_fields(): void {
		$r = Check_Result::from_api(
			'ip',
			array(
				'fraud_score'  => 87.5,
				'proxy'        => 1,
				'vpn'          => false,
				'country_code' => 'PK',
				'ISP'          => 'X',
				'ASN'          => 123,
				'organization' => 'Org',
				'name'         => 'Owner',
			)
		);
		$this->assertSame( 'ok', $r->status );
		$this->assertSame( 88, $r->score );
		$this->assertTrue( $r->flag( 'proxy' ) );
		$this->assertTrue( $r->has_flag( 'vpn' ) );
		$this->assertFalse( $r->flag( 'vpn' ) );
		$this->assertFalse( $r->flag( 'tor' ) );
		$this->assertFalse( $r->has_flag( 'tor' ) );
		$this->assertSame( array( 'country_code' => 'PK', 'ISP' => 'X' ), $r->detail );
		$this->assertStringNotContainsString( 'Owner', wp_json_encode( $r->to_array() ) );
	}

	public function test_phone_name_never_kept(): void {
		$r = Check_Result::from_api( 'phone', array( 'name' => 'Jane Doe', 'carrier' => 'Jazz', 'valid' => true ) );
		$this->assertSame( array( 'carrier' => 'Jazz' ), $r->detail );
		$this->assertTrue( $r->flag( 'valid' ) );
	}

	public function test_first_seen_object_keeps_human(): void {
		$r = Check_Result::from_api( 'email', array( 'first_seen' => array( 'human' => '2 years ago', 'iso' => 'x' ), 'fraud_score' => 10 ) );
		$this->assertSame( '2 years ago', $r->detail['first_seen'] );
		$r2 = Check_Result::from_api( 'email', array( 'first_seen' => 'N/A' ) );
		$this->assertSame( 'N/A', $r2->detail['first_seen'] );
	}

	public function test_missing_score_is_null(): void {
		$this->assertNull( Check_Result::from_api( 'email', array() )->score );
	}

	public function test_skipped_and_error(): void {
		$s = Check_Result::skipped( 'ip', 'private ip' );
		$this->assertSame( 'skipped', $s->status );
		$this->assertSame( 'private ip', $s->message );
		$this->assertFalse( $s->counted() );
		$e = Check_Result::error( 'ip', 'rate_limited', 'Slow down' );
		$this->assertSame( 'error', $e->status );
		$this->assertSame( 'rate_limited', $e->error_code );
		$this->assertSame( 'Slow down', $e->message );
		$this->assertFalse( $e->counted() );
	}

	public function test_round_trip_and_cached(): void {
		$r = Check_Result::from_api( 'phone', array( 'fraud_score' => 40, 'VOIP' => 1, 'carrier' => 'Jazz' ) );
		$b = Check_Result::from_array( $r->to_array() );
		$this->assertEquals( $r, $b );
		$c = $r->as_cached();
		$this->assertSame( 'cached', $c->status );
		$this->assertSame( 'ok', $r->status );
		$this->assertTrue( $c->counted() );
		$this->assertTrue( $r->counted() );
	}
}
