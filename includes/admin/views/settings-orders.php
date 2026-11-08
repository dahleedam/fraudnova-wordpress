<?php
/**
 * Settings view: Orders tab.
 *
 * Variables in scope: $settings (array), $plugin_settings (Settings), $page (Settings_Page), $name (option name).
 *
 * @package FraudNova
 */

defined( 'ABSPATH' ) || exit;

$fraudnova_labels   = $page->enum_labels();
$fraudnova_methods  = $settings['order_payment_methods'];
$fraudnova_gateways = $page->gateway_choices();
$fraudnova_rules    = array(
	'rule_phone_invalid'    => __( 'Phone number is invalid', 'fraudnova' ),
	'rule_phone_voip'       => __( 'Phone number is VOIP', 'fraudnova' ),
	'rule_email_disposable' => __( 'Email is disposable', 'fraudnova' ),
	'rule_ip_proxy'         => __( 'IP is a proxy, VPN or Tor exit', 'fraudnova' ),
	'rule_ip_abuse'         => __( 'IP has recent abuse', 'fraudnova' ),
	'rule_email_abuse'      => __( 'Email has recent abuse', 'fraudnova' ),
);
$fraudnova_ip_help  = array(
	'woocommerce' => __( 'The address WooCommerce recorded on the order (default). Trusts proxy headers the same way WooCommerce does.', 'fraudnova' ),
	'remote_addr' => __( 'The connecting address only. Correct when the site is not behind a proxy or CDN; header-safe.', 'fraudnova' ),
	'cloudflare'  => __( 'The CF-Connecting-IP header, for sites behind Cloudflare. Only safe if visitors cannot reach the server directly.', 'fraudnova' ),
);
?>
<?php if ( ! empty( $settings['test_mode'] ) ) : ?>
	<div class="notice notice-info inline fraudnova-test-mode-banner">
		<p><?php esc_html_e( 'Test mode is on: orders are scored and logged but never held or cancelled.', 'fraudnova' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'WooCommerce is not active. These settings take effect once it is.', 'fraudnova' ); ?></p>
	</div>
