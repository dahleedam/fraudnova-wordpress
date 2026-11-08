<?php
/**
 * Admin bootstrap.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Admin;

use FraudNova\WordPress\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings page, assets, AJAX handlers and notices.
 */
class Admin {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Settings page.
	 *
	 * @var Settings_Page
	 */
	private $page;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->page   = new Settings_Page( $plugin );
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this->page, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FRAUDNOVA_FILE ), array( $this, 'action_links' ) );

		( new Ajax( $this->plugin ) )->register();
		( new Notices( $this->plugin ) )->register();

		if ( class_exists( 'WooCommerce' ) ) {
			( new Order_Meta_Box() )->register();
			( new Order_List_Column() )->register();
			( new Recheck( $this->plugin ) )->register();
		}
	}

	/**
	 * Add the Settings → FraudNova page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'FraudNova', 'fraudnova' ),
			__( 'FraudNova', 'fraudnova' ),
			'manage_options',
			Settings_Page::SLUG,
			array( $this->page, 'render' )
		);
	}

	/**
	 * Screen ids that load the admin assets.
	 *
	 * @return string[]
	 */
	public function screen_ids(): array {
		$ids = array( 'settings_page_' . Settings_Page::SLUG, 'shop_order', 'edit-shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$ids[] = (string) wc_get_page_screen_id( 'shop_order' );
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Enqueue the admin CSS and JS on our screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = ( is_object( $screen ) && isset( $screen->id ) ) ? (string) $screen->id : '';
		$ids    = $this->screen_ids();
		if ( ! in_array( $id, $ids, true ) && ! in_array( (string) $hook_suffix, $ids, true ) ) {
			return;
		}

		wp_enqueue_style( 'fraudnova-admin', FRAUDNOVA_URL . 'assets/css/admin.css', array(), FRAUDNOVA_VERSION );
		wp_enqueue_script( 'fraudnova-admin', FRAUDNOVA_URL . 'assets/js/admin.js', array(), FRAUDNOVA_VERSION, true );
		wp_localize_script(
			'fraudnova-admin',
			'fraudnovaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fraudnova_admin' ),
				'i18n'    => array(
					'testing' => __( 'Testing…', 'fraudnova' ),
					'failed'  => __( 'The connection test failed. Please try again.', 'fraudnova' ),
				),
			)
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( Settings_Page::url() ),
				esc_html__( 'Settings', 'fraudnova' )
			)
		);
		return $links;
	}
}
