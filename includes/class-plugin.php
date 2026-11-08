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
	 * Activity log instance.
	 *
	 * @var Activity_Log|null
	 */
	private $log = null;

	/**
	 * Error state instance.
	 *
	 * @var Error_State|null
	 */
	private $error_state = null;

	/**
	 * Cache instance.
	 *
	 * @var Cache|null
	 */
	private $cache = null;

	/**
	 * Scorer instance.
	 *
	 * @var Scorer|null
	 */
	private $scorer = null;

	/**
	 * WooCommerce order integration.
	 *
	 * @var Integrations\Woocommerce_Orders|null
	 */
	private $orders = null;

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
	 * Get the activity log.
	 *
	 * @return Activity_Log
	 */
	public function log(): Activity_Log {
		if ( null === $this->log ) {
			$this->log = new Activity_Log();
		}
		return $this->log;
	}

	/**
	 * Get the error state.
	 *
	 * @return Error_State
	 */
	public function error_state(): Error_State {
		if ( null === $this->error_state ) {
			$this->error_state = new Error_State();
		}
		return $this->error_state;
	}

	/**
	 * Get the cache.
	 *
	 * @return Cache
	 */
	public function cache(): Cache {
		if ( null === $this->cache ) {
			$this->cache = new Cache();
		}
		return $this->cache;
	}

	/**
	 * Get the scorer.
	 *
	 * @return Scorer
	 */
	public function scorer(): Scorer {
		if ( null === $this->scorer ) {
			$this->scorer = new Scorer( $this->api_client(), $this->cache(), $this->settings(), $this->log(), $this->error_state() );
		}
		return $this->scorer;
	}

	/**
	 * Get the WooCommerce order integration.
	 *
	 * @return Integrations\Woocommerce_Orders
	 */
	public function orders(): Integrations\Woocommerce_Orders {
		if ( null === $this->orders ) {
			$this->orders = new Integrations\Woocommerce_Orders( $this );
		}
		return $this->orders;
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
	public function init_modules(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->orders()->register();
		}

		$settings = $this->settings();
		// This runs on plugins_loaded priority 20; Contact Form 7 defines its classes on priority 10, so class_exists() is reliable here.
		if ( get_option( 'users_can_register' ) && $settings->get( 'form_wp_registration' ) ) {
			( new Integrations\Wp_Registration( $this ) )->register();
		}
		if ( class_exists( 'WooCommerce' ) && $settings->get( 'form_wc_registration' ) ) {
			( new Integrations\Woocommerce_Registration( $this ) )->register();
		}
		if ( class_exists( 'WPCF7_Submission' ) && $settings->get( 'form_cf7' ) ) {
			( new Integrations\Contact_Form_7( $this ) )->register();
		}

		if ( is_admin() ) {
			( new Admin\Admin( $this ) )->register();
		}
	}

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
