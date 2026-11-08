<?php
/**
 * Contact Form 7 protection.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Integrations;

use FraudNova\WordPress\Form_Check;
use FraudNova\WordPress\Plugin;
use FraudNova\WordPress\Reasons;

defined( 'ABSPATH' ) || exit;

/**
 * Marks risky Contact Form 7 submissions as spam.
 */
final class Contact_Form_7 {

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
		add_filter( 'wpcf7_spam', array( $this, 'filter' ), 10, 2 );
	}

	/**
	 * Spam filter.
	 *
	 * @param bool  $spam       Whether already spam.
	 * @param mixed $submission WPCF7_Submission (absent in very old versions).
	 * @return bool
	 */
	public function filter( $spam, $submission = null ) {
		if ( $spam ) {
			return true;
		}
		try {
			if ( ! is_object( $submission ) && class_exists( 'WPCF7_Submission' ) ) {
				$submission = \WPCF7_Submission::get_instance();
			}
			if ( ! is_object( $submission ) ) {
				return $spam;
			}
			$assessment = $this->check->assess( 'cf7', $this->email_of( $submission ) );
			if ( ! $assessment->blocked ) {
				return $spam;
			}
			if ( method_exists( $submission, 'add_spam_log' ) ) {
				$submission->add_spam_log(
					array(
						'agent'  => 'fraudnova',
						'reason' => 'FraudNova: ' . implode( ', ', array_map( array( Reasons::class, 'label' ), $assessment->reasons ) ),
					)
				);
			}
			return true;
		} catch ( \Throwable $e ) {
			return $spam;
		}
	}

	/**
	 * First non-empty value among the form's email fields.
	 *
	 * @param object $submission Submission.
	 * @return string
	 */
	private function email_of( $submission ): string {
		$form = method_exists( $submission, 'get_contact_form' ) ? $submission->get_contact_form() : null;
		$data = method_exists( $submission, 'get_posted_data' ) ? $submission->get_posted_data() : array();
		if ( ! is_object( $form ) || ! method_exists( $form, 'scan_form_tags' ) || ! is_array( $data ) ) {
			return '';
		}
		foreach ( (array) $form->scan_form_tags( array( 'basetype' => 'email' ) ) as $tag ) {
			$name = is_object( $tag ) && isset( $tag->name ) ? (string) $tag->name : '';
			if ( '' === $name || ! isset( $data[ $name ] ) ) {
				continue;
			}
			$value = $data[ $name ];
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}
		return '';
	}
}
