<?php
/**
 * Exception used to stop handlers at wp_send_json_* like the real functions do.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

/**
 * Carries the JSON payload a handler tried to send.
 */
class JsonResponseException extends \RuntimeException {

	/**
	 * Whether it was a success response.
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Payload.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * HTTP status, when given.
	 *
	 * @var int|null
	 */
	public $status;

	/**
	 * Constructor.
	 *
	 * @param bool     $success Success flag.
	 * @param mixed    $data    Payload.
	 * @param int|null $status  HTTP status.
	 */
	public function __construct( bool $success, $data, $status = null ) {
		parent::__construct( $success ? 'json success' : 'json error' );
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
	}
}
