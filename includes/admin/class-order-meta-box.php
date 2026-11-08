<?php
/**
 * Order screen meta box.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Order_Assessment;
use FraudNova\WordPress\Order_Meta;
use FraudNova\WordPress\Subject;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the FraudNova assessment on the WooCommerce order edit screen.
 */
class Order_Meta_Box {

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Add the meta box to the legacy and HPOS order screens.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = (string) wc_get_page_screen_id( 'shop_order' );
		}
		add_meta_box(
			'fraudnova',
			__( 'FraudNova', 'fraudnova' ),
			array( $this, 'render' ),
			array_values( array_unique( array_filter( $screens ) ) ),
			'side',
			'high'
		);
	}

	/**
	 * Render the box.
	 *
	 * @param mixed $post_or_order WP_Post or WC_Order.
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = null;
		if ( $post_or_order instanceof \WC_Order ) {
			$order = $post_or_order;
		} elseif ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
			$order = wc_get_order( $post_or_order->ID );
		}
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$box      = $this;
		$order_id = (int) $order->get_id();
		$data     = Order_Meta::read( $order );

		include __DIR__ . '/views/order-meta-box.php';
	}

	/**
	 * A coloured pill for a risk level.
	 *
	 * @param string   $level Level (clean, review, high, unknown, none).
	 * @param int|null $score Score.
	 * @return string Escaped HTML.
	 */
	public static function pill( string $level, ?int $score ): string {
		switch ( $level ) {
			case Order_Assessment::LEVEL_CLEAN:
				$label = __( 'Clean', 'fraudnova' );
				break;
			case Order_Assessment::LEVEL_REVIEW:
				$label = __( 'Review', 'fraudnova' );
				break;
			case Order_Assessment::LEVEL_HIGH:
				$label = __( 'High', 'fraudnova' );
				break;
			case 'none':
				$label = __( 'Not checked', 'fraudnova' );
				break;
			default:
				$label = __( 'Unknown', 'fraudnova' );
		}
		if ( null !== $score ) {
			$label .= ' · ' . $score;
		}
		return sprintf(
			'<span class="fraudnova-pill is-%s">%s</span>',
			esc_attr( sanitize_key( $level ) ),
			esc_html( $label )
		);
	}

	/**
	 * Label for the action FraudNova took on the order.
	 *
	 * @param string $action none|flag|hold|cancel.
	 * @return string
	 */
	public function action_label( string $action ): string {
		switch ( $action ) {
			case Order_Assessment::ACTION_FLAG:
				return __( 'Flagged for review', 'fraudnova' );
			case Order_Assessment::ACTION_HOLD:
				return __( 'Held for review', 'fraudnova' );
			case Order_Assessment::ACTION_CANCEL:
				return __( 'Cancelled', 'fraudnova' );
		}
		return __( 'No action', 'fraudnova' );
	}

	/**
	 * Label for a check type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public function type_label( string $type ): string {
		switch ( $type ) {
			case Subject::IP:
				return __( 'IP address', 'fraudnova' );
			case Subject::PHONE:
				return __( 'Phone', 'fraudnova' );
			default:
				return __( 'Email', 'fraudnova' );
		}
	}

	/**
	 * Label for a skip reason.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	public function skip_label( string $reason ): string {
		switch ( $reason ) {
			case 'empty':
				return __( 'nothing to check', 'fraudnova' );
			case 'private_address':
				return __( 'private address', 'fraudnova' );
			case 'time_budget':
				return __( 'time budget exhausted', 'fraudnova' );
		}
		return $reason;
	}

	/**
	 * Chip labels for the flags worth showing.
	 *
	 * @param Check_Result $result Result.
	 * @return string[]
	 */
	public function flag_labels( Check_Result $result ): array {
		$names = array(
			'proxy'        => __( 'Proxy', 'fraudnova' ),
			'vpn'          => __( 'VPN', 'fraudnova' ),
			'tor'          => __( 'Tor', 'fraudnova' ),
			'active_vpn'   => __( 'Active VPN', 'fraudnova' ),
			'active_tor'   => __( 'Active Tor', 'fraudnova' ),
			'recent_abuse' => __( 'Recent abuse', 'fraudnova' ),
			'bot_status'   => __( 'Bot', 'fraudnova' ),
			'disposable'   => __( 'Disposable', 'fraudnova' ),
			'honeypot'     => __( 'Honeypot', 'fraudnova' ),
			'leaked'       => __( 'Leaked', 'fraudnova' ),
			'VOIP'         => __( 'VOIP', 'fraudnova' ),
			'prepaid'      => __( 'Prepaid', 'fraudnova' ),
			'risky'        => __( 'Risky', 'fraudnova' ),
		);
		$out   = array();
		foreach ( $result->flags as $key => $value ) {
			$key = (string) $key;
			if ( 'valid' === $key || 'active' === $key ) {
				if ( false === $value ) {
					$out[] = 'valid' === $key ? __( 'Invalid', 'fraudnova' ) : __( 'Inactive', 'fraudnova' );
				}
				continue;
			}
			if ( true !== $value ) {
				continue;
			}
			$out[] = isset( $names[ $key ] ) ? $names[ $key ] : ucfirst( str_replace( '_', ' ', $key ) );
		}
		return $out;
	}
}
