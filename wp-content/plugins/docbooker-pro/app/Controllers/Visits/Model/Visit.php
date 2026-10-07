<?php

namespace WpDreamers\WPDDBP\Controllers\Visits\Model;

defined( 'ABSPATH' ) || exit;

class Visit {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_patient_visits';
	}

	public static function find( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function list( $args = [] ) {
		global $wpdb;
		$t        = self::table();
		$clinic   = isset( $args['clinic_id'] ) ? (int) $args['clinic_id'] : null;
		$patient  = isset( $args['patient_id'] ) ? (int) $args['patient_id'] : null;
		$type     = isset( $args['visit_type'] ) ? (string) $args['visit_type'] : null;
		$status   = isset( $args['status'] ) ? (string) $args['status'] : null;
		$due_only = ! empty( $args['due_only'] );
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
		if ( $type !== null ) {
			$where[]  = 'visit_type = %s';
			$params[] = $type;
		}
		if ( $status !== null ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( $due_only ) {
			$where[] = 'total_charge > received';
		}
		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$t}` {$where_sql} ORDER BY visit_date DESC, id DESC LIMIT %d OFFSET %d",
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

	public static function create( $data ) {
		global $wpdb;
		$type      = in_array( $data['visit_type'] ?? 'opd', [ 'opd', 'ipd' ], true ) ? $data['visit_type'] : 'opd';
		$row = [
			'patient_id'        => (int) $data['patient_id'],
			'clinic_id'         => (int) $data['clinic_id'],
			'visit_type'        => $type,
			'visit_date'        => $data['visit_date'] ?? gmdate( 'Y-m-d' ),
			'consult_doctor_id' => isset( $data['consult_doctor_id'] ) ? (int) $data['consult_doctor_id'] : null,
			'admit_doctor_id'   => isset( $data['admit_doctor_id'] ) ? (int) $data['admit_doctor_id'] : null,
			'admitted_at'       => $data['admitted_at'] ?? null,
			'released_at'       => $data['released_at'] ?? null,
			'bed_no'            => isset( $data['bed_no'] ) ? sanitize_text_field( $data['bed_no'] ) : null,
			'total_charge'      => (float) ( $data['total_charge'] ?? 0 ),
			'received'          => (float) ( $data['received'] ?? 0 ),
			'doctor_advice'     => isset( $data['doctor_advice'] ) ? wp_kses_post( $data['doctor_advice'] ) : null,
			'notes'             => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			'status'            => $data['status'] ?? 'active',
			'created_by'        => (int) ( $data['created_by'] ?? get_current_user_id() ),
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$update = [];
		foreach ( [ 'bed_no', 'status', 'notes' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		foreach ( [ 'consult_doctor_id', 'admit_doctor_id' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = (int) $data[ $k ];
			}
		}
		foreach ( [ 'admitted_at', 'released_at', 'visit_date', 'doctor_advice' ] as $k ) {
			if ( array_key_exists( $k, $data ) ) {
				$update[ $k ] = $data[ $k ] ?: null;
			}
		}
		foreach ( [ 'total_charge', 'received' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = (float) $data[ $k ];
			}
		}
		if ( empty( $update ) ) {
			return false;
		}
		$update['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( self::table(), $update, [ 'id' => (int) $id ] );
	}

	/**
	 * Recompute and persist total_charge from related visit_items + received
	 * from visit_payments. Returns the updated visit row.
	 */
	public static function recompute_totals( $id ) {
		global $wpdb;
		$items_t    = $wpdb->prefix . 'wpddb_visit_items';
		$payments_t = $wpdb->prefix . 'wpddb_visit_payments';
		$total      = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(qty * unit_price * (1 - disc_percent/100)), 0)
				 FROM `{$items_t}` WHERE visit_id=%d",
				(int) $id
			)
		);
		$received   = (float) $wpdb->get_var(
			$wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM `{$payments_t}` WHERE visit_id=%d", (int) $id )
		);
		$wpdb->update(
			self::table(),
			[
				'total_charge' => round( $total, 2 ),
				'received'     => round( $received, 2 ),
				'updated_at'   => current_time( 'mysql' ),
			],
			[ 'id' => (int) $id ]
		);
		return self::find( $id );
	}
}
