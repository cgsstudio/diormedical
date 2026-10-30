<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package    HIPAAtizer
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete the custom table created by the plugin.
 */
function hipaa_delete_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hipaatizer';
	$wpdb->query(sprintf(
		"DROP TABLE IF EXISTS `%s`",
		esc_sql($wpdb->prefix . 'hipaatizer')
	));
	}

if (current_user_can('delete_plugins')) {
    if (is_multisite()) {
        $sites = get_sites();
        foreach ($sites as $site) {
            try {
                switch_to_blog($site->blog_id);
                hipaa_delete_plugin();
            } finally {
                restore_current_blog();
            }
        }
    } else {
        hipaa_delete_plugin();
    }
}
