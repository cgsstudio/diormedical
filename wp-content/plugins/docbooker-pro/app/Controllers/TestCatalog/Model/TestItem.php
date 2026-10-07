<?php

namespace WpDreamers\WPDDBP\Controllers\TestCatalog\Model;

defined( 'ABSPATH' ) || exit;

class TestItem {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_test_items';
	}

	public static function find( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function find_by_clinic_name( $clinic_id, $name ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$t}` WHERE clinic_id=%d AND name=%s", (int) $clinic_id, $name ),
			ARRAY_A
		);
	}

	/**
	 * Returns items visible to a clinic: that clinic's own + global (clinic_id=0).
	 */
	public static function list_for_clinic( $clinic_id, $args = [] ) {
		global $wpdb;
		$t        = self::table();
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$status   = $args['status'] ?? 'active';

		$where  = [ 'status = %s' ];
		$params = [ $status ];
		if ( (int) $clinic_id > 0 ) {
			$where[]  = '(clinic_id = %d OR clinic_id = 0)';
			$params[] = (int) $clinic_id;
		}
		if ( $search !== '' ) {
			$where[]  = '(name LIKE %s OR category LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$t}` {$where_sql} ORDER BY name ASC LIMIT %d OFFSET %d",
				array_merge( $params, [ $per_page, $offset ] )
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` {$where_sql}", $params ) );

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	public static function list_admin( $args = [] ) {
		global $wpdb;
		$t        = self::table();
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$clinic   = isset( $args['clinic_id'] ) ? (int) $args['clinic_id'] : null;

		$where  = [ '1=1' ];
		$params = [];
		if ( $clinic !== null ) {
			$where[]  = 'clinic_id = %d';
			$params[] = $clinic;
		}
		if ( $search !== '' ) {
			$where[]  = '(name LIKE %s OR category LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$t}` {$where_sql} ORDER BY clinic_id ASC, name ASC LIMIT %d OFFSET %d",
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
		$status_in = $data['status'] ?? 'active';
		$row = [
			'clinic_id'  => (int) ( $data['clinic_id'] ?? 0 ),
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'price'      => (float) ( $data['price'] ?? 0 ),
			'category'   => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : null,
			'status'     => in_array( $status_in, [ 'active', 'archived' ], true ) ? $status_in : 'active',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$update = [];
		foreach ( [ 'name', 'category', 'status' ] as $k ) {
			if ( array_key_exists( $k, $data ) ) {
				$update[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		if ( isset( $data['price'] ) ) {
			$update['price'] = (float) $data['price'];
		}
		if ( isset( $data['clinic_id'] ) ) {
			$update['clinic_id'] = (int) $data['clinic_id'];
		}
		if ( empty( $update ) ) {
			return false;
		}
		$update['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( self::table(), $update, [ 'id' => (int) $id ] );
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), [ 'id' => (int) $id ] );
	}

	/**
	 * Upsert matched on (clinic_id, name) — used by the CSV bulk import.
	 * Returns ['inserted'|'updated', id].
	 */
	public static function upsert( $clinic_id, $name, $price, $category = null ) {
		$existing = self::find_by_clinic_name( (int) $clinic_id, (string) $name );
		if ( $existing ) {
			self::update( $existing['id'], [
				'price'    => (float) $price,
				'category' => $category,
				'status'   => 'active',
			] );
			return [ 'updated', (int) $existing['id'] ];
		}
		$id = self::create( [
			'clinic_id' => (int) $clinic_id,
			'name'      => $name,
			'price'     => (float) $price,
			'category'  => $category,
			'status'    => 'active',
		] );
		return [ 'inserted', (int) $id ];
	}
}
