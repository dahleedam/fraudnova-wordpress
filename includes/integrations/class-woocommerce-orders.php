<?php
/**
 * WooCommerce order integration.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Integrations;

use FraudNova\WordPress\Client_Ip;
use FraudNova\WordPress\Order_Assessment;
use FraudNova\WordPress\Order_Meta;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Scorer;
use FraudNova\WordPress\Subject;
use FraudNova\WordPress\Time_Budget;

defined( 'ABSPATH' ) || exit;

/**
 * Scores orders at checkout and holds or cancels risky cash-on-delivery orders.
 */
final class Woocommerce_Orders {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Scorer override (tests).
	 *
	 * @var Scorer|null
	 */
	private $scorer = null;

	/**
	 * Payment gateway override (tests).
	 *
	 * @var array|null
	 */
	private $gateways = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Replace the scorer.
	 *
	 * @param Scorer $scorer Scorer.
	 * @return void
	 */
	public function set_scorer( Scorer $scorer ): void {
		$this->scorer = $scorer;
	}

	/**
	 * Replace the payment gateway list.
	 *
	 * @param array $gateways Gateway objects.
	 * @return void
	 */
	public function set_gateways( array $gateways ): void {
		$this->gateways = $gateways;
	}

	/**
	 * The scorer in use.
	 *
	 * @return Scorer
	 */
	private function scorer(): Scorer {
		return null !== $this->scorer ? $this->scorer : $this->plugin->scorer();
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'handle_classic' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'handle_store_api' ), 10, 1 );
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'cod_status' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( $this, 'hold_email_enabled' ), 10, 2 );
	}

	/**
	 * Classic checkout hook.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $posted   Posted data.
	 * @param mixed $order    WC_Order.
	 * @return void
	 */
	public function handle_classic( $order_id, $posted, $order ): void {
		if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}
		$this->maybe_score( $order );
	}

	/**
	 * Block (Store API) checkout hook.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 */
	public function handle_store_api( $order ): void {
		$this->maybe_score( $order );
	}

	/**
	 * Score at checkout; never lets an error break the checkout.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 */
	private function maybe_score( $order ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}
		try {
			$this->score_order( $order );
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			try {
				$order->add_order_note(
					sprintf(
						/* translators: %s: error message. */
						__( 'FraudNova: the check failed (%s). Use Check now on the order screen to retry.', 'fraudnova' ),
						$message
					)
				);
				$order->save();
			} catch ( \Throwable $ignored ) {
				unset( $ignored );
			}
			try {
				$this->plugin->log()->record(
					array(
						'source'   => 'order',
						'type'     => 'order',
						'order_id' => $order->get_id(),
						'message'  => $message,
					)
				);
			} catch ( \Throwable $ignored ) {
				unset( $ignored );
			}
		}
	}

	/**
	 * Whether the order should be scored.
	 *
	 * @param mixed $order WC_Order.
	 * @return bool
	 */
	public function applies_to( $order ): bool {
		$settings = $this->plugin->settings();
		if ( ! $settings->get( 'orders_enabled' ) ) {
			return false;
		}
		$methods = $settings->get( 'order_payment_methods' );
		if ( 'all' === $methods ) {
			return true;
		}
		$id = strtolower( (string) $order->get_payment_method() );
		if ( is_array( $methods ) ) {
			return in_array( $id, array_map( 'strtolower', array_map( 'strval', $methods ) ), true );
		}
		// Default: the store's cash-on-delivery gateways, or every method when it has none.
		$cod_ids = self::cod_like_gateway_ids( $this->gateways );
		if ( array() === $cod_ids ) {
			return true;
		}
		return in_array( $id, $cod_ids, true );
	}

	/**
	 * Lowercase ids of the enabled gateways that look like cash on delivery.
	 * This is the default payment-method selection, used by checkout and the settings page.
	 *
	 * @param array|null $gateways Gateway objects (tests); defaults to WooCommerce's list.
	 * @return string[]
	 */
	public static function cod_like_gateway_ids( ?array $gateways = null ): array {
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

		$ids = array();
		foreach ( $gateways as $gateway ) {
			if ( ! is_object( $gateway ) || ! isset( $gateway->id ) ) {
				continue;
			}
			if ( isset( $gateway->enabled ) && 'yes' !== $gateway->enabled ) {
				continue;
			}
			$id = (string) $gateway->id;
			if ( self::looks_like_cod( $id, self::gateway_title( $gateway ) ) ) {
				$ids[] = strtolower( $id );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * A gateway's display title.
	 *
	 * @param object $gateway Gateway.
	 * @return string
	 */
	public static function gateway_title( $gateway ): string {
		$title = method_exists( $gateway, 'get_title' ) ? (string) $gateway->get_title() : '';
		if ( '' === $title && method_exists( $gateway, 'get_method_title' ) ) {
			$title = (string) $gateway->get_method_title();
		}
		if ( '' === $title && isset( $gateway->title ) && is_scalar( $gateway->title ) ) {
			$title = (string) $gateway->title;
		}
		return $title;
	}

	/**
	 * Whether a gateway looks like cash on delivery.
	 *
	 * @param string $id    Gateway ID.
	 * @param string $title Gateway title.
	 * @return bool
	 */
	public static function looks_like_cod( string $id, string $title ): bool {
		return (bool) preg_match( '/(?<![a-z])cod(?![a-z])|cash.?on.?delivery/i', $id . ' ' . $title );
	}

	/**
	 * Score an order.
	 *
	 * @param mixed $order WC_Order.
	 * @param bool  $force Recheck: ignore the gate and cache, never change status.
	 * @return Order_Assessment|null Null when the order was not scored.
	 */
	public function score_order( $order, bool $force = false ): ?Order_Assessment {
		if ( ! $force ) {
			if ( ! $this->applies_to( $order ) || Order_Meta::is_checked( $order ) ) {
				return null;
			}
			Order_Meta::mark_checking( $order );
		}

		$settings = $this->plugin->settings();
		$scorer   = $this->scorer();
		$budget   = new Time_Budget( (float) apply_filters( 'fraudnova_order_time_budget', 8.0 ) );
		$options  = array(
			'strictness'   => (int) $settings->get( 'strictness' ),
			'bypass_cache' => $force,
			'budget'       => $budget,
		);

		$ip      = Client_Ip::resolve( $settings, $order );
		$email   = (string) $order->get_billing_email();
		$results = array();
		if ( $settings->get( 'check_ip' ) ) {
			$results[ Subject::IP ] = $scorer->score( Subject::IP, $ip, $options );
		}
		if ( $settings->get( 'check_phone' ) ) {
			$results[ Subject::PHONE ] = $scorer->score(
				Subject::PHONE,
				(string) $order->get_billing_phone(),
				array_merge( $options, array( 'country' => (string) $order->get_billing_country() ) )
			);
		}
		if ( $settings->get( 'check_email' ) ) {
			$results[ Subject::EMAIL ] = $scorer->score( Subject::EMAIL, $email, $options );
		}

		$paid_by_card = '' !== (string) $order->get_transaction_id();
		$assessment   = Order_Assessment::evaluate( $results, $settings, $paid_by_card );

		if ( $force ) {
			// A re-check never changes the status, so the recorded action stays
			// what was actually done; a first manual check can only flag.
			$previous = (string) $order->get_meta( Order_Meta::ACTION );
			if ( '' !== $previous ) {
				$assessment->action = $previous;
			} elseif ( Order_Assessment::ACTION_HOLD === $assessment->action || Order_Assessment::ACTION_CANCEL === $assessment->action ) {
				$assessment->action = Order_Assessment::ACTION_FLAG;
			}
		}

		Order_Meta::write( $order, $assessment, $results );
		if ( $force ) {
			$order->delete_meta_data( Order_Meta::ACTION_PENDING );
		}
		$note = $force ? $assessment->recheck_note() : $assessment->note();
		$order->add_order_note( $note );
		$order->save();

		$this->plugin->log()->record(
			array(
				'source'   => $force ? 'recheck' : 'order',
				'subject'  => '' !== trim( $email ) ? Subject::mask( Subject::EMAIL, $email ) : Subject::mask( Subject::IP, $ip ),
				'type'     => 'order',
				'score'    => $assessment->score,
				'level'    => $assessment->level,
				'action'   => $assessment->action,
				'order_id' => $order->get_id(),
				'message'  => $note,
			)
		);

		return $assessment;
	}

	/**
	 * Core COD gateway status filter: apply a pending hold or cancel.
	 *
	 * @param string $status Status the gateway would set.
	 * @param mixed  $order  WC_Order.
	 * @return string
	 */
	public function cod_status( $status, $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return $status;
		}
		$target = self::target( (string) $order->get_meta( Order_Meta::ACTION_PENDING ) );
		if ( '' === $target ) {
			return $status;
		}
		$this->mark_applied( $order );
		return $target;
	}

	/**
	 * Generic seam for gateways that set the status themselves. A pending
	 * action is consumed on the first transition out of an unpaid status;
	 * later (manual) transitions never apply it.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 * @param mixed  $order    WC_Order.
	 * @return void
	 */
	public function on_status_changed( $order_id, $from, $to, $order = null ): void {
		if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}
		$target = self::target( (string) $order->get_meta( Order_Meta::ACTION_PENDING ) );
		if ( '' === $target || '' !== (string) $order->get_meta( Order_Meta::ACTION_APPLIED ) ) {
			return;
		}
		if ( ! in_array( (string) $from, array( '', 'pending', 'checkout-draft', 'failed' ), true ) ) {
			return;
		}

		if ( '' !== (string) $order->get_transaction_id() ) {
			$this->downgrade( $order, __( 'FraudNova: paid by card, flagged only.', 'fraudnova' ) );
			return;
		}

		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			$this->mark_applied( $order );
			$note = 'on-hold' === $target
				? __( 'FraudNova: held for review.', 'fraudnova' )
				: __( 'FraudNova: cancelled after a fraud check.', 'fraudnova' );
			$order->update_status( $target, $note );
			return;
		}

		if ( $to === $target ) {
			$this->mark_applied( $order );
			return;
		}

		$message = 'on-hold' === $target
			/* translators: %s: order status. */
			? __( 'FraudNova: hold not applied — the payment method moved the order to %s.', 'fraudnova' )
			/* translators: %s: order status. */
			: __( 'FraudNova: cancellation not applied — the payment method moved the order to %s.', 'fraudnova' );
		$this->downgrade( $order, sprintf( $message, (string) $to ) );
	}

	/**
	 * Drop a pending action and record the order as flagged only.
	 *
	 * @param mixed  $order WC_Order.
	 * @param string $note  Order note.
	 * @return void
	 */
	private function downgrade( $order, string $note ): void {
		$order->delete_meta_data( Order_Meta::ACTION_PENDING );
		$order->update_meta_data( Order_Meta::ACTION, Order_Assessment::ACTION_FLAG );
		$order->add_order_note( $note );
		$order->save();
	}

	/**
	 * Suppress the customer on-hold email for orders FraudNova held.
	 *
	 * @param bool  $enabled Whether the email is enabled.
	 * @param mixed $order   WC_Order.
	 * @return bool
	 */
	public function hold_email_enabled( $enabled, $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return $enabled;
		}
		if ( $this->plugin->settings()->get( 'suppress_hold_email' )
			&& Order_Assessment::ACTION_HOLD === $order->get_meta( Order_Meta::ACTION )
			&& '' !== (string) $order->get_meta( Order_Meta::ACTION_APPLIED ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Order status for a pending action.
	 *
	 * @param string $pending Pending action.
	 * @return string Empty when there is nothing to apply.
	 */
	private static function target( string $pending ): string {
		if ( Order_Assessment::ACTION_HOLD === $pending ) {
			return 'on-hold';
		}
		if ( Order_Assessment::ACTION_CANCEL === $pending ) {
			return 'cancelled';
		}
		return '';
	}

	/**
	 * Record that the pending action was applied, and save.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 */
	private function mark_applied( $order ): void {
		$order->update_meta_data( Order_Meta::ACTION_APPLIED, gmdate( 'c' ) );
		$order->delete_meta_data( Order_Meta::ACTION_PENDING );
		$order->save();
	}
}
