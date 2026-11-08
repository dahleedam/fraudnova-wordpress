<?php
/**
 * Settings view: General tab.
 *
 * Variables in scope: $settings (array), $plugin_settings (Settings), $name (option name).
 *
 * @package FraudNova
 */

defined( 'ABSPATH' ) || exit;

$fraudnova_from_constant = $plugin_settings->api_key_from_constant();
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="fraudnova-api-key"><?php esc_html_e( 'API key', 'fraudnova' ); ?></label></th>
		<td>
			<?php if ( $fraudnova_from_constant ) : ?>
				<?php // No name attribute: nothing is posted, so the key saved in the option survives. ?>
				<input type="password" id="fraudnova-api-key" class="regular-text" autocomplete="off" value="" readonly="readonly" />
			<?php else : ?>
				<input type="password" id="fraudnova-api-key" class="regular-text" autocomplete="off" spellcheck="false"
					name="<?php echo esc_attr( $name . '[api_key]' ); ?>"
					value="<?php echo esc_attr( (string) $settings['api_key'] ); ?>" />
			<?php endif; ?>
			<button type="button" class="button" id="fraudnova-test-connection"><?php esc_html_e( 'Test connection', 'fraudnova' ); ?></button>
			<span id="fraudnova-test-result" class="fraudnova-test-result" role="status" aria-live="polite"></span>
			<?php if ( $fraudnova_from_constant ) : ?>
				<p class="description"><?php esc_html_e( 'Defined as FRAUDNOVA_API_KEY in wp-config.php, which overrides this field.', 'fraudnova' ); ?></p>
			<?php else : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to the FraudNova dashboard. */
						esc_html__( 'Create a key in your %s. Save the key before testing the connection.', 'fraudnova' ),
						'<a href="https://fraudnova.com/dashboard/api-keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'FraudNova dashboard', 'fraudnova' ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Test mode', 'fraudnova' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name . '[test_mode]' ); ?>" value="1" <?php checked( ! empty( $settings['test_mode'] ) ); ?> />
				<?php esc_html_e( 'Score and log orders, but never hold or cancel them', 'fraudnova' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Leave this on until the Activity tab shows the results you expect.', 'fraudnova' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Low-credit email', 'fraudnova' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name . '[low_credit_email]' ); ?>" value="1" <?php checked( ! empty( $settings['low_credit_email'] ) ); ?> />
				<?php esc_html_e( 'Email the site admin when the account runs out of credits (at most once a day)', 'fraudnova' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Uninstall', 'fraudnova' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name . '[delete_on_uninstall]' ); ?>" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
				<?php esc_html_e( 'Delete all FraudNova settings and the activity log when the plugin is deleted', 'fraudnova' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Links', 'fraudnova' ); ?></th>
		<td>
			<a href="https://fraudnova.com/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Dashboard', 'fraudnova' ); ?></a>
			&middot;
			<a href="https://fraudnova.com/dashboard/billing" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy credits', 'fraudnova' ); ?></a>
			&middot;
			<a href="https://fraudnova.com/docs#wordpress" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'fraudnova' ); ?></a>
		</td>
	</tr>
</table>
