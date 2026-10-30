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

class Ai1wmue_Import_Update_Settings {

	public static function execute( $params ) {
		global $wpdb;

		// Set progress
		Ai1wm_Status::info( __( 'Updating settings...', AI1WMUE_PLUGIN_NAME ) );

		// Read settings.json file
		$handle = ai1wm_open( ai1wm_settings_path( $params, 'unlimited' ), 'r' );

		// Parse settings.json file
		$settings = ai1wm_read( $handle, filesize( ai1wm_settings_path( $params, 'unlimited' ) ) );
		$settings = json_decode( $settings, true );

		// Close handle
		ai1wm_close( $handle );

		// Update settings
		foreach ( $settings as $item ) {
			if ( isset( $item['option_name'], $item['option_value'] ) ) {
				$num_rows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $item['option_name'] ) );
				if ( $num_rows > 0 ) {
					$wpdb->update( $wpdb->options, array( 'option_value' => $item['option_value'] ), array( 'option_name' => $item['option_name'] ) );
				} else {
					$wpdb->insert( $wpdb->options, $item );
				}
			}
		}

		// The raw writes bypass the options API, so flush the cache
		ai1wm_cache_flush();

		// The cron option came from the archive, so book the restored schedules again
		wp_unschedule_hook( Ai1wmue_Schedule_Event::CRON_HOOK );

		$events = new Ai1wmue_Schedule_Events();
		foreach ( $events->all() as $event ) {
			$event->create_schedule();
		}

		// Set progress
		Ai1wm_Status::info( __( 'Done updating settings.', AI1WMUE_PLUGIN_NAME ) );

		return $params;
	}
}
