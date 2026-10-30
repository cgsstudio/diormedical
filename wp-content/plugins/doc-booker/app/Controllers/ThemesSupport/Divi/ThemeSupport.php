<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\Divi;

use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ThemeSupport {

	use SingletonTrait;

	public function __construct() {
		add_filter( 'body_class', [ __CLASS__, 'force_fullwidth_layout' ], 99 );
	}

	private static function is_target_page() {
		return Doctor::is_doctor() || Clinic::is_clinic();
	}

	/**
	 * Divi keeps its default right/left "sidebar" body layout on the plugin's
	 * doctor/clinic pages, which pins #left-area to ~78% width and reserves the
	 * remainder for a Divi sidebar (#sidebar) the plugin never outputs — squeezing
	 * the plugin's own content + booking-sidebar columns into the left of the page
	 * and leaving a large empty gap on the right.
	 *
	 * Swap Divi's sidebar body classes for its full-width layout classes so the
	 * plugin gets the whole content-area width and its built-in right sidebar (the
	 * booking calendar) sits correctly beside the content. The matching visual rules
	 * live in src/sass/public/compatibility/_divi-theme.scss.
	 */
	public static function force_fullwidth_layout( $classes ) {
		if ( ! self::is_target_page() ) {
			return $classes;
		}

		$classes   = array_diff( $classes, [ 'et_right_sidebar', 'et_left_sidebar' ] );
		$classes[] = 'et_full_width_page';
		$classes[] = 'et_no_sidebar';

		return array_values( array_unique( $classes ) );
	}
}
