<?php

namespace WpDreamers\WPDDB\Controllers\Frontend;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Booking;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The page behind the link in the patient's emails: their appointment, and a
 * button to cancel it. Opening the link changes nothing (mail scanners open
 * links too); only the button does.
 */
class ManageBooking {

	use SingletonTrait;

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_render' ], 1 );
	}

	public function maybe_render() {
		if ( ! isset( $_GET['wpddb_booking'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the key in the link is the check.
			return;
		}
		$id      = absint( $_GET['wpddb_booking'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$booking = $id ? Booking::get( $id ) : null;
		$valid   = $booking && Booking::key_matches( $booking, $key );

		// The appointment as a calendar file (Apple Calendar, Outlook).
		if ( $valid && isset( $_GET['ics'] ) && 'approved' === $booking->status && Booking::starts_at( $booking ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			nocache_headers();
			header( 'Content-Type: text/calendar; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="appointment-' . sanitize_title( $booking->booking_id ) . '.ics"' );
			echo Booking::ics( $booking ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar text, escaped for that format.
			exit;
		}

		if ( $valid && isset( $_POST['wpddb_cancel_booking'] ) ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			$state = 'expired';
			if ( wp_verify_nonce( $nonce, 'wpddb_cancel_booking_' . $booking->id ) ) {
				// The rules are checked again here, not only when the page was drawn.
				$block = Booking::patient_cancel_block( $booking );
				$state = '' === $block ? ( Booking::cancel( $booking->id, 'patient' ) ? 'done' : 'cancelled' ) : $block;
			}
			wp_safe_redirect( add_query_arg( 'result', $state, Booking::manage_url( $booking ) ) );
			exit;
		}

		nocache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'pre_get_document_title', static function () {
			return __( 'Your Appointment', 'doc-booker' );
		} );
		if ( ! $valid ) {
			status_header( 404 );
		}

		$args = [
			'booking' => $valid ? $booking : null,
			'block'   => $valid ? Booking::patient_cancel_block( $booking ) : '',
			'result'  => isset( $_GET['result'] ) ? sanitize_key( $_GET['result'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		];
		if ( $valid ) {
			$args['details'] = self::details( $booking );
		}

		global $wp_version;
		Helper::get_header( $wp_version );
		Helper::render_template( 'booking/manage', $args );
		Helper::get_footer( $wp_version );
		exit;
	}

	/**
	 * What the page shows about the appointment.
	 *
	 * @param object $booking
	 * @return array
	 */
	public static function details( $booking ) {
		$starts      = Booking::starts_at( $booking );
		$doctor_name = get_the_title( $booking->doctor_id );
		$clinic_info = get_post_meta( $booking->doctor_id, 'wpddb_clinics_info', true );
		$phone       = is_array( $clinic_info ) && ! empty( $clinic_info[ $booking->clinic_id ]['phone_number'] )
			? $clinic_info[ $booking->clinic_id ]['phone_number']
			: get_post_meta( $booking->clinic_id, 'wpddb_clinic_hotline', true );
		$fee         = Booking::doctor_fee( $booking->doctor_id );

		$payment = '';
		if ( 'clinic' === $booking->payment_by && $fee > 0 ) {
			/* translators: %s: the fee with its currency, e.g. USD 45 */
			$payment = sprintf( __( '%s, to pay at the clinic', 'doc-booker' ), Booking::money( $fee ) );
		} elseif ( (float) $booking->amount_paid > 0 ) {
			/* translators: %s: the amount with its currency, e.g. USD 45 */
			$payment = sprintf( __( '%s, paid online', 'doc-booker' ), Booking::money( $booking->amount_paid ) );
		}

		return [
			'booking_id' => $booking->booking_id,
			'patient'    => $booking->full_name,
			'doctor'     => $doctor_name,
			'doctor_url' => get_permalink( $booking->doctor_id ),
			'date'       => $starts ? wp_date( get_option( 'date_format' ) ?: 'F j, Y', $starts ) : $booking->day,
			'weekday'    => $starts ? wp_date( 'l', $starts ) : '',
			'time'       => $starts ? wp_date( get_option( 'time_format' ) ?: 'g:i a', $starts ) : $booking->time,
			'clinic'     => get_the_title( $booking->clinic_id ),
			'address'    => (string) get_post_meta( $booking->clinic_id, 'wpddb_clinic_address', true ),
			'phone'      => (string) $phone,
			'payment'    => $payment,
			'cutoff'     => Booking::cancel_cutoff_hours(),
			'calendar'   => 'approved' === $booking->status && Booking::starts_at( $booking ) > time() ? Booking::calendar_links( $booking ) : [],
		];
	}
}
