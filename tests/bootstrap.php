<?php
/**
 * PHPUnit bootstrap.
 *
 * @package FraudNova
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

define( 'FRAUDNOVA_VERSION', '1.0.0' );
define( 'FRAUDNOVA_FILE', dirname( __DIR__ ) . '/fraudnova.php' );
define( 'FRAUDNOVA_PATH', dirname( __DIR__ ) . '/' );
define( 'FRAUDNOVA_URL', 'https://example.test/wp-content/plugins/fraudnova/' );

require __DIR__ . '/stubs/class-wp-error.php';
require __DIR__ . '/stubs/class-wc-order.php';
require __DIR__ . '/stubs/class-wpcf7.php';
require __DIR__ . '/stubs/class-wpdb-fake.php';

require dirname( __DIR__ ) . '/includes/class-autoloader.php';
\FraudNova\WordPress\Autoloader::register( FRAUDNOVA_PATH . 'includes' );
