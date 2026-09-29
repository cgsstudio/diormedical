<?php
/**
 * @package WPDDB
 */
namespace WpDreamers\WPDDB\Traits;

use WpDreamers\WPDDB\Controllers\Helper\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
trait TemplateTraits{
	public static function page_title($echo = true,$page=null) {
		$page_title='';
		if (is_search()) {
			/* translators: %s: search query */
			$page_title = sprintf(__('Search results: &ldquo;%s&rdquo;', 'doc-booker'), get_search_query());

			if (get_query_var('paged')) {
				/* translators: %s: page number */
				$page_title .= sprintf(__('&nbsp;&ndash; Page %s', 'doc-booker'), get_query_var('paged'));
			}
		} elseif (is_tax()) {

			$page_title = single_term_title('', false);

		} elseif('doctor'===$page) {
			$doctor_page_id = Helper::get_page_id('doctors');
			$page_title = get_the_title($doctor_page_id);
		}elseif ('clinic'===$page){
			$clinic_page_id = Helper::get_page_id('clinics');
			$page_title = get_the_title($clinic_page_id);
		}

		$page_title = apply_filters('wpddb_page_title', $page_title);

		if ($echo) {
			echo esc_html($page_title);
		} else {
			return $page_title;
		}
	}
}