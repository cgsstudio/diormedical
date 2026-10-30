<?php
/**
 * Multi-Clinic Manager — Pro REST endpoints.
 *
 * Two endpoint families:
 *
 *   /wpddb_pro/v1/clinic-managers/...       Admin-only (manage_options)
 *     - List, create (existing or inline-new user), update, delete assignments
 *     - Helper: list clinics for the form dropdown, list users for the picker
 *
 *   /wpddb_pro/v1/dashboard/...             Clinic-manager-only (wpddb_manage_clinic)
 *     - Auto-scoped to the caller's wpddb_managed_clinic_id user_meta
 *     - clinic info read/write, doctors CRUD, departments, bookings
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User_Query;
use WpDreamers\WPDDB\Controllers\Helper\Helper as FreeHelper;
use WpDreamers\WPDDB\Controllers\Admin\Api\RestApi as FreeRestApi;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class RestApi {
    use SingletonTrait;

    public const NAMESPACE = 'wpddb_pro/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // ===== Assignment (admin) =====
        register_rest_route( self::NAMESPACE, '/clinic-managers', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_managers' ],
                'permission_callback' => [ $this, 'verify_admin' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_manager' ],
                'permission_callback' => [ $this, 'verify_admin' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/clinic-managers/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'update_manager' ],
                'permission_callback' => [ $this, 'verify_admin' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_manager' ],
                'permission_callback' => [ $this, 'verify_admin' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/clinic-managers/clinics', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_clinics_for_picker' ],
            'permission_callback' => [ $this, 'verify_admin' ],
        ] );

        register_rest_route( self::NAMESPACE, '/clinic-managers/users', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_users_for_picker' ],
            'permission_callback' => [ $this, 'verify_admin' ],
        ] );

        // ===== Dashboard (clinic_manager) =====
        register_rest_route( self::NAMESPACE, '/dashboard/clinic', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_my_clinic' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'update_my_clinic' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/doctors', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_my_doctors' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_my_doctor' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/doctors/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_my_doctor' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'update_my_doctor' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/doctors/(?P<id>\d+)/thumbnail', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'upload_my_doctor_thumbnail' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_my_doctor_thumbnail' ],
                'permission_callback' => [ $this, 'verify_clinic_manager' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/departments', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_my_departments' ],
            'permission_callback' => [ $this, 'verify_clinic_manager' ],
        ] );

        // All departments (for the doctor-add / edit picker, not scoped to clinic).
        register_rest_route( self::NAMESPACE, '/dashboard/all-departments', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_all_departments' ],
            'permission_callback' => [ $this, 'verify_clinic_manager' ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/bookings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_my_bookings' ],
            'permission_callback' => [ $this, 'verify_clinic_manager' ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/bookings/(?P<id>\d+)/cancel', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'cancel_my_booking' ],
            'permission_callback' => [ $this, 'verify_clinic_manager' ],
        ] );
    }

    // ============================================================
    // Permission callbacks
    // ============================================================

    public function verify_admin( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        return wp_verify_nonce( $nonce, 'wp_rest' ) && current_user_can( 'manage_options' );
    }

    public function verify_clinic_manager( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }
        // Cap is enough — handlers below return a structured "unassigned"
        // response when the manager has no clinic linked yet, so the dashboard
        // can render an empty state instead of a 403.
        return current_user_can( UserRole::CAPABILITY );
    }

    // ============================================================
    // Assignment endpoints
    // ============================================================

    public function list_managers( WP_REST_Request $request ) {
        $query = new WP_User_Query( [
            'meta_key'     => UserRole::META_KEY,
            'meta_compare' => 'EXISTS',
            'orderby'      => 'user_login',
            'order'        => 'ASC',
            'number'       => 200,
        ] );

        $rows = [];
        foreach ( $query->get_results() as $user ) {
            $clinic_id = (int) get_user_meta( $user->ID, UserRole::META_KEY, true );
            if ( ! $clinic_id ) {
                continue;
            }
            $rows[] = $this->serialize_manager( $user, $clinic_id );
        }

        return new WP_REST_Response( $rows, 200 );
    }

    public function create_manager( WP_REST_Request $request ) {
        $clinic_id = (int) $request->get_param( 'clinic_id' );
        if ( $clinic_id <= 0 || get_post_type( $clinic_id ) !== wpddb()->post_type_clinic ) {
            return new WP_REST_Response( [ 'error' => __( 'Pick a valid clinic.', 'doc-booker-pro' ) ], 400 );
        }

        $mode = $request->get_param( 'mode' ); // 'new' | 'existing'

        if ( $mode === 'new' ) {
            $username = sanitize_user( $request->get_param( 'username' ), true );
            $email    = sanitize_email( $request->get_param( 'email' ) );
            $password = (string) $request->get_param( 'password' );

            if ( ! $username || ! is_email( $email ) || strlen( $password ) < 6 ) {
                return new WP_REST_Response( [ 'error' => __( 'Username, email, and a password (6+ chars) are required.', 'doc-booker-pro' ) ], 400 );
            }
            if ( username_exists( $username ) ) {
                return new WP_REST_Response( [ 'error' => __( 'That username is already taken.', 'doc-booker-pro' ) ], 400 );
            }
            if ( email_exists( $email ) ) {
                return new WP_REST_Response( [ 'error' => __( 'That email is already registered.', 'doc-booker-pro' ) ], 400 );
            }

            $user_id = wp_insert_user( [
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => $password,
                'role'       => UserRole::ROLE_SLUG,
            ] );
            if ( is_wp_error( $user_id ) ) {
                return new WP_REST_Response( [ 'error' => $user_id->get_error_message() ], 400 );
            }
            $user = get_user_by( 'id', $user_id );
        } else {
            $user_id = (int) $request->get_param( 'user_id' );
            $user    = $user_id ? get_user_by( 'id', $user_id ) : false;
            if ( ! $user ) {
                return new WP_REST_Response( [ 'error' => __( 'User not found.', 'doc-booker-pro' ) ], 404 );
            }
            // Add (not replace) — preserves existing roles.
            if ( ! in_array( UserRole::ROLE_SLUG, (array) $user->roles, true ) ) {
                $user->add_role( UserRole::ROLE_SLUG );
            }
        }

        update_user_meta( $user->ID, UserRole::META_KEY, $clinic_id );

        $payload = $this->serialize_manager( $user, $clinic_id );
        $payload['warning'] = $this->detect_role_warning( $user );

        return new WP_REST_Response( $payload, 201 );
    }

    public function update_manager( WP_REST_Request $request ) {
        $user_id   = (int) $request['id'];
        $clinic_id = (int) $request->get_param( 'clinic_id' );
        $user      = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_REST_Response( [ 'error' => __( 'User not found.', 'doc-booker-pro' ) ], 404 );
        }
        if ( $clinic_id <= 0 || get_post_type( $clinic_id ) !== wpddb()->post_type_clinic ) {
            return new WP_REST_Response( [ 'error' => __( 'Pick a valid clinic.', 'doc-booker-pro' ) ], 400 );
        }
        update_user_meta( $user->ID, UserRole::META_KEY, $clinic_id );
        return new WP_REST_Response( $this->serialize_manager( $user, $clinic_id ), 200 );
    }

    public function delete_manager( WP_REST_Request $request ) {
        $user_id = (int) $request['id'];
        $user    = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_REST_Response( [ 'error' => __( 'User not found.', 'doc-booker-pro' ) ], 404 );
        }
        if ( in_array( UserRole::ROLE_SLUG, (array) $user->roles, true ) ) {
            $user->remove_role( UserRole::ROLE_SLUG );
        }
        delete_user_meta( $user->ID, UserRole::META_KEY );
        return new WP_REST_Response( [ 'removed' => $user_id ], 200 );
    }

    public function list_clinics_for_picker( WP_REST_Request $request ) {
        $posts = get_posts( [
            'post_type'      => wpddb()->post_type_clinic,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $list = array_map( fn( $p ) => [
            'value' => (int) $p->ID,
            'label' => $p->post_title ?: '#' . $p->ID,
        ], $posts );
        return new WP_REST_Response( $list, 200 );
    }

    public function list_users_for_picker( WP_REST_Request $request ) {
        $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $args   = [
            'orderby' => 'user_login',
            'order'   => 'ASC',
            'number'  => 50,
        ];
        if ( $search !== '' ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }
        $users = ( new WP_User_Query( $args ) )->get_results();
        $list  = array_map( fn( $u ) => [
            'value' => (int) $u->ID,
            'label' => sprintf( '%s — %s', $u->user_login, $u->user_email ),
            'roles' => array_values( $u->roles ),
        ], $users );
        return new WP_REST_Response( $list, 200 );
    }

    // ============================================================
    // Dashboard endpoints (auto-scoped)
    // ============================================================

    public function get_my_clinic( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        if ( ! $clinic_id ) {
            return new WP_REST_Response( [ 'unassigned' => true ], 200 );
        }
        $post = get_post( $clinic_id );
        if ( ! $post || $post->post_type !== wpddb()->post_type_clinic ) {
            return new WP_REST_Response( [ 'error' => 'Clinic not found' ], 404 );
        }
        return new WP_REST_Response( $this->serialize_clinic( $post ), 200 );
    }

    public function update_my_clinic( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        if ( ! $clinic_id ) {
            return new WP_REST_Response( [ 'error' => 'No clinic assigned' ], 403 );
        }
        // Defense in depth: confirm the body's id (if present) matches scope.
        $body_id = (int) $request->get_param( 'id' );
        if ( $body_id && $body_id !== $clinic_id ) {
            return new WP_REST_Response( [ 'error' => 'Cannot edit another clinic' ], 403 );
        }

        $allowed = [
            'wpddb_clinic_email'   => 'sanitize_email',
            'wpddb_clinic_hotline' => 'sanitize_text_field',
            'wpddb_clinic_address' => 'sanitize_textarea_field',
            'wpddb_clinic_latitude'  => 'sanitize_text_field',
            'wpddb_clinic_longitude' => 'sanitize_text_field',
        ];
        foreach ( $allowed as $key => $sanitizer ) {
            if ( $request->get_param( $key ) !== null ) {
                update_post_meta( $clinic_id, $key, call_user_func( $sanitizer, $request->get_param( $key ) ) );
            }
        }

        // Optional: title + content
        $title   = $request->get_param( 'post_title' );
        $content = $request->get_param( 'post_content' );
        if ( $title !== null || $content !== null ) {
            wp_update_post( [
                'ID'           => $clinic_id,
                'post_title'   => $title !== null ? sanitize_text_field( $title ) : get_the_title( $clinic_id ),
                'post_content' => $content !== null ? wp_kses_post( $content ) : get_post_field( 'post_content', $clinic_id ),
            ] );
        }

        return new WP_REST_Response( $this->serialize_clinic( get_post( $clinic_id ) ), 200 );
    }

    public function list_my_doctors( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        if ( ! $clinic_id ) {
            return new WP_REST_Response( [], 200 );
        }

        $all_ids = get_posts( [
            'post_type'      => wpddb()->post_type_doctor,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $doctor_ids = $all_ids ? FreeRestApi::filter_doctor_ids_by_clinic( $all_ids, $clinic_id ) : [];

        $rows = [];
        foreach ( $doctor_ids as $id ) {
            $rows[] = $this->serialize_doctor_summary( (int) $id );
        }
        return new WP_REST_Response( $rows, 200 );
    }

    public function get_my_doctor( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        $doctor_id = (int) $request['id'];
        if ( ! $this->doctor_in_clinic( $doctor_id, $clinic_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Doctor not in your clinic' ], 403 );
        }
        return new WP_REST_Response( $this->serialize_doctor_full( $doctor_id, $clinic_id ), 200 );
    }

    public function update_my_doctor( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        $doctor_id = (int) $request['id'];
        if ( ! $this->doctor_in_clinic( $doctor_id, $clinic_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Doctor not in your clinic' ], 403 );
        }

        $title = $request->get_param( 'post_title' );
        if ( $title !== null ) {
            wp_update_post( [
                'ID'         => $doctor_id,
                'post_title' => sanitize_text_field( $title ),
            ] );
        }

        $allowed = [
            'wpddb_doctor_designation' => 'sanitize_text_field',
            'wpddb_doctor_speciality'  => 'sanitize_text_field',
            'wpddb_doctor_degree'      => 'sanitize_text_field',
            'wpddb_doctor_workplace'   => 'sanitize_text_field',
            'wpddb_doctor_email'       => 'sanitize_email',
        ];
        foreach ( $allowed as $key => $sanitizer ) {
            if ( $request->get_param( $key ) !== null ) {
                update_post_meta( $doctor_id, $key, call_user_func( $sanitizer, $request->get_param( $key ) ) );
            }
        }

        // Department (single term)
        $dept_id = $request->get_param( 'department_id' );
        if ( $dept_id !== null && (int) $dept_id > 0 ) {
            wp_set_object_terms( $doctor_id, [ (int) $dept_id ], wpddb()->doctor_category );
        }

        // Schedule update — sanitize and scope to manager's clinic only, then merge with
        // the existing schedule so other clinics' entries on this doctor are preserved.
        $schedule = $request->get_param( 'schedule' );
        if ( is_array( $schedule ) ) {
            $schedule = $this->sanitize_schedule( $schedule );
            $schedule = $this->ensure_clinic_in_schedule( $schedule, $clinic_id );
            $schedule = $this->merge_schedule_preserving_other_clinics( $doctor_id, $schedule, $clinic_id );
            update_post_meta( $doctor_id, 'wpddb_doctor_schedule', $schedule );
            $this->sync_available_clinics( $doctor_id );
        }

        // Clinic info (phone + holiday) — scoped to THIS clinic; other clinics untouched.
        $clinic_info = $request->get_param( 'clinic_info' );
        if ( is_array( $clinic_info ) ) {
            $this->merge_clinic_info( $doctor_id, $clinic_info, $clinic_id );
        }

        return new WP_REST_Response( $this->serialize_doctor_full( $doctor_id, $clinic_id ), 200 );
    }

    public function create_my_doctor( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        if ( ! $clinic_id ) {
            return new WP_REST_Response( [ 'error' => 'No clinic assigned' ], 403 );
        }
        $title = sanitize_text_field( (string) $request->get_param( 'post_title' ) );
        if ( $title === '' ) {
            return new WP_REST_Response( [ 'error' => 'Doctor name required' ], 400 );
        }
        $doctor_id = wp_insert_post( [
            'post_type'   => wpddb()->post_type_doctor,
            'post_status' => 'publish',
            'post_title'  => $title,
        ], true );
        if ( is_wp_error( $doctor_id ) ) {
            return new WP_REST_Response( [ 'error' => $doctor_id->get_error_message() ], 400 );
        }

        // Auto-build schedule with the manager's clinic
        $schedule = $request->get_param( 'schedule' );
        if ( ! is_array( $schedule ) || empty( $schedule ) ) {
            $schedule = $this->build_default_schedule( $clinic_id );
        } else {
            $schedule = $this->sanitize_schedule( $schedule );
            $schedule = $this->ensure_clinic_in_schedule( $schedule, $clinic_id );
        }
        update_post_meta( $doctor_id, 'wpddb_doctor_schedule', $schedule );
        $this->sync_available_clinics( $doctor_id );

        // Department
        $dept_id = (int) $request->get_param( 'department_id' );
        if ( $dept_id > 0 ) {
            wp_set_object_terms( $doctor_id, [ $dept_id ], wpddb()->doctor_category );
        }

        // Optional meta
        foreach ( [ 'wpddb_doctor_designation', 'wpddb_doctor_speciality', 'wpddb_doctor_email' ] as $k ) {
            if ( $request->get_param( $k ) !== null ) {
                update_post_meta( $doctor_id, $k, sanitize_text_field( $request->get_param( $k ) ) );
            }
        }

        // Optional clinic info on create
        $clinic_info = $request->get_param( 'clinic_info' );
        if ( is_array( $clinic_info ) ) {
            $this->merge_clinic_info( $doctor_id, $clinic_info, $clinic_id );
        }

        return new WP_REST_Response( $this->serialize_doctor_full( $doctor_id, $clinic_id ), 201 );
    }

    /**
     * Upload a doctor profile image and set it as the doctor's featured image.
     * Server-side validates mime + size; doctor must belong to the caller's clinic.
     */
    public function upload_my_doctor_thumbnail( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        $doctor_id = (int) $request['id'];
        if ( ! $this->doctor_in_clinic( $doctor_id, $clinic_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Doctor not in your clinic' ], 403 );
        }

        $files = $request->get_file_params();
        $file  = $files['file'] ?? null;
        if ( empty( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_REST_Response( [ 'error' => 'No file provided' ], 400 );
        }
        if ( ! empty( $file['error'] ) ) {
            return new WP_REST_Response( [ 'error' => 'Upload error (' . (int) $file['error'] . ')' ], 400 );
        }

        // Size cap: 2 MB.
        if ( (int) ( $file['size'] ?? 0 ) > 2 * 1024 * 1024 ) {
            return new WP_REST_Response( [ 'error' => 'Image must be 2MB or smaller.' ], 400 );
        }

        // Mime validation based on actual file contents, not just the Content-Type header.
        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
        ];
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
        if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed_mimes, true ) ) {
            return new WP_REST_Response( [ 'error' => 'Only JPG or PNG images are allowed.' ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // test_form=false skips the nonce check inside wp_handle_upload (we already
        // validated wp_rest nonce + wpddb_manage_clinic cap via permission_callback).
        $handled = wp_handle_upload( $file, [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ] );
        if ( isset( $handled['error'] ) ) {
            return new WP_REST_Response( [ 'error' => $handled['error'] ], 400 );
        }

        $att_id = wp_insert_attachment( [
            'post_mime_type' => $handled['type'],
            'post_title'     => sanitize_text_field( pathinfo( $handled['file'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $handled['file'], $doctor_id, true );

        if ( is_wp_error( $att_id ) ) {
            @unlink( $handled['file'] );
            return new WP_REST_Response( [ 'error' => $att_id->get_error_message() ], 500 );
        }

        wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $handled['file'] ) );

        // Swap thumbnail: delete the previous attachment so we don't orphan media.
        $old_thumb = (int) get_post_thumbnail_id( $doctor_id );
        set_post_thumbnail( $doctor_id, $att_id );
        if ( $old_thumb && $old_thumb !== $att_id ) {
            wp_delete_attachment( $old_thumb, true );
        }

        return new WP_REST_Response( [
            'attachment_id'   => $att_id,
            'thumbnail'       => wp_get_attachment_image_url( $att_id, 'thumbnail' ),
            'thumbnail_large' => wp_get_attachment_image_url( $att_id, 'medium' ),
        ], 200 );
    }

    public function delete_my_doctor_thumbnail( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        $doctor_id = (int) $request['id'];
        if ( ! $this->doctor_in_clinic( $doctor_id, $clinic_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Doctor not in your clinic' ], 403 );
        }
        $thumb_id = (int) get_post_thumbnail_id( $doctor_id );
        if ( $thumb_id ) {
            delete_post_thumbnail( $doctor_id );
            wp_delete_attachment( $thumb_id, true );
        }
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * Every doctor department — used by the doctor add/edit picker. Not scoped to
     * the caller's clinic because a new doctor may belong to a department no other
     * doctor at this clinic currently uses.
     */
    public function list_all_departments( WP_REST_Request $request ) {
        $terms = get_terms( [
            'taxonomy'   => wpddb()->doctor_category,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );
        if ( is_wp_error( $terms ) ) {
            return new WP_REST_Response( [], 200 );
        }
        $list = [];
        foreach ( $terms as $t ) {
            $list[] = [
                'id'   => (int) $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
            ];
        }
        return new WP_REST_Response( $list, 200 );
    }

    public function list_my_departments( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        if ( ! $clinic_id ) {
            return new WP_REST_Response( [], 200 );
        }
        $all_ids = get_posts( [
            'post_type'      => wpddb()->post_type_doctor,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        $doctor_ids = $all_ids ? FreeRestApi::filter_doctor_ids_by_clinic( $all_ids, $clinic_id ) : [];
        if ( ! $doctor_ids ) {
            return new WP_REST_Response( [], 200 );
        }
        $term_ids = wp_get_object_terms( $doctor_ids, wpddb()->doctor_category, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
            return new WP_REST_Response( [], 200 );
        }
        $terms = get_terms( [
            'taxonomy' => wpddb()->doctor_category,
            'include'  => array_unique( $term_ids ),
        ] );
        // Fetch all doctor-term relationships once outside the loop to avoid N+1 queries.
        $doctor_terms = wp_get_object_terms( $doctor_ids, wpddb()->doctor_category, [ 'fields' => 'all_with_object_id' ] );
        // Build a count map: term_id => number of clinic-scoped doctors in that term.
        $term_count = [];
        if ( ! is_wp_error( $doctor_terms ) ) {
            foreach ( $doctor_terms as $dt ) {
                $tid = (int) $dt->term_id;
                $term_count[ $tid ] = ( $term_count[ $tid ] ?? 0 ) + 1;
            }
        }
        $list = [];
        foreach ( $terms as $t ) {
            $list[] = [
                'id'    => (int) $t->term_id,
                'name'  => $t->name,
                'count' => $term_count[ (int) $t->term_id ] ?? 0,
            ];
        }
        return new WP_REST_Response( $list, 200 );
    }

    public function list_my_bookings( WP_REST_Request $request ) {
        $clinic_id = $this->get_caller_clinic_id();
        if ( ! $clinic_id ) {
            return new WP_REST_Response( [], 200 );
        }

        $args              = FreeRestApi::collect_booking_query_args( $request );
        $args['clinic_id'] = $clinic_id; // hard-override

        $page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $rows  = FreeRestApi::run_booking_query( $args, $per_page, $offset );
        $total = FreeRestApi::run_booking_count( $args );

        $resp = new WP_REST_Response( $rows, 200 );
        $resp->header( 'X-WP-Total', (int) $total );
        $resp->header( 'X-WP-TotalPages', $per_page > 0 ? (int) ceil( $total / $per_page ) : 0 );
        return $resp;
    }

    public function cancel_my_booking( WP_REST_Request $request ) {
        global $wpdb;
        $clinic_id  = $this->get_caller_clinic_id();
        $booking_id = (int) $request['id'];
        $table      = $wpdb->prefix . 'wpddb_bookings';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, p.full_name, p.email, p.phone FROM {$table} b LEFT JOIN {$wpdb->prefix}wpddb_patients p ON p.id = b.patient_id WHERE b.id = %d",
            $booking_id
        ), ARRAY_A );
        if ( ! $row ) {
            return new WP_REST_Response( [ 'error' => 'Booking not found' ], 404 );
        }
        if ( (int) $row['clinic_id'] !== $clinic_id ) {
            return new WP_REST_Response( [ 'error' => 'Not your clinic' ], 403 );
        }
        if ( $row['status'] !== 'approved' ) {
            return new WP_REST_Response( [ 'error' => 'Already cancelled or not active' ], 400 );
        }

        $wpdb->update( $table, [ 'status' => 'cancelled' ], [ 'id' => $booking_id ], [ '%s' ], [ '%d' ] );
        FreeHelper::send_booking_notifications(
            $row['booking_id'],
            (int) $row['doctor_id'],
            $row['day'],
            FreeHelper::convert_time_format( $row['time'], '12' ),
            (int) $row['clinic_id'],
            [
                'name'  => $row['full_name'] ?: '',
                'email' => $row['email'] ?: '',
                'phone' => $row['phone'] ?: '',
            ],
            'cancel',
            $row['booking_date']
        );
        return new WP_REST_Response( [ 'cancelled' => $booking_id ], 200 );
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function serialize_manager( $user, $clinic_id ) {
        $clinic = $clinic_id ? get_post( $clinic_id ) : null;
        return [
            'user_id'      => (int) $user->ID,
            'username'     => $user->user_login,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'roles'        => array_values( (array) $user->roles ),
            'clinic_id'    => (int) $clinic_id,
            'clinic_name'  => $clinic ? $clinic->post_title : '',
            'assigned_at'  => get_user_meta( $user->ID, UserRole::META_KEY . '_assigned_at', true ) ?: gmdate( 'Y-m-d', strtotime( $user->user_registered ) ),
        ];
    }

    private function detect_role_warning( $user ) {
        $roles = (array) $user->roles;
        if ( in_array( 'administrator', $roles, true ) ) {
            return 'administrator';
        }
        if ( in_array( 'booking_manager', $roles, true ) ) {
            return 'booking_manager';
        }
        return null;
    }

    private function get_caller_clinic_id() {
        $clinic_id = UserRole::get_user_clinic_id();
        // Admins debugging without an assignment can pass clinic_id explicitly
        // — only honored when the caller has manage_options.
        if ( ! $clinic_id && current_user_can( 'manage_options' ) ) {
            $req_clinic = isset( $_REQUEST['clinic_id'] ) ? (int) $_REQUEST['clinic_id'] : 0;
            if ( $req_clinic && get_post_type( $req_clinic ) === wpddb()->post_type_clinic ) {
                return $req_clinic;
            }
        }
        return $clinic_id;
    }

    private function serialize_clinic( $post ) {
        return [
            'id'           => (int) $post->ID,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'meta'         => [
                'wpddb_clinic_email'     => get_post_meta( $post->ID, 'wpddb_clinic_email', true ),
                'wpddb_clinic_hotline'   => get_post_meta( $post->ID, 'wpddb_clinic_hotline', true ),
                'wpddb_clinic_address'   => get_post_meta( $post->ID, 'wpddb_clinic_address', true ),
                'wpddb_clinic_latitude'  => get_post_meta( $post->ID, 'wpddb_clinic_latitude', true ),
                'wpddb_clinic_longitude' => get_post_meta( $post->ID, 'wpddb_clinic_longitude', true ),
            ],
            'thumbnail'    => get_the_post_thumbnail_url( $post->ID, 'medium' ),
        ];
    }

    private function serialize_doctor_summary( $doctor_id ) {
        $depts    = wp_get_object_terms( $doctor_id, wpddb()->doctor_category, [ 'fields' => 'names' ] );
        $thumb_id = (int) get_post_thumbnail_id( $doctor_id );
        return [
            'id'              => (int) $doctor_id,
            'post_title'      => get_the_title( $doctor_id ),
            'designation'     => get_post_meta( $doctor_id, 'wpddb_doctor_designation', true ),
            'speciality'      => get_post_meta( $doctor_id, 'wpddb_doctor_speciality', true ),
            'departments'     => is_wp_error( $depts ) ? [] : $depts,
            'thumbnail_id'    => $thumb_id,
            'thumbnail'       => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
            'thumbnail_large' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' )    : '',
            'fee'             => get_post_meta( $doctor_id, 'wpddb_doctor_booking_fee', true ),
        ];
    }

    private function serialize_doctor_full( $doctor_id, $clinic_id = 0 ) {
        $base = $this->serialize_doctor_summary( $doctor_id );
        $term_ids = wp_get_object_terms( $doctor_id, wpddb()->doctor_category, [ 'fields' => 'ids' ] );

        // Expose only THIS clinic's info from wpddb_clinics_info so the manager
        // never sees (or can infer) other clinics' phone numbers and holidays.
        $all_info    = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );
        $clinic_info = [
            'phone_number'  => '',
            'is_holiday'    => false,
            'holiday_dates' => [ 'start_date' => '', 'end_date' => '' ],
        ];
        if ( $clinic_id && is_array( $all_info ) && ! empty( $all_info[ $clinic_id ] ) ) {
            $entry = $all_info[ $clinic_id ];
            $clinic_info = [
                'phone_number'  => (string) ( $entry['phone_number'] ?? '' ),
                'is_holiday'    => ! empty( $entry['is_holiday'] ),
                'holiday_dates' => [
                    'start_date' => (string) ( $entry['holiday_dates']['start_date'] ?? '' ),
                    'end_date'   => (string) ( $entry['holiday_dates']['end_date'] ?? '' ),
                ],
            ];
        }

        return array_merge( $base, [
            'post_content'   => get_post_field( 'post_content', $doctor_id ),
            'department_id'  => is_wp_error( $term_ids ) || empty( $term_ids ) ? 0 : (int) $term_ids[0],
            'meta'           => [
                'wpddb_doctor_designation' => get_post_meta( $doctor_id, 'wpddb_doctor_designation', true ),
                'wpddb_doctor_speciality'  => get_post_meta( $doctor_id, 'wpddb_doctor_speciality', true ),
                'wpddb_doctor_degree'      => get_post_meta( $doctor_id, 'wpddb_doctor_degree', true ),
                'wpddb_doctor_workplace'   => get_post_meta( $doctor_id, 'wpddb_doctor_workplace', true ),
                'wpddb_doctor_email'       => get_post_meta( $doctor_id, 'wpddb_doctor_email', true ),
            ],
            'schedule'       => get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true ) ?: [],
            'clinic_info'    => $clinic_info,
        ] );
    }

    /**
     * Sanitize and merge a partial clinics_info update for ONE clinic into the
     * doctor's full wpddb_clinics_info, leaving other clinics' entries intact.
     * IDOR-safe: ignores any clinic_id in the payload, always writes to $clinic_id.
     */
    private function merge_clinic_info( $doctor_id, array $incoming, $clinic_id ) {
        $all   = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );
        $all   = is_array( $all ) ? $all : [];
        $entry = [
            'phone_number'  => sanitize_text_field( (string) ( $incoming['phone_number'] ?? '' ) ),
            'is_holiday'    => ! empty( $incoming['is_holiday'] ),
            'holiday_dates' => [
                'start_date' => sanitize_text_field( (string) ( $incoming['holiday_dates']['start_date'] ?? '' ) ),
                'end_date'   => sanitize_text_field( (string) ( $incoming['holiday_dates']['end_date'] ?? '' ) ),
            ],
        ];
        $all[ (int) $clinic_id ] = $entry;
        update_post_meta( $doctor_id, 'wpddb_clinics_info', $all );
    }

    private function doctor_in_clinic( $doctor_id, $clinic_id ) {
        if ( get_post_type( $doctor_id ) !== wpddb()->post_type_doctor ) {
            return false;
        }
        $schedule = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
        if ( ! is_array( $schedule ) ) {
            return false;
        }
        foreach ( $schedule as $day ) {
            if ( empty( $day['clinics'] ) || ! is_array( $day['clinics'] ) ) {
                continue;
            }
            foreach ( $day['clinics'] as $c ) {
                if ( isset( $c['id'] ) && (int) $c['id'] === (int) $clinic_id ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Sanitize a manager-submitted schedule so that:
     *  1. Only the manager's own clinic_id can appear in the clinics array for each day.
     *     Other clinic entries are stripped (prevents IDOR overwrite of shared-doctor timings).
     *  2. If the manager's clinic is absent from every day, append a default Mon entry so the
     *     doctor stays linked to the clinic.
     */
    private function ensure_clinic_in_schedule( array $schedule, $clinic_id ) {
        $clinic_found = false;

        foreach ( $schedule as &$day ) {
            if ( empty( $day['clinics'] ) || ! is_array( $day['clinics'] ) ) {
                continue;
            }
            // Keep only the manager's clinic; discard any other clinic entry.
            $filtered = [];
            foreach ( $day['clinics'] as $c ) {
                if ( isset( $c['id'] ) && (int) $c['id'] === (int) $clinic_id ) {
                    $filtered[]   = $c;
                    $clinic_found = true;
                }
            }
            $day['clinics'] = $filtered;
        }
        unset( $day );

        if ( ! $clinic_found ) {
            $schedule[] = $this->build_default_schedule( $clinic_id )[0];
        }
        return $schedule;
    }

    /**
     * Sanitize a raw schedule array from user input.
     * Uses the same formats as the admin's save_wpddb_doctor_post_meta:
     *  - clinic.id: string (sanitize_text_field), not int
     *  - timing.is_bookable: FILTER_VALIDATE_BOOLEAN (handles "true"/"false" strings)
     */
    private function sanitize_schedule( array $schedule ) {
        $allowed_days = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
        $sanitized    = [];
        foreach ( $schedule as $day ) {
            if ( ! is_array( $day ) ) {
                continue;
            }
            $day_name = sanitize_text_field( (string) ( $day['day'] ?? '' ) );
            if ( ! in_array( $day_name, $allowed_days, true ) ) {
                continue;
            }
            $entry = [
                'day'       => $day_name,
                'available' => filter_var( $day['available'] ?? false, FILTER_VALIDATE_BOOLEAN ),
                'clinics'   => [],
            ];
            foreach ( (array) ( $day['clinics'] ?? [] ) as $clinic ) {
                if ( ! is_array( $clinic ) || empty( $clinic['id'] ) ) {
                    continue;
                }
                $timings = [];
                foreach ( (array) ( $clinic['timings'] ?? [] ) as $t ) {
                    if ( ! is_array( $t ) || empty( $t['time'] ) ) {
                        continue;
                    }
                    $timings[] = [
                        'time'        => sanitize_text_field( (string) $t['time'] ),
                        'is_bookable' => filter_var( $t['is_bookable'] ?? true, FILTER_VALIDATE_BOOLEAN ),
                    ];
                }
                $entry['clinics'][] = [
                    'id'      => sanitize_text_field( (string) $clinic['id'] ),
                    'name'    => sanitize_text_field( (string) ( $clinic['name'] ?? '' ) ),
                    'timings' => $timings,
                ];
            }
            $sanitized[] = $entry;
        }
        return $sanitized;
    }

    /**
     * Merge the manager's updated schedule (which only contains their clinic's entries)
     * back into the doctor's full existing schedule so other clinics' data is preserved.
     *
     * For each day in the existing schedule:
     *  - Keep all clinic entries whose id !== manager's clinic_id (untouched).
     *  - Replace the manager's clinic entries with what the manager submitted for that day.
     *
     * Days present in the incoming schedule but not in the existing one are appended.
     */
    private function merge_schedule_preserving_other_clinics( $doctor_id, array $incoming, $clinic_id ) {
        $existing = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
        if ( ! is_array( $existing ) || empty( $existing ) ) {
            return $incoming;
        }

        // Index incoming by day name for fast lookup.
        $incoming_by_day = [];
        foreach ( $incoming as $day ) {
            $incoming_by_day[ $day['day'] ] = $day;
        }

        $merged = [];
        foreach ( $existing as $ex_day ) {
            $day_name = $ex_day['day'] ?? '';
            if ( isset( $incoming_by_day[ $day_name ] ) ) {
                // Strip manager's clinic from existing; insert manager's updated entries.
                $other_clinics = array_values( array_filter(
                    (array) ( $ex_day['clinics'] ?? [] ),
                    fn( $c ) => (int) ( $c['id'] ?? 0 ) !== (int) $clinic_id
                ) );
                $mgr_clinics = array_values( array_filter(
                    (array) ( $incoming_by_day[ $day_name ]['clinics'] ?? [] ),
                    fn( $c ) => (int) ( $c['id'] ?? 0 ) === (int) $clinic_id
                ) );
                $ex_day['clinics']   = array_merge( $other_clinics, $mgr_clinics );
                $ex_day['available'] = $incoming_by_day[ $day_name ]['available'] ?? $ex_day['available'];
                unset( $incoming_by_day[ $day_name ] );
            }
            $merged[] = $ex_day;
        }

        // Append any days the manager added that didn't exist in the full schedule.
        foreach ( $incoming_by_day as $new_day ) {
            $merged[] = $new_day;
        }

        return $merged;
    }

    private function build_default_schedule( $clinic_id ) {
        $clinic_name = get_the_title( $clinic_id ) ?: '#' . $clinic_id;
        return [
            [
                'day'       => 'Monday',
                'available' => true,
                'clinics'   => [
                    [
                        // String ID matches the admin's sanitize_text_field format.
                        'id'      => (string) (int) $clinic_id,
                        'name'    => $clinic_name,
                        'timings' => [
                            [ 'time' => '9:00 AM',  'is_bookable' => true ],
                            [ 'time' => '10:00 AM', 'is_bookable' => true ],
                            [ 'time' => '11:00 AM', 'is_bookable' => true ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Rebuild wpddb_doctor_available_clinic from the current full schedule,
     * matching what the admin's PostMeta::save_wpddb_doctor_post_meta writes.
     * Called after every schedule write so clinic-based queries stay correct.
     */
    private function sync_available_clinics( $doctor_id ) {
        $schedule = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
        $ids      = [];
        if ( is_array( $schedule ) ) {
            foreach ( $schedule as $day ) {
                foreach ( (array) ( $day['clinics'] ?? [] ) as $c ) {
                    if ( ! empty( $c['id'] ) ) {
                        $ids[] = sanitize_text_field( (string) $c['id'] );
                    }
                }
            }
        }
        $ids = array_values( array_unique( $ids ) );
        if ( $ids ) {
            update_post_meta( $doctor_id, 'wpddb_doctor_available_clinic', $ids );
        } else {
            delete_post_meta( $doctor_id, 'wpddb_doctor_available_clinic' );
        }
    }
}
