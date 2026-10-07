<?php

namespace WpDreamers\WPDDBP\Controllers\TestOrder\Model;

defined( 'ABSPATH' ) || exit;

class TestOrder {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_test_orders';
	}

	public static function find( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function find_by_code( $code ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE order_code=%s", $code ), ARRAY_A );
	}

	public static function find_for_phone_and_code( $phone, $code ) {
		global $wpdb;
		$t = self::table();
		$p = $wpdb->prefix . 'wpddb_patients';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.* FROM `{$t}` o INNER JOIN `{$p}` pa ON pa.id = o.patient_id
				 WHERE o.order_code = %s AND pa.phone = %s LIMIT 1",
				$code,
				$phone
			),
			ARRAY_A
		);
	}

	public static function list( $args = [] ) {
		global $wpdb;
		$t        = self::table();
		$clinic   = isset( $args['clinic_id'] ) ? (int) $args['clinic_id'] : null;
		$patient  = isset( $args['patient_id'] ) ? (int) $args['patient_id'] : null;
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [ '1=1' ];
		$params = [];
		if ( $clinic !== null ) {
			$where[]  = 'clinic_id = %d';
			$params[] = $clinic;
		}
		if ( $patient !== null ) {
			$where[]  = 'patient_id = %d';
			$params[] = $patient;
		}
		if ( $search !== '' ) {
			$where[]  = 'order_code LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$t}` {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				array_merge( $params, [ $per_page, $offset ] )
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var(
			$params
				? $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` {$where_sql}", $params )
				: "SELECT COUNT(*) FROM `{$t}` {$where_sql}"
		);

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	public static function generate_order_code() {
		// B + YYMMDD + 6 digits, retry on collision.
		global $wpdb;
		$t = self::table();
		for ( $i = 0; $i < 5; $i++ ) {
			$code = 'B' . gmdate( 'ymd' ) . str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE order_code=%s", $code ) );
			if ( ! $exists ) {
				return $code;
			}
		}
		return 'B' . time();
	}

	public static function create( $data ) {
		global $wpdb;
		$row = [
			'order_code'      => $data['order_code'] ?? self::generate_order_code(),
			'patient_id'      => (int) $data['patient_id'],
			'clinic_id'       => (int) $data['clinic_id'],
			'receptionist_id' => (int) ( $data['receptionist_id'] ?? get_current_user_id() ),
			'refd_by'         => isset( $data['refd_by'] ) ? sanitize_text_field( $data['refd_by'] ) : null,
			'subtotal'        => (float) ( $data['subtotal'] ?? 0 ),
			'discount_amount' => (float) ( $data['discount_amount'] ?? 0 ),
			'extra_discount'  => (float) ( $data['extra_discount'] ?? 0 ),
			'received'        => (float) ( $data['received'] ?? 0 ),
			'payment_status'  => $data['payment_status'] ?? 'unpaid',
			'delivery_at'     => $data['delivery_at'] ?? null,
			'notes'           => isset( $data['notes'] ) ? sanitize_text_field( $data['notes'] ) : null,
			'status'          => $data['status'] ?? 'active',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$update = [];
		foreach ( [ 'subtotal', 'discount_amount', 'extra_discount', 'received' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = (float) $data[ $k ];
			}
		}
		foreach ( [ 'payment_status', 'status', 'notes', 'refd_by' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		if ( array_key_exists( 'delivery_at', $data ) ) {
			$update['delivery_at'] = $data['delivery_at'] ?: null;
		}
		if ( empty( $update ) ) {
			return false;
		}
		$update['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( self::table(), $update, [ 'id' => (int) $id ] );
	}

	/**
	 * Computed totals: payable = subtotal - discount_amount - extra_discount;
	 * due = payable - received.
	 */
	public static function compute_totals( array $row ) {
		$subtotal = (float) ( $row['subtotal'] ?? 0 );
		$disc     = (float) ( $row['discount_amount'] ?? 0 );
		$extra    = (float) ( $row['extra_discount'] ?? 0 );
		$received = (float) ( $row['received'] ?? 0 );
		$payable  = max( 0, $subtotal - $disc - $extra );
		$due      = max( 0, $payable - $received );
		return [
			'payable' => $payable,
			'due'     => $due,
		];
	}
}
