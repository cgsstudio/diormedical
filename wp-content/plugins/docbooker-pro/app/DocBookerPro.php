<?php
namespace WpDreamers\WPDDBP;

use WpDreamers\WPDDBP\Controllers\Admin\Api\RestApi;
use WpDreamers\WPDDBP\Controllers\Admin\Notice\PaymentConfigNotice;
use WpDreamers\WPDDBP\Controllers\Payments\BookingPayment;
use WpDreamers\WPDDBP\Controllers\AjaxPagination;
use WpDreamers\WPDDBP\Controllers\Cron\ReportPurger;
use WpDreamers\WPDDBP\Controllers\DepartmentTermMeta;
use WpDreamers\WPDDBP\Controllers\Dependencies;
use WpDreamers\WPDDBP\Controllers\Hooks\ActionHooks;
use WpDreamers\WPDDBP\Controllers\Hooks\FilterHooks;
use WpDreamers\WPDDBP\Controllers\Installation;
use WpDreamers\WPDDBP\Controllers\MultiClinic\AdminMenu as MultiClinicAdminMenu;
use WpDreamers\WPDDBP\Controllers\MultiClinic\ClinicReportsApi;
use WpDreamers\WPDDBP\Controllers\MultiClinic\Dashboard as MultiClinicDashboard;
use WpDreamers\WPDDBP\Controllers\MultiClinic\LoginRedirect as MultiClinicLoginRedirect;
use WpDreamers\WPDDBP\Controllers\MultiClinic\RestApi as MultiClinicRestApi;
use WpDreamers\WPDDBP\Controllers\MultiClinic\Scoping as MultiClinicScoping;
use WpDreamers\WPDDBP\Controllers\PatientPortal\BookingSms as PortalBookingSms;
use WpDreamers\WPDDBP\Controllers\PatientPortal\Page as PortalPage;
use WpDreamers\WPDDBP\Controllers\PatientPortal\RestApi as PortalRestApi;
use WpDreamers\WPDDBP\Controllers\Receipt\Page as ReceiptPage;
use WpDreamers\WPDDBP\Controllers\Visits\BillPage as VisitBillPage;
use WpDreamers\WPDDBP\Controllers\Receptionist\Dashboard as ReceptionistDashboard;
use WpDreamers\WPDDBP\Controllers\Receptionist\LoginRedirect as ReceptionistLoginRedirect;
use WpDreamers\WPDDBP\Controllers\Receptionist\RestApi as ReceptionistRestApi;
use WpDreamers\WPDDBP\Controllers\Receptionist\Scoping as ReceptionistScoping;
use WpDreamers\WPDDBP\Controllers\ScriptsController;
use WpDreamers\WPDDBP\Controllers\Staff\AdminMenu as StaffAdminMenu;
use WpDreamers\WPDDBP\Controllers\Staff\Api\StaffRestApi;
use WpDreamers\WPDDBP\Controllers\Staff\LastLoginTracker as StaffLastLoginTracker;
use WpDreamers\WPDDBP\Controllers\StripeCSPOverride;
use WpDreamers\WPDDBP\Controllers\TestCatalog\AdminMenu as TestCatalogAdminMenu;
use WpDreamers\WPDDBP\Controllers\TestCatalog\RestApi as TestCatalogRestApi;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
final class DocBookerPro{
    /**
     * Singleton
     */
    use SingletonTrait;


    /**
     * Class Constructor
     */
    private function __construct() {

        add_action( 'plugins_loaded', [ $this, 'init' ], 999 );

        // Run idempotent migrations on every admin page load — catches WP
        // auto-updates that skip the activation hook.
        add_action( 'plugins_loaded', [ Installation::class, 'maybe_upgrade' ], 5 );

        add_action( 'init', [ $this, 'init_hooks' ] );
        register_activation_hook( WPDDBP_FILE, [ Installation::class, 'activate' ] );
        register_deactivation_hook( WPDDBP_FILE, [ Installation::class, 'deactivation' ] );

        // Deleting the plugin removes its data. When Freemius runs it owns the
        // plugin's WordPress uninstall hook, so listen to its after_uninstall
        // instead; registering our own would replace Freemius's.
        if ( isset( $GLOBALS['dp_fs'] ) && is_object( $GLOBALS['dp_fs'] ) ) {
            $GLOBALS['dp_fs']->add_action( 'after_uninstall', [ Installation::class, 'uninstall' ] );
        } else {
            register_uninstall_hook( WPDDBP_FILE, [ Installation::class, 'uninstall' ] );
        }

    }
    public function load_language() {
        // The path is relative to the plugins folder; an absolute one was never found.
        load_plugin_textdomain( 'doc-booker-pro', false, dirname( WPDDBP_BASENAME ) . '/languages' );
    }
    /**
     * Init
     *
     * @return void
     */
    public function init() {
        if ( ! Dependencies::instance()->check() ) {
            return;
        }
        ScriptsController::instance();
        RestApi::instance();
        BookingPayment::instance();
        PaymentConfigNotice::instance();
        ActionHooks::instance();
        FilterHooks::instance();
        AjaxPagination::instance();

        // Department taxonomy term-meta (icon + accent color) + archive hero (Phase 3).
        DepartmentTermMeta::instance();

        // Multi-Clinic Manager (Pro feature). The submenu + dashboard
        // self-gate on the toggle, so it's safe to instantiate unconditionally.
        MultiClinicAdminMenu::instance();
        MultiClinicRestApi::instance();
        MultiClinicDashboard::instance();
        MultiClinicLoginRedirect::instance();
        MultiClinicScoping::instance();
        ClinicReportsApi::instance();

        // Unified Staff admin page + API.
        StaffAdminMenu::instance();
        StaffRestApi::instance();
        StaffLastLoginTracker::instance();

        // Test catalog (admin + clinic-admin REST).
        TestCatalogAdminMenu::instance();
        TestCatalogRestApi::instance();

        // Staff and Lab Test Catalog as pages of the DocBooker app.
        \WpDreamers\WPDDBP\Controllers\AppPages::instance();

        // Receptionist role + dashboard + REST.
        ReceptionistDashboard::instance();
        ReceptionistLoginRedirect::instance();
        ReceptionistRestApi::instance();
        ReceptionistScoping::instance();

        // Patient online-report portal.
        PortalPage::instance();
        PortalRestApi::instance();
        PortalBookingSms::instance();

        // Printable receipt + visit bill/invoice routes.
        ReceiptPage::instance();
        VisitBillPage::instance();

        // Daily retention cron.
        ReportPurger::instance();

        /**
         * Pro passed its checks (DocBooker version, licence) and is running.
         * The free plugin's has_pro() and the blocks wait for this, so a Pro
         * that switched itself off counts as not installed.
         */
        do_action( 'wpddbp_loaded' );
    }
    public function init_hooks() {
        $this->load_language();

    }
    /**
     * Assets url generate with given assets file
     *
     * @param string $file File.
     *
     * @return string
     */
    public function get_assets_uri( $file ) {
        $file = ltrim( $file, '/' );
        return trailingslashit( WPDDBP_URL . '/assets' ) . $file;
    }

    /**
     * Get the template path.
     *
     * @return string
     */
    public function get_template_path() {
        return apply_filters( 'wpddbp_template_path', 'doc-booker-pro/' );
    }
    /**
     * et the plugin templates path.
     *
     * @return string
     */
    public function get_plugin_template_path() {
        return $this->plugin_path() . '/templates/';
    }

    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( WPDDBP_FILE ) );
    }
    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit( WPDDBP_URL );
    }

}
