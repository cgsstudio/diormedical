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

class Ai1wmue_Reset_Compatibility {

	public static function execute( $params ) {
		if ( AI1WM_VERSION !== 'develop' ) {
			if ( version_compare( AI1WM_VERSION, AI1WMUE_MIN_AI1WM_VERSION, '<' ) ) {
				if ( defined( 'WP_CLI' ) ) {
					throw new Ai1wm_Compatibility_Exception(
						esc_html__( 'All-in-One WP Migration is not the latest version. You must update the plugin before you can use it. ', AI1WMUE_PLUGIN_NAME )
					);
				}

				throw new Ai1wm_Compatibility_Exception(
					wp_kses(
						sprintf(
							__( '<strong>All-in-One WP Migration</strong> is not the latest version. <br />You must <a href="%s">update the plugin</a> before you can use it. <br />', AI1WMUE_PLUGIN_NAME ),
							esc_url( network_admin_url( 'plugins.php' ) )
						),
						ai1wmue_allowed_html_tags()
					)
				);
			}
		}

		return $params;
	}
}
