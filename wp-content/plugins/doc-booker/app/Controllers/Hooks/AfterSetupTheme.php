<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\Hooks;

use WpDreamers\WPDDB\Controllers\Helper\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

class AfterSetupTheme{
    public static function template_functions() {
        self::addPluginSupport();
        add_action('template_redirect', [__CLASS__, 'template_redirect']);
    }

    public static function addPluginSupport() {
        global $_wp_theme_features;

        
        if( !isset($_wp_theme_features['add_theme_support']) ){
            add_theme_support('wpddb');
        }

        add_image_size( 'wpddb_size1', 1240, 720, true );

    }
    public static function template_redirect() {

        global $wp;

        if (!empty($_GET['page_id']) && '' === get_option('permalink_structure') && Helper::get_page_id('doctors') === absint($_GET['page_id']) && get_post_type_archive_link(wpddb()->post_type_doctor)) { // WPCS: input var ok, CSRF ok.
            wp_safe_redirect(get_post_type_archive_link(wpddb()->post_type_doctor));

            exit;
        }
        if (!empty($_GET['page_id']) && '' === get_option('permalink_structure') && Helper::get_page_id('clinics') === absint($_GET['page_id']) && get_post_type_archive_link(wpddb()->post_type_clinic)) { // WPCS: input var ok, CSRF ok.
            wp_safe_redirect(get_post_type_archive_link(wpddb()->post_type_clinic));
            exit;
        }

    }
}