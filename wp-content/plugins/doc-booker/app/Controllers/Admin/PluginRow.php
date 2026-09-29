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
        $demo_url    = 'https://wpdreamers.com/';
        $new_links[] = '<a href="' . admin_url( 'edit.php?post_type=wpddb_doctor&page=settings' ) . '">' . esc_html__( 'Settings', 'doc-booker' ) . '</a>';
        $new_links[] = '<a target="_blank" href="' . esc_url( $demo_url ) . '">' . esc_html__( 'Demo', 'doc-booker' ) . '</a>';
        $new_links[] = '<a target="_blank" href="' . esc_url( 'https://wpdreamers.com/' ) . '">' . esc_html__( 'Documentation', 'doc-booker' ) . '</a>';

        if(!wpddb()->has_pro()){
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
            $report_url         = 'https://wpdreamers.com/contact-us/';
            $row_meta['issues'] = sprintf(
                '%2$s <a target="_blank" href="%1$s"><span style="color: red">%3$s</span></a>',
                esc_url( $report_url ),
                esc_html__( 'Facing issue?', 'doc-booker' ),
                esc_html__( 'Please open a support ticket.', 'doc-booker' )
            );

            return array_merge( $links, $row_meta );
        }

        return (array) $links;
    }
}