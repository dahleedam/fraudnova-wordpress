<?php
/**
 * Client IP resolution.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the visitor IP according to the configured source. Forwarded-for
 * headers are never trusted; only the chosen source is read.
 */
final class Client_Ip {

	/**
	 * Resolve the client IP.
	 *
	 * @param Settings $settings Settings.
	 * @param mixed    $order    Optional WC_Order.
	 * @return string Valid IP or empty string.
	 */
	public static function resolve( Settings $settings, $order = null ): string {
		switch ( $settings->get( 'ip_source' ) ) {
			case 'cloudflare':
				$ip = self::valid( self::server( 'HTTP_CF_CONNECTING_IP' ) );
				if ( '' === $ip ) {
					$ip = self::valid( self::server( 'REMOTE_ADDR' ) );
				}
				break;
			case 'remote_addr':
				$ip = self::valid( self::server( 'REMOTE_ADDR' ) );
				break;
			default:
				if ( is_object( $order ) && method_exists( $order, 'get_customer_ip_address' ) ) {
					$ip = self::valid( (string) $order->get_customer_ip_address() );
				} elseif ( class_exists( 'WC_Geolocation' ) ) {
					$ip = self::valid( (string) \WC_Geolocation::get_ip_address() );
				} else {
					$ip = self::valid( self::server( 'REMOTE_ADDR' ) );
				}
		}
		return self::valid( (string) apply_filters( 'fraudnova_client_ip', $ip, $order ) );
	}

	/**
	 * Read a sanitized server variable.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function server( string $key ): string {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
	}

	/**
	 * Return the value when it is a valid IP, else an empty string.
	 *
	 * @param string $value Candidate.
	 * @return string
	 */
	private static function valid( string $value ): string {
		$value = trim( $value );
		return false !== filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}
}
