<?php
/**
 * Last API error state.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Remembers the most recent blocking API error for admin notices.
 */
final class Error_State {

	const OPTION = 'fraudnova_last_error';

	/**
	 * Store an error, replacing any previous one.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human message.
	 * @return void
	 */
	public function set( string $code, string $message ): void {
		update_option(
			self::OPTION,
			array(
				'code'    => $code,
				'message' => $message,
				'time'    => time(),
			),
			false
		);
	}

	/**
	 * Current error.
	 *
	 * @return array|null
	 */
	public function get(): ?array {
		$value = get_option( self::OPTION, null );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Clear the error.
	 *
	 * @return void
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Whether the current error has this code.
	 *
	 * @param string $code Error code.
	 * @return bool
	 */
	public function is( string $code ): bool {
		$state = $this->get();
		return null !== $state && isset( $state['code'] ) && $code === $state['code'];
	}
}
