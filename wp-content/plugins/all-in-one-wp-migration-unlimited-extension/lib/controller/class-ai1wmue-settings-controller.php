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

class Ai1wmue_Settings_Controller {

	/**
	 * Enqueue scripts and styles for Settings Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wmue_settings', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmue_settings',
				Ai1wm_Template::asset_link( 'css/settings.min.rtl.css', 'AI1WMUE' ),
				array( 'ai1wm_servmask' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmue_settings',
				Ai1wm_Template::asset_link( 'css/settings.min.css', 'AI1WMUE' ),
				array( 'ai1wm_servmask' )
			);
		}

		wp_enqueue_script(
			'ai1wmue_settings',
			Ai1wm_Template::asset_link( 'javascript/settings.min.js', 'AI1WMUE' ),
			array( 'ai1wm_settings' )
		);

		wp_localize_script(
			'ai1wmue_settings',
			'ai1wmue_settings',
			array(
				'ajax'       => array(
					'url' => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_list_folders' ) ) ),
				),
				'secret_key' => get_option( AI1WM_SECRET_KEY ),
			)
		);

		wp_localize_script(
			'ai1wmue_settings',
			'ai1wmue_locale',
			array(
				'folder_browser_change' => __( 'Change', AI1WMUE_PLUGIN_NAME ),
				'title_name'            => __( 'Name', AI1WMUE_PLUGIN_NAME ),
				'title_date'            => __( 'Date', AI1WMUE_PLUGIN_NAME ),
				'empty_list_message'    => __( 'No folders to list. Click on the navbar to go back.', AI1WMUE_PLUGIN_NAME ),
				'legend_select_info'    => __( 'Select with a click', AI1WMUE_PLUGIN_NAME ),
				'legend_open_info'      => __( 'Open with two clicks', AI1WMUE_PLUGIN_NAME ),
				'button_close'          => __( 'Close', AI1WMUE_PLUGIN_NAME ),
				'button_select'         => __( 'Select folder &gt;', AI1WMUE_PLUGIN_NAME ),
				'show_more'             => __( 'more', AI1WMUE_PLUGIN_NAME ),
				'show_less'             => __( 'less', AI1WMUE_PLUGIN_NAME ),
			)
		);

		// Add Google Tag Manager
		add_action( 'admin_print_scripts', 'Ai1wmue_GTM_Controller::print_scripts', 60 );
	}
}
