<?php

namespace WpDreamers\WPDDBP\Controllers\Visits\Model;

defined( 'ABSPATH' ) || exit;

class VisitPayment {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_visit_payments';
	}

	public static function list_for_visit( $visit_id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$t}` WHERE visit_id=%d ORDER BY received_at DESC, id DESC", (int) $visit_id ),
			ARRAY_A
		) ?: [];
	}

	public static function create( $data ) {
		global $wpdb;
		$row = [
			'visit_id'    => (int) $data['visit_id'],
			'amount'      => (float) $data['amount'],
			'method'      => sanitize_text_field( $data['method'] ?? 'cash' ),
			'reference'   => isset( $data['reference'] ) ? sanitize_text_field( $data['reference'] ) : null,
			'received_by' => (int) ( $data['received_by'] ?? get_current_user_id() ),
			'received_at' => current_time( 'mysql' ),
			'notes'       => isset( $data['notes'] ) ? sanitize_text_field( $data['notes'] ) : null,
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'id' => (int) $id ] );
	}
}
