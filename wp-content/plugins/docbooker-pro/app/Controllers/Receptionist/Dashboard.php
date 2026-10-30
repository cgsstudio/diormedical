<?php
/**
 * Receptionist — front-end dashboard page handler.
 *
 * Same pattern as MultiClinic\Dashboard: swaps the configured page's content
 * for a React mount and gates by role. The Receptionist toggle gates
 * registration; if off, the page renders normally.
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class Dashboard {
	use SingletonTrait;

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'guard_dashboard_page' ] );
		add_filter( 'the_content',       [ $this, 'inject_dashboard_mount' ], 999 );
		add_filter( 'body_class',        [ $this, 'add_body_class' ] );
		add_action( 'template_redirect', [ $this, 'suppress_admin_bar' ] );
	}

	public static function is_enabled() {
		$settings = get_option( 'wpddb_global_settings', [] );
		return isset( $settings['receptionist_manage_enable'] ) && $settings['receptionist_manage_enable'] === 'on';
	}

	public static function get_dashboard_page_id() {
		$page_settings = get_option( 'wpddb_page_settings', [] );
		return isset( $page_settings['receptionist_dashboard'] ) ? (int) $page_settings['receptionist_dashboard'] : 0;
	}

	public function is_dashboard_page() {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$page_id = self::get_dashboard_page_id();
		return $page_id > 0 && (int) get_the_ID() === $page_id;
	}

	public function suppress_admin_bar() {
		if ( ! $this->is_dashboard_page() || ! self::is_enabled() ) {
			return;
		}
		show_admin_bar( false );
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
	}

	public function guard_dashboard_page() {
		if ( ! $this->is_dashboard_page() || ! self::is_enabled() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink() ) );
			exit;
		}
	}

	public function inject_dashboard_mount( $content ) {
		if ( ! $this->is_dashboard_page() || ! self::is_enabled() ) {
			return $content;
		}
		if ( strpos( $content, 'wpddb-receptionist-dashboard-root' ) !== false ) {
			return $content;
		}
		if ( ! current_user_can( UserRole::CAPABILITY ) ) {
			return '<div class="wpddb-clinic-dashboard-message">'
				. esc_html__( 'You do not have permission to view the Receptionist Dashboard. Please ask an administrator.', 'doc-booker-pro' )
				. '</div>';
		}
		return '<div id="wpddb-receptionist-dashboard-root"></div>';
	}

	public function add_body_class( $classes ) {
		if ( $this->is_dashboard_page() && self::is_enabled() ) {
			$classes[] = 'wpddb-receptionist-dashboard-page';
			$classes[] = 'wpddb-clinic-dashboard-page'; // share SCSS skin
		}
		return $classes;
	}
}
