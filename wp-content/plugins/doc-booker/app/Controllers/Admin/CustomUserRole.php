<?php

namespace WpDreamers\WPDDB\Controllers\Admin;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
class CustomUserRole {

	public static function create_custom_user_roles(  ) {
		if (get_role('booking_manager')) {
			remove_role('booking_manager');
		}

		add_role(
			'booking_manager',
			'Booking Manager',
			array(
				'read' => true,
				'wpddb_manage_booking_system' => true
			)
		);

		$admin_role = get_role('administrator');
		$admin_role->add_cap('wpddb_manage_booking_system');
	}
}