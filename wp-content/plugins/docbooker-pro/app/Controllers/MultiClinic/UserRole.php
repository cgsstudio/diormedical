<?php
/**
 * Multi-Clinic Manager — WP role install / uninstall.
 *
 * Idempotent: install() is safe to call multiple times; uninstall() removes
 * the role from the wp_options.wp_user_roles registry but leaves any user_meta
 * mappings (`wpddb_managed_clinic_id`) intact in case the toggle is re-enabled.
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

defined( 'ABSPATH' ) || exit;

class UserRole {

    public const ROLE_SLUG    = 'wpddb_clinic_manager';
    public const CAPABILITY   = 'wpddb_manage_clinic';
    public const META_KEY     = 'wpddb_managed_clinic_id';

    /**
     * Add the role + grant the cap to administrators.
     */
    public static function install() {
        if ( null === get_role( self::ROLE_SLUG ) ) {
            add_role(
                self::ROLE_SLUG,
                __( 'Clinic Manager', 'doc-booker-pro' ),
                [
                    'read'              => true,
                    self::CAPABILITY    => true,
                ]
            );
        } else {
            // Make sure the cap is present even if the role pre-exists.
            $role = get_role( self::ROLE_SLUG );
            if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
                $role->add_cap( self::CAPABILITY );
            }
        }

        // Mirror the booking_manager pattern: admins always have the cap too.
        $admin = get_role( 'administrator' );
        if ( $admin && ! $admin->has_cap( self::CAPABILITY ) ) {
            $admin->add_cap( self::CAPABILITY );
        }
    }

    /**
     * Remove the role from the global registry. user_meta mappings are kept
     * so re-installing restores everything for already-assigned users.
     */
    public static function uninstall() {
        if ( null !== get_role( self::ROLE_SLUG ) ) {
            remove_role( self::ROLE_SLUG );
        }
        $admin = get_role( 'administrator' );
        if ( $admin && $admin->has_cap( self::CAPABILITY ) ) {
            $admin->remove_cap( self::CAPABILITY );
        }
    }

    /**
     * Returns the clinic id this user is assigned to manage, or 0 if none.
     */
    public static function get_user_clinic_id( $user_id = null ) {
        $user_id = $user_id ?: get_current_user_id();
        return (int) get_user_meta( $user_id, self::META_KEY, true );
    }
}
