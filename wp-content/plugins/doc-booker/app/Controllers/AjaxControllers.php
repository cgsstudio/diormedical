<?php

namespace WpDreamers\WPDDB\Controllers;

use WP_Query;
use WpDreamers\WPDDB\Controllers\Admin\RegisterPostType;
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Booking;
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

		// Save only the groups the request carries: a group missing from the
		// request used to be saved as empty, blanking those settings.
		$groups = [
			'wpddb_page_settings'       => 'page_options_settings_save',
			'wpddb_permalinks_settings' => 'permalink_options_settings_save',
			'wpddb_doctor_settings'     => 'doctor_options_settings_save',
			'wpddb_clinic_settings'     => 'clinic_options_settings_save',
			'wpddb_style_settings'      => 'style_options_settings_save',
			'wpddb_global_settings'     => 'currency_settings_save',
		];
		foreach ( $groups as $key => $method ) {
			if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
				$this->$method( $key, wp_unslash( $_POST[ $key ] ) );
			}
		}

        do_action( 'wpddb_save_settings',$_POST );
		wp_send_json_success( __( 'Settings saved.', 'doc-booker' ) );
	}

	/**
	 * Currency and its position, which the free plugin uses for the doctor's
	 * fee. Only these two keys: the rest of this option belongs to Pro, which
	 * saves it in full after this.
	 */
	public function currency_settings_save( $key, $data ) {
		$settings = get_option( $key, [] );
		$settings = is_array( $settings ) ? $settings : [];
		$currency = strtoupper( sanitize_text_field( $data['currency'] ?? '' ) );
		if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$settings['currency'] = $currency;
		}
		$position                      = sanitize_key( $data['currency_position'] ?? 'left' );
		$settings['currency_position'] = in_array( $position, [ 'left', 'right', 'left_space', 'right_space' ], true ) ? $position : 'left';
		update_option( $key, $settings );
	}

	public function doctor_options_settings_save( $key, $data ) {

		$doctor_settings                               = get_option( $key, [] );
		$doctor_settings['doctor_archive_style']       = sanitize_text_field( $data['doctor_archive_style'] ?? 'layout-1' );
		$doctor_settings['doctor_posts_per_page']      = sanitize_text_field( $data['doctor_posts_per_page'] ?? '9' );
		$doctor_settings['doctor_grid_columns']        = sanitize_text_field( $data['doctor_grid_columns'] ?? '3' );
		$doctor_settings['doctor_page_layout']         = sanitize_text_field( $data['doctor_page_layout'] ?? 'full-width' );
		$doctor_settings['doctor_single_page_layout']  = sanitize_text_field( $data['doctor_single_page_layout'] ?? 'full-width' );
		$doctor_settings['include_doctor']             = ! empty( $data['include_doctor'] ) ? array_map( 'sanitize_text_field', $data['include_doctor'] ) : [];
		$doctor_settings['exclude_doctor']             = ! empty( $data['exclude_doctor'] ) ? array_map( 'sanitize_text_field', $data['exclude_doctor'] ) : [];
		$doctor_settings['doctor_categories']          = ! empty( $data['doctor_categories'] ) ? array_map( 'sanitize_text_field', $data['doctor_categories'] ) : [];
		$doctor_settings['doctor_orderBy']             = sanitize_text_field( $data['doctor_orderBy'] ?? 'none' );
		$doctor_settings['doctor_order']               = sanitize_text_field( $data['doctor_order'] ?? 'ASC' );
		$doctor_settings['doctor_thumbnail_width']     = sanitize_text_field( $data['doctor_thumbnail_width'] ?? '570' );
		$doctor_settings['doctor_thumbnail_height']    = sanitize_text_field( $data['doctor_thumbnail_height'] ?? '400' );
		$doctor_settings['doctor_thumbnail_hard_crop'] = sanitize_text_field( $data['doctor_thumbnail_hard_crop'] ?? 'on' );
		$doctor_settings['slider_autoplay']            = sanitize_text_field( $data['slider_autoplay'] ?? 'on' );
		$doctor_settings['slider_loop']                = sanitize_text_field( $data['slider_loop'] ?? 'on' );
		$doctor_settings['centered_slider']            = sanitize_text_field( $data['centered_slider'] ?? 'off' );
		$doctor_settings['slides_per_view']            = sanitize_text_field( $data['slides_per_view'] ?? '3' );
		$doctor_settings['display_doctor_schedule']    = sanitize_text_field( $data['display_doctor_schedule'] ?? 'on' );
		$doctor_settings['call_for_booking']           = sanitize_text_field( $data['call_for_booking'] ?? 'on' );
		$doctor_settings['showing_bookable_text']      = sanitize_text_field( $data['showing_bookable_text'] ?? 'on' );
		$doctor_settings['pre_booking_window']         = max( 0, intval( $data['pre_booking_window'] ?? 0 ) );
		$doctor_settings['min_notice_hours']           = (string) min( 720, max( 0, intval( $data['min_notice_hours'] ?? 0 ) ) );
		$doctor_settings['reminder_email']             = 'off' === ( $data['reminder_email'] ?? 'on' ) ? 'off' : 'on';
		$doctor_settings['doctor_reminder_email']      = 'off' === ( $data['doctor_reminder_email'] ?? 'on' ) ? 'off' : 'on';
		$doctor_settings['patient_cancel']             = 'off' === ( $data['patient_cancel'] ?? 'on' ) ? 'off' : 'on';
		$doctor_settings['cancel_cutoff_hours']        = (string) min( 720, max( 0, intval( $data['cancel_cutoff_hours'] ?? 24 ) ) );

        $doctor_settings = apply_filters( 'wpddb_pro_doctor_settings_save', $doctor_settings, $data );
		update_option( $key, $doctor_settings );
	}

	public function clinic_options_settings_save( $key, $data ) {
		$clinic_settings                              = get_option( $key, [] );
		$clinic_settings['clinic_archive_style']      = sanitize_text_field( $data['clinic_archive_style'] ?? 'layout-1' );
		$clinic_settings['clinic_posts_per_page']     = sanitize_text_field( $data['clinic_posts_per_page'] ?? '9' );
		$clinic_settings['clinic_grid_columns']       = sanitize_text_field( $data['clinic_grid_columns'] ?? '3' );
		$clinic_settings['clinic_page_layout']        = sanitize_text_field( $data['clinic_page_layout'] ?? 'full-width' );
		$clinic_settings['clinic_single_page_layout'] = sanitize_text_field( $data['clinic_single_page_layout'] ?? 'full-width' );
		$clinic_settings['include_clinic']            = ! empty( $data['include_clinic'] ) ? array_map( 'sanitize_text_field', $data['include_clinic'] ) : [];
		$clinic_settings['exclude_clinic']            = ! empty( $data['exclude_clinic'] ) ? array_map( 'sanitize_text_field', $data['exclude_clinic'] ) : [];

		$clinic_settings['clinic_orderBy']             = sanitize_text_field( $data['clinic_orderBy'] ?? 'none' );
		$clinic_settings['clinic_order']               = sanitize_text_field( $data['clinic_order'] ?? 'ASC' );
		$clinic_settings['clinic_thumbnail_width']     = sanitize_text_field( $data['clinic_thumbnail_width'] ?? '570' );
		$clinic_settings['clinic_thumbnail_height']    = sanitize_text_field( $data['clinic_thumbnail_height'] ?? '400' );
		$clinic_settings['clinic_thumbnail_hard_crop'] = sanitize_text_field( $data['clinic_thumbnail_hard_crop'] ?? 'on' );

        $clinic_settings['slider_autoplay']            = sanitize_text_field( $data['slider_autoplay'] ?? 'on' );
        $clinic_settings['slider_loop']                = sanitize_text_field( $data['slider_loop'] ?? 'on' );
        $clinic_settings['centered_slider']            = sanitize_text_field( $data['centered_slider'] ?? 'off' );
        $clinic_settings['slides_per_view']            = sanitize_text_field( $data['slides_per_view'] ?? '1' );

        $clinic_settings = apply_filters( 'wpddb_pro_clinic_settings_save', $clinic_settings, $data );

		update_option( $key, $clinic_settings );
	}

	public function permalink_options_settings_save( $key, $data ) {
		$permalinks_settings                         = get_option( $key, [] );
		$permalinks_settings['doctor_base']          = sanitize_text_field( $data['doctor_base'] ?? '' );
		$permalinks_settings['doctor_category_base'] = sanitize_text_field( $data['doctor_category_base'] ?? '' );
		$permalinks_settings['clinic_base']          = sanitize_text_field( $data['clinic_base'] ?? '' );

		// New bases only take effect once the rewrite rules are rebuilt.
		if ( $permalinks_settings !== get_option( $key, [] ) ) {
			RegisterPostType::flush_rewrite_rules();
		}
		update_option( $key, $permalinks_settings );
	}

	public function page_options_settings_save( $key, $data ) {
		$page_settings            = get_option( $key, [] );
		$page_settings['doctors'] = sanitize_text_field( $data['doctors'] ?? '' );
		$page_settings['clinics'] = sanitize_text_field( $data['clinics'] ?? '' );
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

		// The doctor and clinic archives live at their page's address, so a
		// different page means different rewrite rules.
		$old_pages = get_option( $key, [] );
		foreach ( [ 'doctors', 'clinics' ] as $page ) {
			if ( (string) ( $old_pages[ $page ] ?? '' ) !== (string) $page_settings[ $page ] ) {
				RegisterPostType::flush_rewrite_rules();
				break;
			}
		}
		update_option( $key, $page_settings );
	}

	public function style_options_settings_save( $key, $data ) {
		$style_settings                                   = get_option( $key, [] );
		// Empty is "None": Helper::get_*_color() then gives the built-in colour.
		$style_settings['wpddb_primary_color']            = self::sanitize_color( $data['wpddb_primary_color'] ?? '', '' );
		$style_settings['wpddb_secondary_color']          = self::sanitize_color( $data['wpddb_secondary_color'] ?? '', '' );
		$style_settings['wpddb_border_color']             = self::sanitize_color( $data['wpddb_border_color'] ?? '', '' );
		$style_settings['wpddb_doctor_title_color']       = self::sanitize_color( $data['wpddb_doctor_title_color'] ?? '', '' );
		$style_settings['wpddb_doctor_content_color']     = self::sanitize_color( $data['wpddb_doctor_content_color'] ?? '', '' );
		$style_settings['wpddb_doctor_designation_color'] = self::sanitize_color( $data['wpddb_doctor_designation_color'] ?? '', '' );
		$style_settings['wpddb_doctor_speciality_color']  = self::sanitize_color( $data['wpddb_doctor_speciality_color'] ?? '', '' );
		$style_settings['wpddb_doctor_workplace_color']   = self::sanitize_color( $data['wpddb_doctor_workplace_color'] ?? '', '' );
		$style_settings['wpddb_doctor_degree_color']      = self::sanitize_color( $data['wpddb_doctor_degree_color'] ?? '', '' );
		$style_settings['wpddb_clinic_title_color']       = self::sanitize_color( $data['wpddb_clinic_title_color'] ?? '', '' );
		$style_settings['wpddb_clinic_content_color']     = self::sanitize_color( $data['wpddb_clinic_content_color'] ?? '', '' );
		$style_settings['wpddb_clinic_bg_color']          = self::sanitize_color( $data['wpddb_clinic_bg_color'] ?? '', '' );
		$style_settings['wpddb_doctor_bg_color']          = self::sanitize_color( $data['wpddb_doctor_bg_color'] ?? '', '' );

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
        if ( ! Booking::get( $id ) ) {
            wp_send_json_error( __( 'Booking not found.', 'doc-booker' ) );
        }
        // Everything about the booking comes from the database, not the request:
        // the browser only says which one.
        if ( ! Booking::cancel( $id, 'admin' ) ) {
            wp_send_json_error( __( 'This booking is already cancelled.', 'doc-booker' ) );
        }
        wp_send_json_success( __( 'Booking cancelled.', 'doc-booker' ) );
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

	/**
	 * A colour from the style settings: hex (#rgb, #rrggbb, #rrggbbaa) or
	 * rgb()/rgba(). Anything else falls back to $default; these values are
	 * printed into inline CSS, where free text could break out of the rule.
	 */
	private static function sanitize_color( $value, $default = '' ) {
		$value = trim( (string) wp_unslash( $value ) );
		if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value )
			|| preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
			return $value;
		}
		return $default;
	}
}
