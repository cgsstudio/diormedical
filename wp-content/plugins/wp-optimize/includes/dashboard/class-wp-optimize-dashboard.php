<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('WP_Optimize_Dashboard')) :

/**
 * WP-Optimize Dashboard orchestrator.
 *
 * Assembles the full data payload the dashboard template needs.
 * Heavy lifting is delegated to per-module data classes.
 */
class WP_Optimize_Dashboard {

	/** @var WP_Optimize_Dashboard */
	private static $instance = null;

	/** @return self */
	public static function instance(): self {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
	}

	/**
	 * Full payload for the dashboard template.
	 *
	 * @return array<string, mixed>
	 */
	public function get_dashboard_data(): array {
		$module_data = $this->get_modules_data();
		$modules     = $this->get_module_statuses($module_data);
		$setup       = $this->get_setup_progress($modules);

		return array(
			'modules'    => $modules,
			'savings'    => $this->get_savings_summary($module_data['cache'], $module_data['images'], $module_data['minify']),
			'cache'      => $module_data['cache'],
			'images'     => $module_data['images'],
			'minify'     => $module_data['minify'],
			'database'   => $module_data['database'],
			'setup'      => $setup,
			'banner'     => $this->get_banner_view_data($modules, $setup),
			'is_premium' => WP_Optimize::is_premium(),
		);
	}

	/**
	 * Builds all pre-computed strings the setup-banner template needs for display.
	 * Keeps the template free of logic.
	 *
	 * @param array<string, array{active: bool, label: string, scheduled: bool}>                           $modules Output of get_module_statuses().
	 * @param array{active_modules: int, total_modules: int, pending_modules: string[], running_days: int} $setup   Output of get_setup_progress().
	 * @return array<string, mixed>
	 */
	private function get_banner_view_data(array $modules, array $setup): array {
		$active_count = (int) $setup['active_modules'];
		$total_count  = (int) $setup['total_modules'];
		$running_days = (int) $setup['running_days'];
		$is_complete  = $total_count === $active_count;
		$is_start     = 0 === $active_count;

		$module_labels = array(
			'cache'    => __('caching', 'wp-optimize'),
			'images'   => __('image compression', 'wp-optimize'),
			'minify'   => __('minification', 'wp-optimize'),
			'database' => __('database cleanup', 'wp-optimize'),
		);

		$cta_page_map = array(
			'cache'    => 'wpo_cache',
			'images'   => 'wpo_images',
			'minify'   => 'wpo_minify',
			'database' => 'wpo_database',
		);

		$cta_label_map = array(
			'cache'    => __('Set up cache', 'wp-optimize'),
			'images'   => __('Set up images', 'wp-optimize'),
			'minify'   => __('Setup minification', 'wp-optimize'),
			'database' => __('Set up database', 'wp-optimize'),
		);

		$eyebrow = sprintf(
			/* translators: %1$d active, %2$d total */
			__('%1$d OF %2$d MODULES ACTIVE', 'wp-optimize'),
			$active_count,
			$total_count
		);

		if ($is_start) {
			$eyebrow  = __('Get started', 'wp-optimize');
			$title    = __('Make your site faster in a few clicks', 'wp-optimize');
			$subtitle = __('WP-Optimize can cache pages, compress images, clean your database, and minify code.', 'wp-optimize')
				. ' ' . __('Pick a module below or let us set up the basics for you.', 'wp-optimize');

			global $wp_version;
			$onboarding_supported = version_compare(PHP_VERSION, '7.4', '>=') && version_compare($wp_version, '6.2', '>=');
			$cta_href  = $onboarding_supported ? WP_Optimize()->get_onboarding()->get_reopen_onboarding_url() : '';
			$cta_label = $onboarding_supported ? __('Quick setup', 'wp-optimize') : '';
		} elseif ($is_complete) {
			$title  = __('Your site is optimised', 'wp-optimize');
			$active_labels = $this->collect_module_labels($modules, $module_labels, true);
			$subtitle = ucfirst(sprintf(
			/* translators: %s = comma-separated module names */
			  _n('%s is active.', '%s are active.', count($active_labels), 'wp-optimize'),
			  self::join_with_and($active_labels)
			));
			$subtitle = $active_labels ? $subtitle : '';
			$cta_href  = '';
			$cta_label = '';
		} else {
			$title          = __('You\'re almost set up', 'wp-optimize');
			$active_labels  = $this->collect_module_labels($modules, $module_labels, true);
			$pending_labels = $this->collect_module_labels($modules, $module_labels, false);
			$subtitle       = '';
			if ($active_labels) {
				$subtitle .= ucfirst(sprintf(
					/* translators: %s = comma-separated module names */
					_n('%s is active.', '%s are active.', count($active_labels), 'wp-optimize'),
					self::join_with_and($active_labels)
				)) . ' ';
			}
			if ($pending_labels) {
				$subtitle .= ucfirst(sprintf(
					/* translators: %s = comma-separated module names */
					_n('%s is available when you\'re ready.', '%s are available when you\'re ready.', count($pending_labels), 'wp-optimize'),
					self::join_with_and($pending_labels)
				));
			}

			$pending_key = $setup['pending_modules'][0] ?? null;
			if (null !== $pending_key && isset($cta_page_map[$pending_key])) {
				$cta_href  = WP_Optimize()->get_options()->admin_page_url($cta_page_map[$pending_key]);
				$cta_label = $cta_label_map[$pending_key];
			} else {
				$cta_href  = '';
				$cta_label = '';
			}
		}
		$running_label = sprintf(
		/* translators: %d = days */
		  _n('Running for %d day', 'Running for %d days', $running_days, 'wp-optimize'),
		  $running_days
		);
		$running_label = $running_days > 0 ? $running_label : __('Installed today', 'wp-optimize');

		return array(
			'is_start'      => $is_start,
			'is_complete'   => $is_complete,
			'eyebrow'       => $eyebrow,
			'title'         => $title,
			'subtitle'      => $subtitle,
			'running_label' => $running_label,
			'cta_href'      => $cta_href,
			'cta_label'     => $cta_label,
		);
	}

