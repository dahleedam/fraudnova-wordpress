<?php
/**
 * Recheck action tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Recheck;
use FraudNova\WordPress\Plugin;

/**
 * Tests for Admin\Recheck.
 */
class RecheckTest extends TestCase {

	/**
	 * Log of events.
	 *
	 * @var array
	 */
	private $log = array();

	protected function setUp(): void {
		parent::setUp();
		$this->log = array();
		$log       = &$this->log;
		$_GET      = array( 'order_id' => '12' );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'check_admin_referer' )->alias(
			function ( $a ) use ( &$log ) {
				$log[] = 'nonce:' . $a;
				return 1;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'add_query_arg' )->alias(
			function ( $k, $v, $url ) {
				return $url . '&' . $k . '=' . $v;
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( $url ) use ( &$log ) {
				$log[] = 'redirect:' . $url;
			}
		);
		Functions\when( 'wp_die' )->alias(
			function ( $m, $t = '', $a = array() ) {
				throw new \RuntimeException( 'die:' . ( is_array( $a ) ? $a['response'] : '' ) );
			}
		);
		Functions\when( 'wc_get_order' )->alias(
			function ( $id ) {
				return 12 === $id ? new \WC_Order( 12 ) : false;
			}
		);
	}

	protected function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	private function recheck(): Recheck {
		$log = &$this->log;
		return new class( Plugin::instance(), $log ) extends Recheck {
			/** @var array */
			private $log;

			public function __construct( $plugin, array &$log ) {
				parent::__construct( $plugin );
				$this->log = &$log;
			}

			protected function score( $order ): void {
				$this->log[] = 'score:' . $order->get_id();
			}

			protected function terminate(): void {
				$this->log[] = 'terminated';
			}
		};
	}

	public function test_happy_path_checks_nonce_scores_and_redirects() {
		$this->recheck()->handle();
		$this->assertSame(
			array(
				'nonce:fraudnova_recheck_12',
				'score:12',
				'redirect:https://example.test/wp-admin/post.php?post=12&action=edit&fraudnova=rechecked',
				'terminated',
			),
			$this->log
		);
	}

	public function test_post_body_is_ignored() {
		$_GET  = array();
		$_POST = array( 'order_id' => '12' );
		try {
			$this->recheck()->handle();
			$this->fail( 'expected wp_die' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'die:404', $e->getMessage() );
		} finally {
			$_POST = array();
		}
		$this->assertSame( 'nonce:fraudnova_recheck_0', $this->log[0] );
	}

	public function test_denied_without_capability() {
		Functions\when( 'current_user_can' )->justReturn( false );
		try {
			$this->recheck()->handle();
			$this->fail( 'expected wp_die' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'die:403', $e->getMessage() );
		}
		$this->assertNotContains( 'score:12', $this->log );
	}

	public function test_missing_order_is_404() {
		$_GET['order_id'] = '99';
		try {
			$this->recheck()->handle();
			$this->fail( 'expected wp_die' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'die:404', $e->getMessage() );
		}
	}

	public function test_notice_prints_only_for_rechecked_flag() {
		$r = $this->recheck();
		ob_start();
		$r->notice();
		$this->assertSame( '', ob_get_clean() );
		$_GET['fraudnova'] = 'rechecked';
		ob_start();
		$r->notice();
		$this->assertStringContainsString( 'FraudNova re-checked this order.', (string) ob_get_clean() );
	}
}
