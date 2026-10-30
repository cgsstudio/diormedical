<?php
/**
 * Hero booking-card data resolver (split-action layout).
 *
 * When the hero's action card is in "dynamic" mode it shows REAL data — a
 * featured doctor (or the first published one), that doctor's department +
 * clinic, and the doctor's next genuinely-available slot — instead of static
 * placeholder text. All lookups reuse the free plugin's data model
 * (`wpddb_doctor_schedule` meta, `wpddb_doctor_department` taxonomy, the
 * `{prefix}wpddb_bookings` table) so the card never contradicts the booking flow.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpddbp_hero_doctor_ids' ) ) {
	/**
	 * Resolve the featured doctor IDs for the card.
	 *
	 * @param array $ids Explicit featured IDs (from the block). Empty ⇒ all published.
	 * @param int   $limit Max doctors to return for the dropdown.
	 * @return int[] Ordered, validated, published doctor IDs.
	 */
	function wpddbp_hero_doctor_ids( $ids = array(), $limit = 30 ) {
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( ! empty( $ids ) ) {
			// Keep only the ones that still exist and are published.
			return array_values( array_filter( $ids, static function ( $id ) {
				return 'publish' === get_post_status( $id ) && 'wpddb_doctor' === get_post_type( $id );
			} ) );
		}
		$q = get_posts( array(
			'post_type'      => 'wpddb_doctor',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		return array_map( 'intval', $q );
	}
}

if ( ! function_exists( 'wpddbp_hero_doctor_department' ) ) {
	/**
	 * First department (taxonomy term) name for a doctor.
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return array{id:int,name:string} Term id + name (0/'' when none).
	 */
	function wpddbp_hero_doctor_department( $doctor_id ) {
		$terms = wp_get_post_terms( (int) $doctor_id, 'wpddb_doctor_department' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array( 'id' => 0, 'name' => '' );
		}
		return array( 'id' => (int) $terms[0]->term_id, 'name' => $terms[0]->name );
	}
}

if ( ! function_exists( 'wpddbp_hero_doctor_clinics' ) ) {
	/**
	 * Clinics a doctor practises at, from the schedule meta (de-duplicated).
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return array<int,array{id:int,name:string}> Keyed by clinic id.
	 */
	function wpddbp_hero_doctor_clinics( $doctor_id ) {
		$schedule = get_post_meta( (int) $doctor_id, 'wpddb_doctor_schedule', true );
		$out      = array();
		if ( is_array( $schedule ) ) {
			foreach ( $schedule as $day ) {
				if ( empty( $day['clinics'] ) || ! is_array( $day['clinics'] ) ) {
					continue;
				}
				foreach ( $day['clinics'] as $c ) {
					$cid = (int) ( $c['id'] ?? 0 );
					if ( $cid && ! isset( $out[ $cid ] ) ) {
						$out[ $cid ] = array( 'id' => $cid, 'name' => (string) ( $c['name'] ?? get_the_title( $cid ) ) );
					}
				}
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'wpddbp_hero_earliest_slot' ) ) {
	/**
	 * The doctor's next genuinely-available slot, scanning forward from today.
	 *
	 * Mirrors the free plugin's availability rules: weekday schedule + `available`
	 * flag + per-timing `is_bookable` + holiday ranges + already-booked slots +
	 * past-time-today. Returns null when nothing is open within the window.
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @param int $clinic_id Restrict to one clinic (0 = any).
	 * @param int $days      How many days ahead to scan.
	 * @return array{date:string,day:string,time:string,clinic_id:int,clinic_name:string}|null
	 */
	function wpddbp_hero_earliest_slot( $doctor_id, $clinic_id = 0, $days = 45 ) {
		$doctor_id = (int) $doctor_id;
		$schedule  = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
		if ( ! is_array( $schedule ) || empty( $schedule ) ) {
			return null;
		}
		$clinics_info = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );

		global $wpdb;
		$booking_table = $wpdb->prefix . 'wpddb_bookings';
		$today         = current_time( 'Y-m-d' );
		$now_24        = current_time( 'H:i' );

		for ( $i = 0; $i < $days; $i++ ) {
			$date    = wp_date( 'Y-m-d', strtotime( $today . ' +' . $i . ' days' ) );
			$weekday = wp_date( 'l', strtotime( $date ) );

			// Matching, available weekday?
			$day_schedule = null;
			foreach ( $schedule as $day_item ) {
				if ( isset( $day_item['day'] ) && strcasecmp( $day_item['day'], $weekday ) === 0 && ! empty( $day_item['available'] ) ) {
					$day_schedule = $day_item;
					break;
				}
			}
			if ( ! $day_schedule || empty( $day_schedule['clinics'] ) ) {
				continue;
			}

			// Booked slots for this date.
			$booked = $wpdb->get_results( $wpdb->prepare(
				"SELECT time, clinic_id FROM {$booking_table} WHERE doctor_id = %d AND booking_date = %s AND status = 'approved' AND booking_present_status = 'upcoming'",
				$doctor_id,
				$date
			), ARRAY_A );
			$booked_lookup = array();
			foreach ( (array) $booked as $b ) {
				$booked_lookup[ $b['clinic_id'] . '_' . $b['time'] ] = true;
			}

			$is_today = ( $date === $today );

			foreach ( $day_schedule['clinics'] as $clinic ) {
				$cid = (int) ( $clinic['id'] ?? 0 );
				if ( $clinic_id && $cid !== (int) $clinic_id ) {
					continue;
				}

				// Holiday?
				if ( is_array( $clinics_info ) && isset( $clinics_info[ $cid ]['is_holiday'] ) && $clinics_info[ $cid ]['is_holiday']
					&& ! empty( $clinics_info[ $cid ]['holiday_dates']['start_date'] ) && ! empty( $clinics_info[ $cid ]['holiday_dates']['end_date'] ) ) {
					$dts = strtotime( $date );
					if ( $dts >= strtotime( $clinics_info[ $cid ]['holiday_dates']['start_date'] ) && $dts <= strtotime( $clinics_info[ $cid ]['holiday_dates']['end_date'] ) ) {
						continue;
					}
				}

				$timings = is_array( $clinic['timings'] ?? null ) ? $clinic['timings'] : array();
				usort( $timings, static function ( $a, $b ) {
					return strtotime( $a['time'] ?? '' ) - strtotime( $b['time'] ?? '' );
				} );

				foreach ( $timings as $timing ) {
					if ( empty( $timing['is_bookable'] ) ) {
						continue;
					}
					$time_str = (string) ( $timing['time'] ?? '' );
					// Match booked slots against both the raw label and a 24h normalisation.
					$is_booked = isset( $booked_lookup[ $cid . '_' . $time_str ] ) || isset( $booked_lookup[ $cid . '_' . wp_date( 'H:i', strtotime( $time_str ) ) ] );
					$is_past   = $is_today && strtotime( $time_str ) <= strtotime( $now_24 );
					if ( $is_booked || $is_past ) {
						continue;
					}
					return array(
						'date'        => $date,
						'day'         => $weekday,
						'time'        => $time_str,
						'clinic_id'   => $cid,
						'clinic_name' => (string) ( $clinic['name'] ?? get_the_title( $cid ) ),
					);
				}
			}
		}
		return null;
	}
}

if ( ! function_exists( 'wpddbp_hero_format_slot' ) ) {
	/**
	 * Human label for a resolved slot, e.g. "Today, 9:00 AM" / "Tue 15 Jul, 9:00 AM".
	 *
	 * @param array|null $slot Slot from wpddbp_hero_earliest_slot().
	 * @return string Empty string when no slot.
	 */
	function wpddbp_hero_format_slot( $slot ) {
		if ( empty( $slot ) || empty( $slot['date'] ) ) {
			return '';
		}
		$today = current_time( 'Y-m-d' );
		$tmrw  = wp_date( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		if ( $slot['date'] === $today ) {
			$day = __( 'Today', 'doc-booker-pro' );
		} elseif ( $slot['date'] === $tmrw ) {
			$day = __( 'Tomorrow', 'doc-booker-pro' );
		} else {
			$day = wp_date( 'D j M', strtotime( $slot['date'] ) );
		}
		return $day . ( ! empty( $slot['time'] ) ? ', ' . $slot['time'] : '' );
	}
}
