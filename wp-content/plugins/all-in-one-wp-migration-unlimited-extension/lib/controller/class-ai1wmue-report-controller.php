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

class Ai1wmue_Report_Controller {

	public static function on_export( $params ) {
		if ( isset( $params['ai1wm_manual_export'] ) ) {
			self::send_report( implode( '/', array( AI1WMUE_REPORT_URL, AI1WMUE_PURCHASE_ID, 'export' ) ) );
		}
	}

	public static function on_import( $params ) {
		if ( isset( $params['ai1wm_manual_import'] ) ) {
			self::send_report( implode( '/', array( AI1WMUE_REPORT_URL, AI1WMUE_PURCHASE_ID, 'import' ) ) );
		}
	}

	public static function on_restore( $params ) {
		if ( isset( $params['ai1wm_manual_restore'] ) ) {
			self::send_report( implode( '/', array( AI1WMUE_REPORT_URL, AI1WMUE_PURCHASE_ID, 'restore' ) ) );
		}
	}

	public static function on_activate() {
		self::send_report( AI1WMUE_ACTIVATION_URL );
	}

	protected static function send_report( $destination_url ) {
		global $wpdb;

		if ( AI1WMUE_PURCHASE_ID ) {
			@wp_remote_post(
				$destination_url,
				array(
					'timeout' => 5,
					'body'    => array(
						'url'           => get_site_url(),
						'email'         => get_option( 'admin_email' ),
						'wp_version'    => get_bloginfo( 'version' ),
						'php_version'   => PHP_VERSION,
						'mysql_version' => $wpdb->db_version(),
						'uuid'          => AI1WMUE_PURCHASE_ID,
					),
				)
			);
		}
	}
}
