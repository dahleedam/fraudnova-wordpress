<?php
/**
 * Admin AJAX and form-post handlers.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Api_Response;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Scorer;

defined( 'ABSPATH' ) || exit;

/**
 * Connection test and activity-log clearing.
 */
class Ajax {

	const BALANCE_TRANSIENT = 'fraudnova_balance';

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
		add_action( 'wp_ajax_fraudnova_test_connection', array( $this, 'test_connection' ) );
		add_action( 'admin_post_fraudnova_clear_log', array( $this, 'clear_log' ) );
	}

	/**
	 * AJAX: check the API key against the balance endpoint.
	 *
	 * @return void
	 */
	public function test_connection(): void {
		check_ajax_referer( 'fraudnova_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'fraudnova' ) ), 403 );
			return;
		}

		$response = $this->scorer()->balance();

		if ( $response->ok ) {
			$credits = null !== $response->credits_remaining
				? $response->credits_remaining
				: ( isset( $response->data['credits_remaining'] ) ? (int) $response->data['credits_remaining'] : 0 );

			set_transient(
				self::BALANCE_TRANSIENT,
				array(
					'credits' => $credits,
					'time'    => time(),
				),
				10 * MINUTE_IN_SECONDS
			);

			wp_send_json_success(
				array(
					/* translators: %s: number of credits. */
					'message' => sprintf( __( 'Connected. %s credits remaining.', 'fraudnova' ), number_format_i18n( $credits ) ),
					'credits' => $credits,
				)
			);
			return;
		}

		wp_send_json_error( array( 'message' => self::failure_message( $response ) ) );
	}

	/**
	 * Friendly message for a failed connection test.
	 *
	 * @param Api_Response $response Response.
	 * @return string
	 */
	public static function failure_message( Api_Response $response ): string {
		switch ( $response->error_code ) {
			case 'no_api_key':
				return __( 'Enter your API key first.', 'fraudnova' );
			case 'unauthorized':
				return __( 'The API key was rejected.', 'fraudnova' );
			case 'transport':
				/* translators: %s: transport error message. */
				return sprintf( __( 'Could not reach fraudnova.com: %s', 'fraudnova' ), $response->message );
		}
		return '' !== $response->message ? $response->message : __( 'Unexpected response from FraudNova.', 'fraudnova' );
	}

	/**
	 * Form post: clear the activity log.
	 *
	 * @return void
	 */
	public function clear_log(): void {
		check_admin_referer( 'fraudnova_clear_log' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fraudnova' ), '', array( 'response' => 403 ) );
			return;
		}

		$this->plugin->log()->clear();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => Settings_Page::SLUG,
					'tab'     => 'activity',
					'cleared' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		$this->terminate();
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
