<?php
/**
 * Staff and Lab Test Catalog as pages of the DocBooker app (Dashboard)
 * instead of separate WordPress submenu pages.
 *
 * The free plugin's app lists add-on pages from the `wpddb_app_pages` filter
 * and loads their script on `wpddb_app_enqueue`. With an older free plugin
 * that has no such slot, nothing here runs and the old submenu pages stay.
 */

namespace WpDreamers\WPDDBP\Controllers;

use WpDreamers\WPDDB\Controllers\Admin\AppPage;
use WpDreamers\WPDDBP\Controllers\Staff\AdminMenu as StaffAdminMenu;
use WpDreamers\WPDDBP\Controllers\Staff\StaffContext;
use WpDreamers\WPDDBP\Controllers\TestCatalog\AdminMenu as TestCatalogAdminMenu;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class AppPages {
	use SingletonTrait;

	public function __construct() {
		if ( ! self::in_app() ) {
			return;
		}
		add_filter( 'wpddb_app_pages', [ $this, 'pages' ] );
		add_filter( 'wpddb_app_reload_on', [ $this, 'reload_on' ] );
		add_action( 'wpddb_app_enqueue', [ $this, 'enqueue' ] );
		// WordPress refuses a page it has no menu item for before admin_init; this
		// fires at that moment, so old links can still be sent to the app.
		add_action( 'admin_page_access_denied', [ $this, 'redirect_old_pages' ] );
	}

	/**
	 * True when the free plugin's app can hold Pro's pages.
	 */
	public static function in_app() {
		return class_exists( AppPage::class ) && defined( AppPage::class . '::PAGES_API' );
	}

	/**
	 * The pages show while clinic managers or the front desk are switched on
	 * (Settings → Clinic Staff), for admins, as the old submenu did.
	 */
	public static function visible() {
		return current_user_can( 'manage_options' ) && StaffAdminMenu::is_enabled();
	}

	public function pages( $pages ) {
		if ( ! self::visible() ) {
			return $pages;
		}
		$pages[] = [
			'key'   => 'staff',
			'label' => __( 'Staff', 'doc-booker-pro' ),
			'icon'  => 'users',
		];
		$pages[] = [
			'key'   => 'lab-tests',
			'label' => __( 'Lab Test Catalog', 'doc-booker-pro' ),
			'icon'  => 'flask',
		];
		return $pages;
	}

	/**
	 * Turning staff logins on or off adds or removes the pages.
	 */
	public function reload_on( $keys ) {
		$keys[] = 'wpddb_global_settings.multi_clinic_manage_enable';
		$keys[] = 'wpddb_global_settings.receptionist_manage_enable';
		return $keys;
	}

	/**
	 * Loads the pages on the app screen, with the same settings the old
	 * Staff and Lab Test Catalog screens were given.
	 *
	 * @param string $app_handle The app's script handle.
	 */
	public function enqueue( $app_handle ) {
		if ( ! self::visible() ) {
			return;
		}
		$js  = 'assets/admin/js/wpd-app-pages.js';
		$css = 'assets/admin/css/wpd-app-pages.css';
		$ver = static function ( $file ) {
			$path = WPDDBP_PATH . $file;
			return file_exists( $path ) ? (string) filemtime( $path ) : WPDDBP_VERSION;
		};
		wp_enqueue_style( 'wpddb-pro-app-pages', wpddbp()->get_assets_uri( 'admin/css/wpd-app-pages.css' ), [ $app_handle ], $ver( $css ) );
		wp_enqueue_script( 'wpddb-pro-app-pages', wpddbp()->get_assets_uri( 'admin/js/wpd-app-pages.js' ), [ 'wp-i18n', $app_handle ], $ver( $js ), true );
		wp_set_script_translations( 'wpddb-pro-app-pages', 'doc-booker-pro', WPDDBP_PATH . 'languages' );
		$nonce = wp_create_nonce( 'wp_rest' );
		wp_localize_script( 'wpddb-pro-app-pages', 'wpddbProStaffParams', [
			'restApiUrl'           => esc_url_raw( rest_url() ),
			'rest_nonce'           => $nonce,
			'multi_clinic_enabled' => StaffContext::is_multi_clinic_enabled(),
			'receptionist_enabled' => StaffContext::is_receptionist_enabled(),
			'single_clinic_id'     => StaffContext::single_clinic_id_or_zero(),
		] );
		wp_localize_script( 'wpddb-pro-app-pages', 'wpddbProTestItemsParams', [
			'restApiUrl' => esc_url_raw( rest_url() ),
			'rest_nonce' => $nonce,
			'is_admin'   => current_user_can( 'manage_options' ),
			'clinics'    => current_user_can( 'manage_options' ) ? self::clinics_for_picker() : [],
		] );
	}

	/**
	 * Links and bookmarks to the old screens open the pages in the app.
	 */
	public function redirect_old_pages() {
		if ( wp_doing_ajax() || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$map  = [
			StaffAdminMenu::PAGE_SLUG       => '/staff',
			TestCatalogAdminMenu::PAGE_SLUG => '/lab-tests',
		];
		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $map[ $page ] ) && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( AppPage::url( $map[ $page ] ) );
			exit;
		}
	}

	/**
	 * Clinic list for the admin "Clinic" select: [ { value, label }, ... ].
	 *
	 * @return array
	 */
	private static function clinics_for_picker() {
		$posts = get_posts( [
			'post_type'      => wpddb()->post_type_clinic,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		return array_map( static function ( $p ) {
			return [
				'value' => (int) $p->ID,
				'label' => $p->post_title ?: ( '#' . $p->ID ),
			];
		}, $posts );
	}
}
