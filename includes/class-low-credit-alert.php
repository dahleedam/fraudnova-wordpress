<?php
/**
 * Out-of-credits email.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Emails the site admin, at most once a day, when the account runs out of credits.
 * Sent from the request that received the 402, so it does not wait for an admin to visit wp-admin.
 */
final class Low_Credit_Alert {

	const TRANSIENT   = 'fraudnova_low_credit_mailed';
	const BILLING_URL = 'https://fraudnova.com/dashboard/billing';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send the email unless it is turned off or was sent in the last day.
	 *
	 * @return void
	 */
	public function maybe_send(): void {
		if ( ! $this->settings->get( 'low_credit_email' ) ) {
			return;
		}
		if ( false !== get_transient( self::TRANSIENT ) ) {
			return;
		}
		// Set before sending, so a slow or failing mailer never sends twice.
		set_transient( self::TRANSIENT, 1, DAY_IN_SECONDS );

		$body = __( 'Your FraudNova account is out of credits, so new orders and forms are not being checked.', 'fraudnova' )
			. ' ' . __( 'Buy credits to resume fraud checks:', 'fraudnova' )
			. "\n\n" . self::BILLING_URL;

		wp_mail( (string) get_option( 'admin_email' ), __( 'FraudNova: your account is out of credits', 'fraudnova' ), $body );
	}
}
