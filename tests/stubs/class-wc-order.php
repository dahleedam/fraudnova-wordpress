<?php
/**
 * Minimal WC_Order stub.
 *
 * @package FraudNova
 */

// phpcs:ignoreFile

class WC_Order {

	/** @var int */
	public $id;

	/** @var array */
	public $props;

	/** @var array */
	public $meta = array();

	/** @var int */
	public $saves = 0;

	/** @var array */
	public $status_changes = array();

	/** @var array */
	public $notes = array();

	public function __construct( int $id = 1, array $props = array() ) {
		$this->id    = $id;
		$this->props = $props;
		if ( isset( $props['meta'] ) && is_array( $props['meta'] ) ) {
			$this->meta = $props['meta'];
		}
	}

	private function prop( string $key, $default = '' ) {
		return array_key_exists( $key, $this->props ) ? $this->props[ $key ] : $default;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_billing_email() {
		return $this->prop( 'billing_email' );
	}

	public function get_billing_phone() {
		return $this->prop( 'billing_phone' );
	}

	public function get_billing_country() {
		return $this->prop( 'billing_country' );
	}

	public function get_customer_ip_address() {
		return $this->prop( 'customer_ip_address' );
	}

	public function get_payment_method() {
		return $this->prop( 'payment_method' );
	}

	public function get_payment_method_title() {
		return $this->prop( 'payment_method_title' );
	}

	public function get_transaction_id() {
		return $this->prop( 'transaction_id' );
	}

	public function get_status() {
		return $this->prop( 'status', 'pending' );
	}

	public function has_status( $s ) {
		return in_array( $this->get_status(), (array) $s, true );
	}

	public function get_meta( string $key, bool $single = true ) {
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
	}

	public function update_meta_data( string $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function delete_meta_data( string $key ) {
		unset( $this->meta[ $key ] );
	}

	public function save() {
		++$this->saves;
		return $this->id;
	}

	public function update_status( string $status, string $note = '' ) {
		$this->status_changes[] = array( $status, $note );
		$this->props['status']  = $status;
		return true;
	}

	public function add_order_note( string $note, int $customer = 0 ) {
		$this->notes[] = array( $note, $customer );
		return count( $this->notes );
	}

	public function get_edit_order_url() {
		return 'https://example.test/wp-admin/post.php?post=' . $this->id . '&action=edit';
	}

	public function get_meta_data() {
		return $this->meta;
	}
}
