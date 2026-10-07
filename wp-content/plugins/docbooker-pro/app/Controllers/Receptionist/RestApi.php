<?php
/**
 * Receptionist front-desk REST endpoints. All endpoints are clinic-scoped via
 * the caller's `wpddb_managed_clinic_id` user meta. Capability:
 * `wpddb_manage_reception` (admins also have it).
 *
 *   /reception/me
 *   /reception/bookings, /reception/bookings/{id}/cancel
 *   /reception/patients (search/create), /reception/patients/lookup
 *   /reception/patients/{id}, /reception/patients/{id}/visits, /reception/patients/{id}/orders
 *   /reception/test-items
 *   /reception/orders, /reception/orders/{id} (and /{code})
 *   /reception/orders/{id}/reports        (single-clinic mode only)
 *   /reception/visits, /reception/visits/{id}, /reception/visits/{id}/release
 *   /reception/visits/{id}/items[/{item_id}], /reception/visits/{id}/payments
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WpDreamers\WPDDB\Controllers\Admin\Api\RestApi as FreeRestApi;
use WpDreamers\WPDDBP\Controllers\Receptionist\Model\Patient;
use WpDreamers\WPDDBP\Controllers\Staff\StaffContext;
use WpDreamers\WPDDBP\Controllers\TestCatalog\Model\TestItem;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrder;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrderItem;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestReport;
use WpDreamers\WPDDBP\Controllers\Visits\Model\Visit;
use WpDreamers\WPDDBP\Controllers\Visits\Model\VisitItem;
use WpDreamers\WPDDBP\Controllers\Visits\Model\VisitPayment;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class RestApi {
	use SingletonTrait;

	public const NAMESPACE = 'wpddb_pro/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		$ns = self::NAMESPACE;

		register_rest_route( $ns, '/reception/me', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_me' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/bookings', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_bookings' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/patients', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_patients' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_patient' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/patients/lookup', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'lookup_patient_by_phone' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/patients/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_patient' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_patient' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/patients/(?P<id>\d+)/visits', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_patient_visits' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/patients/(?P<id>\d+)/orders', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_patient_orders' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/test-items', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_test_items' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/orders', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_orders' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_order' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/orders/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_order' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_order' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/orders/(?P<id>\d+)/cancel', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'cancel_order' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/orders/(?P<id>\d+)/reports', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_order_reports' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_order_reports' ],
				'permission_callback' => [ $this, 'verify_can_upload' ],
			],
		] );

		register_rest_route( $ns, '/reception/orders/(?P<id>\d+)/reports/(?P<report_id>\d+)', [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'delete_order_report' ],
			'permission_callback' => [ $this, 'verify_can_upload' ],
		] );

		register_rest_route( $ns, '/reception/visits', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_visits' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_visit' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/visits/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_visit' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_visit' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/visits/(?P<id>\d+)/release', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'release_visit' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );

		register_rest_route( $ns, '/reception/visits/(?P<id>\d+)/items', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_visit_items' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_visit_item' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/visits/(?P<id>\d+)/items/(?P<item_id>\d+)', [
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_visit_item' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_visit_item' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/visits/(?P<id>\d+)/payments', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_visit_payments' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_visit_payment' ],
				'permission_callback' => [ $this, 'verify_reception' ],
			],
		] );

		register_rest_route( $ns, '/reception/clinic/doctors', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_clinic_doctors' ],
			'permission_callback' => [ $this, 'verify_reception' ],
		] );
	}

	// =========================================================
	// Permission callbacks
	// =========================================================

	public function verify_reception( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}
		return current_user_can( UserRole::CAPABILITY ) || current_user_can( 'manage_options' );
	}

	public function verify_can_upload( WP_REST_Request $request ) {
		if ( ! $this->verify_reception( $request ) ) return false;
		// Receptionist may upload only when multi-clinic is OFF (clinic admins handle uploads in multi-clinic mode).
		if ( current_user_can( 'manage_options' ) ) return true;
		return ! StaffContext::is_multi_clinic_enabled();
	}

	// =========================================================
	// /me + clinic helpers
	// =========================================================

	public function get_me() {
		$user      = wp_get_current_user();
		$clinic_id = StaffContext::get_current_staff_clinic_id();
		$clinic    = null;
		if ( $clinic_id ) {
			$post = get_post( $clinic_id );
			if ( $post ) {
				$clinic = [
					'id'      => $post->ID,
					'name'    => $post->post_title,
					'address' => get_post_meta( $post->ID, 'wpddb_clinic_address', true ),
					'hotline' => get_post_meta( $post->ID, 'wpddb_clinic_hotline', true ),
					'email'   => get_post_meta( $post->ID, 'wpddb_clinic_email', true ),
				];
			}
		}
		return new WP_REST_Response( [
			'id'                 => $user->ID,
			'display'            => $user->display_name,
			'login'              => $user->user_login,
			'clinic'             => $clinic,
			'multi_clinic'       => StaffContext::is_multi_clinic_enabled(),
			'can_upload_reports' => ! StaffContext::is_multi_clinic_enabled() || current_user_can( 'manage_options' ),
		], 200 );
	}

	private function caller_clinic_id() {
		$cid = StaffContext::get_current_staff_clinic_id();
		if ( ! $cid && current_user_can( 'manage_options' ) ) {
			// Site admin testing as receptionist — fall back to the only published clinic.
			$cid = StaffContext::single_clinic_id_or_zero();
		}
		return (int) $cid;
	}

	private function ensure_clinic_assigned() {
		$cid = $this->caller_clinic_id();
		if ( $cid <= 0 ) {
			return new WP_Error( 'wpddbp_no_clinic', __( 'Your account is not linked to a clinic yet.', 'doc-booker-pro' ), [ 'status' => 409 ] );
		}
		return $cid;
	}

	// =========================================================
	// Patients
	// =========================================================

	public function list_patients( WP_REST_Request $request ) {
		$result = Patient::search( [
			'search'   => $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ) ?: 1,
			'per_page' => (int) $request->get_param( 'per_page' ) ?: 20,
		] );
		return new WP_REST_Response( $result, 200 );
	}

	public function lookup_patient_by_phone( WP_REST_Request $request ) {
		$phone = trim( (string) $request->get_param( 'phone' ) );
		if ( $phone === '' ) {
			return new WP_REST_Response( null, 200 );
		}
		$p = Patient::find_by_phone( $phone );
		return new WP_REST_Response( $p ? Patient::enrich( $p ) : null, 200 );
	}

	public function create_patient( WP_REST_Request $request ) {
		$body = $this->body( $request );
		if ( empty( $body['full_name'] ) || empty( $body['phone'] ) ) {
			return new WP_Error( 'wpddbp_required', __( 'Name and phone are required.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		$id = Patient::create( $body );
		if ( ! $id ) {
			return new WP_Error( 'wpddbp_insert_failed', __( 'Could not create patient.', 'doc-booker-pro' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( Patient::enrich( Patient::find( $id ) ), 201 );
	}

	public function get_patient( WP_REST_Request $request ) {
		$p = Patient::find( (int) $request['id'] );
		if ( ! $p ) return new WP_Error( 'wpddbp_not_found', __( 'Patient not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		return new WP_REST_Response( Patient::enrich( $p ), 200 );
	}

	public function update_patient( WP_REST_Request $request ) {
		$p = Patient::find( (int) $request['id'] );
		if ( ! $p ) return new WP_Error( 'wpddbp_not_found', __( 'Patient not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		Patient::update( $p['id'], $this->body( $request ) );
		return new WP_REST_Response( Patient::enrich( Patient::find( $p['id'] ) ), 200 );
	}

	public function list_patient_visits( WP_REST_Request $request ) {
		$clinic = $this->ensure_clinic_assigned();
		if ( is_wp_error( $clinic ) ) return $clinic;
		$res = Visit::list( [
			'patient_id' => (int) $request['id'],
			'clinic_id'  => $clinic,
			'page'       => (int) $request->get_param( 'page' ) ?: 1,
			'per_page'   => 50,
		] );
		// Enrich with items + payments for the expanded breakdown view.
		foreach ( $res['rows'] as &$row ) {
			$row['items']    = VisitItem::list_for_visit( $row['id'] );
			$row['payments'] = VisitPayment::list_for_visit( $row['id'] );
			$row['due']      = max( 0, (float) $row['total_charge'] - (float) $row['received'] );
			$doctor_id = ( ( $row['visit_type'] ?? 'opd' ) === 'ipd' )
				? (int) ( $row['admit_doctor_id'] ?: $row['consult_doctor_id'] )
				: (int) ( $row['consult_doctor_id'] ?: $row['admit_doctor_id'] );
			$row['doctor_name'] = $doctor_id ? get_the_title( $doctor_id ) : '';
		}
		return new WP_REST_Response( $res, 200 );
	}

	public function list_patient_orders( WP_REST_Request $request ) {
		$clinic = $this->ensure_clinic_assigned();
		if ( is_wp_error( $clinic ) ) return $clinic;
		$res = TestOrder::list( [
			'patient_id' => (int) $request['id'],
			'clinic_id'  => $clinic,
			'page'       => 1,
			'per_page'   => 50,
		] );
		foreach ( $res['rows'] as &$row ) {
			$row['items']   = TestOrderItem::list_for_order( $row['id'] );
			$row['reports'] = TestReport::list_for_order( $row['id'] );
			$row['totals']  = TestOrder::compute_totals( $row );
		}
		return new WP_REST_Response( $res, 200 );
	}

	// =========================================================
	// Bookings (delegates to free plugin)
	// =========================================================

	public function list_bookings( WP_REST_Request $request ) {
		// Mirror MultiClinic\RestApi::list_my_bookings — call the free plugin's
		// static helpers directly so we don't hit the booking_details endpoint's
		// `wpddb_manage_booking_system` cap gate (receptionists don't have it).
		$clinic_id = $this->caller_clinic_id();
		if ( $clinic_id <= 0 ) {
			return new WP_REST_Response( [ 'rows' => [], 'total' => 0 ], 200 );
		}

		$args              = FreeRestApi::collect_booking_query_args( $request );
		$args['clinic_id'] = $clinic_id;

		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$rows  = FreeRestApi::run_booking_query( $args, $per_page, $offset );
		$total = (int) FreeRestApi::run_booking_count( $args );

		return new WP_REST_Response( [ 'rows' => $rows, 'total' => $total ], 200 );
	}

	// =========================================================
	// Test items (for the new-order picker)
	// =========================================================

	public function list_test_items( WP_REST_Request $request ) {
		$clinic = $this->caller_clinic_id();
		$result = TestItem::list_for_clinic( $clinic, [
			'search'   => $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ) ?: 1,
			'per_page' => (int) $request->get_param( 'per_page' ) ?: 100,
		] );
		return new WP_REST_Response( $result, 200 );
	}

	// =========================================================
	// Orders
	// =========================================================

	public function list_orders( WP_REST_Request $request ) {
		$clinic = $this->ensure_clinic_assigned();
		if ( is_wp_error( $clinic ) ) return $clinic;
		$res = TestOrder::list( [
			'clinic_id' => $clinic,
			'search'    => $request->get_param( 'search' ),
			'page'      => (int) $request->get_param( 'page' ) ?: 1,
			'per_page'  => (int) $request->get_param( 'per_page' ) ?: 20,
		] );
		foreach ( $res['rows'] as &$row ) {
			$row['patient'] = Patient::enrich( Patient::find( (int) $row['patient_id'] ) );
			$row['totals']  = TestOrder::compute_totals( $row );
			$row['reports_count'] = count( TestReport::list_for_order( (int) $row['id'] ) );
		}
		return new WP_REST_Response( $res, 200 );
	}

	public function create_order( WP_REST_Request $request ) {
		$clinic = $this->ensure_clinic_assigned();
		if ( is_wp_error( $clinic ) ) return $clinic;

		$body       = $this->body( $request );
		$patient_id = (int) ( $body['patient_id'] ?? 0 );
		if ( ! $patient_id ) {
			return new WP_Error( 'wpddbp_patient_required', __( 'Patient is required.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		$patient = Patient::find( $patient_id );
		if ( ! $patient ) {
			return new WP_Error( 'wpddbp_patient_not_found', __( 'Patient not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		$items = isset( $body['items'] ) && is_array( $body['items'] ) ? $body['items'] : [];
		if ( ! $items ) {
			return new WP_Error( 'wpddbp_items_required', __( 'At least one test item is required.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		$order_id = TestOrder::create( [
			'patient_id'      => $patient_id,
			'clinic_id'       => $clinic,
			'receptionist_id' => get_current_user_id(),
			'refd_by'         => $body['refd_by'] ?? null,
			'discount_amount' => $body['discount_amount'] ?? 0,
			'extra_discount'  => $body['extra_discount'] ?? 0,
			'received'        => $body['received'] ?? 0,
			'payment_status'  => $body['payment_status'] ?? 'unpaid',
			'delivery_at'     => $body['delivery_at'] ?? null,
			'notes'           => $body['notes'] ?? null,
			'subtotal'        => 0,
		] );
		if ( ! $order_id ) {
			return new WP_Error( 'wpddbp_insert_failed', __( 'Could not create order.', 'doc-booker-pro' ), [ 'status' => 500 ] );
		}

		foreach ( $items as $line ) {
			$test_item_id   = isset( $line['test_item_id'] ) ? (int) $line['test_item_id'] : null;
			$name           = $line['name'] ?? '';
			$price          = (float) ( $line['price'] ?? 0 );
			if ( $test_item_id ) {
				$catalog = TestItem::find( $test_item_id );
				if ( $catalog ) {
					$name  = $name ?: $catalog['name'];
					$price = $price ?: (float) $catalog['price'];
				}
			}
			if ( $name === '' ) continue;
			TestOrderItem::create( [
				'order_id'       => $order_id,
				'test_item_id'   => $test_item_id ?: null,
				'name_snapshot'  => $name,
				'price_snapshot' => $price,
				'qty'            => max( 1, (int) ( $line['qty'] ?? 1 ) ),
				'disc_percent'   => (float) ( $line['disc_percent'] ?? 0 ),
			] );
		}
		// Subtotal is the GROSS sum (qty × price); per-line discounts are aggregated
		// into discount_amount so the summary + receipt read
		// Subtotal − Discount − Extra discount = Payable. "Extra discount" stays
		// the manual flat discount from the request.
		$gross = TestOrderItem::compute_gross_for_order( $order_id );
		$net   = TestOrderItem::compute_subtotal_for_order( $order_id );
		TestOrder::update( $order_id, [
			'subtotal'        => $gross,
			'discount_amount' => round( $gross - $net, 2 ),
		] );
		$payment_status = $this->derive_payment_status( $order_id );
		TestOrder::update( $order_id, [ 'payment_status' => $payment_status ] );

		return new WP_REST_Response( $this->order_response( $order_id ), 201 );
	}

	public function get_order( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return new WP_REST_Response( $this->order_response( $id ), 200 );
	}

	public function update_order( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$body = $this->body( $request );
		TestOrder::update( $id, $body );
		if ( isset( $body['received'] ) ) {
			TestOrder::update( $id, [ 'payment_status' => $this->derive_payment_status( $id ) ] );
		}
		return new WP_REST_Response( $this->order_response( $id ), 200 );
	}

	public function cancel_order( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		TestOrder::update( $id, [ 'status' => 'cancelled' ] );
		return new WP_REST_Response( $this->order_response( $id ), 200 );
	}

	public function list_order_reports( WP_REST_Request $request ) {
		$rows = TestReport::list_for_order( (int) $request['id'] );
		foreach ( $rows as &$r ) {
			$r['filename'] = get_the_title( $r['attachment_id'] );
		}
		return new WP_REST_Response( $rows, 200 );
	}

	public function upload_order_reports( WP_REST_Request $request ) {
		$result = ReportUploader::handle_files( (int) $request['id'] );
		if ( is_wp_error( $result ) ) return $result;
		return new WP_REST_Response( $result, 200 );
	}

	public function delete_order_report( WP_REST_Request $request ) {
		$report = TestReport::find( (int) $request['report_id'] );
		if ( ! $report ) return new WP_Error( 'wpddbp_not_found', __( 'Report not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		wp_delete_attachment( (int) $report['attachment_id'], true );
		TestReport::delete( $report['id'] );
		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	private function derive_payment_status( $order_id ) {
		$order   = TestOrder::find( $order_id );
		if ( ! $order ) return 'unpaid';
		$totals  = TestOrder::compute_totals( $order );
		$payable = (float) $totals['payable'];
		$rcv     = (float) $order['received'];
		if ( $payable <= 0 || $rcv >= $payable ) return 'paid';
		if ( $rcv > 0 ) return 'partial';
		return 'unpaid';
	}

	private function order_response( $order_id ) {
		$order = TestOrder::find( $order_id );
		if ( ! $order ) return null;
		$order['items']   = TestOrderItem::list_for_order( $order_id );
		$order['reports'] = TestReport::list_for_order( $order_id );
		foreach ( $order['reports'] as &$r ) {
			$r['filename'] = get_the_title( $r['attachment_id'] );
		}
		$order['patient'] = Patient::enrich( Patient::find( (int) $order['patient_id'] ) );
		$order['totals']  = TestOrder::compute_totals( $order );
		// Receipt URL with HMAC token for the print page.
		$token = $this->build_receipt_token( $order['order_code'] );
		$order['receipt_url'] = add_query_arg( [
			'wpddb_receipt' => $order['order_code'],
			't'             => $token,
		], home_url( '/' ) );
		return $order;
	}

	public static function build_receipt_token( $order_code ) {
		return substr( hash_hmac( 'sha256', 'receipt|' . $order_code, wp_salt( 'auth' ) ), 0, 32 );
	}

	public static function build_visit_bill_token( $visit_id ) {
		return substr( hash_hmac( 'sha256', 'visit-bill|' . (int) $visit_id, wp_salt( 'auth' ) ), 0, 32 );
	}

	// =========================================================
	// Visits
	// =========================================================

	public function list_visits( WP_REST_Request $request ) {
		$clinic = $this->ensure_clinic_assigned();
		if ( is_wp_error( $clinic ) ) return $clinic;

		$res = Visit::list( [
			'clinic_id' => $clinic,
			'visit_type'=> $request->get_param( 'visit_type' ),
			'status'    => $request->get_param( 'status' ),
			'due_only'  => (bool) $request->get_param( 'due_only' ),
			'page'      => (int) $request->get_param( 'page' ) ?: 1,
			'per_page'  => (int) $request->get_param( 'per_page' ) ?: 20,
		] );
		foreach ( $res['rows'] as &$row ) {
			$row['patient'] = Patient::enrich( Patient::find( (int) $row['patient_id'] ) );
			$row['due']     = max( 0, (float) $row['total_charge'] - (float) $row['received'] );
			// Relevant doctor for the list: admitting doctor for IPD, consulting for OPD.
			$doctor_id = ( ( $row['visit_type'] ?? 'opd' ) === 'ipd' )
				? (int) ( $row['admit_doctor_id'] ?: $row['consult_doctor_id'] )
				: (int) ( $row['consult_doctor_id'] ?: $row['admit_doctor_id'] );
			$row['doctor_name'] = $doctor_id ? get_the_title( $doctor_id ) : '';
		}
		return new WP_REST_Response( $res, 200 );
	}

	public function create_visit( WP_REST_Request $request ) {
		$clinic = $this->ensure_clinic_assigned();
		if ( is_wp_error( $clinic ) ) return $clinic;

		$body       = $this->body( $request );
		$patient_id = (int) ( $body['patient_id'] ?? 0 );
		if ( ! $patient_id ) {
			return new WP_Error( 'wpddbp_patient_required', __( 'Patient is required.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}
		$visit_id = Visit::create( [
			'patient_id'        => $patient_id,
			'clinic_id'         => $clinic,
			'visit_type'        => $body['visit_type'] ?? 'opd',
			'visit_date'        => $body['visit_date'] ?? gmdate( 'Y-m-d' ),
			'consult_doctor_id' => $body['consult_doctor_id'] ?? null,
			'admit_doctor_id'   => $body['admit_doctor_id'] ?? null,
			'admitted_at'       => $body['admitted_at'] ?? null,
			'bed_no'            => $body['bed_no'] ?? null,
			'notes'             => $body['notes'] ?? null,
			'created_by'        => get_current_user_id(),
		] );
		if ( ! $visit_id ) {
			return new WP_Error( 'wpddbp_insert_failed', __( 'Could not create visit.', 'doc-booker-pro' ), [ 'status' => 500 ] );
		}

		// Auto-add a consultation line for OPD if a doctor was picked.
		if ( ( $body['visit_type'] ?? 'opd' ) === 'opd' && ! empty( $body['consult_doctor_id'] ) ) {
			$fee = (float) get_post_meta( (int) $body['consult_doctor_id'], 'wpddb_doctor_booking_fee', true );
			$doctor_title = get_the_title( (int) $body['consult_doctor_id'] );
			VisitItem::create( [
				'visit_id'   => $visit_id,
				'item_type'  => 'consultation',
				'name'       => sprintf( __( 'Consultation - %s', 'doc-booker-pro' ), $doctor_title ),
				'qty'        => 1,
				'unit_price' => $fee,
				'disc_percent' => 0,
				'item_date'  => gmdate( 'Y-m-d' ),
			] );
		}
		// Initial received in body (OPD typically pays upfront).
		if ( ! empty( $body['received_amount'] ) ) {
			VisitPayment::create( [
				'visit_id'    => $visit_id,
				'amount'      => (float) $body['received_amount'],
				'method'      => $body['payment_method'] ?? 'cash',
				'received_by' => get_current_user_id(),
			] );
		}
		Visit::recompute_totals( $visit_id );
		return new WP_REST_Response( $this->visit_response( $visit_id ), 201 );
	}

	public function get_visit( WP_REST_Request $request ) {
		return new WP_REST_Response( $this->visit_response( (int) $request['id'] ), 200 );
	}

	public function update_visit( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		Visit::update( $id, $this->body( $request ) );
		return new WP_REST_Response( $this->visit_response( $id ), 200 );
	}

	public function release_visit( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$visit   = Visit::find( $id );
		if ( ! $visit ) return new WP_Error( 'wpddbp_not_found', __( 'Visit not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );

		$body         = $this->body( $request );
		$released_at  = $body['released_at'] ?? current_time( 'mysql' );
		$bed_qty_override = isset( $body['bed_qty'] ) ? max( 1, (int) $body['bed_qty'] ) : null;

		// Optional final payment.
		if ( ! empty( $body['final_payment'] ) ) {
			VisitPayment::create( [
				'visit_id'    => $id,
				'amount'      => (float) $body['final_payment'],
				'method'      => $body['payment_method'] ?? 'cash',
				'received_by' => get_current_user_id(),
			] );
		}

		// Auto-bump bed item qty by days stayed if present.
		if ( $bed_qty_override ) {
			$items = VisitItem::list_for_visit( $id );
			foreach ( $items as $it ) {
				if ( $it['item_type'] === 'bed' ) {
					VisitItem::update( $it['id'], [ 'qty' => $bed_qty_override ] );
					break;
				}
			}
		}

		Visit::update( $id, [
			'released_at' => $released_at,
			'status'      => 'released',
		] );
		Visit::recompute_totals( $id );
		return new WP_REST_Response( $this->visit_response( $id ), 200 );
	}

	public function list_visit_items( WP_REST_Request $request ) {
		return new WP_REST_Response( VisitItem::list_for_visit( (int) $request['id'] ), 200 );
	}

	public function create_visit_item( WP_REST_Request $request ) {
		$visit_id = (int) $request['id'];
		$body     = $this->body( $request );
		$body['visit_id'] = $visit_id;
		$id = VisitItem::create( $body );
		Visit::recompute_totals( $visit_id );
		return new WP_REST_Response( [ 'id' => $id, 'visit' => $this->visit_response( $visit_id ) ], 201 );
	}

	public function update_visit_item( WP_REST_Request $request ) {
		$visit_id = (int) $request['id'];
		VisitItem::update( (int) $request['item_id'], $this->body( $request ) );
		Visit::recompute_totals( $visit_id );
		return new WP_REST_Response( $this->visit_response( $visit_id ), 200 );
	}

	public function delete_visit_item( WP_REST_Request $request ) {
		$visit_id = (int) $request['id'];
		VisitItem::delete( (int) $request['item_id'] );
		Visit::recompute_totals( $visit_id );
		return new WP_REST_Response( $this->visit_response( $visit_id ), 200 );
	}

	public function list_visit_payments( WP_REST_Request $request ) {
		return new WP_REST_Response( VisitPayment::list_for_visit( (int) $request['id'] ), 200 );
	}

	public function create_visit_payment( WP_REST_Request $request ) {
		$visit_id = (int) $request['id'];
		$body     = $this->body( $request );
		$body['visit_id'] = $visit_id;
		VisitPayment::create( $body );
		Visit::recompute_totals( $visit_id );
		return new WP_REST_Response( $this->visit_response( $visit_id ), 201 );
	}

	private function visit_response( $id ) {
		$visit = Visit::find( $id );
		if ( ! $visit ) return null;
		$visit['items']    = VisitItem::list_for_visit( $id );
		$visit['payments'] = VisitPayment::list_for_visit( $id );
		$visit['patient']  = Patient::enrich( Patient::find( (int) $visit['patient_id'] ) );
		$visit['due']      = max( 0, (float) $visit['total_charge'] - (float) $visit['received'] );
		if ( ! empty( $visit['consult_doctor_id'] ) ) {
			$visit['consult_doctor'] = [ 'id' => (int) $visit['consult_doctor_id'], 'name' => get_the_title( (int) $visit['consult_doctor_id'] ) ];
		}
		if ( ! empty( $visit['admit_doctor_id'] ) ) {
			$visit['admit_doctor'] = [ 'id' => (int) $visit['admit_doctor_id'], 'name' => get_the_title( (int) $visit['admit_doctor_id'] ) ];
		}
		// Printable bill/invoice URL (interim while admitted, final once released).
		$visit['bill_url'] = add_query_arg( [
			'wpddb_visit_bill' => (int) $id,
			't'                => self::build_visit_bill_token( (int) $id ),
		], home_url( '/' ) );
		return $visit;
	}

	// =========================================================
	// Doctors picker (for visit forms)
	// =========================================================

	public function list_clinic_doctors() {
		$clinic = $this->caller_clinic_id();
		$args   = [
			'post_type'      => 'wpddb_doctor',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];
		if ( $clinic ) {
			$args['meta_query'] = [
				[
					'key'     => 'wpddb_doctor_available_clinic',
					'value'   => '"' . $clinic . '"',
					'compare' => 'LIKE',
				],
			];
		}
		$q    = new \WP_Query( $args );
		$rows = [];
		foreach ( $q->posts as $p ) {
			$rows[] = [
				'id'   => $p->ID,
				'name' => $p->post_title,
				'fee'  => (float) get_post_meta( $p->ID, 'wpddb_doctor_booking_fee', true ),
			];
		}
		return new WP_REST_Response( $rows, 200 );
	}

	// =========================================================
	// Utility
	// =========================================================

	private function body( WP_REST_Request $request ) {
		$json = $request->get_json_params();
		if ( is_array( $json ) ) return $json;
		return $request->get_params();
	}
}
