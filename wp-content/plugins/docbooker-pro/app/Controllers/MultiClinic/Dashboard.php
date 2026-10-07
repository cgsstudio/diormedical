<?php
/**
 * Multi-Clinic Manager — front-end dashboard page handler.
 *
 *  - On the page admin selected as the "Clinic Dashboard Page", swaps the
 *    page content with a React mount (<div id="wpddb-clinic-dashboard-root">).
 *  - Forces login if a non-cap visitor lands on the dashboard page.
 *  - Hides theme header/footer in fullscreen mode (CSS does the work; this class
 *    only ensures the mount + body class hooks exist).
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class Dashboard {
    use SingletonTrait;

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'guard_dashboard_page' ] );
        add_filter( 'the_content', [ $this, 'inject_dashboard_mount' ], 999 );
        add_filter( 'body_class', [ $this, 'add_body_class' ] );
        add_action( 'template_redirect', [ $this, 'suppress_admin_bar' ] );
    }

    /**
     * On the dashboard page, hide the WP admin bar and drop the inline
     * `html{margin-top:32px}` it bumps onto the page. The dashboard is a
     * self-contained app and the 32/46px gap above the shell is unwanted.
     */
    public function suppress_admin_bar() {
        if ( ! $this->is_dashboard_page() || ! AdminMenu::is_enabled() ) {
            return;
        }
        show_admin_bar( false );
        remove_action( 'wp_head', '_admin_bar_bump_cb' );
    }

    /**
     * Block anonymous / under-privileged visitors from the dashboard page.
     */
    public function guard_dashboard_page() {
        if ( ! $this->is_dashboard_page() ) {
            return;
        }
        if ( ! AdminMenu::is_enabled() ) {
            return; // feature off — page renders normally
        }
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( get_permalink() ) );
            exit;
        }
        // Logged in but no cap → bounce to the home page (or login could be
        // re-redirected loop). Keeping it explicit: if no cap, show the page
        // and let inject_dashboard_mount() render an inline message.
    }

    /**
     * On the dashboard page, replace any content with the React mount div so
     * the admin doesn't need to add a shortcode — just pick the page.
     */
    public function inject_dashboard_mount( $content ) {
        if ( ! $this->is_dashboard_page() || ! AdminMenu::is_enabled() ) {
            return $content;
        }
        // Allow themes / users to opt-out (e.g. they put their own mount div in)
        if ( strpos( $content, 'wpddb-clinic-dashboard-root' ) !== false ) {
            return $content;
        }
        if ( ! current_user_can( UserRole::CAPABILITY ) ) {
            return '<div class="wpddb-clinic-dashboard-message">'
                . esc_html__( 'You do not have permission to view the Clinic Dashboard. Please ask an administrator.', 'doc-booker-pro' )
                . '</div>';
        }
        return '<div id="wpddb-clinic-dashboard-root"></div>';
    }

    /**
     * Tag the body so our SCSS can scope the dashboard layout safely.
     */
    public function add_body_class( $classes ) {
        if ( $this->is_dashboard_page() && AdminMenu::is_enabled() ) {
            $classes[] = 'wpddb-clinic-dashboard-page';
        }
        return $classes;
    }

    /**
     * True when the currently rendering page id matches the configured dashboard page.
     */
    public function is_dashboard_page() {
        if ( ! is_singular( 'page' ) ) {
            return false;
        }
        $page_id = AdminMenu::get_dashboard_page_id();
        return $page_id > 0 && (int) get_the_ID() === $page_id;
    }
}
