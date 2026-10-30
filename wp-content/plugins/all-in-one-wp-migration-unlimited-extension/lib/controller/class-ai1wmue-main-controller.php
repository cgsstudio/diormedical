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

class Ai1wmue_Main_Controller {

	/**
	 * Main Application Controller
	 *
	 * @return object
	 */
	public function __construct() {
		register_activation_hook( AI1WMUE_PLUGIN_BASENAME, 'Ai1wmue_Upgrader::sync_options' );
		register_activation_hook( AI1WMUE_PLUGIN_BASENAME, 'Ai1wmue_Report_Controller::on_activate' );
		register_activation_hook( AI1WMUE_PLUGIN_BASENAME, 'Ai1wmue_Install_Controller::on_activate' );

		// Activate hooks
		$this->activate_actions();
		$this->activate_filters();

		// Load plugin defaults
		$this->enqueue_scripts_and_styles();
		$this->maybe_sync_options();
	}

	/**
	 * Outputs menu icon between head tags (do not use template to render the code)
	 *
	 * @return void
	 */
	public function admin_head() {
		?>
		<style type="text/css" media="all">
			.ai1wm-label {
				border: 1px solid #5cb85c;
				background-color: transparent;
				color: #5cb85c;
				cursor: pointer;
				text-transform: uppercase;
				font-weight: 600;
				outline: none;
				transition: background-color 0.2s ease-out;
				padding: .2em .6em;
				font-size: 0.8em;
				border-radius: 5px;
				text-decoration: none !important;
			}

			.ai1wm-label:hover {
				background-color: #5cb85c;
				color: #fff;
			}
		</style>
		<?php
	}

