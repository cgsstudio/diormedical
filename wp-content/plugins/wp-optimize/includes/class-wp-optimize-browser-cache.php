<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Class WP_Optimize_Browser_Cache
 */
class WP_Optimize_Browser_Cache {

	use WP_Optimize_HTTP_Error_Codes_Trait;

	private $_htaccess;

	private $_options;

	private $_wp_optimize;

	/**
	 * Browser cache section in htaccess will be wrapped with this comment
	 *
	 * @var string
	 */
	private $_htaccess_section_comment = 'WP-Optimize Browser Cache';

	/**
	 * WP_Optimize_Browser_Cache constructor.
	 */
	public function __construct() {
		$this->_wp_optimize = WP_Optimize();

		$this->_htaccess = new WP_Optimize_Htaccess();

		$this->_options = $this->_wp_optimize->get_options();

		$this->maybe_restore_expire_values_from_htaccess();
	}

	/**
	 * Returns singleton instance object
	 *
	 * @return WP_Optimize_Browser_Cache Returns `WP_Optimize_Browser_Cache` object
	 */
	public static function instance() {
		static $_instance = null;
		if (null === $_instance) {
			$_instance = new self();
		}
		return $_instance;
	}

	/**
	 * Check headers for Cache-Control and Etag. And if they are exist return true.
	 *
	 * @return bool|WP_Error
	 **/
	public function is_enabled() {
		$headers = $this->get_stylesheet_headers();

		if (is_wp_error($headers)) return $headers;

		$cache_control = isset($headers['cache-control']) ? (is_array($headers['cache-control']) ? implode(', ', $headers['cache-control']) : $headers['cache-control']) : '';
		if (array_key_exists('expires', $headers) && array_key_exists('cache-control', $headers) && preg_match('/\b(public|private)\b/i', $cache_control)) {
			$is_enabled = true;
		} else {
			$is_enabled = false;
		}

		if ($this->is_browser_cache_section_exists() && false === $this->_wp_optimize->is_apache_module_loaded(array('mod_expires', 'mod_headers'))) {
			$is_enabled = new WP_Error('Browser cache', __('We successfully updated your .htaccess file.', 'wp-optimize') . ' ' . __('But it seems one of Apache modules - mod_expires or mod_headers is not active.', 'wp-optimize'));
		}

		return $is_enabled;
	}

	/**
	 * Get expire days and hours values from options.
	 *
	 * @return array // [0] - expire days, [1] - expire hours
	 */
	private function get_expire_data() {
		return array(
			absint($this->_options->get_option('browser_cache_expire_days', 0)),
			absint($this->_options->get_option('browser_cache_expire_hours', 0)),
		);
	}


	/**
	 * Check if .htaccess has already updated settings (manually by user) then reset error message output
	 *
	 * @return void
	 */
	public function maybe_reset_error_message_output() {
		$error_message = $this->_options->get_option('browser_cache_error_message', '');
		if ('' === $error_message) return;

		list($expire_days, $expire_hours) = $this->get_expire_data();

		$expire = $this->prepare_interval($expire_days, $expire_hours);
		$search = 'ExpiresByType text/css "access '.$expire.'"';

		// if we already have setting in the file then we can reset error message output
		if (false !== strpos($this->_htaccess->get_content(), $search)) {
			$this->_options->update_option('browser_cache_error_message', '');
			$this->_options->update_option('browser_cache_output', '');
		}
	}

	/**
	 * Set expire days and hours values.
	 *
	 * @param int $expire_days
	 * @param int $expire_hours
	 * @return void
	 */
	private function set_expire_values($expire_days, $expire_hours) {
		$this->_options->update_option('browser_cache_expire_days', absint($expire_days));
		$this->_options->update_option('browser_cache_expire_hours', absint($expire_hours));
	}
	
	/**
	 * Enable browser cache - add settings into .htaccess.
	 *
	 * @return void
	 */
	public function enable() {
		list($expire_days, $expire_hours) = $this->get_expire_data();

		$this->_htaccess->update_commented_section($this->prepare_browser_cache_section($expire_days, $expire_hours), $this->_htaccess_section_comment);
		$this->_htaccess->write_file();
		$this->_options->update_option('enable_browser_cache', true);
	}

