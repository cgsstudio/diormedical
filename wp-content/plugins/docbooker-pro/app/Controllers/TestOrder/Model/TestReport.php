<?php

namespace WpDreamers\WPDDBP\Controllers\TestOrder\Model;

defined( 'ABSPATH' ) || exit;

class TestReport {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_test_reports';
	}

	public static function find( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function list_for_order( $order_id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$t}` WHERE order_id=%d ORDER BY uploaded_at DESC", (int) $order_id ),
			ARRAY_A
		) ?: [];
	}

	public static function exists_for_order_and_hash( $order_id, $hash ) {
		global $wpdb;
		$t = self::table();
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM `{$t}` WHERE order_id=%d AND file_hash=%s", (int) $order_id, $hash )
		);
	}

	public static function create( $data ) {
		global $wpdb;
		$retention_days = (int) self::get_retention_days();
		$expires        = gmdate( 'Y-m-d H:i:s', time() + ( $retention_days * DAY_IN_SECONDS ) );
		$row            = [
			'order_id'      => (int) $data['order_id'],
			'attachment_id' => (int) $data['attachment_id'],
			'uploaded_by'   => (int) ( $data['uploaded_by'] ?? get_current_user_id() ),
			'file_hash'     => isset( $data['file_hash'] ) ? substr( (string) $data['file_hash'], 0, 64 ) : null,
			'note'          => isset( $data['note'] ) ? sanitize_text_field( $data['note'] ) : null,
			'uploaded_at'   => current_time( 'mysql' ),
			'expires_at'    => $expires,
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'id' => (int) $id ] );
	}

	public static function get_retention_days() {
		$g = get_option( 'wpddb_global_settings', [] );
		$d = isset( $g['report_retention_days'] ) ? (int) $g['report_retention_days'] : 30;
		return $d > 0 ? $d : 30;
	}
}
