<?php
/**
 * Multi-Clinic Manager — admin "Clinic Managers" submenu.
 *
 * Hooks into free's `wpddb/add/more/submenu` action so the page lives next
 * to free's existing Settings + Booking Management items. Only registered
 * when the toggle is ON and the current user is an administrator.
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class AdminMenu {
    use SingletonTrait;

    public const PAGE_SLUG = 'clinic-managers';

    public function __construct() {
        add_action( 'wpddb/add/more/submenu', [ $this, 'register_submenu' ], 10, 2 );
    }

    public function register_submenu( $parent_menu_slug, $menu_capability ) {
        // Replaced by the unified Staff submenu (Staff\AdminMenu). The page
        // class + REST API stay registered so existing /clinic-managers/*
        // calls keep working until the new Staff React app fully supersedes
        // the legacy bundle.
        return;
    }

    public function render_page() {
        echo '<div class="wrap"><div id="wpddb_pro_clinic_managers_root"></div></div>';
    }

    /**
     * True when the Multi-Clinic toggle is ON in wpddb_global_settings.
     */
    public static function is_enabled() {
        $settings = get_option( 'wpddb_global_settings', [] );
        return isset( $settings['multi_clinic_manage_enable'] ) && $settings['multi_clinic_manage_enable'] === 'on';
    }

    /**
     * Returns the WP page id selected as the front-end Clinic Dashboard, or 0.
     */
    public static function get_dashboard_page_id() {
        $page_settings = get_option( 'wpddb_page_settings', [] );
        return isset( $page_settings['clinic_dashboard'] ) ? (int) $page_settings['clinic_dashboard'] : 0;
    }
}