	/**
	 * Collects human-readable module labels for either active or pending modules.
	 *
	 * @param array<string, array{active: bool, label: string, scheduled: bool}> $modules     Module statuses.
	 * @param array<string, string>                                              $label_map   Human-readable label per module key.
	 * @param bool                                                               $active_only True = collect active, false = collect pending.
	 * @return string[]
	 */
	private function collect_module_labels(array $modules, array $label_map, bool $active_only): array {
		$result = array();
		foreach ($modules as $key => $module) {
			$is_active = $module['active'] || $module['scheduled'];
			if ($active_only === $is_active) {
				$result[] = $label_map[$key] ?? strtolower($module['label']);
			}
		}
		return $result;
	}

	/**
	 * Joins items with ", " and "and" before the last entry.
	 * ["A"] → "A"; ["A","B"] → "A and B"; ["A","B","C"] → "A, B, and C".
	 *
	 * @param string[] $items
	 * @return string
	 */
	private static function join_with_and(array $items): string {
		$count = count($items);
		if (0 === $count) return '';
		if (1 === $count) return $items[0];
		if (2 === $count) return $items[0] . ' ' . __('and', 'wp-optimize') . ' ' . $items[1];
		$last = array_pop($items);
		return implode(', ', $items) . ', ' . __('and', 'wp-optimize') . ' ' . $last;
	}

	/**
	 * Instantiates each module class and calls collect() once.
	 *
	 * @return array{cache: WpoDashboardCacheData, images: WpoDashboardImagesData, minify: WpoDashboardMinifyData, database: WpoDashboardDatabaseData}
	 */
	private function get_modules_data(): array {
		return array(
			'cache'    => (new WP_Optimize_Dashboard_Cache())->collect(),
			'images'   => (new WP_Optimize_Dashboard_Images())->collect(),
			'minify'   => (new WP_Optimize_Dashboard_Minify())->collect(),
			'database' => (new WP_Optimize_Dashboard_Database())->collect(),
		);
	}

