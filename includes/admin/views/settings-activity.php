<?php
/**
 * Settings view: Activity tab.
 *
 * Variables in scope: $rows (array), $balance (array|false), $cleared (bool).
 *
 * @package FraudNova
 */

defined( 'ABSPATH' ) || exit;

$fraudnova_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
?>
<?php if ( $cleared ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Activity log cleared.', 'fraudnova' ); ?></p></div>
<?php endif; ?>

<p class="fraudnova-balance">
	<?php if ( is_array( $balance ) && isset( $balance['credits'], $balance['time'] ) ) : ?>
		<?php
		printf(
			/* translators: 1: number of credits, 2: date and time of the last check. */
			esc_html__( 'Balance: %1$s credits (as of %2$s).', 'fraudnova' ),
			esc_html( number_format_i18n( (int) $balance['credits'] ) ),
			esc_html( wp_date( $fraudnova_format, (int) $balance['time'] ) )
		);
		?>
	<?php else : ?>
		<?php esc_html_e( 'Balance: Unknown — use Test connection.', 'fraudnova' ); ?>
	<?php endif; ?>
</p>

<table class="widefat striped fraudnova-activity">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Time', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Source', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Type', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Subject', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Score', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Level', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Action', 'fraudnova' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Message', 'fraudnova' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $rows ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No activity yet', 'fraudnova' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rows as $fraudnova_row ) : ?>
				<?php
				$fraudnova_level = isset( $fraudnova_row['level'] ) ? (string) $fraudnova_row['level'] : '';
				$fraudnova_map   = array(
					'clean'     => 'clean',
					'allowed'   => 'clean',
					'review'    => 'review',
					'test_mode' => 'review',
					'high'      => 'high',
					'blocked'   => 'high',
				);
				$fraudnova_pill  = isset( $fraudnova_map[ $fraudnova_level ] ) ? $fraudnova_map[ $fraudnova_level ] : ( '' === $fraudnova_level ? 'none' : 'unknown' );
				?>
				<tr>
					<td class="fraudnova-nowrap"><?php echo esc_html( isset( $fraudnova_row['time'] ) ? wp_date( $fraudnova_format, (int) $fraudnova_row['time'] ) : '' ); ?></td>
					<td><?php echo esc_html( isset( $fraudnova_row['source'] ) ? (string) $fraudnova_row['source'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $fraudnova_row['type'] ) ? (string) $fraudnova_row['type'] : '' ); ?></td>
					<td><code><?php echo esc_html( isset( $fraudnova_row['subject'] ) ? (string) $fraudnova_row['subject'] : '' ); ?></code></td>
					<td><?php echo esc_html( isset( $fraudnova_row['score'] ) && null !== $fraudnova_row['score'] ? (string) $fraudnova_row['score'] : '—' ); ?></td>
					<td><span class="<?php echo esc_attr( 'fraudnova-pill is-' . $fraudnova_pill ); ?>"><?php echo esc_html( '' === $fraudnova_level ? '—' : $fraudnova_level ); ?></span></td>
					<td><?php echo esc_html( isset( $fraudnova_row['action'] ) ? (string) $fraudnova_row['action'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $fraudnova_row['message'] ) ? (string) $fraudnova_row['message'] : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fraudnova-clear-log">
	<input type="hidden" name="action" value="fraudnova_clear_log" />
	<?php wp_nonce_field( 'fraudnova_clear_log' ); ?>
	<?php submit_button( __( 'Clear', 'fraudnova' ), 'secondary small', 'submit', false, empty( $rows ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
</form>
