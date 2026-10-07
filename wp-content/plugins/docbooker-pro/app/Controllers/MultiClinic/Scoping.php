<?php
/**
 * Multi-Clinic Manager — backend scoping / hardening.
 *
 * Two callbacks:
 *   1. wpddb_bookings_query_where  — when caller is a clinic_manager (and not
 *      an administrator), inject WHERE clinic_id = <user's clinic id>. Prevents
 *      URL/header tampering on the existing free booking_details endpoint.
 *   2. map_meta_cap                — denies edit_post / delete_post on
 *      wpddb_doctor / wpddb_clinic posts that are outside the manager's clinic.
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class Scoping {
    use SingletonTrait;

    public function __construct() {
        add_filter( 'wpddb_bookings_query_args', [ $this, 'force_clinic_scope_in_args' ], 10, 1 );
        add_filter( 'map_meta_cap', [ $this, 'deny_out_of_scope_post_edits' ], 10, 4 );
    }

    /**
     * Mutates the booking-query arg set BEFORE the WHERE clause is built so
     * any clinic_id passed by the caller is overridden with the manager's
     * assigned clinic. Prevents URL/header tampering by clinic_manager users.
     */
    public function force_clinic_scope_in_args( $args ) {
        if ( ! is_user_logged_in() ) {
            return $args;
        }
        // Admins are never restricted.
        if ( current_user_can( 'manage_options' ) ) {
            return $args;
        }
        if ( ! current_user_can( UserRole::CAPABILITY ) ) {
            return $args;
        }
        $clinic_id = UserRole::get_user_clinic_id();
        // Force the manager's clinic id; -1 (no rows) when somehow no clinic is assigned.
        $args['clinic_id'] = $clinic_id > 0 ? $clinic_id : -1;
        return $args;
    }

    /**
     * Block edit/delete on posts outside the manager's scope.
     * For wpddb_clinic: only the manager's assigned clinic.
     * For wpddb_doctor: only doctors whose schedule includes the clinic.
     */
    public function deny_out_of_scope_post_edits( $caps, $cap, $user_id, $args ) {
        // Only apply to edit/delete singular post caps
        $relevant = [ 'edit_post', 'delete_post', 'edit_published_posts', 'delete_published_posts' ];
        if ( ! in_array( $cap, $relevant, true ) ) {
            return $caps;
        }
        if ( empty( $args[0] ) ) {
            return $caps;
        }
        $post_id = (int) $args[0];

        $user = get_user_by( 'id', $user_id );
        if ( ! $user || in_array( 'administrator', (array) $user->roles, true ) ) {
            return $caps; // admins exempt
        }
        if ( ! in_array( UserRole::ROLE_SLUG, (array) $user->roles, true ) ) {
            return $caps; // not a clinic manager
        }

        $post_type = get_post_type( $post_id );
        $clinic_id = (int) get_user_meta( $user_id, UserRole::META_KEY, true );

        if ( $post_type === wpddb()->post_type_clinic ) {
            if ( (int) $post_id !== $clinic_id ) {
                return [ 'do_not_allow' ];
            }
            // In-scope: fall through to normal WP cap resolution.
            return $caps;
        }

        if ( $post_type === wpddb()->post_type_doctor ) {
            if ( ! self::doctor_in_clinic( $post_id, $clinic_id ) ) {
                return [ 'do_not_allow' ];
            }
            // In-scope: fall through to normal WP cap resolution.
            return $caps;
        }
        return $caps;
    }

    private static function doctor_in_clinic( $doctor_id, $clinic_id ) {
        $schedule = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
        if ( ! is_array( $schedule ) ) {
            return false;
        }
        foreach ( $schedule as $day ) {
            if ( empty( $day['clinics'] ) || ! is_array( $day['clinics'] ) ) continue;
            foreach ( $day['clinics'] as $c ) {
                if ( isset( $c['id'] ) && (int) $c['id'] === (int) $clinic_id ) {
                    return true;
                }
            }
        }
        return false;
    }
}