<?php endif; ?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Order checks', 'fraudnova' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name . '[orders_enabled]' ); ?>" value="1" <?php checked( ! empty( $settings['orders_enabled'] ) ); ?> />
				<?php esc_html_e( 'Score new WooCommerce orders', 'fraudnova' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Payment methods', 'fraudnova' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Payment methods', 'fraudnova' ); ?></legend>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name . '[order_payment_methods][]' ); ?>" value="all" <?php checked( 'all' === $fraudnova_methods ); ?> />
					<?php esc_html_e( 'Every payment method', 'fraudnova' ); ?>
				</label><br />
				<?php foreach ( $fraudnova_gateways as $fraudnova_id => $fraudnova_gateway ) : ?>
					<?php
					$fraudnova_checked = is_array( $fraudnova_methods )
						? in_array( (string) $fraudnova_id, $fraudnova_methods, true )
						: ( null === $fraudnova_methods && $fraudnova_gateway['cod_like'] );
					?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name . '[order_payment_methods][]' ); ?>" value="<?php echo esc_attr( (string) $fraudnova_id ); ?>" <?php checked( $fraudnova_checked ); ?> />
						<?php echo esc_html( $fraudnova_gateway['title'] ); ?>
						<code><?php echo esc_html( (string) $fraudnova_id ); ?></code>
						<?php if ( ! $fraudnova_gateway['enabled'] ) : ?>
							<span class="description"><?php esc_html_e( '(disabled)', 'fraudnova' ); ?></span>
						<?php endif; ?>
					</label><br />
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Leave everything unticked to detect cash-on-delivery gateways automatically.', 'fraudnova' ); ?></p>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Customer IP address', 'fraudnova' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Customer IP address', 'fraudnova' ); ?></legend>
				<?php foreach ( $plugin_settings->enum_values( 'ip_source' ) as $fraudnova_value ) : ?>
					<label>
						<input type="radio" name="<?php echo esc_attr( $name . '[ip_source]' ); ?>" value="<?php echo esc_attr( $fraudnova_value ); ?>" <?php checked( $settings['ip_source'], $fraudnova_value ); ?> />
						<?php echo esc_html( $fraudnova_labels[ $fraudnova_value ] ); ?>
					</label>
					<p class="description fraudnova-option-help"><?php echo esc_html( $fraudnova_ip_help[ $fraudnova_value ] ); ?></p>
				<?php endforeach; ?>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'What to check', 'fraudnova' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'What to check', 'fraudnova' ); ?></legend>
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[check_ip]' ); ?>" value="1" <?php checked( ! empty( $settings['check_ip'] ) ); ?> /> <?php esc_html_e( 'IP address', 'fraudnova' ); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[check_phone]' ); ?>" value="1" <?php checked( ! empty( $settings['check_phone'] ) ); ?> /> <?php esc_html_e( 'Billing phone', 'fraudnova' ); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[check_email]' ); ?>" value="1" <?php checked( ! empty( $settings['check_email'] ) ); ?> /> <?php esc_html_e( 'Billing email', 'fraudnova' ); ?></label>
				<p class="description"><?php esc_html_e( 'Each check uses one credit.', 'fraudnova' ); ?></p>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="fraudnova-strictness"><?php esc_html_e( 'IP strictness', 'fraudnova' ); ?></label></th>
		<td>
			<select id="fraudnova-strictness" name="<?php echo esc_attr( $name . '[strictness]' ); ?>">
				<?php for ( $fraudnova_level = 0; $fraudnova_level <= 3; $fraudnova_level++ ) : ?>
					<option value="<?php echo esc_attr( (string) $fraudnova_level ); ?>" <?php selected( (int) $settings['strictness'], $fraudnova_level ); ?>><?php echo esc_html( (string) $fraudnova_level ); ?></option>
				<?php endfor; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Higher levels catch more proxies but flag more legitimate visitors. 1 suits most stores.', 'fraudnova' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="fraudnova-review-threshold"><?php esc_html_e( 'Review threshold', 'fraudnova' ); ?></label></th>
		<td>
			<input type="number" min="1" max="100" step="1" class="small-text" id="fraudnova-review-threshold" name="<?php echo esc_attr( $name . '[review_threshold]' ); ?>" value="<?php echo esc_attr( (string) $settings['review_threshold'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Orders with a fraud score at or above this need review.', 'fraudnova' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="fraudnova-hold-threshold"><?php esc_html_e( 'High-risk threshold', 'fraudnova' ); ?></label></th>
		<td>
			<input type="number" min="1" max="100" step="1" class="small-text" id="fraudnova-hold-threshold" name="<?php echo esc_attr( $name . '[hold_threshold]' ); ?>" value="<?php echo esc_attr( (string) $settings['hold_threshold'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Orders with a fraud score at or above this are high risk. Never lower than the review threshold.', 'fraudnova' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Rules', 'fraudnova' ); ?></th>
		<td>
			<table class="fraudnova-rules">
				<?php foreach ( $fraudnova_rules as $fraudnova_key => $fraudnova_label ) : ?>
					<tr>
						<td><label for="<?php echo esc_attr( 'fraudnova-' . $fraudnova_key ); ?>"><?php echo esc_html( $fraudnova_label ); ?></label></td>
						<td>
							<select id="<?php echo esc_attr( 'fraudnova-' . $fraudnova_key ); ?>" name="<?php echo esc_attr( $name . '[' . $fraudnova_key . ']' ); ?>">
								<?php foreach ( $plugin_settings->enum_values( $fraudnova_key ) as $fraudnova_value ) : ?>
									<option value="<?php echo esc_attr( $fraudnova_value ); ?>" <?php selected( $settings[ $fraudnova_key ], $fraudnova_value ); ?>><?php echo esc_html( $fraudnova_labels[ $fraudnova_value ] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description"><?php esc_html_e( 'A matching rule raises the order to at least that level, whatever the score.', 'fraudnova' ); ?></p>
		</td>
	</tr>
	<?php
	$fraudnova_actions = array(
		'review_action' => __( 'Needs review', 'fraudnova' ),
		'high_action'   => __( 'High risk', 'fraudnova' ),
		'on_error'      => __( 'When a check fails', 'fraudnova' ),
	);
	?>
	<?php foreach ( $fraudnova_actions as $fraudnova_key => $fraudnova_label ) : ?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( 'fraudnova-' . $fraudnova_key ); ?>"><?php echo esc_html( $fraudnova_label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( 'fraudnova-' . $fraudnova_key ); ?>" name="<?php echo esc_attr( $name . '[' . $fraudnova_key . ']' ); ?>">
					<?php foreach ( $plugin_settings->enum_values( $fraudnova_key ) as $fraudnova_value ) : ?>
						<?php $fraudnova_option = ( 'on_error' === $fraudnova_key && 'review' === $fraudnova_value ) ? __( 'Treat as needs review', 'fraudnova' ) : $fraudnova_labels[ $fraudnova_value ]; ?>
						<option value="<?php echo esc_attr( $fraudnova_value ); ?>" <?php selected( $settings[ $fraudnova_key ], $fraudnova_value ); ?>><?php echo esc_html( $fraudnova_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	<?php endforeach; ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Hold emails', 'fraudnova' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name . '[suppress_hold_email]' ); ?>" value="1" <?php checked( ! empty( $settings['suppress_hold_email'] ) ); ?> />
				<?php esc_html_e( 'Do not send the customer the "order on hold" email when FraudNova holds an order', 'fraudnova' ); ?>
			</label>
		</td>
	</tr>
</table>
