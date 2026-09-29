<?php

namespace WpDreamers\WPDDB\Controllers;

use WP_Query;
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class AjaxControllers {
	use SingletonTrait;

	public function __construct() {
		add_action( 'wp_ajax_wpddb_save_settings', [ $this, 'save_options_settings' ] );
        add_action( 'wp_ajax_wpd_booking_cancel', [ $this, 'cancel_doctor_booking' ] );
        add_action( 'wp_ajax_wpd_delete_booking', [ $this, 'delete_doctor_booking' ] );

        // Doctor Filter Widget.
        add_action('wp_ajax_wpddb_get_clinics_by_category', [$this,'get_clinics_by_category']);
        add_action('wp_ajax_nopriv_wpddb_get_clinics_by_category', [$this,'get_clinics_by_category']);

        add_action('wp_ajax_wpddb_get_doctors_by_clinic', [$this,'get_doctors_by_clinic']);
        add_action('wp_ajax_nopriv_wpddb_get_doctors_by_clinic', [$this,'get_doctors_by_clinic']);
	}

	public function save_options_settings() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], wpddb()->nonceText ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Nonce verification failed.' );
		}

		$this->page_options_settings_save( 'wpddb_page_settings', $_POST['wpddb_page_settings'] );
		$this->permalink_options_settings_save( 'wpddb_permalinks_settings', $_POST['wpddb_permalinks_settings'] );
		$this->doctor_options_settings_save( 'wpddb_doctor_settings', $_POST['wpddb_doctor_settings'] );
		$this->clinic_options_settings_save( 'wpddb_clinic_settings', $_POST['wpddb_clinic_settings'] );
		$this->style_options_settings_save( 'wpddb_style_settings', $_POST['wpddb_style_settings'] );

        do_action( 'wpddb_save_settings',$_POST );
		wp_send_json_success( __( 'Settings Save Successfully.', 'doc-booker' ) );
	}

	public function doctor_options_settings_save( $key, $data ) {

		$doctor_settings                               = get_option( $key, [] );
		$doctor_settings['doctor_archive_style']       = sanitize_text_field( $data['doctor_archive_style'] ) ?? 'layout-1';
		$doctor_settings['doctor_posts_per_page']      = sanitize_text_field( $data['doctor_posts_per_page'] ) ?? '9';
		$doctor_settings['doctor_grid_columns']        = sanitize_text_field( $data['doctor_grid_columns'] ) ?? '3';
		$doctor_settings['doctor_page_layout']         = sanitize_text_field( $data['doctor_page_layout'] ) ?? 'full-width';
		$doctor_settings['doctor_single_page_layout']  = sanitize_text_field( $data['doctor_single_page_layout'] ) ?? 'full-width';
		$doctor_settings['include_doctor']             = ! empty( $data['include_doctor'] ) ? array_map( 'sanitize_text_field', $data['include_doctor'] ) : [];
		$doctor_settings['exclude_doctor']             = ! empty( $data['exclude_doctor'] ) ? array_map( 'sanitize_text_field', $data['exclude_doctor'] ) : [];
		$doctor_settings['doctor_categories']          = ! empty( $data['doctor_categories'] ) ? array_map( 'sanitize_text_field', $data['doctor_categories'] ) : [];
		$doctor_settings['doctor_orderBy']             = sanitize_text_field( $data['doctor_orderBy'] ) ?? 'none';
		$doctor_settings['doctor_order']               = sanitize_text_field( $data['doctor_order'] ) ?? 'ASC';
		$doctor_settings['doctor_thumbnail_width']     = sanitize_text_field( $data['doctor_thumbnail_width'] ) ?? '570';
		$doctor_settings['doctor_thumbnail_height']    = sanitize_text_field( $data['doctor_thumbnail_height'] ) ?? '400';
		$doctor_settings['doctor_thumbnail_hard_crop'] = sanitize_text_field( $data['doctor_thumbnail_hard_crop'] ) ?? 'on';
		$doctor_settings['slider_autoplay']            = sanitize_text_field( $data['slider_autoplay'] ) ?? 'on';
		$doctor_settings['slider_loop']                = sanitize_text_field( $data['slider_loop'] ) ?? 'on';
		$doctor_settings['centered_slider']            = sanitize_text_field( $data['centered_slider'] ) ?? 'off';
		$doctor_settings['slides_per_view']            = sanitize_text_field( $data['slides_per_view'] ) ?? '3';
		$doctor_settings['display_doctor_schedule']    = sanitize_text_field( $data['display_doctor_schedule'] ) ?? 'on';
		$doctor_settings['call_for_booking']           = sanitize_text_field( $data['call_for_booking'] ) ?? 'on';
		$doctor_settings['showing_bookable_text']      = sanitize_text_field( $data['showing_bookable_text'] ) ?? 'on';
		$doctor_settings['pre_booking_window']         = max( 0, intval( $data['pre_booking_window'] ?? 0 ) );

        $doctor_settings = apply_filters( 'wpddb_pro_doctor_settings_save', $doctor_settings, $data );
		update_option( $key, $doctor_settings );
	}

	public function clinic_options_settings_save( $key, $data ) {
		$clinic_settings                              = get_option( $key, [] );
		$clinic_settings['clinic_archive_style']      = sanitize_text_field( $data['clinic_archive_style'] ) ?? 'layout-1';
		$clinic_settings['clinic_posts_per_page']     = sanitize_text_field( $data['clinic_posts_per_page'] ) ?? '9';
		$clinic_settings['clinic_grid_columns']       = sanitize_text_field( $data['clinic_grid_columns'] ) ?? '3';
		$clinic_settings['clinic_page_layout']        = sanitize_text_field( $data['clinic_page_layout'] ) ?? 'full-width';
		$clinic_settings['clinic_single_page_layout'] = sanitize_text_field( $data['clinic_single_page_layout'] ) ?? 'full-width';
		$clinic_settings['include_clinic']            = ! empty( $data['include_clinic'] ) ? array_map( 'sanitize_text_field', $data['include_clinic'] ) : [];
		$clinic_settings['exclude_clinic']            = ! empty( $data['exclude_clinic'] ) ? array_map( 'sanitize_text_field', $data['exclude_clinic'] ) : [];

		$clinic_settings['clinic_orderBy']             = sanitize_text_field( $data['clinic_orderBy'] ) ?? 'none';
		$clinic_settings['clinic_order']               = sanitize_text_field( $data['clinic_order'] ) ?? 'ASC';
		$clinic_settings['clinic_thumbnail_width']     = sanitize_text_field( $data['clinic_thumbnail_width'] ) ?? '570';
		$clinic_settings['clinic_thumbnail_height']    = sanitize_text_field( $data['clinic_thumbnail_height'] ) ?? '400';
		$clinic_settings['clinic_thumbnail_hard_crop'] = sanitize_text_field( $data['clinic_thumbnail_hard_crop'] ) ?? 'on';

        $clinic_settings['slider_autoplay']            = sanitize_text_field( $data['slider_autoplay'] ) ?? 'on';
        $clinic_settings['slider_loop']                = sanitize_text_field( $data['slider_loop'] ) ?? 'on';
        $clinic_settings['centered_slider']            = sanitize_text_field( $data['centered_slider'] ) ?? 'off';
        $clinic_settings['slides_per_view']            = sanitize_text_field( $data['slides_per_view'] ) ?? '1';

        $clinic_settings = apply_filters( 'wpddb_pro_clinic_settings_save', $clinic_settings, $data );

		update_option( $key, $clinic_settings );
	}

	public function permalink_options_settings_save( $key, $data ) {
		$permalinks_settings                         = get_option( $key, [] );
		$permalinks_settings['doctor_base']          = sanitize_text_field( $data['doctor_base'] ) ?? '';
		$permalinks_settings['doctor_category_base'] = sanitize_text_field( $data['doctor_category_base'] ) ?? '';
		$permalinks_settings['clinic_base']          = sanitize_text_field( $data['clinic_base'] ) ?? '';


		update_option( $key, $permalinks_settings );
	}

	public function page_options_settings_save( $key, $data ) {
		$page_settings            = get_option( $key, [] );
		$page_settings['doctors'] = sanitize_text_field( $data['doctors'] ) ?? '';
		$page_settings['clinics'] = sanitize_text_field( $data['clinics'] ) ?? '';
		// Pro: clinic_dashboard page id (only meaningful when Multi-Clinic Manage is enabled)
		if ( isset( $data['clinic_dashboard'] ) ) {
			$page_settings['clinic_dashboard'] = absint( $data['clinic_dashboard'] );
		}
		// Pro: receptionist_dashboard page id (only meaningful when Receptionist Management is enabled)
		if ( isset( $data['receptionist_dashboard'] ) ) {
			$page_settings['receptionist_dashboard'] = absint( $data['receptionist_dashboard'] );
		}
		// Pro: patient_report_portal page id (public portal where patients view their lab reports)
		if ( isset( $data['patient_report_portal'] ) ) {
			$page_settings['patient_report_portal'] = absint( $data['patient_report_portal'] );
		}
		update_option( $key, $page_settings );
	}

	public function style_options_settings_save( $key, $data ) {
		$style_settings                                   = get_option( $key, [] );
		$style_settings['wpddb_primary_color']            = sanitize_text_field( $data['wpddb_primary_color'] ) ?? '#005dd0';
		$style_settings['wpddb_secondary_color']          = sanitize_text_field( $data['wpddb_secondary_color'] ) ?? '#0a4b78';
		$style_settings['wpddb_border_color']             = sanitize_text_field( $data['wpddb_border_color'] ) ?? '#dedede';
		$style_settings['wpddb_doctor_title_color']       = sanitize_text_field( $data['wpddb_doctor_title_color'] ) ?? '';
		$style_settings['wpddb_doctor_content_color']     = sanitize_text_field( $data['wpddb_doctor_content_color'] ) ?? '';
		$style_settings['wpddb_doctor_designation_color'] = sanitize_text_field( $data['wpddb_doctor_designation_color'] ) ?? '';
		$style_settings['wpddb_doctor_speciality_color']  = sanitize_text_field( $data['wpddb_doctor_speciality_color'] ) ?? '';
		$style_settings['wpddb_doctor_workplace_color']   = sanitize_text_field( $data['wpddb_doctor_workplace_color'] ) ?? '';
		$style_settings['wpddb_doctor_degree_color']      = sanitize_text_field( $data['wpddb_doctor_degree_color'] ) ?? '';
		$style_settings['wpddb_clinic_title_color']       = sanitize_text_field( $data['wpddb_clinic_title_color'] ) ?? '';
		$style_settings['wpddb_clinic_content_color']     = sanitize_text_field( $data['wpddb_clinic_content_color'] ) ?? '';
		$style_settings['wpddb_clinic_bg_color']          = sanitize_text_field( $data['wpddb_clinic_bg_color'] ) ?? '';
		$style_settings['wpddb_doctor_bg_color']          = sanitize_text_field( $data['wpddb_doctor_bg_color'] ) ?? '';

        $style_settings = apply_filters( 'wpddb_pro_style_settings_save', $style_settings, $data );
		update_option( $key, $style_settings );
	}
    public function cancel_doctor_booking(  ) {

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], wpddb()->nonceText ) || ! current_user_can('wpddb_manage_booking_system') ) {
            wp_send_json_error( 'Nonce verification failed.' );
        }
        $id = isset( $_POST['booking_info']['id'] ) ? intval( $_POST['booking_info']['id'] ) : 0;
        if ( $id <= 0 ) {
            wp_send_json_error( 'Invalid Payment ID.' );
        }
        $doctor_id    = intval( $_POST['booking_info']['doctor_id'] );
        $clinic_id    = intval( $_POST['booking_info']['clinic_id'] );
        $booking_id   = sanitize_text_field( $_POST['booking_info']['booking_id'] );
        $booking_day  = sanitize_text_field( $_POST['booking_info']['day'] );
        $booking_time = sanitize_text_field( $_POST['booking_info']['time'] );
		$booking_time_24 = Helper::convert_time_format($booking_time);
        $booking_date = isset( $_POST['booking_info']['booking_date'] ) ? sanitize_text_field( $_POST['booking_info']['booking_date'] ) : null;
        $patient_details = [
            'name'  => sanitize_text_field( $_POST['booking_info']['full_name'] ),
            'email' => sanitize_email( $_POST['booking_info']['email'] ),
            'phone' => sanitize_text_field( $_POST['booking_info']['phone'] ),
        ];

        global $wpdb;
        $booking_table_name = $wpdb->prefix . 'wpddb_bookings';
        $cancel_booking_sql = $wpdb->prepare(
            "UPDATE $booking_table_name SET status = %s WHERE id = %d AND status = %s",
            'cancel',
            $id,
            'approved'
        );

        $cancel_result = $wpdb->query( $cancel_booking_sql );
        if ( $cancel_result !== false ) {
            // For date-based bookings, no need to toggle is_bookable — availability is query-based
            // For legacy bookings (no booking_date), reset the is_bookable flag in post meta
            if ( empty( $booking_date ) ) {
                Helper::update_doctor_booking_meta_data_time( $doctor_id, $booking_day, $booking_time_24, $clinic_id );
            }
            Helper::send_booking_notifications( $booking_id, $doctor_id, $booking_day, $booking_time, $clinic_id, $patient_details, 'cancel', $booking_date );
            wp_send_json_success( __( 'Booking cancel successfully.', 'doc-booker' ) );
        } else {
            wp_send_json_error( __( 'Failed to booking cancel.', 'doc-booker' ) );
        }
    }

	public function delete_doctor_booking(  ) {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], wpddb()->nonceText ) || ! current_user_can('wpddb_manage_booking_system') ) {
			wp_send_json_error( 'Nonce verification failed.' );
		}
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( 'Invalid Payment ID.' );
		}
		global $wpdb;
		$table_name    = $wpdb->prefix . 'wpddb_bookings';
		$delete_sql    = $wpdb->prepare( "DELETE FROM $table_name WHERE id = %d", $id );
		$delete_result = $wpdb->query( $delete_sql );
		if ( $delete_result !== false ) {
			wp_send_json_success( __( 'Booking delete successfully.', 'doc-booker' ) );
		} else {
			wp_send_json_error( __( 'Failed to delete booking.', 'doc-booker' ) );
		}
	}
    public function get_clinics_by_category() {

        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], wpddb()->nonceText )  ) {
            wp_send_json_error( 'Nonce verification failed.' );
        }

        $category_id = absint($_POST['category_id']);
        if (!$category_id) {
            wp_send_json_error('Invalid category.');
        }
        $args = [
            'post_type'      => wpddb()->post_type_doctor,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => wpddb()->doctor_category,
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                ]
            ],
        ];
        $doctors = new WP_Query($args);
        $clinic_ids = [];

        if ( $doctors->have_posts() ) {
            while ( $doctors->have_posts() ) {
                $doctors->the_post();
                $clinics = get_post_meta(get_the_ID(), 'wpddb_doctor_available_clinic', true);
                if (is_array($clinics)) {
                    foreach ($clinics as $cid) {
                        if (!in_array($cid, $clinic_ids)) {
                            $clinic_ids[] = $cid;
                        }
                    }
                }
            }
            wp_reset_postdata();
        }


        $clinics = [];
        foreach ($clinic_ids as $id) {
            $clinics[] = ['id' => $id, 'name' => get_the_title($id)];
        }

        wp_send_json_success($clinics);
    }
    function get_doctors_by_clinic() {
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['_ajax_nonce'], wpddb()->nonceText )  ) {
            wp_send_json_error( 'Nonce verification failed.' );
        }

        $clinic_id = absint($_POST['clinic_id']);
        $category_id = absint( $_POST['category_id'] );
        if (!$clinic_id || !$category_id) {
            wp_send_json_error('Invalid clinic or doctor category.');
        }

        $query = new WP_Query([
            'post_type'      => wpddb()->post_type_doctor,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'wpddb_doctor_available_clinic',
                    'value'   => '"' . $clinic_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'tax_query'      => [
                [
                    'taxonomy' => wpddb()->doctor_category,
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                ],
            ],
        ]);

        $doctor_list = [];
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $doctor ) {
                $doctor_degree = get_post_meta($doctor->ID,'wpddb_doctor_degree',true);
                $doctor_name   = get_the_title( $doctor->ID );
                if ( ! empty( $doctor_degree ) ) {
                    $doctor_name .= ' - ( ' . $doctor_degree . ' )';
                }
                $doctor_list[] = [
                    'id'   => $doctor->ID,
                    'name' => $doctor_name,
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success($doctor_list);
    }
}