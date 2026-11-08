<?php
/**
 * Uninstall handler.
 *
 * @package FraudNova
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-autoloader.php';
\FraudNova\WordPress\Autoloader::register( __DIR__ . '/includes' );

global $wpdb;
\FraudNova\WordPress\Uninstaller::run_for_all_sites( $wpdb );
