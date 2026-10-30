<?php

namespace WpDreamers\WPDDBP\Controllers\TestCatalog;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class AdminMenu {
	use SingletonTrait;

	public const PAGE_SLUG = 'wpddb-test-items';

	public function __construct() {
		add_action( 'wpddb/add/more/submenu', [ $this, 'register_submenu' ], 11, 2 );
		add_action( 'in_admin_header', [ $this, 'suppress_notices' ], 1000 );
	}

	/**
	 * Suppress unrelated admin notices on the Test Items page so they don't get
	 * hoisted after the page <h1> and split the header. Only fires on this page.
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
		// With the DocBooker app able to hold it, the page lives there instead.
		if ( \WpDreamers\WPDDBP\Controllers\AppPages::in_app() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_submenu_page(
			$parent_menu_slug,
			esc_html__( 'Lab Test Catalog', 'doc-booker-pro' ),
			esc_html__( 'Lab Test Catalog', 'doc-booker-pro' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			56
		);
	}

	public function render_page() {
		echo '<div class="wrap"><div id="wpddb_pro_test_items_root"></div></div>';
	}
}
