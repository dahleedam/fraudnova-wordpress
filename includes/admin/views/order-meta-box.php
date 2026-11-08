<?php
/**
 * Order meta box view.
 *
 * Variables in scope: $box (Order_Meta_Box), $order_id (int), $data (array|null).
 *
 * @package FraudNova
 */

use FraudNova\WordPress\Admin\Order_Meta_Box;
use FraudNova\WordPress\Check_Result;
use FraudNova\WordPress\Reasons;
use FraudNova\WordPress\Subject;

defined( 'ABSPATH' ) || exit;

$fraudnova_checked = is_array( $data );
?>
<div class="fraudnova-order-box">
<?php if ( $fraudnova_checked ) : ?>
	<?php
	$fraudnova_assessment = $data['assessment'];
	$fraudnova_time       = strtotime( (string) $data['checked_at'] );
	?>
	<p>
		<?php echo Order_Meta_Box::pill( $fraudnova_assessment->level, $fraudnova_assessment->score ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() escapes. ?>
	</p>
	<?php if ( false !== $fraudnova_time ) : ?>
		<p class="description">
			<?php
			/* translators: %s: date and time. */
			echo esc_html( sprintf( __( 'Checked %s', 'fraudnova' ), wp_date( 'Y-m-d H:i', $fraudnova_time ) ) );
			?>
		</p>
	<?php endif; ?>
	<?php if ( ! empty( $fraudnova_assessment->reasons ) ) : ?>
		<p>
			<?php
			$fraudnova_labels = array();
			foreach ( $fraudnova_assessment->reasons as $fraudnova_reason ) {
				$fraudnova_labels[] = Reasons::label( (string) $fraudnova_reason );
			}
			echo esc_html( implode( ', ', $fraudnova_labels ) );
			?>
		</p>
	<?php endif; ?>
	<?php foreach ( array( Subject::IP, Subject::PHONE, Subject::EMAIL ) as $fraudnova_type ) : ?>
		<?php
		if ( ! isset( $data['results'][ $fraudnova_type ] ) ) {
			continue;
		}
		$fraudnova_result = $data['results'][ $fraudnova_type ];
		?>
		<div class="fraudnova-order-result">
			<strong><?php echo esc_html( $box->type_label( $fraudnova_type ) ); ?>:</strong>
			<?php if ( Check_Result::STATUS_SKIPPED === $fraudnova_result->status ) : ?>
				<?php
				/* translators: %s: reason a check was skipped. */
				echo esc_html( sprintf( __( 'Skipped: %s', 'fraudnova' ), $box->skip_label( $fraudnova_result->message ) ) );
				?>
			<?php elseif ( Check_Result::STATUS_ERROR === $fraudnova_result->status ) : ?>
				<?php
				/* translators: %s: error message. */
				echo esc_html( sprintf( __( 'Error: %s', 'fraudnova' ), $fraudnova_result->message ) );
				?>
			<?php else : ?>
				<?php if ( null !== $fraudnova_result->score ) : ?>
					<?php echo esc_html( (string) $fraudnova_result->score ); ?>
				<?php endif; ?>
				<?php foreach ( $box->flag_labels( $fraudnova_result ) as $fraudnova_chip ) : ?>
					<span class="fraudnova-pill is-review"><?php echo esc_html( $fraudnova_chip ); ?></span>
				<?php endforeach; ?>
				<?php
				$fraudnova_detail = array_filter(
					array_map( 'strval', array_values( $fraudnova_result->detail ) ),
					static function ( $v ) {
						return '' !== $v;
					}
				);
				?>
				<?php if ( ! empty( $fraudnova_detail ) ) : ?>
					<br /><span class="description"><?php echo esc_html( implode( ', ', $fraudnova_detail ) ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php else : ?>
	<p><?php echo Order_Meta_Box::pill( 'none', null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() escapes. ?></p>
<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="fraudnova_recheck" />
		<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>" />
		<?php wp_nonce_field( 'fraudnova_recheck_' . $order_id ); ?>
		<p>
			<button type="submit" class="button">
				<?php
				echo $fraudnova_checked
					? esc_html__( 'Check again (uses up to 3 credits)', 'fraudnova' )
					: esc_html__( 'Check now (uses up to 3 credits)', 'fraudnova' );
				?>
			</button>
		</p>
	</form>
</div>
