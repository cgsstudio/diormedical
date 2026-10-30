<?php
if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('WPO_WP_Kses_Compatibility')) :

/**
 * Adds compatibility for WP KSES plugin.
 */
class WPO_WP_Kses_Compatibility {

	/**
	 * Explicit list of data-* attributes used in plugin's HTML output.
	 *
	 * @var array<string>
	 */
	private static $allowed_attrs = array(
		'data-alt-label',
		'data-attachment-id',
		'data-background',
		'data-background-image',
		'data-blog',
		'data-blog_id',
		'data-colname',
		'data-count',
		'data-disabled',
		'data-embed',
		'data-enable',
		'data-filename',
		'data-id',
		'data-iframe-attr',
		'data-label',
		'data-max',
		'data-menuslug',
		'data-mode',
		'data-no-image-dimensions',
		'data-optimizable',
		'data-optimization',
		'data-optimization_id',
		'data-optimization_run_sort_order',
		'data-page',
		'data-post_id',
		'data-saveas',
		'data-sort',
		'data-src',
		'data-srcset',
		'data-tab',
		'data-table',
		'data-tablename',
		'data-title',
		'data-tooltip',
		'data-tweak',
		'data-type',
		'data-url',
		'data-video-url',
		'data-whichpage',
		'data-wpo-lcp',
	);

	/**
	 * Attribute names that are never permitted through this class's filter, regardless of what 'wpo_kses_allowed_attrs' returns.
	 * These grant script execution or resource injection independent of the value assigned to them.
	 *
	 * @var array<string>
	 */
	private static $blocked_attrs = array(
		'style',
		'srcdoc',
		'formaction',
	);

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter('wp_kses_allowed_html', array($this, 'wp_kses_allowed_html'), 10, 2);
	}

	/**
	 * Returns singleton instance.
	 *
	 * @return WPO_WP_Kses_Compatibility
	 */
	public static function instance() {
		static $_instance = null;
		if (null === $_instance) {
			$_instance = new self();
		}
		return $_instance;
	}

	/**
	 * Add extra attributes to allowed HTML tags for wp_kses, for tags that are already permitted in the given context.
	 *
	 * @param array<string, array<string, bool>> $allowed_tags Allowed HTML tags and attributes.
	 * @param string                             $context      Context for which the allowed tags are being filtered.
	 *
	 * @return array<string, array<string, bool>> Modified allowed HTML tags and attributes.
	 */
	public function wp_kses_allowed_html($allowed_tags, $context) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $context is required by the filter but not used in this function
		$attrs = $this->sanitize_allowed_attrs(
			apply_filters('wpo_kses_allowed_attrs', self::$allowed_attrs)
		);

		if (!is_array($allowed_tags)) {
			return $allowed_tags;
		}

		foreach (array_keys($allowed_tags) as $tag) {
			foreach ($attrs as $attr) {
				$allowed_tags[$tag][$attr] = true;
			}
		}

		return $allowed_tags;
	}

	/**
	 * Validate a list of attribute names before they're merged into kses's allowlist.
	 *
	 * $allowed_attrs is exposed via the 'wpo_kses_allowed_attrs' filter,
	 * so third-party code can add to or replace the list before it reaches wp_kses_allowed_html().
	 * Without this check, a hook (malicious or just careless) could inject event-handler
	 * attributes or other script-bearing attribute names directly into kses's allowlist,
	 * defeating the sanitizer it's meant to support.
	 * This rejects malformed names, event handlers (on*), and a small denylist of other high-risk attribute names
	 *
	 * @param mixed $attrs Attribute list, expected to be an array of strings.
	 *
	 * @return array<string> Filtered list of attribute names safe to add to kses's allowlist.
	 */
	private function sanitize_allowed_attrs($attrs) {
		if (!is_array($attrs)) {
			return self::$allowed_attrs;
		}

		/** @var array<string> $sanitized */
		$sanitized = array();

		foreach ($attrs as $attr) {
			if (!is_string($attr)) {
				continue;
			}

			if (!preg_match('/^[a-z][a-z0-9_-]*$/', $attr)) {
				continue;
			}

			if (0 === stripos($attr, 'on')) {
				continue;
			}

			if (in_array(strtolower($attr), self::$blocked_attrs, true)) {
				continue;
			}

			$sanitized[] = $attr;
		}

		return $sanitized;
	}
}

endif;
