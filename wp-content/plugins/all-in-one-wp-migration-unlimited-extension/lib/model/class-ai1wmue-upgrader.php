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

class Ai1wmue_Upgrader {

	public static function sync_options() {
		global $wpdb;

		// The upgrader is already up to date
		if ( get_option( 'ai1wmue_upgrader_version', 0 ) >= 1 ) {
			return;
		}

		// Schedules
		if ( defined( 'AI1WM_PLUGIN_NAME' ) ) {
			$result = $wpdb->query( "INSERT INTO {$wpdb->options} (option_name, option_value) SELECT 'ai1wmue_schedule_events', REPLACE(o.option_value, 'Ai1wmve_Schedule_Event', 'Ai1wmue_Schedule_Event') FROM `{$wpdb->options}` AS o WHERE o.option_name = 'ai1wmve_schedule_events' AND NOT EXISTS (SELECT * FROM {$wpdb->options} WHERE `option_name` = 'ai1wmue_schedule_events')" );
			if ( $result === 1 ) {
				// Per-event last run status and logs are stored under option names prefixed with the cron hook
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->options} (option_name, option_value) SELECT REPLACE(o.option_name, 'ai1wmve_schedule_event_', 'ai1wmue_schedule_event_'), o.option_value FROM `{$wpdb->options}` AS o WHERE ( o.option_name LIKE %s OR o.option_name LIKE %s ) AND NOT EXISTS (SELECT * FROM {$wpdb->options} AS n WHERE n.option_name = REPLACE(o.option_name, 'ai1wmve_schedule_event_', 'ai1wmue_schedule_event_'))",
						$wpdb->esc_like( 'ai1wmve_schedule_event_last_run_' ) . '%',
						$wpdb->esc_like( 'ai1wmve_schedule_event_log_' ) . '%'
					)
				);

				// The raw inserts bypass the options API, so drop the cached lookups
				ai1wm_cache_flush();

				// Re-create the cron entries under the new hook
				$events = new Ai1wmue_Schedule_Events();
				foreach ( $events->all() as $event ) {
					$event->create_schedule();
				}
			}

			// Set the upgrader version
			update_option( 'ai1wmue_upgrader_version', 1 );
		}
	}
}
