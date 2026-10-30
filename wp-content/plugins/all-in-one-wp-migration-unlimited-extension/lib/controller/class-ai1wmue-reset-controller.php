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

class Ai1wmue_Reset_Controller {

	/**
	 * Enqueue scripts and styles for Reset Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wmue_reset', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmue_reset',
				Ai1wm_Template::asset_link( 'css/reset.min.rtl.css', 'AI1WMUE' ),
				array( 'ai1wm_backups' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmue_reset',
				Ai1wm_Template::asset_link( 'css/reset.min.css', 'AI1WMUE' ),
				array( 'ai1wm_backups' )
			);
		}

		wp_enqueue_script(
			'ai1wmue_backups',
			Ai1wm_Template::asset_link( 'javascript/backups.min.js', 'AI1WMUE' ),
			array( 'ai1wm_backups' )
		);

		wp_enqueue_script(
			'ai1wmue_reset',
			Ai1wm_Template::asset_link( 'javascript/reset.min.js', 'AI1WMUE' ),
			array( 'ai1wmue_backups' )
		);

		wp_enqueue_script(
			'ai1wmue_wasm_exec',
			Ai1wm_Template::asset_link( 'javascript/vendor/wasm_exec.js', 'AI1WMUE' ),
			array( 'ai1wmue_reset' )
		);

		// Base service
		wp_localize_script(
			'ai1wmue_reset',
			'ai1wmue_base_service',
			array(
				'url' => AI1WMUE_SERVICE_URL,
				'key' => AI1WMUE_PURCHASE_ID,
			)
		);

		// Site details
		wp_localize_script(
			'ai1wmue_reset',
			'ai1wmue_site_details',
			array(
				'site_url'    => site_url(),
				'admin_email' => get_option( 'admin_email' ),
			)
		);

		wp_localize_script(
			'ai1wmue_reset',
			'ai1wmue_reset',
			array(
				'ajax'       => array(
					'url' => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1 ), admin_url( 'admin-ajax.php?action=ai1wmue_reset_tools' ) ) ),
				),
				'status'     => array(
					'url' => wp_make_link_relative( add_query_arg( array( 'ai1wm_import' => 1, 'secret_key' => get_option( AI1WM_SECRET_KEY ) ), admin_url( 'admin-ajax.php?action=ai1wm_status' ) ) ),
				),
				'secret_key' => get_option( AI1WM_SECRET_KEY ),
			)
		);

		wp_localize_script(
			'ai1wmue_reset',
			'ai1wmue_locale',
			array(
				// Reset type agnostic translations
				'reset_in_progress'           => __( 'Reset In Progress', AI1WMUE_PLUGIN_NAME ),
				'reset_in_progress_info'      => __( 'Your request is being processed. This may take a few moments. Please do not close this window or navigate away from this page while the reset is in progress.', AI1WMUE_PLUGIN_NAME ),
				'stop_resetting_your_website' => __( 'You are about to stop resetting your website, are you sure?', AI1WMUE_PLUGIN_NAME ),
				'unable_to_stop_the_reset'    => __( 'Unable to stop the reset. Refresh the page and try again', AI1WMUE_PLUGIN_NAME ),
				'unable_to_start_the_reset'   => __( 'Unable to start the reset. Refresh the page and try again', AI1WMUE_PLUGIN_NAME ),
				'unable_to_reset'             => __( 'Unable to reset', AI1WMUE_PLUGIN_NAME ),
				'create_snapshot_title'       => __( 'Create a new snapshot', AI1WMUE_PLUGIN_NAME ),
				'create_snapshot_btn'         => __( 'Create snapshot', AI1WMUE_PLUGIN_NAME ),
				'cancel'                      => __( 'Cancel', AI1WMUE_PLUGIN_NAME ),
				'done'                        => __( 'Done', AI1WMUE_PLUGIN_NAME ),
				'stop'                        => __( 'Stop Reset', AI1WMUE_PLUGIN_NAME ),
				'close'                       => __( 'Close', AI1WMUE_PLUGIN_NAME ),
				'backup_btn'                  => __( 'Create Backup', AI1WMUE_PLUGIN_NAME ),
				'retry'                       => __( 'Retry', AI1WMUE_PLUGIN_NAME ),

				// Translations for each of a reset type
				'plugins'                     => array(
					'name'          => __( 'Plugin Purge', AI1WMUE_PLUGIN_NAME ),
					'description'   => __( 'Quickly removes all installed plugins from your WordPress site. Ideal for troubleshooting conflicts or starting fresh with plugin installations.', AI1WMUE_PLUGIN_NAME ),
					'help'          => __( 'This tool will remove all installed plugins from your site.', AI1WMUE_PLUGIN_NAME ),
					'reset_btn'     => __( 'Purge Plugins', AI1WMUE_PLUGIN_NAME ),
					'confirm_title' => __( 'Confirm Plugin Purge', AI1WMUE_PLUGIN_NAME ),
					'confirm_text'  => __( 'Are you sure you want to purge your plugins? This will delete all the plugins.', AI1WMUE_PLUGIN_NAME ),
					'confirm_btn'   => __( 'Purge Plugins', AI1WMUE_PLUGIN_NAME ),
				),
				'themes'                      => array(
					'name'          => __( 'Theme Reset', AI1WMUE_PLUGIN_NAME ),
					'description'   => __( 'Deletes all themes and reactivates the default WordPress theme. Useful for reverting to a clean state or resolving theme-related issues.', AI1WMUE_PLUGIN_NAME ),
					'help'          => __( 'This tool will delete all themes and revert to the default WordPress theme.', AI1WMUE_PLUGIN_NAME ),
					'reset_btn'     => __( 'Theme Reset', AI1WMUE_PLUGIN_NAME ),
					'confirm_title' => __( 'Confirm Theme Reset', AI1WMUE_PLUGIN_NAME ),
					'confirm_text'  => __( 'Are you sure you want to reset your themes? This will delete all your current themes and reactivate the default WordPress theme.', AI1WMUE_PLUGIN_NAME ),
					'confirm_btn'   => __( 'Theme Reset', AI1WMUE_PLUGIN_NAME ),
				),
				'media'                       => array(
					'name'          => __( 'Media Clean-Up', AI1WMUE_PLUGIN_NAME ),
					'description'   => __( 'Erases all media files from the site\'s media library. Ideal for clearing outdated or unnecessary media to declutter your site.', AI1WMUE_PLUGIN_NAME ),
					'help'          => __( 'This tool will delete all media files from your site\'s media library.', AI1WMUE_PLUGIN_NAME ),
					'reset_btn'     => __( 'Media Clean-Up', AI1WMUE_PLUGIN_NAME ),
					'confirm_title' => __( 'Confirm Media Clean-Up', AI1WMUE_PLUGIN_NAME ),
					'confirm_text'  => __( 'Are you sure you want to erase all media files from your site media library?', AI1WMUE_PLUGIN_NAME ),
					'confirm_btn'   => __( 'Media Clean-Up', AI1WMUE_PLUGIN_NAME ),
				),
				'database'                    => array(
					'name'          => __( 'Reset Database', AI1WMUE_PLUGIN_NAME ),
					'description'   => __( 'This action will permanently erase all existing data within your database and revert your WordPress site to its default state. This includes posts, pages, comments, settings, and user data. Useful for reverting to a clean state and starting fresh.', AI1WMUE_PLUGIN_NAME ),
					'help'          => __( 'This tool will delete all existing data within your database and revert your WordPress site to its default state.', AI1WMUE_PLUGIN_NAME ),
					'reset_btn'     => __( 'Reset Database', AI1WMUE_PLUGIN_NAME ),
					'confirm_title' => __( 'Confirm Database Reset', AI1WMUE_PLUGIN_NAME ),
					'confirm_text'  => __( 'Are you sure you want to reset your database? This action will permanently erase all existing data within your database and revert your WordPress site to its default state. This includes posts, pages, comments, settings, and user data. Once completed, this action cannot be undone.', AI1WMUE_PLUGIN_NAME ),
					'confirm_btn'   => __( 'Reset Database', AI1WMUE_PLUGIN_NAME ),
				),
				'all'                         => array(
					'name'          => __( 'Full Site Reset', AI1WMUE_PLUGIN_NAME ),
					'description'   => __( 'Completely resets the site, restoring WordPress to its initial installation state. Best for starting entirely from scratch or for a clean slate on the site.', AI1WMUE_PLUGIN_NAME ),
					'help'          => __( 'This tool will reset your entire WordPress site to its default installation state.', AI1WMUE_PLUGIN_NAME ),
					'reset_btn'     => __( 'Full Site Reset', AI1WMUE_PLUGIN_NAME ),
					'confirm_title' => __( 'Confirm Site Reset', AI1WMUE_PLUGIN_NAME ),
					'confirm_text'  => __( 'Are you sure you want to erase all media files from your site media library? This action is ideal for clearing outdated or unnecessary media to declutter your site.', AI1WMUE_PLUGIN_NAME ),
					'confirm_btn'   => __( 'Full Site Reset', AI1WMUE_PLUGIN_NAME ),
				),
			)
		);

		// Add Google Tag Manager
		add_action( 'admin_print_scripts', 'Ai1wmue_GTM_Controller::print_scripts', 60 );
	}
}
