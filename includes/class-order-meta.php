<?php
/**
 * Order meta storage.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the FraudNova assessment on a WooCommerce order.
 * Works through the order's meta API, so it is HPOS compatible.
 */
final class Order_Meta {

	const CHECKED_AT       = '_fraudnova_checked_at';
	const VERSION          = '_fraudnova_version';
	const SCORE            = '_fraudnova_score';
	const LEVEL            = '_fraudnova_level';
	const REASONS          = '_fraudnova_reasons';
	const ACTION           = '_fraudnova_action';
	const REQUESTED_ACTION = '_fraudnova_requested_action';
	const TEST_MODE        = '_fraudnova_test_mode';
	const ACTION_PENDING   = '_fraudnova_action_pending';
	const ACTION_APPLIED   = '_fraudnova_action_applied';
	const RESULT_IP        = '_fraudnova_ip';
	const RESULT_EMAIL     = '_fraudnova_email';
	const RESULT_PHONE     = '_fraudnova_phone';

	/**
	 * Map of check type => result meta key.
	 *
	 * @return array
	 */
	private static function result_keys(): array {
		return array(
			Subject::IP    => self::RESULT_IP,
			Subject::PHONE => self::RESULT_PHONE,
			Subject::EMAIL => self::RESULT_EMAIL,
		);
	}

	/**
	 * Every meta key this plugin writes.
	 *
	 * @return string[]
	 */
	public static function all_keys(): array {
		return array(
			self::CHECKED_AT,
			self::VERSION,
			self::SCORE,
			self::LEVEL,
			self::REASONS,
			self::ACTION,
			self::REQUESTED_ACTION,
			self::TEST_MODE,
			self::ACTION_PENDING,
			self::ACTION_APPLIED,
			self::RESULT_IP,
			self::RESULT_EMAIL,
			self::RESULT_PHONE,
		);
	}

	/**
	 * Write an assessment and its results. Does not save the order.
	 *
	 * @param mixed            $order   WC_Order.
	 * @param Order_Assessment $a       Assessment.
	 * @param array            $results Map of type => Check_Result.
	 * @return void
	 */
	public static function write( $order, Order_Assessment $a, array $results ): void {
		$order->update_meta_data( self::CHECKED_AT, gmdate( 'c' ) );
		$order->update_meta_data( self::VERSION, FRAUDNOVA_VERSION );
		$order->update_meta_data( self::SCORE, null === $a->score ? '' : $a->score );
		$order->update_meta_data( self::LEVEL, $a->level );
		$order->update_meta_data( self::REASONS, $a->reasons );
		$order->update_meta_data( self::ACTION, $a->action );
		$order->update_meta_data( self::REQUESTED_ACTION, $a->requested_action );
		$order->update_meta_data( self::TEST_MODE, $a->test_mode );

		if ( Order_Assessment::ACTION_HOLD === $a->action || Order_Assessment::ACTION_CANCEL === $a->action ) {
			$order->update_meta_data( self::ACTION_PENDING, $a->action );
		} else {
			$order->delete_meta_data( self::ACTION_PENDING );
		}

		foreach ( self::result_keys() as $type => $key ) {
			if ( isset( $results[ $type ] ) && $results[ $type ] instanceof Check_Result ) {
				$order->update_meta_data( $key, $results[ $type ]->to_array() );
			} else {
				$order->delete_meta_data( $key );
			}
		}
	}

	/**
	 * Read the stored assessment.
	 *
	 * @param mixed $order WC_Order.
	 * @return array|null Null when the order was never checked.
	 */
	public static function read( $order ): ?array {
		$checked_at = (string) $order->get_meta( self::CHECKED_AT );
		if ( '' === $checked_at ) {
			return null;
		}

		$level      = (string) $order->get_meta( self::LEVEL );
		$assessment = Order_Assessment::from_meta(
			array(
				'level'            => '' === $level ? Order_Assessment::LEVEL_UNKNOWN : $level,
				'score'            => $order->get_meta( self::SCORE ),
				'reasons'          => $order->get_meta( self::REASONS ),
				'action'           => $order->get_meta( self::ACTION ),
				'requested_action' => $order->get_meta( self::REQUESTED_ACTION ),
				'test_mode'        => $order->get_meta( self::TEST_MODE ),
			)
		);

		$results = array();
		foreach ( self::result_keys() as $type => $key ) {
			$stored = $order->get_meta( $key );
			if ( is_array( $stored ) ) {
				$results[ $type ] = Check_Result::from_array( $stored );
			}
		}

		return array(
			'assessment' => $assessment,
			'results'    => $results,
			'checked_at' => $checked_at,
		);
	}

	/**
	 * Whether the order has been checked (or a check has started).
	 *
	 * @param mixed $order WC_Order.
	 * @return bool
	 */
	public static function is_checked( $order ): bool {
		return '' !== (string) $order->get_meta( self::CHECKED_AT );
	}

	/**
	 * Mark a check as started and save, so a second hook firing skips it.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 */
	public static function mark_checking( $order ): void {
		$order->update_meta_data( self::CHECKED_AT, gmdate( 'c' ) );
		$order->save();
	}

	/**
	 * Remove every FraudNova meta key. Does not save the order.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 */
	public static function delete_all( $order ): void {
		foreach ( self::all_keys() as $key ) {
			$order->delete_meta_data( $key );
		}
	}
}
