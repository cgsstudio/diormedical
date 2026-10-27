<?php

namespace WpDreamers\WPDDB\Controllers\Admin\Api;

use WP_REST_Request;
use WP_REST_Response;
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Booking;
use WpDreamers\WPDDB\Controllers\Model\BookingInbox;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Data for the DocBooker app (Dashboard and Bookings).
 *
 * The booking list builds its WHERE clause with RestApi's shared builder, so
 * Pro's filters (clinic-manager scoping, payment filters) apply exactly as on
 * the old Booking Management screen. Cancel, delete, add and export keep using
 * their existing endpoints; this class only reads, plus "mark as seen".
 */
class AppApi {

	use SingletonTrait;

	const NS = 'wpddb_app/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
	}

	public function routes() {
		$can = [ $this, 'can_manage' ];
		register_rest_route( self::NS, '/bookings', [ 'methods' => 'GET', 'callback' => [ $this, 'bookings' ], 'permission_callback' => $can ] );
		register_rest_route( self::NS, '/bookings/(?P<id>\d+)', [ 'methods' => 'GET', 'callback' => [ $this, 'booking' ], 'permission_callback' => $can ] );
		register_rest_route( self::NS, '/bookings/seen', [ 'methods' => 'POST', 'callback' => [ $this, 'seen' ], 'permission_callback' => $can ] );
		register_rest_route( self::NS, '/dashboard', [ 'methods' => 'GET', 'callback' => [ $this, 'dashboard' ], 'permission_callback' => $can ] );
		register_rest_route( self::NS, '/lookups', [ 'methods' => 'GET', 'callback' => [ $this, 'lookups' ], 'permission_callback' => $can ] );
	}

	public function can_manage() {
		return current_user_can( 'wpddb_manage_booking_system' );
	}

	/* ------------------------------------------------------------ bookings */

	const VIEWS = [ 'new', 'upcoming', 'done', 'cancelled', 'all' ];

	/**
	 * The list: one page of bookings plus how many each view holds.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function bookings( WP_REST_Request $request ) {
		global $wpdb;
		$view     = in_array( $request->get_param( 'view' ), self::VIEWS, true ) ? $request->get_param( 'view' ) : 'upcoming';
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 10, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );

		[ $join, $where, $args ] = $this->where_for( $request );
		$tables = $this->tables();
		$from   = "FROM {$tables['b']} AS b LEFT JOIN {$tables['p']} AS p ON b.patient_id = p.id {$join}";

		// Counts for every view in one query, over the same filters.
		// New = not opened yet and still on (a cancelled booking isn't news).
		$seen_col = BookingInbox::ready() ? "IF( b.status IN ('cancelled','cancel',''), 1, b.admin_seen )" : '1';
		$counts   = $wpdb->get_row( $this->prepare(
			"SELECT COUNT(*) AS total,
			        SUM( {$seen_col} = 0 ) AS new_count,
			        SUM( b.status = 'approved' AND b.booking_present_status = 'upcoming' ) AS upcoming,
			        SUM( b.status = 'approved' AND b.booking_present_status = 'expired' ) AS done,
			        SUM( b.status IN ('cancelled','cancel','') ) AS cancelled
			 $from $where",
			$args
		), ARRAY_A );

		$view_sql = [
			'new'       => " AND {$seen_col} = 0",
			'upcoming'  => " AND b.status = 'approved' AND b.booking_present_status = 'upcoming'",
			'done'      => " AND b.status = 'approved' AND b.booking_present_status = 'expired'",
			'cancelled' => " AND b.status IN ('cancelled','cancel','')",
			'all'       => '',
		][ $view ];
		$where_view = $where ? $where . $view_sql : ( $view_sql ? ' WHERE 1=1' . $view_sql : '' );

		// New first, then by appointment: soonest first for what is coming,
		// latest first for what has passed or was cancelled.
		$dir   = in_array( $view, [ 'done', 'cancelled' ], true ) ? 'DESC' : 'ASC';
		$order = "ORDER BY {$seen_col} ASC, b.booking_date IS NULL ASC, b.booking_date {$dir}, b.time {$dir}, b.id DESC";
		$sql   = "SELECT b.*, p.full_name, p.phone, p.email $from $where_view $order LIMIT %d OFFSET %d";
		$rows  = $wpdb->get_results( $this->prepare( $sql, array_merge( $args, [ $per_page, ( $page - 1 ) * $per_page ] ) ), ARRAY_A );
		$total = (int) $wpdb->get_var( $this->prepare( "SELECT COUNT(*) $from $where_view", $args ) );

		return new WP_REST_Response( [
			'rows'   => array_map( [ $this, 'format_row' ], $this->with_titles( $rows ?: [] ) ),
			'total'  => $total,
			'page'   => $page,
			'pages'  => (int) ceil( $total / $per_page ),
			'counts' => [
				'new'       => (int) ( $counts['new_count'] ?? 0 ),
				'upcoming'  => (int) ( $counts['upcoming'] ?? 0 ),
				'done'      => (int) ( $counts['done'] ?? 0 ),
				'cancelled' => (int) ( $counts['cancelled'] ?? 0 ),
				'all'       => (int) ( $counts['total'] ?? 0 ),
			],
			'summary' => $this->list_summary( $from, $where, $args ),
			'unseen'  => BookingInbox::unseen_count(),
		], 200 );
	}

	/**
	 * The figures above the list, for the same filters.
	 */
	private function list_summary( $from, $where, $args ) {
		global $wpdb;
		$today    = current_time( 'Y-m-d' );
		$tomorrow = wp_date( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		$and      = $where ? $where . ' AND ' : ' WHERE ';
		$upcoming = "b.status = 'approved' AND b.booking_present_status = 'upcoming'";

		$row = $wpdb->get_row( $this->prepare(
			"SELECT SUM( b.booking_date = %s ) AS today, SUM( b.booking_date = %s ) AS tomorrow {$from} {$and} {$upcoming}",
			array_merge( [ $today, $tomorrow ], $args )
		), ARRAY_A );

		// Fees to collect: upcoming bookings paid at the clinic, at each doctor's fee.
		$by_doctor = $wpdb->get_results( $this->prepare(
			"SELECT b.doctor_id, COUNT(*) AS n {$from} {$and} {$upcoming} AND ( b.payment_by IS NULL OR b.payment_by IN ('', 'clinic') ) GROUP BY b.doctor_id",
			$args
		), ARRAY_A );
		$collect = 0.0;
		$collect_n = 0;
		foreach ( (array) $by_doctor as $d ) {
			$collect   += Booking::doctor_fee( (int) $d['doctor_id'] ) * (int) $d['n'];
			$collect_n += (int) $d['n'];
		}

		$paid = $wpdb->get_row( $this->prepare(
			"SELECT COALESCE( SUM( b.amount_paid ), 0 ) AS amount, COUNT(*) AS n, SUM( b.payment_status = 'failed' ) AS failed {$from} {$and} b.payment_by IN ('stripe','razorpay') AND b.payment_status IN ('succeeded','failed')",
			$args
		), ARRAY_A );

		return [
			'today'         => (int) ( $row['today'] ?? 0 ),
			'tomorrow'      => (int) ( $row['tomorrow'] ?? 0 ),
			'collect'       => Booking::money( $collect ),
			'collect_count' => $collect_n,
			'paid'          => Booking::money( (float) ( $paid['amount'] ?? 0 ) ),
			'paid_failed'   => (int) ( $paid['failed'] ?? 0 ),
		];
	}

	/**
	 * One booking with its history. Opening it marks it as seen.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function booking( WP_REST_Request $request ) {
		global $wpdb;
		$id     = absint( $request['id'] );
		$tables = $this->tables();

		// The same scoping as the list: Pro's clinic managers see their clinic only.
		[ $join, $where, $args ] = $this->where_for( new WP_REST_Request( 'GET' ) );
		$and = $where ? $where . ' AND' : ' WHERE';
		$row = $wpdb->get_row( $this->prepare(
			"SELECT b.*, p.full_name, p.phone, p.email FROM {$tables['b']} AS b LEFT JOIN {$tables['p']} AS p ON b.patient_id = p.id {$join} {$and} b.id = %d",
			array_merge( $args, [ $id ] )
		), ARRAY_A );
		if ( ! $row ) {
			return new WP_REST_Response( [ 'message' => __( 'Booking not found.', 'doc-booker' ) ], 404 );
		}

		$was_new = BookingInbox::ready() && 0 === (int) $row['admin_seen'] && ! in_array( $row['status'], [ 'cancelled', 'cancel', '' ], true );
		if ( $was_new ) {
			BookingInbox::mark_seen( [ $id ] );
		}

		$item            = $this->format_row( $this->with_titles( [ $row ] )[0] );
		$item['was_new'] = $was_new;
		$item['departments'] = wp_list_pluck( get_the_terms( (int) $row['doctor_id'], wpddb()->doctor_category ) ?: [], 'name' );
		$item['history']     = $this->history( $row );
		$item['unseen']      = BookingInbox::unseen_count();

		return new WP_REST_Response( $item, 200 );
	}

	/**
	 * Mark bookings as seen: { ids: [..] } or { all: true }.
	 */
	public function seen( WP_REST_Request $request ) {
		$changed = $request->get_param( 'all' ) ? BookingInbox::mark_seen( null ) : BookingInbox::mark_seen( (array) $request->get_param( 'ids' ) );

		return new WP_REST_Response( [ 'changed' => $changed, 'unseen' => BookingInbox::unseen_count() ], 200 );
	}

	/**
	 * What happened to a booking, oldest first, from what the table records.
	 */
	private function history( array $row ) {
		$at   = static function ( $mysql ) {
			$ts = $mysql ? strtotime( $mysql ) : 0;
			return $ts ? Helper::site_date_label( gmdate( 'Y-m-d', $ts ), false ) . ' · ' . wp_date( get_option( 'time_format' ) ?: 'g:i a', $ts, new \DateTimeZone( 'UTC' ) ) : '';
		};
		$via  = $row['booked_via'] ?? '';
		$out  = [];
		$out[] = [
			'type'  => 'booked',
			'title' => __( 'Booked', 'doc-booker' ),
			'meta'  => trim( $at( $row['created_at'] ) . ' · ' . ( 'admin' === $via ? __( 'added by staff', 'doc-booker' ) : ( 'public' === $via ? __( 'by the patient online', 'doc-booker' ) : '' ) ), ' ·' ),
		];
		if ( 'public' === $via && ! empty( $row['email'] ) ) {
			$out[] = [
				'type'  => 'email',
				'title' => __( 'Confirmation email sent', 'doc-booker' ),
				/* translators: %s: email address */
				'meta'  => sprintf( __( 'to %s', 'doc-booker' ), $row['email'] ),
			];
		}
		$gateway = ucfirst( (string) $row['payment_by'] );
		if ( in_array( $row['payment_by'], [ 'stripe', 'razorpay' ], true ) ) {
			if ( 'succeeded' === $row['payment_status'] || 'refunded' === $row['payment_status'] ) {
				$out[] = [
					'type'  => 'paid',
					/* translators: %s: amount */
					'title' => sprintf( __( 'Paid online · %s', 'doc-booker' ), Booking::money( (float) $row['amount_paid'] ) ),
					'meta'  => trim( $gateway . ( wpddb()->has_pro() && $row['transaction_id'] ? ' · ' . $row['transaction_id'] : '' ) ),
				];
			} elseif ( 'failed' === $row['payment_status'] ) {
				$out[] = [ 'type' => 'failed', 'title' => __( 'Online payment failed', 'doc-booker' ), 'meta' => $gateway ];
			}
		}
		if ( ! empty( $row['reminder_sent'] ) ) {
			$out[] = [ 'type' => 'reminder', 'title' => __( 'Reminder email sent', 'doc-booker' ), 'meta' => __( 'About a day before', 'doc-booker' ) ];
		}
		if ( in_array( $row['status'], [ 'cancelled', 'cancel', '' ], true ) ) {
			$out[] = [ 'type' => 'cancelled', 'title' => __( 'Cancelled', 'doc-booker' ), 'meta' => $at( $row['updated_at'] ) ];
			if ( 'refunded' === $row['payment_status'] ) {
				$out[] = [ 'type' => 'refund', 'title' => __( 'Refunded', 'doc-booker' ), 'meta' => Booking::money( (float) $row['amount_paid'] ) ];
			}
		} elseif ( 'expired' === $row['booking_present_status'] ) {
			$out[] = [ 'type' => 'done', 'title' => __( 'Appointment time passed', 'doc-booker' ), 'meta' => '' ];
		}

		return $out;
	}

	/**
	 * Patient, doctor, clinic, payment and status as the app shows them.
	 */
	private function format_row( array $r ) {
		$status = in_array( $r['status'], [ 'cancelled', 'cancel', '' ], true ) ? 'cancelled' : ( 'expired' === $r['booking_present_status'] ? 'done' : 'upcoming' );
		$online = in_array( $r['payment_by'], [ 'stripe', 'razorpay' ], true );
		if ( $online ) {
			$pay = in_array( $r['payment_status'], [ 'succeeded', 'refunded', 'failed' ], true ) ? $r['payment_status'] : 'pending';
		} else {
			$pay = 'cancelled' === $status ? 'nocharge' : 'clinic';
		}
		$fee     = $online && (float) $r['amount_paid'] > 0 ? (float) $r['amount_paid'] : Booking::doctor_fee( (int) $r['doctor_id'] );
		$created = ! empty( $r['created_at'] ) ? strtotime( $r['created_at'] ) : 0;
		$now     = current_time( 'timestamp' );

		return [
			'id'           => (int) $r['id'],
			'booking_id'   => (string) $r['booking_id'],
			'patient'      => [ 'name' => (string) $r['full_name'], 'phone' => (string) $r['phone'], 'email' => (string) $r['email'] ],
			'doctor'       => [ 'id' => (int) $r['doctor_id'], 'name' => (string) ( $r['doctor_name'] ?? '' ) ],
			'clinic'       => [ 'id' => (int) $r['clinic_id'], 'name' => (string) ( $r['clinic_name'] ?? '' ) ],
			'date'         => (string) $r['booking_date'],
			'date_label'   => ! empty( $r['booking_date'] ) ? Helper::site_date_label( $r['booking_date'] ) : Helper::weekday_label( $r['day'] ),
			'time_label'   => ! empty( $r['time'] ) ? Helper::site_time_label( $r['time'] ) : '',
			'time24'       => ! empty( $r['time'] ) ? Helper::convert_time_format( (string) $r['time'] ) : '',
			'status'       => $status,
			'payment'      => [ 'kind' => $online ? 'online' : 'clinic', 'state' => $pay, 'gateway' => $online ? (string) $r['payment_by'] : '', 'transaction' => wpddb()->has_pro() ? (string) $r['transaction_id'] : '' ],
			'fee'          => Booking::money( $fee ),
			'note'         => (string) $r['patient_note'],
			'is_new'       => BookingInbox::ready() && 0 === (int) $r['admin_seen'] && ! in_array( $r['status'] ?? '', [ 'cancelled', 'cancel', '' ], true ),
			'via'          => (string) ( $r['booked_via'] ?? '' ),
			/* translators: %s: time since booking, e.g. "12 mins" */
			'booked_ago'   => $created ? sprintf( __( '%s ago', 'doc-booker' ), human_time_diff( $created, max( $created, $now ) ) ) : '',
			'booked_label' => $created ? Helper::site_date_label( gmdate( 'Y-m-d', $created ), false ) : '',
			'can_cancel'   => 'upcoming' === $status,
		];
	}

	/* ------------------------------------------------------------ dashboard */

	/**
	 * The dashboard figures for a period, optionally for some clinics/doctors.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function dashboard( WP_REST_Request $request ) {
		global $wpdb;
		$today = current_time( 'Y-m-d' );
		[ $from, $to ] = $this->period( (string) $request->get_param( 'period' ), (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ), $today );
		$days    = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
		$p_to    = wp_date( 'Y-m-d', strtotime( $from . ' -1 day' ) );
		$p_from  = wp_date( 'Y-m-d', strtotime( $p_to . ' -' . ( $days - 1 ) . ' days' ) );
		// Whole months compare with the whole months before (September, not Aug 31 – Sep 30).
		$period_key = (string) $request->get_param( 'period' );
		if ( in_array( $period_key, [ 'month', '3m' ], true ) || '' === $period_key ) {
			$months = '3m' === $period_key ? 3 : 1;
			$p_from = wp_date( 'Y-m-01', strtotime( substr( $from, 0, 7 ) . "-01 -{$months} months" ) );
			// $p_to, the day before $from, is already the last day of the previous month.
		}

		$clinics = array_values( array_filter( array_map( 'absint', (array) $request->get_param( 'clinic' ) ) ) );
		$doctors = array_values( array_filter( array_map( 'absint', (array) $request->get_param( 'doctor' ) ) ) );
		[ $scope, $scope_args ] = $this->scope( $clinics, $doctors );
		$t = $this->tables()['b'];

		$count = function ( $cond, $cond_args, $a, $b ) use ( $wpdb, $t, $scope, $scope_args ) {
			return (int) $wpdb->get_var( $this->prepare( "SELECT COUNT(*) FROM {$t} AS b WHERE b.booking_date BETWEEN %s AND %s AND {$cond} {$scope}", array_merge( [ $a, $b ], $cond_args, $scope_args ) ) );
		};
		$approved  = "b.status = 'approved'";
		$cancelled = "b.status IN ('cancelled','cancel','')";
		$week_end  = wp_date( 'Y-m-d', strtotime( $today . ' +6 days' ) );
		$wk_p_from = wp_date( 'Y-m-d', strtotime( $today . ' -7 days' ) );
		$wk_p_to   = wp_date( 'Y-m-d', strtotime( $today . ' -1 day' ) );

		$booked      = $count( $approved, [], $from, $to );
		$booked_prev = $count( $approved, [], $p_from, $p_to );
		$canc        = $count( $cancelled, [], $from, $to );
		$canc_prev   = $count( $cancelled, [], $p_from, $p_to );
		$week        = $count( $approved, [], $today, $week_end );
		$week_prev   = $count( $approved, [], $wk_p_from, $wk_p_to );

		// Today's list and the next appointment.
		$today_rows = $wpdb->get_results( $this->prepare(
			"SELECT b.*, p.full_name, p.phone, p.email FROM {$t} AS b LEFT JOIN {$this->tables()['p']} AS p ON b.patient_id = p.id WHERE b.booking_date = %s {$scope} ORDER BY b.time ASC LIMIT 20",
			array_merge( [ $today ], $scope_args )
		), ARRAY_A );
		$today_rows = array_map( [ $this, 'format_row' ], $this->with_titles( $today_rows ?: [] ) );
		$now_24     = current_time( 'H:i' );
		$next       = null;
		foreach ( $today_rows as $r ) {
			if ( 'upcoming' === $r['status'] && $r['time24'] >= $now_24 ) {
				$next = $r;
				break;
			}
		}

		// Bookings per day (or per week over a long period): booked and cancelled.
		$series_from = $days < 7 ? wp_date( 'Y-m-d', strtotime( $today . ' -13 days' ) ) : $from;
		$series_to   = $days < 7 ? $today : $to;
		$series_days = (int) round( ( strtotime( $series_to ) - strtotime( $series_from ) ) / DAY_IN_SECONDS ) + 1;
		$weekly      = $series_days > 31;
		$rows        = $wpdb->get_results( $this->prepare(
			"SELECT b.booking_date AS d, SUM( {$approved} ) AS booked, SUM( {$cancelled} ) AS cancelled FROM {$t} AS b WHERE b.booking_date BETWEEN %s AND %s {$scope} GROUP BY b.booking_date",
			array_merge( [ $series_from, $series_to ], $scope_args )
		), ARRAY_A );
		$by_day = [];
		foreach ( (array) $rows as $r ) {
			$by_day[ $r['d'] ] = [ (int) $r['booked'], (int) $r['cancelled'] ];
		}
		$series = [];
		for ( $ts = strtotime( $series_from ); $ts <= strtotime( $series_to ); $ts += DAY_IN_SECONDS ) {
			$d   = gmdate( 'Y-m-d', $ts );
			$key = $weekly ? gmdate( 'o-\WW', $ts ) : $d;
			if ( ! isset( $series[ $key ] ) ) {
				$series[ $key ] = [ 'label' => wp_date( 'M j', $ts + 12 * HOUR_IN_SECONDS, new \DateTimeZone( 'UTC' ) ), 'booked' => 0, 'cancelled' => 0 ];
			}
			$series[ $key ]['booked']    += $by_day[ $d ][0] ?? 0;
			$series[ $key ]['cancelled'] += $by_day[ $d ][1] ?? 0;
		}

		// Busiest doctors and departments in the period.
		$doc_rows = $wpdb->get_results( $this->prepare(
			"SELECT b.doctor_id AS id, COUNT(*) AS n FROM {$t} AS b WHERE b.booking_date BETWEEN %s AND %s AND {$approved} {$scope} GROUP BY b.doctor_id ORDER BY n DESC LIMIT 5",
			array_merge( [ $from, $to ], $scope_args )
		), ARRAY_A );
		$doc_rows = $this->with_titles( array_map( static function ( $r ) {
			return [ 'doctor_id' => $r['id'], 'clinic_id' => 0, 'n' => (int) $r['n'] ];
		}, $doc_rows ?: [] ) );

		$tr      = $wpdb->term_relationships;
		$tt      = $wpdb->term_taxonomy;
		$dep_rows = $wpdb->get_results( $this->prepare(
			"SELECT tt.term_id AS id, COUNT(*) AS n FROM {$t} AS b INNER JOIN {$tr} AS tr ON tr.object_id = b.doctor_id INNER JOIN {$tt} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s WHERE b.booking_date BETWEEN %s AND %s AND {$approved} {$scope} GROUP BY tt.term_id ORDER BY n DESC",
			array_merge( [ wpddb()->doctor_category, $from, $to ], $scope_args )
		), ARRAY_A );
		$departments = [];
		foreach ( (array) $dep_rows as $r ) {
			$term          = get_term( (int) $r['id'] );
			$departments[] = [ 'name' => $term && ! is_wp_error( $term ) ? $term->name : '', 'count' => (int) $r['n'] ];
		}

		// Money: fees still to collect at the clinic, and (Pro) paid online.
		$collect_rows = $wpdb->get_results( $this->prepare(
			"SELECT b.doctor_id, COUNT(*) AS n FROM {$t} AS b WHERE b.booking_date BETWEEN %s AND %s AND {$approved} AND b.booking_present_status = 'upcoming' AND ( b.payment_by IS NULL OR b.payment_by IN ('', 'clinic') ) {$scope} GROUP BY b.doctor_id",
			array_merge( [ $from, $to ], $scope_args )
		), ARRAY_A );
		$collect   = 0.0;
		$collect_n = 0;
		foreach ( (array) $collect_rows as $r ) {
			$collect   += Booking::doctor_fee( (int) $r['doctor_id'] ) * (int) $r['n'];
			$collect_n += (int) $r['n'];
		}
		$paid = $wpdb->get_row( $this->prepare(
			"SELECT COALESCE( SUM( b.amount_paid ), 0 ) AS amount, COUNT(*) AS n FROM {$t} AS b WHERE b.booking_date BETWEEN %s AND %s AND b.payment_status = 'succeeded' {$scope}",
			array_merge( [ $from, $to ], $scope_args )
		), ARRAY_A );

		return new WP_REST_Response( [
			'range'   => [
				'from'       => $from,
				'to'         => $to,
				'label'      => $this->range_label( $from, $to ),
				'prev_label' => $this->range_label( $p_from, $p_to ),
			],
			'kpis'    => [
				'today'     => [ 'value' => count( array_filter( $today_rows, static function ( $r ) { return 'cancelled' !== $r['status']; } ) ), 'next' => $next ? [ 'time' => $next['time_label'], 'doctor' => $next['doctor']['name'] ] : null ],
				'week'      => [ 'value' => $week, 'delta' => $this->delta( $week, $week_prev ) ],
				'booked'    => [ 'value' => $booked, 'delta' => $this->delta( $booked, $booked_prev ) ],
				'cancelled' => [ 'value' => $canc, 'delta' => $this->delta( $canc, $canc_prev ), 'share' => $booked + $canc ? round( $canc / ( $booked + $canc ) * 100, 1 ) : 0 ],
			],
			'series'      => array_values( $series ),
			'series_note' => $days < 7 ? 'last14' : ( $weekly ? 'weekly' : 'daily' ),
			'today'       => [ 'label' => Helper::site_date_label( $today ), 'rows' => $today_rows ],
			'doctors'     => array_map( static function ( $r ) {
				return [ 'name' => $r['doctor_name'], 'count' => $r['n'] ];
			}, $doc_rows ),
			'departments' => $departments,
			'money'       => [
				'collect'       => Booking::money( $collect ),
				'collect_count' => $collect_n,
				'paid'          => Booking::money( (float) ( $paid['amount'] ?? 0 ) ),
				'paid_count'    => (int) ( $paid['n'] ?? 0 ),
			],
			'no_email'    => $this->doctors_without_email(),
			'unseen'      => BookingInbox::unseen_count(),
		], 200 );
	}

	/**
	 * Clinics, doctors and departments for the filters.
	 */
	public function lookups() {
		$posts = static function ( $type ) {
			return array_map( static function ( $p ) {
				return [ 'id' => (int) $p->ID, 'name' => $p->post_title ];
			}, get_posts( [ 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC' ] ) );
		};
		$terms = get_terms( [ 'taxonomy' => wpddb()->doctor_category, 'hide_empty' => false ] );

		return new WP_REST_Response( [
			'clinics'     => $posts( wpddb()->post_type_clinic ),
			'doctors'     => $posts( wpddb()->post_type_doctor ),
			'departments' => is_wp_error( $terms ) ? [] : array_map( static function ( $t ) {
				return [ 'id' => (int) $t->term_id, 'name' => $t->name ];
			}, $terms ),
		], 200 );
	}

	/* ------------------------------------------------------------ helpers */

	private function tables() {
		global $wpdb;
		return [ 'b' => $wpdb->prefix . 'wpddb_bookings', 'p' => $wpdb->prefix . 'wpddb_patients' ];
	}

	/**
	 * JOIN, WHERE and values for the list, from the request, through RestApi's
	 * shared builder and the same filters Pro hooks into.
	 *
	 * @return array [ join, where (with leading " WHERE" or ''), args ]
	 */
	private function where_for( WP_REST_Request $request ) {
		$args = [
			'search'                 => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'date_from'              => $this->ymd( $request->get_param( 'from' ) ),
			'date_to'                => $this->ymd( $request->get_param( 'to' ) ),
			'doctor_id'              => absint( $request->get_param( 'doctor_id' ) ),
			'clinic_id'              => absint( $request->get_param( 'clinic_id' ) ),
			'department_id'          => 0,
			'status'                 => '',
			'booking_present_status' => '',
			'payment_status'         => '',
			'payment_by'             => '',
			'amount_min'             => null,
			'amount_max'             => null,
			'ids'                    => [],
			'order_by'               => 'created_at',
			'order'                  => 'DESC',
		];
		$args = apply_filters( 'wpddb_bookings_query_args', $args );
		[ $join, $where, $where_args ] = RestApi::build_booking_where_clauses( $args );

		$payment = (string) $request->get_param( 'payment' );
		if ( 'clinic' === $payment ) {
			$where .= ( $where ? ' AND' : ' WHERE' ) . " ( b.payment_by IS NULL OR b.payment_by IN ('', 'clinic') )";
		} elseif ( 'online' === $payment ) {
			$where .= ( $where ? ' AND' : ' WHERE' ) . " b.payment_by IN ('stripe', 'razorpay')";
		}

		return [ $join, $where, $where_args ];
	}

	/**
	 * Clinic/doctor limits for the dashboard, plus Pro's clinic-manager scoping.
	 *
	 * @return array [ " AND …" sql, args ]
	 */
	private function scope( array $clinics, array $doctors ) {
		$sql  = '';
		$args = [];
		if ( $clinics ) {
			$sql .= ' AND b.clinic_id IN (' . implode( ',', array_fill( 0, count( $clinics ), '%d' ) ) . ')';
			$args = array_merge( $args, $clinics );
		}
		if ( $doctors ) {
			$sql .= ' AND b.doctor_id IN (' . implode( ',', array_fill( 0, count( $doctors ), '%d' ) ) . ')';
			$args = array_merge( $args, $doctors );
		}
		// A clinic manager (Pro) only ever sees their own clinic.
		$scoped = apply_filters( 'wpddb_bookings_query_args', [ 'clinic_id' => 0 ] + $this->empty_args() );
		if ( ! empty( $scoped['clinic_id'] ) ) {
			$sql   .= ' AND b.clinic_id = %d';
			$args[] = (int) $scoped['clinic_id'];
		}

		return [ $sql, $args ];
	}

	private function empty_args() {
		return [ 'search' => '', 'date_from' => '', 'date_to' => '', 'doctor_id' => 0, 'department_id' => 0, 'status' => '', 'booking_present_status' => '', 'payment_status' => '', 'payment_by' => '', 'amount_min' => null, 'amount_max' => null, 'ids' => [], 'order_by' => 'created_at', 'order' => 'DESC' ];
	}

	/**
	 * Doctor and clinic names for the rows, in one query.
	 */
	private function with_titles( array $rows ) {
		global $wpdb;
		$ids = [];
		foreach ( $rows as $r ) {
			$ids[] = (int) ( $r['doctor_id'] ?? 0 );
			$ids[] = (int) ( $r['clinic_id'] ?? 0 );
		}
		$ids    = array_values( array_unique( array_filter( $ids ) ) );
		$titles = [];
		if ( $ids ) {
			$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($in)", ...$ids ), ARRAY_A ) as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$titles[ (int) $p['ID'] ] = $p['post_title'];
			}
		}
		foreach ( $rows as &$r ) {
			$r['doctor_name'] = $titles[ (int) ( $r['doctor_id'] ?? 0 ) ] ?? '';
			$r['clinic_name'] = $titles[ (int) ( $r['clinic_id'] ?? 0 ) ] ?? '';
		}

		return $rows;
	}

	private function prepare( $sql, array $args ) {
		global $wpdb;
		return $args ? $wpdb->prepare( $sql, ...$args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function ymd( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * [from, to] for a period key, in site time.
	 */
	private function period( $key, $from, $to, $today ) {
		$from = $this->ymd( $from );
		$to   = $this->ymd( $to );
		switch ( $key ) {
			case 'today':
				return [ $today, $today ];
			case '7d':
				return [ wp_date( 'Y-m-d', strtotime( $today . ' -6 days' ) ), $today ];
			case '3m':
				return [ wp_date( 'Y-m-01', strtotime( substr( $today, 0, 7 ) . '-01 -2 months' ) ), wp_date( 'Y-m-t', strtotime( $today ) ) ];
			case 'custom':
				if ( $from && $to && $from <= $to ) {
					return [ $from, $to ];
				}
				// Fall through to this month.
			default:
				return [ substr( $today, 0, 7 ) . '-01', wp_date( 'Y-m-t', strtotime( $today ) ) ];
		}
	}

	private function range_label( $from, $to ) {
		return $from === $to ? Helper::site_date_label( $from ) : Helper::site_date_label( $from, false ) . ' – ' . Helper::site_date_label( $to, false );
	}

	/**
	 * Change against the previous period, in percent; null when there was nothing before.
	 */
	private function delta( $now, $before ) {
		return $before > 0 ? round( ( $now - $before ) / $before * 100, 1 ) : null;
	}

	private function doctors_without_email() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'wpddb_doctor_email' WHERE p.post_type = %s AND p.post_status = 'publish' AND ( m.meta_value IS NULL OR m.meta_value = '' )",
			wpddb()->post_type_doctor
		) );
	}
}
