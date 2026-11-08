<?php
/**
 * Cache tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Cache;
use FraudNova\WordPress\Check_Result;

/**
 * Transient cache.
 */
final class CacheTest extends TestCase {

	/**
	 * In-test transient store.
	 *
	 * @var array
	 */
	private $store = array();

	protected function setUp(): void {
		parent::setUp();
		$this->store = array();
		$store       = &$this->store;
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}
		Functions\when( 'get_transient' )->alias(
			function ( $k ) use ( &$store ) {
				return isset( $store[ $k ] ) ? $store[ $k ]['v'] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $k, $v, $ttl = 0 ) use ( &$store ) {
				$store[ $k ] = array( 'v' => $v, 'ttl' => $ttl );
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( $k ) use ( &$store ) {
				unset( $store[ $k ] );
				return true;
			}
		);
	}

	public function test_key_hides_subject(): void {
		$key = Cache::key( 'email', 'john@example.com' );
		$this->assertStringNotContainsString( 'john', $key );
		$this->assertMatchesRegularExpression( '/^fraudnova_email_[0-9a-f]{40}$/', $key );
	}

	public function test_set_stores_only_ok_results(): void {
		$cache = new Cache();
		$cache->set( 'ip', '8.8.8.8', Check_Result::error( 'ip', 'x', 'y' ) );
		$cache->set( 'ip', '8.8.8.8', Check_Result::skipped( 'ip', 'z' ) );
		$this->assertNull( $cache->get( 'ip', '8.8.8.8' ) );
		$this->assertSame( array(), $this->store );
	}

	public function test_get_returns_cached_status(): void {
		$cache = new Cache();
		$cache->set( 'ip', '8.8.8.8', Check_Result::from_api( 'ip', array( 'fraud_score' => 50, 'proxy' => true ) ) );
		$got = $cache->get( 'ip', '8.8.8.8' );
		$this->assertSame( 'cached', $got->status );
		$this->assertSame( 50, $got->score );
		$this->assertTrue( $got->flag( 'proxy' ) );
		$this->assertSame( 86400, $cache->ttl() );
	}

	public function test_backoff_clamped(): void {
		$cache = new Cache();
		$this->assertSame( 0, $cache->backoff_remaining() );
		$cache->set_backoff( 120 );
		$this->assertLessThanOrEqual( 60, $cache->backoff_remaining() );
		$this->assertGreaterThan( 0, $cache->backoff_remaining() );
		$cache->set_backoff( 0 );
		$this->assertLessThanOrEqual( 1, $cache->backoff_remaining() );
		$this->assertSame( 1, $this->store[ Cache::BACKOFF_KEY ]['ttl'] );
		$cache->clear_backoff();
		$this->assertSame( 0, $cache->backoff_remaining() );
	}
}
