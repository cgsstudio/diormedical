<?php
/**
 * Receptionist — WP role install / uninstall.
 *
 * Idempotent: install() is safe to call multiple times. Mirrors the
 * MultiClinic\UserRole pattern and reuses the same `wpddb_managed_clinic_id`
 * user meta so Scoping/Staff helpers can treat both roles uniformly.
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

defined( 'ABSPATH' ) || exit;

class UserRole {

	public const ROLE_SLUG  = 'wpddb_receptionist';
	public const CAPABILITY = 'wpddb_manage_reception';
	public const META_KEY   = 'wpddb_managed_clinic_id';

	public static function install() {
		if ( null === get_role( self::ROLE_SLUG ) ) {
			add_role(
				self::ROLE_SLUG,
				__( 'Receptionist', 'doc-booker-pro' ),
				[
					'read'           => true,
					self::CAPABILITY => true,
				]
			);
		} else {
			$role = get_role( self::ROLE_SLUG );
			if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAPABILITY ) ) {
			$admin->add_cap( self::CAPABILITY );
		}
	}

	public static function uninstall() {
		if ( null !== get_role( self::ROLE_SLUG ) ) {
			remove_role( self::ROLE_SLUG );
		}
		$admin = get_role( 'administrator' );
		if ( $admin && $admin->has_cap( self::CAPABILITY ) ) {
			$admin->remove_cap( self::CAPABILITY );
		}
	}

	public static function get_user_clinic_id( $user_id = null ) {
		$user_id = $user_id ?: get_current_user_id();
		return (int) get_user_meta( $user_id, self::META_KEY, true );
	}

	public static function user_is_receptionist( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return false;
		}
		return in_array( self::ROLE_SLUG, (array) $user->roles, true );
	}
}
