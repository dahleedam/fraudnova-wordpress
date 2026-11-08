<?php
/**
 * Privacy policy text and personal data erasure.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Registers privacy policy content and WooCommerce erasure support.
 */
final class Privacy {

	/**
	 * Plugin instance.
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
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'erase_order' ), 10, 1 );
	}

	/**
	 * Suggest privacy policy text.
	 *
	 * @return void
	 */
	public function add_policy_content(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'FraudNova', wp_kses_post( self::policy_text() ) );
		}
	}

	/**
	 * Remove FraudNova data from an order being erased.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 */
	public function erase_order( $order ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'delete_meta_data' ) ) {
			return;
		}
		Order_Meta::delete_all( $order );
		$order->save();
	}

	/**
	 * Suggested privacy policy paragraph.
	 *
	 * @return string
	 */
	public static function policy_text(): string {
		return sprintf(
			/* translators: 1: privacy policy URL, 2: terms URL. */
			__( 'This site uses FraudNova (fraudnova.com) to score visitors for fraud risk. To obtain a fraud score, it sends the customer IP address, billing phone number, billing country and email address of new orders, and the IP address and email address of registrations and form submissions, to FraudNova. The score and risk flags are stored on the order, and a masked activity log is kept in the site administration, visible to administrators only. See the FraudNova <a href="%1$s">privacy policy</a> and <a href="%2$s">terms</a>.', 'fraudnova' ),
			esc_url( 'https://fraudnova.com/privacy' ),
			esc_url( 'https://fraudnova.com/terms' )
		);
	}
}
