<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class PluginRow{
    /**
     * Singleton Trait.
     */
    use SingletonTrait;

    /**
     * Text domain
     *
     * @var string
     */
    public string $textdomain = 'doc-booker';
    /**
     * Construct function
     */
    private function __construct() {
        add_filter( 'plugin_action_links_' . plugin_basename( WPDDB_FILE ), [ $this, 'plugins_setting_links' ] );
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
        // Deactivation feedback survey lives in its own controller: see PluginSurvey.
    }
    /**
     * @param array $links default plugin action link.
     *
     * @return array [array] plugin action link
     */
    public function plugins_setting_links( $links ) {
        $new_links   = [];
        $new_links[] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpddb_doctor&page=settings' ) ) . '">' . esc_html__( 'Settings', 'doc-booker' ) . '</a>';
        $new_links[] = '<a target="_blank" href="' . esc_url( 'https://docbooker.wpdreamers.com/doctor-booking/' ) . '">' . esc_html__( 'Demo', 'doc-booker' ) . '</a>';
        $new_links[] = '<a target="_blank" href="' . esc_url( 'https://docbooker.wpdreamers.com/documentation/' ) . '">' . esc_html__( 'Documentation', 'doc-booker' ) . '</a>';

        // Only when Pro isn't installed at all; an outdated Pro gets an update notice instead.
        if ( ! function_exists( 'wpddbp' ) ) {
            $new_links[] = '<a style="font-weight: bold; color: #008000;" target="_blank" href="' . esc_url( 'https://docbooker.wpdreamers.com/' ) . '">' . esc_html__( 'Get Pro', 'doc-booker' ) . '</a>';
        }

        $links = array_merge( $new_links, $links );


        return $links;
    }
    /**
     * Plugin links row.
     *
     * @param array  $links Links.
     * @param string $file File.
     *
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {

        if ( WPDDB_BASENAME === $file ) {
            // Questions go to the WordPress.org forum, where other owners can find the answers too.
            $row_meta['support'] = sprintf(
                '<a target="_blank" href="%1$s">%2$s</a>',
                esc_url( 'https://wordpress.org/support/plugin/doc-booker/' ),
                esc_html__( 'Support', 'doc-booker' )
            );

            return array_merge( $links, $row_meta );
        }

        return (array) $links;
    }
}