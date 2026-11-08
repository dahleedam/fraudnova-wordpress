<?php
/**
 * FraudNova API client.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Thin transport over wp_remote_get(); no retries, sleeping or logging.
 */
class Api_Client {

	const DEFAULT_BASE_URL = 'https://fraudnova.com/api/v1';

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
	 * API base URL without trailing slash.
	 *
	 * @return string
	 */
	public function base_url(): string {
		return rtrim( (string) apply_filters( 'fraudnova_api_base_url', self::DEFAULT_BASE_URL ), '/' );
	}

	/**
	 * User-Agent header value.
	 *
	 * @return string
	 */
	public function user_agent(): string {
		$wc = defined( 'WC_VERSION' ) ? WC_VERSION : 'none';
		return 'FraudNova-WordPress/' . FRAUDNOVA_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . '; WooCommerce/' . $wc . ')';
	}

	/**
	 * Request timeout in seconds.
	 *
	 * @return int
	 */
	public function timeout(): int {
		return (int) apply_filters( 'fraudnova_http_timeout', 5 );
	}

	/**
	 * Check an IP address.
	 *
	 * @param string $ip   IP address.
	 * @param array  $args Optional strictness, user_agent, user_language.
	 * @return Api_Response
	 */
	public function check_ip( string $ip, array $args = array() ): Api_Response {
		$args  = (array) apply_filters( 'fraudnova_ip_request_args', $args, $ip );
		$query = array( 'ip' => $ip );
		foreach ( array( 'strictness', 'user_agent', 'user_language' ) as $key ) {
			if ( isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) && '' !== $args[ $key ] ) {
				$query[ $key ] = $args[ $key ];
			}
		}
		return $this->get( '/check', $query );
	}

	/**
	 * Check an email address.
	 *
	 * @param string $email Email address.
	 * @return Api_Response
	 */
	public function check_email( string $email ): Api_Response {
		return $this->get( '/check/email', array( 'email' => $email ) );
	}

	/**
	 * Check a phone number.
	 *
	 * @param string $phone   Phone number.
	 * @param string $country Optional two-letter country hint.
	 * @return Api_Response
	 */
	public function check_phone( string $phone, string $country = '' ): Api_Response {
		$query   = array( 'phone' => $phone );
		$country = strtoupper( trim( $country ) );
		if ( 1 === preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$query['country'] = $country;
		}
		return $this->get( '/check/phone', $query );
	}

	/**
	 * Credit balance.
	 *
	 * @return Api_Response
	 */
	public function balance(): Api_Response {
		return $this->get( '/balance', array() );
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $path  Path beginning with a slash.
	 * @param array  $query Query arguments.
	 * @return Api_Response
	 */
	private function get( string $path, array $query ): Api_Response {
		if ( ! $this->settings->has_api_key() ) {
			return Api_Response::failure( 'no_api_key', __( 'No FraudNova API key is configured.', 'fraudnova' ) );
		}

		$url = $this->base_url() . $path;
		if ( $query ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => $this->timeout(),
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $this->settings->api_key(),
					'Accept'        => 'application/json',
					'User-Agent'    => $this->user_agent(),
				),
			)
		);

		return Api_Response::from_wp( $response );
	}
}
