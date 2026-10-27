<?php

namespace WpDreamers\WPDDB\Controllers\Model;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * One booking: its fee, the patient's cancel link, whether the patient may
 * still cancel, and cancelling it. Shared by the booking form, the emails, the
 * reminder job, the patient's cancel page and the admin's Cancel button.
 */
class Booking {

	/**
	 * A booking row with the patient's name, email and phone, or null.
	 *
	 * @param int $id Row id (not the "#WPDB-…" booking id).
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$bookings = $wpdb->prefix . 'wpddb_bookings';
		$patients = $wpdb->prefix . 'wpddb_patients';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT b.*, p.full_name, p.email, p.phone FROM $bookings b LEFT JOIN $patients p ON p.id = b.patient_id WHERE b.id = %d",
			absint( $id )
		) );
	}

	/**
	 * The doctor's booking fee; 0 when none is set.
	 *
	 * @param int $doctor_id
	 * @return float
	 */
	public static function doctor_fee( $doctor_id ) {
		return max( 0, (float) get_post_meta( absint( $doctor_id ), 'wpddb_doctor_booking_fee', true ) );
	}

	/**
	 * The site's currency code, upper case.
	 *
	 * @return string
	 */
	public static function currency() {
		return strtoupper( WpddbOptions::get_option( 'currency', 'wpddb_global_settings' ) ?: 'USD' );
	}

	/**
	 * "USD 45" or "45 USD", as the Currency Position setting says.
	 *
	 * @param float $amount
	 * @return string
	 */
	public static function money( $amount ) {
		$value    = rtrim( rtrim( number_format_i18n( (float) $amount, 2 ), '0' ), '.,' );
		$currency = self::currency();

		return self::currency_after()
			? $value . ' ' . $currency
			: $currency . ' ' . $value;
	}

	/**
	 * Currency Position "Right" or "Right with space": the code goes after
	 * the amount. A code always has a space, so both read "45 USD".
	 *
	 * @return bool
	 */
	public static function currency_after() {
		return in_array( WpddbOptions::get_option( 'currency_position', 'wpddb_global_settings' ), [ 'right', 'right_space' ], true );
	}

	/**
	 * What the booking screens need to show a fee: the currency and its
	 * position, as the shape money() in booking-ui.js reads.
	 *
	 * @return array
	 */
	public static function pricing() {
		return [
			'currency'          => self::currency(),
			'currency_position' => self::currency_after() ? 'right' : 'left',
		];
	}

	/**
	 * Minimum notice: how many hours ahead a patient must book (0 = none).
	 *
	 * @return int
	 */
	public static function min_notice_hours() {
		return min( 720, max( 0, (int) WpddbOptions::get_option( 'min_notice_hours', 'wpddb_doctor_settings' ) ) );
	}

	/**
	 * Whether a slot (site date + "H:i") starts too soon for the minimum notice.
	 *
	 * @param string $date Y-m-d.
	 * @param string $time_24 H:i.
	 * @return bool
	 */
	public static function too_soon( $date, $time_24 ) {
		$hours = self::min_notice_hours();
		if ( ! $hours ) {
			return false;
		}
		$start = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time_24, wp_timezone() );

