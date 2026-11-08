<?php
/**
 * Error state tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Error_State;

/**
 * Last-error option.
 */
final class ErrorStateTest extends TestCase {

	/**
	 * In-test option store.
	 *
	 * @var array
	 */
	private $options = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options = array();
		$options       = &$this->options;
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$options ) {
				return array_key_exists( $k, $options ) ? $options[ $k ] : $d;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $k, $v ) use ( &$options ) {
				$options[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $k ) use ( &$options ) {
				unset( $options[ $k ] );
				return true;
			}
		);
	}

	public function test_round_trip_and_is(): void {
		$state = new Error_State();
		$this->assertNull( $state->get() );
		$this->assertFalse( $state->is( 'invalid_key' ) );
		$state->set( 'invalid_key', 'Bad key' );
		$got = $state->get();
		$this->assertSame( 'invalid_key', $got['code'] );
		$this->assertSame( 'Bad key', $got['message'] );
		$this->assertGreaterThan( 1000, $got['time'] );
		$this->assertTrue( $state->is( 'invalid_key' ) );
		$this->assertFalse( $state->is( 'no_credits' ) );
	}

	public function test_set_overwrites_and_clear(): void {
		$state = new Error_State();
		$state->set( 'a', 'one' );
		$state->set( 'b', 'two' );
		$this->assertTrue( $state->is( 'b' ) );
		$state->clear();
		$this->assertNull( $state->get() );
	}

	public function test_non_array_is_null(): void {
		$this->options[ Error_State::OPTION ] = 'x';
		$this->assertNull( ( new Error_State() )->get() );
	}
}
