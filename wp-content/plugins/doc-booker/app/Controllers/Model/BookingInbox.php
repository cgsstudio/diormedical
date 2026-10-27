<?php

namespace WpDreamers\WPDDB\Controllers\Model;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Which bookings are new to the clinic: a patient booked online and nobody on
 * the admin side has opened it yet. Bookings staff add themselves are never
 * new. The admin app highlights new bookings and counts them in the menu.
 */
class BookingInbox {

	use SingletonTrait;

	public function __construct() {
		add_action( 'wpddb_booking_created', [ $this, 'record_created' ] );
		// A cancelled booking is no longer news (the admin is emailed about it).
		add_action( 'wpddb_booking_cancelled', [ $this, 'record_cancelled' ] );
	}

	/**
	 * @param object $booking The booking as it was before cancelling.
	 * @return void
	 */
	public function record_cancelled( $booking ) {
		if ( ! empty( $booking->id ) ) {
			self::mark_seen( [ (int) $booking->id ] );
		}
	}

	/**
	 * Remember where a booking came from, and flag patient bookings as new.
	 *
	 * @param array $data The wpddb_booking_created payload.
	 * @return void
	 */
	public function record_created( $data ) {
		if ( empty( $data['booking_id'] ) ) {
			return;
		}
		$via = isset( $data['source'] ) && 'admin' === $data['source'] ? 'admin' : 'public';
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wpddb_bookings',
			[ 'admin_seen' => 'admin' === $via ? 1 : 0, 'booked_via' => $via ],
			[ 'booking_id' => (string) $data['booking_id'] ],
			[ '%d', '%s' ],
			[ '%s' ]
		);
		wp_cache_delete( 'unseen', 'wpddb_inbox' );
	}

	/**
	 * How many bookings nobody has opened yet.
	 *
	 * @return int
	 */
	public static function unseen_count() {
		$cached = wp_cache_get( 'unseen', 'wpddb_inbox' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		if ( ! self::ready() ) {
			return 0;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE admin_seen = 0 AND status NOT IN ('cancelled','cancel','')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		wp_cache_set( 'unseen', $count, 'wpddb_inbox' );

		return $count;
	}

	/**
	 * Mark bookings as seen.
	 *
	 * @param int[]|null $ids Row ids, or null for every booking.
	 * @return int Rows changed.
	 */
	public static function mark_seen( $ids = null ) {
		if ( ! self::ready() ) {
			return 0;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';
		if ( null === $ids ) {
			$changed = $wpdb->query( "UPDATE {$table} SET admin_seen = 1 WHERE admin_seen = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
			if ( ! $ids ) {
				return 0;
			}
			$in      = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET admin_seen = 1 WHERE admin_seen = 0 AND id IN ($in)", ...$ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		wp_cache_delete( 'unseen', 'wpddb_inbox' );

		return (int) $changed;
	}

	/**
	 * The column exists (schema step 2 has run).
	 *
	 * @return bool
	 */
	public static function ready() {
		return (int) get_option( 'wpddb_schema_version', 0 ) >= 2;
	}
}
