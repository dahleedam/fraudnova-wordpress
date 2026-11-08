<?php
/**
 * Activity log tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Activity_Log;

/**
 * Capped, newest-first activity log.
 */
final class ActivityLogTest extends TestCase {

	/**
	 * In-test option store.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Recorded autoload flags.
	 *
	 * @var array
	 */
	private $autoload = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options  = array();
		$this->autoload = array();
		$options        = &$this->options;
		$autoload       = &$this->autoload;
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$options ) {
				return array_key_exists( $k, $options ) ? $options[ $k ] : $d;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $k, $v, $a = null ) use ( &$options, &$autoload ) {
				$options[ $k ]  = $v;
				$autoload[ $k ] = $a;
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

	private function event( string $message = 'm' ): array {
		return array(
			'source'   => 'order',
			'subject'  => 'j***@example.com',
			'type'     => 'email',
			'score'    => '77',
			'level'    => 'review',
			'action'   => 'flag',
			'message'  => $message,
			'order_id' => '12',
		);
	}

	public function test_record_normalizes_and_drops_unknown_keys(): void {
		$log   = new Activity_Log();
		$event = $this->event();
		$event['evil'] = 'x';
		$event['time'] = 5;
		$log->record( $event );
		$rows = $log->all();
		$this->assertCount( 1, $rows );
		$this->assertArrayNotHasKey( 'evil', $rows[0] );
		$this->assertSame( 77, $rows[0]['score'] );
		$this->assertSame( 12, $rows[0]['order_id'] );
		$this->assertGreaterThan( 1000, $rows[0]['time'] );
		$this->assertSame( 'review', $rows[0]['level'] );
	}

	public function test_null_score_and_order_stay_null(): void {
		$log   = new Activity_Log();
		$event = $this->event();
		$event['score']    = null;
		$event['order_id'] = null;
		$log->record( $event );
		$rows = $log->all();
		$this->assertNull( $rows[0]['score'] );
		$this->assertNull( $rows[0]['order_id'] );
	}

	public function test_newest_first_and_capped(): void {
		$log = new Activity_Log();
		for ( $i = 1; $i <= 205; $i++ ) {
			$log->record( $this->event( 'n' . $i ) );
		}
		$rows = $log->all();
		$this->assertCount( 200, $rows );
		$this->assertSame( 'n205', $rows[0]['message'] );
		$this->assertSame( 'n6', $rows[199]['message'] );
	}

	public function test_autoload_is_off(): void {
		$log = new Activity_Log();
		$log->record( $this->event() );
		$this->assertFalse( $this->autoload[ Activity_Log::OPTION ] );
	}

	public function test_long_strings_truncated(): void {
		$log = new Activity_Log();
		$log->record( $this->event( str_repeat( 'a', 500 ) ) );
		$this->assertSame( 200, strlen( $log->all()[0]['message'] ) );
	}

	public function test_clear(): void {
		$log = new Activity_Log();
		$log->record( $this->event() );
		$log->clear();
		$this->assertSame( array(), $log->all() );
	}

	public function test_non_array_option_is_reset(): void {
		$this->options[ Activity_Log::OPTION ] = 'garbage';
		$log = new Activity_Log();
		$this->assertSame( array(), $log->all() );
		$log->record( $this->event() );
		$this->assertCount( 1, $log->all() );
	}
}
