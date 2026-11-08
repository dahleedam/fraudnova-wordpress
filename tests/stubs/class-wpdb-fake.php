<?php
/**
 * Minimal wpdb fake.
 *
 * @package FraudNova
 */

// phpcs:ignoreFile

class wpdb {

	/** @var string */
	public $options = 'wp_options';

	/** @var string */
	public $postmeta = 'wp_postmeta';

	/** @var string */
	public $prefix = 'wp_';

	/** @var array */
	public $queries = array();

	/** @var mixed */
	public $get_var_result = null;

	public function query( $sql ) {
		$this->queries[] = $sql;
		return 1;
	}

	public function prepare( $sql, ...$args ) {
		foreach ( $args as $arg ) {
			$sql = preg_replace( '/%s/', "'" . addcslashes( (string) $arg, '\\' ) . "'", $sql, 1 );
		}
		return $sql;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function get_var( $sql ) {
		return $this->get_var_result;
	}
}
