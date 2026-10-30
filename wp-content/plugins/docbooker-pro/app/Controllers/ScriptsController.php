<?php

namespace WpDreamers\WPDDBP\Controllers;
// Do not allow directly accessing this file.
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDBP\Controllers\MultiClinic\AdminMenu;
use WpDreamers\WPDDBP\Controllers\PatientPortal\Page as PortalPage;
use WpDreamers\WPDDBP\Controllers\Receptionist\Dashboard as ReceptionistDashboard;
use WpDreamers\WPDDBP\Controllers\Staff\AdminMenu as StaffAdminMenu;
use WpDreamers\WPDDBP\Controllers\Staff\StaffContext;
use WpDreamers\WPDDBP\Controllers\TestCatalog\AdminMenu as TestCatalogAdminMenu;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class ScriptsController{
    use SingletonTrait;
    private $version;


    private $ajaxurl;

    private $styles = [];


    private $scripts = [];
    public function __construct() {
        // Mirrored stylesheets for right-to-left languages.
        add_action( 'wp_print_styles', [ $this, 'rtl_styles' ], 0 );
        add_action( 'admin_print_styles', [ $this, 'rtl_styles' ], 0 );
        add_action( 'wp_print_footer_scripts', [ $this, 'rtl_styles' ], 0 );

        $this->version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : WPDDBP_VERSION;


        /**
         * Public scripts.
         */
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ], 15 );

        /**
         * Admin scripts (Multi-Clinic Manager + Staff + Test Items).
         */
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 20 );

        /**
         * Front-end clinic dashboard scripts.
         */
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_clinic_dashboard' ], 25 );

        /**
         * Front-end receptionist dashboard + patient portal.
         */
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_receptionist_dashboard' ], 26 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_patient_portal' ], 27 );

        /**
         * Late: undo the free plugin's eager payment-SDK enqueue on doctor singles
         * (BookingHead lazy-loads it when the visitor engages with the calendar).
         */
        add_action( 'wp_enqueue_scripts', [ $this, 'defer_doctor_payment_sdk' ], 100 );
    }

    /**
     * The free plugin eagerly enqueues the gateway SDK (~240 KB from
     * js.stripe.com / checkout.razorpay.com) on EVERY doctor context —
     * singles, the archive, the doctors page and department archives —
     * although only a single-page visitor who reaches the payment step ever
     * uses it. Dequeue it everywhere free's own Doctor::is_doctor() gate
     * enqueued it: on singles docBookerPro.BookingHead lazy-injects the same
     * script the moment a date is picked (long before a payment form can
     * mount), and archive/taxonomy pages have no payment UI at all. The
     * global booking-form shortcode is untouched — its enqueue runs at
     * render time, after this hook.
     */
    public function defer_doctor_payment_sdk() {
        $doctor_ctx = class_exists( '\WpDreamers\WPDDB\Controllers\Model\Doctor' )
            ? \WpDreamers\WPDDB\Controllers\Model\Doctor::is_doctor()
            : is_singular( 'wpddb_doctor' );
        if ( ! $doctor_ctx ) {
            return;
        }
        wp_dequeue_script( 'stripe-js' );
        wp_dequeue_script( 'razorpay-js' );
    }

    public function enqueue_admin_scripts() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }

        $is_clinic_mgr_page = $screen->id === 'wpddb_doctor_page_' . AdminMenu::PAGE_SLUG;
        $is_staff_page      = $screen->id === 'wpddb_doctor_page_' . StaffAdminMenu::PAGE_SLUG;
        $is_test_items_page = $screen->id === 'wpddb_doctor_page_' . TestCatalogAdminMenu::PAGE_SLUG;

        if ( $is_clinic_mgr_page ) {
            wp_enqueue_style( 'wpddb-pro-clinic-managers', wpddbp()->get_assets_uri( 'admin/css/wpd-clinic-managers.css' ), [], $this->version );
            wp_enqueue_script( 'wpddb-pro-clinic-managers', wpddbp()->get_assets_uri( 'admin/js/wpd-clinic-managers.js' ), [ 'wp-i18n' ], $this->version, true );
            wp_set_script_translations( 'wpddb-pro-clinic-managers', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
            $this->add_week_start( 'wpddb-pro-clinic-managers' );
            wp_localize_script( 'wpddb-pro-clinic-managers', 'wpddbProClinicManagersParams', [
                'restApiUrl' => esc_url_raw( rest_url() ),
                'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'    => esc_url( admin_url( 'admin-ajax.php' ) ),
            ] );
        }

        if ( $is_staff_page ) {
            wp_enqueue_style( 'wpddb-pro-staff', wpddbp()->get_assets_uri( 'admin/css/wpd-staff.css' ), [], $this->version );
            wp_enqueue_script( 'wpddb-pro-staff', wpddbp()->get_assets_uri( 'admin/js/wpd-staff.js' ), [ 'wp-i18n' ], $this->version, true );
            wp_set_script_translations( 'wpddb-pro-staff', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
            $this->add_week_start( 'wpddb-pro-staff' );
            wp_localize_script( 'wpddb-pro-staff', 'wpddbProStaffParams', [
                'restApiUrl'           => esc_url_raw( rest_url() ),
                'rest_nonce'           => wp_create_nonce( 'wp_rest' ),
                'multi_clinic_enabled' => StaffContext::is_multi_clinic_enabled(),
                'receptionist_enabled' => StaffContext::is_receptionist_enabled(),
                'single_clinic_id'     => StaffContext::single_clinic_id_or_zero(),
            ] );
        }

        if ( $is_test_items_page ) {
            wp_enqueue_style( 'wpddb-pro-test-items', wpddbp()->get_assets_uri( 'admin/css/wpd-test-items.css' ), [], $this->version );
            wp_enqueue_script( 'wpddb-pro-test-items', wpddbp()->get_assets_uri( 'admin/js/wpd-test-items.js' ), [ 'wp-i18n' ], $this->version, true );
            wp_set_script_translations( 'wpddb-pro-test-items', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
            $this->add_week_start( 'wpddb-pro-test-items' );
            wp_localize_script( 'wpddb-pro-test-items', 'wpddbProTestItemsParams', [
                'restApiUrl'  => esc_url_raw( rest_url() ),
                'rest_nonce'  => wp_create_nonce( 'wp_rest' ),
                'is_admin'    => current_user_can( 'manage_options' ),
                // Clinic picker options for the admin "Scope" select (0 = Global).
                'clinics'     => current_user_can( 'manage_options' ) ? $this->get_clinics_for_picker() : [],
            ] );
        }
    }

    /**
     * Calendars start their week on the day set in Settings → General →
     * Week Starts On (read by src/js/common/antd-locale.js).
     *
     * @param string $handle Script handle.
     */
    private function add_week_start( $handle ) {
        wp_add_inline_script( $handle, 'window.wpddbpWeekStart = ' . (int) get_option( 'start_of_week', 1 ) . ';', 'before' );
    }

    /**
     * Clinic list for admin pickers, ordered by title: [ { value, label }, ... ].
     *
     * @return array
     */
    private function get_clinics_for_picker() {
        $posts = get_posts( [
            'post_type'      => wpddb()->post_type_clinic,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        return array_map( fn( $p ) => [
            'value' => (int) $p->ID,
            'label' => $p->post_title ?: ( '#' . $p->ID ),
        ], $posts );
    }

    public function maybe_enqueue_clinic_dashboard() {
        if ( ! AdminMenu::is_enabled() ) {
            return;
        }
        $page_id = AdminMenu::get_dashboard_page_id();
        if ( ! $page_id || ! is_singular( 'page' ) || (int) get_the_ID() !== $page_id ) {
            return;
        }

        wp_enqueue_style( 'wpddb-pro-clinic-dashboard', wpddbp()->get_assets_uri( 'public/css/wpd-clinic-dashboard.css' ), [], $this->version );
        wp_enqueue_script( 'wpddb-pro-clinic-dashboard', wpddbp()->get_assets_uri( 'public/js/wpd-clinic-dashboard.js' ), [ 'wp-i18n' ], $this->version, true );
        wp_set_script_translations( 'wpddb-pro-clinic-dashboard', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
        $this->add_week_start( 'wpddb-pro-clinic-dashboard' );
        wp_localize_script( 'wpddb-pro-clinic-dashboard', 'wpddbProClinicDashboardParams', [
            'restApiUrl'     => esc_url_raw( rest_url() ),
            'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
            'logoutUrl'      => esc_url_raw( wp_logout_url( get_permalink( $page_id ) ) ),
            'online_payment' => \WpDreamers\WPDDBP\Controllers\Helper\Helper::has_online_payment(),
            'multi_clinic_enabled' => StaffContext::is_multi_clinic_enabled(),
        ] );
    }

    public function maybe_enqueue_receptionist_dashboard() {
        if ( ! ReceptionistDashboard::is_enabled() ) {
            return;
        }
        $page_id = ReceptionistDashboard::get_dashboard_page_id();
        if ( ! $page_id || ! is_singular( 'page' ) || (int) get_the_ID() !== $page_id ) {
            return;
        }
        wp_enqueue_style( 'wpddb-pro-receptionist-dashboard', wpddbp()->get_assets_uri( 'public/css/wpd-receptionist-dashboard.css' ), [], $this->version );
        wp_enqueue_script( 'wpddb-pro-receptionist-dashboard', wpddbp()->get_assets_uri( 'public/js/wpd-receptionist-dashboard.js' ), [ 'wp-i18n' ], $this->version, true );
        wp_set_script_translations( 'wpddb-pro-receptionist-dashboard', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
        $this->add_week_start( 'wpddb-pro-receptionist-dashboard' );
        wp_localize_script( 'wpddb-pro-receptionist-dashboard', 'wpddbProReceptionParams', [
            'restApiUrl'           => esc_url_raw( rest_url() ),
            'rest_nonce'           => wp_create_nonce( 'wp_rest' ),
            'logoutUrl'            => esc_url_raw( wp_logout_url( get_permalink( $page_id ) ) ),
            'multi_clinic_enabled' => StaffContext::is_multi_clinic_enabled(),
        ] );
    }

    public function maybe_enqueue_patient_portal() {
        $page_id = PortalPage::get_page_id();
        if ( ! $page_id || ! is_singular( 'page' ) || (int) get_the_ID() !== $page_id ) {
            return;
        }
        wp_enqueue_style( 'wpddb-pro-patient-portal', wpddbp()->get_assets_uri( 'public/css/wpd-patient-portal.css' ), [], $this->version );
        wp_enqueue_script( 'wpddb-pro-patient-portal', wpddbp()->get_assets_uri( 'public/js/wpd-patient-portal.js' ), [ 'wp-i18n' ], $this->version, true );
        wp_set_script_translations( 'wpddb-pro-patient-portal', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
        $this->add_week_start( 'wpddb-pro-patient-portal' );
        wp_localize_script( 'wpddb-pro-patient-portal', 'wpddbProPortalParams', [
            'restApiUrl' => esc_url_raw( rest_url() ),
            'ref'        => isset( $_GET['ref'] ) ? sanitize_text_field( (string) $_GET['ref'] ) : '',
        ] );
    }

    public function enqueue_public_scripts() {
        $clinic_gallery_view    = WpddbOptions::get_option('details_gallery_view','wpddb_clinic_settings') ?:'slider';

        /**
         * Register scripts.
         */
        $this->register_public_scripts();


        if (Clinic::is_single_clinic() && $clinic_gallery_view == 'grid') {
            wp_enqueue_style( 'wpdbp-photoswipe' );
            wp_enqueue_style( 'wpdbp-photoswipe-default-skin' );
            wp_enqueue_script( 'wpdbp-photoswipe' );
            wp_enqueue_script( 'wpdbp-photoswipe-ui' );
        }
        if (Clinic::is_single_clinic() && $clinic_gallery_view == 'slider'){
            wp_enqueue_style('wpdbp-swiper');
            $this->load_swiper();
        }
        /**
         * Styles.
         */
        wp_dequeue_style('wpd-doc-booker');
        wp_enqueue_style( 'wpd-doc-booker-pro' );
        /**
         * Scripts.
         */
        wp_enqueue_script( 'wpd-doc-booker-pro' );

        $params = [
            'ajaxUrl'         => esc_url( admin_url( 'admin-ajax.php' ) ),
            'adminUrl'        => esc_url( admin_url() ),
            wpddb()->nonceId  => wp_create_nonce(wpddb()->nonceText),
            'i18n'            => [
                'bookingFee'      => __( 'Booking fee', 'doc-booker-pro' ),
                'bookingProgress' => __( 'Booking progress', 'doc-booker-pro' ),
                'removeClinicFilter'     => __( 'Remove clinic filter', 'doc-booker-pro' ),
                'removeDepartmentFilter' => __( 'Remove department filter', 'doc-booker-pro' ),
                'noMoreResults'   => __( 'No more results to load', 'doc-booker-pro' ),
                'previous'        => __( 'Previous', 'doc-booker-pro' ),
                'next'            => __( 'Next', 'doc-booker-pro' ),
                'somethingWrong'  => __( 'Oops! Something went wrong', 'doc-booker-pro' ),
                'tryAgain'        => __( 'Try Again', 'doc-booker-pro' ),
            ],
        ];

        // BookingHead data (doctor singles): the fee badge + step count + lazy SDK
        // need payment config and the doctor's fee. Localizing them saves the two
        // REST round-trips the head would otherwise duplicate alongside the free
        // widget's own fetches. Mirrors RestApi::get_payment_config's key checks.
        if ( is_singular( 'wpddb_doctor' ) ) {
            $enabled = WpddbOptions::get_option( 'online_booking_payment', 'wpddb_global_settings' ) === 'on';
            $gateway = WpddbOptions::get_option( 'payment_gateway', 'wpddb_global_settings' ) ?: 'stripe';
            if ( $enabled ) {
                if ( 'razorpay' === $gateway ) {
                    $mode    = WpddbOptions::get_option( 'razorpay_mode', 'wpddb_global_settings' ) ?: 'test';
                    $key     = WpddbOptions::get_option( 'live' === $mode ? 'razorpay_live_key_id' : 'razorpay_test_key_id', 'wpddb_global_settings' );
                    $secret  = WpddbOptions::get_option( 'live' === $mode ? 'razorpay_live_secret_key' : 'razorpay_test_secret_key', 'wpddb_global_settings' );
                    $enabled = ! empty( $key ) && ! empty( $secret );
                } else {
                    $mode    = WpddbOptions::get_option( 'stripe_mode', 'wpddb_global_settings' ) ?: 'test';
                    $key     = WpddbOptions::get_option( 'live' === $mode ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key', 'wpddb_global_settings' );
                    $secret  = WpddbOptions::get_option( 'live' === $mode ? 'stripe_live_secret_key' : 'stripe_test_secret_key', 'wpddb_global_settings' );
                    $enabled = ! empty( $key ) && ! empty( $secret );
                }
            }
            $params['bookingHead'] = [
                'enabled'  => $enabled,
                'gateway'  => $gateway,
                'currency' => strtoupper( WpddbOptions::get_option( 'currency', 'wpddb_global_settings' ) ?: 'USD' ),
                'fee'      => (float) get_post_meta( get_the_ID(), 'wpddb_doctor_booking_fee', true ),
            ];
        }

        wp_localize_script( 'wpd-doc-booker-pro', 'docBookerProParams', $params );

    }
    /**
     * Register public scripts.
     *
     * @return void
     */
    public function register_public_scripts() {
        $this->get_public_assets();
        wp_deregister_style('wpd-doc-booker');
        // Register public styles.
        foreach ( $this->styles as $style ) {
            wp_register_style( $style['handle'], $style['src'], '', $this->version );
        }
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
            'handle' => 'wpdbp-photoswipe',
            'src'    => wpddbp()->get_assets_uri( 'public/css/vendor/photoswipe.css' ),
        ];
        $this->styles[] = [
            'handle' => 'wpdbp-photoswipe-default-skin',
            'src'    => wpddbp()->get_assets_uri( 'public/css/vendor/photoswipe.default.skin.css' ),
        ];
        $this->styles[] = [
            'handle' => 'wpdbp-swiper',
            'src'    => wpddbp()->get_assets_uri( 'public/css/vendor/swiper.min.css' ),
        ];
        $this->styles[] = [
            'handle' => 'wpd-doc-booker-pro',
            'src'    => wpddbp()->get_assets_uri( 'public/css/wpd-doc-booker-pro.css' ),
        ];


        return $this;
    }
    /**
     * Get public styles.
     *
     * @return object
     */
    private function get_public_scripts() {

        $this->scripts[] = [
            'handle' => 'wpdbp-photoswipe',
            'src'    => wpddbp()->get_assets_uri( 'public/js/vendor/photoswipe.min.js' ),
            'deps'   => ['jquery'],
            'footer' => true,
        ];
        $this->scripts[] = [
            'handle' => 'wpdbp-photoswipe-ui',
            'src'    => wpddbp()->get_assets_uri( 'public/js/vendor/photoswipe.ui.min.js' ),
            'deps'   => ['wpdbp-photoswipe'],
            'footer' => true,
        ];
        $this->scripts[] = [
            'handle' => 'wpd-doc-booker-pro',
            'src'    => wpddbp()->get_assets_uri( 'public/js/wpd-doc-booker-pro.js' ),
            'deps'   => [ 'jquery','wpdbp-photoswipe' ],
            'footer' => true,
        ];


        return $this;
    }
    public function load_swiper() {
        $default_swiper_handle = 'swiper';
        $default_swiper_path   = wpddbp()->get_assets_uri( 'public/js/vendor/swiper.min.js' );
        if ( defined( 'ELEMENTOR_ASSETS_PATH' ) ) {
            $is_swiper8_enable = get_option( 'elementor_experiment-e_swiper_latest' );

            if ( $is_swiper8_enable == 'active' ) {
                $el_swiper_path = 'lib/swiper/v8/swiper.min.js';
            } else {
                $el_swiper_path = 'lib/swiper/swiper.min.js';
            }

            $elementor_swiper_path = ELEMENTOR_ASSETS_PATH . $el_swiper_path;

            if ( file_exists( $elementor_swiper_path ) ) {
                $default_swiper_path = ELEMENTOR_ASSETS_URL . $el_swiper_path;
            }
        }
        wp_register_script( $default_swiper_handle, $default_swiper_path, array( 'jquery' ), $this->version, true );
        wp_enqueue_script( $default_swiper_handle );
    }

    /**
     * On right-to-left sites, swap each of this plugin's stylesheets for the
     * mirrored <name>-rtl.css the build writes next to it. Styles are enqueued
     * in many places (shortcodes, dashboards, admin screens), so this looks at
     * whatever is registered just before printing; a stylesheet without an RTL
     * file is left alone.
     */
    public function rtl_styles() {
        if ( ! is_rtl() ) {
            return;
        }
        // Plugin stylesheets live in assets/ (laravel-mix) and build/ (blocks).
        $roots = [
            wpddbp()->get_assets_uri( '' )                => WPDDBP_PATH . 'assets/',
            trailingslashit( WPDDBP_URL ) . 'build/'      => WPDDBP_PATH . 'build/',
        ];
        foreach ( wp_styles()->registered as $handle => $style ) {
            $src = is_string( $style->src ) ? $style->src : '';
            if ( '' === $src || ! preg_match( '/(?<!-rtl)\.css$/', $src ) ) {
                continue;
            }
            foreach ( $roots as $url_prefix => $path_prefix ) {
                if ( 0 !== strpos( $src, $url_prefix ) ) {
                    continue;
                }
                $rtl_file = $path_prefix . preg_replace( '/\.css$/', '-rtl.css', substr( $src, strlen( $url_prefix ) ) );
                if ( file_exists( $rtl_file ) ) {
                    wp_style_add_data( $handle, 'rtl', 'replace' );
                    // WordPress tags block styles with a ".min" suffix when
                    // SCRIPT_DEBUG is off and then looks for "-rtl.min.css";
                    // these files are not minified-named, so clear it.
                    $suffix = $style->extra['suffix'] ?? '';
                    if ( '' !== $suffix && false === strpos( $src, $suffix . '.css' ) ) {
                        wp_style_add_data( $handle, 'suffix', '' );
                    }
                }
                break;
            }
        }
    }

}
