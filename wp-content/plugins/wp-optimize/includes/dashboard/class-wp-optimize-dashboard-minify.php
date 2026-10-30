<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('WP_Optimize_Dashboard_Minify')) :

/**
 * Collects all data needed to render the File Optimisation / Minify dashboard card.
 */
class WP_Optimize_Dashboard_Minify implements WP_Optimize_Dashboard_Interface {

	/**
	 * @return WpoDashboardMinifyData
	 */
	public function collect(): array {
		$config = wp_optimize_minify_config()->get();
		if (!is_array($config)) {
			$config = wp_optimize_minify_config()->get_defaults();
		}
		$is_premium = WP_Optimize::is_premium();
		$active     = (bool) $config['enabled'];

		$files      = WP_Optimize_Minify_Cache_Functions::get_cached_files();
		$js_count   = count($files['js']);
		$css_count  = count($files['css']);

		$raw_ts      = (int) $config['last-cache-update'];
		$saved_bytes = $this->compute_minify_savings($files);

		return array(
			'active'                   => $active,
			'label'                    => __('File optimisation', 'wp-optimize'),
			'js_enabled'               => (bool) $config['enable_js'],
			'css_enabled'              => (bool) $config['enable_css'],
			'js_file_count'            => $js_count,
			'css_file_count'           => $css_count,
			'fonts_cached_locally'     => $is_premium && (bool) $config['host_local_google_fonts'],
			'analytics_hosted_locally' => $is_premium && (bool) $config['enable_analytics'],
			'is_premium'               => $is_premium,
			'last_rebuilt_ts'          => $raw_ts,
			'last_rebuilt_human'       => WP_Optimize_Utils::human_time_diff_or_never($raw_ts),
			'saved_bytes'              => $saved_bytes,
			'saved_human'              => $saved_bytes > 0 ? size_format($saved_bytes, 1) : '',
		);
	}

	/**
	 * Computes byte savings by comparing original vs minified sizes per cache bundle.
	 *
	 * Savings are summed per bundle (each .json log = one minified output file).
	 * A bundle where the output is larger than its sources (e.g. already-minified vendor
	 * files that gain processing overhead) contributes 0 — it does NOT cancel savings from
	 * other bundles. External/CDN files are skipped; they have no local path to stat.
	 *
	 * @param array<string, mixed> $files Output of WP_Optimize_Minify_Cache_Functions::get_cached_files().
	 * @return int Total bytes saved, never negative.
	 */
	private function compute_minify_savings(array $files): int {
		$home_path  = untrailingslashit(get_home_path());
		$cache_path = WP_Optimize_Minify_Cache_Functions::cache_path();
		$cache_dir  = $cache_path['cachedir'];
		$total      = 0;

		foreach (array('js', 'css') as $ext) {
			if (empty($files[$ext]) || !is_array($files[$ext])) continue;
			foreach ($files[$ext] as $cached) {
				if (!is_array($cached)) continue;
				$log      = isset($cached['log']) && $cached['log'] instanceof stdClass ? $cached['log'] : null;
				$file_url = isset($cached['file_url']) && is_string($cached['file_url']) ? $cached['file_url'] : '';
				if (!$log instanceof stdClass || isset($log->error) || !isset($log->files) || '' === $file_url) continue;

				// Minified combined bundle size.
				$cache_file = $cache_dir . '/' . basename($file_url);
				$min_size   = file_exists($cache_file) ? (int) filesize($cache_file) : 0;
				if (0 === $min_size) continue;

				// Sum original sizes for all local files in this bundle.
				$orig_size = 0;
				foreach ($log->files as $file_log) {
					if (!$file_log instanceof stdClass || empty($file_log->url) || empty($file_log->success)) continue;
					$local_path = $home_path . $file_log->url;
					if (file_exists($local_path)) {
						$orig_size += (int) filesize($local_path);
					}
				}

				// Only count bundles where minification actually reduced the size.
				// Pre-minified vendor files often end up slightly larger — ignore those.
				$total += max(0, $orig_size - $min_size);
			}
		}

		return $total;
	}
}
endif;
