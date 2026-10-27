<?php
/**
 * Patient online report portal — public REST endpoints.
 *
 *   POST /wpddb_pro/v1/patient-portal/request-otp  { phone, order_code }
 *   POST /wpddb_pro/v1/patient-portal/verify-otp   { phone, order_code, code }
 *   GET  /wpddb_pro/v1/patient-portal/reports                            (bearer)
 *   GET  /wpddb_pro/v1/patient-portal/reports/{id}/view?exp=&sig=         (bearer + signed)
 *   GET  /wpddb_pro/v1/patient-portal/reports/{id}/download?exp=&sig=     (bearer + signed)
 */

namespace WpDreamers\WPDDBP\Controllers\PatientPortal;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WpDreamers\WPDDBP\Controllers\Receptionist\Model\Patient;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrder;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrderItem;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestReport;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class RestApi {
	use SingletonTrait;

	public const NAMESPACE = 'wpddb_pro/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/patient-portal/request-otp', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'request_otp' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/patient-portal/verify-otp', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'verify_otp' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/patient-portal/reports', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_reports' ],
			'permission_callback' => [ $this, 'verify_bearer' ],
		] );

		register_rest_route( self::NAMESPACE, '/patient-portal/reports/(?P<id>\d+)/view', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'view_report' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/patient-portal/reports/(?P<id>\d+)/download', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'download_report' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function request_otp( WP_REST_Request $request ) {
		$body       = $request->get_json_params() ?: $request->get_params();
		$phone      = trim( (string) ( $body['phone'] ?? '' ) );
		$order_code = trim( (string) ( $body['order_code'] ?? '' ) );
		// Where codes go is a site setting, not a fact about this order, so
		// saying it never reveals whether the order exists.
		$sent = [ 'ok' => true, 'channel' => Sms::is_configured() ? 'sms' : 'email' ];

		if ( $phone === '' || $order_code === '' ) {
			// Don't leak which field is missing.
			return new WP_REST_Response( $sent, 200 );
		}

		// Limited per network before anything is looked up, so the limit itself
		// says nothing about which phone/order pairs exist.
		if ( ! Otp::allow_request_from() ) {
			return new WP_Error( 'wpddbp_otp_ip_limit', __( 'Too many requests from this network. Please try again later.', 'doc-booker-pro' ), [ 'status' => 429 ] );
		}

		// Validate phone matches an order (without surfacing whether it does).
		$order = TestOrder::find_for_phone_and_code( $phone, $order_code );
		if ( ! $order ) {
			// Pretend success to avoid enumeration.
			return new WP_REST_Response( $sent, 200 );
		}

		// Check retention window: reports must still be available.
		// (We allow OTP even if no reports yet — patient can come back later.)

		$res = Otp::request( $phone, $order_code );
		if ( is_wp_error( $res ) ) {
			// A code was sent under a minute ago. Answer exactly as for an
			// unknown pair; the code already sent still works.
			return new WP_REST_Response( $sent, 200 );
		}

		if ( Sms::is_configured() ) {
			Sms::send_otp( $phone, $res['code'] );
		} else {
			// No text service: email the code to the patient on this order.
			self::email_otp( $order, $res['code'] );
		}
		return new WP_REST_Response( $sent, 200 );
	}

	/**
	 * Email a portal login code to the patient on the order.
	 */
	private static function email_otp( $order, $code ) {
		$patient = Patient::find( (int) $order['patient_id'] );
		$email   = $patient['email'] ?? '';
		if ( ! is_email( $email ) ) {
			return false;
		}
		$clinic = ! empty( $order['clinic_id'] ) ? get_the_title( (int) $order['clinic_id'] ) : '';
		$from   = $clinic ?: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: clinic or site name */
		$subject = sprintf( __( 'Your code to see your lab reports at %s', 'doc-booker-pro' ), $from );
		$message = sprintf(
			/* translators: 1: patient name, 2: 6-digit code, 3: minutes the code is valid */
			__( "Hello %1\$s,\n\nYour code to see your lab reports is: %2\$s\n\nIt works for %3\$d minutes. If you did not ask for it, you can ignore this email.", 'doc-booker-pro' ),
			$patient['full_name'] ?? '',
			preg_replace( '/[^0-9]/', '', (string) $code ),
			(int) ( Otp::TTL_SECONDS / MINUTE_IN_SECONDS )
		);
		return wp_mail( $email, $subject, $message );
	}

	public function verify_otp( WP_REST_Request $request ) {
		$body       = $request->get_json_params() ?: $request->get_params();
		$phone      = trim( (string) ( $body['phone'] ?? '' ) );
		$order_code = trim( (string) ( $body['order_code'] ?? '' ) );
		$code       = trim( (string) ( $body['code'] ?? '' ) );

		if ( ! Otp::may_try_code() ) {
			return new WP_Error( 'wpddbp_otp_ip_limit', __( 'Too many attempts from this network. Please try again later.', 'doc-booker-pro' ), [ 'status' => 429 ] );
		}

		// One answer for every failure — unknown order, wrong, expired or
		// used-up code — so a response never confirms that an order exists.
		$order  = TestOrder::find_for_phone_and_code( $phone, $order_code );
		$verify = $order ? Otp::verify( $phone, $order_code, $code ) : null;
		if ( ! $order || is_wp_error( $verify ) ) {
			Otp::record_failed_try();
			return new WP_Error( 'wpddbp_invalid', __( 'That code is not valid. Please check it or request a new one.', 'doc-booker-pro' ), [ 'status' => 401 ] );
		}

		$session = Session::issue( $phone, (int) $order['id'], (int) $order['patient_id'] );
		return new WP_REST_Response( [
			'token'      => $session['token'],
			'expires_in' => $session['expires_in'],
			'order_code' => $order_code,
		], 200 );
	}

	public function verify_bearer( WP_REST_Request $request ) {
		$token = Session::read_bearer( $request );
		$data  = Session::validate( $token );
		return ! empty( $data );
	}

	public function list_reports( WP_REST_Request $request ) {
		$token = Session::read_bearer( $request );
		$data  = Session::validate( $token );
		if ( ! $data ) {
			return new WP_Error( 'wpddbp_unauthorized', __( 'Session expired.', 'doc-booker-pro' ), [ 'status' => 401 ] );
		}
		$order = TestOrder::find( (int) $data['order_id'] );
		if ( ! $order ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Order not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		$patient = Patient::enrich( Patient::find( (int) $order['patient_id'] ) );
		$reports = TestReport::list_for_order( (int) $order['id'] );
		$rows    = [];
		foreach ( $reports as $r ) {
			$r      = TestReport::for_display( $r );
			$rows[] = [
				'id'           => (int) $r['id'],
				'filename'     => $r['filename'],
				'uploaded_at'  => $r['uploaded_at'],
				'expires_at'   => $r['expires_at'],
				'download_url' => Download::signed_url( (int) $r['id'], 'attachment' ),
				'view_url'     => Download::signed_url( (int) $r['id'], 'inline' ),
			];
		}
		return new WP_REST_Response( [
			'order' => [
				'id'         => (int) $order['id'],
				'order_code' => $order['order_code'],
				'items'      => TestOrderItem::list_for_order( (int) $order['id'] ),
				'totals'     => TestOrder::compute_totals( $order ),
			],
			'patient' => [
				'full_name'    => $patient['full_name'] ?? '',
				'patient_code' => $patient['patient_code'] ?? '',
			],
			'reports' => $rows,
		], 200 );
	}

	public function view_report( WP_REST_Request $request ) {
		return $this->stream( $request, 'inline' );
	}

	public function download_report( WP_REST_Request $request ) {
		return $this->stream( $request, 'attachment' );
	}

	private function stream( WP_REST_Request $request, $disposition ) {
		$report_id = (int) $request['id'];
		$exp       = (int) $request->get_param( 'exp' );
		$sig       = (string) $request->get_param( 'sig' );
		$token     = Session::read_bearer( $request );
		$session   = Session::validate( $token );

		if ( ! Download::verify_signature( $report_id, $exp, $sig, $disposition ) ) {
			return new WP_Error( 'wpddbp_bad_sig', __( 'Invalid download link.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}

		$report = TestReport::find( $report_id );
		if ( ! $report ) {
			return new WP_Error( 'wpddbp_not_found', __( 'Report not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}
		// Session must be order-scoped to the same order_id as the report.
		if ( ! $session || (int) $session['order_id'] !== (int) $report['order_id'] ) {
			return new WP_Error( 'wpddbp_forbidden', __( 'Forbidden.', 'doc-booker-pro' ), [ 'status' => 403 ] );
		}
		Download::stream( $report_id, $disposition );
		// Download::stream() exits; this line is unreachable.
		return null;
	}
}
