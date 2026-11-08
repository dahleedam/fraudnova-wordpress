<?php
/**
 * Transient cache for check results.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Result cache and API back-off marker.
 */
final class Cache {

	const PREFIX      = 'fraudnova_';
	const BACKOFF_KEY = 'fraudnova_backoff';
	const MAX_BACKOFF = 60;

	/**
	 * Cache key; never contains the raw subject.
	 *
	 * @param string $type       Type.
	 * @param string $normalized Normalized subject.
	 * @return string
	 */
	public static function key( string $type, string $normalized ): string {
		return self::PREFIX . $type . '_' . sha1( $normalized );
	}

	/**
	 * Cache lifetime in seconds.
	 *
	 * @return int
	 */
	public function ttl(): int {
		return (int) apply_filters( 'fraudnova_cache_ttl', DAY_IN_SECONDS );
	}

	/**
	 * Cached result, if any.
	 *
	 * @param string $type       Type.
	 * @param string $normalized Normalized subject.
	 * @return Check_Result|null
	 */
	public function get( string $type, string $normalized ): ?Check_Result {
		$stored = get_transient( self::key( $type, $normalized ) );
		if ( ! is_array( $stored ) ) {
			return null;
		}
		return Check_Result::from_array( $stored )->as_cached();
	}

	/**
	 * Store a successful result.
	 *
	 * @param string       $type       Type.
	 * @param string       $normalized Normalized subject.
	 * @param Check_Result $result     Result.
	 */
	public function set( string $type, string $normalized, Check_Result $result ): void {
		if ( Check_Result::STATUS_OK !== $result->status ) {
			return;
		}
		set_transient( self::key( $type, $normalized ), $result->to_array(), $this->ttl() );
	}

	/**
	 * Seconds of back-off left.
	 *
	 * @return int
	 */
	public function backoff_remaining(): int {
		return max( 0, (int) get_transient( self::BACKOFF_KEY ) - time() );
	}

	/**
	 * Pause API calls for a number of seconds (1..60).
	 *
	 * @param int $seconds Seconds.
	 */
	public function set_backoff( int $seconds ): void {
		$seconds = max( 1, min( self::MAX_BACKOFF, $seconds ) );
		set_transient( self::BACKOFF_KEY, time() + $seconds, $seconds );
	}

	/**
	 * Clear the back-off marker.
	 */
	public function clear_backoff(): void {
		delete_transient( self::BACKOFF_KEY );
	}
}
