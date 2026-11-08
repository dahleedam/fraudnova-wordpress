<?php
/**
 * Settings page.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Integrations\Woocommerce_Orders;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → FraudNova: four tabs over one option.
 */
class Settings_Page {

	const SLUG = 'fraudnova';
	const TABS = array( 'general', 'orders', 'forms', 'activity' );

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * URL of the settings page, optionally on a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function url( string $tab = '' ): string {
		$path = 'options-general.php?page=' . self::SLUG;
		if ( '' !== $tab ) {
			$path .= '&tab=' . rawurlencode( $tab );
		}
		return admin_url( $path );
	}

	/**
	 * Register the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'fraudnova',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->plugin->settings(), 'sanitize' ),
			)
		);
	}

	/**
	 * Tab labels.
	 *
	 * @return array
	 */
	public function tab_labels(): array {
		return array(
			'general'  => __( 'General', 'fraudnova' ),
			'orders'   => __( 'Orders', 'fraudnova' ),
			'forms'    => __( 'Forms', 'fraudnova' ),
			'activity' => __( 'Activity', 'fraudnova' ),
		);
	}

	/**
	 * Setting keys edited on a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string[]
	 */
	public function tab_keys( string $tab ): array {
		$keys = array_keys( Settings::defaults() );
		switch ( $tab ) {
			case 'general':
				return array( 'api_key', 'test_mode', 'low_credit_email', 'delete_on_uninstall' );
			case 'forms':
				return array_values(
					array_filter(
						$keys,
						function ( $key ) {
							return 0 === strpos( $key, 'form_' );
						}
					)
				);
			case 'orders':
				$other = array_merge( $this->tab_keys( 'general' ), $this->tab_keys( 'forms' ) );
				return array_values( array_diff( $keys, $other ) );
		}
		return array();
	}

