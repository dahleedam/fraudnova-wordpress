<?php
/**
 * Uninstall cleanup.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Removes plugin data on uninstall.
 */
final class Uninstaller {

	/**
	 * Clean up the current site.
	 *
	 * @param \wpdb $wpdb              Database.
	 * @param bool  $delete_order_data Also delete per-order FraudNova meta.
	 * @return void
	 */
	public static function run( \wpdb $wpdb, bool $delete_order_data ): void {
		delete_option( 'fraudnova_settings' );
		delete_option( 'fraudnova_activity_log' );
		delete_option( 'fraudnova_last_error' );

		$like = $wpdb->esc_like( 'fraudnova_' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_' . $like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_' . $like ) );

		if ( $delete_order_data ) {
			$meta_like = $wpdb->esc_like( '_fraudnova_' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $meta_like ) );

			$orders_meta = $wpdb->prefix . 'wc_orders_meta';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $orders_meta ) ) ) === $orders_meta ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders_meta} WHERE meta_key LIKE %s", $meta_like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Clean up every site on multisite, or the single site otherwise.
	 *
	 * Each site's own delete_on_uninstall setting is read after switching to it.
	 *
	 * @param \wpdb $wpdb Database.
	 * @return void
	 */
	public static function run_for_all_sites( \wpdb $wpdb ): void {
		if ( ! is_multisite() ) {
			self::run( $wpdb, self::wants_order_data_deleted() );
			return;
		}
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::run( $wpdb, self::wants_order_data_deleted() );
			restore_current_blog();
		}
	}

	/**
	 * Whether the current site opted in to deleting order data.
	 *
	 * @return bool
	 */
	private static function wants_order_data_deleted(): bool {
		$settings = get_option( 'fraudnova_settings' );
		return is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );
	}
}
