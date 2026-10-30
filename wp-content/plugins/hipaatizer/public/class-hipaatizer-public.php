<?php
/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    HIPAAtizer
 * @subpackage HIPAAtizer/public
 * @author     HIPAAtizer
 */
class HIPAAtizer_Public {

	private $hipaatizer;
	private $version;

	public function __construct( $hipaatizer, $version ) {
		$this->hipaatizer = $hipaatizer;
		$this->version = $version;

		add_shortcode( 'hipaatizer', array($this, 'hipaa_shortcode') );

	}

	public function enqueue_styles() {

		wp_enqueue_style( $this->hipaatizer, plugin_dir_url( __FILE__ ) . 'css/hipaatizer-public.css', array(), $this->version, 'all' );

	}

	public function hipaa_shortcode ( $atts ) {
		global $hipaaID, $whiteLabelUrl;

		$formID = isset($atts['id']) ? sanitize_text_field($atts['id']) : '';
		if (empty($formID)) {
			return '';
		}

		// Determine the workflow flag (true for Form Packets, false otherwise).
		// This requires a linked account so we can look the form up in the
		// account's published-workflows list. If the site is NOT linked
		// ($hipaaID empty) we still emit the embed — the remote renderer can
		// render a form by ID alone — matching the 1.3.7 behavior. We just
		// default the flag to 'false' since we can't determine the type.
		$form = array();
		if ( ! empty( $hipaaID ) ) {
			$transient_key = 'hipaatizer_workflows_' . get_current_blog_id() . '_' . $hipaaID;
			$results       = $this->hipaa_get_workflows( $hipaaID, $transient_key, false );

			if ( is_array( $results ) ) {
				// Match the shortcode's id="..." against the workflow's GUID (the 'id'
				// field in the /published_workflows API response). Strict equality on
				// that one field — do not match against name/link/etc.
				$form = $this->hipaa_find_form( $formID, $results );

				// Cache miss for this specific form — the cached workflows list might be
				// stale (form just published in HIPAAtizer dashboard) or corrupted. Try
				// ONE fresh fetch, but only if we have NOT recently tried for this form,
				// to keep `[hipaatizer id="garbage"]` from spamming the API.
				if ( empty( $form ) ) {
					$miss_key = 'hipaatizer_workflow_miss_' . get_current_blog_id() . '_' . md5( $hipaaID . '|' . $formID );
					if ( false === get_transient( $miss_key ) ) {
						$fresh = $this->hipaa_get_workflows( $hipaaID, $transient_key, true );
						if ( is_array( $fresh ) ) {
							$form = $this->hipaa_find_form( $formID, $fresh );
						}
						if ( empty( $form ) ) {
							// Record the miss for 60s to throttle repeated lookups for this bad/missing ID
							set_transient( $miss_key, 1, 60 );
						}
					}
				}
			}
		}

		$script_src = !empty($whiteLabelUrl) ? $whiteLabelUrl . '/shared/hipaatizer-form-renderer.js' : HIPAATIZER_APP . '/shared/hipaatizer-form-renderer.js';
		$param = (!empty($form) && isset($form[0]['type']) && $form[0]['type'] === 'Workflow') ? 'true' : 'false';

		return '<div class="hipaa-form-embed">'
			. '<script id="' . esc_attr($formID) . '-script" src="' . esc_url($script_src) . '"></script>'
			. '<script>new Hipaatizer("' . esc_js($formID) . '", ' . $param . ').render();</script>'
			. '</div>';
	}

	/**
	 * Load the published-workflows list for the given account.
	 *
	 * Returns a (possibly empty) array on success, or false on hard network/API failure.
	 * Caches the result for an hour via WP transients.
	 *
	 * @param string $hipaaID       Account GUID.
	 * @param string $transient_key Cache key to read/write.
	 * @param bool   $force_refresh When true, bypass the cache and refetch from the API.
	 * @return array|false
	 */
	private function hipaa_get_workflows( $hipaaID, $transient_key, $force_refresh ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$curl     = HIPAATIZER_APP . '/api/v1/account/' . $hipaaID . '/published_workflows';
		$response = wp_remote_get( $curl, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}
		$body    = wp_remote_retrieve_body( $response );
		$res     = json_decode( $body, true );
		$results = isset( $res['workflows'] ) && is_array( $res['workflows'] ) ? $res['workflows'] : array();
		set_transient( $transient_key, $results, HOUR_IN_SECONDS );
		return $results;
	}

	/**
	 * Find a workflow object by its 'id' field (the GUID).
	 * Strict equality on that one field — does NOT match against name/link/etc.
	 *
	 * @param string $formID  GUID to look up.
	 * @param array  $results Workflows array from the API.
	 * @return array          Single-element array with the matched workflow, or empty array.
	 */
	private function hipaa_find_form( $formID, $results ) {
		if ( ! is_array( $results ) ) {
			return array();
		}
		foreach ( $results as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item['id'] ) && $item['id'] === $formID ) {
				return array( $item );
			}
		}
		return array();
	}
}

