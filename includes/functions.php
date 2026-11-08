<?php
/**
 * Global helper functions.
 *
 * @package FraudNova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the plugin instance.
 *
 * @return \FraudNova\WordPress\Plugin
 */
function fraudnova(): \FraudNova\WordPress\Plugin {
	return \FraudNova\WordPress\Plugin::instance();
}
