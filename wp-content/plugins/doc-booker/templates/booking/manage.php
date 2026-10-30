<?php
/**
 * The patient's appointment page, opened from the link in their emails.
 *
 * A theme can override it with doc-booker/booking/manage.php.
 *
 * @package WpdDocBooker
 * @var object|null $booking The booking, or null when the link is not valid.
 * @var string      $block   Why it cannot be cancelled: '', 'cancelled', 'past', 'disabled', 'paid' or 'late'.
 * @var string      $result  After pressing Cancel: 'done', 'cancelled', 'expired', or one of the $block reasons.
 * @var array       $details What to show about the appointment.
 * @version 1.0.0
 */

use WpDreamers\WPDDB\Controllers\Helper\Helper;

defined( 'ABSPATH' ) || exit;

$accent = Helper::get_primary_color();
?>
<style>
	.wpddb-manage { --mb-accent: <?php echo esc_attr( $accent ); ?>; max-width: 560px; margin: 48px auto; padding: 0 16px; font-size: 15px; line-height: 1.55; color: #0a2540; }
	.wpddb-manage__card { background: #fff; border: 1px solid #e6eaf0; border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 12px 32px rgba(15, 23, 42, .07); overflow: hidden; }
	.wpddb-manage__head { padding: 26px 26px 18px; }
	.wpddb-manage__icon { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; margin-bottom: 14px; font-size: 22px; font-weight: 700; }
	.wpddb-manage__icon.is-info { background: color-mix(in srgb, var(--mb-accent) 12%, #fff); color: var(--mb-accent); }
	.wpddb-manage__icon.is-ok { background: #d1fae5; color: #047857; }
	.wpddb-manage__icon.is-warn { background: #fef3c7; color: #92400e; }
	.wpddb-manage h1 { margin: 0 0 6px; font-size: 24px; line-height: 1.25; letter-spacing: -.01em; color: #0a2540; }
	.wpddb-manage__head p { margin: 0; color: #425466; }
	.wpddb-manage dl { display: grid; grid-template-columns: max-content 1fr; gap: 10px 18px; margin: 0; padding: 18px 26px; background: #f8fafc; border-top: 1px solid #eef0f4; border-bottom: 1px solid #eef0f4; }
	.wpddb-manage dt { color: #64748b; font-size: 13px; font-weight: 600; }
	.wpddb-manage dd { margin: 0; font-weight: 600; }
	.wpddb-manage dd small { display: block; color: #64748b; font-weight: 400; font-size: 13px; }
	.wpddb-manage__foot { padding: 20px 26px 26px; }
	.wpddb-manage__foot p { margin: 0 0 14px; color: #425466; font-size: 14px; }
	.wpddb-manage__btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 20px; border-radius: 10px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
	.wpddb-manage__btn.is-danger { border: 1px solid #fecaca; background: #fff; color: #b91c1c; }
	.wpddb-manage__btn.is-danger:hover { background: #fef2f2; }
	.wpddb-manage__btn.is-primary { border: 0; background: var(--mb-accent); color: #fff; }
	.wpddb-manage__btn.is-ghost { min-height: 38px; padding: 8px 14px; border: 1px solid #d6e0eb; background: #fff; color: #0a2540; font-size: 14px; }
	.wpddb-manage__btn.is-ghost:hover { border-color: var(--mb-accent); color: var(--mb-accent); }
	.wpddb-manage__cal { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 16px 26px 0; color: #64748b; font-size: 13px; }
	.wpddb-manage__btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--mb-accent) 40%, transparent); outline-offset: 2px; }
	.wpddb-manage__note { margin-top: 12px !important; font-size: 13px !important; color: #64748b !important; }
	@media (max-width: 480px) {
		.wpddb-manage { margin: 24px auto; }
		.wpddb-manage dl { grid-template-columns: 1fr; gap: 2px; }
		.wpddb-manage dd { margin-bottom: 10px; }
		.wpddb-manage__btn { width: 100%; }
		.wpddb-manage__cal span { width: 100%; }
	}
</style>
<main class="wpddb-manage">
	<div class="wpddb-manage__card">
		<?php if ( ! $booking ) { ?>
			<div class="wpddb-manage__head">
				<div class="wpddb-manage__icon is-warn" aria-hidden="true">!</div>
				<h1><?php esc_html_e( 'This Link Doesn’t Work', 'doc-booker' ); ?></h1>
				<p><?php esc_html_e( 'It may be incomplete. Open it again from your appointment email, or contact the clinic.', 'doc-booker' ); ?></p>
			</div>
		<?php } else {
			$phone_text = $details['phone']
				/* translators: %s: clinic phone number */
				? sprintf( __( 'Please call the clinic: %s', 'doc-booker' ), $details['phone'] )
				: __( 'Please contact the clinic.', 'doc-booker' );

			// What the top of the card says.
			if ( 'done' === $result ) {
				$icon    = 'is-ok';
				$mark    = '✓';
				$heading = __( 'Your Appointment Is Cancelled', 'doc-booker' );
				$text    = __( 'The time is free again for other patients. We have emailed you a confirmation.', 'doc-booker' );
			} elseif ( 'cancelled' === $block ) {
				$icon    = 'is-info';
				$mark    = 'i';
				$heading = __( 'This Appointment Is Cancelled', 'doc-booker' );
				$text    = __( 'There is nothing more to do. You are welcome to book another time.', 'doc-booker' );
			} elseif ( 'past' === $block ) {
				$icon    = 'is-info';
				$mark    = 'i';
				$heading = __( 'This Appointment Has Passed', 'doc-booker' );
				$text    = __( 'It can no longer be changed.', 'doc-booker' );
			} elseif ( 'paid' === $block ) {
				$icon    = 'is-warn';
				$mark    = '!';
				$heading = __( 'Your Appointment', 'doc-booker' );
				$text    = __( 'This appointment was paid online, so it can’t be cancelled here. Please contact the clinic to cancel and arrange a refund.', 'doc-booker' );
			} elseif ( 'late' === $block ) {
				$icon    = 'is-warn';
				$mark    = '!';
				$heading = __( 'Your Appointment', 'doc-booker' );
				$text    = sprintf(
					/* translators: %d: number of hours */
					_n( 'It’s less than %d hour to your appointment, so it can’t be cancelled online.', 'It’s less than %d hours to your appointment, so it can’t be cancelled online.', $details['cutoff'], 'doc-booker' ),
					$details['cutoff']
				) . ' ' . $phone_text;
			} elseif ( 'disabled' === $block ) {
				$icon    = 'is-info';
				$mark    = 'i';
				$heading = __( 'Your Appointment', 'doc-booker' );
				$text    = __( 'To cancel or change it, please contact the clinic.', 'doc-booker' );
			} else {
				$icon    = 'is-info';
				$mark    = 'i';
				$heading = __( 'Your Appointment', 'doc-booker' );
				$text    = 'expired' === $result
					? __( 'The page was open for too long. Please press Cancel Appointment again.', 'doc-booker' )
					: __( 'Here are the details of your booking.', 'doc-booker' );
			}
			?>
			<div class="wpddb-manage__head"<?php echo 'done' === $result ? ' role="status"' : ''; ?>>
				<div class="wpddb-manage__icon <?php echo esc_attr( $icon ); ?>" aria-hidden="true"><?php echo esc_html( $mark ); ?></div>
				<h1><?php echo esc_html( $heading ); ?></h1>
				<p><?php echo esc_html( $text ); ?></p>
			</div>
			<dl>
				<dt><?php esc_html_e( 'Doctor', 'doc-booker' ); ?></dt>
				<dd><?php echo esc_html( $details['doctor'] ); ?></dd>
				<dt><?php esc_html_e( 'Date', 'doc-booker' ); ?></dt>
				<dd><?php echo esc_html( trim( $details['weekday'] . ', ' . $details['date'], ', ' ) ); ?></dd>
				<dt><?php esc_html_e( 'Time', 'doc-booker' ); ?></dt>
				<dd><?php echo esc_html( $details['time'] ); ?></dd>
				<dt><?php esc_html_e( 'Clinic', 'doc-booker' ); ?></dt>
				<dd>
					<?php echo esc_html( $details['clinic'] ); ?>
					<?php if ( $details['address'] ) { ?><small><?php echo esc_html( $details['address'] ); ?></small><?php } ?>
				</dd>
				<?php if ( $details['payment'] ) { ?>
					<dt><?php esc_html_e( 'Fee', 'doc-booker' ); ?></dt>
					<dd><?php echo esc_html( $details['payment'] ); ?></dd>
				<?php } ?>
				<dt><?php esc_html_e( 'Booking ID', 'doc-booker' ); ?></dt>
				<dd><?php echo esc_html( $details['booking_id'] ); ?></dd>
			</dl>
			<?php if ( ! empty( $details['calendar']['google'] ) && 'done' !== $result ) { ?>
				<div class="wpddb-manage__cal">
					<span><?php esc_html_e( 'Add to your calendar:', 'doc-booker' ); ?></span>
					<a class="wpddb-manage__btn is-ghost" href="<?php echo esc_url( $details['calendar']['google'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Google Calendar', 'doc-booker' ); ?></a>
					<a class="wpddb-manage__btn is-ghost" href="<?php echo esc_url( $details['calendar']['ics'] ); ?>"><?php esc_html_e( 'Apple / Outlook', 'doc-booker' ); ?></a>
				</div>
			<?php } ?>
			<div class="wpddb-manage__foot">
				<?php if ( '' === $block ) { ?>
					<p>
						<?php
						echo esc_html( $details['cutoff'] > 0
							/* translators: %d: number of hours */
							? sprintf( _n( 'Can’t make it? You can cancel up to %d hour before the appointment, and the time goes to another patient.', 'Can’t make it? You can cancel up to %d hours before the appointment, and the time goes to another patient.', $details['cutoff'], 'doc-booker' ), $details['cutoff'] )
							: __( 'Can’t make it? Cancel, and the time goes to another patient.', 'doc-booker' ) );
						?>
					</p>
					<form method="post">
						<?php wp_nonce_field( 'wpddb_cancel_booking_' . $booking->id ); ?>
						<button type="submit" name="wpddb_cancel_booking" value="1" class="wpddb-manage__btn is-danger"><?php esc_html_e( 'Cancel Appointment', 'doc-booker' ); ?></button>
					</form>
					<p class="wpddb-manage__note"><?php esc_html_e( 'Nothing changes until you press the button.', 'doc-booker' ); ?></p>
				<?php } elseif ( in_array( $block, [ 'cancelled', 'past' ], true ) || 'done' === $result ) { ?>
					<a class="wpddb-manage__btn is-primary" href="<?php echo esc_url( $details['doctor_url'] ); ?>"><?php esc_html_e( 'Book Another Appointment', 'doc-booker' ); ?></a>
				<?php } elseif ( $details['phone'] ) { ?>
					<a class="wpddb-manage__btn is-primary" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $details['phone'] ) ); ?>">
						<?php
						/* translators: %s: clinic phone number */
						echo esc_html( sprintf( __( 'Call %s', 'doc-booker' ), $details['phone'] ) );
						?>
					</a>
				<?php } ?>
			</div>
		<?php } ?>
	</div>
</main>
