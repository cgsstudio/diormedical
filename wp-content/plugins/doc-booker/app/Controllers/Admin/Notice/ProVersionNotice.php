<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Notice;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Tells admins when DocBooker Pro is active but older than WPDDB_MIN_PRO_VERSION.
 * DocBooker then keeps that Pro from starting (DocBooker::stop_outdated_pro())
 * and has_pro() reports no Pro, so its features stay off until it is updated.
 */
class ProVersionNotice {
    use SingletonTrait;

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'maybe_render' ] );
    }

    public function maybe_render() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        $version = wpddb()->outdated_pro_version();
        if ( '' === $version ) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong></p><p>%2$s</p><p><a href="%3$s" class="button button-primary">%4$s</a></p></div>',
            esc_html__( 'DocBooker Pro needs an update', 'doc-booker' ),
            esc_html(
                sprintf(
                    /* translators: 1: lowest DocBooker Pro version that works, 2: the installed DocBooker Pro version */
                    __( 'This version of DocBooker works with DocBooker Pro %1$s or later, and you have %2$s. Pro features are switched off until you update it: go to Plugins and click "Update now" under DocBooker Pro.', 'doc-booker' ),
                    WPDDB_MIN_PRO_VERSION,
                    $version
                )
            ),
            esc_url( self_admin_url( 'plugins.php' ) ),
            esc_html__( 'Open Plugins', 'doc-booker' )
        );
    }
}
