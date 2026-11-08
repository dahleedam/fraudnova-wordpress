<?php
/**
 * Scorer.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Scores one subject: cache, back-off, time budget, API call and error state.
 */
class Scorer {

	/**
	 * API client.
	 *
	 * @var Api_Client
	 */
	private $client;

	/**
	 * Cache.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Activity log (written by callers, kept for them).
	 *
	 * @var Activity_Log
	 */
	private $log;

	/**
	 * Error state.
	 *
	 * @var Error_State
	 */
	private $errors;

	/**
	 * Out-of-credits email, or null for none.
	 *
	 * @var Low_Credit_Alert|null
	 */
	private $alert;

	/**
	 * Constructor.
	 *
	 * @param Api_Client            $client   API client.
	 * @param Cache                 $cache    Cache.
	 * @param Settings              $settings Settings.
	 * @param Activity_Log          $log      Activity log.
	 * @param Error_State           $errors   Error state.
	 * @param Low_Credit_Alert|null $alert    Out-of-credits email.
	 */
	public function __construct( Api_Client $client, Cache $cache, Settings $settings, Activity_Log $log, Error_State $errors, ?Low_Credit_Alert $alert = null ) {
		$this->client   = $client;
		$this->cache    = $cache;
		$this->settings = $settings;
		$this->log      = $log;
		$this->errors   = $errors;
		$this->alert    = $alert;
	}

	/**
	 * Score a subject.
	 *
	 * @param string $type    ip|email|phone.
	 * @param string $value   Raw value.
	 * @param array  $options country, strictness, bypass_cache, budget.
	 * @return Check_Result
	 */
	public function score( string $type, string $value, array $options = array() ): Check_Result {
		$value = trim( $value );
		if ( '' === $value ) {
			return Check_Result::skipped( $type, 'empty' );
		}
		if ( Subject::PHONE === $type ) {
			$value = self::phone_for_api( $value );
			if ( '' === trim( $value, '+' ) ) {
				return Check_Result::skipped( $type, 'empty' );
			}
		}
		if ( Subject::IP === $type && Subject::is_private_ip( $value ) ) {
			return Check_Result::skipped( $type, 'private_address' );
		}
		if ( ! $this->settings->has_api_key() ) {
			return Check_Result::error( $type, 'no_api_key', __( 'No FraudNova API key is configured.', 'fraudnova' ) );
		}

		$budget = ( isset( $options['budget'] ) && $options['budget'] instanceof Time_Budget ) ? $options['budget'] : Time_Budget::unlimited();
		if ( $budget->exhausted() ) {
			return Check_Result::skipped( $type, 'time_budget' );
		}

		$wait = $this->cache->backoff_remaining();
		if ( $wait > 0 ) {
			/* translators: %d: seconds. */
			return Check_Result::error( $type, 'rate_limited', sprintf( __( 'Backing off for %d s', 'fraudnova' ), $wait ) );
		}

		$country    = isset( $options['country'] ) ? (string) $options['country'] : '';
		$normalized = Subject::normalize( $type, $value, $country );

		if ( empty( $options['bypass_cache'] ) ) {
			$cached = $this->cache->get( $type, $normalized );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$cap     = (int) max( 1, floor( min( $this->client->timeout(), $budget->remaining() ) ) );
		$limiter = static function () use ( $cap ) {
			return $cap;
		};
		add_filter( 'fraudnova_http_timeout', $limiter, 99 );
		try {
			if ( Subject::EMAIL === $type ) {
				$response = $this->client->check_email( $value );
			} elseif ( Subject::PHONE === $type ) {
				$response = $this->client->check_phone( $value, Subject::country( $country ) );
			} else {
				$strictness = isset( $options['strictness'] ) ? (int) $options['strictness'] : (int) $this->settings->get( 'strictness' );
				$response   = $this->client->check_ip( $value, array( 'strictness' => $strictness ) );
			}
		} finally {
			remove_filter( 'fraudnova_http_timeout', $limiter, 99 );
		}

		$this->track( $response );

		if ( $response->ok ) {
			$result = Check_Result::from_api( $type, $response->data );
			$this->cache->set( $type, $normalized, $result );
			return $result;
		}

		if ( 429 === $response->status ) {
			$this->cache->set_backoff( $response->retry_after ? $response->retry_after : 10 );
		}
		return Check_Result::error( $type, $response->error_code, $response->message );
	}

	/**
	 * A phone number as the API accepts it: digits, with at most one leading plus.
	 *
	 * @param string $value Raw phone number.
	 * @return string
	 */
	private static function phone_for_api( string $value ): string {
		$value = (string) preg_replace( '/[^0-9+]/', '', $value );
		if ( '' === $value ) {
			return '';
		}
		return substr( $value, 0, 1 ) . str_replace( '+', '', substr( $value, 1 ) );
	}

	/**
	 * Credit balance; ignores back-off and time budget.
	 *
	 * @return Api_Response
	 */
	public function balance(): Api_Response {
		$response = $this->client->balance();
		$this->track( $response );
		return $response;
	}

	/**
	 * Update the stored error state from a response.
	 *
	 * @param Api_Response $response Response.
	 * @return void
	 */
	private function track( Api_Response $response ): void {
		$blocking = array( 'unauthorized', 'insufficient_credits', 'no_api_key' );
		if ( $response->ok ) {
			$state = $this->errors->get();
			if ( null !== $state && isset( $state['code'] ) && in_array( $state['code'], $blocking, true ) ) {
				$this->errors->clear();
			}
			return;
		}
		if ( in_array( $response->error_code, $blocking, true ) ) {
			$this->errors->set( $response->error_code, $response->message );
		}
		if ( 'insufficient_credits' === $response->error_code && null !== $this->alert ) {
			$this->alert->maybe_send();
		}
	}
}
