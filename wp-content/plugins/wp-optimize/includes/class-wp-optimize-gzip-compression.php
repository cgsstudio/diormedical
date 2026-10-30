<?php
if (!defined('ABSPATH')) die('Access denied.');

/**
 * Class WP_Optimize_Gzip_Compression
 */
class WP_Optimize_Gzip_Compression {

	use WP_Optimize_HTTP_Error_Codes_Trait;
	
	/**
	 * WP_Optimize_Htaccess instance.
	 *
	 * @var WP_Optimize_Htaccess
	 */
	private $_htaccess;

	/**
	 * WP_Optimize instance.
	 *
	 * @var WP_Optimize
	 */
	private $_wp_optimize;

	/**
	 * Gzip section in htaccess will be wrapped with this comment
	 *
	 * @var string
	 */
	private $_htaccess_section_comment = 'WP-Optimize Gzip compression';

	/**
	 * WP_Optimize_Gzip_Compression constructor.
	 */
	public function __construct() {
		$this->_wp_optimize = WP_Optimize();
		$this->_htaccess = $this->_wp_optimize->get_htaccess();
	}

	/**
	 * Returns singleton instance object
	 *
	 * @return WP_Optimize_Gzip_Compression Returns `WP_Optimize_Gzip_Compression` object
	 */
	public static function instance() {
		static $_instance = null;
		if (null === $_instance) {
			$_instance = new self();
		}
		return $_instance;
	}

	/**
	 * Make http request to $url, get 'server' line and check headers for gzip/brotli encoding option.
	 *
	 * @param string $url
	 *
	 * @return array|WP_Error
	 */
	public function get_headers_information($url) {
		static $cached_headers_information = array();
		if (isset($cached_headers_information[$url])) return $cached_headers_information[$url];

		$response = wp_remote_get($url, array('timeout' => 10));

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);

		if (200 !== $response_code) {
			// translators: %1$s is a requested URL, %2$s is the HTTP response code
			return new WP_Error($response_code, sprintf(__('Unexpected response code when trying to get response for %1$s: %2$s (expected 200)', 'wp-optimize'), $url, $response_code));
		}
	
		$headers = wp_remote_retrieve_headers($response);

		if (empty($headers)) {
			// translators: %s is a requested URL
			return new WP_Error($response_code, sprintf(__('Unable to retrieve HTTP headers information for %s', 'wp-optimize'), $url));
		}

		if (is_object($headers) && method_exists($headers, 'getAll')) {
			$headers = $headers->getAll();
		}

		$headers_information = array(
			'server' => array_key_exists('server', $headers) ? $headers['server'] : '',
		);

		if (array_key_exists('content-encoding', $headers) && preg_match('/^(.*\W|)br(\W.*|)$/i', $headers['content-encoding'])) {
			// check if there exists Content-encoding header with br(Brotli) value.
			$headers_information['compression'] = 'brotli';
			$this->disable();
		} elseif (array_key_exists('content-encoding', $headers) && preg_match('/gzip/i', $headers['content-encoding'])) {
			// check if there exists Content-encoding header with gzip value.
			$headers_information['compression'] = 'gzip';
		} elseif (array_key_exists('content-encoding', $headers) && preg_match('/zstd/i', $headers['content-encoding'])) {
			// check if there exists Content-encoding header with zstd value.
			$headers_information['compression'] = 'zstd';
		} else {
			$headers_information['compression'] = false;
		}

		$cached_headers_information[$url] = $headers_information;

