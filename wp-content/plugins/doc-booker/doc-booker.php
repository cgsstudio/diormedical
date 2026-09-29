<?php
/**
 * @wordpress-plugin
 * Plugin Name: DocBooker - Doctor Appointment & Hospital Management
 * Plugin URI: https://docbooker.wpdreamers.com/
 * Description: The Doctor Booking & Hospital Management System.
 * Version: 2.1.1
 * Author: Wp Dreamers
 * Author URI: https://wpdreamers.com/
 * Text Domain: doc-booker
 * Domain Path: /languages
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * @package WpDreamers\WPDDB
 */

// Do not allow directly accessing this file.
use WpDreamers\WPDDB\DocBooker;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}


define( 'WPDDB_VERSION', '2.1.1' );
define( 'WPDDB_DB_VERSION', '1.9.0' );

define( 'WPDDB_FILE', __FILE__ );

define( 'WPDDB_BASENAME', plugin_basename( __FILE__ ) );

define( 'WPDDB_URL', plugins_url( '', WPDDB_FILE ) );

define( 'WPDDB_ABSPATH', dirname( WPDDB_FILE ) );

define( 'WPDDB_PATH', plugin_dir_path( __FILE__ ) );

require_once WPDDB_PATH . 'vendor/autoload.php';

function wpddb() {
    static $cached_instance;
    if ( null !== $cached_instance ) {
        return $cached_instance;
    }
    $cached_instance = DocBooker::instance();
    return $cached_instance;
}

wpddb();
