<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\Blocksy;

use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ThemeSupport {

	use SingletonTrait;

	public function __construct() {
		add_filter( 'blocksy:pro:content-blocks:supported-content-types', [ __CLASS__, 'remove_wpddb_types' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_inline_css' ], 30 );
	}

	private static function is_target_page() {
		return Doctor::is_doctor() || Clinic::is_clinic();
	}

	public static function remove_wpddb_types( $types ) {
		if ( ! is_array( $types ) ) {
			return $types;
		}
		foreach ( [ 'wpddb_doctor', 'wpddb_clinic' ] as $slug ) {
			$pos = array_search( $slug, $types, true );
			if ( false !== $pos ) {
				unset( $types[ $pos ] );
			}
		}
		return array_values( $types );
	}

	public static function enqueue_inline_css() {
		if ( ! self::is_target_page() ) {
			return;
		}

		$css = '
			.wpddb #primary .ct-container { max-width: var(--block-max-width, 1290px); margin-left: auto; margin-right: auto; padding-left: var(--content-edge-spacing, 20px); padding-right: var(--content-edge-spacing, 20px); }
		';

		wp_register_style( 'wpddb-blocksy-support', false );
		wp_enqueue_style( 'wpddb-blocksy-support' );
		wp_add_inline_style( 'wpddb-blocksy-support', $css );
	}
}
