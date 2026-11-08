<?php
/**
 * Subject normalization and masking.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for the value being checked (ip, email or phone).
 */
final class Subject {

	const IP    = 'ip';
	const EMAIL = 'email';
	const PHONE = 'phone';

	/**
	 * Canonical form used for cache keys and API calls.
	 *
	 * @param string $type    ip|email|phone.
	 * @param string $value   Raw value.
	 * @param string $country Optional country hint (phone only).
	 * @return string
	 */
	public static function normalize( string $type, string $value, string $country = '' ): string {
		if ( self::EMAIL === $type ) {
			return strtolower( trim( $value ) );
		}
		if ( self::PHONE === $type ) {
			$digits = (string) preg_replace( '/\D+/', '', $value );
			$cc     = self::country( $country );
			return '' !== $cc ? $cc . ':' . $digits : $digits;
		}
		$ip = trim( $value );
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			if ( false !== $packed ) {
				$out = inet_ntop( $packed );
				if ( false !== $out ) {
					return $out;
				}
			}
		}
		return $ip;
	}

	/**
	 * Whether an IP is invalid, private, loopback, link-local or reserved.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_private_ip( string $ip ): bool {
		return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Uppercase two-letter country code, or empty string.
	 *
	 * @param string $country Raw country.
	 * @return string
	 */
	public static function country( string $country ): string {
		$country = strtoupper( trim( $country ) );
		return 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
	}

	/**
	 * Masked form safe for logs and the activity list.
	 *
	 * @param string $type  ip|email|phone.
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function mask( string $type, string $value ): string {
		$value = trim( $value );
		if ( self::EMAIL === $type ) {
			$at = strrpos( $value, '@' );
			if ( false === $at || 0 === $at ) {
				return '' === $value ? '***' : substr( $value, 0, 1 ) . '***';
			}
			return substr( $value, 0, 1 ) . '***' . substr( $value, $at );
		}
		if ( self::PHONE === $type ) {
			$digits = (string) preg_replace( '/\D+/', '', $value );
			$len    = strlen( $digits );
			if ( $len < 5 ) {
				return str_repeat( '*', max( 1, $len ) );
			}
			$plus = ( '' !== $value && '+' === $value[0] ) ? '+' : '';
			return $plus . str_repeat( '*', $len - 4 ) . substr( $digits, -4 );
		}
		if ( false !== filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $value );
			return $parts[0] . '.x.x.x';
		}
		if ( false !== filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $value );
			return ( '' === $parts[0] ? '0' : $parts[0] ) . ':x:x';
		}
		return '***';
	}
}
