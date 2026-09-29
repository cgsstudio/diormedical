<?php

namespace WpDreamers\WPDDB\Controllers;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Traits\SingletonTrait;

class BookingManagement {
	use SingletonTrait;

	public function __construct() {
		add_action('init', [$this, 'maybe_delete_past_bookings']);
	}

	public function maybe_delete_past_bookings() {
		$last_run = get_option('wpdbb_last_cleanup_run');
		$last_run_timestamp = $last_run ? strtotime($last_run) : 0;

		$morning   = strtotime('today 06:00');
		$evening   = strtotime('today 14:00');
		$night     = strtotime('today 22:00');
		$now       = current_time('timestamp');
		if (($last_run_timestamp < $morning && $now >= $morning) ||
		    ($last_run_timestamp < $evening && $now >= $evening) ||
		    ($last_run_timestamp < $night && $now >= $night)) {
			$this->delete_past_bookings();
			update_option('wpdbb_last_cleanup_run', current_time('mysql'));
		}
	}

	public function delete_past_bookings() {
		global $wpdb;
		$booking_table_name = $wpdb->prefix . 'wpddb_bookings';

		$current_time = current_time( 'H:i' );
		$current_date = current_time( 'Y-m-d' );

		// --- Date-based bookings (new system) ---
		$date_expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM $booking_table_name
				 WHERE booking_date IS NOT NULL
				   AND booking_present_status = 'upcoming'
				   AND status = 'approved'
				   AND (booking_date < %s OR (booking_date = %s AND time < %s))",
				$current_date,
				$current_date,
				$current_time
			)
		);

		if ( ! empty( $date_expired ) ) {
			$ids          = array_map( fn( $b ) => $b->id, $date_expired );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $booking_table_name SET booking_present_status = 'expired' WHERE id IN ($placeholders)",
					...$ids
				)
			);
		}

		// --- Legacy bookings (weekday-based, no booking_date) ---
		$current_day = strtolower( date( 'l' ) );

		$legacy_expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, doctor_id, clinic_id, booking_id, status, booking_present_status, day, time
				 FROM $booking_table_name
				 WHERE booking_date IS NULL
				   AND status = 'approved'
				   AND day = %s
				   AND time < %s",
				$current_day,
				$current_time
			)
		);

		if ( ! empty( $legacy_expired ) ) {
			$booking_ids    = array_map( fn( $b ) => $b->id, $legacy_expired );
			$placeholders   = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $booking_table_name SET booking_present_status = 'expired' WHERE id IN ($placeholders)",
					...$booking_ids
				)
			);

			// Reset is_bookable in post meta for legacy bookings only
			$schedules = [];
			foreach ( $legacy_expired as $booking ) {
				if ( ! isset( $schedules[ $booking->doctor_id ] ) ) {
					$schedules[ $booking->doctor_id ] = get_post_meta( $booking->doctor_id, 'wpddb_doctor_schedule', true );
				}

				$schedule = &$schedules[ $booking->doctor_id ];

				if ( ! $schedule ) {
					continue;
				}

				foreach ( $schedule as &$day_item ) {
					if ( $day_item['day'] === $booking->day ) {
						foreach ( $day_item['clinics'] as &$clinic ) {
							if ( $clinic['id'] == $booking->clinic_id ) {
								foreach ( $clinic['timings'] as &$timing ) {
									if ( Helper::convert_time_format( $timing['time'], '24' ) === $booking->time ) {
										$timing['is_bookable'] = true;
									}
								}
							}
						}
					}
				}
			}

			foreach ( $schedules as $doctor_id => $updated_schedule ) {
				update_post_meta( $doctor_id, 'wpddb_doctor_schedule', $updated_schedule );
			}
		}
	}
}