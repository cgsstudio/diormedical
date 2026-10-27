<?php
/**
 * Dependencies Class.
 *
 */

namespace WpDreamers\WPDDBP\Controllers;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Dependencies Class.
 */
class Dependencies {
    use SingletonTrait;

    /**
     * Minimum PHP version.
     *
     * @var string
     */
    const MINIMUM_PHP_VERSION = '7.4';

    /**
     * Minimum DocBooker version.
     *
     * @var string
     */
    const MINIMUM_WPDDB_VERSION = '2.2.0';

    /**
     * Plugin name.
     *
     * @var string
     */
    const PLUGIN_NAME = 'DocBooker Pro - Doctor Appointment & Hospital Management';

    /**
     * Missing dependencies.
     *
     * @var array
     */
    private $missing = [];

    /**
     * All dependencies ok.
     *
     * @var bool
     */
    private $allOk = true;

    /**
     * Check dependencies.
     *
     * @return bool
     */
    public function check() {
        if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'minimum_php_version' ] );
            $this->allOk = false;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists( 'wp_create_nonce' ) ) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }

        // Check DocBooker.
        // is_plugin_active() only checks the active_plugins option — it returns true even
        // if the plugin's main file is missing/empty and never actually defined wpddb().
        // The function_exists() probe verifies the runtime entry point actually loaded.
        $docbooker = 'doc-booker/doc-booker.php';
        // Only the kind of problem and its link are stored here: this runs on
        // plugins_loaded, too early to translate. The text is built when the
        // notice prints (see notice_text()).
        if ( ! is_plugin_active( $docbooker ) || ! function_exists( 'wpddb' ) ) {
            if ( $this->is_plugins_installed( $docbooker ) ) {
                $this->missing['doc-booker'] = [
                    'name' => 'DocBooker',
                    'kind' => 'activate',
                    'url'  => wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $docbooker . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $docbooker ),
                ];
            } else {
                $this->missing['doc-booker'] = [
                    'name' => 'DocBooker',
                    'kind' => 'install',
                    'url'  => wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=doc-booker' ), 'install-plugin_doc-booker' ),
                ];
            }
        } elseif ( defined( 'WPDDB_VERSION' ) && version_compare( WPDDB_VERSION, self::MINIMUM_WPDDB_VERSION, '<' ) ) {
            $this->missing['doc-booker'] = [
                'name' => 'DocBooker',
                'kind' => 'update',
                'url'  => add_query_arg(
                    [
                        'tab'       => 'plugin-information',
                        'plugin'    => 'doc-booker',
                        'TB_iframe' => 'true',
                        'width'     => '640',
                        'height'    => '500',
                    ],
                    admin_url( 'plugin-install.php' )
                ),
            ];
            $this->allOk = false;
        }
		elseif ( (! defined( 'WPD_FL__DEV_MODE' ) || ! WPD_FL__DEV_MODE ) && !dp_fs()->can_use_premium_code()){
            $this->missing['doc-booker-pro'] = [
                'name' => 'DocBooker Pro',
                'kind' => 'license',
                'url'  => admin_url( 'admin.php?page=docbooker-pro' ),
            ];
            $this->allOk = false;
        }

        if ( ! empty( $this->missing ) ) {
            add_action( 'admin_notices', [ $this, 'missing_plugins_warning' ] );
            $this->allOk = false;
        }


        return $this->allOk;
    }

    /**
     * Admin Notice For Required PHP Version
     */
    public function minimum_php_version() {
        if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            unset( $_GET['activate'] );
        }

        $message = sprintf(
        /* translators: 1: Plugin name 2: PHP 3: Required PHP version */
            esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'doc-booker-pro' ),
            '<strong>DocBooker Pro</strong>',
            '<strong>PHP</strong>',
            self::MINIMUM_PHP_VERSION
        );
        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Admin Notice For Missing Plugins
     *
     * @return void
     */
    public function missing_plugins_warning() {
        $missingPlugins = '';
        $counter        = 0;
        foreach ( $this->missing as $plugin ) {
            $counter++;
            if ( $counter == sizeof( $this->missing ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative, WordPress.PHP.YodaConditions.NotYoda
                $sep = '';
            } elseif ( $counter == sizeof( $this->missing ) - 1 ) {  // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative, WordPress.PHP.YodaConditions.NotYoda
                $sep = ' and ';
            } else {
                $sep = ', ';
            }
            if ( current_user_can( 'activate_plugins' ) ) {
                list( $message, $button_text ) = $this->notice_text( $plugin['kind'], $plugin['url'] );
                $button = '<p><a href="' . esc_url( $plugin['url'] ) . '" class="button-primary">' . esc_html( $button_text ) . '</a></p>';
                // $message is built from escaped parts in notice_text().
                printf( '<div class="error"><p>%1$s</p>%2$s</div>', $message, $button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                $missingPlugins .= '<strong>' . esc_html( $plugin['name'] ) . '</strong>' . $sep;
            }
        }
    }

    /**
     * The message (HTML) and button text for one dependency notice.
     *
     * @param string $kind activate | install | update | license.
     * @param string $url  The notice's action URL.
     *
     * @return array [ message HTML, button text ]
     */
    private function notice_text( $kind, $url ) {
        $pro  = '<strong>' . esc_html( self::PLUGIN_NAME ) . '</strong>';
        $free = '<strong>DocBooker</strong>';
        switch ( $kind ) {
            case 'activate':
                return [
                    /* translators: 1: this plugin's name, 2: "DocBooker" */
                    sprintf( esc_html__( '%1$s requires the %2$s plugin to be active. Please activate DocBooker to continue.', 'doc-booker-pro' ), $pro, $free ),
                    __( 'Activate DocBooker', 'doc-booker-pro' ),
                ];
            case 'install':
                return [
                    /* translators: 1: this plugin's name, 2: "DocBooker" */
                    sprintf( esc_html__( '%1$s requires the %2$s plugin to be installed and active. Please install DocBooker to continue.', 'doc-booker-pro' ), $pro, $free ),
                    __( 'Install DocBooker', 'doc-booker-pro' ),
                ];
            case 'update':
                $link = '<a class="thickbox open-plugin-details-modal" aria-label="' . esc_attr__( 'More information about DocBooker', 'doc-booker-pro' ) . '" data-title="DocBooker" href="' . esc_url( $url ) . '">DocBooker</a>';
                return [
                    /* translators: 1: "DocBooker Pro", 2: link to DocBooker, 3: minimum DocBooker version */
                    sprintf( esc_html__( '%1$s is enabled but not working: it needs %2$s version %3$s or later.', 'doc-booker-pro' ), '<strong>DocBooker Pro</strong>', $link, esc_html( self::MINIMUM_WPDDB_VERSION ) ),
                    __( 'Update DocBooker', 'doc-booker-pro' ),
                ];
            default: // license
                $link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'License Activation', 'doc-booker-pro' ) . '</a>';
                return [
                    /* translators: 1: "DocBooker Pro", 2: link to the License Activation page */
                    sprintf( esc_html__( '%1$s is enabled but not working until your license is active. Activate it on the %2$s page.', 'doc-booker-pro' ), '<strong>DocBooker Pro</strong>', $link ),
                    __( 'Activate License', 'doc-booker-pro' ),
                ];
        }
    }

    /**
     * Check if the plugin is installed
     *
     * @param string $plugin_file_path Plugin file path.
     *
     * @return bool
     */
    public function is_plugins_installed( $plugin_file_path = null ) {
        $installed_plugins_list = get_plugins();
        return isset( $installed_plugins_list[ $plugin_file_path ] );
    }
}