	/**
	 * Disable cache - remove settings from .htaccess added in enable() function.
	 *
	 * @return void
	 */
	public function disable() {
		$this->_htaccess->remove_commented_section($this->_htaccess_section_comment);
		$this->_htaccess->write_file();
		$this->_options->update_option('enable_browser_cache', false);
	}

	/**
	 * Restore expiration settings from .htaccess if the options are empty.
	 *
	 * @return void
	 */
	public function maybe_restore_expire_values_from_htaccess() {
		$expire_days = $this->_options->get_option('browser_cache_expire_days', '');
		$expire_hours = $this->_options->get_option('browser_cache_expire_hours', '');

		if ('' === $expire_days && '' === $expire_hours) {
			$this->restore_expire_values_from_htaccess();
		}
	}

	/**
	 * Check if browser cache option is set to true then add section with browser cache settings into .htaccess.
	 *
	 * @return void
	 */
	public function restore() {
		list($expire_days, $expire_hours) = $this->get_expire_data();

		$enabled = 0 !== $expire_days || 0 !== $expire_hours;

		// If we don't have values in the database try to read them from .htaccess file
		if (!$enabled) {
			$this->restore_expire_values_from_htaccess();

			list($expire_days, $expire_hours) = $this->get_expire_data();
			$enabled = 0 !== $expire_days || 0 !== $expire_hours;
		}

		if ($enabled && $this->_htaccess->is_writable()) $this->enable();
	}

	/**
	 * Restore expire days and expire hours values form .htaccess if possible.
	 *
	 * @return void
	 */
	private function restore_expire_values_from_htaccess() {
		$htaccess_values = $this->get_browser_cache_section_expire_values();
		list($expire_days, $expire_hours) = $htaccess_values;

		$enabled = 0 !== $expire_days || 0 !== $expire_hours;

		if ($enabled) {
			$this->set_expire_values($expire_days, $expire_hours);
		}
	}

	/**
	 * Check if section with browser cache settings already exists.
	 *
	 * @return bool
	 */
	public function is_browser_cache_section_exists() {
		return $this->_htaccess->is_commented_section_exists($this->_htaccess_section_comment);
	}

	/**
	 * Get expire days and hours values from the .htaccess file
	 *
	 * @return array|bool
	 */
	private function get_browser_cache_section_expire_values() {
		$section_content = $this->_htaccess->get_section_content();

		if (preg_match('/ExpiresByType text\/css "access ([0-9a-z\s]+)"/i', $section_content, $matches)) {
			return $this->expire_string_to_days_hours($matches[1]);
		}

		return array(0, 0);
	}

	/**
	 * Returns days and hours values from expire string
	 *
	 * @param string $string
	 * @return array
	 */
	private function expire_string_to_days_hours($string) {
		$days = 0;
		$hours = 0;

		preg_match_all(
			'/(\d+)\s+(year|years|month|months|day|days|hour|hours)/i',
			$string,
			$matches,
			PREG_SET_ORDER
		);

		foreach ($matches as $match) {
			$value = (int) $match[1];
			$unit = strtolower($match[2]);

			switch ($unit) {
				case 'year':
				case 'years':
					$days += $value * 365;
					break;

				case 'month':
				case 'months':
					$days += $value * 30;
					break;

				case 'day':
				case 'days':
					$days += $value;
					break;

				case 'hour':
				case 'hours':
					$hours += $value;
					break;
			}
		}

		// Convert extra hours into days
		$days += intdiv($hours, 24);
		$hours %= 24;

		return array(absint($days), absint($hours));
	}

