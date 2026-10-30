<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\BookingInbox;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The DocBooker app: Dashboard, Bookings and Settings on one screen with its
 * own menu, routed by the address hash (#/bookings, #/settings/doctors).
 *
 * The old Settings and Booking Management screens stay registered and keep
 * working by their address; only their menu items are hidden. Settings saved
 * here go through the old screen's save handler into the same options.
 */
class AppPage {

	/** Add-ons check this before moving their screens into the app. */
	const PAGES_API = 1;

	use SingletonTrait;

	const SLUG   = 'wpddb-app';
	const HANDLE = 'wpddb-app';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register' ], 9 );
		// After every other item (Pro adds its own at the default priority).
		add_action( 'admin_menu', [ $this, 'arrange_menu' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'current_screen', [ $this, 'quiet_notices' ] );
		add_action( 'current_screen', [ $this, 'hidden_page_title' ] );
	}

	/**
	 * The base address of the app.
	 *
	 * @param string $route e.g. '/bookings' or '/settings/general'.
	 * @return string
	 */
	public static function url( $route = '' ) {
		return admin_url( 'edit.php?post_type=' . wpddb()->post_type_doctor . '&page=' . self::SLUG ) . ( $route ? '#' . $route : '' );
	}

	public function register() {
		$parent = 'edit.php?post_type=' . wpddb()->post_type_doctor;
		$cap    = current_user_can( 'wpddb_manage_booking_system' ) ? 'wpddb_manage_booking_system' : 'manage_options';
		// One menu item: Bookings and Settings open from the app's own sidebar.
		add_submenu_page( $parent, esc_html__( 'Dashboard', 'doc-booker' ), esc_html__( 'Dashboard', 'doc-booker' ), $cap, self::SLUG, [ $this, 'render' ], 0 );
	}

	/**
	 * The old Settings and Booking Management items leave the menu (their
	 * pages still open by address); Settings opens from the app's own sidebar.
	 * The DocBooker menu shows how many bookings are new.
	 */
	public function arrange_menu() {
		global $menu;
		$parent = 'edit.php?post_type=' . wpddb()->post_type_doctor;

		remove_submenu_page( $parent, 'settings' );
		remove_submenu_page( $parent, 'booking-management' );

		$count = current_user_can( 'wpddb_manage_booking_system' ) ? BookingInbox::unseen_count() : 0;
		if ( $count && is_array( $menu ) ) {
			foreach ( $menu as $i => $item ) {
				if ( isset( $item[2] ) && $item[2] === $parent ) {
					$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					break;
				}
			}
		}
	}

	/**
	 * The old Settings and Booking Management pages are out of the menu, so
	 * WordPress cannot find their title; give it, or the page header gets none.
	 */
	public function hidden_page_title() {
		global $title, $plugin_page;
		$titles = [
			'settings'           => __( 'Settings', 'doc-booker' ),
			'booking-management' => __( 'Booking Management', 'doc-booker' ),
		];
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $title ) && isset( $titles[ $plugin_page ] ) && wpddb()->post_type_doctor === $type ) {
			$title = $titles[ $plugin_page ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Pages an add-on adds to the app (sidebar tab + screen), from the
	 * `wpddb_app_pages` filter. Each: key, label, icon, title, subtitle.
	 * The add-on draws the screen itself (see `wpddb_app_enqueue`).
	 *
	 * @return array
	 */
	public static function extra_pages() {
		$out = [];
		foreach ( (array) apply_filters( 'wpddb_app_pages', [] ) as $p ) {
			$key = isset( $p['key'] ) ? sanitize_key( $p['key'] ) : '';
			if ( ! $key || in_array( $key, [ 'dashboard', 'bookings', 'settings' ], true ) ) {
				continue;
			}
			$out[] = [
				'key'      => $key,
				'label'    => isset( $p['label'] ) ? wp_strip_all_tags( $p['label'] ) : $key,
				'title'    => isset( $p['title'] ) ? wp_strip_all_tags( $p['title'] ) : '',
				'subtitle' => isset( $p['subtitle'] ) ? wp_strip_all_tags( $p['subtitle'] ) : '',
				'icon'     => isset( $p['icon'] ) ? sanitize_key( $p['icon'] ) : 'file',
			];
		}
		return $out;
	}

	public function render() {
		echo '<div id="wpddb_app_root" class="wpdba-root"></div>';
	}

	/**
	 * The app has its own notices; other plugins' admin notices would land
	 * on top of the header.
	 *
	 * @param \WP_Screen $screen
	 */
	public function quiet_notices( $screen ) {
		if ( ! $screen || false === strpos( (string) $screen->id, self::SLUG ) ) {
			return;
		}
		$clear = static function () {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
		};
		$clear();
		add_action( 'in_admin_header', $clear, PHP_INT_MAX );
	}

	/**
	 * @param string $hook
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		$js  = 'assets/admin/js/wpddb-app.js';
		$css = 'assets/admin/css/wpddb-app.css';
		// File time as the version, so a new build is never served from cache.
		$ver = static function ( $file ) {
			$path = WPDDB_PATH . $file;
			return file_exists( $path ) ? (string) filemtime( $path ) : WPDDB_VERSION;
		};
		wp_enqueue_style( self::HANDLE, wpddb()->plugin_url() . '/' . $css, [], $ver( $css ) );
		wp_enqueue_script( self::HANDLE, wpddb()->plugin_url() . '/' . $js, [ 'wp-i18n', 'jquery' ], $ver( $js ), true );
		wp_add_inline_style( self::HANDLE, self::accent_css( Helper::get_primary_color() ) );
		wp_set_script_translations( self::HANDLE, 'doc-booker', WPDDB_PATH . 'languages' );
		wp_localize_script( self::HANDLE, 'wpddbAppParams', $this->params() );
		// An add-on loads the script that draws its pages here.
		do_action( 'wpddb_app_enqueue', self::HANDLE );
	}

	/**
	 * The app's accent follows the Main colour setting. Also gives the text
	 * colour for buttons in that colour, and a version of it dark enough to
	 * read as text on white. src/js/admin/app/accent.js does the same after a save.
	 *
	 * @param string $color Any colour the style settings accept.
	 * @return string
	 */
	public static function accent_css( $color ) {
		$rgb = self::rgb( $color ) ?: [ 93, 61, 253 ];
		$lum = static function ( $c ) {
			$f = static function ( $v ) {
				$v /= 255;
				return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
			};
			return 0.2126 * $f( $c[0] ) + 0.7152 * $f( $c[1] ) + 0.0722 * $f( $c[2] );
		};
		$hex = static function ( $c ) {
			return sprintf( '#%02x%02x%02x', ...array_map( 'intval', array_map( 'round', $c ) ) );
		};
		$l = $lum( $rgb );
		// White or dark text on the colour: whichever stands out more.
		$ink = ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.0589 ) ? '#ffffff' : '#0f172a';
		// As text on white it needs 4.5:1; darken step by step until it has it.
		$text = $rgb;
		for ( $i = 0; $i < 12 && $lum( $text ) > 0.183; $i++ ) {
			$text = array_map( static function ( $v ) {
				return $v * 0.85;
			}, $text );
		}
		return sprintf( '.wpdba-root{--wpdba-accent:%s;--wpdba-accent-ink:%s;--wpdba-accent-text:%s}', $hex( $rgb ), $ink, $hex( $text ) );
	}

	/**
	 * @param string $color #rgb, #rrggbb, #rrggbbaa or rgb()/rgba().
	 * @return int[]|null
	 */
	private static function rgb( $color ) {
		$c = strtolower( trim( (string) $color ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $c, $m ) ) {
			return [ hexdec( $m[1] . $m[1] ), hexdec( $m[2] . $m[2] ), hexdec( $m[3] . $m[3] ) ];
		}
		if ( preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})(?:[0-9a-f]{2})?$/', $c, $m ) ) {
			return [ hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) ];
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/', $c, $m ) ) {
			return [ min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) ];
		}
		return null;
	}

	/**
	 * Everything the app needs on first paint.
	 *
	 * @return array
	 */
	private function params() {
		$user     = wp_get_current_user();
		$name     = $user ? $user->display_name : '';
		$initials = strtoupper( implode( '', array_map( static function ( $w ) {
			return mb_substr( $w, 0, 1 );
		}, array_slice( preg_split( '/\s+/', trim( $name ) ) ?: [], 0, 2 ) ) ) );
		$doctor   = wpddb()->post_type_doctor;
		$global   = get_option( 'wpddb_global_settings', [] );

		return [
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'restApiUrl'      => esc_url_raw( rest_url() ),
			'rest_nonce'      => wp_create_nonce( 'wp_rest' ),
			wpddb()->nonceId  => wp_create_nonce( wpddb()->nonceText ),
			'plugin_file_url' => wpddb()->plugin_url(),
			'has_pro'         => wpddb()->has_pro(),
			// Pro is on the site, even if switched off (outdated, licence): no sales prompts then.
			'pro_installed'   => function_exists( 'wpddbp' ),
			'caps'            => [
				'bookings' => current_user_can( 'wpddb_manage_booking_system' ),
				'settings' => current_user_can( 'manage_options' ),
			],
			'site'            => array_merge( Helper::site_clock(), [
				'dateFormat' => get_option( 'date_format' ),
				'timeFormat' => get_option( 'time_format' ),
				'name'       => get_bloginfo( 'name' ),
			] ),
			// The old screen reads the currency only when Pro is active; the
			// app shows what is really saved.
			'globalSettings'  => is_array( $global ) ? $global : [],
			'doctor_layout'   => Helper::doctor_page_layout(),
			'clinic_layout'   => Helper::clinic_page_layout(),
			'unseen'          => BookingInbox::unseen_count(),
			'extraPages'      => self::extra_pages(),
			// Settings ("group.key") that add or remove pages: saving a change
			// to one reloads the app so its menu matches.
			'reloadOn'        => array_values( array_map( 'strval', (array) apply_filters( 'wpddb_app_reload_on', [] ) ) ),
			'user'            => [ 'name' => $name, 'initials' => $initials ?: 'A' ],
			'urls'            => [
				'app'         => self::url(),
				'doctors'     => admin_url( 'edit.php?post_type=' . $doctor ),
				'departments' => admin_url( 'edit-tags.php?taxonomy=' . wpddb()->doctor_category . '&post_type=' . $doctor ),
				'clinics'     => admin_url( 'edit.php?post_type=' . wpddb()->post_type_clinic ),
				'oldSettings' => admin_url( 'edit.php?post_type=' . $doctor . '&page=settings' ),
				'site'        => home_url( '/' ),
				'docs'        => 'https://docbooker.wpdreamers.com/documentation/',
				'pro'         => 'https://docbooker.wpdreamers.com/',
				'support'     => 'https://wordpress.org/support/plugin/doc-booker/',
			],
		];
	}
}
