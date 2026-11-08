<?php
/**
 * WooCommerce registration integration tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FraudNova\WordPress\Form_Assessment;
use FraudNova\WordPress\Form_Check;
use FraudNova\WordPress\Integrations\Woocommerce_Registration;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Settings;
use Mockery;

/**
 * Tests for Woocommerce_Registration.
 */
class WooCommerceRegistrationTest extends TestCase {

	/**
	 * Form check mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $check;

	/**
	 * Integration.
	 *
	 * @var Woocommerce_Registration
	 */
	private $reg;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) {
				return Settings::OPTION === $k ? array( 'form_block_message' => 'Nope.' ) : $d;
			}
		);
		Functions\when( 'did_action' )->justReturn( 0 );
		$this->check = Mockery::mock( Form_Check::class );
		$this->reg   = new Woocommerce_Registration( Plugin::instance(), $this->check );
	}

	/**
	 * A blocked assessment.
	 *
	 * @return Form_Assessment
	 */
	private function blocked(): Form_Assessment {
		$a          = Form_Assessment::allowed();
		$a->blocked = true;
		return $a;
	}

	public function test_registers_filter() {
		Filters\expectAdded( 'woocommerce_registration_errors' )->once()->with( array( $this->reg, 'filter' ), 20, 3 );
		$this->reg->register();
		$this->assertNotFalse( has_filter( 'woocommerce_registration_errors', array( $this->reg, 'filter' ) ) );
	}

	public function test_blocked_adds_configured_error() {
		$this->check->shouldReceive( 'assess' )->once()->with( 'wc_registration', 'a@b.com' )->andReturn( $this->blocked() );
		$errors = $this->reg->filter( new \WP_Error(), 'bob', 'a@b.com' );
		$this->assertSame( array( 'fraudnova_blocked' ), $errors->get_error_codes() );
		$this->assertSame( 'Nope.', $errors->get_error_message( 'fraudnova_blocked' ) );
	}

	public function test_allowed_is_untouched() {
		$this->check->shouldReceive( 'assess' )->once()->andReturn( Form_Assessment::allowed() );
		$errors = $this->reg->filter( new \WP_Error(), 'bob', 'a@b.com' );
		$this->assertFalse( $errors->has_errors() );
	}

	public function test_checkout_context_never_assesses() {
		Functions\when( 'did_action' )->justReturn( 1 );
		$this->check->shouldNotReceive( 'assess' );
		$errors = $this->reg->filter( new \WP_Error(), 'bob', 'a@b.com' );
		$this->assertFalse( $errors->has_errors() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_store_api_request_never_assesses() {
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->alias( 'stripslashes' );
		define( 'REST_REQUEST', true );
		$saved                   = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
		$this->check->shouldNotReceive( 'assess' );
		$errors = $this->reg->filter( new \WP_Error(), 'bob', 'a@b.com' );
		$this->assertFalse( $errors->has_errors() );
		if ( null === $saved ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $saved;
		}
	}

	public function test_non_error_object_is_returned_untouched() {
		$this->check->shouldNotReceive( 'assess' );
		$this->assertSame( 'x', $this->reg->filter( 'x', 'bob', 'a@b.com' ) );
	}
}
