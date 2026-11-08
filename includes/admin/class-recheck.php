<?php
/**
 * Manual order re-check.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the "Check again" button on the order screen.
 */
class Recheck {

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
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_fraudnova_recheck', array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Handle the form post.
	 *
	 * @return void
	 */
	public function handle(): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line, the nonce action needs the id.
		check_admin_referer( 'fraudnova_recheck_' . $order_id );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fraudnova' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'fraudnova' ), '', array( 'response' => 404 ) );
		}

		$this->score( $order );

		wp_safe_redirect( add_query_arg( 'fraudnova', 'rechecked', $order->get_edit_order_url() ) );
		$this->terminate();
	}

	/**
	 * One-shot success notice after a re-check.
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( isset( $_GET['fraudnova'] ) && 'rechecked' === $_GET['fraudnova'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, literal compare.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'FraudNova re-checked this order.', 'fraudnova' ) . '</p></div>';
		}
	}

	/**
	 * Force-score the order.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	protected function score( $order ): void {
		$this->plugin->orders()->score_order( $order, true );
	}

	/**
	 * Stop the request after a redirect.
	 *
	 * @return void
	 */
	protected function terminate(): void {
		exit;
	}
}
