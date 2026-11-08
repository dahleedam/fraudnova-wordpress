<?php
/**
 * Minimal WP_Error stub.
 *
 * @package FraudNova
 */

// phpcs:ignoreFile

class WP_Error {

	/** @var array<string, string[]> */
	public $errors = array();

	/** @var array<string, mixed> */
	public $error_data = array();

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' === $code ) {
			return;
		}
		$this->errors[ $code ][] = $message;
		if ( '' !== $data ) {
			$this->error_data[ $code ] = $data;
		}
	}

	public function get_error_code() {
		$codes = array_keys( $this->errors );
		return $codes ? $codes[0] : '';
	}

	public function get_error_message( $code = '' ) {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}
		return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
	}

	public function add( $code, $message ) {
		$this->errors[ $code ][] = $message;
	}

	public function get_error_codes() {
		return array_keys( $this->errors );
	}

	public function has_errors() {
		return ! empty( $this->errors );
	}
}
