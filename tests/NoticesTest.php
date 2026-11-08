<?php
/**
 * Admin notices tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Notices;
use FraudNova\WordPress\Error_State;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Settings;

/**
 * Tests for Admin\Notices.
 */
class NoticesTest extends TestCase {

	/**
	 * Option store.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Transient store.
	 *
	 * @var array
	 */
	private $transients = array();

	/**
	 * Current screen id.
	 *
	 * @var string
	 */
	private $screen = 'dashboard';

	/**
	 * Notices under test.
	 *
	 * @var Notices
	 */
	private $notices;

	protected function setUp(): void {
		parent::setUp();
		$this->options    = array(
			Settings::OPTION => array( 'api_key' => 'fn_test' ),
			'admin_email'    => 'owner@shop.test',
		);
		$this->transients = array();
		$this->screen     = 'dashboard';
		$options          = &$this->options;
		$transients       = &$this->transients;
		$screen           = &$this->screen;

		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$options ) {
				return array_key_exists( $k, $options ) ? $options[ $k ] : $d;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( $k ) use ( &$transients ) {
				return array_key_exists( $k, $transients ) ? $transients[ $k ] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $k, $v ) use ( &$transients ) {
				$transients[ $k ] = $v;
				return true;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'admin_url' )->alias(
			function ( $p = '' ) {
				return 'https://shop.test/wp-admin/' . $p;
			}
		);
		Functions\when( 'get_current_screen' )->alias(
			function () use ( &$screen ) {
				return (object) array( 'id' => $screen );
			}
		);

		$this->notices = new Notices( Plugin::instance() );
	}

	/**
	 * Set the stored error state.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @return void
	 */
	private function error( string $code, string $message = 'API says no' ): void {
		$this->options[ Error_State::OPTION ] = array(
			'code'    => $code,
			'message' => $message,
			'time'    => 1,
		);
	}

	/**
	 * Render notices into a string.
	 *
	 * @return string
	 */
	private function render(): string {
		ob_start();
		$this->notices->render();
		return (string) ob_get_clean();
	}

	public function test_registers_admin_notices() {
		Actions\expectAdded( 'admin_notices' )->once()->with( array( $this->notices, 'render' ) );
		$this->notices->register();
		$this->addToAssertionCount( 1 );
	}

	public function test_nothing_rendered_without_an_error() {
		Functions\expect( 'wp_mail' )->never();
		$this->assertSame( '', $this->render() );
	}

	public function test_nothing_rendered_for_users_who_cannot_manage_options() {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->error( 'unauthorized' );
		$this->assertSame( '', $this->render() );
	}

	public function test_unauthorized_renders_a_persistent_error() {
		$this->error( 'unauthorized' );
		$html = $this->render();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringNotContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( 'FraudNova: your API key was rejected.', $html );
		$this->assertStringContainsString( 'href="https://shop.test/wp-admin/options-general.php?page=fraudnova"', $html );
		$this->assertStringContainsString( 'Check the settings', $html );
	}

	public function test_insufficient_credits_renders_a_warning_with_billing_link() {
		Functions\when( 'wp_mail' )->justReturn( true );
		$this->error( 'insufficient_credits' );
		$html = $this->render();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'FraudNova: your account is out of credits, so checks are skipped and orders are not held.', $html );
		$this->assertStringContainsString( 'href="https://fraudnova.com/dashboard/billing"', $html );
		$this->assertStringContainsString( 'Buy credits', $html );
	}

	public function test_low_credit_mail_is_sent_once_per_day() {
		$this->error( 'insufficient_credits' );
		Functions\expect( 'wp_mail' )->once()->with(
			'owner@shop.test',
			'FraudNova: your account is out of credits',
			\Mockery::on(
				function ( $body ) {
					return false !== strpos( $body, 'https://fraudnova.com/dashboard/billing' );
				}
			)
		)->andReturn( true );

		$this->render();
		$this->render();

		$this->assertArrayHasKey( 'fraudnova_low_credit_mailed', $this->transients );
	}

	public function test_low_credit_mail_respects_the_setting() {
		$this->options[ Settings::OPTION ]['low_credit_email'] = false;
		$this->error( 'insufficient_credits' );
		Functions\expect( 'wp_mail' )->never();

		$this->assertStringContainsString( 'Buy credits', $this->render() );
	}

	public function test_no_api_key_notice_on_plugins_screen() {
		$this->options[ Settings::OPTION ] = array();
		$this->screen                      = 'plugins';
		$html                              = $this->render();

		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( 'FraudNova needs an API key.', $html );
		$this->assertStringContainsString( 'Add it in Settings → FraudNova', $html );
	}

	public function test_no_api_key_notice_on_settings_screen() {
		$this->options[ Settings::OPTION ] = array();
		$this->screen                      = 'settings_page_fraudnova';

		$this->assertStringContainsString( 'FraudNova needs an API key.', $this->render() );
	}

	public function test_no_api_key_notice_hidden_on_other_screens() {
		$this->options[ Settings::OPTION ] = array();
		$this->assertSame( '', $this->render() );
	}

	public function test_no_api_key_notice_hidden_when_every_feature_is_off() {
		$this->options[ Settings::OPTION ] = array(
			'orders_enabled'       => false,
			'form_wp_registration' => false,
			'form_wc_registration' => false,
			'form_cf7'             => false,
		);
		$this->screen                      = 'plugins';
		$this->assertSame( '', $this->render() );
	}

	public function test_error_message_is_escaped() {
		$this->error( 'unauthorized', '<script>x</script>' );
		$this->assertStringNotContainsString( '<script>', $this->render() );
	}
}
