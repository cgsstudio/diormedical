<?php

/**
 * @wordpress-plugin
 * Plugin Name: DocBooker Pro - Doctor Appointment & Hospital Management
 * Plugin URI:
 * Description: The Doctor Booking & Hospital Management System.
 * Version: 2.2.0
 * Update URI: https://api.freemius.com
 * Author: Wp Dreamers
 * Author URI: https://wpdreamers.com/
 * Text Domain: doc-booker-pro
 * Domain Path: /languages
 * License:
 * License URI:
 * @package WpDreamers\WPDDBPP
 */
// Do not allow directly accessing this file.
use WpDreamers\WPDDBP\DocBookerPro;
if ( !defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
define( 'WPDDBP_VERSION', '2.2.0' );
define( 'WPDDBP_DB_VERSION', '1.1.5' );
define( 'WPDDBP_FILE', __FILE__ );
define( 'WPDDBP_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPDDBP_URL', plugins_url( '', WPDDBP_FILE ) );
define( 'WPDDBP_ABSPATH', dirname( WPDDBP_FILE ) );
define( 'WPDDBP_PATH', plugin_dir_path( __FILE__ ) );
require_once WPDDBP_PATH . 'vendor/autoload.php';
if ( !function_exists( 'dp_fs' ) ) {
    function dp_fs() {
        global $dp_fs;
        if ( !isset( $dp_fs ) ) {
            $dp_fs = fs_dynamic_init( array(
                'id'                => '22086',
                'slug'              => 'docbooker-pro',
                'premium_slug'      => 'docbooker-pro',
                'type'              => 'plugin',
                'public_key'        => 'pk_e74d2da6707035e5b7955b37171d8',
                'is_premium'        => true,
                'is_premium_only'   => true,
                'has_addons'        => false,
                'has_paid_plans'    => true,
                'wp_org_gatekeeper' => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
                'menu'              => array(
                    'slug'    => 'edit.php?post_type=wpddb_doctor',
                    'support' => false,
                ),
                'is_live'           => true,
            ) );
        }
        return $dp_fs;
    }

    if ( !defined( 'WPD_FL__DEV_MODE' ) || !WPD_FL__DEV_MODE ) {
        dp_fs();
    }
    do_action( 'dp_fs_loaded' );
}
function wpddbp() {
    static $cached_instance;
    if ( null !== $cached_instance ) {
        return $cached_instance;
    }
    $cached_instance = DocBookerPro::instance();
    return $cached_instance;
}

wpddbp();
// Gutenberg blocks (separate bootstrap, compiled from blocks-src/ → build/).
// No-ops cleanly until the first `npm run build`, and self-gates on the free
// DocBooker plugin being active. Has zero impact on existing Pro features.
if ( file_exists( __DIR__ . '/build/blocks/bootstrap.php' ) ) {
    require_once __DIR__ . '/build/blocks/bootstrap.php';
}