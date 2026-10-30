<?php

if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('WPO_KD_Submissions_Compatibility')) :

/**
 * Handles compatibility with the KD Submissions plugin.
 *
 * Ensures KD Submissions database tables are associated with Elementor Pro
 * when both plugins are present, allowing them to be optimized alongside
 * Elementor tables.
 */
class WPO_KD_Submissions_Compatibility {

	/**
	 * Elementor Pro plugin identifier.
	 *
	 * @var string
	 */
	const ELEMENTOR_PRO_PLUGIN = 'elementor-pro';

	/**
	 * KD Submissions plugin identifier.
	 *
	 * @var string
	 */
	const KD_SUBMISSIONS_PLUGIN = 'kd-submissions';

	/**
	 * Constructor
	 */
	private function __construct() {
		add_filter('wp_optimize_get_tables', array($this, 'check_kd_submissions_tables'));
		add_filter('wp_optimize_get_table', array($this, 'check_kd_submissions_table'));
	}

	/**
	 * Associates KD Submissions tables with Elementor Pro when it is present.
	 *
	 * @param array<int, stdClass> $tables List of database table objects.
	 * @return array<int, stdClass> Updated list with corrected plugin associations.
	 */
	public function check_kd_submissions_tables($tables) {
		$kd_submissions_status = $this->get_kd_submissions_status();
		$elementor_pro_status = $this->get_elementor_pro_status();
		$is_elementor_pro_available = $elementor_pro_status['installed'] || $elementor_pro_status['active'];

		foreach ($tables as $key => $table) {
			if (!$this->is_kd_submissions_table($table) && !$this->is_elementor_pro_table($table)) {
				continue;
			}

			if (($is_elementor_pro_available || $this->is_elementor_pro_table($table)) && !$this->has_plugin_in_list(self::ELEMENTOR_PRO_PLUGIN, $table->plugin_status)) {
				$tables[$key]->plugin_status[] = array(
					'plugin' => self::ELEMENTOR_PRO_PLUGIN,
					'status' => $elementor_pro_status,
				);

				// Allow to remove the table when Elementor Pro and KD Submissions are inactive.
				$tables[$key]->can_be_removed = !$elementor_pro_status['active'] && !$kd_submissions_status['active'];
			} else {
				// If Elementor Pro is not available, ensure KD Submissions is associated
				if (!$this->has_plugin_in_list(self::KD_SUBMISSIONS_PLUGIN, $table->plugin_status)) {
					$tables[$key]->plugin_status[] = array(
						'plugin' => self::KD_SUBMISSIONS_PLUGIN,
						'status' => $kd_submissions_status,
					);

					// Allow to remove the table when KD Submissions is inactive.
					$tables[$key]->can_be_removed = !$kd_submissions_status['active'];
				}
			}

		}

		return $tables;
	}

	/**
	 * Associates a single KD Submissions table with Elementor Pro, if present.
	 * If the provided table is not a KD Submissions table, it is returned unchanged.
	 *
	 * @param stdClass $table A database table object with a Name property and plugin_status array.
	 * @return stdClass
	 */
	public function check_kd_submissions_table($table) {
		if (!property_exists($table, 'Name')) return $table;

		$tables = array(
			$table->Name => $table,
		);

		$tables = $this->check_kd_submissions_tables($tables);

		return $tables[$table->Name];
	}

	/**
	 * Determines whether the given table belongs to KD Submissions.
	 *
	 * A table is identified as a KD Submissions table if its name matches
	 * the Elementor submissions actions log pattern, or if it contains the
	 * '_e_' infix and is already tagged with the KD Submissions plugin.
	 *
	 * @param stdClass $table A database table object with a Name property and plugin_status array.
	 * @return bool
	 */
	private function is_kd_submissions_table($table) {
		return false !== strpos($table->Name, '_e_')
			&& $this->has_plugin_in_list(self::KD_SUBMISSIONS_PLUGIN, $table->plugin_status);
	}

	/**
	 * Determines whether the given table belongs to Elementor Pro
	 *
	 * @param stdClass $table A database table object with a Name property and plugin_status array.
	 * @return boolean
	 */
	private function is_elementor_pro_table($table) {
		if (preg_match('/e_submissions_actions_log$/', $table->Name)) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether a given plugin is already present in the plugin status list.
	 *
	 * @param string $plugin        The plugin slug to search for.
	 * @param array<int, array{plugin: string, status: array{installed: bool, active: bool}}> $plugin_status The list of plugin status entries.
	 * @return bool
	 */
	private function has_plugin_in_list($plugin, $plugin_status) {
		foreach ($plugin_status as $plugin_info) {
			if ($plugin === $plugin_info['plugin']) return true;
		}

		return false;
	}

	/**
	 * Retrieves the installation and activation status of Elementor Pro.
	 *
	 * @return array{installed: bool, active: bool}
	 */
	private function get_elementor_pro_status() {
		return WP_Optimize()->get_db_info()->get_plugin_status(self::ELEMENTOR_PRO_PLUGIN);
	}

	/**
	 * Retrieves the installation and activation status of KD Submissions.
	 *
	 * @return array{installed: bool, active: bool}
	 */
	private function get_kd_submissions_status() {
		return WP_Optimize()->get_db_info()->get_plugin_status(self::KD_SUBMISSIONS_PLUGIN);
	}


	/**
	 * Returns the singleton instance of this class.
	 *
	 * @return WPO_KD_Submissions_Compatibility
	 */
	public static function instance() {
		static $_instance = null;
		if (null === $_instance) {
			$_instance = new self();
		}
		return $_instance;
	}
}

endif;
