<?php
/**
 * Records the last successful login timestamp for any user holding a Staff
 * role (clinic_manager or receptionist). The value is stored as a unix
 * timestamp in user meta and surfaced by the Staff REST endpoint to drive
 * the "Last login" column and derived activity status pill.
 */

namespace WpDreamers\WPDDBP\Controllers\Staff;

use WP_User;
use WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole as ManagerRole;
use WpDreamers\WPDDBP\Controllers\Receptionist\UserRole as ReceptionistRole;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class LastLoginTracker {
	use SingletonTrait;

	public const META_KEY = 'wpddbp_last_login';

	public function __construct() {
		add_action( 'wp_login', [ $this, 'record_login' ], 10, 2 );
	}

	public function record_login( $user_login, $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$roles = (array) $user->roles;
		if (
			! in_array( ManagerRole::ROLE_SLUG, $roles, true ) &&
			! in_array( ReceptionistRole::ROLE_SLUG, $roles, true )
		) {
			return;
		}
		update_user_meta( $user->ID, self::META_KEY, time() );
	}

	/**
	 * Activity buckets used by the UI status pill.
	 *  active  — within the last 24 hours
	 *  idle    — within the last 7 days
	 *  offline — older than 7 days OR never logged in since tracking began
	 */
	public static function status_for( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return 'offline';
		}
		$delta = time() - $timestamp;
		if ( $delta < DAY_IN_SECONDS ) {
			return 'active';
		}
		if ( $delta < 7 * DAY_IN_SECONDS ) {
			return 'idle';
		}
		return 'offline';
	}
}
