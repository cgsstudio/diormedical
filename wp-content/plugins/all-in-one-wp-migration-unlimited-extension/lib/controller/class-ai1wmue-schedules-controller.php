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

class Ai1wmue_Schedules_Controller {

	/**
	 * Enqueue scripts and styles for Schedules Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wmue_schedules', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmue_schedules',
				Ai1wm_Template::asset_link( 'css/schedules.min.rtl.css', 'AI1WMUE' ),
				array( 'ai1wm_servmask' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmue_schedules',
				Ai1wm_Template::asset_link( 'css/schedules.min.css', 'AI1WMUE' ),
				array( 'ai1wm_servmask' )
			);
		}

		wp_enqueue_script(
			'ai1wmue_schedules',
			Ai1wm_Template::asset_link( 'javascript/schedules.min.js', 'AI1WMUE' ),
			array( 'ai1wm_schedules' )
		);

		wp_localize_script(
			'ai1wmue_schedules',
			'ai1wmue_schedules',
			array(
				'ajax'       => array(
					'delete' => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_schedule_event_delete' ) ) ),
					'log'    => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_schedule_event_log' ) ) ),
					'clean'  => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_schedule_event_clean' ) ) ),
					'run'    => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_schedule_event_manual_run' ) ) ),
					'status' => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_schedule_event_status' ) ) ),
					'cron'   => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), site_url( 'wp-cron.php' ) ) ),
				),
				'status'     => array(
					'none'    => Ai1wmue_Schedule_Event::LAST_STATUS_NONE,
					'failed'  => Ai1wmue_Schedule_Event::LAST_STATUS_FAILED,
					'success' => Ai1wmue_Schedule_Event::LAST_STATUS_SUCCESS,
					'running' => Ai1wmue_Schedule_Event::LAST_STATUS_RUNNING,
				),
				'secret_key' => get_option( AI1WM_SECRET_KEY ),
			)
		);

		// List files
		wp_localize_script(
			'ai1wmue_schedules',
			'ai1wmue_list_files',
			array(
				'ajax' => array(
					'url'   => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_list_files' ) ) ),
					'nonce' => wp_create_nonce( 'ai1wmue_list_files' ),
				),
			)
		);

		wp_localize_script(
			'ai1wmue_schedules',
			'ai1wmue_locale',
			array(
				'want_to_clean_this_log'             => __( 'Are you sure you want to clean this log?', AI1WMUE_PLUGIN_NAME ),
				'want_to_delete_this_event'          => __( 'Are you sure you want to delete this event?', AI1WMUE_PLUGIN_NAME ),
				'want_to_start_this_event'           => __( 'Are you sure you want to start this event?', AI1WMUE_PLUGIN_NAME ),
				'event_log_modal_title'              => __( 'Event log', AI1WMUE_PLUGIN_NAME ),
				'event_log_no_records'               => __( 'There are no log records for this event', AI1WMUE_PLUGIN_NAME ),
				'close_modal'                        => __( 'Close', AI1WMUE_PLUGIN_NAME ),
				'button_done'                        => __( 'Done', AI1WMUE_PLUGIN_NAME ),
				'loading_placeholder'                => __( 'Listing files ...', AI1WMUE_PLUGIN_NAME ),
				'selected_no_files'                  => __( 'No files selected', AI1WMUE_PLUGIN_NAME ),
				'selected_multiple'                  => __( '{x} files and {y} folders selected', AI1WMUE_PLUGIN_NAME ),
				'selected_multiple_folders'          => __( '{y} folders selected', AI1WMUE_PLUGIN_NAME ),
				'selected_multiple_files'            => __( '{x} files selected', AI1WMUE_PLUGIN_NAME ),
				'selected_one_file'                  => __( '{x} file selected', AI1WMUE_PLUGIN_NAME ),
				'selected_one_file_multiple_folders' => __( '{x} file and {y} folders selected', AI1WMUE_PLUGIN_NAME ),
				'selected_one_file_one_folder'       => __( '{x} file and {y} folder selected', AI1WMUE_PLUGIN_NAME ),
				'selected_one_folder'                => __( '{y} folder selected', AI1WMUE_PLUGIN_NAME ),
				'selected_multiple_files_one_folder' => __( '{x} files and {y} folder selected', AI1WMUE_PLUGIN_NAME ),
				'column_name'                        => __( 'Name', AI1WMUE_PLUGIN_NAME ),
				'column_date'                        => __( 'Date', AI1WMUE_PLUGIN_NAME ),
				'legend_select'                      => __( 'Click checkbox to toggle selection', AI1WMUE_PLUGIN_NAME ),
				'legend_expand'                      => __( 'Click folder name to expand', AI1WMUE_PLUGIN_NAME ),
				'error_message'                      => __( 'Something went wrong, please refresh and try again', AI1WMUE_PLUGIN_NAME ),
				'button_clear'                       => __( 'Clear selection', AI1WMUE_PLUGIN_NAME ),
				'empty_list_message'                 => __( 'Folder empty. Click on folder icon to close it.', AI1WMUE_PLUGIN_NAME ),
				'column_table_name'                  => __( 'Table Name', AI1WMUE_PLUGIN_NAME ),
				'selected_no_tables'                 => __( 'No tables selected', AI1WMUE_PLUGIN_NAME ),
				'selected_one_table'                 => __( '{x} table selected', AI1WMUE_PLUGIN_NAME ),
				'selected_multiple_tables'           => __( '{x} tables selected', AI1WMUE_PLUGIN_NAME ),
				'empty_table_list_message'           => __( 'No tables found.', AI1WMUE_PLUGIN_NAME ),
				'database_name'                      => DB_NAME,

				// Translations for each of a schedule type
				Ai1wmue_Schedule_Event::TYPE_EXPORT  => array(
					'no_spam_comments'    => __( 'Exclude spam comments', AI1WMUE_PLUGIN_NAME ),
					'no_post_revisions'   => __( 'Exclude post revisions', AI1WMUE_PLUGIN_NAME ),
					'no_database'         => __( 'Exclude database (sql)', AI1WMUE_PLUGIN_NAME ),
					'no_email_replace'    => __( 'Do <strong>not</strong> replace email domain (sql)', AI1WMUE_PLUGIN_NAME ),
					'no_media'            => __( 'Exclude media library (files)', AI1WMUE_PLUGIN_NAME ),
					'no_inactive_themes'  => __( 'Exclude inactive themes (files)', AI1WMUE_PLUGIN_NAME ),
					'no_themes'           => __( 'Exclude themes (files)', AI1WMUE_PLUGIN_NAME ),
					'no_muplugins'        => __( 'Exclude must-use plugins (files)', AI1WMUE_PLUGIN_NAME ),
					'no_plugins'          => __( 'Exclude plugins (files)', AI1WMUE_PLUGIN_NAME ),
					'no_inactive_plugins' => __( 'Exclude inactive plugins (files)', AI1WMUE_PLUGIN_NAME ),
					'no_cache'            => __( 'Exclude cache (files)', AI1WMUE_PLUGIN_NAME ),
				),
				Ai1wmue_Schedule_Event::TYPE_IMPORT  => array(),
			)
		);

		// Add Google Tag Manager
		add_action( 'admin_print_scripts', 'Ai1wmue_GTM_Controller::print_scripts', 60 );
	}
}
