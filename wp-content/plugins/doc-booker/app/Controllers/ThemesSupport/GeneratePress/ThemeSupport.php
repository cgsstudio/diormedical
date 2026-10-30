<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\GeneratePress;

use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ThemeSupport {

	use SingletonTrait;

	public function __construct() {
		add_filter( 'generate_sidebar_layout', [ __CLASS__, 'force_no_sidebar' ], 20 );
		add_filter( 'generate_blog_columns', [ __CLASS__, 'force_no_columns' ], 20 );
		add_filter( 'generate_blog_masonry', [ __CLASS__, 'force_no_masonry' ], 20 );
	}

	private static function is_target_page() {
		return Doctor::is_doctor() || Clinic::is_clinic();
	}

	public static function force_no_sidebar( $layout ) {
		if ( self::is_target_page() ) {
			return 'no-sidebar';
		}
		return $layout;
	}

	public static function force_no_columns( $columns ) {
		if ( self::is_target_page() ) {
			return false;
		}
		return $columns;
	}

	public static function force_no_masonry( $masonry ) {
		if ( self::is_target_page() ) {
			return false;
		}
		return $masonry;
	}
}
