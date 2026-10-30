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

class Ai1wmue_Install_Controller {

	/**
	 * Install or activate base plugin
	 *
	 * @return void
	 */
	public static function on_activate() {
		// Check if the base plugin is installed
		if ( is_wp_error( validate_plugin( 'all-in-one-wp-migration/all-in-one-wp-migration.php' ) ) ) {

			// Install the base plugin
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			if ( ! class_exists( 'WP_Upgrader', false ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => 'all-in-one-wp-migration',
					'fields' => array(
						'short_description' => false,
						'sections'          => false,
						'requires'          => false,
						'rating'            => false,
						'ratings'           => false,
						'downloaded'        => false,
						'last_updated'      => false,
						'added'             => false,
						'tags'              => false,
						'compatibility'     => false,
						'homepage'          => false,
						'donate_link'       => false,
					),
				)
			);

			if ( is_wp_error( $api ) ) {
				return $api;
			}

			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$response = $upgrader->install( $api->download_link );

			// Installation failed, deactivate this plugin
			if ( is_wp_error( $response ) ) {
				deactivate_plugins( AI1WMUE_PLUGIN_BASENAME );
				wp_die(
					wp_kses(
						sprintf(
							__( 'The All-in-One WP Migration plugin could not be installed automatically. Please <a href="%s" target="_blank">download and install it manually</a> before activating this extension.', AI1WMUE_PLUGIN_NAME ),
							esc_url( 'https://wordpress.org/plugins/all-in-one-wp-migration/' )
						),
						ai1wmue_allowed_html_tags()
					)
				);
			}
		}

		// Activate the base plugin if it's not already active
		if ( is_plugin_inactive( 'all-in-one-wp-migration/all-in-one-wp-migration.php' ) ) {
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$response = activate_plugin( 'all-in-one-wp-migration/all-in-one-wp-migration.php' );

			// Activation failed, deactivate this plugin
			if ( is_wp_error( $response ) ) {
				deactivate_plugins( AI1WMUE_PLUGIN_BASENAME );
				wp_die(
					wp_kses(
						sprintf(
							__( 'The All-in-One WP Migration plugin could not be activated automatically. Please <a href="%s">activate it manually</a> before activating this extension.', AI1WMUE_PLUGIN_NAME ),
							esc_url( admin_url( 'plugins.php' ) )
						),
						ai1wmue_allowed_html_tags()
					)
				);
			}
		}
	}
}
