<?php
/**
 * Plugin settings: defaults, sanitizing and access.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for settings and their defaults.
 */
class Settings {

	const OPTION = 'fraudnova_settings';

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'api_key'                    => '',
			'test_mode'                  => true,
			'low_credit_email'           => true,
			'delete_on_uninstall'        => false,
			'orders_enabled'             => true,
			'order_payment_methods'      => null,
			'ip_source'                  => 'woocommerce',
			'check_ip'                   => true,
			'check_phone'                => true,
			'check_email'                => true,
			'strictness'                 => 1,
			'review_threshold'           => 60,
			'hold_threshold'             => 85,
			'rule_phone_invalid'         => 'high',
			'rule_phone_voip'            => 'review',
			'rule_email_disposable'      => 'review',
			'rule_ip_proxy'              => 'review',
			'rule_ip_abuse'              => 'review',
			'rule_email_abuse'           => 'review',
			'review_action'              => 'flag',
			'high_action'                => 'hold',
			'suppress_hold_email'        => true,
			'on_error'                   => 'allow',
			'form_wp_registration'       => true,
			'form_wc_registration'       => true,
			'form_cf7'                   => true,
			'form_check_ip'              => true,
			'form_check_email'           => true,
			'form_block_threshold'       => 85,
			'form_rule_email_disposable' => true,
			'form_rule_email_invalid'    => false,
			'form_rule_ip_proxy'         => false,
			'form_rule_ip_abuse'         => true,
			'form_block_message'         => 'Registration could not be completed. Please contact us if you believe this is a mistake.',
		);
	}

	/**
	 * Allowed values for select fields.
	 *
	 * @param string $key Setting key.
	 * @return string[] Empty for keys that are not enums.
	 */
	public function enum_values( string $key ): array {
		if ( 0 === strpos( $key, 'rule_' ) ) {
			return array( 'off', 'review', 'high' );
		}
		switch ( $key ) {
			case 'ip_source':
				return array( 'woocommerce', 'remote_addr', 'cloudflare' );
			case 'review_action':
				return array( 'flag', 'hold' );
			case 'high_action':
				return array( 'flag', 'hold', 'cancel' );
			case 'on_error':
				return array( 'allow', 'review' );
		}
		return array();
	}

	/**
	 * Inclusive integer ranges.
	 *
	 * @return array
	 */
	private function ranges(): array {
		return array(
			'strictness'           => array( 0, 3 ),
			'review_threshold'     => array( 1, 100 ),
			'hold_threshold'       => array( 1, 100 ),
			'form_block_threshold' => array( 1, 100 ),
		);
	}

	/**
	 * All settings: stored option merged over defaults, unknown keys dropped.
	 *
	 * @return array
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$out = self::defaults();
		foreach ( $out as $key => $default ) {
			if ( array_key_exists( $key, $stored ) ) {
				$out[ $key ] = $stored[ $key ];
			}
		}
		return $out;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null for unknown keys.
	 */
	public function get( string $key ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * The FRAUDNOVA_API_KEY constant, when defined and non-empty.
	 *
	 * @return string|null
	 */
	protected function constant_api_key(): ?string {
		if ( defined( 'FRAUDNOVA_API_KEY' ) && is_string( FRAUDNOVA_API_KEY ) && '' !== trim( FRAUDNOVA_API_KEY ) ) {
			return trim( FRAUDNOVA_API_KEY );
		}
		return null;
	}

	/**
	 * Effective API key (constant wins over option).
	 *
	 * @return string
	 */
	public function api_key(): string {
		$const = $this->constant_api_key();
		if ( null !== $const ) {
			return $const;
		}
		return trim( (string) $this->get( 'api_key' ) );
	}

	/**
	 * Whether the key comes from the wp-config constant.
	 *
	 * @return bool
	 */
	public function api_key_from_constant(): bool {
		return null !== $this->constant_api_key();
	}

	/**
	 * Whether an API key is configured.
	 *
	 * @return bool
	 */
	public function has_api_key(): bool {
		return '' !== $this->api_key();
	}

	/**
	 * Whether test mode is on.
	 *
	 * @return bool
	 */
	public function test_mode(): bool {
		return (bool) $this->get( 'test_mode' );
	}

	/**
	 * Settings API callback: returns the full sanitized settings array.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$ranges = $this->ranges();
		$out    = array();

		foreach ( self::defaults() as $key => $default ) {
			$present = array_key_exists( $key, $input );
			$value   = $present ? $input[ $key ] : null;

			if ( 'order_payment_methods' === $key ) {
				$out[ $key ] = $this->sanitize_payment_methods( $value );
			} elseif ( 'form_block_message' === $key ) {
				$msg         = $present && is_scalar( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
				$out[ $key ] = '' === $msg ? $default : $msg;
			} elseif ( 'api_key' === $key ) {
				$out[ $key ] = $present && is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
			} elseif ( is_bool( $default ) ) {
				$out[ $key ] = ! empty( $value );
			} elseif ( is_int( $default ) ) {
				list( $min, $max ) = $ranges[ $key ];
				$number            = $present && is_scalar( $value ) ? absint( $value ) : $default;
				$out[ $key ]       = max( $min, min( $max, $number ) );
			} else {
				$allowed     = $this->enum_values( $key );
				$out[ $key ] = ( $present && is_scalar( $value ) && in_array( (string) $value, $allowed, true ) ) ? (string) $value : $default;
			}
		}

		if ( $out['hold_threshold'] < $out['review_threshold'] ) {
			$out['hold_threshold'] = $out['review_threshold'];
		}

		return $out;
	}

	/**
	 * Sanitize the payment-method selection.
	 *
	 * @param mixed $value Raw value.
	 * @return string|string[]|null 'all', a list of gateway ids, or null for auto-detect.
	 */
	private function sanitize_payment_methods( $value ) {
		if ( 'all' === $value ) {
			return 'all';
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$ids = array_values(
			array_filter(
				array_map(
					function ( $id ) {
						return is_scalar( $id ) ? sanitize_key( (string) $id ) : '';
					},
					$value
				)
			)
		);
		return array() === $ids ? null : $ids;
	}

	/**
	 * Merge values over current settings, sanitize and save.
	 *
	 * @param array $values Values to change.
	 * @return void
	 */
	public function update( array $values ): void {
		update_option( self::OPTION, $this->sanitize( array_merge( $this->all(), $values ) ) );
	}
}