	/**
	 * Returns active/label/features status for each module.
	 *
	 * @param array<string, array<string, mixed>> $module_data Already-fetched module data arrays.
	 * @return array<string, array{active: bool, label: string, scheduled: bool}>
	 */
	private function get_module_statuses(array $module_data): array {
		$statuses = array();
		foreach ($module_data as $key => $data) {
			$statuses[$key] = array(
				'active'    => !empty($data['active']),
				'label'     => is_string($data['label']) ? $data['label'] : $key,
				'scheduled' => !empty($data['scheduled']),
			);
		}
		return $statuses;
	}

	/**
	 * Aggregates byte savings from all modules.
	 * Segment colors: Media=#1A80BB, Database=#EAC11C, Cache=#B8B8B8, Files=#605F69.
	 *
	 * @param WpoDashboardCacheData  $cache
	 * @param WpoDashboardImagesData $images
	 * @param WpoDashboardMinifyData $minify
	 *
	 * @return array<string, mixed>
	 */
	private function get_savings_summary($cache, $images, $minify): array {
		$image_bytes = $images['saved_bytes'];
		$db_bytes    = (int) WP_Optimize()->get_options()->get_option('db_bytes_saved', 0);
		$cache_bytes = $cache['cache_size_bytes'];
		$files_bytes = $minify['saved_bytes'];
		$total       = $image_bytes + $db_bytes + $cache_bytes + $files_bytes;

		$total_human_formatted = size_format(max(0, $total), 1);
		$parts  = is_string($total_human_formatted) ? explode(' ', $total_human_formatted, 2) : array('0', 'B');
		$number = $parts[0];
		$unit   = $parts[1] ?? 'B';

		$total_human = sprintf('%s <span class="size-unit">%s</span>', esc_html($number), esc_html($unit));

		return array(
			'total_bytes'    => $total,
			'total_human'    => $total_human,
			'image_bytes'    => $image_bytes,
			'image_human'    => (string) size_format($image_bytes, 1),
			'database_bytes' => $db_bytes,
			'database_human' => (string) size_format($db_bytes, 1),
			'cache_bytes'    => $cache_bytes,
			'cache_human'    => (string) size_format($cache_bytes, 1),
			'files_bytes'    => $files_bytes,
			'files_human'    => (string) size_format($files_bytes, 1),
			'image_pct'      => self::savings_pct($image_bytes, $total),
			'database_pct'   => self::savings_pct($db_bytes,    $total),
			'cache_pct'      => self::savings_pct($cache_bytes, $total),
			'files_pct'      => self::savings_pct($files_bytes, $total),
		);
	}

	/**
	 * Returns how many modules are active and which are still pending setup.
	 *
	 * @param array<string, array{active: bool, label: string, scheduled: bool}> $modules Output of get_module_statuses().
	 * @return array{active_modules: int, total_modules: int, pending_modules: string[], running_days: int}
	 */
	private function get_setup_progress(array $modules): array {
		$total   = count($modules);
		$active  = 0;
		$pending = array();

		foreach ($modules as $key => $data) {
			if (!empty($data['active']) || !empty($data['scheduled'])) {
				$active++;
			} else {
				$pending[] = $key;
			}
		}

		return array(
			'total_modules'   => $total,
			'active_modules'  => $active,
			'pending_modules' => $pending,
			'running_days'    => $this->days_since_activation(),
		);
	}

	/**
	 * Returns the percentage share of $part in $total, clamped to [1, 100].
	 * Returns 0 if either value is non-positive.
	 *
	 * @param int $part
	 * @param int $total
	 * @return int
	 */
	private static function savings_pct(int $part, int $total): int {
		if ($total <= 0 || $part <= 0) return 0;
		return min(100, max(1, (int) round(($part / $total) * 100)));
	}

	/**
	 * Returns the number of days since the plugin was first activated.
	 *
	 * @return int Days elapsed, or 0 if the activation timestamp is not recorded.
	 */
	private function days_since_activation(): int {
		$activated = WP_Optimize()->get_options()->get_option('newly-activated', null);
		// Legacy installs stored `true` instead of a timestamp — treat as unknown.
		if (!is_numeric($activated) || (int) $activated <= 0) return 0;
		return (int) floor((time() - (int) $activated) / DAY_IN_SECONDS);
	}
}
endif;
