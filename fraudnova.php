<?php
/**
 * Plugin Name: FraudNova – Fraud Detection for WooCommerce and Forms
 * Plugin URI: https://fraudnova.com/docs#wordpress
 * Description: Scores the IP address, phone number and email of every new WooCommerce order and holds risky cash-on-delivery orders for review. Blocks throwaway emails and abusive traffic on registration and Contact Form 7 forms.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * WC requires at least: 8.0
 * WC tested up to: 11.0
 * Author: FraudNova
 * Author URI: https://fraudnova.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fraudnova
 * Domain Path: /languages
 *
 * @package FraudNova
 */

defined( 'ABSPATH' ) || exit;

define( 'FRAUDNOVA_VERSION', '1.0.0' );
define( 'FRAUDNOVA_FILE', __FILE__ );
define( 'FRAUDNOVA_PATH', plugin_dir_path( __FILE__ ) );
define( 'FRAUDNOVA_URL', plugin_dir_url( __FILE__ ) );
define( 'FRAUDNOVA_MIN_PHP', '7.4' );
define( 'FRAUDNOVA_MIN_WP', '6.0' );

/**
 * Whether the PHP and WordPress versions are supported.
 *
 * @return bool
 */
function fraudnova_requirements_met(): bool {
	return version_compare( PHP_VERSION, FRAUDNOVA_MIN_PHP, '>=' )
		&& version_compare( get_bloginfo( 'version' ), FRAUDNOVA_MIN_WP, '>=' );
}

/**
 * Show an admin notice when requirements are not met.
 *
 * @return void
 */
function fraudnova_requirements_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: minimum PHP version, 2: minimum WordPress version. */
				__( 'FraudNova requires PHP %1$s or newer and WordPress %2$s or newer. The plugin is inactive.', 'fraudnova' ),
				FRAUDNOVA_MIN_PHP,
				FRAUDNOVA_MIN_WP
			)
		)
	);
}

if ( ! fraudnova_requirements_met() ) {
	add_action( 'admin_notices', 'fraudnova_requirements_notice' );
	return;
}

require_once FRAUDNOVA_PATH . 'includes/class-autoloader.php';
require_once FRAUDNOVA_PATH . 'includes/functions.php';

\FraudNova\WordPress\Autoloader::register( FRAUDNOVA_PATH . 'includes' );

fraudnova()->boot();
