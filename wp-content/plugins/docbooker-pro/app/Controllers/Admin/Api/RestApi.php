<?php
namespace WpDreamers\WPDDBP\Controllers\Admin\Api;


use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use WpDreamers\WPDDB\Controllers\Admin\Api\RestApi as FreeRestApi;
use WpDreamers\WPDDB\Controllers\Helper\Helper as FreeHelper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDBP\Controllers\Helper\Helper;
use WpDreamers\WPDDBP\Controllers\Payments\BookingPayment;
use WpDreamers\WPDDBP\Traits\SingletonTrait;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class RestApi{
    use SingletonTrait;

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_rest_api_endpoint' ] );
    }
    public function register_rest_api_endpoint() {
        // Existing endpoint
        register_rest_route('get_wpddbp_currency_list/v1', '/data', array(
            'methods' => 'GET',
            'callback' => [$this,'get_all_currency'],
            'permission_callback' => '__return_true',
        ));

        // Stripe endpoints
        register_rest_route('doctor-details-booking/v1', '/payment-config', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_payment_config'],
            'permission_callback' => '__return_true',
        ));

        register_rest_route('doctor-details-booking/v1', '/doctor/(?P<doctor_id>\d+)/booking-fee', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_doctor_booking_fee'],
            'permission_callback' => '__return_true',
        ));

        // Doctors filtered by department AND/OR clinic — for pickers that narrow as the
        // visitor chooses (e.g. the hero booking card). The free
        // `wpddb_get_doctors_by_clinic` can't serve this: it *requires* a category (no
        // "any"), ignores the clinic+department combination, and returns dropdown label
        // strings rather than data. Public, read-only, published doctors only.
        register_rest_route('doctor-details-booking/v1', '/doctors-by', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_doctors_by'],
            'permission_callback' => '__return_true',
            'args' => array(
                'dept'   => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
                'clinic' => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
            ),
        ));

        // Departments that actually have doctors — optionally only at a given clinic, so a
        // picker can hide departments that would dead-end.
        register_rest_route('doctor-details-booking/v1', '/departments-by', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_departments_by'],
            'permission_callback' => '__return_true',
            'args' => array(
                'clinic' => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
            ),
        ));

        register_rest_route('doctor-details-booking/v1', '/process-payment', array(
            'methods' => 'POST',
            'callback' => [$this, 'process_payment'],
            'permission_callback' => [$this, 'verify_nonce'],
        ));

        // Razorpay endpoints
        register_rest_route('doctor-details-booking/v1', '/razorpay/create-order', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_razorpay_order' ],
            'permission_callback' => [ $this, 'verify_nonce' ],
        ]);
        register_rest_route('doctor-details-booking/v1', '/razorpay/verify-payment', [
            'methods'  => 'POST',
            'callback' => [ $this, 'verify_razorpay_payment' ],
            'permission_callback' => [ $this, 'verify_nonce' ],
        ]);

        // Pro Booking Management — bulk cancel
        register_rest_route('wpddb_pro/v1', '/bookings/bulk-cancel', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'bulk_cancel_bookings' ],
            'permission_callback' => [ $this, 'verify_booking_admin' ],
        ]);

        // Pro Booking Management — CSV export (honors current filter set)
        register_rest_route('wpddb_pro/v1', '/bookings/export', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'export_bookings_csv' ],
            'permission_callback' => [ $this, 'verify_booking_admin' ],
        ]);
    }

    public function verify_booking_admin( WP_REST_Request $request ) {
        // Header for fetch() POSTs, URL param for window.open() CSV downloads.
        $nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' );
        return wp_verify_nonce( $nonce, 'wp_rest' ) && current_user_can( 'wpddb_manage_booking_system' );
    }

    /**
     * Bulk-cancel: sets b.status='cancel' for all matching ids that are still 'approved'.
     * Sends one notification per cancelled booking via the free Helper.
     */
    public function bulk_cancel_bookings( WP_REST_Request $request ) {
        $ids = array_filter( array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
        if ( ! $ids ) {
            return new WP_REST_Response( [ 'success_ids' => [], 'failed_ids' => [], 'message' => 'No ids provided' ], 400 );
        }

        global $wpdb;
        $table        = $wpdb->prefix . 'wpddb_bookings';
        $patients     = $wpdb->prefix . 'wpddb_patients';
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // Fetch records once so notifications can be sent + we know which were actually cancellable.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id, b.booking_id, b.doctor_id, b.clinic_id, b.day, b.time, b.booking_date, b.status,
                    p.full_name, p.email, p.phone
             FROM {$table} AS b LEFT JOIN {$patients} AS p ON b.patient_id = p.id
             WHERE b.id IN ($placeholders)",
            ...$ids
        ), ARRAY_A );

        $success = [];
        $failed  = [];

        foreach ( $rows as $row ) {
            if ( $row['status'] !== 'approved' ) {
                $failed[] = (int) $row['id'];
                continue;
            }
            $updated = $wpdb->update(
                $table,
                [ 'status' => 'cancelled' ],
                [ 'id' => (int) $row['id'], 'status' => 'approved' ],
                [ '%s' ],
                [ '%d', '%s' ]
            );
            if ( $updated ) {
                $success[] = (int) $row['id'];
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
            } else {
                $failed[] = (int) $row['id'];
            }
        }

        return new WP_REST_Response( [
            'success_ids' => $success,
            'failed_ids'  => $failed,
        ], 200 );
    }

    /**
     * Streams a CSV of bookings matching the same filter args as the booking_details endpoint.
     * Reuses the free query builder so filter semantics never drift between table and export.
     */
    public function export_bookings_csv( WP_REST_Request $request ) {
        $args = FreeRestApi::collect_booking_query_args( $request );

        // 0/0 means "no LIMIT" — return all matching rows.
        $rows = FreeRestApi::run_booking_query( $args, 0, 0 );

        $filename = 'bookings-' . wp_date( 'Y-m-d_His' ) . '.csv';

        // Send headers manually because we're streaming raw CSV, not JSON.
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        // BOM so Excel opens UTF-8 cleanly
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [
            'Booking ID', 'Patient', 'Email', 'Phone', 'Doctor', 'Clinic',
            'Date', 'Day', 'Time', 'Status', 'Present Status',
            'Payment Status', 'Payment Gateway', 'Amount', 'Created At',
        ] );

        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row['booking_id'] ?? '',
                $row['full_name'] ?? '',
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['doctor_name'] ?? '',
                $row['clinic_name'] ?? '',
                $row['booking_date'] ?? '',
                $row['day'] ?? '',
                $row['time'] ?? '',
                $row['status'] ?? '',
                $row['booking_present_status'] ?? '',
                $row['payment_status'] ?? '',
                $row['payment_by'] ?? '',
                $row['amount_paid'] ?? '',
                $row['created_at'] ?? '',
            ] );
        }

        fclose( $out );
        exit; // we've already flushed the response
    }
    public function get_all_currency(  ) {
        $currency_list=[];
        foreach (Helper::currency_list() as $key=>$currency){
            $currency_list[]= [
                'value' => $key,
                'label' => $currency['name'] . ' (' . $currency['symbol'] . ')'
            ];
        }

        return wp_json_encode($currency_list);
    }

    // ============================================
    // Nonce Verification
    // ============================================
    public function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        return wp_verify_nonce( $nonce, 'wp_rest' ) !== false;
    }

    // ============================================
    // Stripe Initialization
    // ============================================
    private function init_stripe() {
        try {

            $stripe_mode =WpddbOptions::get_option('stripe_mode','wpddb_global_settings') ?:'test';
            $api_key = $stripe_mode === 'live'
                ? WpddbOptions::get_option('stripe_live_secret_key','wpddb_global_settings')
                : WpddbOptions::get_option('stripe_test_secret_key','wpddb_global_settings');

            if ( ! $api_key ) {
                throw new \Exception( 'Stripe API key not configured' );
            }

            \Stripe\Stripe::setApiKey( $api_key );
            return true;
        } catch ( \Exception $e ) {
            error_log( 'Stripe Init Error: ' . $e->getMessage() );
            return false;
        }
    }


    // ============================================
    // GET: Doctor Booking Fee
    // ============================================
    /**
     * GET /doctor-details-booking/v1/doctors-by?dept=&clinic=
     *
     * Doctors matching a department and/or a clinic. Either filter may be 0 = "any", so
     * the same route serves every combination the hero card can produce. An empty list is
     * a legitimate answer (that department genuinely has nobody at that clinic) — the
     * caller shows its "nothing available" note rather than falling back to everyone.
     *
     * clinic match: `wpddb_doctor_available_clinic` holds a serialised list of clinic ids
     * as strings, so it's matched on the quoted id — the same way the rest of the plugin
     * queries that relation.
     */
    public function get_doctors_by( $request ) {
        $dept   = absint( $request->get_param( 'dept' ) );
        $clinic = absint( $request->get_param( 'clinic' ) );

        $args = array(
            'post_type'              => wpddb()->post_type_doctor,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        );
        if ( $clinic ) {
            $args['meta_query'] = array(
                array(
                    'key'     => 'wpddb_doctor_available_clinic',
                    'value'   => '"' . $clinic . '"',
                    'compare' => 'LIKE',
                ),
            );
        }
        if ( $dept ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => wpddb()->doctor_category,
                    'field'    => 'term_id',
                    'terms'    => $dept,
                ),
            );
        }

        $out = array();
        foreach ( get_posts( apply_filters( 'wpddbp_doctors_by_query_args', $args, $dept, $clinic ) ) as $doc ) {
            // Superset of free's get_doctor_by_departments shape (id/name/designation/
            // speciality/degree/image/work_place) so the booking form's doctor cards keep
            // rendering, PLUS value/label for label-based pickers like the hero dropdown.
            // `id` stays an int to match free — the form compares doctorId === dc.id, and a
            // string would silently break selection + preselect highlighting.
            $out[] = array(
                'id'          => (int) $doc->ID,
                'name'        => $doc->post_title,
                'designation' => get_post_meta( $doc->ID, 'wpddb_doctor_designation', true ),
                'speciality'  => get_post_meta( $doc->ID, 'wpddb_doctor_speciality', true ),
                'degree'      => get_post_meta( $doc->ID, 'wpddb_doctor_degree', true ),
                'image'       => get_the_post_thumbnail_url( $doc->ID, 'medium' ),
                'work_place'  => get_post_meta( $doc->ID, 'wpddb_doctor_workplace', true ),
                'value'       => (string) $doc->ID,
                'label'       => $doc->post_title,
            );
        }

        return new \WP_REST_Response( $out, 200 );
    }

    /**
     * GET /doctor-details-booking/v1/departments-by?clinic=
     *
     * Departments with at least one doctor, with per-department counts. With `clinic` set,
     * only doctors at that clinic count — so a department nobody covers there is omitted
     * rather than leading the visitor into an empty doctor step.
     *
     * Mirrors the free departments response (id/name/count) so callers can swap it in.
     */
    public function get_departments_by( $request ) {
        $clinic = absint( $request->get_param( 'clinic' ) );
        $terms  = get_terms( array(
            'taxonomy'   => wpddb()->doctor_category,
            'hide_empty' => false,
        ) );
        if ( is_wp_error( $terms ) ) {
            return new \WP_REST_Response( array(), 200 );
        }

        $out = array();
        foreach ( $terms as $term ) {
            $args = array(
                'post_type'      => wpddb()->post_type_doctor,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'tax_query'      => array(
                    array(
                        'taxonomy' => wpddb()->doctor_category,
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ),
                ),
            );
            if ( $clinic ) {
                $args['meta_query'] = array(
                    array(
                        'key'     => 'wpddb_doctor_available_clinic',
                        'value'   => '"' . $clinic . '"',
                        'compare' => 'LIKE',
                    ),
                );
            }
            $count = count( get_posts( $args ) );
            if ( ! $count ) {
                continue; // nobody here — don't offer it
            }
            $out[] = array(
                'id'    => (int) $term->term_id,
                'name'  => $term->name,
                'count' => $count,
                'value' => (string) $term->term_id,
                'label' => $term->name,
            );
        }

        return new \WP_REST_Response( $out, 200 );
    }

    public function get_doctor_booking_fee( $request ) {
        try {
            $doctor_id = intval( $request['doctor_id'] );
            // The real fee; 0 means booking this doctor is free. (A missing fee used
            // to be quoted as 50, which the server never charged.)
            $booking_fee = BookingPayment::doctor_fee( $doctor_id );

            return new \WP_REST_Response( array(
                'bookingFee' => $booking_fee,
            ), 200 );

        } catch ( \Exception $e ) {
            return new \WP_REST_Response( array(
                'error' => 'Failed to get booking fee'
            ), 500 );
        }
    }

    // ============================================
    // POST: Process Payment
    // ============================================
    public function process_payment( $request ) {
        try {
            // Verify nonce
            $nonce = $request->get_header( 'X-WP-Nonce' );
            if ( !  wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new \WP_REST_Response( array(
                    'error' => 'Invalid security token'
                ), 403 );
            }

            // Initialize Stripe
            if ( !  $this->init_stripe() ) {
                return new \WP_REST_Response( array(
                    'error' => 'Payment configuration not available'
                ), 500 );
            }

            // Get request body
            $body = json_decode( $request->get_body(), true );
            $body = is_array( $body ) ? $body : array();

            if ( empty( $body['paymentMethodId'] ) ) {
                return new \WP_REST_Response( array(
                    'error' => 'Missing required field: paymentMethodId'
                ), 400 );
            }

            // The browser says which doctor; the server decides the price. Any
            // amount or currency in the request is ignored.
            $doctor_id = absint( $body['doctorId'] ?? ( $body['metadata']['doctorId'] ?? 0 ) );
            if ( ! $doctor_id || get_post_status( $doctor_id ) !== 'publish' || get_post_type( $doctor_id ) !== wpddb()->post_type_doctor ) {
                return new \WP_REST_Response( array(
                    'error' => 'Doctor not found'
                ), 404 );
            }
            if ( 'stripe' !== BookingPayment::active_gateway() || ! BookingPayment::is_required( $doctor_id ) ) {
                return new \WP_REST_Response( array(
                    'error' => 'Online payment is not required for this doctor'
                ), 400 );
            }

            $currency_code = BookingPayment::currency();
            $amount        = BookingPayment::to_minor( BookingPayment::doctor_fee( $doctor_id ), $currency_code );
            $metadata      = array(
                'doctor_id'     => (string) $doctor_id,
                'patient_email' => sanitize_email( $body['metadata']['patientEmail'] ?? '' ),
                'booking_day'   => sanitize_text_field( $body['metadata']['bookingDay'] ?? '' ),
                'booking_time'  => sanitize_text_field( $body['metadata']['bookingTime'] ?? '' ),
            );

            try {
                // Create payment intent with the payment method
                $payment_method = sanitize_text_field( $body['paymentMethodId'] );
                $payment_intent = \Stripe\PaymentIntent::create( array(
                    'amount' => $amount,
                    'currency' => strtolower( $currency_code ),
                    'payment_method' => $payment_method,
                    'confirm' => true,
                    'description' => sanitize_text_field( $body['description'] ?? 'Doctor Booking' ),
                    'metadata' => $metadata,
                    'automatic_payment_methods' => array(
                        'enabled' => true,
                        'allow_redirects' => 'never'
                    ),
                ), array(
                    // A retried request with the same card form cannot charge twice.
                    'idempotency_key' => 'wpddbp_' . md5( $payment_method . '|' . $doctor_id . '|' . $amount ),
                ) );

                // Check payment status
                if ( $payment_intent->status === 'succeeded' ) {
                    error_log( 'Payment succeeded:  ' . $payment_intent->id );

                    return new \WP_REST_Response( array(
                        'paymentIntentId' => $payment_intent->id,
                        'status' => 'succeeded',
                        'amount' => $payment_intent->amount,
                        'currency' => $payment_intent->currency,
                    ), 200 );

                } elseif ( $payment_intent->status === 'requires_action' ) {
                    return new \WP_REST_Response( array(
                        'error' => 'Payment requires additional action',
                        'paymentIntentId' => $payment_intent->id,
                        'clientSecret' => $payment_intent->client_secret,
                    ), 402 );

                } else {
                    return new \WP_REST_Response( array(
                        'error' => 'Payment failed:  ' . $payment_intent->status
                    ), 402 );
                }

            } catch ( \Stripe\Exception\CardException $e ) {
                error_log( 'Card Exception: ' . $e->getMessage() );
                return new \WP_REST_Response( array(
                    'error' => 'Card declined: ' . $e->getMessage()
                ), 402 );

            } catch ( \Stripe\Exception\RateLimitException $e ) {
                error_log( 'Rate Limit Exception: ' . $e->getMessage() );
                return new \WP_REST_Response( array(
                    'error' => 'Too many requests. Please try again later.'
                ), 429 );

            } catch ( \Stripe\Exception\InvalidRequestException $e ) {
                error_log( 'Invalid Request:  ' . $e->getMessage() );
                return new \WP_REST_Response( array(
                    'error' => 'Invalid payment details:  ' . $e->getMessage()
                ), 400 );

            } catch ( \Stripe\Exception\AuthenticationException $e ) {
                error_log( 'Authentication Exception: ' .  $e->getMessage() );
                return new \WP_REST_Response( array(
                    'error' => 'Authentication failed'
                ), 401 );

            } catch ( \Stripe\Exception\ApiConnectionException $e ) {
                error_log( 'API Connection Exception: ' . $e->getMessage() );
                return new \WP_REST_Response( array(
                    'error' => 'Connection error.  Please try again later.'
                ), 503 );

            } catch ( \Stripe\Exception\ApiErrorException $e ) {
                error_log( 'API Error:  ' . $e->getMessage() );
                return new \WP_REST_Response( array(
                    'error' => 'Payment processing error: ' . $e->getMessage()
                ), 500 );
            }

        } catch ( \Exception $e ) {
            error_log( 'Payment Process Error: ' . $e->getMessage() );
            return new \WP_REST_Response( array(
                'error' => 'An unexpected error occurred'
            ), 500 );
        }
    }
    public function get_payment_config( $request ) {
        try {
            $online_booking_payment = WpddbOptions::get_option('online_booking_payment','wpddb_global_settings') ? :'on';
            $payment_gateway = WpddbOptions::get_option('payment_gateway','wpddb_global_settings') ?:'stripe';
            $enabled = $online_booking_payment === 'on';
            $stripe_mode = WpddbOptions::get_option('stripe_mode','wpddb_global_settings') ?:'test';
            $currency = WpddbOptions::get_option('currency','wpddb_global_settings') ?:'USD';
            $currency_position = WpddbOptions::get_option('currency_position','wpddb_global_settings') ?:'left';

            if ( !  $enabled ) {
                return new \WP_REST_Response([
                    'enabled' => false,
                    'gateway' => null
                ], 200);
            }

            if ( $payment_gateway === 'stripe' ) {
                $publishable_key = $stripe_mode === 'live'
                    ? WpddbOptions:: get_option('stripe_live_publishable_key','wpddb_global_settings')
                    : WpddbOptions::get_option('stripe_test_publishable_key','wpddb_global_settings');
                $secret_key = $stripe_mode === 'live'
                    ? WpddbOptions::get_option('stripe_live_secret_key','wpddb_global_settings')
                    : WpddbOptions::get_option('stripe_test_secret_key','wpddb_global_settings');

                // Missing keys ⇒ Stripe.js can't initialise (window.Stripe('') throws),
                // so the card form would silently fail to load. Report the gateway as
                // not configured (and therefore not enabled) so the booking form falls
                // back to request-only instead of rendering a broken card field.
                $configured = ! empty( $publishable_key ) && ! empty( $secret_key );

                return new \WP_REST_Response( array(
                    'enabled' => $configured,
                    'configured' => $configured,
                    'currency' => strtoupper( $currency ),
                    'gateway' => 'stripe',
                    'currency_position' => esc_html($currency_position),
                    'mode' => $stripe_mode,
                    'publishableKey' => $publishable_key,
                    'payAtClinic' => \WpDreamers\WPDDBP\Controllers\Payments\BookingPayment::pay_at_clinic_allowed(),
                ), 200 );
            }elseif ( $payment_gateway === 'razorpay' ) {
                $mode = WpddbOptions::get_option('razorpay_mode', 'wpddb_global_settings') ?: 'test';
                $key_id = WpddbOptions::get_option(
                    $mode === 'live' ? 'razorpay_live_key_id' : 'razorpay_test_key_id',
                    'wpddb_global_settings'
                );
                $key_secret = WpddbOptions::get_option(
                    $mode === 'live' ? 'razorpay_live_secret_key' : 'razorpay_test_secret_key',
                    'wpddb_global_settings'
                );
                $configured = ! empty( $key_id ) && ! empty( $key_secret );
                return new \WP_REST_Response([
                    'enabled' => $configured,
                    'configured' => $configured,
                    'gateway' => 'razorpay',
                    'currency' => $currency,
                    'currency_position' => esc_html($currency_position),
                    'mode' => $mode,
                    'keyId' => $key_id,
                    'payAtClinic' => \WpDreamers\WPDDBP\Controllers\Payments\BookingPayment::pay_at_clinic_allowed(),
                ], 200);
            }
            return new \WP_REST_Response([
                'enabled' => false,
                'gateway' => null
            ], 200);


        } catch ( \Exception $e ) {
            return new \WP_REST_Response( array(
                'error' => 'Failed to get Stripe configuration'
            ), 500 );
        }
    }
    public function create_razorpay_order( $request ) {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];

        // The browser says which doctor; the server decides the price.
        $doctor_id = absint( $params['doctorId'] ?? ( $params['metadata']['doctorId'] ?? 0 ) );
        if ( ! $doctor_id || get_post_status( $doctor_id ) !== 'publish' || get_post_type( $doctor_id ) !== wpddb()->post_type_doctor ) {
            return new \WP_REST_Response( [ 'error' => 'Doctor not found' ], 404 );
        }
        if ( 'razorpay' !== BookingPayment::active_gateway() || ! BookingPayment::is_required( $doctor_id ) ) {
            return new \WP_REST_Response( [ 'error' => 'Online payment is not required for this doctor' ], 400 );
        }

        $api      = BookingPayment::razorpay_api();
        $currency = BookingPayment::currency();

        $orderData = [
            'receipt'  => 'booking_' . $doctor_id . '_' . time(),
            'amount'   => BookingPayment::to_minor( BookingPayment::doctor_fee( $doctor_id ), $currency ),
            'currency' => $currency,
            'notes'    => [ 'doctor_id' => (string) $doctor_id ],
        ];

        try {
            $order = $api->order->create($orderData);

            return new \WP_REST_Response([
                'orderId' => $order['id'],
                'amount'  => $order['amount'],
                'currency' => $order['currency']
            ], 200);
        } catch (\Exception $e) {
            error_log( 'DocBooker Pro: Razorpay order failed: ' . $e->getMessage() );
            return new \WP_REST_Response([
                'error' => 'Could not start the payment. Please try again.'
            ], 500);
        }
    }
    public function verify_razorpay_payment( $request ) {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];
        $api    = BookingPayment::razorpay_api();

        if ( ! $api || empty( $params['razorpay_order_id'] ) || empty( $params['razorpay_payment_id'] ) || empty( $params['razorpay_signature'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'Payment could not be verified' ], 400 );
        }

        try {
            $attributes = [
                'razorpay_order_id'   => sanitize_text_field( $params['razorpay_order_id'] ),
                'razorpay_payment_id' => sanitize_text_field( $params['razorpay_payment_id'] ),
                'razorpay_signature'  => sanitize_text_field( $params['razorpay_signature'] ),
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // The signature proves Razorpay saw this payment for this order; the
            // order itself says how much and for which doctor.
            $order = $api->order->fetch( $attributes['razorpay_order_id'] );
            BookingPayment::remember_razorpay_payment(
                $attributes['razorpay_payment_id'],
                (int) ( $order['notes']['doctor_id'] ?? 0 ),
                (int) $order['amount'],
                (string) $order['currency']
            );

            return new \WP_REST_Response([
                'verified' => true,
                'paymentId' => $attributes['razorpay_payment_id']
            ], 200);

        } catch (SignatureVerificationError $e) {
            return new \WP_REST_Response([
                'error' => 'Payment could not be verified'
            ], 400);
        } catch (\Exception $e) {
            error_log( 'DocBooker Pro: Razorpay verification failed: ' . $e->getMessage() );
            return new \WP_REST_Response([
                'error' => 'Payment could not be verified'
            ], 500);
        }
    }

}
