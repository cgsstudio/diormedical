<?php

namespace WpDreamers\WPDDBP\Controllers\Visits\Model;

defined( 'ABSPATH' ) || exit;

class VisitItem {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_visit_items';
	}

	public static function list_for_visit( $visit_id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$t}` WHERE visit_id=%d ORDER BY id ASC", (int) $visit_id ),
			ARRAY_A
		) ?: [];
	}

	public static function find( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function create( $data ) {
		global $wpdb;
		$allowed_types = [ 'consultation', 'bed', 'medicine', 'procedure', 'test', 'service', 'other' ];
		$type          = in_array( $data['item_type'] ?? 'service', $allowed_types, true ) ? $data['item_type'] : 'service';

		$row = [
			'visit_id'     => (int) $data['visit_id'],
			'item_type'    => $type,
			'name'         => sanitize_text_field( $data['name'] ),
			'qty'          => max( 1, (int) ( $data['qty'] ?? 1 ) ),
			'unit_price'   => (float) ( $data['unit_price'] ?? 0 ),
			'disc_percent' => (float) ( $data['disc_percent'] ?? 0 ),
			'item_date'    => $data['item_date'] ?? null,
			'notes'        => isset( $data['notes'] ) ? sanitize_text_field( $data['notes'] ) : null,
			'created_by'   => (int) ( $data['created_by'] ?? get_current_user_id() ),
			'created_at'   => current_time( 'mysql' ),
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$update = [];
		if ( isset( $data['name'] ) )       $update['name']       = sanitize_text_field( $data['name'] );
		if ( isset( $data['item_type'] ) )  $update['item_type']  = sanitize_text_field( $data['item_type'] );
		if ( isset( $data['notes'] ) )      $update['notes']      = sanitize_text_field( $data['notes'] );
		if ( isset( $data['qty'] ) )        $update['qty']        = max( 1, (int) $data['qty'] );
		if ( isset( $data['unit_price'] ) ) $update['unit_price'] = (float) $data['unit_price'];
		if ( isset( $data['disc_percent'] ) ) $update['disc_percent'] = (float) $data['disc_percent'];
		if ( array_key_exists( 'item_date', $data ) ) $update['item_date'] = $data['item_date'] ?: null;
		if ( empty( $update ) ) return false;
		return $wpdb->update( self::table(), $update, [ 'id' => (int) $id ] );
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'id' => (int) $id ] );
	}

	public static function line_total( array $item ) {
		$qty   = max( 1, (int) ( $item['qty'] ?? 1 ) );
		$price = (float) ( $item['unit_price'] ?? 0 );
		$disc  = (float) ( $item['disc_percent'] ?? 0 );
		return round( $qty * $price * ( 1 - $disc / 100 ), 2 );
	}
}