	/**
	 * The requested tab, whitelisted.
	 *
	 * @return string
	 */
	public function current_tab(): string {
		// Read-only navigation parameter, checked against a fixed whitelist; no state changes.
		$raw = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $raw, self::TABS, true ) ? $raw : 'general';
	}

	/**
	 * Hidden inputs for every setting not on the current tab, so saving one tab keeps the others.
	 *
	 * @param string[] $except_keys Keys rendered as visible fields.
	 * @return string HTML.
	 */
	public function hidden_fields( array $except_keys ): string {
		$html = '';
		foreach ( $this->plugin->settings()->all() as $key => $value ) {
			// The API key is never echoed into other tabs; sanitize() keeps the stored key when it is not posted.
			if ( 'api_key' === $key || in_array( $key, $except_keys, true ) || null === $value ) {
				continue;
			}
			$name = Settings::OPTION . '[' . $key . ']';
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$html .= $this->hidden_input( $name . '[]', (string) $item );
					}
				}
			} elseif ( is_bool( $value ) ) {
				$html .= $this->hidden_input( $name, $value ? '1' : '0' );
			} elseif ( is_scalar( $value ) ) {
				$html .= $this->hidden_input( $name, (string) $value );
			}
		}
		return $html;
	}

	/**
	 * One escaped hidden input.
	 *
	 * @param string $name  Field name.
	 * @param string $value Value.
	 * @return string
	 */
	private function hidden_input( string $name, string $value ): string {
		return sprintf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $name ), esc_attr( $value ) );
	}

	/**
	 * Allowed tags when printing hidden_fields().
	 *
	 * @return array
	 */
	public static function hidden_fields_kses(): array {
		return array(
			'input' => array(
				'type'  => true,
				'name'  => true,
				'value' => true,
			),
		);
	}

	/**
	 * WooCommerce payment gateways for the checklist.
	 *
	 * @param array|null $gateways Gateway objects (tests); defaults to WooCommerce's list.
	 * @return array id => ['title', 'enabled', 'cod_like'].
	 */
	public function gateway_choices( ?array $gateways = null ): array {
		if ( null === $gateways ) {
			if ( ! function_exists( 'WC' ) ) {
				return array();
			}
			$wc = WC();
			if ( ! is_object( $wc ) || ! method_exists( $wc, 'payment_gateways' ) ) {
				return array();
			}
			$gateways = (array) $wc->payment_gateways()->payment_gateways();
		}

		$cod_ids = Woocommerce_Orders::cod_like_gateway_ids( $gateways );
		$out     = array();
		foreach ( $gateways as $gateway ) {
			if ( ! is_object( $gateway ) || ! isset( $gateway->id ) ) {
				continue;
			}
			$id    = (string) $gateway->id;
			$title = Woocommerce_Orders::gateway_title( $gateway );
			if ( '' === $title ) {
				$title = $id;
			}
			$out[ $id ] = array(
				'title'    => $title,
				'enabled'  => isset( $gateway->enabled ) && 'yes' === $gateway->enabled,
				'cod_like' => in_array( strtolower( $id ), $cod_ids, true ),
			);
		}
		return $out;
	}

	/**
	 * Checklist values that render ticked: the stored choice, or the same
	 * default checkout applies (cash-on-delivery gateways, else every method).
	 *
	 * @param mixed $stored  Stored order_payment_methods value.
	 * @param array $choices Result of gateway_choices().
	 * @return string[] Lowercase gateway ids and/or 'all'.
	 */
	public static function checked_methods( $stored, array $choices ): array {
		if ( 'all' === $stored ) {
			return array( 'all' );
		}
		if ( is_array( $stored ) ) {
			return array_values( array_map( 'strtolower', array_map( 'strval', $stored ) ) );
		}
		$cod = array();
		foreach ( $choices as $id => $choice ) {
			if ( ! empty( $choice['cod_like'] ) ) {
				$cod[] = strtolower( (string) $id );
			}
		}
		return array() === $cod ? array( 'all' ) : $cod;
	}

	/**
	 * Stored gateway ids that the checklist cannot show (gateway or WooCommerce inactive).
	 *
	 * @param mixed $stored  Stored order_payment_methods value.
	 * @param array $choices Result of gateway_choices().
	 * @return string[]
	 */
	public static function missing_gateway_ids( $stored, array $choices ): array {
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$missing = array();
		foreach ( $stored as $id ) {
			if ( is_scalar( $id ) && ! array_key_exists( (string) $id, $choices ) ) {
				$missing[] = (string) $id;
			}
		}
		return array_values( array_unique( $missing ) );
	}

	/**
	 * Human labels for enum values.
	 *
	 * @return array
	 */
	public function enum_labels(): array {
		return array(
			'off'         => __( 'Off', 'fraudnova' ),
			'review'      => __( 'Needs review', 'fraudnova' ),
			'high'        => __( 'High risk', 'fraudnova' ),
			'flag'        => __( 'Flag only (add a note)', 'fraudnova' ),
			'hold'        => __( 'Put on hold', 'fraudnova' ),
			'cancel'      => __( 'Cancel the order', 'fraudnova' ),
			'allow'       => __( 'Let the order through', 'fraudnova' ),
			'woocommerce' => __( 'WooCommerce', 'fraudnova' ),
			'remote_addr' => __( 'Connecting address (REMOTE_ADDR)', 'fraudnova' ),
			'cloudflare'  => __( 'Cloudflare (CF-Connecting-IP)', 'fraudnova' ),
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab             = $this->current_tab();
		$page            = $this;
		$plugin_settings = $this->plugin->settings();
		$settings        = $plugin_settings->all();
		$name            = Settings::OPTION;

		echo '<div class="wrap fraudnova-settings">';
		echo '<h1>' . esc_html__( 'FraudNova', 'fraudnova' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper fraudnova-tabs">';
		foreach ( $this->tab_labels() as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( $slug ) ),
				$slug === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( 'activity' === $tab ) {
			$rows    = $this->plugin->log()->all();
			$balance = get_transient( Ajax::BALANCE_TRANSIENT );
			// Read-only flag set by our own redirect after clearing; it only toggles a message.
			$cleared = isset( $_GET['cleared'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			include __DIR__ . '/views/settings-activity.php';
		} else {
			echo '<form method="post" action="options.php" class="fraudnova-form">';
			settings_fields( 'fraudnova' );
			echo wp_kses( $this->hidden_fields( $this->tab_keys( $tab ) ), self::hidden_fields_kses() );
			include __DIR__ . '/views/settings-' . $tab . '.php';
			submit_button();
			echo '</form>';
		}

		echo '</div>';
	}
}
