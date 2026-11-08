<?php
/**
 * Form check tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Activity_Log;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Form_Check;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Scorer;
use FraudNova\WordPress\Settings;
use FraudNova\WordPress\Time_Budget;
use Mockery;

/**
 * Tests for Form_Check.
 */
class FormCheckTest extends TestCase {

	/**
	 * Settings option.
	 *
	 * @var array
	 */
	private $settings_option = array();

	/**
	 * Other options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Saved server.
	 *
	 * @var array
	 */
	private $server;

	/**
	 * Scorer mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $scorer;

	/**
	 * Check under test.
	 *
	 * @var Form_Check
	 */
	private $check;

	protected function setUp(): void {
		parent::setUp();
		$this->server          = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
		$this->settings_option = array(
			'test_mode' => false,
			'api_key'   => 'fn_test',
			'ip_source' => 'remote_addr',
		);
		$this->options         = array();
		$settings              = &$this->settings_option;
		$options               = &$this->options;
		Functions\when( 'wp_unslash' )->alias( 'stripslashes' );
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$settings, &$options ) {
				if ( Settings::OPTION === $k ) {
					return $settings;
				}
				return array_key_exists( $k, $options ) ? $options[ $k ] : $d;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $k, $v ) use ( &$options ) {
				$options[ $k ] = $v;
				return true;
			}
		);
		$this->scorer = Mockery::mock( Scorer::class );
		$this->check  = new Form_Check( Plugin::instance() );
		$this->check->set_scorer( $this->scorer );
	}

	protected function tearDown(): void {
		$_SERVER = $this->server;
		parent::tearDown();
	}

	public function test_scores_ip_and_email_with_budget_and_logs_masked_email() {
		$opts = Mockery::on(
			function ( $o ) {
				return $o['budget'] instanceof Time_Budget;
			}
		);
		$this->scorer->shouldReceive( 'score' )->once()->with( 'ip', '8.8.8.8', $opts )
			->andReturn( Check_Result::from_api( 'ip', array( 'fraud_score' => 20 ) ) );
		$this->scorer->shouldReceive( 'score' )->once()->with( 'email', 'jane@example.com', $opts )
			->andReturn( Check_Result::from_api( 'email', array( 'fraud_score' => 95 ) ) );

		$a = $this->check->assess( 'wp_registration', 'jane@example.com' );
		$this->assertTrue( $a->blocked );

		$log = ( new Activity_Log() )->all();
		$this->assertCount( 1, $log );
		$this->assertSame( 'wp_registration', $log[0]['source'] );
		$this->assertSame( 'form', $log[0]['type'] );
		$this->assertSame( 'j***@example.com', $log[0]['subject'] );
		$this->assertSame( 95, $log[0]['score'] );
		$this->assertSame( 'blocked', $log[0]['level'] );
		$this->assertSame( 'blocked', $log[0]['action'] );
		$this->assertStringContainsString( 'Fraud score 95', $log[0]['message'] );
	}

	public function test_respects_toggles() {
		$this->settings_option['form_check_ip'] = false;
		$this->scorer->shouldReceive( 'score' )->once()->with( 'email', 'jane@example.com', Mockery::type( 'array' ) )
			->andReturn( Check_Result::from_api( 'email', array( 'fraud_score' => 10 ) ) );
		$a = $this->check->assess( 'cf7', 'jane@example.com' );
		$this->assertFalse( $a->blocked );
		$this->assertSame( 'allowed', ( new Activity_Log() )->all()[0]['level'] );
	}

	public function test_email_toggle_off_and_empty_email_skip_email_check() {
		$this->settings_option['form_check_email'] = false;
		$this->scorer->shouldReceive( 'score' )->once()->with( 'ip', '8.8.8.8', Mockery::type( 'array' ) )
			->andReturn( Check_Result::from_api( 'ip', array( 'fraud_score' => 10 ) ) );
		$this->check->assess( 'cf7', 'jane@example.com' );
		$log = ( new Activity_Log() )->all();
		$this->assertSame( 'j***@example.com', $log[0]['subject'] );
	}

	public function test_no_email_logs_masked_ip() {
		$this->scorer->shouldReceive( 'score' )->once()->with( 'ip', '8.8.8.8', Mockery::type( 'array' ) )
			->andReturn( Check_Result::from_api( 'ip', array( 'fraud_score' => 10 ) ) );
		$this->check->assess( 'cf7', '  ' );
		$this->assertStringNotContainsString( '8.8.8.8', ( new Activity_Log() )->all()[0]['subject'] );
		$this->assertNotSame( '', ( new Activity_Log() )->all()[0]['subject'] );
	}

	public function test_unresolvable_ip_is_not_scored() {
		unset( $_SERVER['REMOTE_ADDR'] );
		$this->scorer->shouldNotReceive( 'score' );
		$a = $this->check->assess( 'cf7', '' );
		$this->assertFalse( $a->blocked );
	}

	public function test_test_mode_logs_test_mode_and_never_blocks() {
		$this->settings_option['test_mode'] = true;
		$this->scorer->shouldReceive( 'score' )->andReturn( Check_Result::from_api( 'ip', array( 'fraud_score' => 99 ) ) );
		$a = $this->check->assess( 'cf7', 'jane@example.com' );
		$this->assertFalse( $a->blocked );
		$this->assertTrue( $a->would_block );
		$log = ( new Activity_Log() )->all();
		$this->assertSame( 'test_mode', $log[0]['level'] );
		$this->assertSame( 'allowed', $log[0]['action'] );
	}

	public function test_fails_open_when_the_scorer_throws() {
		$this->scorer->shouldReceive( 'score' )->andThrow( new \RuntimeException( 'boom' ) );
		$a = $this->check->assess( 'cf7', 'jane@example.com' );
		$this->assertFalse( $a->blocked );
		$log = ( new Activity_Log() )->all();
		$this->assertCount( 1, $log );
		$this->assertSame( 'boom', $log[0]['message'] );
	}
}
