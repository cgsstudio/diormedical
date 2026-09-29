<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\Hooks;


// Do not allow directly accessing this file.
use WpDreamers\WPDDB\Controllers\Helper\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
class TemplateLoader{

    private static $theme_support = false;

    public static function init(){
        
        self::$theme_support = current_theme_supports('wpddb');

        if (self::$theme_support) {
            add_filter('template_include', [__CLASS__, 'template_loader']);
        }
    }
    public static function template_loader($template) {

        if (is_embed()) {
            return $template;
        }
        
        $default_file = self::get_template_loader_default_file();


        if ($default_file) {

            $search_files = self::get_template_loader_files($default_file);

            $template = locate_template($search_files);

            if (!$template) {
                $fallback = wpddb()->get_plugin_template_path() . $default_file;
                $template = file_exists($fallback) ? $fallback : '';
                $template = apply_filters('wpddb_template_loader_fallback_file', $template, $default_file);
            }

        }

        return $template;
    }
    private static function get_template_loader_default_file() {
        $default_file = '';

        if (is_singular(wpddb()->post_type_doctor)) {
            $default_file = 'single_wpddb_doctor.php';
        }
        elseif(is_singular(wpddb()->post_type_clinic)){
            $default_file = 'single_wpddb_clinic.php';
        }
		elseif (is_tax( get_object_taxonomies( wpddb()->post_type_doctor ) )) {
			$object = get_queried_object();
			if ( is_tax( get_object_taxonomies(  wpddb()->post_type_doctor )  ) ) {
				$default_file = $object->taxonomy . '.php';
			} else {
				$default_file = 'archive-' . 'post_type' . '.php';
			}
		}
        elseif (is_post_type_archive(wpddb()->post_type_doctor) || (($doctors_page_id = Helper::get_page_id('doctors')) && is_page($doctors_page_id))) {
	        $default_file = 'archive_wpddb_doctor.php';
        }
        elseif (is_post_type_archive(wpddb()->post_type_clinic) || (($clinic_page_id = Helper::get_page_id('clinics')) && is_page($clinic_page_id))) {
	        $default_file = 'archive_wpddb_clinic.php';
        }
	    return apply_filters('wpddb_template_loader_default_file', $default_file);
    }
    private static function get_template_loader_files($default_file) {

        if (is_page_template()) {
            $templates[] = get_page_template_slug();
        }

        if (is_singular(wpddb()->post_type_doctor)) {
            $object = get_queried_object();
            $name_decoded = urldecode($object->post_name);
            if ($name_decoded !== $object->post_name) {
                $templates[] = "single_wpddb-doctor-{$name_decoded}.php";
            }
            $templates[] = "single_wpddb-doctor-{$object->post_name}.php";
        }

        elseif (is_singular(wpddb()->post_type_clinic)) {
            $object = get_queried_object();
            $name_decoded = urldecode($object->post_name);
            if ($name_decoded !== $object->post_name) {
                $templates[] = "single_wpddb-clinic-{$name_decoded}.php";
            }
            $templates[] = "single_wpddb-clinic-{$object->post_name}.php";
        }

        $templates = [
            $default_file,
            wpddb()->get_template_path() . $default_file,
        ];

        $templates = apply_filters('wpddb_template_loader_files', $templates, $default_file);

        return array_unique($templates);
    }

}