	/**
	 * Handle for enable_browser_cache command used in WP_Optimize_Commands.
	 *
	 * @param array $params - ['browser_cache_expire' => '1 month 15 days 2 hours' || '' - for disable cache]
	 * @return array
	 */
	public function enable_browser_cache_command_handler(array $params): array {
		$expire_days = absint($params['browser_cache_expire_days']);
		$expire_hours = absint($params['browser_cache_expire_hours']);

		list($current_expire_days, $current_expire_hours) = $this->get_browser_cache_section_expire_values();

		// clear error message output
		$this->_options->update_option('browser_cache_error_message', '');
		$this->_options->update_option('browser_cache_output', '');

		$enable = 0 !== $expire_days || 0 !== $expire_hours;

		// Store new expire values in the database
		$this->set_expire_values($expire_days, $expire_hours);

		/**
		 * If we don't need to do anything in .htaccess then return message.
		 */
		if ($enable === $this->_htaccess->is_commented_section_exists()
			&& $expire_days === $current_expire_days
			&& $expire_hours === $current_expire_hours
		) {
			if ($enable) {
				$message = __('Browser static caching settings already exists in the .htaccess file', 'wp-optimize');
			} else {
				$message = '';
			}

			return array(
				'success' => true,
				'enabled' => $enable,
				'message' => $message,
			);
		}

		$section_updated = $this->update_htaccess_section($enable);

		$is_enabled = $this->is_enabled();

		if ($section_updated) {

			if (is_wp_error($is_enabled)) {
				return array(
					'success' => true,
					'enabled' => false,
					'error_message' => $is_enabled->get_error_message(),
				);
			} else {
				return array(
					'success' => true,
					'enabled' => $is_enabled,
					'message' => __('We successfully updated your .htaccess file.', 'wp-optimize'),
				);
			}
		} else {

			if ($enable) {
				// translators: %s is a file name
				$message = sprintf(__("We can't update your %s file.", 'wp-optimize'), $this->_htaccess->get_filename()) . ' ' . __('Please try to add following lines manually:', 'wp-optimize');
			} else {
				// translators: %s is a file name
				$message = sprintf(__("We can't update your %s file.", 'wp-optimize'), $this->_htaccess->get_filename()) . ' ' . __('Please try to remove following lines manually:', 'wp-optimize');
			}

			$output = htmlentities($this->get_htaccess_section_output($enable));

			// store error message output
			$this->_options->update_option('browser_cache_error_message', $message);
			$this->_options->update_option('browser_cache_output', $output);

			if (is_wp_error($is_enabled)) {
				$message .= ' ' .$is_enabled->get_error_message();
				$is_enabled = false;
			}
			
			return array(
				'success' => false,
				'enabled' => $is_enabled,
				'error_message' => $message,
				'output' => $output,
			);
		}
	}

	/**
	 * Update the .htaccess section for browser caching.
	 *
	 * @param boolean $enable
	 * @return bool True if section updated successfully, false otherwise
	 */
	private function update_htaccess_section(bool $enable) {
		
		if (!$this->_htaccess->is_writable()) {
			return false;
		}

		// update commented section
		if ($enable) {
			$this->enable();
		} else {
			$this->disable();
		}

		// read updated file.
		$this->_htaccess->read_file();
		// check if section added or removed successfully.
		$section_exists = $this->_htaccess->is_commented_section_exists();
		// return true if section updated successfully, false otherwise.
		return $enable === $section_exists;
	}

	/**
	 * Get output content for enable/disable browser cache in .htaccess.
	 *
	 * @param bool $enable
	 * @return string
	 */
	private function get_htaccess_section_output($enable) {
		list($expire_days, $expire_hours) = $this->get_expire_data();

		if ($enable) {
			$cache_section = $this->prepare_browser_cache_section($expire_days, $expire_hours);

			$output = $this->_htaccess->get_section_begin_comment() . PHP_EOL .
				join(PHP_EOL, $this->_htaccess->get_flat_array($cache_section)).
				PHP_EOL . $this->_htaccess->get_section_end_comment();
		} else {
			$output = $this->_htaccess->get_section_begin_comment() . PHP_EOL .
				' ... ... ... '.
				PHP_EOL . $this->_htaccess->get_section_end_comment();
		}

		return $output;
	}

	/**
	 * Use $days and $hours values to build correct time interval as a string like '2 days 3 hours' or empty string if date is empty.
	 *
	 * @param int $days
	 * @param int $hours
	 * @return string
	 */
	private function prepare_interval(int $days, int $hours): string {

		if (0 === $days && 0 === $hours) {
			return '';
		}

		$parts = array();

		// if hours value more than one day then fix it.
		$days += floor($hours / 24);
		$hours = $hours % 24;

		$years = floor($days / 365);
		$days = $days % 365;
		$months = floor($days / 30);
		$days = $days % 30;

		if ($years > 0) {
			$parts[] = $years . ($years > 1 ? ' years' : ' year');
		}

		if ($months > 0) {
			$parts[] = $months . ($months > 1 ? ' months' : ' month');
		}

		if ($days > 0) {
			$parts[] = $days . ($days > 1 ? ' days' : ' day');
		}

		if ($hours > 0) {
			$parts[] = $hours . ($hours > 1 ? ' hours' : ' hour');
		}

		return join(' ', $parts);
	}

