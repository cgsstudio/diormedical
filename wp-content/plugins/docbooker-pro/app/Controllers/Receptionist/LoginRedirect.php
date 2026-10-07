<?php

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class LoginRedirect {
	use SingletonTrait;

	public function __construct() {
		add_filter( 'login_redirect', [ $this, 'redirect_receptionist' ], 11, 3 );
	}

	public function redirect_receptionist( $redirect_to, $requested_redirect, $user ) {
		if ( ! Dashboard::is_enabled() ) {
			return $redirect_to;
		}
		if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
			return $redirect_to;
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return $redirect_to;
		}
		if ( ! in_array( UserRole::ROLE_SLUG, (array) $user->roles, true ) ) {
			return $redirect_to;
		}
		$page_id = Dashboard::get_dashboard_page_id();
		if ( $page_id ) {
			return get_permalink( $page_id ) ?: $redirect_to;
		}
		return $redirect_to;
	}
}
