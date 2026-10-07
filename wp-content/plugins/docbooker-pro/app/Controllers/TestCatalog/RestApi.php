<?php
/**
 * Test Items REST. Admin endpoints manage the global + per-clinic catalog;
 * the clinic-admin dashboard uses the same endpoints scoped to their clinic.
 *
 *   GET    /wpddb_pro/v1/test-items?clinic_id=&search=&page=&per_page=
 *   POST   /wpddb_pro/v1/test-items                  (admin: any clinic_id, 0=global)
 *   PATCH  /wpddb_pro/v1/test-items/{id}             (admin)
 *   DELETE /wpddb_pro/v1/test-items/{id}             (admin)
 *   POST   /wpddb_pro/v1/test-items/import           (multipart CSV)
 */

namespace WpDreamers\WPDDBP\Controllers\TestCatalog;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WpDreamers\WPDDBP\Controllers\TestCatalog\Model\TestItem;
use WpDreamers\WPDDBP\Controllers\Staff\StaffContext;
use WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole as ManagerRole;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class RestApi {
	use SingletonTrait;

	public const NAMESPACE = 'wpddb_pro/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/test-items', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_items' ],
				'permission_callback' => [ $this, 'verify_admin_or_clinic' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'verify_admin_or_clinic' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/test-items/(?P<id>\d+)', [
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'verify_admin_or_clinic' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'verify_admin_or_clinic' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/test-items/import', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'import_csv' ],
			'permission_callback' => [ $this, 'verify_admin_or_clinic' ],
		] );
	}

	public function verify_admin_or_clinic( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}
		return current_user_can( 'manage_options' ) || current_user_can( ManagerRole::CAPABILITY );
	}

	private function scope_clinic_id( WP_REST_Request $request ) {
		// Admins may pass any clinic_id (including 0 for global). Clinic admins
		// are locked to their own clinic.
		if ( current_user_can( 'manage_options' ) ) {
			return null === $request->get_param( 'clinic_id' )
				? null
				: (int) $request->get_param( 'clinic_id' );
		}
		return StaffContext::get_current_staff_clinic_id();
	}

	public function list_items( WP_REST_Request $request ) {
		$args = [
			'search'   => $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ) ?: 1,
			'per_page' => (int) $request->get_param( 'per_page' ) ?: 50,
		];
		if ( current_user_can( 'manage_options' ) ) {
			if ( null !== $request->get_param( 'clinic_id' ) ) {
				$args['clinic_id'] = (int) $request->get_param( 'clinic_id' );
			}
			$result = TestItem::list_admin( $args );
		} else {
			$clinic_id = StaffContext::get_current_staff_clinic_id();
			$result    = TestItem::list_for_clinic( $clinic_id, $args );
		}
		return new WP_REST_Response( $result, 200 );
	}

	public function create_item( WP_REST_Request $request ) {
		$data = [
			'clinic_id' => $this->resolve_clinic_id_for_write( $request ),
			'name'      => $request->get_param( 'name' ),
			'price'     => $request->get_param( 'price' ),
			'category'  => $request->get_param( 'category' ),
			'status'    => $request->get_param( 'status' ),
		];
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'wpddbp_name_required', __( 'Name is required.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		$dup = TestItem::find_by_clinic_name( $data['clinic_id'], $data['name'] );
		if ( $dup ) {
			return new WP_Error( 'wpddbp_duplicate', __( 'A test item with this name already exists for this clinic.', 'doc-booker-pro' ), [ 'status' => 409 ] );
		}
		$id = TestItem::create( $data );
		if ( ! $id ) {
			return new WP_Error( 'wpddbp_insert_failed', __( 'Failed to create.', 'doc-booker-pro' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( TestItem::find( $id ), 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$row = TestItem::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Test item not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		if ( ! $this->can_write_to( $row['clinic_id'] ) ) {
			return new WP_Error( 'wpddbp_forbidden', __( 'You cannot modify this item.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}
		$data = [];
		foreach ( [ 'name', 'price', 'category', 'status' ] as $k ) {
			if ( $request->has_param( $k ) ) {
				$data[ $k ] = $request->get_param( $k );
			}
		}

		// Admins may reassign the item's clinic scope on edit (0 = global). Clinic
		// admins are locked to their own clinic, so any clinic_id they send is ignored.
		if ( current_user_can( 'manage_options' ) && $request->has_param( 'clinic_id' ) ) {
			$data['clinic_id'] = max( 0, (int) $request->get_param( 'clinic_id' ) );
		}

		// Guard the UNIQUE(clinic_id, name) index when the name or scope changes.
		$target_clinic = array_key_exists( 'clinic_id', $data ) ? (int) $data['clinic_id'] : (int) $row['clinic_id'];
		$target_name   = array_key_exists( 'name', $data ) ? (string) $data['name'] : (string) $row['name'];
		$dup           = TestItem::find_by_clinic_name( $target_clinic, $target_name );
		if ( $dup && (int) $dup['id'] !== (int) $row['id'] ) {
			return new WP_Error( 'wpddbp_duplicate', __( 'A test item with this name already exists for this clinic.', 'doc-booker-pro' ), [ 'status' => 409 ] );
		}

		TestItem::update( $row['id'], $data );
		return new WP_REST_Response( TestItem::find( $row['id'] ), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$row = TestItem::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Test item not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		if ( ! $this->can_write_to( $row['clinic_id'] ) ) {
			return new WP_Error( 'wpddbp_forbidden', __( 'You cannot delete this item.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}
		TestItem::delete( $row['id'] );
		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	private function resolve_clinic_id_for_write( WP_REST_Request $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			$id = (int) $request->get_param( 'clinic_id' );
			return $id >= 0 ? $id : 0;
		}
		return StaffContext::get_current_staff_clinic_id();
	}

	private function can_write_to( $clinic_id ) {
		if ( current_user_can( 'manage_options' ) ) return true;
		$mine = StaffContext::get_current_staff_clinic_id();
		return $mine && (int) $clinic_id === (int) $mine;
	}

	/**
	 * CSV upload. Expects a file under $_FILES['file'] with columns:
	 *   name, price, category(optional), clinic_id(optional, admin only)
	 * Returns: { inserted, updated, skipped, errors: [{row, message}] }
	 */
	public function import_csv( WP_REST_Request $request ) {
		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			return new WP_Error( 'wpddbp_no_file', __( 'No CSV file uploaded.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		$tmp  = $_FILES['file']['tmp_name'];
		$size = (int) ( $_FILES['file']['size'] ?? 0 );
		if ( $size <= 0 || $size > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'wpddbp_size', __( 'CSV file is empty or exceeds 5MB.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		$handle = fopen( $tmp, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'wpddbp_open_failed', __( 'Could not read the uploaded file.', 'doc-booker-pro' ), [ 'status' => 500 ] );
		}

		$inserted = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = [];
		$row_num  = 0;
		$header   = null;

		$default_clinic = current_user_can( 'manage_options' ) ? 0 : StaffContext::get_current_staff_clinic_id();

		while ( ( $line = fgetcsv( $handle ) ) !== false ) {
			$row_num++;
			if ( $row_num === 1 ) {
				$header = array_map( 'strtolower', array_map( 'trim', $line ) );
				if ( ! in_array( 'name', $header, true ) || ! in_array( 'price', $header, true ) ) {
					fclose( $handle );
					return new WP_Error(
						'wpddbp_bad_header',
						__( 'CSV header must include at least "name" and "price".', 'doc-booker-pro' ),
						[ 'status' => 400 ]
					);
				}
				continue;
			}
			$cells = array_combine( $header, array_pad( $line, count( $header ), '' ) );
			if ( ! $cells ) {
				$skipped++;
				$errors[] = [ 'row' => $row_num, 'message' => 'Column count mismatch' ];
				continue;
			}
			$name = trim( (string) ( $cells['name'] ?? '' ) );
			if ( $name === '' ) {
				$skipped++;
				$errors[] = [ 'row' => $row_num, 'message' => 'Missing name' ];
				continue;
			}
			$price = (float) ( $cells['price'] ?? 0 );
			if ( $price < 0 ) {
				$skipped++;
				$errors[] = [ 'row' => $row_num, 'message' => 'Invalid price' ];
				continue;
			}
			$category  = isset( $cells['category'] ) ? trim( (string) $cells['category'] ) : null;
			$clinic_id = $default_clinic;
			if ( current_user_can( 'manage_options' ) && isset( $cells['clinic_id'] ) && $cells['clinic_id'] !== '' ) {
				$clinic_id = (int) $cells['clinic_id'];
			}

			[ $op, $id ] = TestItem::upsert( $clinic_id, $name, $price, $category );
			if ( $op === 'inserted' ) $inserted++;
			elseif ( $op === 'updated' ) $updated++;
		}
		fclose( $handle );

		return new WP_REST_Response( [
			'inserted' => $inserted,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'errors'   => $errors,
		], 200 );
	}
}