		return $start && $start->getTimestamp() < time() + $hours * HOUR_IN_SECONDS;
	}

	/**
	 * Unix time the appointment starts, on the site's clock; 0 without a date.
	 *
	 * @param object $booking
	 * @return int
	 */
	public static function starts_at( $booking ) {
		if ( empty( $booking->booking_date ) ) {
			return 0;
		}
		$start = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $booking->booking_date . ' ' . $booking->time, wp_timezone() );

		return $start ? $start->getTimestamp() : 0;
	}

	/**
	 * Paid online through a gateway (Pro). Such a booking is not cancelled from
	 * the patient's link: the clinic decides about the refund.
	 *
	 * @param object $booking
	 * @return bool
	 */
	public static function is_paid_online( $booking ) {
		return (float) $booking->amount_paid > 0
			&& ! in_array( (string) $booking->payment_by, [ '', 'clinic' ], true )
			&& in_array( (string) $booking->payment_status, [ 'completed', 'succeeded' ], true );
	}

	/**
	 * Patients may cancel from the link in their emails (Doctor Settings).
	 *
	 * @return bool
	 */
	public static function patient_cancel_enabled() {
		return 'off' !== WpddbOptions::get_option( 'patient_cancel', 'wpddb_doctor_settings' );
	}

	/**
	 * How many hours before the appointment the link stops cancelling.
	 *
	 * @return int
	 */
	public static function cancel_cutoff_hours() {
		$hours = WpddbOptions::get_option( 'cancel_cutoff_hours', 'wpddb_doctor_settings' );

		return max( 0, (int) ( '' === $hours || null === $hours ? 24 : $hours ) );
	}

	/**
	 * The secret part of a booking's cancel link. It is worked out from the
	 * booking and the site's own secret keys, so it is never stored and cannot
	 * be guessed from the booking id.
	 *
	 * @param object $booking
	 * @return string
	 */
	public static function link_key( $booking ) {
		$data = $booking->id . '|' . $booking->booking_id . '|' . $booking->patient_id . '|' . $booking->created_at;

		return substr( hash_hmac( 'sha256', $data, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * The page where the patient sees the booking and can cancel it.
	 *
	 * @param object $booking
	 * @return string
	 */
	public static function manage_url( $booking ) {
		return add_query_arg(
			[
				'wpddb_booking' => (int) $booking->id,
				'key'           => self::link_key( $booking ),
			],
			home_url( '/' )
		);
	}

	/**
	 * How long an appointment lasts in the patient's calendar: the gap to the
	 * doctor's next slot that day at this clinic, else 30 minutes.
	 *
	 * @param object $booking
	 * @return int Minutes.
	 */
	public static function duration_minutes( $booking ) {
		$minutes  = 30;
		$schedule = get_post_meta( $booking->doctor_id, 'wpddb_doctor_schedule', true );
		$weekday  = $booking->booking_date ? Helper::get_weekday_name( $booking->booking_date ) : $booking->day;
		foreach ( is_array( $schedule ) ? $schedule : [] as $day ) {
			if ( strcasecmp( (string) ( $day['day'] ?? '' ), (string) $weekday ) !== 0 ) {
				continue;
			}
			foreach ( (array) ( $day['clinics'] ?? [] ) as $clinic ) {
				if ( (int) ( $clinic['id'] ?? 0 ) !== (int) $booking->clinic_id ) {
					continue;
				}
				$starts = array_map( static function ( $t ) {
					return strtotime( '2000-01-01 ' . Helper::convert_time_format( $t['time'] ?? '' ) );
				}, (array) ( $clinic['timings'] ?? [] ) );
				sort( $starts );
				$mine = strtotime( '2000-01-01 ' . $booking->time );
				foreach ( $starts as $start ) {
					if ( $start > $mine ) {
						$minutes = (int) round( ( $start - $mine ) / 60 );
						break;
					}
				}
			}
		}

		return (int) apply_filters( 'wpddb_appointment_minutes', max( 5, min( 240, $minutes ) ), $booking );
	}

	/**
	 * What a calendar entry says about the appointment.
	 *
	 * @param object $booking
	 * @return array{title:string, location:string, details:string, start:int, end:int}
	 */
	public static function calendar_event( $booking ) {
		$doctor   = get_the_title( $booking->doctor_id );
		$clinic   = get_the_title( $booking->clinic_id );
		$address  = (string) get_post_meta( $booking->clinic_id, 'wpddb_clinic_address', true );
		$start    = self::starts_at( $booking );
		/* translators: %s: doctor's name */
		$title    = sprintf( __( 'Appointment with %s', 'doc-booker' ), $doctor );
		/* translators: %s: booking ID such as #WPDB-12345678 */
		$details  = sprintf( __( 'Booking ID: %s', 'doc-booker' ), $booking->booking_id ) . "\n" . get_bloginfo( 'name' );

		return [
			'title'    => $title,
			'location' => trim( $clinic . ( '' !== trim( $address ) ? ', ' . $address : '' ) ),
			'details'  => $details,
			'start'    => $start,
			'end'      => $start + self::duration_minutes( $booking ) * MINUTE_IN_SECONDS,
		];
	}

	/**
	 * Links that add the appointment to the patient's calendar: Google
	 * Calendar, and an .ics file (Apple, Outlook) served from the signed link.
	 *
	 * @param object $booking
	 * @return array{google:string, ics:string}|array
	 */
	public static function calendar_links( $booking ) {
		$event = self::calendar_event( $booking );
		if ( ! $event['start'] ) {
			return [];
		}
		$utc = static function ( $ts ) {
			return gmdate( 'Ymd\THis\Z', $ts );
		};

		return [
			'google' => add_query_arg(
				[
					'action'   => 'TEMPLATE',
					'text'     => rawurlencode( $event['title'] ),
					'dates'    => $utc( $event['start'] ) . '/' . $utc( $event['end'] ),
					'details'  => rawurlencode( $event['details'] ),
					'location' => rawurlencode( $event['location'] ),
				],
				'https://calendar.google.com/calendar/render'
			),
			'ics'    => add_query_arg( 'ics', '1', self::manage_url( $booking ) ),
		];
	}

	/**
	 * The appointment as an iCalendar file.
	 *
	 * @param object $booking
	 * @return string
	 */
	public static function ics( $booking ) {
		$event  = self::calendar_event( $booking );
		$escape = static function ( $text ) {
			return str_replace( [ '\\', ';', ',', "\r\n", "\n" ], [ '\\\\', '\\;', '\\,', '\\n', '\\n' ], (string) $text );
		};
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$lines  = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//DocBooker//Appointment//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . sanitize_title( $booking->booking_id ) . '@' . $host,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', $event['start'] ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', $event['end'] ),
			'SUMMARY:' . $escape( $event['title'] ),
			'LOCATION:' . $escape( $event['location'] ),
			'DESCRIPTION:' . $escape( $event['details'] ),
			'STATUS:' . ( 'cancelled' === $booking->status ? 'CANCELLED' : 'CONFIRMED' ),
			'BEGIN:VALARM',
			'TRIGGER:-PT1H',
			'ACTION:DISPLAY',
			'DESCRIPTION:' . $escape( $event['title'] ),
			'END:VALARM',
			'END:VEVENT',
			'END:VCALENDAR',
		];

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Whether a cancel link's key belongs to the booking.
	 *
	 * @param object $booking
	 * @param string $key
	 * @return bool
	 */
	public static function key_matches( $booking, $key ) {
		return is_string( $key ) && '' !== $key && hash_equals( self::link_key( $booking ), $key );
	}

	/**
	 * Why the patient cannot cancel now, or '' when they can.
	 *
	 * @param object $booking
	 * @return string 'cancelled', 'past', 'disabled', 'paid' or 'late'.
	 */
	public static function patient_cancel_block( $booking ) {
		if ( 'cancelled' === $booking->status ) {
			return 'cancelled';
		}
		$starts = self::starts_at( $booking );
		if ( 'upcoming' !== $booking->booking_present_status || ! $starts || $starts <= time() ) {
			return 'past';
		}
		if ( ! self::patient_cancel_enabled() ) {
			return 'disabled';
		}
		if ( self::is_paid_online( $booking ) ) {
			return 'paid';
		}
		if ( $starts - self::cancel_cutoff_hours() * HOUR_IN_SECONDS <= time() ) {
			return 'late';
		}

		return '';
	}

	/**
	 * Cancel a booking: free its slot, email the patient, the doctor and the
	 * admin, and tell add-ons (Pro sends an SMS).
	 *
	 * @param int    $id Row id.
	 * @param string $by 'admin' or 'patient'.
	 * @return bool False when it was not an active booking.
	 */
	public static function cancel( $id, $by = 'admin' ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'wpddb_bookings';
		$booking = self::get( $id );
		if ( ! $booking ) {
			return false;
		}

		// Only an active booking changes; a second click changes nothing.
		$cancelled = $wpdb->query( $wpdb->prepare(
			"UPDATE $table SET status = %s, updated_at = %s WHERE id = %d AND status = %s",
			'cancelled',
			current_time( 'mysql' ),
			(int) $booking->id,
			'approved'
		) );
		if ( ! $cancelled ) {
			return false;
		}

		$booking_time = wp_date( 'g:i A', strtotime( '2000-01-01 ' . $booking->time ), new \DateTimeZone( 'UTC' ) );
		// Legacy bookings (no booking_date) switched the slot off in the schedule.
		if ( empty( $booking->booking_date ) ) {
			Helper::update_doctor_booking_meta_data_time( (int) $booking->doctor_id, $booking->day, $booking->time, (int) $booking->clinic_id );
		}
		Helper::send_booking_notifications(
			$booking->booking_id,
			(int) $booking->doctor_id,
			$booking->day,
			$booking_time,
			(int) $booking->clinic_id,
			[ 'name' => $booking->full_name, 'email' => $booking->email, 'phone' => $booking->phone ],
			'cancel',
			$booking->booking_date ?: null,
			[ 'cancelled_by' => $by ]
		);

		/**
		 * Fires after a booking is cancelled.
		 *
		 * @param object $booking The booking as it was before cancelling.
		 * @param string $by      'admin' or 'patient'.
		 */
		do_action( 'wpddb_booking_cancelled', $booking, $by );

		return true;
	}
}
