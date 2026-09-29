<?php

namespace WpDreamers\WPDDB\Controllers;
// Do not allow directly accessing this file.
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;
use WpDreamers\WPDDBP\Controllers\Helper\Helper as HelperPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ScriptsController {
	use SingletonTrait;


	private $version;


	private $ajaxurl;

	private $styles = [];


	private $scripts = [];


	/**
	 * Class Constructor
	 */
	public function __construct() {
		$this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : WPDDB_VERSION;
		/**
		 * Admin scripts.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'backend_assets' ], 99 );

		/**
		 * Public scripts.
		 */
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ], 15 );

        add_action('wp_footer', [ $this, 'maybe_enqueue_global_doctor_booking_script' ]);
	}

	/**
	 * Registers Admin scripts.
	 *
	 * @return void
	 */
	public function backend_assets( $screen ) {
		$_screen = get_current_screen();
		$scripts = [
			[
				'handle' => 'wpd-booking-management-js',
				'src'    => wpddb()->get_assets_uri( 'admin/js/wpd-booking-management.js' ),
				'deps'   => [ 'wp-i18n' ],
				'footer' => true,
			],
            [
                'handle' => 'wpd-settings-page-js',
                'src'    => wpddb()->get_assets_uri( 'admin/js/wpd-settings-page.js' ),
                'deps'   => [ 'wp-i18n' ],
                'footer' => true,
            ],
			[
				'handle' => 'wpd-doc-booker-admin-js',
				'src'    => wpddb()->get_assets_uri( 'admin/js/wpd-doc-booker-admin.js' ),
				'deps'   => [ 'wp-i18n' ],
				'footer' => true,
			],
		];

		// Register public scripts.
		foreach ( $scripts as $script ) {
			wp_register_script( $script['handle'], $script['src'], $script['deps'], $this->version, $script['footer'] );
		}
		$styles = [
			[
				'handle' => 'wpd-doc-booker-admin-css',
				'src'    => wpddb()->get_assets_uri( 'admin/css/wpd-doc-booker-admin.css' ),
			],
		];

		// Register public styles.
		foreach ( $styles as $style ) {
			wp_register_style( $style['handle'], $style['src'], [], $this->version );
		}
		$common_params = $this->get_common_localize_params();
		switch ($screen) {
			case 'edit.php':
			case 'post.php':
			case 'post-new.php':
				if ($this->admin_enqueue_supported_posts_type($_screen)) {
					wp_enqueue_media();
					wp_enqueue_script('wpd-doc-booker-admin-js');
					wp_set_script_translations( 'wpd-doc-booker-admin-js', 'doc-booker', WPDDB_PATH . 'languages' );

					$post_id = get_the_ID();
					wp_localize_script(
						'wpd-doc-booker-admin-js',
						'wpddbParams',
						array_merge($common_params, [
							'metaFields'    => $this->meta_fields($post_id),
							'shortcodeMetaFields'    => $this->shortcode_meta_fields($post_id),
                            'doctor_shortcode_layouts' => Helper::doctor_shortcode_layout(),
                            'clinic_shortcode_layouts' => Helper::clinic_shortcode_layout(),
							'clinics'       => Clinic::get_clinics(),
							'language_suggestions'   => wpddb()->has_pro() ? HelperPro::get_doctor_languages() : [],
							'credential_suggestions' => wpddb()->has_pro() ? HelperPro::get_doctor_credentials() : [],
							'clinic_service_suggestions' => wpddb()->has_pro() ? HelperPro::get_clinic_services() : [],
							'marker_icon'   => wpddb()->get_assets_uri('public/img/marker-icon.png'),
							'marker_shadow' => wpddb()->get_assets_uri('public/img/marker-shadow.png'),
						])
					);
				}
				break;

			case 'wpddb_doctor_page_settings':
				wp_enqueue_script('wpd-settings-page-js');
				wp_set_script_translations( 'wpd-settings-page-js', 'doc-booker', WPDDB_PATH . 'languages' );
				wp_localize_script(
					'wpd-settings-page-js',
					'wpddbSettingsParams',
					array_merge($common_params, [
						'doctor_layout' => Helper::doctor_page_layout(),
						'clinic_layout' => Helper::clinic_page_layout(),
					])
				);
				break;

			case 'wpddb_doctor_page_booking-management':
				wp_enqueue_script('wpd-booking-management-js');
				wp_set_script_translations( 'wpd-booking-management-js', 'doc-booker', WPDDB_PATH . 'languages' );
				wp_localize_script(
					'wpd-booking-management-js',
					'wpddbBookingParams',
					$common_params
				);
				break;
		}
        wp_enqueue_style( 'wpd-doc-booker-admin-css' );

	}


	/**
	 * Enqueues public scripts.
	 *
	 * @return void
	 */
	public function enqueue_public_scripts() {
        global $wpddb_needs_doctor_booking_script;

		/**
		 * Register scripts.
		 */
		$this->register_public_scripts();

		if ( Clinic::is_single_clinic() ) {
			wp_enqueue_style( 'wpd-doc-leaflet' );
		}


		/**
		 * Styles.
		 */
		wp_enqueue_style( 'wpd-doc-booker' );
        $this->dynamic_styles();

		/**
		 * Scripts.
		 */
		if ( Clinic::is_single_clinic() ) {
			wp_enqueue_script( 'wpd-doc-leaflet' );
		}
		if (Doctor::is_doctor()){
			$doctor_id = get_the_ID();
			wp_enqueue_script('wpd-doc-details-booking');
			wp_set_script_translations( 'wpd-doc-details-booking', 'doc-booker', WPDDB_PATH . 'languages' );
			$this->payment_scripts_enqueue();
			wp_localize_script('wpd-doc-details-booking', 'doctorBookingData', array(
				'doctorId' => $doctor_id,
				'doctorName' => get_the_title( $doctor_id ),
				'apiUrl' => esc_url_raw(rest_url()),
				'nonce' => wp_create_nonce('wp_rest'),
				'has_pro' => wpddb()->has_pro(),
				'preBookingWindow' => max( 0, intval( WpddbOptions::get_option( 'pre_booking_window', 'wpddb_doctor_settings', 0 ) ) ),
			));
		}
		wp_enqueue_script( 'wpd-doc-booker' );
        wp_localize_script(
            'wpd-doc-booker',
            'wpddbPublicParams',
            [
                'ajaxUrl'         => esc_url( admin_url( 'admin-ajax.php' ) ),
                'adminUrl'        => esc_url( admin_url() ),
                wpddb()->nonceId  => wp_create_nonce(wpddb()->nonceText),

            ]
        );

	}

	/**
	 * Register public scripts.
	 *
	 * @return void
	 */
	public function register_public_scripts() {
		$this->get_public_assets();

		// Register public styles.
		foreach ( $this->styles as $style ) {
			wp_register_style( $style['handle'], $style['src'], '', $this->version );
		}

		// Register public scripts.
		foreach ( $this->scripts as $script ) {
			wp_register_script( $script['handle'], $script['src'], $script['deps'], $this->version, $script['footer'] );
		}
	}

	/**
	 * Get all frontend scripts.
	 *
	 * @return void
	 */
	private function get_public_assets() {
		$this->get_public_styles()->get_public_scripts();
	}

	/**
	 * Get public styles.
	 *
	 * @return object
	 */
	private function get_public_styles() {


		$this->styles[] = [
			'handle' => 'wpd-doc-booker',
			'src'    => wpddb()->get_assets_uri( 'public/css/wpd-doc-booker.css' ),
		];
		$this->styles[] = [
			'handle' => 'wpd-doc-leaflet',
			'src'    => wpddb()->get_assets_uri( 'vendor/leaflet/css/leaflet.min.css' ),
		];

		return $this;
	}

	/**
	 * Get public scripts.
	 *
	 * @return object
	 */
	private function get_public_scripts() {

		$this->scripts[] = [
			'handle' => 'wpd-doc-booker',
			'src'    => wpddb()->get_assets_uri( 'public/js/wpd-doc-booker.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];
		$this->scripts[] = [
			'handle' => 'wpd-doc-leaflet',
			'src'    => wpddb()->get_assets_uri( 'vendor/leaflet/js/leaflet.min.js' ),
			'deps'   => [ 'jquery' ],
			'footer' => true,
		];
		$this->scripts[] = [
			'handle' => 'wpd-doc-details-booking',
			'src'    => wpddb()->get_assets_uri( 'public/js/wpd-doc-details-booking.js' ),
			'deps'   => [ 'wp-i18n' ],
			'footer' => true,
		];
        $this->scripts[] = [
            'handle' => 'wpd-global-doc-booking',
            'src'    => wpddb()->get_assets_uri( 'public/js/wpd-global-doc-booking.js' ),
            'deps'   => [ 'wp-i18n' ],
            'footer' => true,
        ];
		return $this;
	}

	public function admin_enqueue_supported_posts_type( $screen ) {
		$post_types = apply_filters(
			'wpddb_admin_enqueue_supported_posts_type',
			[
				wpddb()->post_type_doctor,
				wpddb()->post_type_clinic,
				wpddb()->post_type_doctor_shortcode,
				wpddb()->post_type_clinic_shortcode,
			]
		);
		if ( in_array( $screen->post_type, $post_types, true ) ) {
			return true;
		} else {
			return false;
		}
	}

    public function maybe_enqueue_global_doctor_booking_script() {
        global $wpddb_needs_doctor_booking_script;

        if (!empty($wpddb_needs_doctor_booking_script)) {
            wp_enqueue_script('wpd-global-doc-booking');
            wp_set_script_translations( 'wpd-global-doc-booking', 'doc-booker', WPDDB_PATH . 'languages' );
            $this->payment_scripts_enqueue();
            wp_localize_script('wpd-global-doc-booking', 'globalDoctorBooking', [
                'apiUrl' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'has_pro' => wpddb()->has_pro(),
                'preBookingWindow' => max( 0, intval( WpddbOptions::get_option( 'pre_booking_window', 'wpddb_doctor_settings', 0 ) ) ),
            ]);
        }
    }

    public function payment_scripts_enqueue() {
        if ( !  wpddb()->has_pro() ) {
            return;
        }
        $online_booking_payment = WpddbOptions::get_option(
            'online_booking_payment',
            'wpddb_global_settings'
        );

        $payment_gateway = WpddbOptions::get_option(
            'payment_gateway',
            'wpddb_global_settings'
        );

        $online_booking_payment = $online_booking_payment === 'on';

        if (  $online_booking_payment ) {
            if ( $payment_gateway === 'stripe' ) {
                wp_enqueue_script(
                    'stripe-js',
                    'https://js.stripe.com/v3/',
                    array(),
                    null,
                    true
                );
            }elseif ( $payment_gateway === 'razorpay' ) {
                wp_enqueue_script(
                    'razorpay-js',
                    'https://checkout.razorpay.com/v1/checkout.js',
                    array(),
                    null,
                    true
                );
            }

        }
    }

    public function meta_fields( $post_id ) {
        $data = [
            'doctor_designation' => get_post_meta( $post_id, 'wpddb_doctor_designation', true ) ?: '',
            'doctor_speciality'  => get_post_meta( $post_id, 'wpddb_doctor_speciality', true ) ?: '',
            'doctor_workplace'   => get_post_meta( $post_id, 'wpddb_doctor_workplace', true ) ?: '',
            'doctor_degree'      => get_post_meta( $post_id, 'wpddb_doctor_degree', true ) ?: '',
            'clinic_address'     => get_post_meta( $post_id, 'wpddb_clinic_address', true ) ?: '',
            'clinic_latitude'    => get_post_meta( $post_id, 'wpddb_clinic_latitude', true ) ?: '',
            'clinic_longitude'   => get_post_meta( $post_id, 'wpddb_clinic_longitude', true ) ?: '',
            'clinic_hotline'     => get_post_meta( $post_id, 'wpddb_clinic_hotline', true ) ?: '',
            'clinic_email'       => get_post_meta( $post_id, 'wpddb_clinic_email', true ) ?: '',
            'schedule'           => get_post_meta( $post_id, 'wpddb_doctor_schedule', true ) ?: [],
            'clinics_info'       => get_post_meta( $post_id, 'wpddb_clinics_info', true ) ?: [],
        ];


        $data = apply_filters( 'wpddb_extend_meta_fields', $data, $post_id );

        return apply_filters( 'wppdb_post_meta_value', $data );
    }

    public function shortcode_meta_fields( $post_id ) {
        $post_type = get_post_type( $post_id );

        $meta = [];

        if ( wpddb()->post_type_doctor_shortcode === $post_type ) {
            $doctor_fields = [
                'layout', 'post_limit', 'grid_columns', 'more_btn', 'btn_text', 'btn_url',
                'img_width', 'img_height', 'img_hard_crop', 'include', 'exclude', 'categories',
                'order_by', 'order', 'bg_color', 'title_color', 'department_color', 'content_color',
            ];

            foreach ( $doctor_fields as $field ) {
                $key = 'doctor_' . $field;
                $value = get_post_meta( $post_id, 'wpddb_' . $key, true );

                if ( in_array( $field, ['include', 'exclude', 'categories'], true ) ) {
                    $meta[ $key ] = $value ? $value : [];
                } else {
                    $meta[ $key ] = $value ?: '';
                }
            }
            $meta = apply_filters( 'wpddb_pro_shortcode_doctor_meta', $meta, $post_id );
        } elseif ( wpddb()->post_type_clinic_shortcode === $post_type ) {
            $clinic_fields = [
                'layout', 'post_limit', 'grid_columns', 'more_btn', 'btn_text', 'btn_url',
                'img_width', 'img_height', 'img_hard_crop', 'include', 'exclude',
                'order_by', 'order', 'bg_color', 'title_color', 'content_color',
            ];

            foreach ( $clinic_fields as $field ) {
                $key = 'clinic_' . $field;
                $value = get_post_meta( $post_id, 'wpddb_' . $key, true );

                if ( in_array( $field, ['include', 'exclude'], true ) ) {
                    $meta[ $key ] = $value ? $value : [];
                } else {
                    $meta[ $key ] = $value ?: '';
                }
            }
            $meta = apply_filters( 'wpddb_pro_shortcode_clinic_meta', $meta, $post_id );
        }

        return apply_filters( 'wpddb_shortcode_'.$post_type.'_meta_value', $meta );
    }

    private function get_common_localize_params() {
		return [
			'ajaxUrl'         => esc_url(admin_url('admin-ajax.php')),
			'adminUrl'        => esc_url(admin_url()),
			'restApiUrl'      => esc_url_raw(rest_url()),
			'rest_nonce'      => wp_create_nonce('wp_rest'),
			 wpddb()->nonceId  => wp_create_nonce(wpddb()->nonceText),
			'plugin_file_url' => wpddb()->plugin_url(),
            'has_pro' => wpddb()->has_pro(),
            'social_links' => wpddb()->has_pro() ? HelperPro::get_social_links() : [],
            'online_payment' => wpddb()->has_pro() && HelperPro::has_online_payment()
		];
	}
    public function dynamic_styles() {
        ob_start();
        require_once WPDDB_PATH. 'app/DynamicStyles/Frontend.php';
        $dynamic_css = ob_get_clean();
        $dynamic_css = $this->optimized_css( $dynamic_css );
        $dynamic_css = apply_filters( 'wpddb_settings_dynamic_css', $dynamic_css );
        wp_register_style( 'wpddb-dynamic', false );
        wp_enqueue_style( 'wpddb-dynamic' );
        wp_add_inline_style( 'wpddb-dynamic', $dynamic_css );
    }
    public function optimized_css( $css ) {
        $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
        $css = str_replace( [ "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ], ' ', $css );

        return $css;
    }
}
