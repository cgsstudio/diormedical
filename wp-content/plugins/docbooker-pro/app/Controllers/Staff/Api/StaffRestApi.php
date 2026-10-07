<?php
/**
 * Unified REST for the Staff admin page. Wraps Clinic Manager + Receptionist
 * user management with a single CRUD surface.
 *
 *   GET    /wpddb_pro/v1/staff?role=clinic_manager|receptionist&search=&page=
 *   POST   /wpddb_pro/v1/staff   { role, mode:new|existing, clinic_id, username?, email?, password?, user_id? }
 *   PATCH  /wpddb_pro/v1/staff/{id}  { clinic_id }
 *   DELETE /wpddb_pro/v1/staff/{id}
 *   GET    /wpddb_pro/v1/staff/clinics  — picker dropdown
 *   GET    /wpddb_pro/v1/staff/users    — user picker (search/login/email)
 */

namespace WpDreamers\WPDDBP\Controllers\Staff\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_User_Query;
use WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole as ManagerRole;
use WpDreamers\WPDDBP\Controllers\Receptionist\UserRole as ReceptionistRole;
use WpDreamers\WPDDBP\Controllers\Staff\LastLoginTracker;
use WpDreamers\WPDDBP\Controllers\Staff\StaffContext;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class StaffRestApi {
	use SingletonTrait;

	public const NAMESPACE = 'wpddb_pro/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/staff', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_staff' ],
				'permission_callback' => [ $this, 'verify_admin' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_staff' ],
				'permission_callback' => [ $this, 'verify_admin' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/staff/(?P<id>\d+)', [
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_staff' ],
				'permission_callback' => [ $this, 'verify_admin' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_staff' ],
				'permission_callback' => [ $this, 'verify_admin' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/staff/bulk', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'bulk_staff' ],
			'permission_callback' => [ $this, 'verify_admin' ],
		] );

		register_rest_route( self::NAMESPACE, '/staff/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => [ $this, 'verify_admin' ],
		] );

		register_rest_route( self::NAMESPACE, '/staff/clinics', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_clinics' ],
			'permission_callback' => [ $this, 'verify_admin' ],
		] );

		register_rest_route( self::NAMESPACE, '/staff/users', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_users' ],
			'permission_callback' => [ $this, 'verify_admin' ],
		] );

		register_rest_route( self::NAMESPACE, '/staff/context', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_context' ],
			'permission_callback' => [ $this, 'verify_admin' ],
		] );
	}

	public function verify_admin( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return wp_verify_nonce( $nonce, 'wp_rest' ) && current_user_can( 'manage_options' );
	}

	public function get_context() {
		return new WP_REST_Response( [
			'multi_clinic_enabled' => StaffContext::is_multi_clinic_enabled(),
			'receptionist_enabled' => StaffContext::is_receptionist_enabled(),
			'single_clinic_id'     => StaffContext::single_clinic_id_or_zero(),
		], 200 );
	}

	public function list_staff( WP_REST_Request $request ) {
		$role_filter = $request->get_param( 'role' );
		$search      = trim( (string) $request->get_param( 'search' ) );

		$roles = [];
		if ( $role_filter === ManagerRole::ROLE_SLUG || $role_filter === 'clinic_manager' ) {
			$roles = [ ManagerRole::ROLE_SLUG ];
		} elseif ( $role_filter === ReceptionistRole::ROLE_SLUG || $role_filter === 'receptionist' ) {
			$roles = [ ReceptionistRole::ROLE_SLUG ];
		} else {
			$roles = [ ManagerRole::ROLE_SLUG, ReceptionistRole::ROLE_SLUG ];
		}

		$args = [
			'role__in' => $roles,
			'orderby'  => 'user_login',
			'order'    => 'ASC',
			'number'   => 200,
		];
		if ( $search !== '' ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}
		$q     = new WP_User_Query( $args );
		$rows  = [];
		foreach ( $q->get_results() as $user ) {
			$rows[] = $this->serialize_user( $user );
		}
		return new WP_REST_Response( $rows, 200 );
	}

	public function create_staff( WP_REST_Request $request ) {
		$role_in   = $request->get_param( 'role' );
		$role_slug = $role_in === 'receptionist' ? ReceptionistRole::ROLE_SLUG
			: ( $role_in === 'clinic_manager' ? ManagerRole::ROLE_SLUG : null );
		if ( ! $role_slug ) {
			return new WP_Error( 'wpddbp_invalid_role', __( 'Invalid role.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		// Reject creating a role whose feature toggle is currently off — defense
		// in depth so a crafted POST can't bypass the hidden UI.
		if ( $role_slug === ReceptionistRole::ROLE_SLUG && ! StaffContext::is_receptionist_enabled() ) {
			return new WP_Error( 'wpddbp_receptionist_disabled', __( 'Receptionist management is currently disabled.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}
		if ( $role_slug === ManagerRole::ROLE_SLUG && ! StaffContext::is_multi_clinic_enabled() ) {
			return new WP_Error( 'wpddbp_multi_clinic_disabled', __( 'Multi-Clinic management is currently disabled.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}

		$clinic_id = (int) $request->get_param( 'clinic_id' );
		if ( $clinic_id > 0 && get_post_type( $clinic_id ) !== 'wpddb_clinic' ) {
			return new WP_Error( 'wpddbp_invalid_clinic', __( 'Invalid clinic.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		if ( $clinic_id === 0 ) {
			// Auto-fill in single-clinic mode for receptionist convenience.
			$clinic_id = StaffContext::single_clinic_id_or_zero();
		}
		if ( $clinic_id <= 0 ) {
			return new WP_Error( 'wpddbp_clinic_required', __( 'Clinic is required.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		$mode = $request->get_param( 'mode' );
		if ( $mode === 'existing' ) {
			$user_id = (int) $request->get_param( 'user_id' );
			$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
			if ( ! $user ) {
				return new WP_Error( 'wpddbp_user_not_found', __( 'User not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
			}
			if ( ! in_array( $role_slug, (array) $user->roles, true ) ) {
				$user->add_role( $role_slug );
			}
			update_user_meta( $user->ID, StaffContext::META_KEY, $clinic_id );
			return new WP_REST_Response( $this->serialize_user( $user ), 200 );
		}

		$username = sanitize_user( $request->get_param( 'username' ), true );
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );

		if ( ! $username || username_exists( $username ) ) {
			return new WP_Error( 'wpddbp_username_invalid', __( 'Username is empty or already in use.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		if ( ! is_email( $email ) || email_exists( $email ) ) {
			return new WP_Error( 'wpddbp_email_invalid', __( 'Email is invalid or already in use.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		if ( strlen( $password ) < 6 ) {
			return new WP_Error( 'wpddbp_password_weak', __( 'Password must be at least 6 characters.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		$user_id = wp_insert_user( [
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => $role_slug,
		] );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'wpddbp_user_insert_failed', $user_id->get_error_message(), [ 'status' => 400 ] );
		}
		update_user_meta( $user_id, StaffContext::META_KEY, $clinic_id );

		return new WP_REST_Response( $this->serialize_user( get_user_by( 'id', $user_id ) ), 201 );
	}

	public function update_staff( WP_REST_Request $request ) {
		$user = get_user_by( 'id', (int) $request['id'] );
		if ( ! $user ) {
			return new WP_Error( 'wpddbp_user_not_found', __( 'User not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		$clinic_id = (int) $request->get_param( 'clinic_id' );
		if ( $clinic_id > 0 && get_post_type( $clinic_id ) !== 'wpddb_clinic' ) {
			return new WP_Error( 'wpddbp_invalid_clinic', __( 'Invalid clinic.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		update_user_meta( $user->ID, StaffContext::META_KEY, $clinic_id );
		return new WP_REST_Response( $this->serialize_user( $user ), 200 );
	}

	public function delete_staff( WP_REST_Request $request ) {
		$user = get_user_by( 'id', (int) $request['id'] );
		if ( ! $user ) {
			return new WP_Error( 'wpddbp_user_not_found', __( 'User not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		// Remove both roles + the clinic meta; we don't delete the WP user account.
		foreach ( [ ManagerRole::ROLE_SLUG, ReceptionistRole::ROLE_SLUG ] as $slug ) {
			if ( in_array( $slug, (array) $user->roles, true ) ) {
				$user->remove_role( $slug );
			}
		}
		delete_user_meta( $user->ID, StaffContext::META_KEY );
		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	public function bulk_staff( WP_REST_Request $request ) {
		$action    = (string) $request->get_param( 'action' );
		$ids       = array_values( array_unique( array_map( 'intval', (array) $request->get_param( 'ids' ) ) ) );
		$ids       = array_filter( $ids, function ( $id ) { return $id > 0; } );

		if ( empty( $ids ) ) {
			return new WP_Error( 'wpddbp_no_ids', __( 'No staff selected.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		if ( $action === 'delete' ) {
			$removed = 0;
			foreach ( $ids as $id ) {
				$user = get_user_by( 'id', $id );
				if ( ! $user ) continue;
				foreach ( [ ManagerRole::ROLE_SLUG, ReceptionistRole::ROLE_SLUG ] as $slug ) {
					if ( in_array( $slug, (array) $user->roles, true ) ) {
						$user->remove_role( $slug );
					}
				}
				delete_user_meta( $user->ID, StaffContext::META_KEY );
				$removed++;
			}
			return new WP_REST_Response( [ 'ok' => true, 'affected' => $removed ], 200 );
		}

		if ( $action === 'reassign' ) {
			$clinic_id = (int) $request->get_param( 'clinic_id' );
			if ( $clinic_id <= 0 || get_post_type( $clinic_id ) !== 'wpddb_clinic' ) {
				return new WP_Error( 'wpddbp_invalid_clinic', __( 'Invalid clinic.', 'doc-booker-pro' ), [ 'status' => 400 ] );
			}
			$updated = 0;
			foreach ( $ids as $id ) {
				if ( ! get_user_by( 'id', $id ) ) continue;
				update_user_meta( $id, StaffContext::META_KEY, $clinic_id );
				$updated++;
			}
			return new WP_REST_Response( [ 'ok' => true, 'affected' => $updated ], 200 );
		}

		return new WP_Error( 'wpddbp_bad_action', __( 'Unknown bulk action.', 'doc-booker-pro' ), [ 'status' => 400 ] );
	}

	public function get_stats() {
		$managers      = count_users();
		$total_managers = isset( $managers['avail_roles'][ ManagerRole::ROLE_SLUG ] )
			? (int) $managers['avail_roles'][ ManagerRole::ROLE_SLUG ] : 0;
		$total_recept   = isset( $managers['avail_roles'][ ReceptionistRole::ROLE_SLUG ] )
			? (int) $managers['avail_roles'][ ReceptionistRole::ROLE_SLUG ] : 0;

		$clinics_q = new \WP_Query( [
			'post_type'      => 'wpddb_clinic',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => false,
			'fields'         => 'ids',
		] );
		$clinic_total = (int) $clinics_q->found_posts;

		// "Active in last 24h" — count staff with a recent last-login timestamp.
		$cutoff = time() - DAY_IN_SECONDS;
		$active_q = new WP_User_Query( [
			'role__in'   => [ ManagerRole::ROLE_SLUG, ReceptionistRole::ROLE_SLUG ],
			'meta_key'   => LastLoginTracker::META_KEY,
			'meta_value' => $cutoff,
			'meta_compare' => '>=',
			'meta_type'  => 'NUMERIC',
			'count_total' => true,
			'number'     => 1,
			'fields'     => 'ID',
		] );
		$active_total = (int) $active_q->get_total();

		return new WP_REST_Response( [
			'total_staff'   => $total_managers + $total_recept,
			'managers'      => $total_managers,
			'receptionists' => $total_recept,
			'clinics'       => $clinic_total,
			'active_24h'    => $active_total,
		], 200 );
	}

	public function list_clinics() {
		$q = new \WP_Query( [
			'post_type'      => 'wpddb_clinic',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		$rows = [];
		foreach ( $q->posts as $p ) {
			$rows[] = [ 'id' => $p->ID, 'name' => $p->post_title ];
		}
		return new WP_REST_Response( $rows, 200 );
	}

	public function list_users( WP_REST_Request $request ) {
		$search = trim( (string) $request->get_param( 'search' ) );
		$args   = [ 'number' => 20, 'orderby' => 'user_login' ];
		if ( $search !== '' ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}
		$q    = new WP_User_Query( $args );
		$rows = [];
		foreach ( $q->get_results() as $u ) {
			$rows[] = [
				'id'         => $u->ID,
				'user_login' => $u->user_login,
				'user_email' => $u->user_email,
				'display'    => $u->display_name,
			];
		}
		return new WP_REST_Response( $rows, 200 );
	}

	private function serialize_user( \WP_User $user ) {
		$clinic_id = (int) get_user_meta( $user->ID, StaffContext::META_KEY, true );
		$roles     = (array) $user->roles;
		$role      = null;
		if ( in_array( ManagerRole::ROLE_SLUG, $roles, true ) )    $role = 'clinic_manager';
		elseif ( in_array( ReceptionistRole::ROLE_SLUG, $roles, true ) ) $role = 'receptionist';
		$clinic_name = $clinic_id ? get_the_title( $clinic_id ) : '';

		$last_login = (int) get_user_meta( $user->ID, LastLoginTracker::META_KEY, true );

		return [
			'id'              => $user->ID,
			'user_login'      => $user->user_login,
			'user_email'      => $user->user_email,
			'display'         => $user->display_name,
			'role'            => $role,
			'clinic_id'       => $clinic_id,
			'clinic_name'     => $clinic_name,
			'last_login'      => $last_login ?: null,
			'last_login_human'=> $last_login ? human_time_diff( $last_login, time() ) . ' ' . __( 'ago', 'doc-booker-pro' ) : '',
			'status'          => LastLoginTracker::status_for( $last_login ),
		];
	}
}
