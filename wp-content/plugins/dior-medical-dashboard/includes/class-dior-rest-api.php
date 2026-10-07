<?php
/**
 * Dior Medical - REST API v2
 * 
 * Provides modern REST API endpoints for patient data access
 * Endpoints: /wp-json/dior/v2/
 * 
 * @package Dior Medical
 * @version 2.8
 */

if (!defined('ABSPATH'))
    exit;

class Dior_Medical_REST_API
{
    private $namespace = 'dior/v2';
    private $rest_base_patients = 'patients';
    private $rest_base_appointments = 'appointments';
    private $rest_base_prescriptions = 'prescriptions';
    private $rest_base_analytics = 'analytics';

    private $rest_base_hipaa_intake = 'hipaa-intake';

    public function check_hipaa_intake_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'dior_rest_not_logged_in',
                'You must be logged in to access this information.',
                array( 'status' => 401 )
            );
        }

        $current_user = wp_get_current_user();
        if ( current_user_can( 'manage_options' ) ) return true;
        if ( in_array( 'doctor', (array) $current_user->roles, true ) ) return true;

        $patient_id = absint( $request->get_param( 'patient_id' ) );
        if ( ! $patient_id ) {
            $patient_id = get_current_user_id();
        }

        if ( $patient_id !== get_current_user_id() ) {
            return new \WP_Error(
                'dior_rest_forbidden',
                'You are not allowed to access this patient record.',
                array( 'status' => 403 )
            );
        }
        return true;
    }

    public function get_hipaa_intake( $request ) {
        $patient_id = absint( $request->get_param( 'patient_id' ) );
        if ( ! $patient_id ) $patient_id = get_current_user_id();

        if ( ! $patient_id ) {
            return new \WP_Error('dior_invalid_patient', 'Invalid patient ID.', array( 'status' => 400 ));
        }

        $patient = get_user_by( 'ID', $patient_id );
        if ( ! $patient ) {
            return new \WP_Error('dior_patient_not_found', 'Patient not found.', array( 'status' => 404 ));
        }

        $hipaa_data = get_user_meta($patient_id, 'dior_hipaa_intake', true);
        if ( empty( $hipaa_data ) ) {
            return new \WP_Error('dior_hipaa_data_not_found', 'No HIPAA intake data found for this patient.', array( 'status' => 404 ));
        }

        if ( ! is_array( $hipaa_data ) ) $hipaa_data = array('value' => $hipaa_data);

        $raw_data = array();
        if ( isset( $hipaa_data['raw_data'] ) && is_array( $hipaa_data['raw_data'] ) ) {
            $raw_data = $hipaa_data['raw_data'];
        }

        $eligibility_fields = array('eligibility_age_18', 'eligibility_pregnant', 'eligibility_emergency', 'eligibility_location');
        $eligibility = array();
        $eligibility_labels = array();

        foreach ( $eligibility_fields as $field_name ) {
            $value = null;
            if ( array_key_exists( $field_name, $raw_data ) ) {
                $value = $raw_data[ $field_name ];
            }
            if ( null === $value && isset( $raw_data['form_data'] ) && is_array( $raw_data['form_data'] ) && array_key_exists( $field_name, $raw_data['form_data'] ) ) {
                $value = $raw_data['form_data'][ $field_name ];
            }
            if ( null === $value && array_key_exists( $field_name, $hipaa_data ) ) {
                $value = $hipaa_data[ $field_name ];
            }
            
            // Ensure value is a string for mapping if exists
            $str_val = (string) $value;
            $eligibility[ $field_name ] = $str_val;
            
            // Generate labels
            $label = '';
            if ( $field_name === 'eligibility_age_18' || $field_name === 'eligibility_pregnant' ) {
                if ( $str_val === '1' ) $label = 'Yes';
                elseif ( $str_val === '2' ) $label = 'No';
            } else {
                if ( $str_val === '1' ) $label = 'Yes';
                elseif ( $str_val === '2' ) $label = 'No';
                elseif ( $str_val === '3' ) $label = "I'm not sure";
            }
            
            $eligibility_labels[ $field_name ] = $label;
        }

        return rest_ensure_response(array(
            'success'            => true,
            'patient_id'         => $patient_id,
            'submitted'          => true,
            'eligibility'        => $eligibility,
            'eligibility_labels' => $eligibility_labels,
            'form'               => array(
                'title'       => 'Dior Medical Urgent Care - Patient Eligibility',
                'workflow_id' => '01a110f3-ddb0-7783-88de-8b9b744cb56d'
            )
        ));
    }


    /**
     * Initialize REST API
     */
    public static function init()
    {
        $api = new self();
        add_action('rest_api_init', [$api, 'register_routes']);
    }

    /**
     * Register all REST routes
     */
    public function register_routes()
    {
        // Patient endpoints
        register_rest_route($this->namespace, '/' . $this->rest_base_patients, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_patients'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'per_page' => ['type' => 'integer', 'default' => 20],
                    'page' => ['type' => 'integer', 'default' => 1],
                    'search' => ['type' => 'string'],
                ],
            ],
        ]);

        // Get single patient
        register_rest_route($this->namespace, '/' . $this->rest_base_patients . '/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_patient'],
                'permission_callback' => [$this, 'check_patient_permission'],
            ],
        ]);

        // Update patient profile
        register_rest_route($this->namespace, '/' . $this->rest_base_patients . '/(?P<id>\d+)', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_patient'],
                'permission_callback' => [$this, 'check_patient_permission'],
            ],
        ]);

        // Appointment endpoints
        register_rest_route($this->namespace, '/' . $this->rest_base_appointments, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_appointments'],
                'permission_callback' => [$this, 'check_auth_permission'],
                'args' => [
                    'patient_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string'],
                    'from_date' => ['type' => 'string'],
                    'to_date' => ['type' => 'string'],
                ],
            ],
        ]);

        // Create appointment
        register_rest_route($this->namespace, '/' . $this->rest_base_appointments, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_appointment'],
                'permission_callback' => [$this, 'check_auth_permission'],
            ],
        ]);

        // Get single appointment
        register_rest_route($this->namespace, '/' . $this->rest_base_appointments . '/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_appointment'],
                'permission_callback' => [$this, 'check_auth_permission'],
            ],
        ]);

        // Update appointment
        register_rest_route($this->namespace, '/' . $this->rest_base_appointments . '/(?P<id>\d+)', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_appointment'],
                'permission_callback' => [$this, 'check_auth_permission'],
            ],
        ]);

        // Prescription endpoints
        register_rest_route($this->namespace, '/' . $this->rest_base_prescriptions, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_prescriptions'],
                'permission_callback' => [$this, 'check_auth_permission'],
            ],
        ]);

        // Get single prescription
        register_rest_route($this->namespace, '/' . $this->rest_base_prescriptions . '/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_prescription'],
                'permission_callback' => [$this, 'check_auth_permission'],
            ],
        ]);

        // Analytics endpoints (admin only)
        register_rest_route($this->namespace, '/' . $this->rest_base_analytics . '/dashboard', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_analytics_dashboard'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);
    }

    /**
     * Permission Callbacks
     */
    public function check_admin_permission()
    {
        return current_user_can('manage_options');
    }

    public function check_auth_permission()
    {
        return is_user_logged_in();
    }

    public function check_patient_permission($request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $patient_id = $request['id'];
        $current_user = wp_get_current_user();

        // Allow if user is viewing their own profile or is admin
        return (current_user_can('manage_options') || intval($current_user->ID) === intval($patient_id));
    }

    /**
     * ====== PATIENT ENDPOINTS ======
     */

    /**
     * Get all patients (admin)
     */
    public function get_patients($request)
    {
        $page = intval($request['page']);
        $per_page = intval($request['per_page']);
        $search = $request['search'] ?? '';

        $args = [
            'role__in' => ['subscriber', 'patient', 'customer'],
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
        }

        $user_query = new WP_User_Query($args);
        $patients = [];

        foreach ($user_query->get_results() as $user) {
            $patients[] = $this->format_patient_data($user);
        }

        return rest_ensure_response([
            'data' => $patients,
            'total' => $user_query->get_total(),
            'pages' => ceil($user_query->get_total() / $per_page),
        ]);
    }

    /**
     * Get single patient
     */
    public function get_patient($request)
    {
        $patient_id = intval($request['id']);
        $patient = get_userdata($patient_id);

        if (!$patient) {
            return new WP_Error('patient_not_found', 'Patient not found', ['status' => 404]);
        }

        return rest_ensure_response([
            'data' => $this->format_patient_data($patient),
        ]);
    }

    /**
     * Update patient profile
     */
    public function update_patient($request)
    {
        $patient_id = intval($request['id']);
        $params = $request->get_json_params();

        $updates = [
            'first_name' => $params['first_name'] ?? null,
            'last_name' => $params['last_name'] ?? null,
            'phone' => $params['phone'] ?? null,
            'dob' => $params['dob'] ?? null,
            'gender' => $params['gender'] ?? null,
            'address' => $params['address'] ?? null,
        ];

        foreach ($updates as $key => $value) {
            if ($value !== null) {
                update_user_meta($patient_id, $key, sanitize_text_field($value));
            }
        }

        $patient = get_userdata($patient_id);
        return rest_ensure_response([
            'data' => $this->format_patient_data($patient),
            'message' => 'Patient updated successfully',
        ]);
    }

    /**
     * ====== APPOINTMENT ENDPOINTS ======
     */

    /**
     * Get appointments
     */
    public function get_appointments($request)
    {
        global $wpdb;

        $patient_id = $request['patient_id'] ?? get_current_user_id();
        $status = $request['status'] ?? null;
        $from_date = $request['from_date'] ?? date('Y-m-d', strtotime('-1 month'));
        $to_date = $request['to_date'] ?? date('Y-m-d', strtotime('+1 month'));

        // Build query
        $where = "WHERE user_id = %d AND appointment_date BETWEEN %s AND %s";
        $params = [$patient_id, $from_date, $to_date];

        if ($status) {
            $where .= " AND status = %s";
            $params[] = $status;
        }

        // Note: This assumes custom table. Adjust based on your actual storage
        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = 'appointment_data' $where",
                ...$params
            ),
            ARRAY_A
        );

        return rest_ensure_response([
            'data' => $appointments,
            'count' => count($appointments),
        ]);
    }

    /**
     * Create appointment
     */
    public function create_appointment($request)
    {
        $params = $request->get_json_params();

        $appointment = [
            'patient_id' => get_current_user_id(),
            'service' => sanitize_text_field($params['service'] ?? ''),
            'appointment_date' => sanitize_text_field($params['appointment_date'] ?? ''),
            'appointment_time' => sanitize_text_field($params['appointment_time'] ?? ''),
            'consultation_type' => sanitize_text_field($params['consultation_type'] ?? 'phone'),
            'status' => 'scheduled',
            'created_at' => current_time('mysql'),
        ];

        // Store appointment
        $appointment_id = wp_insert_post([
            'post_type' => 'dior_appointment',
            'post_status' => 'publish',
            'post_title' => 'Appointment - ' . $appointment['service'],
        ]);

        if (is_wp_error($appointment_id)) {
            return new WP_Error('appointment_creation_failed', 'Failed to create appointment', ['status' => 500]);
        }

        foreach ($appointment as $key => $value) {
            update_post_meta($appointment_id, $key, $value);
        }

        return rest_ensure_response([
            'data' => array_merge($appointment, ['id' => $appointment_id]),
            'message' => 'Appointment created successfully',
        ]);
    }

    /**
     * Get single appointment
     */
    public function get_appointment($request)
    {
        $appointment_id = intval($request['id']);
        $post = get_post($appointment_id);

        if (!$post) {
            return new WP_Error('appointment_not_found', 'Appointment not found', ['status' => 404]);
        }

        $appointment = [
            'id' => $post->ID,
            'service' => get_post_meta($post->ID, 'service', true),
            'appointment_date' => get_post_meta($post->ID, 'appointment_date', true),
            'status' => get_post_meta($post->ID, 'status', true),
        ];

        return rest_ensure_response(['data' => $appointment]);
    }

    /**
     * Update appointment
     */
    public function update_appointment($request)
    {
        $appointment_id = intval($request['id']);
        $params = $request->get_json_params();

        $allowed_updates = ['status', 'appointment_date', 'appointment_time', 'notes'];

        foreach ($allowed_updates as $field) {
            if (isset($params[$field])) {
                update_post_meta($appointment_id, $field, sanitize_text_field($params[$field]));
            }
        }

        return rest_ensure_response([
            'message' => 'Appointment updated successfully',
            'id' => $appointment_id,
        ]);
    }

    /**
     * ====== PRESCRIPTION ENDPOINTS ======
     */

    /**
     * Get prescriptions
     */
    public function get_prescriptions($request)
    {
        global $wpdb;

        $patient_id = intval($request['patient_id'] ?? get_current_user_id());

        $prescriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->postmeta} 
                WHERE post_id IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'patient_id' AND meta_value = %d
                ) AND meta_key = 'prescription_data'",
                $patient_id
            ),
            ARRAY_A
        );

        return rest_ensure_response([
            'data' => $prescriptions,
            'count' => count($prescriptions),
        ]);
    }

    /**
     * Get single prescription
     */
    public function get_prescription($request)
    {
        $prescription_id = intval($request['id']);
        $prescription_data = get_post_meta($prescription_id, 'prescription_data', true);

        if (!$prescription_data) {
            return new WP_Error('prescription_not_found', 'Prescription not found', ['status' => 404]);
        }

        return rest_ensure_response([
            'data' => array_merge($prescription_data, ['id' => $prescription_id]),
        ]);
    }

    /**
     * ====== ANALYTICS ENDPOINTS ======
     */

    /**
     * Get analytics dashboard data
     */
    public function get_analytics_dashboard()
    {
        return rest_ensure_response([
            'total_patients' => count_users()['total_users'],
            'appointments_today' => $this->count_appointments_today(),
            'revenue_today' => $this->get_revenue_today(),
            'pending_actions' => $this->get_pending_actions(),
        ]);
    }

    /**
     * ====== HELPER METHODS ======
     */

    /**
     * Format patient data for API response
     */
    private function format_patient_data($user)
    {
        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'first_name' => get_user_meta($user->ID, 'first_name', true) ?: $user->first_name,
            'last_name' => get_user_meta($user->ID, 'last_name', true) ?: $user->last_name,
            'phone' => get_user_meta($user->ID, 'phone', true),
            'date_of_birth' => get_user_meta($user->ID, 'dob', true),
            'gender' => get_user_meta($user->ID, 'gender', true),
            'registered_date' => $user->user_registered,
        ];
    }

    /**
     * Count appointments for today
     */
    private function count_appointments_today()
    {
        global $wpdb;
        $today = date('Y-m-d');

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'appointment_date' 
                AND meta_value LIKE %s",
                $today . '%'
            )
        );
    }

    /**
     * Get revenue for today
     */
    private function get_revenue_today()
    {
        global $wpdb;
        $today = date('Y-m-d');

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'payment_amount' 
                AND post_id IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'payment_date' AND meta_value LIKE %s
                )",
                $today . '%'
            )
        ) ?: 0;
    }

    /**
     * Get pending actions
     */
    private function get_pending_actions()
    {
        global $wpdb;

        return [
            'pending_questionnaires' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'questionnaire_status' AND meta_value = 'pending'"
            ),
            'pending_prescriptions' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'prescription_status' AND meta_value = 'pending'"
            ),
            'pending_payments' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'payment_status' AND meta_value = 'pending'"
            ),
        ];
    }
}

// Initialize REST API
Dior_Medical_REST_API::init();
