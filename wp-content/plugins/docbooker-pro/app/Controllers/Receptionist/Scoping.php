<?php
/**
 * Receptionist — booking-query scoping.
 *
 * Test-orders + patient_visits + visit_items + test_reports queries are scoped
 * inside RestApi directly (custom tables, no map_meta_cap involved). This file
 * only handles the existing free-plugin booking queries that the receptionist
 * may surface in the dashboard's Bookings view.
 *
 * Hook priority 11 so it runs AFTER MultiClinic\Scoping (priority 10): a user
 * who somehow has both roles ends up with clinic_manager's scope, not double-
 * clobbered.
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

use WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole as ClinicManagerRole;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class Scoping {
	use SingletonTrait;

	public function __construct() {
		add_filter( 'wpddb_bookings_query_args', [ $this, 'force_clinic_scope_in_args' ], 11, 1 );
	}

	public function force_clinic_scope_in_args( $args ) {
		if ( ! is_user_logged_in() ) {
			return $args;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return $args;
		}
		if ( current_user_can( ClinicManagerRole::CAPABILITY ) ) {
			// Already scoped by MultiClinic\Scoping at priority 10.
			return $args;
		}
		if ( ! current_user_can( UserRole::CAPABILITY ) ) {
			return $args;
		}
		$clinic_id         = UserRole::get_user_clinic_id();
		$args['clinic_id'] = $clinic_id > 0 ? $clinic_id : -1;
		return $args;
	}
}
