<?php
/**
 * Human-readable risk reasons.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Translates reason keys into labels.
 */
final class Reasons {

	/**
	 * Label for a reason key.
	 *
	 * @param string $key Reason key (e.g. ip_proxy, score_91).
	 * @return string Unknown keys are returned unchanged.
	 */
	public static function label( string $key ): string {
		switch ( $key ) {
			case 'phone_invalid':
				return __( 'Phone number invalid', 'fraudnova' );
			case 'phone_voip':
				return __( 'VOIP or risky phone number', 'fraudnova' );
			case 'email_disposable':
				return __( 'Disposable or invalid email', 'fraudnova' );
			case 'email_abuse':
				return __( 'Email linked to recent abuse', 'fraudnova' );
			case 'ip_proxy':
				return __( 'Proxy, VPN or Tor', 'fraudnova' );
			case 'ip_abuse':
				return __( 'IP linked to recent abuse or bots', 'fraudnova' );
			case 'api_error':
				return __( 'A check could not be completed', 'fraudnova' );
		}
		if ( 1 === preg_match( '/^score_(\d+)$/', $key, $m ) ) {
			/* translators: %d: fraud score. */
			return sprintf( __( 'Fraud score %d', 'fraudnova' ), (int) $m[1] );
		}
		return $key;
	}

	/**
	 * Signal keys that fire across the counted results.
	 *
	 * @param array $results Map of type => Check_Result.
	 * @return string[] Any of phone_invalid, phone_voip, email_disposable, email_invalid, email_abuse, ip_proxy, ip_abuse.
	 */
	public static function signals( array $results ): array {
		$fired = array();
		foreach ( $results as $result ) {
			if ( ! $result instanceof Check_Result || ! $result->counted() ) {
				continue;
			}
			if ( Subject::PHONE === $result->type ) {
				if ( $result->has_flag( 'valid' ) && ! $result->flag( 'valid' ) ) {
					$fired[] = 'phone_invalid';
				}
				if ( $result->flag( 'VOIP' ) || $result->flag( 'risky' ) ) {
					$fired[] = 'phone_voip';
				}
			} elseif ( Subject::EMAIL === $result->type ) {
				if ( $result->flag( 'disposable' ) ) {
					$fired[] = 'email_disposable';
				}
				if ( $result->has_flag( 'valid' ) && ! $result->flag( 'valid' ) ) {
					$fired[] = 'email_invalid';
				}
				if ( $result->flag( 'recent_abuse' ) || $result->flag( 'honeypot' ) ) {
					$fired[] = 'email_abuse';
				}
			} elseif ( Subject::IP === $result->type ) {
				foreach ( array( 'proxy', 'vpn', 'tor', 'active_vpn', 'active_tor' ) as $flag ) {
					if ( $result->flag( $flag ) ) {
						$fired[] = 'ip_proxy';
						break;
					}
				}
				if ( $result->flag( 'recent_abuse' ) || $result->flag( 'bot_status' ) ) {
					$fired[] = 'ip_abuse';
				}
			}
		}
		return array_values( array_unique( $fired ) );
	}

	/**
	 * Highest score among counted results.
	 *
	 * @param array $results Map of type => Check_Result.
	 * @return int|null Null when nothing counted or no score.
	 */
	public static function max_score( array $results ): ?int {
		$max = null;
		foreach ( $results as $result ) {
			if ( $result instanceof Check_Result && $result->counted() && null !== $result->score ) {
				$max = null === $max ? $result->score : max( $max, $result->score );
			}
		}
		return $max;
	}
}
