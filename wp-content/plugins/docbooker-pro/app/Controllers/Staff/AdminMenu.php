<?php
/**
 * Unified "Staff" admin submenu. Replaces the previous "Clinic Managers"
 * submenu — the React app inside handles both managers and receptionists with
 * a role tab filter.
 */

namespace WpDreamers\WPDDBP\Controllers\Staff;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class AdminMenu {
	use SingletonTrait;

	public const PAGE_SLUG = 'wpddb-staff';

	public function __construct() {
		add_action( 'wpddb/add/more/submenu', [ $this, 'register_submenu' ], 9, 2 );
		add_action( 'in_admin_header', [ $this, 'suppress_notices' ], 1000 );
	}

	/**
	 * Suppress unrelated admin notices so they don't push our header below the
	 * primary action button. Only fires on the Staff page.
	 */
	public function suppress_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( (string) $screen->id, self::PAGE_SLUG ) === false ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	public function register_submenu( $parent_menu_slug, $menu_capability ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_submenu_page(
			$parent_menu_slug,
			esc_html__( 'Staff', 'doc-booker-pro' ),
			esc_html__( 'Staff', 'doc-booker-pro' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			55
		);
	}

	public function render_page() {
		echo '<div class="wrap wpddbp-staff-wrap"><div id="wpddb_pro_staff_root"></div></div>';
	}

	/**
	 * Page available whenever either toggle is on.
	 */
	public static function is_enabled() {
		return StaffContext::is_multi_clinic_enabled() || StaffContext::is_receptionist_enabled();
	}
}
