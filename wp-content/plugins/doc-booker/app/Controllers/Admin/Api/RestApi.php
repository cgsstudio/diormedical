<?php

namespace WpDreamers\WPDDB\Controllers\Admin\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDB\Traits\Constants;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class RestApi {
	use SingletonTrait, Constants;

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'wpddb_register_rest_api_endpoint' ] );

	}

	public function wpddb_register_rest_api_endpoint() {
		register_rest_route( $this->doctor_endpoint_namespace, '/posts', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_all_doctors' ],
			'permission_callback' => '__return_true',
		) );
		register_rest_route( $this->doctor_endpoint_namespace, '/categories', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_all_rest_doctor_categories' ],
			'permission_callback' => '__return_true',
		) );
		register_rest_route( $this->clinic_endpoint_namespace, '/posts', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_all_rest_clinics' ],
			'permission_callback' => '__return_true',
		) );
		register_rest_route( $this->wpddb_endpoint_namespace, '/pages', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_all_pages' ],
			'permission_callback' => '__return_true',
		) );
		register_rest_route( $this->wpddb_endpoint_namespace, '/options', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_all_settings_options' ],
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );

		// Get departments
		register_rest_route( 'get-doctor/v1', '/departments', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_departments' ),
			'permission_callback' => '__return_true'
		) );

		// Get doctor by department
		register_rest_route( 'get-doctor-by-departments/v1', '/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_doctor_by_departments' ],
			'permission_callback' => '__return_true',
		) );

		// Get doctor schedule
		register_rest_route( 'doctor-details-booking/v1', '/doctors/(?P<id>\d+)/schedule', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_doctor_schedule' ],
			'permission_callback' => array( $this, 'verify_doctor_schedule_access' ),
		) );

		// Create booking
		register_rest_route( 'doctor-details-booking/v1', '/bookings', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_doctor_details_booking' ],
			'permission_callback' => array( $this, 'verify_booking_creation' ),
		) );
		register_rest_route( $this->wpddb_endpoint_namespace, '/booking_details', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_booking_details' ],
			'permission_callback' => array( $this, 'verify_get_booking_access' ),
		) );

		// Get doctor clinics info (holiday data)
		register_rest_route( 'doctor-details-booking/v1', '/doctors/(?P<id>\d+)/clinics-info', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_doctor_clinics_info' ],
			'permission_callback' => array( $this, 'verify_doctor_schedule_access' ),
		) );

		// Get date-specific availability
		register_rest_route( 'doctor-details-booking/v1', '/doctors/(?P<id>\d+)/availability', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_date_availability' ],
			'permission_callback' => array( $this, 'verify_doctor_schedule_access' ),
		) );
	}

	public function get_all_doctors( $request = null ) {
		$clinic_id   = $request ? (int) $request->get_param( 'clinic_id' ) : 0;
		$category_id = $request ? (int) $request->get_param( 'category_id' ) : 0;

		$args = [
			'post_type'      => wpddb()->post_type_doctor,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $category_id > 0 ) {
			$args['tax_query'] = [
				[
					'taxonomy' => wpddb()->doctor_category,
					'field'    => 'term_id',
					'terms'    => $category_id,
				],
			];
		}

		$doctor_ids = get_posts( $args );

		if ( $doctor_ids && $clinic_id > 0 ) {
			$doctor_ids = self::filter_doctor_ids_by_clinic( $doctor_ids, $clinic_id );
		}

		$doctor_list = [];
		if ( $doctor_ids ) {
			foreach ( $doctor_ids as $doctor_id ) {
				$doctor_list[] = [
					'value' => (int) $doctor_id,
					'label' => get_the_title( $doctor_id ) ?: '#' . $doctor_id,
				];
			}
		}

		return wp_json_encode( $doctor_list );
	}

	/**
	 * Filters a doctor ID list down to those whose schedule meta references a given clinic_id.
	 * Bulk-fetches schedule metas in a single SELECT to avoid N+1 lookups.
	 */
	public static function filter_doctor_ids_by_clinic( array $doctor_ids, $clinic_id ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $doctor_ids ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = 'wpddb_doctor_schedule' AND post_id IN ($placeholders)",
			...$doctor_ids
		), ARRAY_A );

		$matched = [];
		foreach ( $rows as $row ) {
			$schedule = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $schedule ) ) {
				continue;
			}
			foreach ( $schedule as $day ) {
				if ( empty( $day['clinics'] ) || ! is_array( $day['clinics'] ) ) {
					continue;
				}
				foreach ( $day['clinics'] as $clinic ) {
					if ( isset( $clinic['id'] ) && (int) $clinic['id'] === (int) $clinic_id ) {
						$matched[ (int) $row['post_id'] ] = true;
						break 2;
					}
				}
			}
		}

		return array_values( array_intersect( $doctor_ids, array_keys( $matched ) ) );
	}

	public function get_departments() {
		$terms       = get_terms( array(
			'taxonomy' => wpddb()->doctor_category,
		) );
		$departments = array();
		if ( ! is_wp_error( $terms ) && $terms ) {
			foreach ( $terms as $term ) {
				$departments[] = array(
					'id'    => $term->term_id,
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => $term->count
				);
			}
		}

		return wp_json_encode( $departments );
	}

	public function get_doctor_by_departments( WP_REST_Request $request ) {

		$department_id = intval( $request->get_param( 'id' ) );
		$args          = array(
			'post_type'      => wpddb()->post_type_doctor,
			'posts_per_page' => - 1,
			'tax_query'      => array(
				array(
					'taxonomy' => wpddb()->doctor_category,
					'field'    => 'term_id',
					'terms'    => $department_id,
				),
			),
		);

		$doctors     = get_posts( $args );

		$doctor_data = [];
		if ( $doctors ) {
			foreach ( $doctors as $doctor ) {
				$doctor_data[] = array(
					'id'          => $doctor->ID,
					'name'        => $doctor->post_title,
					'designation' => get_post_meta( $doctor->ID, 'wpddb_doctor_designation', true ),
					'speciality' => get_post_meta( $doctor->ID, 'wpddb_doctor_speciality', true ),
					'degree' => get_post_meta( $doctor->ID, 'wpddb_doctor_degree', true ),
					'image'       => get_the_post_thumbnail_url( $doctor->ID, 'medium' ),
					'work_place'  => get_post_meta( $doctor->ID, 'wpddb_doctor_workplace', true ),
				);
			}
		}

		return wp_json_encode( $doctor_data );
	}

	public function get_all_rest_doctor_categories( $request = null ) {
		$clinic_id = $request ? (int) $request->get_param( 'clinic_id' ) : 0;

		$terms = get_terms( [
			'taxonomy'   => wpddb()->doctor_category,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return wp_json_encode( [] );
		}

		// When a clinic is selected, restrict the term list to those terms attached to at least
		// one doctor whose schedule references that clinic. Lets the UI cascade clinic→department.
		if ( $clinic_id > 0 ) {
			$doctor_ids = get_posts( [
				'post_type'      => wpddb()->post_type_doctor,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );
			$doctor_ids = $doctor_ids ? self::filter_doctor_ids_by_clinic( $doctor_ids, $clinic_id ) : [];

			if ( ! $doctor_ids ) {
				return wp_json_encode( [] );
			}

			$valid_term_ids = wp_get_object_terms( $doctor_ids, wpddb()->doctor_category, [ 'fields' => 'ids' ] );
			$valid_term_ids = is_wp_error( $valid_term_ids ) ? [] : array_map( 'intval', $valid_term_ids );

			$terms = array_filter( $terms, fn( $t ) => in_array( (int) $t->term_id, $valid_term_ids, true ) );
		}

		$terms_list = [];
		foreach ( $terms as $term ) {
			$terms_list[] = [
				'value' => (int) $term->term_id,
				'label' => $term->name,
			];
		}

		return wp_json_encode( array_values( $terms_list ) );
	}

	public function get_all_rest_clinics() {
		$clinic_list = [];
		$args        = array(
			'post_type'   => wpddb()->post_type_clinic,
			'post_status' => 'publish',
			'numberposts' => - 1,
		);
		$clinics     = get_posts( $args );
		if ( $clinics ) {
			foreach ( $clinics as $clinic ) {
				$clinic_list[] = [
					'value' => $clinic->ID,
					'label' => ! empty( $clinic->post_title ) ? $clinic->post_title : '#' . $clinic->ID
				];
			}
		}

		return wp_json_encode( $clinic_list );

	}

	public function get_all_pages( $data ) {
		$page_list = [];
		$pages     = get_pages(
			[
				'sort_column'  => 'menu_order',
				'sort_order'   => 'ASC',
				'hierarchical' => 0,
			]
		);
		if ( $pages ) {
			foreach ( $pages as $page ) {
				$page_list[] = [
					'value' => $page->ID,
					'label' => ! empty( $page->post_title ) ? $page->post_title : '#' . $page->ID
				];
			}
		}

		return wp_json_encode( $page_list );
	}

	public function get_all_settings_options() {
		return WpddbOptions::wpddb_get_all_settings_options();
	}

	public function check_admin_permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to access this endpoint.', 'doc-booker' ), array( 'status' => 403 ) );
		}

		return true;
	}

	function verify_doctor_schedule_access( WP_REST_Request $request ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			// For public access, we can implement nonce verification
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return false;
			}
		}

		$doctor_id = intval( $request->get_param( 'id' ) );
		$doctor    = get_post( $doctor_id );


		if ( ! $doctor || $doctor->post_status !== 'publish' || $doctor->post_type !== wpddb()->post_type_doctor ) {
			return false;
		}

		return true;
	}

	public function verify_get_booking_access( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) || ! current_user_can( 'wpddb_manage_booking_system' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to access this endpoint.', 'doc-booker' ), array( 'status' => 403 ) );
		}

		return true;
	}

	public function verify_booking_creation( WP_REST_Request $request ) {
		// Verify nonce
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		// Additional verification as needed
		$params = $request->get_params();


		// Verify doctor exists and is active
		if ( isset( $params['doctorId'] ) ) {
			$doctor = get_post( $params['doctorId'] );
			if ( ! $doctor || $doctor->post_status !== 'publish' || $doctor->post_type !== wpddb()->post_type_doctor ) {
				return false;
			}
		}

		return true;
	}

	public function get_doctor_schedule( $request ) {
		$doctor_id = intval( $request->get_param( 'id' ) );

		// Validate doctor ID
		if ( ! get_post( $doctor_id ) || get_post_type( $doctor_id ) !== wpddb()->post_type_doctor ) {
			return new WP_REST_Response( array( 'error' => 'Doctor not found' ), 404 );
		}

		// Get doctor schedule from post meta
		$schedules = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );

		if ( ! $schedules ) {
			return new WP_REST_Response( array( 'error' => 'No schedule found for this doctor' ), 404 );
		}
		foreach ( $schedules as &$day_schedule ) {
			foreach ( $day_schedule['clinics'] as &$clinic ) {
				usort( $clinic['timings'], function ( $a, $b ) {
					return strtotime( $a['time'] ) - strtotime( $b['time'] );
				} );
			}
		}

		return new WP_REST_Response( $schedules, 200 );
	}

	public function create_doctor_details_booking( $request ) {
		$params = $request->get_params();

		// Validate required fields
		if (
			! isset( $params['doctorId'] ) ||
			! isset( $params['day'] ) ||
			! isset( $params['clinicId'] ) ||
			! isset( $params['time'] ) ||
			! isset( $params['patient'] ) ||
			! isset( $params['patient']['name'] ) ||
			! isset( $params['patient']['email'] ) ||
			! isset( $params['patient']['phone'] )
		) {
			return new WP_REST_Response( array( 'error' => 'Missing required fields' ), 400 );
		}

		$doctor_id        = intval( $params['doctorId'] );
		$day              = sanitize_text_field( $params['day'] );
		$clinic_id        = intval( $params['clinicId'] );
		$time             = sanitize_text_field( $params['time'] );
		$booking_date     = isset( $params['bookingDate'] ) ? sanitize_text_field( $params['bookingDate'] ) : null;
		$has_booking_date = ! empty( $booking_date ) && strtotime( $booking_date );

        $online_booking_payment = WpddbOptions::get_option('online_booking_payment','wpddb_global_settings') ?:'on';
        $transaction_id = '';
        $amount_paid = 0.00;
        $payment_status = '';
        $payment_by = '';
        if ( wpddb()->has_pro() && $online_booking_payment === 'on' && ! empty( $params['transactionId'] ) ) {
            $transaction_id = sanitize_text_field( $params['transactionId'] );
            $payment_by = sanitize_text_field( $params['paymentGateway'] );
            $amount_paid = floatval( $params['amountPaid'] ?? 0 );
            $payment_status = 'completed';
        }

		$sanitize_patient = [
			'name'  => sanitize_text_field( $params['patient']['name'] ),
			'phone' => sanitize_text_field( $params['patient']['phone'] ),
			'email' => sanitize_email( $params['patient']['email'] ),
			'note'  => sanitize_text_field( $params['patient']['notes'] ) ?? ''
		];

		$booking_id       = Helper::generate_booking_id();

		// Validate doctor exists
		if ( ! get_post( $doctor_id ) || get_post_type( $doctor_id ) !== wpddb()->post_type_doctor ) {
			return new WP_REST_Response( array( 'error' => 'Doctor not found' ), 404 );
		}

		// If booking_date is provided, validate it
		if ( $has_booking_date ) {
			// Validate date is not in the past
			$today = current_time( 'Y-m-d' );
			if ( $booking_date < $today ) {
				return new WP_REST_Response( array( 'error' => 'Cannot book a date in the past' ), 400 );
			}

			// If booking is for today, reject if the time slot has already passed
			if ( $booking_date === $today ) {
				$slot_time_24 = Helper::convert_time_format( $time );
				if ( strtotime( $slot_time_24 ) <= strtotime( current_time( 'H:i' ) ) ) {
					return new WP_REST_Response( array( 'error' => 'Cannot book a time slot that has already passed' ), 400 );
				}
			}

			// Validate against pre-booking window (0 = no limit)
			$pre_booking_window = max( 0, intval( WpddbOptions::get_option( 'pre_booking_window', 'wpddb_doctor_settings', 0 ) ) );
			if ( $pre_booking_window > 0 ) {
				$max_date = wp_date( 'Y-m-d', strtotime( $today . ' +' . ( $pre_booking_window - 1 ) . ' days' ) );
				if ( $booking_date > $max_date ) {
					return new WP_REST_Response( array( 'error' => sprintf( 'Bookings can only be made up to %d day(s) in advance', $pre_booking_window ) ), 400 );
				}
			}

			// Validate weekday matches. Use a locale-independent English name: the
			// schedule stores days in English, while wp_date() would translate them.
			$date_weekday = Helper::get_weekday_name( $booking_date );
			if ( strcasecmp( $date_weekday, $day ) !== 0 ) {
				return new WP_REST_Response( array( 'error' => 'Date does not match the selected day' ), 400 );
			}

			// Check holiday conflict
			$clinics_info = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );
			if ( is_array( $clinics_info ) && isset( $clinics_info[ $clinic_id ] ) ) {
				$info = $clinics_info[ $clinic_id ];
				if ( ! empty( $info['is_holiday'] ) && ! empty( $info['holiday_dates']['start_date'] ) && ! empty( $info['holiday_dates']['end_date'] ) ) {
					$date_ts  = strtotime( $booking_date );
					$start_ts = strtotime( $info['holiday_dates']['start_date'] );
					$end_ts   = strtotime( $info['holiday_dates']['end_date'] );
					if ( $date_ts >= $start_ts && $date_ts <= $end_ts ) {
						return new WP_REST_Response( array( 'error' => 'Doctor is on holiday on this date' ), 400 );
					}
				}
			}
		}

		// Get doctor schedule
		$schedule = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );

		if ( ! $schedule ) {
			return new WP_REST_Response( array( 'error' => 'No schedule found for this doctor' ), 404 );
		}

		// Find the day in the schedule
		$day_index    = - 1;
		$clinic_index = - 1;
		$time_index   = - 1;

		foreach ( $schedule as $index => $day_item ) {
			if ( strcasecmp( $day_item['day'], $day ) === 0 ) {
				$day_index = $index;

				// Find the clinic in the day's clinics
				foreach ( $day_item['clinics'] as $c_index => $clinic ) {
					if ( $clinic['id'] == $clinic_id ) {
						$clinic_index = $c_index;

						// Find the time in the clinic's timings
						foreach ( $clinic['timings'] as $t_index => $timing ) {
							if ( $timing['time'] === $time ) {
								$time_index = $t_index;
								break;
							}
						}

						break;
					}
				}

				break;
			}
		}
		// Validate time slot exists and is bookable (admin-level flag)
		if (
			$day_index === - 1 ||
			$clinic_index === - 1 ||
			$time_index === - 1 ||
			! $schedule[ $day_index ]['clinics'][ $clinic_index ]['timings'][ $time_index ]['is_bookable']
		) {
			return new WP_REST_Response( array( 'error' => 'Time slot not available' ), 400 );
		}

		global $wpdb;
		$booking_table_name = $wpdb->prefix . 'wpddb_bookings';

		// For date-based bookings, check if the slot is already booked for this specific date
		if ( $has_booking_date ) {
			$time_24  = Helper::convert_time_format( $time );
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $booking_table_name WHERE doctor_id = %d AND booking_date = %s AND clinic_id = %d AND time = %s AND status = 'approved' AND booking_present_status = 'upcoming'",
				$doctor_id, $booking_date, $clinic_id, $time_24
			) );
			if ( $existing > 0 ) {
				return new WP_REST_Response( array( 'error' => 'Time slot not available for this date' ), 400 );
			}
		}
		$patients_table     = $wpdb->prefix . 'wpddb_patients';
		$inserted_booking   = false;

		$patient_id         = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $patients_table WHERE email = %s",
			$sanitize_patient['email']
		) );

		if ( ! $patient_id ) {
			$wpdb->insert(
				$patients_table,
				[
					'full_name' => $sanitize_patient['name'],
					'email'     => $sanitize_patient['email'],
					'phone'     => $sanitize_patient['phone'],
				],
				[ '%s', '%s', '%s' ]
			);
			$patient_id = $wpdb->insert_id;
		}

		if ( $patient_id ) {
			$insert_data = [
				'booking_id'            => $booking_id,
				'patient_id'            => $patient_id,
				'doctor_id'             => $doctor_id,
				'clinic_id'             => $clinic_id,
				'day'                   => $day,
				'time'                  => Helper::convert_time_format( $time ),
				'patient_note'          => $sanitize_patient['note'],
				'status'                => 'approved',
				'booking_present_status' => 'upcoming',
				'created_at'            => current_time( 'mysql' ),
				'updated_at'            => current_time( 'mysql' ),
				'transaction_id'        => $transaction_id ?? '',
				'amount_paid'           => $amount_paid ?? 0.00,
				'payment_status'        => $payment_status ?? 'pending',
				'payment_by'            => $payment_by ?? '',
			];
			$insert_format = [ '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' ];

			if ( $has_booking_date ) {
				$insert_data['booking_date'] = $booking_date;
				$insert_format[]             = '%s';
			}

			$inserted_booking = $wpdb->insert( $booking_table_name, $insert_data, $insert_format );
		}

		if ( ! $inserted_booking ) {
			return new WP_REST_Response( array( 'error' => 'Failed to create booking' ), 500 );
		}

		// For legacy bookings (no date), toggle is_bookable in post meta
		// For date-based bookings, availability is determined by querying the bookings table
		if ( ! $has_booking_date ) {
			$schedule[ $day_index ]['clinics'][ $clinic_index ]['timings'][ $time_index ]['is_bookable'] = '';
			update_post_meta( $doctor_id, 'wpddb_doctor_schedule', $schedule );
		}

		// Send notification emails
		Helper::send_booking_notifications( $booking_id, $doctor_id, $day, $time, $clinic_id, $sanitize_patient, 'success', $booking_date );

		// Notify extensions (Pro plugin uses this to send a confirmation SMS).
		do_action( 'wpddb_booking_created', [
			'booking_id'   => $booking_id,
			'patient_id'   => (int) $patient_id,
			'patient_name' => $sanitize_patient['name'],
			'patient_phone'=> $sanitize_patient['phone'],
			'patient_email'=> $sanitize_patient['email'],
			'doctor_id'    => $doctor_id,
			'clinic_id'    => $clinic_id,
			'day'          => $day,
			'time'         => $time,
			'booking_date' => $has_booking_date ? $booking_date : null,
		] );

		return new WP_REST_Response( array(
			'success'    => true,
			'booking_id' => $booking_id,
			'message'    => 'Booking created successfully'
		), 201 );
	}

	public function get_doctor_clinics_info( $request ) {
		$doctor_id   = intval( $request->get_param( 'id' ) );
		$clinics_info = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );

		if ( ! $clinics_info || ! is_array( $clinics_info ) ) {
			return new WP_REST_Response( new \stdClass(), 200 );
		}

		return new WP_REST_Response( $clinics_info, 200 );
	}

	public function get_date_availability( $request ) {
		$doctor_id = intval( $request->get_param( 'id' ) );
		$date      = sanitize_text_field( $request->get_param( 'date' ) );

		if ( ! $date || ! strtotime( $date ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid date parameter' ), 400 );
		}

		// Enforce pre-booking window (0 = no limit)
		$pre_booking_window = max( 0, intval( WpddbOptions::get_option( 'pre_booking_window', 'wpddb_doctor_settings', 0 ) ) );
		if ( $pre_booking_window > 0 ) {
			$today    = current_time( 'Y-m-d' );
			$max_date = wp_date( 'Y-m-d', strtotime( $today . ' +' . ( $pre_booking_window - 1 ) . ' days' ) );
			if ( $date > $max_date ) {
				return new WP_REST_Response( array( 'error' => sprintf( 'Date is outside the booking window (%d day(s) max)', $pre_booking_window ) ), 400 );
			}
		}

		// Derive weekday from date as a locale-independent English name, so it
		// matches the English day names stored in the schedule on every language.
		$weekday = Helper::get_weekday_name( $date );

		// Get doctor schedule
		$schedules = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
		if ( ! $schedules ) {
			return new WP_REST_Response( array( 'error' => 'No schedule found' ), 404 );
		}

		// Find the matching day in the schedule
		$day_schedule = null;
		foreach ( $schedules as $day_item ) {
			if ( strcasecmp( $day_item['day'], $weekday ) === 0 && ! empty( $day_item['available'] ) ) {
				$day_schedule = $day_item;
				break;
			}
		}

		if ( ! $day_schedule ) {
			return new WP_REST_Response( array( 'error' => 'Doctor is not available on this day' ), 404 );
		}

		// Get clinics info for holiday checking
		$clinics_info = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );

		// Get existing bookings for this date
		global $wpdb;
		$booking_table = $wpdb->prefix . 'wpddb_bookings';
		$booked_slots  = $wpdb->get_results( $wpdb->prepare(
			"SELECT time, clinic_id FROM $booking_table WHERE doctor_id = %d AND booking_date = %s AND status = 'approved' AND booking_present_status = 'upcoming'",
			$doctor_id,
			$date
		), ARRAY_A );

		// Build a lookup of booked time+clinic combos
		$booked_lookup = [];
		foreach ( $booked_slots as $slot ) {
			$booked_lookup[ $slot['clinic_id'] . '_' . $slot['time'] ] = true;
		}

		// If the requested date is today, slots whose time has already passed must not be bookable
		$is_today      = ( $date === current_time( 'Y-m-d' ) );
		$now_24        = current_time( 'H:i' );

		// Build availability response
		$clinics_result = [];
		foreach ( $day_schedule['clinics'] as $clinic ) {
			$clinic_id = $clinic['id'];

			// Check if this clinic is on holiday for the requested date
			$is_on_holiday = false;
			if ( is_array( $clinics_info ) && isset( $clinics_info[ $clinic_id ] ) ) {
				$info = $clinics_info[ $clinic_id ];
				if ( ! empty( $info['is_holiday'] ) && ! empty( $info['holiday_dates']['start_date'] ) && ! empty( $info['holiday_dates']['end_date'] ) ) {
					$date_ts  = strtotime( $date );
					$start_ts = strtotime( $info['holiday_dates']['start_date'] );
					$end_ts   = strtotime( $info['holiday_dates']['end_date'] );
					if ( $date_ts >= $start_ts && $date_ts <= $end_ts ) {
						$is_on_holiday = true;
					}
				}
			}

			$timings = [];
			foreach ( $clinic['timings'] as $timing ) {
				$time_24     = Helper::convert_time_format( $timing['time'] );
				$is_booked   = isset( $booked_lookup[ $clinic_id . '_' . $time_24 ] );
				$is_past     = $is_today && strtotime( $time_24 ) <= strtotime( $now_24 );
				$is_available = ! empty( $timing['is_bookable'] ) && ! $is_booked && ! $is_on_holiday && ! $is_past;

				$timings[] = [
					'time'         => $timing['time'],
					'is_available' => $is_available,
				];
			}

			// Sort timings by time
			usort( $timings, function ( $a, $b ) {
				return strtotime( $a['time'] ) - strtotime( $b['time'] );
			} );

			$clinics_result[] = [
				'id'            => $clinic_id,
				'name'          => $clinic['name'],
				'timings'       => $timings,
				'is_on_holiday' => $is_on_holiday,
			];
		}

		return new WP_REST_Response( [
			'date'    => $date,
			'day'     => $weekday,
			'clinics' => $clinics_result,
		], 200 );
	}

	public function get_booking_details( $request ) {
		$args     = self::collect_booking_query_args( $request );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = max( 1, min( 200, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$results = self::run_booking_query( $args, $per_page, $offset );
		$total   = self::run_booking_count( $args );

		$response = new WP_REST_Response( $results );
		$response->set_status( 200 );
		$response->header( 'X-WP-Total', (int) $total );
		$response->header( 'X-WP-TotalPages', $per_page > 0 ? (int) ceil( $total / $per_page ) : 0 );

		return $response;
	}

	/**
	 * Collects and sanitizes booking query args from a REST request.
	 *
	 * Used by both the admin booking-details endpoint and the Pro CSV export endpoint
	 * so they apply identical filter semantics.
	 */
	public static function collect_booking_query_args( $request ) {
		$order_by_allowed = [ 'created_at', 'booking_date', 'amount_paid', 'id' ];
		$order_by         = $request->get_param( 'order_by' );
		$order            = strtoupper( (string) $request->get_param( 'order' ) );

		return [
			'search'                 => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'date_from'              => sanitize_text_field( (string) $request->get_param( 'date_from' ) ),
			'date_to'                => sanitize_text_field( (string) $request->get_param( 'date_to' ) ),
			'doctor_id'              => (int) $request->get_param( 'doctor_id' ),
			'clinic_id'              => (int) $request->get_param( 'clinic_id' ),
			'department_id'          => (int) $request->get_param( 'department_id' ),
			'status'                 => sanitize_text_field( (string) $request->get_param( 'status' ) ),
			'booking_present_status' => sanitize_text_field( (string) $request->get_param( 'booking_present_status' ) ),
			'payment_status'         => sanitize_text_field( (string) $request->get_param( 'payment_status' ) ),
			'payment_by'             => sanitize_text_field( (string) $request->get_param( 'payment_by' ) ),
			'amount_min'             => is_numeric( $request->get_param( 'amount_min' ) ) ? (float) $request->get_param( 'amount_min' ) : null,
			'amount_max'             => is_numeric( $request->get_param( 'amount_max' ) ) ? (float) $request->get_param( 'amount_max' ) : null,
			'ids'                    => array_filter( array_map( 'intval', (array) $request->get_param( 'ids' ) ) ),
			'order_by'               => in_array( $order_by, $order_by_allowed, true ) ? $order_by : 'created_at',
			'order'                  => $order === 'ASC' ? 'ASC' : 'DESC',
		];
	}

	/**
	 * Builds and executes the paginated booking query, including N+1-free title hydration.
	 */
	public static function run_booking_query( array $args, $per_page, $offset ) {
		global $wpdb;
		$booking_table  = $wpdb->prefix . 'wpddb_bookings';
		$patients_table = $wpdb->prefix . 'wpddb_patients';

		// Pro extension point — allows clinic_manager scoping to override args before SQL is built.
		$args = apply_filters( 'wpddb_bookings_query_args', $args );

		[ $join_sql, $where_sql, $where_args ] = self::build_booking_where_clauses( $args );

		$select  = "SELECT b.id, b.booking_id, b.doctor_id, b.clinic_id, b.patient_id,
		                  b.booking_date, b.day, b.time, b.status, b.booking_present_status,
		                  b.created_at, b.patient_note, b.payment_status, b.transaction_id,
		                  b.amount_paid, b.payment_by,
		                  p.full_name, p.phone, p.email";
		$from    = "FROM {$booking_table} AS b LEFT JOIN {$patients_table} AS p ON b.patient_id = p.id";
		$order   = "ORDER BY b.{$args['order_by']} {$args['order']}";
		$limit   = '';
		$prepare = $where_args;

		if ( $per_page > 0 ) {
			$limit     = 'LIMIT %d OFFSET %d';
			$prepare[] = (int) $per_page;
			$prepare[] = (int) $offset;
		}

		$sql = trim( "$select $from $join_sql $where_sql $order $limit" );
		$sql = empty( $prepare ) ? $sql : $wpdb->prepare( $sql, ...$prepare );

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results ? self::hydrate_booking_titles( $results ) : [];
	}

	/**
	 * Count query mirroring the same JOIN/WHERE so X-WP-Total stays in sync.
	 */
	public static function run_booking_count( array $args ) {
		global $wpdb;
		$booking_table  = $wpdb->prefix . 'wpddb_bookings';
		$patients_table = $wpdb->prefix . 'wpddb_patients';

		$args = apply_filters( 'wpddb_bookings_query_args', $args );

		[ $join_sql, $where_sql, $where_args ] = self::build_booking_where_clauses( $args );

		$sql = "SELECT COUNT(*) FROM {$booking_table} AS b LEFT JOIN {$patients_table} AS p ON b.patient_id = p.id $join_sql $where_sql";
		$sql = empty( $where_args ) ? $sql : $wpdb->prepare( $sql, ...$where_args );

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Returns [ $join_sql, $where_sql, $where_args ] derived from the arg set.
	 *
	 * Pro plugin extends behavior via the `wpddb_bookings_query_join` and
	 * `wpddb_bookings_query_where` filters; Pro returns:
	 *   - extra JOIN string (no leading space required)
	 *   - extra WHERE-fragment array (each fragment must be a complete `column op %placeholder` clause)
	 *   - matching placeholder values appended to a single $where_args array
	 */
	public static function build_booking_where_clauses( array $args ) {
		global $wpdb;
		$booking_table = $wpdb->prefix . 'wpddb_bookings';

		$join       = '';
		$where      = [];
		$where_args = [];

		// IDs (used by bulk export "selected only" mode)
		if ( ! empty( $args['ids'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $args['ids'] ), '%d' ) );
			$where[]      = "b.id IN ($placeholders)";
			$where_args   = array_merge( $where_args, $args['ids'] );
		}

		// Search across booking_id, phone, full_name, email
		if ( $args['search'] !== '' ) {
			$like         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]      = '(b.booking_id LIKE %s OR p.phone LIKE %s OR p.full_name LIKE %s OR p.email LIKE %s)';
			$where_args[] = $like;
			$where_args[] = $like;
			$where_args[] = $like;
			$where_args[] = $like;
		}

		// Date range against booking_date (NULL booking_date = legacy and is excluded by date filters)
		if ( $args['date_from'] !== '' && strtotime( $args['date_from'] ) ) {
			$where[]      = 'b.booking_date >= %s';
			$where_args[] = $args['date_from'];
		}
		if ( $args['date_to'] !== '' && strtotime( $args['date_to'] ) ) {
			$where[]      = 'b.booking_date <= %s';
			$where_args[] = $args['date_to'];
		}

		// Doctor / clinic
		if ( $args['doctor_id'] > 0 ) {
			$where[]      = 'b.doctor_id = %d';
			$where_args[] = $args['doctor_id'];
		}
		if ( $args['clinic_id'] > 0 ) {
			$where[]      = 'b.clinic_id = %d';
			$where_args[] = $args['clinic_id'];
		}

		// Department: join taxonomy via doctor's term relationship
		if ( $args['department_id'] > 0 ) {
			$tr  = $wpdb->prefix . 'term_relationships';
			$tt  = $wpdb->prefix . 'term_taxonomy';
			$tax = wpddb()->doctor_category;
			$join .= " INNER JOIN {$tr} AS dept_tr ON dept_tr.object_id = b.doctor_id"
			       . " INNER JOIN {$tt} AS dept_tt ON dept_tt.term_taxonomy_id = dept_tr.term_taxonomy_id AND dept_tt.taxonomy = %s";
			array_unshift( $where_args, $tax ); // join placeholder must precede where placeholders in the final prepare()
			$where[]      = 'dept_tt.term_id = %d';
			$where_args[] = $args['department_id'];
		}

		// Booking status / present status (allowlists)
		if ( in_array( $args['status'], [ 'approved', 'cancelled', 'cancel' ], true ) ) {
			$where[]      = 'b.status = %s';
			$where_args[] = $args['status'];
		}
		if ( in_array( $args['booking_present_status'], [ 'upcoming', 'expired' ], true ) ) {
			$where[]      = 'b.booking_present_status = %s';
			$where_args[] = $args['booking_present_status'];
		}

		// Pro-extension hooks — Pro plugin returns its own WHERE fragments + values.
		$pro = apply_filters( 'wpddb_bookings_query_where', [], $args );
		if ( is_array( $pro ) && ! empty( $pro ) ) {
			foreach ( $pro as $clause ) {
				if ( is_array( $clause ) && isset( $clause['sql'] ) ) {
					$where[] = $clause['sql'];
					if ( isset( $clause['args'] ) && is_array( $clause['args'] ) ) {
						$where_args = array_merge( $where_args, $clause['args'] );
					}
				}
			}
		}

		$join      = apply_filters( 'wpddb_bookings_query_join', $join, $args );
		$where_sql = empty( $where ) ? '' : ' WHERE ' . implode( ' AND ', $where );

		return [ $join, $where_sql, $where_args ];
	}

	/**
	 * Replaces N×2 get_the_title() calls with a single SELECT for all referenced doctor/clinic posts.
	 */
	private static function hydrate_booking_titles( array $rows ) {
		global $wpdb;

		$post_ids = [];
		foreach ( $rows as $row ) {
			if ( ! empty( $row['doctor_id'] ) ) { $post_ids[] = (int) $row['doctor_id']; }
			if ( ! empty( $row['clinic_id'] ) ) { $post_ids[] = (int) $row['clinic_id']; }
		}
		$post_ids = array_unique( array_filter( $post_ids ) );

		$titles = [];
		if ( $post_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			$sql          = $wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($placeholders)",
				...$post_ids
			);
			foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $post ) {
				$titles[ (int) $post['ID'] ] = $post['post_title'];
			}
		}

		foreach ( $rows as &$row ) {
			$row['doctor_name'] = $row['doctor_id'] && isset( $titles[ (int) $row['doctor_id'] ] )
				? $titles[ (int) $row['doctor_id'] ] : '';
			$row['clinic_name'] = $row['clinic_id'] && isset( $titles[ (int) $row['clinic_id'] ] )
				? $titles[ (int) $row['clinic_id'] ] : '';
			$row['time']        = Helper::convert_time_format( $row['time'], '12' );
		}

		return $rows;
	}

}