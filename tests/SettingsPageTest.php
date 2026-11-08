<?php
/**
 * Settings page tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use Brain\Monkey\Functions;
use FraudNova\WordPress\Admin\Settings_Page;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Settings;

/**
 * Tests for Admin\Settings_Page.
 */
class SettingsPageTest extends TestCase {

	/**
	 * Stored settings.
	 *
	 * @var array
	 */
	private $stored = array();

	/**
	 * Page under test.
	 *
	 * @var Settings_Page
	 */
	private $page;

	protected function setUp(): void {
		parent::setUp();
		$this->stored = array();
		$stored       = &$this->stored;
		Functions\when( 'get_option' )->alias(
			function ( $k, $d = false ) use ( &$stored ) {
				return Settings::OPTION === $k ? $stored : $d;
			}
		);
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		$this->page = new Settings_Page( Plugin::instance() );
	}

	protected function tearDown(): void {
		unset( $_GET['tab'] );
		parent::tearDown();
	}

	/**
	 * Parse hidden inputs into name => list of values.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	private function inputs( string $html ): array {
		preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)" \/>/', $html, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $match ) {
			$out[ $match[1] ][] = $match[2];
		}
		return $out;
	}

	public function test_hidden_fields_render_every_key_except_the_given_ones() {
		$this->stored = array(
			'api_key'               => 'fn_"secret"',
			'order_payment_methods' => array( 'cod', 'advanced_cod' ),
		);
		$html   = $this->page->hidden_fields( array( 'test_mode', 'low_credit_email' ) );
		$inputs = $this->inputs( $html );

		$this->assertArrayNotHasKey( 'fraudnova_settings[test_mode]', $inputs );
		$this->assertArrayNotHasKey( 'fraudnova_settings[low_credit_email]', $inputs );
		$this->assertArrayNotHasKey( 'fraudnova_settings[api_key]', $inputs );
		$this->assertStringNotContainsString( 'secret', $html );
		$this->assertSame( array( '1' ), $inputs['fraudnova_settings[orders_enabled]'] );
		$this->assertSame( array( '0' ), $inputs['fraudnova_settings[delete_on_uninstall]'] );
		$this->assertSame( array( '60' ), $inputs['fraudnova_settings[review_threshold]'] );
		$this->assertSame( array( 'woocommerce' ), $inputs['fraudnova_settings[ip_source]'] );
		$this->assertSame( array( 'cod', 'advanced_cod' ), $inputs['fraudnova_settings[order_payment_methods][]'] );

		$expected = count( Settings::defaults() ) - 2 - 1 - 1 + 2; // minus excluded, minus api_key, the array key renders twice.
		$this->assertSame( $expected, substr_count( $html, '<input ' ) );
	}

	public function test_hidden_fields_skip_null_payment_methods_and_keep_all() {
		$inputs = $this->inputs( $this->page->hidden_fields( array() ) );
		$this->assertArrayNotHasKey( 'fraudnova_settings[order_payment_methods]', $inputs );
		$this->assertArrayNotHasKey( 'fraudnova_settings[order_payment_methods][]', $inputs );

		$this->stored = array( 'order_payment_methods' => 'all' );
		$inputs       = $this->inputs( $this->page->hidden_fields( array() ) );
		$this->assertSame( array( 'all' ), $inputs['fraudnova_settings[order_payment_methods]'] );
	}

	public function test_hidden_fields_round_trip_through_sanitize() {
		$this->stored = array(
			'test_mode'             => false,
			'strictness'            => 2,
			'order_payment_methods' => array( 'cod' ),
			'form_block_message'    => 'Nope & no.',
		);
		$html = $this->page->hidden_fields( array() );
		$post = array();
		foreach ( $this->inputs( $html ) as $name => $values ) {
			$key = preg_replace( '/^fraudnova_settings\[([a-z0-9_]+)\](\[\])?$/', '$1', $name );
			$post[ $key ] = '[]' === substr( $name, -2 ) ? $values : html_entity_decode( $values[0] );
		}
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		$saved = Plugin::instance()->settings()->sanitize( $post );

		$this->assertSame( array_merge( Settings::defaults(), $this->stored ), $saved );
	}

	public function test_saving_general_under_the_constant_keeps_the_stored_key() {
		$this->stored = array(
			'api_key'   => 'fn_kept',
			'test_mode' => true,
		);
		// The General form under the constant posts its visible fields without api_key, plus the other tabs' hidden fields.
		$post = array( 'test_mode' => '1' );
		foreach ( $this->inputs( $this->page->hidden_fields( $this->page->tab_keys( 'general' ) ) ) as $name => $values ) {
			$key          = preg_replace( '/^fraudnova_settings\[([a-z0-9_]+)\](\[\])?$/', '$1', $name );
			$post[ $key ] = '[]' === substr( $name, -2 ) ? $values : html_entity_decode( $values[0] );
		}
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$this->assertArrayNotHasKey( 'api_key', $post );
		$this->assertSame( 'fn_kept', Plugin::instance()->settings()->sanitize( $post )['api_key'] );
	}

	public function test_saving_another_tab_keeps_the_stored_key() {
		$this->stored = array( 'api_key' => 'fn_kept' );
		$post         = array();
		foreach ( $this->inputs( $this->page->hidden_fields( $this->page->tab_keys( 'forms' ) ) ) as $name => $values ) {
			$key          = preg_replace( '/^fraudnova_settings\[([a-z0-9_]+)\](\[\])?$/', '$1', $name );
			$post[ $key ] = '[]' === substr( $name, -2 ) ? $values : html_entity_decode( $values[0] );
		}
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$this->assertSame( 'fn_kept', Plugin::instance()->settings()->sanitize( $post )['api_key'] );
	}

	public function test_missing_gateway_ids_keeps_stored_ids_the_checklist_cannot_show() {
		$choices = array(
			'cod' => array(
				'title'    => 'Cash on delivery',
				'enabled'  => true,
				'cod_like' => true,
			),
		);
		$this->assertSame( array( 'advanced_cod', 'other' ), Settings_Page::missing_gateway_ids( array( 'cod', 'advanced_cod', 'other', 'other' ), $choices ) );
		$this->assertSame( array( 'cod' ), Settings_Page::missing_gateway_ids( array( 'cod' ), array() ) );
		$this->assertSame( array(), Settings_Page::missing_gateway_ids( 'all', $choices ) );
		$this->assertSame( array(), Settings_Page::missing_gateway_ids( null, $choices ) );
	}

	public function test_tab_whitelist_falls_back_to_general() {
		$this->assertSame( 'general', $this->page->current_tab() );
		$_GET['tab'] = 'orders';
		$this->assertSame( 'orders', $this->page->current_tab() );
		$_GET['tab'] = 'activity';
		$this->assertSame( 'activity', $this->page->current_tab() );
		$_GET['tab'] = '../../evil';
		$this->assertSame( 'general', $this->page->current_tab() );
		$_GET['tab'] = array( 'orders' );
		$this->assertSame( 'general', $this->page->current_tab() );
	}

	public function test_tab_keys_cover_every_setting_exactly_once() {
		$keys = array();
		foreach ( Settings_Page::TABS as $tab ) {
			$keys = array_merge( $keys, $this->page->tab_keys( $tab ) );
		}
		sort( $keys );
		$all = array_keys( Settings::defaults() );
		sort( $all );
		$this->assertSame( $all, $keys );
	}

	/**
	 * Fake gateway.
	 *
	 * @param string $id      ID.
	 * @param string $enabled 'yes' or 'no'.
	 * @param string $title   Title.
	 * @param string $method  Method title.
	 * @return object
	 */
	private function gateway( string $id, string $enabled, string $title, string $method = '' ) {
		return new class( $id, $enabled, $title, $method ) {
			/**
			 * ID.
			 *
			 * @var string
			 */
			public $id;

			/**
			 * Enabled flag.
			 *
			 * @var string
			 */
			public $enabled;

			/**
			 * Title.
			 *
			 * @var string
			 */
			private $title;

			/**
			 * Method title.
			 *
			 * @var string
			 */
			private $method;

			/**
			 * Constructor.
			 *
			 * @param string $id      ID.
			 * @param string $enabled Enabled.
			 * @param string $title   Title.
			 * @param string $method  Method title.
			 */
			public function __construct( $id, $enabled, $title, $method ) {
				$this->id      = $id;
				$this->enabled = $enabled;
				$this->title   = $title;
				$this->method  = $method;
			}

			/**
			 * Title.
			 *
			 * @return string
			 */
			public function get_title() {
				return $this->title;
			}

			/**
			 * Method title.
			 *
			 * @return string
			 */
			public function get_method_title() {
				return $this->method;
			}
		};
	}

	public function test_gateway_choices_mark_cash_on_delivery() {
		$choices = $this->page->gateway_choices(
			array(
				$this->gateway( 'advanced_cod', 'yes', 'Pay at the door' ),
				$this->gateway( 'custom_pod', 'no', 'Cash on Delivery' ),
				$this->gateway( 'stripe', 'yes', '', 'Stripe card' ),
			)
		);

		$this->assertSame(
			array(
				'advanced_cod' => array(
					'title'    => 'Pay at the door',
					'enabled'  => true,
					'cod_like' => true,
				),
				'custom_pod'   => array(
					'title'    => 'Cash on Delivery',
					'enabled'  => false,
					'cod_like' => true,
				),
				'stripe'       => array(
					'title'    => 'Stripe card',
					'enabled'  => true,
					'cod_like' => false,
				),
			),
			$choices
		);
	}

	public function test_gateway_choices_empty_without_woocommerce() {
		$this->assertSame( array(), $this->page->gateway_choices() );
	}
}
