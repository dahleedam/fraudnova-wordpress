<?php
/**
 * Admin notices.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Shows account problems (rejected key, no credits, missing key) to administrators.
 */
class Notices {

	const BILLING_URL = 'https://fraudnova.com/dashboard/billing';

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
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Render the notice for the current state, if any.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->plugin->settings();

		if ( ! $settings->has_api_key() ) {
			if ( $this->any_feature_enabled() && in_array( $this->screen_id(), array( 'plugins', 'settings_page_' . Settings_Page::SLUG ), true ) ) {
				$this->output(
					'info',
					sprintf(
						/* translators: %s: settings page URL. */
						__( 'FraudNova needs an API key. <a href="%s">Add it in Settings → FraudNova</a>.', 'fraudnova' ),
						esc_url( Settings_Page::url() )
					)
				);
			}
			return;
		}

		$state = $this->plugin->error_state()->get();
		$code  = ( null !== $state && isset( $state['code'] ) ) ? (string) $state['code'] : '';

		if ( 'unauthorized' === $code ) {
			$html = sprintf(
				/* translators: %s: settings page URL. */
				__( 'FraudNova: your API key was rejected. <a href="%s">Check the settings</a>.', 'fraudnova' ),
				esc_url( Settings_Page::url() )
			);
			$this->output( 'error', $html . $this->detail( $state ) );
			return;
		}

		if ( 'insufficient_credits' === $code ) {
			$this->output(
				'warning',
				sprintf(
					/* translators: %s: billing page URL. */
					__( 'FraudNova: your account is out of credits, so checks are skipped and orders are not held. <a href="%s">Buy credits</a>.', 'fraudnova' ),
					esc_url( self::BILLING_URL )
				)
			);
		}
	}

	/**
	 * Escaped API message, appended to a notice.
	 *
	 * @param array|null $state Error state.
	 * @return string
	 */
	private function detail( ?array $state ): string {
		if ( null === $state || empty( $state['message'] ) || ! is_scalar( $state['message'] ) ) {
			return '';
		}
		return ' <em>' . esc_html( (string) $state['message'] ) . '</em>';
	}

	/**
	 * Print one notice.
	 *
	 * @param string $type    info|warning|error.
	 * @param string $message Message HTML (already escaped where needed).
	 * @return void
	 */
	private function output( string $type, string $message ): void {
		echo wp_kses_post( sprintf( '<div class="notice notice-%s"><p>%s</p></div>', $type, $message ) );
	}

	/**
	 * Whether any feature that needs the API is turned on.
	 *
	 * @return bool
	 */
	private function any_feature_enabled(): bool {
		$settings = $this->plugin->settings();
		foreach ( array( 'orders_enabled', 'form_wp_registration', 'form_wc_registration', 'form_cf7' ) as $key ) {
			if ( $settings->get( $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Current admin screen id.
	 *
	 * @return string
	 */
	protected function screen_id(): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return ( is_object( $screen ) && isset( $screen->id ) ) ? (string) $screen->id : '';
	}
}
