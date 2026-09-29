<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Controllers\Admin\Notice\ProBlockNotice;
use WpDreamers\WPDDB\Controllers\Admin\Notice\ProNotice;
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
        ProNotice::instance();
        ProBlockNotice::instance();
        $this->remove_admin_notice();
    }

    public function remove_admin_notice() {
        add_action( 'in_admin_header',
            function () {
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