	/**
	 * Display admin notices (do not use template to render the code)
	 *
	 * @return void
	 */
	public function admin_notices() {
		// Check if the base plugin is not installed or activated
		if ( is_wp_error( validate_plugin( 'all-in-one-wp-migration/all-in-one-wp-migration.php' ) ) ) {
			?>
			<div class="error">
				<p>
					<?php
					echo wp_kses(
						sprintf(
							__( 'Unlimited Extension requires All-in-One WP Migration plugin to be installed. <a href="%s">Install Now</a> or <a href="%s" target="_blank">Download Manually</a>', AI1WMUE_PLUGIN_NAME ),
							esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=all-in-one-wp-migration' ), 'install-plugin_all-in-one-wp-migration' ) ),
							esc_url( 'https://wordpress.org/plugins/all-in-one-wp-migration/' )
						),
						ai1wmue_allowed_html_tags()
					);
					?>
				</p>
			</div>
			<?php
		} elseif ( is_plugin_inactive( 'all-in-one-wp-migration/all-in-one-wp-migration.php' ) ) {
			?>
			<div class="error">
				<p>
					<?php
					echo wp_kses(
						sprintf(
							__( 'Unlimited Extension requires All-in-One WP Migration plugin to be activated. <a href="%s">Activate Now</a>', AI1WMUE_PLUGIN_NAME ),
							esc_url( wp_nonce_url( 'plugins.php?action=activate&plugin=all-in-one-wp-migration/all-in-one-wp-migration.php', 'activate-plugin_all-in-one-wp-migration/all-in-one-wp-migration.php' ) )
						),
						ai1wmue_allowed_html_tags()
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @return void
	 */
	public function enqueue_scripts_and_styles() {
		add_action( 'admin_enqueue_scripts', 'Ai1wmue_Backups_Controller::enqueue_scripts_and_styles', 60 );
		add_action( 'admin_enqueue_scripts', 'Ai1wmue_Export_Controller::enqueue_scripts_and_styles', 60 );
		add_action( 'admin_enqueue_scripts', 'Ai1wmue_Import_Controller::enqueue_scripts_and_styles', 60 );
		add_action( 'admin_enqueue_scripts', 'Ai1wmue_Reset_Controller::enqueue_scripts_and_styles', 60 );
		add_action( 'admin_enqueue_scripts', 'Ai1wmue_Schedules_Controller::enqueue_scripts_and_styles', 60 );
		add_action( 'admin_enqueue_scripts', 'Ai1wmue_Settings_Controller::enqueue_scripts_and_styles', 60 );
	}

	/**
	 * Run the upgrader once after any update, including background updates
	 *
	 * @return void
	 */
	public function maybe_sync_options() {
		add_action( 'init', 'Ai1wmue_Upgrader::sync_options', 60 );
	}

	/**
	 * Load WP ajax for settings and import controllers
	 *
	 * @return void
	 */
	public function load_wp_ajax() {
		if ( current_user_can( 'ai1wm_export_site' ) ) {
			add_action( 'wp_ajax_ai1wmue_list_files', 'Ai1wmue_Advanced_Options_Controller::list_files' );
			add_action( 'wp_ajax_ai1wmue_list_folders', 'Ai1wmue_Pro_Settings_Controller::list_folders' );
			add_action( 'wp_ajax_ai1wmue_reset_tools', 'Ai1wmue_Reset_Tools_Controller::reset_tools' );

			add_action( 'wp_ajax_ai1wmue_schedule_event_delete', 'Ai1wmue_Schedule_Events_Controller::delete' );
			add_action( 'wp_ajax_ai1wmue_schedule_event_log', 'Ai1wmue_Schedule_Events_Controller::event_log' );
			add_action( 'wp_ajax_ai1wmue_schedule_event_clean', 'Ai1wmue_Schedule_Events_Controller::event_clean' );
			add_action( 'wp_ajax_ai1wmue_schedule_event_manual_run', 'Ai1wmue_Schedule_Events_Controller::manual_run' );
			add_action( 'wp_ajax_ai1wmue_schedule_event_status', 'Ai1wmue_Schedule_Events_Controller::event_status' );
		}
	}

	/**
	 * Initializes language domain for the plugin
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( AI1WMUE_PLUGIN_NAME, false, false );
	}

	/**
	 * WP CLI commands
	 *
	 * @return void
	 */
	public function cli_init() {
		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'ai1wm', 'Ai1wmue_Backup_CLI_Command', array( 'shortdesc' => __( 'All-in-One WP Migration Command', AI1WMUE_PLUGIN_NAME ) ) );
		}
	}

	/**
	 * Load WP cron hooks
	 *
	 * @return void
	 */
	public function load_cron_hooks() {
		remove_all_actions( 'ai1wmve_schedule_event' );

		// Add cron hook runner
		add_action( Ai1wmue_Schedule_Event::CRON_HOOK, 'Ai1wmue_Schedule_Events_Controller::run' );
	}

	/**
	 * Load admin menu and notices
	 *
	 * @return void
	 */
	public function load_admin_menu() {
		if ( defined( 'AI1WM_PLUGIN_NAME' ) ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 60 );
		} else {
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 60 );
		}
	}

	/**
	 * Load schedule buttons
	 *
	 * @return void
	 */
	public function load_schedule_buttons() {
		add_filter( 'ai1wmue_schedule_buttons', 'Ai1wmue_Schedule_Events_Controller::buttons' );
		add_filter( 'ai1wmue_incremental_storages', 'Ai1wmue_Schedule_Events_Controller::incremental_storages' );
	}

	/**
	 * Load export and import commands
	 *
	 * @return void
	 */
	public function load_plugin_commands() {
		if ( ai1wmue_is_running() ) {
			remove_all_filters( 'ai1wm_export', 280 );
			remove_all_filters( 'ai1wm_import', 5 );

			add_filter( 'ai1wm_export', 'Ai1wmue_Export_Retention::execute', 280 );
			add_filter( 'ai1wm_import', 'Ai1wmue_Import_Upload::execute', 5 );
		}

		add_filter( 'ai1wm_export', 'Ai1wmue_Export_Compatibility::execute', 10 );
		add_filter( 'ai1wm_import', 'Ai1wmue_Import_Compatibility::execute', 10 );

		add_filter( 'ai1wm_import', 'Ai1wmue_Import_Select_Settings::execute', 290 );
		add_filter( 'ai1wm_import', 'Ai1wmue_Import_Update_Settings::execute', 315 );
	}

	/**
	 * Load reset commands
	 *
	 * @return void
	 */
	public function load_reset_commands() {
		remove_all_filters( 'ai1wm_reset' );

		add_filter( 'ai1wm_reset', 'Ai1wmue_Reset_Compatibility::execute', 10 );
		add_filter( 'ai1wm_reset', 'Ai1wmue_Reset_Init::execute', 10 );
		add_filter( 'ai1wm_reset', 'Ai1wmue_Reset_Plugins::execute', 50 );
		add_filter( 'ai1wm_reset', 'Ai1wmue_Reset_Themes::execute', 100 );
		add_filter( 'ai1wm_reset', 'Ai1wmue_Reset_Media::execute', 150 );
		add_filter( 'ai1wm_reset', 'Ai1wmue_Reset_Database::execute', 200 );
	}

	/**
	 * Load status events
	 *
	 * @return void
	 */
	public function load_status_events() {
		if ( ai1wmue_is_running() ) {
			add_action( 'ai1wm_status_export_done', 'Ai1wmue_Reset_Tools_Controller::reset_labels' );
		}

		add_action( 'ai1wm_status_export_done', 'Ai1wmue_Report_Controller::on_export' );
		add_action( 'ai1wm_status_import_done', 'Ai1wmue_Report_Controller::on_import' );
		add_action( 'ai1wm_status_import_done', 'Ai1wmue_Report_Controller::on_restore' );

		add_action( 'ai1wm_status_export_done', 'Ai1wmue_Schedule_Events_Controller::log_success' );
		add_action( 'ai1wm_status_export_init', 'Ai1wmue_Schedule_Events_Controller::log_running' );
		add_action( 'ai1wm_status_export_error', 'Ai1wmue_Schedule_Events_Controller::log_failed', 10, 2 );

		// Library
		remove_action( 'ai1wm_status_export_done', 'Ai1wmve_Schedules_Controller::log_success' );
		remove_action( 'ai1wm_status_export_init', 'Ai1wmve_Schedules_Controller::log_running' );
		remove_action( 'ai1wm_status_export_error', 'Ai1wmve_Schedules_Controller::log_failed' );
	}

	/**
	 * Update schedule details
	 *
	 * @return void
	 */
	public function admin_post_schedules() {
		add_action( 'admin_post_ai1wmue_schedule_event_save', 'Ai1wmue_Schedule_Events_Controller::save' );
	}

	/**
	 * Update settings details
	 *
	 * @return void
	 */
	public function admin_post_settings() {
		add_action( 'admin_post_ai1wmue_settings', 'Ai1wmue_Pro_Settings_Controller::settings' );
	}

	/**
	 * Enable advanced options
	 *
	 * @return void
	 */
	public function load_advanced_options() {
		// Add export compression types
		if ( ! has_action( 'ai1wm_export_compression_types' ) ) {
			add_action( 'ai1wm_export_compression_types', 'Ai1wmue_Advanced_Options_Controller::compression_types' );
		}

		// Add export inactive themes
		if ( ! has_action( 'ai1wm_export_inactive_themes' ) ) {
			add_action( 'ai1wm_export_inactive_themes', 'Ai1wmue_Advanced_Options_Controller::inactive_themes' );
		}

		// Add export inactive plugins
		if ( ! has_action( 'ai1wm_export_inactive_plugins' ) ) {
			add_action( 'ai1wm_export_inactive_plugins', 'Ai1wmue_Advanced_Options_Controller::inactive_plugins' );
		}

		// Add export cache files
		if ( ! has_action( 'ai1wm_export_cache_files' ) ) {
			add_action( 'ai1wm_export_cache_files', 'Ai1wmue_Advanced_Options_Controller::cache_files' );
		}

		// Add export exclude files
		if ( ! has_action( 'ai1wm_export_advanced_settings' ) ) {
			add_action( 'ai1wm_export_advanced_settings', 'Ai1wmue_Advanced_Options_Controller::exclude_files' );
		}

		// Add export exclude db tables
		if ( ! has_action( 'ai1wm_export_exclude_db_tables' ) ) {
			add_action( 'ai1wm_export_exclude_db_tables', 'Ai1wmue_Advanced_Options_Controller::exclude_db_tables' );
		}

		// Add export include db tables
		if ( ! has_action( 'ai1wm_export_include_db_tables' ) ) {
			add_action( 'ai1wm_export_include_db_tables', 'Ai1wmue_Advanced_Options_Controller::include_db_tables' );
		}
	}

	/**
	 * Add links to plugin list page
	 *
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file === AI1WMUE_PLUGIN_BASENAME ) {
			$links[] = __( '<a href="https://help.servmask.com/knowledgebase/unlimited-extension-user-guide/" target="_blank">User Guide</a>', AI1WMUE_PLUGIN_NAME );
			$links[] = __( '<a href="https://servmask.com/contact-support" target="_blank">Contact Support</a>', AI1WMUE_PLUGIN_NAME );
		}

		return $links;
	}

	/**
	 * Add pro text to plugin page
	 *
	 * @return string
	 */
	public function plugin_pro_text() {
		return Ai1wm_Template::get_content( 'main/pro', array(), AI1WMUE_TEMPLATES_PATH );
	}

	/**
	 * Register listeners for actions
	 *
	 * @return void
	 */
	protected function activate_actions() {
		add_action( 'admin_head', array( $this, 'admin_head' ), 60 );

		add_action( 'cli_init', array( $this, 'cli_init' ), 60 );
		add_action( 'admin_init', array( $this, 'load_wp_ajax' ), 60 );
		add_action( 'admin_init', array( $this, 'load_plugin_textdomain' ), 60 );
		add_action( 'admin_init', array( $this, 'load_schedule_buttons' ), 60 );
		add_action( 'admin_init', array( $this, 'load_advanced_options' ), 60 );

		add_action( 'admin_init', array( $this, 'admin_post_schedules' ), 60 );
		add_action( 'admin_init', array( $this, 'admin_post_settings' ), 60 );

		add_action( 'plugins_loaded', array( $this, 'load_admin_menu' ), 60 );
		add_action( 'plugins_loaded', array( $this, 'load_plugin_commands' ), 60 );
		add_action( 'plugins_loaded', array( $this, 'load_reset_commands' ), 60 );
		add_action( 'plugins_loaded', array( $this, 'load_status_events' ), 60 );
		add_action( 'plugins_loaded', array( $this, 'load_cron_hooks' ), 60 );

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::add_hook( 'ai1wm_cli_commands', array( $this, 'load_plugin_commands' ) );
		}
	}

	/**
	 * Register listeners for filters
	 *
	 * @return void
	 */
	protected function activate_filters() {
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 5, 2 );
		add_filter( 'ai1wm_pro', array( $this, 'plugin_pro_text' ), 60 );
	}

	/**
	 * Register plugin menus
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
			'ai1wm_export',
			__( 'Reset Hub', AI1WMUE_PLUGIN_NAME ),
			__( 'Reset Hub', AI1WMUE_PLUGIN_NAME ),
			'ai1wm_import_site',
			'ai1wmue_reset',
			'Ai1wmue_Reset_Tools_Controller::index'
		);

		add_submenu_page(
			'ai1wm_export',
			__( 'Schedules', AI1WMUE_PLUGIN_NAME ),
			__( 'Schedules', AI1WMUE_PLUGIN_NAME ),
			'ai1wm_export_site',
			'ai1wmue_schedules',
			'Ai1wmue_Schedule_Events_Controller::index'
		);

		add_submenu_page(
			'ai1wm_export',
			__( 'Settings', AI1WMUE_PLUGIN_NAME ),
			__( 'Settings', AI1WMUE_PLUGIN_NAME ),
			'ai1wm_export_site',
			'ai1wmue_settings',
			'Ai1wmue_Pro_Settings_Controller::index'
		);

		remove_submenu_page( 'ai1wm_export', 'ai1wmve_reset' );
		remove_submenu_page( 'ai1wm_export', 'ai1wmve_schedules' );
	}
}
