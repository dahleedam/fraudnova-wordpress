<?php
/**
 * Normalized API response.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable-by-convention result of one FraudNova API call.
 */
final class Api_Response {

	/**
	 * Whether the call succeeded.
	 *
	 * @var bool
	 */
	public bool $ok = false;

	/**
	 * HTTP status (0 when no response was received).
	 *
	 * @var int
	 */
	public int $status = 0;

	/**
	 * Response data payload.
	 *
	 * @var array
	 */
	public array $data = array();

	/**
	 * Error or informational message.
	 *
	 * @var string
	 */
	public string $message = '';

	/**
	 * Credits remaining, when reported.
	 *
	 * @var int|null
	 */
	public ?int $credits_remaining = null;

	/**
	 * Seconds to wait before retrying, when reported.
	 *
	 * @var int|null
	 */
	public ?int $retry_after = null;

	/**
	 * Machine-readable error code ('' on success).
	 *
	 * @var string
	 */
	public string $error_code = '';

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Build a failure that never reached (or could not use) the API.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return self
	 */
	public static function failure( string $code, string $message ): self {
		$r             = new self();
		$r->error_code = $code;
		$r->message    = $message;
		return $r;
	}

	/**
	 * Build from a wp_remote_get() result.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return self
	 */
	public static function from_wp( $response ): self {
		if ( is_wp_error( $response ) ) {
			return self::failure( 'transport', $response->get_error_message() );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$json    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$generic = __( 'Unexpected response from FraudNova.', 'fraudnova' );

		$r         = new self();
		$r->status = $status;

		$retry = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( is_scalar( $retry ) && is_numeric( $retry ) ) {
			$r->retry_after = max( 0, (int) $retry );
		}

		$is_envelope = is_array( $json ) && array_key_exists( 'success', $json );

		// Rate limits and server errors are mapped by status, whatever the body says.
		$forced = ( 429 === $status || ( $status >= 500 && $status <= 599 ) );

		if ( ! $forced && $is_envelope && true === $json['success'] ) {
			$r->ok   = true;
			$r->data = ( isset( $json['data'] ) && is_array( $json['data'] ) ) ? $json['data'] : array();
			if ( isset( $json['credits_remaining'] ) && is_numeric( $json['credits_remaining'] ) ) {
				$r->credits_remaining = (int) $json['credits_remaining'];
			}
			return $r;
		}

		$r->error_code = self::code_for_status( $status );
		if ( $is_envelope && isset( $json['message'] ) && is_string( $json['message'] ) && '' !== $json['message'] ) {
			$r->message = $json['message'];
		} else {
			$r->message = $generic;
		}
		return $r;
	}

	/**
	 * Map an HTTP status to an error code.
	 *
	 * @param int $status HTTP status.
	 * @return string
	 */
	private static function code_for_status( int $status ): string {
		$map = array(
			401 => 'unauthorized',
			402 => 'insufficient_credits',
			403 => 'forbidden',
			422 => 'invalid_input',
			429 => 'rate_limited',
		);
		if ( isset( $map[ $status ] ) ) {
			return $map[ $status ];
		}
		if ( $status >= 500 && $status <= 599 ) {
			return 'upstream';
		}
		return 'unexpected';
	}
}
