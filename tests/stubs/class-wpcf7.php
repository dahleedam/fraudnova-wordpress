<?php
/**
 * Minimal Contact Form 7 stubs.
 *
 * @package FraudNova
 */

// phpcs:ignoreFile

class WPCF7_ContactForm {

	/** @var object[] */
	public $tags;

	public function __construct( array $tags = array() ) {
		$this->tags = $tags;
	}

	public function scan_form_tags( $cond = null ) {
		$out = array();
		foreach ( $this->tags as $tag ) {
			if ( is_array( $cond ) && isset( $cond['basetype'] ) && $tag->basetype !== $cond['basetype'] ) {
				continue;
			}
			$out[] = $tag;
		}
		return $out;
	}
}

class WPCF7_Submission {

	/** @var array */
	public $posted;

	/** @var WPCF7_ContactForm */
	public $form;

	/** @var array[] */
	public $spam_log = array();

	public function __construct( array $posted, WPCF7_ContactForm $form ) {
		$this->posted = $posted;
		$this->form   = $form;
	}

	public function get_posted_data() {
		return $this->posted;
	}

	public function get_contact_form() {
		return $this->form;
	}

	public function add_spam_log( $log ) {
		$this->spam_log[] = $log;
	}
}