	/**
	 * Converts a human-readable time string into total seconds
	 *
	 * @param int $days
	 * @param int $hours
	 * @return string Total number of seconds
	 */
	private function convert_to_seconds(int $days, int $hours): string {
		// Split the string into parts
		$total_seconds = $days * 24 * 60 * 60 + $hours * 60 * 60;

		return strval($total_seconds);
	}

	/**
	 * Build browser cache section array.
	 *
	 * @param int $expire_days
	 * @param int $expire_hours
	 * @return array
	 */
	public function prepare_browser_cache_section(int $expire_days, int $expire_hours): array {
		$expire = $this->prepare_interval($expire_days, $expire_hours);
		$max_age = $this->convert_to_seconds($expire_days, $expire_hours);

		return array(
			array(
				'<IfModule mod_setenvif.c>',
				'SetEnvIf Request_URI "/wp-admin/" WPO_NO_CACHE',
				'</IfModule>',
			),
			array(
				'<IfModule mod_expires.c>',
				'ExpiresActive On',
				'ExpiresByType text/css "access '.$expire.'"',
				'ExpiresByType image/gif "access '.$expire.'"',
				'ExpiresByType image/png "access '.$expire.'"',
				'ExpiresByType image/jpg "access '.$expire.'"',
				'ExpiresByType image/jpeg "access '.$expire.'"',
				'ExpiresByType image/webp "access '.$expire.'"',
				'ExpiresByType image/x-icon "access '.$expire.'"',
				'ExpiresByType application/pdf "access '.$expire.'"',
				'ExpiresByType application/javascript "access '.$expire.'"',
				'ExpiresByType text/x-javascript "access '.$expire.'"',
				'ExpiresByType application/x-shockwave-flash "access '.$expire.'"',
				'ExpiresByType image/svg+xml "access '.$expire.'"',
				'ExpiresByType font/woff "access '.$expire.'"',
				'ExpiresByType font/woff2 "access '.$expire.'"',
				'ExpiresByType font/x-woff "access '.$expire.'"',
				'ExpiresByType application/font-woff "access '.$expire.'"',
				'ExpiresByType application/x-font-woff "access '.$expire.'"',
				'ExpiresByType font/ttf "access '.$expire.'"',
				'ExpiresByType font/otf "access '.$expire.'"',
				'ExpiresByType application/vnd.ms-fontobject "access '.$expire.'"',
				'ExpiresByType application/json "access 0 seconds"',
				'ExpiresByType application/xml "access 0 seconds"',
				'</IfModule>',
			),
			'',
			array(
				'<IfModule mod_headers.c>',
				array(
					'<FilesMatch "\.(ico|jpe?g|png|gif|webp|swf|svg)$">',
					'Header set Cache-Control "public, max-age='.$max_age.'" env=!WPO_NO_CACHE',
					'</FilesMatch>',
				),
				array(
					'<FilesMatch "\.(css)$">',
					'Header set Cache-Control "public, max-age='.$max_age.'" env=!WPO_NO_CACHE',
					'</FilesMatch>',
				),
				array(
					'<FilesMatch "\.(js)$">',
					'Header set Cache-Control "private, max-age='.$max_age.'" env=!WPO_NO_CACHE',
					'</FilesMatch>',
				),
				array(
					'<FilesMatch "\.(woff2?|ttf|otf|eot)$">',
					'Header set Cache-Control "public, max-age='.$max_age.'" env=!WPO_NO_CACHE',
					'</FilesMatch>',
				),
				array(
					'<FilesMatch "\.(x?html?)$">',
					'Header set Cache-Control "private, must-revalidate, max-age='.$max_age.'" env=!WPO_NO_CACHE',
					'</FilesMatch>',
				),
				array(
					'<FilesMatch "\.php$">',
					'Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"',
					'</FilesMatch>',
				),
				'</IfModule>',
			),
			array(
				'<IfModule mod_headers.c>',
				'Header unset Expires env=WPO_NO_CACHE',
				'</IfModule>',
			),
			'',
			'#Disable ETag',
			'FileETag None',
		);
	}
}
