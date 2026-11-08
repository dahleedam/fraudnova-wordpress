<?php
/**
 * Orders list "Risk" column.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Order_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a risk pill column to the WooCommerce orders list.
 */
class Order_List_Column {

	const COLUMN = 'fraudnova_risk';

	/**
	 * Register the hooks (legacy and HPOS).
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Insert the column after the order status.
	 *
	 * @param mixed $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		$label   = __( 'Risk', 'fraudnova' );
		if ( ! array_key_exists( 'order_status', $columns ) ) {
			$columns[ self::COLUMN ] = $label;
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $value ) {
			$out[ $key ] = $value;
			if ( 'order_status' === $key ) {
				$out[ self::COLUMN ] = $label;
			}
		}
		return $out;
	}

	/**
	 * Print the cell.
	 *
	 * @param mixed $column      Column key.
	 * @param mixed $order_or_id WC_Order (HPOS) or post id (legacy).
	 * @return void
	 */
	public function render_column( $column, $order_or_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$order = $order_or_id instanceof \WC_Order ? $order_or_id : wc_get_order( (int) $order_or_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$data = Order_Meta::read( $order );
		if ( null === $data ) {
			echo Order_Meta_Box::pill( 'none', null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() escapes.
			return;
		}
		echo Order_Meta_Box::pill( $data['assessment']->level, $data['assessment']->score ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() escapes.
	}
}
