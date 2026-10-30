<?php

/**
 * Plugin Name:       HIPAAtizer
 * Plugin URI:        https://hipaatizer.com/free-developer-account
 * Description:       HIPAAtizer - Helps you create and manage HIPAA-Compliant web forms.
 * Version:           1.3.10
 * Author:            HIPAAtizer
 * Author URI:        https://hipaatizer.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       hipaatizer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}
/**
 * Define Constants
 */
if (!defined('HIPAATIZER_BASE_PATH')) {
	define('HIPAATIZER_BASE_PATH', __FILE__);
}

if (!defined('HIPAATIZER_PATH')) {
	define('HIPAATIZER_PATH', untrailingslashit(plugins_url('', HIPAATIZER_BASE_PATH)));
}

if (!defined('HIPAATIZER_PLUGIN_DIR')) {
	define('HIPAATIZER_PLUGIN_DIR', untrailingslashit(dirname(HIPAATIZER_BASE_PATH)));
}
if (!defined('HIPAATIZER_APP')) {
	define('HIPAATIZER_APP', 'https://app.hipaatizer.com');
}

/**
 * Currentl plugin version.
 */
define('HIPAATIZER_VERSION', '1.3.10');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hipaatizer-activator.php
 */
function activate_hipaatizer( $network_wide = false )
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-hipaatizer-activator.php';
	HIPAAtizer_Activator::activate( $network_wide );
}

// Ensure the DB table exists for the current blog on every load.
// Handles: upgrades from older versions, new subsites, and first-run after network activation.
function hipaatizer_maybe_create_table() {
	$installed = get_option( 'hipaatizer_db_version' );
	if ( $installed !== HIPAATIZER_VERSION ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-hipaatizer-activator.php';
		// Background upgrade path (fires on plugins_loaded after auto-update or
		// version bump). Must never call wp_die() or deactivate the plugin — a
		// public visitor or cron tick could be the request that triggers this.
		// Wrap in try/catch as a last line of defense in case anything inside
		// the activator throws beyond its own catch block.
		try {
			HIPAAtizer_Activator::create_table_for_blog( null, false );
			update_option( 'hipaatizer_db_version', HIPAATIZER_VERSION );
		} catch ( Exception $e ) {
			error_log( 'HIPAAtizer upgrade error: ' . $e->getMessage() );
		}
	}
}
add_action( 'plugins_loaded', 'hipaatizer_maybe_create_table' );

// Create the table when a new site is added to the network (WP 5.1+).
function hipaatizer_new_site( $site ) {
	if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-hipaatizer-activator.php';
		HIPAAtizer_Activator::create_table_for_blog( $site->id );
		update_blog_option( $site->id, 'hipaatizer_db_version', HIPAATIZER_VERSION );
	}
}
add_action( 'wp_initialize_site', 'hipaatizer_new_site', 10, 1 );

// Fallback for WordPress < 5.1.
function hipaatizer_new_blog( $blog_id ) {
	if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-hipaatizer-activator.php';
		HIPAAtizer_Activator::create_table_for_blog( $blog_id );
		update_blog_option( $blog_id, 'hipaatizer_db_version', HIPAATIZER_VERSION );
	}
}
add_action( 'wpmu_new_blog', 'hipaatizer_new_blog', 10, 1 );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hipaatizer-deactivator.php
 */
function deactivate_hipaatizer()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-hipaatizer-deactivator.php';
	HIPAAtizer_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hipaatizer');
register_deactivation_hook(__FILE__, 'deactivate_hipaatizer');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-hipaatizer.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 */
function run_hipaatizer()
{

	$plugin = new HIPAAtizer();
	$plugin->run();
}
run_hipaatizer();
