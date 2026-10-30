<?php
/**
 * Copyright (C) 2014-2025 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Attribution: This code is part of the All-in-One WP Migration plugin, developed by
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Ai1wmue_Import_Controller {


	/**
	 * Enqueue scripts and styles for Import Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wm_import', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmue_import',
				Ai1wm_Template::asset_link( 'css/import.min.rtl.css', 'AI1WMUE' ),
				array( 'ai1wm_import' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmue_import',
				Ai1wm_Template::asset_link( 'css/import.min.css', 'AI1WMUE' ),
				array( 'ai1wm_import' )
			);
		}

		wp_enqueue_script(
			'ai1wmue_import',
			Ai1wm_Template::asset_link( 'javascript/import.min.js', 'AI1WMUE' ),
			array( 'ai1wm_import' )
		);

		wp_enqueue_script(
			'ai1wmue_wasm_exec',
			Ai1wm_Template::asset_link( 'javascript/vendor/wasm_exec.js', 'AI1WMUE' ),
			array( 'ai1wmue_import' )
		);

		// Base service
		wp_localize_script(
			'ai1wmue_import',
			'ai1wmue_base_service',
			array(
				'url' => AI1WMUE_SERVICE_URL,
				'key' => AI1WMUE_PURCHASE_ID,
			)
		);

		// Site details
		wp_localize_script(
			'ai1wmue_import',
			'ai1wmue_site_details',
			array(
				'site_url'    => site_url(),
				'admin_email' => get_option( 'admin_email' ),
			)
		);

		// File uploader
		wp_localize_script(
			'ai1wmue_import',
			'ai1wmue_file_uploader',
			array(
				'config'  => array(
					'chunk_size'  => (int) apply_filters( 'ai1wm_max_chunk_size', AI1WM_MAX_CHUNK_SIZE ),
					'max_retries' => (int) apply_filters( 'ai1wm_max_chunk_retries', AI1WM_MAX_CHUNK_RETRIES ),
				),
				'url'     => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1, 'ai1wm_manual_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wm_import' ) ) ),
				'params'  => array(
					'priority'   => 5,
					'secret_key' => get_option( AI1WM_SECRET_KEY ),
				),
				'filters' => array(
					'ai1wm_archive_extension' => array( 'wpress' ),
				),
			)
		);

		// Add Google Tag Manager
		add_action( 'admin_print_scripts', 'Ai1wmue_GTM_Controller::print_scripts', 60 );
	}
}
