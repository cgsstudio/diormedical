<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Controllers\Admin\Notice\ProBlockNotice;
use WpDreamers\WPDDB\Controllers\Admin\Notice\ProNotice;
use WpDreamers\WPDDB\Controllers\Admin\Notice\ProVersionNotice;
use WpDreamers\WPDDB\Controllers\Admin\Notice\ReviewNotice;
use WpDreamers\WPDDB\Controllers\Admin\Setup\SetupChecklist;
use WpDreamers\WPDDB\Controllers\Admin\Setup\SetupWizard;
use WpDreamers\WPDDB\Controllers\Admin\Shortcode\ShortcodeInit;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class AdminInit{
    use SingletonTrait;
    public function __construct() {
        PluginRow::instance();
        PluginSurvey::instance();
        ShortcodeInit::instance();
		PostMeta::instance();
        SubMenu::instance();
        SetupWizard::instance();
        SetupChecklist::instance();
        ReviewNotice::instance();
        ProNotice::instance();
        ProBlockNotice::instance();
        ProVersionNotice::instance();
        $this->remove_admin_notice();
    }

    public function remove_admin_notice() {
        add_action( 'in_admin_header',
            function () {
                // Only on the plugin's own full-page screens (Settings, Booking
                // Management, templates, Pro's pages — all opened with ?page=).
                // The doctor/clinic list and edit screens keep their notices, so
                // WordPress errors and other plugins' warnings stay visible.
                if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    return;
                }
                $screen = get_current_screen();
                if ( ( ! empty( $screen->post_type )
                    && in_array( $screen->post_type, [
                        wpddb()->post_type_doctor,
                        wpddb()->post_type_clinic,
                        wpddb()->post_type_doctor_shortcode,
                        wpddb()->post_type_clinic_shortcode,
                    ] ) )
                ) {
                    remove_all_actions( 'admin_notices' );
                    remove_all_actions( 'all_admin_notices' );
                }
            }, 1000 );
    }
}