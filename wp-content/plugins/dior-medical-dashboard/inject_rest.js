const fs = require('fs');
const path = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/includes/class-dior-rest-api.php';

let content = fs.readFileSync(path, 'utf8');

// The code to inject
const phpCode = `
    private $rest_base_hipaa_intake = 'hipaa-intake';

    public function check_hipaa_intake_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new \\WP_Error(
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
            return new \\WP_Error(
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
            return new \\WP_Error('dior_invalid_patient', 'Invalid patient ID.', array( 'status' => 400 ));
        }

        $patient = get_user_by( 'ID', $patient_id );
        if ( ! $patient ) {
            return new \\WP_Error('dior_patient_not_found', 'Patient not found.', array( 'status' => 404 ));
        }

        $hipaa_data = get_user_meta($patient_id, 'dior_hipaa_intake', true);
        if ( empty( $hipaa_data ) ) {
            return new \\WP_Error('dior_hipaa_data_not_found', 'No HIPAA intake data found for this patient.', array( 'status' => 404 ));
        }

        if ( ! is_array( $hipaa_data ) ) $hipaa_data = array('value' => $hipaa_data);

        $raw_data = array();
        if ( isset( $hipaa_data['raw_data'] ) && is_array( $hipaa_data['raw_data'] ) ) {
            $raw_data = $hipaa_data['raw_data'];
        }

        $eligibility_fields = array('eligibility_age_18', 'eligibility_pregnant', 'eligibility_emergency', 'eligibility_location');
        $eligibility = array();

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
            $eligibility[ $field_name ] = $value;
        }

        $intake = $hipaa_data;
        unset( $intake['raw_data'] );

        return rest_ensure_response(array(
            'success'      => true,
            'patient_id'   => $patient_id,
            'submitted'    => true,
            'eligibility'  => $eligibility,
            'intake'       => $intake,
            'form_data'    => $raw_data,
        ));
    }
`;

const registerCode = `
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base_hipaa_intake,
            array(
                'methods'             => \\WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_hipaa_intake' ),
                'permission_callback' => array( $this, 'check_hipaa_intake_permission' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base_hipaa_intake . '/(?P<patient_id>\\d+)',
            array(
                'methods'             => \\WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_hipaa_intake' ),
                'permission_callback' => array( $this, 'check_hipaa_intake_permission' ),
                'args'                => array(
                    'patient_id' => array(
                        'required'          => true,
                        'validate_callback' => function ( $param ) {
                            return is_numeric( $param ) && intval( $param ) > 0;
                        },
                    ),
                ),
            )
        );
`;

// Inject base variable and methods
if (!content.includes('$rest_base_hipaa_intake')) {
    content = content.replace("private $rest_base_analytics = 'analytics';", "private $rest_base_analytics = 'analytics';\n" + phpCode);
}

// Inject routes
if (!content.includes("register_rest_route(\n            $this->namespace,\n            '/' . $this->rest_base_hipaa_intake")) {
    content = content.replace("public function register_routes()\n    {", "public function register_routes()\n    {\n" + registerCode);
}

fs.writeFileSync(path, content, 'utf8');
console.log('Injected REST API routes into class-dior-rest-api.php');
