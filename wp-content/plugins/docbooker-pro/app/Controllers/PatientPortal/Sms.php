<?php
/**
 * Provider-agnostic HTTP SMS dispatcher. Reads the admin-configured template
 * from wpddb_sms_settings, builds the body for either an OTP or a booking
 * confirmation, then substitutes {{phone}} / {{message}} (and {{code}} as a
 * legacy alias) into the provider's payload_template.
 *
 * Admin knobs (wpddb_sms_settings):
 *   provider_url             (required) endpoint URL
 *   method                   "POST" (default) or "GET"
 *   headers_json             JSON object of HTTP headers
 *   payload_template         wire body with {{phone}} {{message}} placeholders
 *   success_path             dot-path to a truthy value in the JSON response
 *   otp_message_template     human text for OTPs (uses {{code}})
 *   booking_message_template human text for bookings (uses {{patient}} ...)
 *   user_agent / sslverify / timeout — non-UI defaults
 */

namespace WpDreamers\WPDDBP\Controllers\PatientPortal;

defined( 'ABSPATH' ) || exit;

class Sms {

	const DEFAULT_UA      = 'Mozilla/5.0 (compatible; DocBookerPro/1.0)';
	const DEFAULT_TIMEOUT = 15;

	/**
	 * Send a 6-digit OTP. Builds the body from otp_message_template, then
	 * dispatches via send_message().
	 */
	public static function send_otp( $phone, $code ) {
		$settings = get_option( 'wpddb_sms_settings', [] );
		$code_safe = preg_replace( '/[^0-9]/', '', (string) $code );

		$tmpl    = (string) ( $settings['otp_message_template'] ?? 'Your DocBooker OTP is {{code}}' );
		$message = strtr( $tmpl, [ '{{code}}' => $code_safe ] );

		// Legacy log line so dev/test mode still surfaces the code when no
		// provider is configured.
		if ( empty( $settings['enabled'] ) || empty( $settings['provider_url'] ) ) {
			error_log( '[wpddbp] SMS not configured; OTP for ' . $phone . ' is ' . $code_safe );
			return true;
		}

		return self::send_message( $phone, $message, [ 'context' => 'otp', 'code' => $code_safe ] );
	}

	/**
	 * Send an arbitrary text body to $phone via the configured provider.
	 *
	 * @param string $phone   E.164-ish phone number.
	 * @param string $message Pre-built human-readable body.
	 * @param array  $context Optional context for logging (e.g. ['context'=>'booking']).
	 *                        May also carry a 'code' key when the caller is an
	 *                        OTP path so legacy payload_templates using {{code}}
	 *                        keep working.
	 */
	public static function send_message( $phone, $message, $context = [] ) {
		$settings = get_option( 'wpddb_sms_settings', [] );
		if ( empty( $settings['enabled'] ) || empty( $settings['provider_url'] ) ) {
			error_log( '[wpddbp] SMS not configured; would have sent to ' . $phone . ': ' . $message );
			return true;
		}

		$method     = strtoupper( $settings['method'] ?? 'POST' );
		$headers    = self::parse_headers( $settings['headers_json'] ?? '' );
		$template   = (string) ( $settings['payload_template'] ?? '' );
		$user_agent = trim( (string) ( $settings['user_agent'] ?? '' ) ) ?: self::DEFAULT_UA;
		$sslverify  = array_key_exists( 'sslverify', $settings ) ? (bool) $settings['sslverify'] : true;
		$timeout    = max( 1, (int) ( $settings['timeout'] ?? self::DEFAULT_TIMEOUT ) );

		// Sanitize phone: keep digits, +, -, spaces, parens. Strip everything else
		// so a malformed value can't smuggle extra JSON keys / query params past
		// the template into the outbound provider request.
		$phone_safe = preg_replace( '/[^0-9+\-\s()]/', '', (string) $phone );

		// In application/x-www-form-urlencoded payloads (or any GET request) the
		// `+` character is decoded as a space on the receiving end, which breaks
		// E.164 phone numbers. URL-encode substitution values so providers like
		// Twilio see `%2B880...` and reconstruct the correct `+880...`.
		$looks_like_form_urlencoded =
			$method === 'GET'
			|| stripos( implode( '|', array_map( 'strval', array_keys( $headers ) ) ) . '|' . implode( '|', array_map( 'strval', $headers ) ), 'x-www-form-urlencoded' ) !== false;

		$ph        = $looks_like_form_urlencoded ? rawurlencode( $phone_safe ) : $phone_safe;
		$msg_value = $looks_like_form_urlencoded ? rawurlencode( $message )    : $message;

		// {{code}} kept as an alias for {{message}} so admins who haven't yet
		// updated their old "Your OTP is {{code}}" payload_templates still
		// receive *something* (their OTP body or booking body, in place of code).
		// For pure OTP calls we still pass the raw code so providers expecting
		// `Body=Your+OTP+is+{{code}}` literally continue to render correctly.
		$code_value = '';
		if ( isset( $context['code'] ) ) {
			$code_value = (string) $context['code'];
			$code_value = $looks_like_form_urlencoded ? rawurlencode( $code_value ) : $code_value;
		} else {
			$code_value = $msg_value;
		}

		$payload = strtr( $template, [
			'{{phone}}'   => $ph,
			'{{message}}' => $msg_value,
			'{{code}}'    => $code_value,
		] );

		$args = [
			'method'     => $method,
			'headers'    => $headers,
			'timeout'    => $timeout,
			'sslverify'  => $sslverify,
			'user-agent' => $user_agent,
		];
		$url = $settings['provider_url'];

		if ( $method === 'GET' ) {
			$query_str = wp_parse_url( $payload, PHP_URL_QUERY );
			if ( $query_str ) {
				$url = add_query_arg( wp_parse_args( $query_str ), $url );
			}
		} else {
			$args['body'] = $payload;
		}

		$ctx_label = isset( $context['context'] ) ? (string) $context['context'] : 'sms';

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			error_log( "[wpddbp] SMS ({$ctx_label}) transport error: " . $response->get_error_message() );
			return false;
		}
		$code_http = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		if ( $code_http >= 400 ) {
			error_log( "[wpddbp] SMS ({$ctx_label}) HTTP {$code_http}; body: " . substr( (string) $body, 0, 500 ) );
			return false;
		}

		// Optional success_path validation against the JSON body.
		$success_path = trim( (string) ( $settings['success_path'] ?? '' ) );
		if ( $success_path !== '' ) {
			$json = json_decode( (string) $body, true );
			$ok   = self::read_path( $json, $success_path );
			if ( $ok === null || $ok === false || $ok === '0' || $ok === 'false' ) {
				error_log( "[wpddbp] SMS ({$ctx_label}) success_path '{$success_path}' check failed; body: " . substr( (string) $body, 0, 500 ) );
				return false;
			}
		}
		return true;
	}

	private static function parse_headers( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) return [];
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	private static function read_path( $data, $path ) {
		if ( ! is_array( $data ) ) return null;
		$parts = explode( '.', $path );
		$cur   = $data;
		foreach ( $parts as $p ) {
			if ( is_array( $cur ) && array_key_exists( $p, $cur ) ) {
				$cur = $cur[ $p ];
			} else {
				return null;
			}
		}
		return $cur;
	}
}
