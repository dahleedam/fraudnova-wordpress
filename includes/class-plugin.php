<?php
/**
 * Main plugin class.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton; wires modules together.
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * API client instance.
	 *
	 * @var Api_Client|null
	 */
	private $api_client = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the settings object.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings();
		}
		return $this->settings;
	}

	/**
	 * Get the API client.
	 *
	 * @return Api_Client
	 */
	public function api_client(): Api_Client {
		if ( null === $this->api_client ) {
			$this->api_client = new Api_Client( $this->settings() );
		}
		return $this->api_client;
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'init_modules' ), 20 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Register modules. Later tasks add their modules here.
	 *
	 * @return void
	 */
	public function init_modules(): void {}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FRAUDNOVA_FILE, true );
		}
	}
}
