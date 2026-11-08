<?php
/**
 * Settings view: Forms tab.
 *
 * Variables in scope: $settings (array), $name (option name).
 *
 * @package FraudNova
 */

defined( 'ABSPATH' ) || exit;

$fraudnova_has_cf7 = class_exists( 'WPCF7_Submission' );
$fraudnova_has_wc  = class_exists( 'WooCommerce' );
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Protected forms', 'fraudnova' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Protected forms', 'fraudnova' ); ?></legend>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name . '[form_wp_registration]' ); ?>" value="1" <?php checked( ! empty( $settings['form_wp_registration'] ) ); ?> />
					<?php esc_html_e( 'WordPress registration', 'fraudnova' ); ?>
				</label>
				<?php if ( ! get_option( 'users_can_register' ) ) : ?>
					<span class="description"><?php esc_html_e( '(registration is turned off in Settings → General)', 'fraudnova' ); ?></span>
				<?php endif; ?>
				<br />
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name . '[form_wc_registration]' ); ?>" value="1" <?php checked( ! empty( $settings['form_wc_registration'] ) ); ?> />
					<?php esc_html_e( 'WooCommerce account registration', 'fraudnova' ); ?>
				</label>
				<?php if ( ! $fraudnova_has_wc ) : ?>
					<span class="description"><?php esc_html_e( '(WooCommerce is not active)', 'fraudnova' ); ?></span>
				<?php endif; ?>
				<br />
				<label>
					<?php if ( $fraudnova_has_cf7 ) : ?>
						<input type="checkbox" name="<?php echo esc_attr( $name . '[form_cf7]' ); ?>" value="1" <?php checked( ! empty( $settings['form_cf7'] ) ); ?> />
					<?php else : ?>
						<input type="hidden" name="<?php echo esc_attr( $name . '[form_cf7]' ); ?>" value="<?php echo esc_attr( empty( $settings['form_cf7'] ) ? '0' : '1' ); ?>" />
						<input type="checkbox" disabled="disabled" <?php checked( ! empty( $settings['form_cf7'] ) ); ?> />
					<?php endif; ?>
					<?php esc_html_e( 'Contact Form 7 forms', 'fraudnova' ); ?>
				</label>
				<?php if ( ! $fraudnova_has_cf7 ) : ?>
					<span class="description"><?php esc_html_e( '(install and activate Contact Form 7 to use this)', 'fraudnova' ); ?></span>
				<?php endif; ?>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'What to check', 'fraudnova' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'What to check', 'fraudnova' ); ?></legend>
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[form_check_ip]' ); ?>" value="1" <?php checked( ! empty( $settings['form_check_ip'] ) ); ?> /> <?php esc_html_e( 'Visitor IP address', 'fraudnova' ); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[form_check_email]' ); ?>" value="1" <?php checked( ! empty( $settings['form_check_email'] ) ); ?> /> <?php esc_html_e( 'Submitted email', 'fraudnova' ); ?></label>
				<p class="description"><?php esc_html_e( 'Each check uses one credit.', 'fraudnova' ); ?></p>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="fraudnova-form-block-threshold"><?php esc_html_e( 'Block threshold', 'fraudnova' ); ?></label></th>
		<td>
			<input type="number" min="1" max="100" step="1" class="small-text" id="fraudnova-form-block-threshold" name="<?php echo esc_attr( $name . '[form_block_threshold]' ); ?>" value="<?php echo esc_attr( (string) $settings['form_block_threshold'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Submissions with a fraud score at or above this are blocked.', 'fraudnova' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Always block when', 'fraudnova' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Always block when', 'fraudnova' ); ?></legend>
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[form_rule_email_disposable]' ); ?>" value="1" <?php checked( ! empty( $settings['form_rule_email_disposable'] ) ); ?> /> <?php esc_html_e( 'The email is disposable', 'fraudnova' ); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[form_rule_email_invalid]' ); ?>" value="1" <?php checked( ! empty( $settings['form_rule_email_invalid'] ) ); ?> /> <?php esc_html_e( 'The email is invalid', 'fraudnova' ); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[form_rule_ip_proxy]' ); ?>" value="1" <?php checked( ! empty( $settings['form_rule_ip_proxy'] ) ); ?> /> <?php esc_html_e( 'The IP is a proxy, VPN or Tor exit', 'fraudnova' ); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr( $name . '[form_rule_ip_abuse]' ); ?>" value="1" <?php checked( ! empty( $settings['form_rule_ip_abuse'] ) ); ?> /> <?php esc_html_e( 'The IP has recent abuse', 'fraudnova' ); ?></label>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="fraudnova-form-block-message"><?php esc_html_e( 'Block message', 'fraudnova' ); ?></label></th>
		<td>
			<textarea id="fraudnova-form-block-message" class="large-text" rows="3" name="<?php echo esc_attr( $name . '[form_block_message]' ); ?>"><?php echo esc_textarea( (string) $settings['form_block_message'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Shown to the visitor when a submission is blocked. Plain text only.', 'fraudnova' ); ?></p>
		</td>
	</tr>
</table>
