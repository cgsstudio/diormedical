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
    const MINIMUM_WPDDB_VERSION = '2.0.0';

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
        if ( ! is_plugin_active( $docbooker ) || ! function_exists( 'wpddb' ) ) {
            if ( $this->is_plugins_installed( $docbooker ) ) {
                $activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $docbooker . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $docbooker );
                $message        = sprintf(
                    '<strong>%s</strong> requires <strong>DocBooker</strong> plugin to be active. Please activate DocBooker to continue.',
                    esc_html( self::PLUGIN_NAME ),
                );
                $button_text    = 'Activate DocBooker';

            } else {
                $activation_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=doc-booker' ), 'install-plugin_doc-booker' );
                $message        = sprintf(
                    '<strong>%s</strong> requires <strong>DocBooker</strong> plugin to be installed and activated. Please install DocBooker to continue.',
                    esc_html( self::PLUGIN_NAME ),
                );
                $button_text    = 'Install DocBooker';
            }
            $this->missing['doc-booker'] = [
                'name'       => 'DocBooker',
                'slug'       => 'doc-booker',
                'file_name'  => $docbooker,
                'url'        => $activation_url,
                'message'    => $message,
                'button_txt' => $button_text,
            ];
        } elseif ( defined( 'WPDDB_VERSION' ) && version_compare( WPDDB_VERSION, self::MINIMUM_WPDDB_VERSION, '<' ) ) {
            $activation_url = esc_url(
                add_query_arg(
                    [
                        'tab'       => 'plugin-information',
                        'plugin'    => 'doc-booker',
                        'TB_iframe' => 'true',
                        'width'     => '640',
                        'height'    => '500',
                    ],
                    admin_url( 'plugin-install.php' )
                )
            );
            $message        = wp_kses(
                sprintf(
                    '<strong>DocBooker Pro</strong> is enabled but not effective. It is not compatible with <span class="is-uninstallable second plugin-version-author-uri"> <a class="thickbox open-plugin-details-modal" aria-label="More information about DocBooker" data-title="DocBooker" href="%1$s" >DocBooker</a> </span> versions prior %2$s.',
                    $activation_url,
                    self::MINIMUM_WPDDB_VERSION
                ),
                [
                    'strong' => [],
                    'a'      => [
                        'href'  => true,
                        'class' => true,
                    ],
                    'span'   => [ 'class' => true ],
                ]
            );

            $button_text                  = 'Update DocBooker';
            $this->missing['doc-booker'] = [
                'name'       => 'DocBooker',
                'slug'       => 'doc-booker',
                'file_name'  => $docbooker,
                'url'        => $activation_url,
                'message'    => $message,
                'button_txt' => $button_text,
            ];
            $this->allOk                  = false;
        }
		elseif ( (! defined( 'WPD_FL__DEV_MODE' ) || ! WPD_FL__DEV_MODE ) && !dp_fs()->can_use_premium_code()){
            $activation_url = admin_url('admin.php?page=docbooker-pro');
            $message        = wp_kses(
                sprintf(
                    '<strong>DocBooker Pro</strong> is enabled but not effective. Please activate your license to use the pro features. Go to <a href="%1$s">License Activation</a> page to activate your license.',
                    $activation_url
                ),
                [
                    'strong' => [],
                    'a'      => [
                        'href'  => true,
                        'class' => true,
                    ],
                ]
            );

            $button_text                  = 'Activate License';
            $this->missing['doc-booker-pro'] = [
                'name'       => 'DocBooker Pro',
                'slug'       => 'doc-booker-pro',
                'file_name'  => WPDDBP_BASENAME,
                'url'        => $activation_url,
                'message'    => $message,
                'button_txt' => $button_text,
            ];
            $this->allOk                  = false;
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
            esc_html( '"%1$s" requires "%2$s" version %3$s or greater.' ),
            '<strong>DocBooker</strong>',
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
                $button = '<p><a href="' . esc_url( $plugin['url'] ) . '" class="button-primary">' . esc_html( $plugin['button_txt'] ) . '</a></p>';
                // $plugin['message'] Already used escaping function.
                printf( '<div class="error"><p>%1$s</p>%2$s</div>', $plugin['message'], $button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                $missingPlugins .= '<strong>' . esc_html( $plugin['name'] ) . '</strong>' . $sep;
            }
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