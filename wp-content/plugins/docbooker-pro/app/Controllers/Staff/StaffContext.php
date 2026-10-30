<?php
/**
 * Shared helpers for Clinic Manager + Receptionist roles. Both share the
 * `wpddb_managed_clinic_id` user meta, so any caller that just needs "what
 * clinic does this staff member manage?" can use this single helper without
 * caring which role they hold.
 */

namespace WpDreamers\WPDDBP\Controllers\Staff;

use WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole as ManagerRole;
use WpDreamers\WPDDBP\Controllers\Receptionist\UserRole as ReceptionistRole;

defined( 'ABSPATH' ) || exit;

class StaffContext {

	public const META_KEY = 'wpddb_managed_clinic_id';

	public static function get_current_staff_clinic_id() {
		return self::get_staff_clinic_id( get_current_user_id() );
	}

	public static function get_staff_clinic_id( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) return 0;
		return (int) get_user_meta( $user_id, self::META_KEY, true );
	}

	public static function is_clinic_manager( $user ) {
		return $user instanceof \WP_User && in_array( ManagerRole::ROLE_SLUG, (array) $user->roles, true );
	}

	public static function is_receptionist( $user ) {
		return $user instanceof \WP_User && in_array( ReceptionistRole::ROLE_SLUG, (array) $user->roles, true );
	}

	public static function is_multi_clinic_enabled() {
		$settings = get_option( 'wpddb_global_settings', [] );
		return isset( $settings['multi_clinic_manage_enable'] ) && $settings['multi_clinic_manage_enable'] === 'on';
	}

	public static function is_receptionist_enabled() {
		$settings = get_option( 'wpddb_global_settings', [] );
		return isset( $settings['receptionist_manage_enable'] ) && $settings['receptionist_manage_enable'] === 'on';
	}

	/**
	 * Returns the only published clinic id if the site has exactly one — used
	 * to auto-fill the clinic field in single-clinic mode. Returns 0 when 0 or
	 * 2+ clinics exist.
	 */
	public static function single_clinic_id_or_zero() {
		$q = new \WP_Query( [
			'post_type'      => 'wpddb_clinic',
			'post_status'    => 'publish',
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		$ids = $q->posts ?: [];
		return count( $ids ) === 1 ? (int) $ids[0] : 0;
	}
}
