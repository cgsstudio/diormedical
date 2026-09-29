<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\Kadence;

use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ThemeSupport {

	use SingletonTrait;

	public function __construct() {
		add_filter( 'kadence_post_layout', [ __CLASS__, 'force_layout_array' ], 20 );
		add_filter( 'kadence_archive_layout', [ __CLASS__, 'force_layout_array' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_inline_css' ], 30 );
	}

	private static function is_target_page() {
		return Doctor::is_doctor() || Clinic::is_clinic();
	}

	public static function force_layout_array( $layout ) {
		if ( ! self::is_target_page() || ! is_array( $layout ) ) {
			return $layout;
		}
		$layout['sidebar'] = 'disable';
		$layout['layout']  = 'normal';
		$layout['content'] = 'normal';
		return $layout;
	}

	public static function enqueue_inline_css() {
		if ( ! self::is_target_page() ) {
			return;
		}

		$css = '
			.wpddb #primary.content-area .site-container { max-width: var(--global-content-width, 1290px); margin-left: auto; margin-right: auto; }
			.wpddb .wpddb-kadence-main { padding: var(--global-content-edge-padding, 1.5rem); }
		';

		wp_register_style( 'wpddb-kadence-support', false );
		wp_enqueue_style( 'wpddb-kadence-support' );
		wp_add_inline_style( 'wpddb-kadence-support', $css );
	}
}
