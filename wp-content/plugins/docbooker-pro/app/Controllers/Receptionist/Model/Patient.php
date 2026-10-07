<?php
/**
 * Thin wrapper over the free plugin's wpddb_patients table — exposes search /
 * lookup / create / update operations that the receptionist UI needs.
 *
 * Lives under WPDDBP because we extend the schema; the free plugin owns the
 * table itself but only uses {id, full_name, email, phone}.
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist\Model;

defined( 'ABSPATH' ) || exit;

class Patient {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpddb_patients';
	}

	public static function find( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function find_by_phone( $phone ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE phone=%s ORDER BY id ASC LIMIT 1", $phone ), ARRAY_A );
	}

	public static function find_by_code( $code ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE patient_code=%s", $code ), ARRAY_A );
	}

	public static function search( $args = [] ) {
		global $wpdb;
		$t        = self::table();
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		if ( $search !== '' ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where  = 'WHERE full_name LIKE %s OR phone LIKE %s OR patient_code LIKE %s OR email LIKE %s';
			$params = [ $like, $like, $like, $like ];
			$rows   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$t}` {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
					array_merge( $params, [ $per_page, $offset ] )
				),
				ARRAY_A
			);
			$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` {$where}", $params ) );
		} else {
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM `{$t}` ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
				ARRAY_A
			);
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
		}

		return [
			'rows'  => array_map( [ self::class, 'enrich' ], $rows ?: [] ),
			'total' => $total,
		];
	}

	public static function generate_patient_code() {
		global $wpdb;
		$t = self::table();
		for ( $i = 0; $i < 5; $i++ ) {
			$code   = 'P' . gmdate( 'ymd' ) . str_pad( (string) wp_rand( 0, 99999 ), 5, '0', STR_PAD_LEFT );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE patient_code=%s", $code ) );
			if ( ! $exists ) {
				return $code;
			}
		}
		return 'P' . time();
	}

	public static function create( $data ) {
		global $wpdb;
		$row = [
			'full_name'    => sanitize_text_field( $data['full_name'] ?? '' ),
			'email'        => isset( $data['email'] ) && $data['email'] !== '' ? sanitize_email( $data['email'] ) : null,
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'dob'          => $data['dob'] ?? null,
			'gender'       => in_array( $data['gender'] ?? null, [ 'male', 'female', 'other' ], true ) ? $data['gender'] : null,
			'address'      => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null,
			'patient_code' => $data['patient_code'] ?? self::generate_patient_code(),
		];
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$update = [];
		foreach ( [ 'full_name', 'phone', 'address' ] as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		if ( isset( $data['email'] ) ) {
			$update['email'] = $data['email'] !== '' ? sanitize_email( $data['email'] ) : null;
		}
		if ( isset( $data['gender'] ) ) {
			$update['gender'] = in_array( $data['gender'], [ 'male', 'female', 'other' ], true ) ? $data['gender'] : null;
		}
		if ( array_key_exists( 'dob', $data ) ) {
			$update['dob'] = $data['dob'] ?: null;
		}
		if ( empty( $update ) ) {
			return false;
		}
		return $wpdb->update( self::table(), $update, [ 'id' => (int) $id ] );
	}

	/**
	 * Add computed `age` from dob to a patient row.
	 */
	public static function enrich( $row ) {
		if ( ! $row ) return $row;
		$row['age'] = self::compute_age( $row['dob'] ?? null );
		return $row;
	}

	public static function compute_age( $dob ) {
		if ( empty( $dob ) ) return null;
		try {
			$d   = new \DateTime( $dob );
			$now = new \DateTime( 'today' );
			return (int) $d->diff( $now )->y;
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
