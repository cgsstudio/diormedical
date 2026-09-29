<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport\Astra;


use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
class ThemeSupport{

	use SingleTonTrait;


	public function __construct(  ) {
		add_filter('astra_dynamic_post_structure_posttypes',[__CLASS__,'astra_post_types'],15);
		add_filter('astra_blog_post_per_page_exclusions',[__CLASS__,'wpddb_post_types_exclude']);
	}


	public static function astra_post_types( $post_types ) {
		foreach ($post_types as $post_type){
			if (wpddb()->post_type_doctor === $post_type || wpddb()->post_type_clinic === $post_type){
				$position = array_search($post_type,$post_types);
				unset($post_types[$position]);
			}
		}

		return $post_types;
	}
	public static function wpddb_post_types_exclude($exclusions){
		$exclusions[] = wpddb()->post_type_doctor;
		$exclusions[] = wpddb()->post_type_clinic;
		return $exclusions;
	}
}