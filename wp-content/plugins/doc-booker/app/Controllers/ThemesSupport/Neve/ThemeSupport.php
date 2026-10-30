<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\Neve;

use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ThemeSupport {

	use SingletonTrait;

	public function __construct() {
		add_filter( 'neve_sidebar_position', [ __CLASS__, 'force_full_width' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_inline_css' ], 30 );
	}

	private static function is_target_page() {
		return Doctor::is_doctor() || Clinic::is_clinic();
	}

	public static function force_full_width( $position ) {
		if ( self::is_target_page() ) {
			return 'full-width';
		}
		return $position;
	}

	public static function enqueue_inline_css() {
		if ( ! self::is_target_page() ) {
			return;
		}

		$css = '
			.wpddb #primary.container { max-width: var(--container, 1170px); margin-left: auto; margin-right: auto; }
		';

		wp_register_style( 'wpddb-neve-support', false );
		wp_enqueue_style( 'wpddb-neve-support' );
		wp_add_inline_style( 'wpddb-neve-support', $css );
	}
}
