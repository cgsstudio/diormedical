<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Notice;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Warns admins when published content still uses DocBooker Pro blocks while the
 * DocBooker Pro plugin is inactive.
 *
 * In that state the Pro blocks (Booking Form, etc.) render as nothing — the front
 * end silently loses its booking/payment UI. This commonly happens after a plugin
 * redeploy/rebuild that momentarily removes the Pro main file: WordPress then
 * auto-deactivates it silently (no `recently_activated` trace). Reactivating Pro
 * clears the notice.
 */
class ProBlockNotice {
    use SingletonTrait;

    const CACHE_KEY = 'wpddb_pro_blocks_in_content';

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'maybe_render' ] );
        // Keep the cached content scan fresh when plugins toggle or content is saved.
        add_action( 'activated_plugin', [ $this, 'flush_cache' ] );
        add_action( 'deactivated_plugin', [ $this, 'flush_cache' ] );
        add_action( 'save_post', [ $this, 'flush_cache' ] );
    }

    public function flush_cache() {
        delete_transient( self::CACHE_KEY );
    }

    public function maybe_render() {
        // Pro active ⇒ blocks render fine; nothing to warn about (also short-circuits
        // the DB scan below in the normal case).
        if ( function_exists( 'wpddbp' ) ) {
            return;
        }
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        if ( ! $this->has_pro_block_content() ) {
            return;
        }

        $pro_file  = 'doc-booker-pro/doc-booker-pro.php';
        $installed = file_exists( WP_PLUGIN_DIR . '/' . $pro_file );

        if ( $installed ) {
            $url    = wp_nonce_url(
                self_admin_url( 'plugins.php?action=activate&plugin=' . $pro_file . '&plugin_status=all' ),
                'activate-plugin_' . $pro_file
            );
            $action = '<a href="' . esc_url( $url ) . '" class="button button-primary">'
                . esc_html__( 'Activate DocBooker Pro', 'doc-booker' ) . '</a>';
        } else {
            $action = '<a href="' . esc_url( self_admin_url( 'plugins.php' ) ) . '" class="button">'
                . esc_html__( 'Open Plugins', 'doc-booker' ) . '</a>';
        }

        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong> — %2$s</p><p>%3$s</p></div>',
            esc_html__( 'DocBooker Pro is inactive', 'doc-booker' ),
            esc_html__( 'One or more published pages use DocBooker Pro blocks (e.g. the Booking Form). While Pro is inactive those blocks render as nothing, so booking and payment will not appear on the front end.', 'doc-booker' ),
            $action // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url/esc_html above.
        );
    }

    /**
     * Cached scan for published content containing a DocBooker Pro block comment
     * (`<!-- wp:doc-booker-pro/... -->`). Only ever runs while Pro is inactive.
     *
     * @return bool
     */
    private function has_pro_block_content() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return 'yes' === $cached;
        }
        global $wpdb;
        $found = (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_content LIKE '%wp:doc-booker-pro/%'"
        );
        set_transient( self::CACHE_KEY, $found ? 'yes' : 'no', 6 * HOUR_IN_SECONDS );
        return $found > 0;
    }
}
