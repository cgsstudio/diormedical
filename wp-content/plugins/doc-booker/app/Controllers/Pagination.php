<?php

namespace WpDreamers\WPDDB\Controllers;
use WpDreamers\WPDDB\Controllers\Helper\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class Pagination {
	public static function get_page_number() {

		global $paged;

		if (get_query_var('paged')) {
			$paged = get_query_var('paged');
		} else if (get_query_var('page')) {
			$paged = get_query_var('page');
		} else {
			$paged = 1;
		}

		return absint($paged);

	}

	public static function pagination() {

		$range = 1;
		$showItems = ($range * 2) + 1;
		$paged = self::get_page_number();

		if (!isset($pages)) {
			global $wp_query;
			$pages = $wp_query->max_num_pages;

			if (!$pages) {
				$pages = 1;
			}
		}

		Helper::render_template( "global/pagination", compact('paged', 'showItems', 'pages') );
	}
}