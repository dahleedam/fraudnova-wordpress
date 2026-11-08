<?php
/**
 * Uninstaller tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Uninstaller;

/**
 * Tests for the Uninstaller.
 */
class UninstallerTest extends TestCase {

	/**
	 * Deleted options.
	 *
	 * @var array
	 */
	private $deleted = array();

	protected function setUp(): void {
		parent::setUp();
		$this->deleted = array();
		Functions\when( 'delete_option' )->alias(
			function ( $name ) {
				$this->deleted[] = $name;
				return true;
			}
		);
	}

	public function test_deletes_options_and_transients() {
		$wpdb = new \wpdb();
		Uninstaller::run( $wpdb, false );
		$this->assertSame( array( 'fraudnova_settings', 'fraudnova_activity_log', 'fraudnova_last_error' ), $this->deleted );
		$this->assertCount( 2, $wpdb->queries );
		$this->assertStringContainsString( "'_transient_fraudnova\\_%'", $wpdb->queries[0] );
		$this->assertStringContainsString( "'_transient_timeout_fraudnova\\_%'", $wpdb->queries[1] );
		$this->assertStringContainsString( 'DELETE FROM wp_options', $wpdb->queries[0] );
	}

	public function test_order_meta_only_when_requested() {
		$wpdb = new \wpdb();
		Uninstaller::run( $wpdb, true );
		$this->assertCount( 3, $wpdb->queries );
		$this->assertStringContainsString( 'DELETE FROM wp_postmeta WHERE meta_key LIKE', $wpdb->queries[2] );
		$this->assertStringContainsString( "'\\_fraudnova\\_%'", $wpdb->queries[2] );
	}

	public function test_orders_meta_table_only_when_it_exists() {
		$wpdb                 = new \wpdb();
		$wpdb->get_var_result = 'wp_wc_orders_meta';
		Uninstaller::run( $wpdb, true );
		$this->assertCount( 4, $wpdb->queries );
		$this->assertStringContainsString( 'DELETE FROM wp_wc_orders_meta', $wpdb->queries[3] );
	}

	public function test_run_for_all_sites_single_site_reads_option() {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( array( 'delete_on_uninstall' => 1 ) );
		$wpdb = new \wpdb();
		Uninstaller::run_for_all_sites( $wpdb );
		$this->assertCount( 3, $wpdb->queries );
	}

	public function test_run_for_all_sites_keeps_order_data_by_default() {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( false );
		$wpdb = new \wpdb();
		Uninstaller::run_for_all_sites( $wpdb );
		$this->assertCount( 2, $wpdb->queries );
	}
}
