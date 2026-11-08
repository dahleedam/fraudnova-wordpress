<?php
/**
 * Normalized check result.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Whitelisted, storage-safe outcome of one check.
 */
final class Check_Result {

	const STATUS_OK      = 'ok';
	const STATUS_CACHED  = 'cached';
	const STATUS_SKIPPED = 'skipped';
	const STATUS_ERROR   = 'error';

	/**
	 * Per-type whitelist of boolean flags.
	 */
	const FLAGS = array(
		'ip'    => array( 'proxy', 'vpn', 'tor', 'active_vpn', 'active_tor', 'recent_abuse', 'bot_status' ),
		'email' => array( 'valid', 'disposable', 'recent_abuse', 'honeypot', 'leaked' ),
		'phone' => array( 'valid', 'active', 'VOIP', 'prepaid', 'risky', 'recent_abuse' ),
	);

	/**
	 * Per-type whitelist of detail fields.
	 */
	const DETAIL = array(
		'ip'    => array( 'country_code', 'region', 'city', 'ISP' ),
		'email' => array( 'deliverability', 'spam_trap_score', 'first_seen' ),
		'phone' => array( 'carrier', 'line_type', 'country' ),
	);

	/**
	 * Check type.
	 *
	 * @var string
	 */
	public string $type = '';

	/**
	 * Status.
	 *
	 * @var string
	 */
	public string $status = self::STATUS_OK;

	/**
	 * Fraud score.
	 *
	 * @var int|null
	 */
	public ?int $score = null;

	/**
	 * Boolean flags.
	 *
	 * @var array
	 */
	public array $flags = array();

	/**
	 * Detail fields.
	 *
	 * @var array
	 */
	public array $detail = array();

	/**
	 * Skip reason or error message.
	 *
	 * @var string
	 */
	public string $message = '';

	/**
	 * Error code.
	 *
	 * @var string
	 */
	public string $error_code = '';

	/**
	 * Use the named constructors.
	 */
	private function __construct() {}

	/**
	 * Build from an API data payload.
	 *
	 * @param string $type ip|email|phone.
	 * @param array  $data API data array.
	 * @return self
	 */
	public static function from_api( string $type, array $data ): self {
		$r         = new self();
		$r->type   = $type;
		$r->status = self::STATUS_OK;

		if ( isset( $data['fraud_score'] ) && is_numeric( $data['fraud_score'] ) ) {
			$r->score = (int) round( (float) $data['fraud_score'] );
		}

		$flags = isset( self::FLAGS[ $type ] ) ? self::FLAGS[ $type ] : array();
		foreach ( $flags as $name ) {
			if ( array_key_exists( $name, $data ) ) {
				$r->flags[ $name ] = (bool) $data[ $name ];
			}
		}

		$details = isset( self::DETAIL[ $type ] ) ? self::DETAIL[ $type ] : array();
		foreach ( $details as $name ) {
			if ( ! array_key_exists( $name, $data ) ) {
				continue;
			}
			$value = $data[ $name ];
			if ( is_array( $value ) ) {
				if ( 'first_seen' === $name && isset( $value['human'] ) && is_scalar( $value['human'] ) ) {
					$r->detail[ $name ] = (string) $value['human'];
				}
				continue;
			}
			if ( is_int( $value ) ) {
				$r->detail[ $name ] = $value;
			} elseif ( is_scalar( $value ) ) {
				$r->detail[ $name ] = (string) $value;
			}
		}

		return $r;
	}

	/**
	 * A skipped check.
	 *
	 * @param string $type   Type.
	 * @param string $reason Reason.
	 * @return self
	 */
	public static function skipped( string $type, string $reason ): self {
		$r          = new self();
		$r->type    = $type;
		$r->status  = self::STATUS_SKIPPED;
		$r->message = $reason;
		return $r;
	}

	/**
	 * A failed check.
	 *
	 * @param string $type    Type.
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return self
	 */
	public static function error( string $type, string $code, string $message ): self {
		$r             = new self();
		$r->type       = $type;
		$r->status     = self::STATUS_ERROR;
		$r->error_code = $code;
		$r->message    = $message;
		return $r;
	}

	/**
	 * Whether a flag is set (false when absent).
	 *
	 * @param string $name Flag.
	 * @return bool
	 */
	public function flag( string $name ): bool {
		return ! empty( $this->flags[ $name ] );
	}

	/**
	 * Whether a flag is present at all.
	 *
	 * @param string $name Flag.
	 * @return bool
	 */
	public function has_flag( string $name ): bool {
		return array_key_exists( $name, $this->flags );
	}

	/**
	 * Whether this result consumed a check (ok or cached).
	 *
	 * @return bool
	 */
	public function counted(): bool {
		return self::STATUS_OK === $this->status || self::STATUS_CACHED === $this->status;
	}

	/**
	 * Copy marked as served from cache.
	 *
	 * @return self
	 */
	public function as_cached(): self {
		$c         = clone $this;
		$c->status = self::STATUS_CACHED;
		return $c;
	}

	/**
	 * Array form for storage.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'type'       => $this->type,
			'status'     => $this->status,
			'score'      => $this->score,
			'flags'      => $this->flags,
			'detail'     => $this->detail,
			'message'    => $this->message,
			'error_code' => $this->error_code,
		);
	}

	/**
	 * Rebuild from stored array (tolerates missing keys).
	 *
	 * @param array $a Stored array.
	 * @return self
	 */
	public static function from_array( array $a ): self {
		$r             = new self();
		$r->type       = isset( $a['type'] ) ? (string) $a['type'] : '';
		$r->status     = isset( $a['status'] ) ? (string) $a['status'] : self::STATUS_OK;
		$r->score      = isset( $a['score'] ) && is_numeric( $a['score'] ) ? (int) $a['score'] : null;
		$r->flags      = isset( $a['flags'] ) && is_array( $a['flags'] ) ? $a['flags'] : array();
		$r->detail     = isset( $a['detail'] ) && is_array( $a['detail'] ) ? $a['detail'] : array();
		$r->message    = isset( $a['message'] ) ? (string) $a['message'] : '';
		$r->error_code = isset( $a['error_code'] ) ? (string) $a['error_code'] : '';
		return $r;
	}
}
