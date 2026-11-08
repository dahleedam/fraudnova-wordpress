<?php
/**
 * Activity log.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Newest-first, capped log of check outcomes, kept in a non-autoloaded option.
 * Callers mask the subject before recording; the log never stores raw values.
 */
final class Activity_Log {

	const OPTION = 'fraudnova_activity_log';
	const MAX    = 200;

	/**
	 * Record an event.
	 *
	 * @param array $event Event data; unknown keys are dropped.
	 * @return void
	 */
	public function record( array $event ): void {
		$row = array( 'time' => time() );
		foreach ( array( 'source', 'subject', 'type', 'level', 'action', 'message' ) as $key ) {
			$row[ $key ] = isset( $event[ $key ] ) && is_scalar( $event[ $key ] )
				? substr( sanitize_text_field( (string) $event[ $key ] ), 0, 200 )
				: '';
		}
		$row['score']    = isset( $event['score'] ) && is_numeric( $event['score'] ) ? (int) $event['score'] : null;
		$row['order_id'] = isset( $event['order_id'] ) && is_numeric( $event['order_id'] ) ? (int) $event['order_id'] : null;

		$rows = $this->all();
		array_unshift( $rows, $row );
		$rows = array_slice( $rows, 0, self::MAX );
		update_option( self::OPTION, $rows, false );

		if ( function_exists( 'wc_get_logger' ) ) {
			$line = sprintf(
				'%s %s %s: score=%s level=%s action=%s %s',
				$row['source'],
				$row['type'],
				$row['subject'],
				null === $row['score'] ? '-' : (string) $row['score'],
				$row['level'],
				$row['action'],
				$row['message']
			);
			wc_get_logger()->info( $line, array( 'source' => 'fraudnova' ) );
		}
	}

	/**
	 * All rows, newest first.
	 *
	 * @return array
	 */
	public function all(): array {
		$rows = get_option( self::OPTION, array() );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete all rows.
	 *
	 * @return void
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}
}
