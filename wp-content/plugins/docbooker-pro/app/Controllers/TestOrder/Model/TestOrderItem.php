<?php

namespace WpDreamers\WPDDBP\Controllers\TestOrder\Model;

defined( 'ABSPATH' ) || exit;

class TestOrderItem {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_test_order_items';
	}

	public static function list_for_order( $order_id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$t}` WHERE order_id=%d ORDER BY id ASC", (int) $order_id ),
			ARRAY_A
		) ?: [];
	}

	public static function delete_for_order( $order_id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'order_id' => (int) $order_id ] );
	}

	public static function create( $data ) {
		global $wpdb;
		$row = [
			'order_id'      => (int) $data['order_id'],
			'test_item_id'  => isset( $data['test_item_id'] ) ? (int) $data['test_item_id'] : null,
			'name_snapshot' => sanitize_text_field( $data['name_snapshot'] ),
			'price_snapshot'=> (float) $data['price_snapshot'],
			'qty'           => max( 1, (int) ( $data['qty'] ?? 1 ) ),
			'disc_percent'  => (float) ( $data['disc_percent'] ?? 0 ),
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function line_total( array $item ) {
		$qty   = max( 1, (int) ( $item['qty'] ?? 1 ) );
		$price = (float) ( $item['price_snapshot'] ?? 0 );
		$disc  = (float) ( $item['disc_percent'] ?? 0 );
		return round( $qty * $price * ( 1 - $disc / 100 ), 2 );
	}

	public static function compute_subtotal_for_order( $order_id ) {
		$items    = self::list_for_order( $order_id );
		$subtotal = 0;
		foreach ( $items as $it ) {
			$subtotal += self::line_total( $it );
		}
		return round( $subtotal, 2 );
	}

	/**
	 * Gross subtotal — sum of qty × price_snapshot BEFORE per-line discounts.
	 * The per-line discount total is then `gross − net` (compute_subtotal_for_order).
	 */
	public static function compute_gross_for_order( $order_id ) {
		$items = self::list_for_order( $order_id );
		$gross = 0;
		foreach ( $items as $it ) {
			$gross += max( 1, (int) ( $it['qty'] ?? 1 ) ) * (float) ( $it['price_snapshot'] ?? 0 );
		}
		return round( $gross, 2 );
	}
}
