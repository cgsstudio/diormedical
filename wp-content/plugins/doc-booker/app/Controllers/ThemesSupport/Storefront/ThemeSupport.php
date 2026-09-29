<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\Storefront;

use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ThemeSupport {

	use SingletonTrait;

	public function __construct() {
		add_filter( 'body_class', [ __CLASS__, 'strip_sidebar_body_class' ], 99 );
		add_action( 'wp', [ __CLASS__, 'maybe_remove_sidebar' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_inline_css' ], 30 );
	}

	private static function is_target_page() {
		return Doctor::is_doctor() || Clinic::is_clinic();
	}

	public static function strip_sidebar_body_class( $classes ) {
		if ( ! self::is_target_page() || ! is_array( $classes ) ) {
			return $classes;
		}
		return array_values( array_diff( $classes, [ 'right-sidebar', 'left-sidebar' ] ) );
	}

	public static function maybe_remove_sidebar() {
		if ( ! self::is_target_page() ) {
			return;
		}
		remove_action( 'storefront_sidebar', 'storefront_get_sidebar', 10 );
	}

	public static function enqueue_inline_css() {
		if ( ! self::is_target_page() ) {
			return;
		}

		$css = '
			.wpddb #primary.content-area { width: 100%; float: none; margin-right: 0; }
			.wpddb #secondary { display: none; }
		';

		wp_register_style( 'wpddb-storefront-support', false );
		wp_enqueue_style( 'wpddb-storefront-support' );
		wp_add_inline_style( 'wpddb-storefront-support', $css );
	}
}
