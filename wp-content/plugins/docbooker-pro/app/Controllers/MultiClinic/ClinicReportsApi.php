<?php
/**
 * Clinic-admin extension endpoints — adds Test Orders + Reports management
 * to the existing /wpddb_pro/v1/dashboard/* family (which already serves the
 * clinic-dashboard React app). Registered separately to avoid bloating the
 * core MultiClinic\RestApi class.
 *
 *   GET    /wpddb_pro/v1/dashboard/orders
 *   GET    /wpddb_pro/v1/dashboard/orders/{id}
 *   GET    /wpddb_pro/v1/dashboard/orders/{id}/reports
 *   POST   /wpddb_pro/v1/dashboard/orders/{id}/reports        (multi-clinic upload path)
 *   DELETE /wpddb_pro/v1/dashboard/orders/{id}/reports/{report_id}
 */

namespace WpDreamers\WPDDBP\Controllers\MultiClinic;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WpDreamers\WPDDBP\Controllers\Receptionist\Model\Patient;
use WpDreamers\WPDDBP\Controllers\Receptionist\ReportUploader;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrder;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrderItem;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestReport;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class ClinicReportsApi {
	use SingletonTrait;

	public const NAMESPACE = 'wpddb_pro/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/dashboard/orders', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_orders' ],
			'permission_callback' => [ $this, 'verify' ],
		] );

		register_rest_route( self::NAMESPACE, '/dashboard/orders/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_order' ],
			'permission_callback' => [ $this, 'verify' ],
		] );

		register_rest_route( self::NAMESPACE, '/dashboard/orders/(?P<id>\d+)/reports', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_reports' ],
				'permission_callback' => [ $this, 'verify' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_reports' ],
				'permission_callback' => [ $this, 'verify' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/dashboard/orders/(?P<id>\d+)/reports/(?P<report_id>\d+)', [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'delete_report' ],
			'permission_callback' => [ $this, 'verify' ],
		] );
	}

	public function verify( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}
		return current_user_can( UserRole::CAPABILITY );
	}

	private function clinic_id() {
		return UserRole::get_user_clinic_id();
	}

	public function list_orders( WP_REST_Request $request ) {
		$clinic = $this->clinic_id();
		if ( ! $clinic ) {
			return new WP_REST_Response( [ 'rows' => [], 'total' => 0, 'unassigned' => true ], 200 );
		}
		$res = TestOrder::list( [
			'clinic_id' => $clinic,
			'search'    => $request->get_param( 'search' ),
			'page'      => (int) $request->get_param( 'page' ) ?: 1,
			'per_page'  => (int) $request->get_param( 'per_page' ) ?: 20,
		] );
		foreach ( $res['rows'] as &$row ) {
			$row['patient']       = Patient::enrich( Patient::find( (int) $row['patient_id'] ) );
			$row['totals']        = TestOrder::compute_totals( $row );
			$row['reports_count'] = count( TestReport::list_for_order( (int) $row['id'] ) );
		}
		return new WP_REST_Response( $res, 200 );
	}

	public function get_order( WP_REST_Request $request ) {
		$id    = (int) $request['id'];
		$order = TestOrder::find( $id );
		if ( ! $order ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Order not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		if ( (int) $order['clinic_id'] !== $this->clinic_id() ) {
			return new WP_Error( 'wpddbp_forbidden', __( 'Order is not in your clinic.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}
		$order['items']   = TestOrderItem::list_for_order( $id );
		$order['reports'] = TestReport::list_for_order( $id );
		foreach ( $order['reports'] as &$r ) {
			$r['filename'] = get_the_title( $r['attachment_id'] );
		}
		$order['patient'] = Patient::enrich( Patient::find( (int) $order['patient_id'] ) );
		$order['totals']  = TestOrder::compute_totals( $order );
		return new WP_REST_Response( $order, 200 );
	}

	public function list_reports( WP_REST_Request $request ) {
		$order = TestOrder::find( (int) $request['id'] );
		if ( ! $order || (int) $order['clinic_id'] !== $this->clinic_id() ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Order not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		$rows = TestReport::list_for_order( (int) $order['id'] );
		foreach ( $rows as &$r ) {
			$r['filename'] = get_the_title( $r['attachment_id'] );
		}
		return new WP_REST_Response( $rows, 200 );
	}

	public function upload_reports( WP_REST_Request $request ) {
		$order = TestOrder::find( (int) $request['id'] );
		if ( ! $order || (int) $order['clinic_id'] !== $this->clinic_id() ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Order not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		$result = ReportUploader::handle_files( (int) $order['id'] );
		if ( is_wp_error( $result ) ) return $result;
		return new WP_REST_Response( $result, 200 );
	}

	public function delete_report( WP_REST_Request $request ) {
		$order = TestOrder::find( (int) $request['id'] );
		if ( ! $order || (int) $order['clinic_id'] !== $this->clinic_id() ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Order not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		$report = TestReport::find( (int) $request['report_id'] );
		if ( ! $report || (int) $report['order_id'] !== (int) $order['id'] ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Report not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		wp_delete_attachment( (int) $report['attachment_id'], true );
		TestReport::delete( $report['id'] );
		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}
}
