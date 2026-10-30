<?php
/**
 * OTP generation + verification with rate-limiting and HMAC-hashed storage.
 */

namespace WpDreamers\WPDDBP\Controllers\PatientPortal;

defined( 'ABSPATH' ) || exit;

class Otp {

	const TTL_SECONDS         = 300;       // 5 minutes
	const MAX_ATTEMPTS        = 5;
	const PHONE_COOLDOWN_SECS = 60;
	const IP_HOURLY_LIMIT     = 10;
	const IP_FAILED_LIMIT     = 20;        // wrong codes per network per hour

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_otp_codes';
	}

	/**
	 * Generate + store a 6-digit code. Returns the raw code so it can be
	 * dispatched via SMS. Returns WP_Error on rate limit.
	 */
	public static function request( $phone, $order_code = null, $ip = null ) {
		global $wpdb;
		$t = self::table();

		$ip = $ip ?: self::client_ip();

		// Cooldown per phone+order.
		$where_params = [ $phone, 'portal_login' ];
		$where        = 'phone = %s AND purpose = %s';
		if ( $order_code !== null ) {
			$where         .= ' AND order_code = %s';
			$where_params[] = $order_code;
		}
		$recent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM `{$t}` WHERE {$where} ORDER BY id DESC LIMIT 1",
				$where_params
			)
		);
		if ( $recent ) {
			// created_at is stored in UTC (it used to be site time, which turned
			// the 60-second cooldown into hours on sites east of UTC).
			$last_ts = strtotime( $recent . ' UTC' );
			if ( $last_ts && ( time() - $last_ts ) < self::PHONE_COOLDOWN_SECS ) {
				return new \WP_Error( 'wpddbp_otp_cooldown', __( 'Please wait before requesting another code.', 'doc-booker-pro' ), [ 'status' => 429 ] );
			}
		}

		$code     = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$expires  = gmdate( 'Y-m-d H:i:s', time() + self::TTL_SECONDS );

		// Insert with empty hash first; recompute with id-binding salt and update.
		$wpdb->insert( $t, [
			'phone'      => $phone,
			'purpose'    => 'portal_login',
			'order_code' => $order_code,
			'code_hash'  => '',
			'expires_at' => $expires,
			'ip'         => $ip,
			'created_at' => current_time( 'mysql', true ),
		] );
		$id   = (int) $wpdb->insert_id;
		$hash = hash_hmac( 'sha256', $code, wp_salt( 'auth' ) . '|' . $id );
		$wpdb->update( $t, [ 'code_hash' => $hash ], [ 'id' => $id ] );

		return [
			'id'      => $id,
			'code'    => $code,
			'expires' => $expires,
		];
	}

	/**
	 * Verify a code. Returns the OTP row id on success, WP_Error on failure.
	 */
	public static function verify( $phone, $order_code, $code ) {
		global $wpdb;
		$t = self::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$t}`
				 WHERE phone = %s AND purpose = 'portal_login'
				   AND ( order_code IS NULL OR order_code = %s )
				   AND verified_at IS NULL
				 ORDER BY id DESC LIMIT 1",
				$phone,
				$order_code
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return new \WP_Error( 'wpddbp_otp_not_found', __( 'No active code. Please request a new one.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		if ( strtotime( $row['expires_at'] ) < time() ) {
			return new \WP_Error( 'wpddbp_otp_expired', __( 'Code expired. Please request a new one.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		if ( (int) $row['attempts'] >= self::MAX_ATTEMPTS ) {
			return new \WP_Error( 'wpddbp_otp_locked', __( 'Too many attempts. Please request a new code.', 'doc-booker-pro' ), [ 'status' => 429 ] );
		}

		$check = hash_hmac( 'sha256', (string) $code, wp_salt( 'auth' ) . '|' . $row['id'] );
		if ( ! hash_equals( (string) $row['code_hash'], $check ) ) {
			$wpdb->update( $t, [ 'attempts' => (int) $row['attempts'] + 1 ], [ 'id' => $row['id'] ] );
			return new \WP_Error( 'wpddbp_otp_invalid', __( 'Incorrect code.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		$wpdb->update( $t, [ 'verified_at' => current_time( 'mysql' ) ], [ 'id' => $row['id'] ] );
		return (int) $row['id'];
	}

	/**
	 * Count one more code request from this network; false once over the hourly cap.
	 * Runs before anything is looked up, so the answer says nothing about orders.
	 */
	public static function allow_request_from( $ip = null ) {
		$key   = 'wpddb_otp_ip_' . md5( $ip ?: self::client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= self::IP_HOURLY_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Whether this network may try another code. Every new code gets fresh
	 * attempts, so without this a script could keep guessing across codes.
	 */
	public static function may_try_code( $ip = null ) {
		return (int) get_transient( 'wpddb_otp_fail_' . md5( $ip ?: self::client_ip() ) ) < self::IP_FAILED_LIMIT;
	}

	public static function record_failed_try( $ip = null ) {
		$key = 'wpddb_otp_fail_' . md5( $ip ?: self::client_ip() );
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	}

	public static function client_ip() {
		// Best-effort. Behind a proxy you'd parse X-Forwarded-For; the rate
		// limiter is defense-in-depth, not auth, so good enough.
		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}
}
