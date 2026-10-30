<?php

namespace WpDreamers\WPDDB\Controllers;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Booking;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Reminder emails: about a day before an appointment the patient gets one,
 * with the clinic address and their cancel link, and the doctor gets one with
 * the patient's details. A booking made less than a day ahead gets none; its
 * confirmation has only just arrived.
 *
 * Runs hourly on WP-Cron, which runs when the site has visitors.
 */
class BookingReminders {

	use SingletonTrait;

	const CRON_HOOK = 'wpddb_send_booking_reminders';

	/** How long before the appointment the reminder goes out. */
	const LEAD = DAY_IN_SECONDS;

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'send_due' ] );
		add_action( 'init', [ $this, 'schedule' ], 20 );
	}

	/**
	 * Keep the hourly job scheduled while reminders are on, and off otherwise.
	 */
	public function schedule() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( self::enabled() && ! $next ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		} elseif ( ! self::enabled() && $next ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	public static function enabled() {
		return self::to_patients() || self::to_doctors();
	}

	public static function to_patients() {
		return 'off' !== WpddbOptions::get_option( 'reminder_email', 'wpddb_doctor_settings' );
	}

	public static function to_doctors() {
		return 'off' !== WpddbOptions::get_option( 'doctor_reminder_email', 'wpddb_doctor_settings' );
	}

	/**
	 * Send every reminder that is due now.
	 *
	 * @return int How many were sent.
	 */
	public function send_due() {
		if ( ! self::enabled() ) {
			return 0;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';

		// Appointments from today to the day after tomorrow on the site's
		// calendar; the exact 24-hour window is checked below.
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM $table WHERE status = 'approved' AND booking_present_status = 'upcoming' AND reminder_sent = 0 AND booking_date BETWEEN %s AND %s",
			$today->format( 'Y-m-d' ),
			$today->modify( '+2 days' )->format( 'Y-m-d' )
		) );

		$now  = time();
		$sent = 0;
		foreach ( $ids as $id ) {
			$booking   = Booking::get( $id );
			if ( ! $booking ) {
				continue;
			}
			$to_patient = self::to_patients() && ! empty( $booking->email );
			$to_doctor  = self::to_doctors() && is_email( get_post_meta( $booking->doctor_id, 'wpddb_doctor_email', true ) );
			if ( ! $to_patient && ! $to_doctor ) {
				continue;
			}
			$starts = Booking::starts_at( $booking );
			if ( ! $starts || $starts <= $now || $starts - $now > self::LEAD ) {
				continue; // Over, or not yet due.
			}
			$created = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $booking->created_at, wp_timezone() );
			if ( $created && $starts - $created->getTimestamp() <= self::LEAD ) {
				continue; // Booked less than a day ahead.
			}

			// Claim the booking first, so two runs at once send one reminder.
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE $table SET reminder_sent = 1 WHERE id = %d AND reminder_sent = 0",
				(int) $booking->id
			) );
			if ( ! $claimed ) {
				continue;
			}

			Helper::send_booking_notifications(
				$booking->booking_id,
				(int) $booking->doctor_id,
				$booking->day,
				wp_date( 'g:i A', strtotime( '2000-01-01 ' . $booking->time ), new \DateTimeZone( 'UTC' ) ),
				(int) $booking->clinic_id,
				[ 'name' => $booking->full_name, 'email' => $booking->email, 'phone' => $booking->phone ],
				'reminder',
				$booking->booking_date,
				[
					'payment_by'  => $booking->payment_by,
					'fee'         => Booking::doctor_fee( $booking->doctor_id ),
					'amount_paid' => (float) $booking->amount_paid,
					'manage_url'  => Booking::manage_url( $booking ),
					'can_cancel'  => '' === Booking::patient_cancel_block( $booking ),
					'to_patient'  => $to_patient,
					'to_doctor'   => $to_doctor,
				]
			);
			++$sent;
		}

		return $sent;
	}
}
