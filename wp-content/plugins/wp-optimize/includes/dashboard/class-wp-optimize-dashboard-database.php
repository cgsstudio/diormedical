<?php
if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('WP_Optimize_Dashboard_Database')) :
/**
 * Collects all data needed to render the Database dashboard card.
 */
class WP_Optimize_Dashboard_Database implements WP_Optimize_Dashboard_Interface {

	/**
	 * @return WpoDashboardDatabaseData
	 */
	public function collect(): array {
		$wp_optimize_commands = new WP_Optimize_Commands();
		$status_data = $wp_optimize_commands->get_all_status_data();

		$scheduled      = $status_data['scheduled_optimizations_enabled'];
		$raw_schedules  = (is_array($status_data['scheduled_optimizations'])) ? $status_data['scheduled_optimizations'] : array();
		$active_schedules = array_filter($raw_schedules, function ($s) {
			return is_array($s) && isset($s['status']) && is_scalar($s['status']) && 1 === absint($s['status']);
		});
		$schedule_count = $scheduled ? count($active_schedules) : 0;

		$last_schedule_type = '';
		if (!empty($active_schedules)) {
			$last_event = end($active_schedules);
			if (is_array($last_event) && !empty($last_event['schedule_type'])) {
				$schedule_type      = $last_event['schedule_type'];
				$last_schedule_type = is_scalar($schedule_type) ? ucfirst(substr((string) $schedule_type, 4)) : '';
			}
		}

		if ('' === $last_schedule_type && $scheduled) {
			$schedule_type_id = WP_Optimize()->get_options()->get_option('schedule-type', 'wpo_weekly');
			if ('wpo_otherweekly' === $schedule_type_id) $schedule_type_id = 'wpo_fortnightly';
			$schedule_types     = WP_Optimize::get_schedule_types();
			$last_schedule_type = isset($schedule_types[$schedule_type_id]) ? $schedule_types[$schedule_type_id] : '';
		}

		$schedules_summary = $this->build_schedules_summary($scheduled, $active_schedules, $last_schedule_type);

		$next_ts    = $status_data['next_optimization_timestamp'];
		$next_human = (is_int($next_ts) && $next_ts > 0) ? date_i18n('F j, Y g:i A', $next_ts) : __('Not scheduled', 'wp-optimize');
		$last_run   = is_numeric($status_data['last_optimized_ts']) ? (int) $status_data['last_optimized_ts'] : 0;
		$last_cleaned_bytes = floatval(WP_Optimize()->get_options()->get_option('last-run-cleaned-bytes', '0'));
		$last_cleaned_bytes_formatted = size_format((int) $last_cleaned_bytes, 1) ?: '';
		$last_cleaned_human = $last_cleaned_bytes > 0 ? $last_cleaned_bytes_formatted : '';
		$last_run_human = $last_run > 0 ? WP_Optimize_Utils::human_time_diff_or_never($last_run) : '';

		return array(
			'active'                => $scheduled || !empty($last_run),
			'label'                 => __('Database', 'wp-optimize'),
			'scheduled'             => (bool) $scheduled,
			'schedule_count'        => $schedule_count,
			'schedule_label'        => $last_schedule_type,
			'schedules_summary'     => $schedules_summary,
			'next_run_human'        => $next_human,
			'backup_before_cleanup' => 'true' === WP_Optimize()->get_options()->get_option('enable-auto-backup-scheduled', 'false'),
			'updraftplus_active'    => class_exists('UpdraftPlus'),
			'last_run_human'        => $last_run_human,
			'last_cleaned_human'    => $last_cleaned_human,
		);
	}

	/**
	 * Builds up to 2 schedule summary rows for the dashboard card.
	 *
	 * @param bool                $scheduled
	 * @param array<mixed, mixed> $active_schedules
	 * @param string              $free_label       Fallback label for the free-tier single schedule.
	 * @return array<int, array{frequency: string, tasks_label: string}>
	 */
	private function build_schedules_summary(bool $scheduled, array $active_schedules, string $free_label): array {
		if (!$scheduled) return array();

		$schedule_types = WP_Optimize::get_schedule_types();

		// Premium: use actual schedule entries.
		if (!empty($active_schedules)) {
			$task_labels = $this->get_optimization_labels();
			$rows        = array();

			foreach (array_values($active_schedules) as $schedule) {
				if (count($rows) >= 2) break;
				if (!is_array($schedule)) continue;

				$type_id   = isset($schedule['schedule_type']) && is_string($schedule['schedule_type']) ? $schedule['schedule_type'] : '';
				$frequency = isset($schedule_types[$type_id]) ? (string) $schedule_types[$type_id] : ucfirst(substr($type_id, 4));

				$task_ids = isset($schedule['optimization']) && is_array($schedule['optimization']) ? $schedule['optimization'] : array();
				$names    = array();
				foreach ($task_ids as $id) {
					if (!is_string($id)) continue;
					$names[] = isset($task_labels[$id]) ? $task_labels[$id] : $id;
				}

				$rows[] = array(
					'frequency'   => $frequency,
					'tasks_label' => $this->format_task_list($names),
				);
			}
			return $rows;
		}

		// Free: single schedule — read enabled tasks from the 'auto' option.
		$auto_options  = WP_Optimize()->get_options()->get_option('auto');
		$task_labels   = $this->get_optimization_labels();
		$enabled_names = array();
		if (is_array($auto_options)) {
			foreach ($auto_options as $auto_id => $value) {
				if (!is_scalar($value) || 'true' !== (string) $value) continue;
				if (!is_string($auto_id)) continue;
				$enabled_names[] = isset($task_labels[$auto_id]) ? $task_labels[$auto_id] : $auto_id;
			}
		}
		return array(
			array(
				'frequency'   => $free_label,
				'tasks_label' => $this->format_task_list($enabled_names),
			),
		);
	}

	/**
	 * Returns a map of ID → human label.
	 * Indexes by both the file-based ID (used by premium schedules) and get_auto_id()
	 * (used by the free 'auto' option), so both lookup paths resolve correctly.
	 *
	 * @return array<string, string>
	 */
	private function get_optimization_labels(): array {
		$optimizer     = WP_Optimize()->get_optimizer();
		$optimizations = $optimizer->get_optimizations();
		$map           = array();
		foreach ($optimizations as $id => $opt) {
			if (!is_object($opt)) continue;
			$label           = method_exists($opt, 'settings_label') ? (string) $opt->settings_label() : (string) $id;
			$map[(string) $id] = $label;
			$auto_id           = method_exists($opt, 'get_auto_id') ? (string) $opt->get_auto_id() : (string) $id;
			$map[$auto_id]     = $label;
		}
		return $map;
	}

	/**
	 * Formats up to 3 task names; appends "..." if more exist.
	 *
	 * @param string[] $names
	 * @return string
	 */
	private function format_task_list(array $names): string {
		if (empty($names)) return '';
		$shown = array_slice($names, 0, 3);
		$label = '';
		$count = count($shown);
		if (1 === $count) {
			$label = $shown[0];
		} elseif (2 === $count) {
			$label = $shown[0] . ' & ' . $shown[1];
		} else {
			$label = $shown[0] . ', ' . $shown[1] . ' & ' . $shown[2];
		}
		if (count($names) > 3) $label .= ' ...';
		return $label;
	}
}
endif;
