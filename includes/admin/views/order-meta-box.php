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
	$fraudnova_applied    = isset( $data['action_applied_at'] ) ? (string) $data['action_applied_at'] : '';
	$fraudnova_time       = strtotime( '' !== $fraudnova_applied ? $fraudnova_applied : (string) $data['checked_at'] );
	$fraudnova_action     = $box->action_label( $fraudnova_assessment->action );
	if ( $fraudnova_assessment->test_mode ) {
		$fraudnova_action .= ' · ' . __( 'test mode', 'fraudnova' );
	}
	?>
	<p>
		<?php echo Order_Meta_Box::pill( $fraudnova_assessment->level, $fraudnova_assessment->score ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() escapes. ?>
	</p>
	<p class="fraudnova-order-action"><?php echo esc_html( $fraudnova_action ); ?></p>
	<?php if ( false !== $fraudnova_time ) : ?>
		<p class="description">
			<?php
			$fraudnova_when = wp_date( 'Y-m-d H:i', $fraudnova_time );
			echo '' !== $fraudnova_applied
				/* translators: %s: date and time. */
				? esc_html( sprintf( __( 'Applied %s', 'fraudnova' ), $fraudnova_when ) )
				/* translators: %s: date and time. */
				: esc_html( sprintf( __( 'Checked %s', 'fraudnova' ), $fraudnova_when ) );
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
	<?php
	$fraudnova_recheck_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=fraudnova_recheck&order_id=' . $order_id ),
		'fraudnova_recheck_' . $order_id
	);
	?>
	<p>
		<a href="<?php echo esc_url( $fraudnova_recheck_url ); ?>" class="button">
			<?php
			echo $fraudnova_checked
				? esc_html__( 'Check again (uses up to 3 credits)', 'fraudnova' )
				: esc_html__( 'Check now (uses up to 3 credits)', 'fraudnova' );
			?>
		</a>
	</p>
</div>
