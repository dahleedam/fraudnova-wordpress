<?php
/**
 * Base test case.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Sets up Brain Monkey and common WordPress function stubs.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'absint' )->alias(
			function ( $v ) {
				return abs( (int) $v );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_email' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias(
			function ( $k ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) );
			}
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'is_wp_error' )->alias(
			function ( $t ) {
				return $t instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_parse_args' )->alias(
			function ( $a, $d ) {
				return array_merge( $d, (array) $a );
			}
		);
	}

	/**
	 * Tear down.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
