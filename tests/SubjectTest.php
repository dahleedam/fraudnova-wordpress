<?php
/**
 * Subject tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use FraudNova\WordPress\Subject;

/**
 * Subject normalization and masking.
 */
final class SubjectTest extends TestCase {

	public function test_phone_normalization_with_country(): void {
		$this->assertSame( 'PK:923001234567', Subject::normalize( 'phone', '+92 (300) 123-4567', 'pk' ) );
		$this->assertSame( '923001234567', Subject::normalize( 'phone', '+92 (300) 123-4567' ) );
		$this->assertSame( '923001234567', Subject::normalize( 'phone', '+92 (300) 123-4567', 'Pakistan' ) );
	}

	public function test_email_normalization(): void {
		$this->assertSame( 'john@example.com', Subject::normalize( 'email', '  John@Example.COM ' ) );
	}

	public function test_ipv6_canonicalized(): void {
		$this->assertSame(
			Subject::normalize( 'ip', '2001:DB8::1' ),
			Subject::normalize( 'ip', ' 2001:db8:0:0:0:0:0:1 ' )
		);
		$this->assertSame( '8.8.8.8', Subject::normalize( 'ip', ' 8.8.8.8 ' ) );
	}

	public function test_country(): void {
		$this->assertSame( 'PK', Subject::country( 'pk' ) );
		$this->assertSame( '', Subject::country( 'PAK' ) );
		$this->assertSame( '', Subject::country( '' ) );
	}

	public function test_is_private_ip(): void {
		foreach ( array( '127.0.0.1', '10.1.2.3', '192.168.1.1', '::1', 'not-an-ip', '169.254.1.1' ) as $ip ) {
			$this->assertTrue( Subject::is_private_ip( $ip ), $ip );
		}
		$this->assertFalse( Subject::is_private_ip( '8.8.8.8' ) );
	}

	public function test_masks(): void {
		$this->assertSame( 'j***@example.com', Subject::mask( 'email', 'john@example.com' ) );
		$this->assertSame( 'j***', Subject::mask( 'email', 'john' ) );
		$this->assertSame( '+' . str_repeat( '*', 8 ) . '4567', Subject::mask( 'phone', '+923001234567' ) );
		$this->assertSame( '****', Subject::mask( 'phone', '1234' ) );
		$this->assertSame( '103.x.x.x', Subject::mask( 'ip', '103.1.2.3' ) );
		$this->assertSame( '2a02:x:x', Subject::mask( 'ip', '2a02:4780::1' ) );
		$this->assertSame( '***', Subject::mask( 'ip', 'garbage' ) );
	}
}
