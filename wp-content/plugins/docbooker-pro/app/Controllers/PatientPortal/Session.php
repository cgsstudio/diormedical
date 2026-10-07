<?php
/**
 * Order-scoped portal session. Token is opaque and short-lived (30 min idle);
 * we store its sha256 in a transient with a payload describing what the
 * bearer is authorized to see.
 */

namespace WpDreamers\WPDDBP\Controllers\PatientPortal;

defined( 'ABSPATH' ) || exit;

class Session {

	const IDLE_TTL = 1800; // 30 min

	public static function issue( $phone, $order_id, $patient_id ) {
		$token = bin2hex( random_bytes( 16 ) );
		$key   = self::cache_key( $token );
		set_transient( $key, [
			'phone'      => $phone,
			'order_id'   => (int) $order_id,
			'patient_id' => (int) $patient_id,
		], self::IDLE_TTL );
		return [
			'token'      => $token,
			'expires_in' => self::IDLE_TTL,
		];
	}

	public static function validate( $token ) {
		if ( ! $token ) return null;
		$key  = self::cache_key( $token );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) return null;
		// Touch on use: extend idle TTL.
		set_transient( $key, $data, self::IDLE_TTL );
		return $data;
	}

	public static function revoke( $token ) {
		if ( ! $token ) return;
		delete_transient( self::cache_key( $token ) );
	}

	public static function read_bearer( $request ) {
		$auth = '';
		if ( method_exists( $request, 'get_header' ) ) {
			$auth = (string) $request->get_header( 'authorization' );
		}
		if ( ! $auth && isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth = (string) $_SERVER['HTTP_AUTHORIZATION'];
		}
		if ( stripos( $auth, 'Bearer ' ) === 0 ) {
			return trim( substr( $auth, 7 ) );
		}
		return '';
	}

	private static function cache_key( $token ) {
		return 'wpddb_portal_sess_' . hash( 'sha256', $token );
	}
}
