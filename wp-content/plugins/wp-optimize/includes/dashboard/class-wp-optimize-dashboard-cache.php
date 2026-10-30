<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('WP_Optimize_Dashboard_Cache')) :

/**
 * Collects all data needed to render the Page Cache dashboard card.
 */
class WP_Optimize_Dashboard_Cache implements WP_Optimize_Dashboard_Interface {

	/**
	 * @return WpoDashboardCacheData
	 */
	public function collect(): array {
		$cache_manager = WP_Optimize()->get_page_cache();
		$config        = $cache_manager->config->get();
		$active        = !empty($config['enable_page_caching']);

		$size_info = $cache_manager->get_cache_size();

		$preloader_status = WP_Optimize_Page_Cache_Preloader::instance()->get_status_info();
		$file_count       = is_array($preloader_status) ? ($preloader_status['file_count'] ?? 0) : 0;
		$files_cached     = is_scalar($file_count) ? (int) $file_count : 0;
		$last_cleared     = (int) ($config['last_cleared'] ?? 0);

		$cache_size_bytes = isset($size_info['size']) ? (int) $size_info['size'] : 0;

		$cache_size_human = size_format($cache_size_bytes, 1) ?: '0 B';

		return array(
			'active'             => $active,
			'label'              => __('Page cache', 'wp-optimize'),
			'files_cached'       => $files_cached,
			'cache_size_bytes'   => $cache_size_bytes,
			'cache_size_human'   => $cache_size_bytes > 0 ? $cache_size_human : '0 B',
			'last_cleared_human' => WP_Optimize_Utils::human_time_diff_or_never($last_cleared),
			'gzip_active'        => (bool) WP_Optimize()->get_options()->get_option('is_gzip_compression_enabled', false),
			'country_caching'    => (bool) ($config['enable_cache_per_country'] ?? false), // Premium — always false in free.
			'is_premium'         => WP_Optimize::is_premium(),
		);
	}
}
endif;
