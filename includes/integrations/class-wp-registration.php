<?php
/**
 * WordPress registration form protection.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Integrations;

use FraudNova\WordPress\Form_Check;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Request_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks risky sign-ups on the core registration form.
 */
final class Wp_Registration {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Form check.
	 *
	 * @var Form_Check
	 */
	private $check;

	/**
	 * Constructor.
	 *
	 * @param Plugin          $plugin Plugin.
	 * @param Form_Check|null $check  Form check (tests).
	 */
	public function __construct( Plugin $plugin, ?Form_Check $check = null ) {
		$this->plugin = $plugin;
		$this->check  = null !== $check ? $check : new Form_Check( $plugin );
	}

	/**
	 * Register the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'registration_errors', array( $this, 'filter' ), 20, 3 );
	}

	/**
	 * Registration errors filter.
	 *
	 * @param mixed  $errors WP_Error.
	 * @param string $login  Login.
	 * @param string $email  Email.
	 * @return mixed
	 */
	public function filter( $errors, $login = '', $email = '' ) {
		if ( ! is_object( $errors ) || ! method_exists( $errors, 'add' ) || Request_Context::is_checkout() ) {
			return $errors;
		}
		if ( $this->check->assess( 'wp_registration', (string) $email )->blocked ) {
			$errors->add( 'fraudnova_blocked', (string) $this->plugin->settings()->get( 'form_block_message' ) );
		}
		return $errors;
	}
}
