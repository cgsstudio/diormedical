<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('WP_Optimize_Dashboard_Images')) :

/**
 * Collects all data needed to render the Media / Images dashboard card.
 */
class WP_Optimize_Dashboard_Images implements WP_Optimize_Dashboard_Interface {

	/**
	 * @return WpoDashboardImagesData
	 */
	public function collect(): array {
		global $wpdb;

		$smush_options   = Updraft_Smush_Manager()->get_smush_options();
		$auto_smush      = !empty($smush_options['autosmush']);
		$webp_conversion = !empty($smush_options['webp_conversion']);

		$optimised_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				'_wpo-smush-complete',
				'1'
			)
		);

		$saved_bytes = (int) WP_Optimize()->get_options()->get_option('total_bytes_saved', 0);

		return array(
			'active'          => $auto_smush,
			'label'           => __('Images', 'wp-optimize'),
			'optimised_count' => $optimised_count,
			'unused_count'    => $this->get_unused_images_count(),
			'saved_bytes'     => $saved_bytes,
			'saved_human'     => size_format($saved_bytes, 1) ?: '0 B',
			'auto_optimize'   => $auto_smush,
			'webp_conversion' => $webp_conversion,
			'is_premium'      => WP_Optimize::is_premium(),
		);
	}

	/**
	 * Returns the total count of images flagged as unused by the WPO media scan.
	 *
	 * The premium WP_Optimization_images::get_unused_images_count() only counts
	 * `unused_images_files` (orphaned filesystem files) and misses `unused_posts_images`
	 * (media-library attachments not used in any post content), which is what the
	 * Unused Images tab actually scans and displays. We read both transient caches
	 * directly and sum them.
	 *
	 * @return int
	 */
	private function get_unused_images_count(): int {
		if (!WP_Optimize::is_premium()) return 0;

		$blog_id  = get_current_blog_id();
		$prefix   = 'wpo_images_cache_' . $blog_id . '_';
		$cache    = WP_Optimize_Transients_Cache::get_instance();

		$posts = $cache->get($prefix . 'unused_posts_images');
		$files = $cache->get($prefix . 'unused_images_files');

		$count = 0;
		if (is_array($posts)) $count += count($posts);
		if (is_array($files)) $count += count($files);

		return $count;
	}
}
endif;
