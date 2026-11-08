<?php
/**
 * Request context helpers.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Detects checkout and REST requests.
 */
final class Request_Context {

	/**
	 * Whether this is a REST request.
	 *
	 * @return bool
	 */
	public static function is_rest(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Whether the current request is a WooCommerce checkout (classic or Store API).
	 *
	 * @return bool
	 */
	public static function is_checkout(): bool {
		if ( did_action( 'woocommerce_before_checkout_process' ) > 0 ) {
			return true;
		}
		if ( ! self::is_rest() ) {
			return false;
		}
		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) && false !== strpos( (string) $GLOBALS['wp']->query_vars['rest_route'], '/wc/store' ) ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return false !== strpos( $uri, '/wc/store' );
	}
}
