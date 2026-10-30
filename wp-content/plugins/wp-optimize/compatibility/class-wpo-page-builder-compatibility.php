<?php
if (!defined('ABSPATH')) {
	die('No direct access allowed');
}

/**
 * Adds compatibility for Page Builder plugins.
 */
class WPO_Page_Builder_Compatibility {

	/**
	 * Query string parameters used by supported page builders to signal edit mode.
	 *
	 * - fl_builder:      Beaver Builder
	 * - et_fb:           Divi Theme Builder
	 * - ct_builder:      Oxygen Builder
	 * - elementor-preview: Elementor
	 * - oxygen:          Oxygen (alternative trigger)
	 */
	const EDIT_MODE_QUERY_PARAMS = array(
		'fl_builder',
		'et_fb',
		'ct_builder',
		'elementor-preview',
		'oxygen',
	);

	/**
	 * Path fragment that identifies Divi cached assets.
	 */
	const DIVI_CACHE_PATH_FRAGMENT = 'et-cache';

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->disable_webp_alter_html_in_edit_mode();

		add_filter('wpo_minify_file_modification_time', array($this, 'use_file_hash_for_divi_assets'), 10, 2);
	}

	/**
	 * Returns singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		static $instance = null;
		if (null === $instance) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Replaces the modification time of Divi assets with the file hash for WPO Minify.
	 *
	 * @param string $modification_time The original modification time.
	 * @param string $file_path         The absolute path to the file.
	 * @return string
	 */
	public function use_file_hash_for_divi_assets($modification_time, $file_path) {
		if (false !== strpos($file_path, self::DIVI_CACHE_PATH_FRAGMENT)) {
			$hash = hash_file('adler32', $file_path);
			if ($hash) {
				return $hash . '-h';
			}
		}

		return $modification_time;
	}

	/**
	 * Determines whether the current page is being viewed inside a page builder's
	 * edit/preview mode (e.g. Beaver Builder, Divi, Elementor, Oxygen Builder).
	 *
	 * @return bool
	 */
	public static function is_edit_mode(): bool {
		foreach (self::EDIT_MODE_QUERY_PARAMS as $param) {
			if (isset($_GET[$param])) { // phpcs:ignore WordPress.Security.NonceVerification -- Only checking for key existence, not using values.
				return true;
			}
		}

		return false;
	}

	/**
	 * Disables altering HTML for WebP when current page is in edit mode.
	 */
	private function disable_webp_alter_html_in_edit_mode(): void {
		if (self::is_edit_mode()) {
			add_filter('wpo_disable_webp_alter_html', '__return_true');
		}
	}
}
