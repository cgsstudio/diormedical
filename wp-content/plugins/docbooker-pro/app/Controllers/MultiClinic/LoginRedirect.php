<?php
/**
 * Multi-Clinic Manager — login redirect.
 *
 * Sends users with the wpddb_clinic_manager role straight to the front-end
 * Clinic Dashboard page after login. Admins keep the default WP behavior.
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class LoginRedirect {
    use SingletonTrait;

    public function __construct() {
        add_filter( 'login_redirect', [ $this, 'redirect_clinic_manager' ], 10, 3 );
    }

    public function redirect_clinic_manager( $redirect_to, $requested_redirect, $user ) {
        if ( ! AdminMenu::is_enabled() ) {
            return $redirect_to;
        }
        if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
            return $redirect_to;
        }
        // Don't intercept admins — let them go to wp-admin as usual.
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return $redirect_to;
        }
        if ( ! in_array( UserRole::ROLE_SLUG, (array) $user->roles, true ) ) {
            return $redirect_to;
        }
        $page_id = AdminMenu::get_dashboard_page_id();
        if ( $page_id ) {
            return get_permalink( $page_id ) ?: $redirect_to;
        }
        return $redirect_to;
    }
}
