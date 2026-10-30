<?php

/**
 * Fired during plugin activation
 *
 *
 * @package    HIPAAtizer
 * @subpackage HIPAAtizer/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    HIPAAtizer
 * @subpackage HIPAAtizer/includes
 * @author     Cappers
 */

class HIPAAtizer_Activator
{
    public static function activate( $network_wide = false ) {
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
            foreach ( $sites as $blog_id ) {
                self::create_table_for_blog( $blog_id, true );
            }
        } else {
            // Interactive single-site activation: pass null blog_id but flag as interactive.
            self::create_table_for_blog( null, true );
        }
    }

    public static function create_table_for_blog( $blog_id = null, $is_interactive_activation = false ) {
        global $wpdb;

        if ( $blog_id ) {
            $prefix = $wpdb->get_blog_prefix( $blog_id );
        } else {
            $prefix = is_multisite()
                ? $wpdb->get_blog_prefix( get_current_blog_id() )
                : $wpdb->prefix;
        }

        $table_name      = $prefix . 'hipaatizer';
        $charset_collate = $wpdb->get_charset_collate();

        try {
            if ( is_multisite() ) {
                $create_table_query = "CREATE TABLE IF NOT EXISTS $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    hipaatizer_id varchar(120) NOT NULL,
                    site_id varchar(120) NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;";
            } else {
                $create_table_query = "CREATE TABLE IF NOT EXISTS $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    hipaatizer_id varchar(120) NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;";
            }

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $create_table_query );

            if ( $wpdb->last_error ) {
                error_log( 'Database error: ' . $wpdb->last_error );
                throw new Exception( 'Database error: ' . $wpdb->last_error );
            }

        } catch ( Exception $e ) {
            $guid = wp_generate_uuid4();

            $error_data = array(
                'url'     => esc_url( get_site_url( $blog_id ) ),
                'error'   => sanitize_text_field( $e->getMessage() ),
                'type'    => 'plugin',
                'traceId' => $guid,
            );

            $api_url = HIPAATIZER_APP . '/logger/';
            wp_remote_post( $api_url, array(
                'method'  => 'POST',
                'body'    => wp_json_encode( $error_data ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 10,
                'blocking' => false,
            ) );

            // Only deactivate and die during interactive activation by an admin —
            // NEVER during the plugins_loaded upgrade-detection path. A failing
            // background upgrade must not surface wp_die() to a public visitor or
            // a cron/REST request.
            if ( $is_interactive_activation && defined( 'HIPAATIZER_BASE_PATH' ) ) {
                deactivate_plugins( plugin_basename( HIPAATIZER_BASE_PATH ) );

                $message = sprintf(
                    __( 'Plugin activation error: There was an issue activating the HIPAAtizer plugin. Please try reinstalling and reactivating the plugin. If the problem persists, contact HIPAAtizer Support at <a href="mailto:support@hipaatizer.com">support@hipaatizer.com</a> and provide the following error code: <strong>%s</strong><br><br><a href="%s/wp-admin/plugins.php">&lt; Back</a>', 'hipaatizer' ),
                    esc_html( $guid ),
                    esc_url( get_site_url() )
                );

                wp_die( $message );
            }
        }
    }
}