		return $headers_information;
	}

	/**
	 * Make request to checkgzipcompression.com api and check if gzip option enabled.
	 *
	 * @return bool|WP_Error
	 */
	public function check_api_for_gzip() {
		$url = get_template_directory_uri() . '/style.css';

		$api_url = 'https://checkgzipcompression.com/js/checkgzip.json?url=' . urlencode($url);

		$result = wp_remote_get($api_url, array('timeout' => 10));

		if (is_wp_error($result)) return $result;

		if (!isset($result['body'])) return new WP_Error('Gzip', __("We can't definitely determine Gzip status as API doesn't return correct answer.", 'wp-optimize'));

		$body = json_decode($result['body']);

		if (isset($body->error) && $body->error)  return new WP_Error('Gzip', __("We can't definitely determine Gzip status as API doesn't return correct answer.", 'wp-optimize'));

		if ($body->result->gzipenabled && !$body->error) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Gzip compression is enabled.
	 *
	 * @param boolean $use_cache - use cached data
	 * @return bool|WP_Error
	 */
	public function is_gzip_compression_enabled($use_cache = true) {

		$compression_types = $this->get_compression_types($use_cache);

		$is_gzip_compression_enabled = true;

		foreach ($compression_types as $compression) {
			if (is_wp_error($compression)) return $compression;
			if (!in_array($compression, array('gzip', 'brotli', 'zstd'))) $is_gzip_compression_enabled = false;
		}

		// if Gzip is not enabled, but we have added settings and Apache modules nt loaded then return error.
		if (!$is_gzip_compression_enabled && $this->is_gzip_compression_section_exists()) {
			if (false === $this->_wp_optimize->is_apache_module_loaded(array('mod_filter', 'mod_deflate'))) {
				return new WP_Error('gzip_missing_module', __('We successfully added Gzip compression settings into .htaccess file.', 'wp-optimize').' '.__('However, the test file we fetched was not Gzip-compressed.', 'wp-optimize').' '.__('It seems one of Apache modules - mod_filter or mod_deflate - is not active.', 'wp-optimize'));
			} elseif (WP_Optimize()->is_apache_server()) {
				return new WP_Error('gzip_missing_module', __('We successfully added Gzip compression settings into .htaccess file.', 'wp-optimize').' '.__('However, the test file we fetched was not Gzip-compressed.', 'wp-optimize').' '.__('Possible causes include that Apache (your webserver) is not configured to allow .htaccess files to take effect, or one of Apache modules - mod_filter or mod_deflate - is not active, or the webserver is configured to disallow Gzip compression.', 'wp-optimize').' '.__('You should speak to your web hosting support to find how to enable it.', 'wp-optimize'));
			} else {
				return new WP_Error('gzip_unsuccessful', __('We successfully added Gzip compression settings into .htaccess file.', 'wp-optimize').' '.__('However, the test file we fetched was not Gzip-compressed.', 'wp-optimize').' '.__('You should speak to your web hosting support to find how to enable it.', 'wp-optimize'));
			}
		}

		// Stored value used to restore gzip compression settings used when plugin being activated,
		// i.e. we need to update it only when we have added settings to .htaccess or gzip is disabled
		if (!$is_gzip_compression_enabled || $this->is_gzip_compression_section_exists()) {
			WP_Optimize()->get_options()->update_option('is_gzip_compression_enabled', $is_gzip_compression_enabled);
		}

		return $is_gzip_compression_enabled;
	}

	/**
	 * Returns the compression type of the given URL.
	 *
	 * @param string $url
	 * @return string|WP_Error
	 */
	private function get_compression_type($url) {

		$headers_info = $this->get_headers_information($url);

		if (is_wp_error($headers_info)) return $headers_info;

		if (!isset($headers_info['compression'])) return '';

		return $headers_info['compression'];
	}

	/**
	 * Check if section with Gzip options already exists in htaccess file.
	 *
	 * @return bool
	 */
	public function is_gzip_compression_section_exists() {
		return $this->_htaccess->is_commented_section_exists($this->_htaccess_section_comment);
	}

	/**
	 * Enable Gzip compression - add settings into .htaccess.
	 */
	public function enable() {
		$this->_htaccess->update_commented_section($this->prepare_gzip_section(), $this->_htaccess_section_comment);
		$this->_htaccess->write_file();
	}

	/**
	 * Disable Gzip compression - remove settings from .htaccess.
	 */
	public function disable() {
		$this->_htaccess->remove_commented_section($this->_htaccess_section_comment);
		$this->_htaccess->write_file();
	}

	/**
	 * Check if gzip compression option is set to true then add section with gzip settings into .htaccess (used when plugin being activated).
	 */
	public function restore() {
		$enabled = WP_Optimize()->get_options()->get_option('is_gzip_compression_enabled');

		if ($enabled && $this->_htaccess->is_writable()) $this->enable();
	}

	/**
	 * Handler for Gzip compression enable command, called from WP_Optimize_Commands.
	 *
	 * @param array $params - ['enable' => true|false]
	 * @return array
	 */
	public function enable_gzip_command_handler($params) {
		$section_updated = false;

		$enable = isset($params['enable']) && $params['enable'];

		if ($this->_htaccess->is_writable()) {

			// update commented section
			if ($enable) {
				$this->enable();
			} else {
				$this->disable();
			}

			// read updated file.
			$this->_htaccess->read_file();
			// check if section added or removed successfully.
			$section_exists = $this->_htaccess->is_commented_section_exists($this->_htaccess_section_comment);
			// set correct $section-updated flag.
			$section_updated = $enable === $section_exists;
		}

		$is_gzip_compression_enabled = $this->is_gzip_compression_enabled(false);

		if ($section_updated) {
			return array(
				'success' => true,
				'enabled' => is_wp_error($is_gzip_compression_enabled) ? false : $is_gzip_compression_enabled,
				// if we can't determine gzip status then return error message.
				'message' => is_wp_error($is_gzip_compression_enabled) ? $is_gzip_compression_enabled->get_error_message() : '',
			);
		} else {
			$gzip_section = $this->prepare_gzip_section();

			if ($is_gzip_compression_enabled) {
				// translators: %s is a file name
				$message = sprintf(__("We can\'t update your %s file.", 'wp-optimize'), $this->_htaccess->get_filename()) . ' ' . __('Please try to remove following lines manually:', 'wp-optimize');
			} else {
				// translators: %s is a file name
				$message = sprintf(__("We can\'t update your %s file.", 'wp-optimize'), $this->_htaccess->get_filename()) . ' ' . __('Please try to add following lines manually:', 'wp-optimize');
			}

			return array(
				'success' => false,
				'enabled' => is_wp_error($is_gzip_compression_enabled) ? false : $is_gzip_compression_enabled,
				'message' => $message,
				'output' =>
					htmlentities($this->_htaccess->get_section_begin_comment($this->_htaccess_section_comment).PHP_EOL.
					join(PHP_EOL, $this->_htaccess->get_flat_array($gzip_section)).
					PHP_EOL.$this->_htaccess->get_section_end_comment($this->_htaccess_section_comment)),
			);
		}
	}

	/**
	 * Retrieve the compression statuses for HTML, CSS, and JS resources in HTML format.
	 *
	 * @param bool $use_cache if true, the cached value will be returned if possible
	 * @return string
	 */
	public function get_compression_test_results_html($use_cache = false) {

		$compression_types = $this->get_compression_types($use_cache);

		$result_html = '';

		foreach ($compression_types as $title => $compression) {
			if (is_wp_error($compression)) {
				$value = $compression->get_error_message();
			} elseif (!empty($compression)) {
				$value = '<b>'.$compression.'</b>';
			} else {
				$value = __('No compression', 'wp-optimize');
			}

			$result_html .= $title.': '. $value . '<br>';
		}

		return wp_kses_post($result_html);
	}

	/**
	 * Get list of content types for which enabled/disabled gzip compression (html, css, js).
	 *
	 * @param bool $use_cache if true, the cached value will be returned if possible
	 *
	 * @return array
	 */
	public function get_enabled_disabled_compression_content_types($use_cache = false) {
		$result = array(
			'enabled' => array(),
			'disabled' => array(),
		);

		$compression_types = $this->get_compression_types($use_cache);

		foreach ($compression_types as $type => $status) {
			if (false !== $status && '' !== $status && !is_wp_error($status)) {
				$result['enabled'][] = $type;
			} else {
				$result['disabled'][] = $type;
			}
		}

		return $result;
	}

	/**
	 * Get compression types for html, css, js files.
	 *
	 * @param bool $use_cache if true, the cached value will be returned if possible
	 *
	 * @return array
	 */
	private function get_compression_types($use_cache = false) {
		$transient_key = 'wpo_compression_test_results';
		$compression_types = array('HTML' => '', 'CSS' => '', 'JS' => '');
		$cached_compression_types = array();

		if ($use_cache) {
			$cached_compression_types = get_transient($transient_key);
		}

		$should_update_cache = false;

		foreach ($compression_types as $content_type => $compression) {
			// if we have cached value and it is not WP_Error then use it, otherwise get compression type and update cache later.
			if ($use_cache && isset($cached_compression_types[$content_type]) && 'WP_Error' !== $cached_compression_types[$content_type]) {
				$compression_types[$content_type] = $cached_compression_types[$content_type];
			} else {
				$compression_types[$content_type] = $this->get_compression_type_by_content_type($content_type);
				$should_update_cache = true;
			}
		}

		if ($should_update_cache) {
			$compression_types_for_cache = $compression_types;
			foreach ($compression_types_for_cache as $content_type => $compression) {
				if (is_wp_error($compression)) {
					$compression_types_for_cache[$content_type] = 'WP_Error';
				}
			}
			set_transient($transient_key, $compression_types_for_cache, HOUR_IN_SECONDS);
		}

		return $compression_types;
	}

	/**
	 * Get compression type for the given content type (html, css, js).
	 *
	 * @param string $content_type - content type (html, css, js)
	 * @return string|false|WP_Error
	 */
	private function get_compression_type_by_content_type($content_type) {
		$content_type = strtoupper($content_type);
		switch ($content_type) {
			case 'HTML':
				return $this->get_compression_type(get_home_url(null, '?cache=false'));
			case 'CSS':
				return $this->get_compression_type($this->css_resource_url());
			case 'JS':
				return $this->get_compression_type(site_url('wp-includes/js/admin-bar.min.js'));
			default:
				return '';
		}
	}

	/**
	 * Get URL to the CSS resource for checking Gzip compression status of CSS files.
	 *
	 * @return string
	 */
	public function css_resource_url() {
		return WPO_PLUGIN_URL . 'css/wp-optimize-admin.css';
	}

	/**
	 * Prepare array with options to switch on gzip in htaccess.
	 *
	 * @return array
	 */
	private function prepare_gzip_section() {
		return array(
			array(
				'<IfModule mod_filter.c>',
				array(
					'<IfModule mod_deflate.c>',
					'# Compress HTML, CSS, JavaScript, Text, XML and fonts',
					'AddType application/vnd.ms-fontobject .eot',
					'AddType font/ttf .ttf',
					'AddType font/otf .otf',
					'AddType font/x-woff .woff',
					'AddType image/svg+xml .svg',
					'',
					'AddOutputFilterByType DEFLATE application/javascript',
					'AddOutputFilterByType DEFLATE application/rss+xml',
					'AddOutputFilterByType DEFLATE application/vnd.ms-fontobject',
					'AddOutputFilterByType DEFLATE application/x-font',
					'AddOutputFilterByType DEFLATE application/x-font-opentype',
					'AddOutputFilterByType DEFLATE application/x-font-otf',
					'AddOutputFilterByType DEFLATE application/x-font-truetype',
					'AddOutputFilterByType DEFLATE application/x-font-ttf',
					'AddOutputFilterByType DEFLATE application/x-font-woff',
					'AddOutputFilterByType DEFLATE application/x-javascript',
					'AddOutputFilterByType DEFLATE application/xhtml+xml',
					'AddOutputFilterByType DEFLATE application/xml',
					'AddOutputFilterByType DEFLATE font/opentype',
					'AddOutputFilterByType DEFLATE font/otf',
					'AddOutputFilterByType DEFLATE font/ttf',
					'AddOutputFilterByType DEFLATE font/woff',
					'AddOutputFilterByType DEFLATE image/svg+xml',
					'AddOutputFilterByType DEFLATE image/x-icon',
					'AddOutputFilterByType DEFLATE text/css',
					'AddOutputFilterByType DEFLATE text/html',
					'AddOutputFilterByType DEFLATE text/javascript',
					'AddOutputFilterByType DEFLATE text/plain',
					'AddOutputFilterByType DEFLATE text/xml',
					'',
					'# Remove browser bugs (only needed for really old browsers)',
					'BrowserMatch ^Mozilla/4 gzip-only-text/html',
					'BrowserMatch ^Mozilla/4\.0[678] no-gzip',
					'BrowserMatch \bMSIE !no-gzip !gzip-only-text/html',
					array(
						'<IfModule mod_headers.c>',
						'Header append Vary User-Agent',
						'</IfModule>',
					),
					'</IfModule>',
				),
				'</IfModule>',
			),
		);
	}
}
