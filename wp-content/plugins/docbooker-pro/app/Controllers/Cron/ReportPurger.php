<?php
/**
 * Daily WP-Cron job that deletes uploaded test reports past their retention
 * window. Runs in batches of 200 rows per fire so very large purges don't
 * blow up a single request.
 */

namespace WpDreamers\WPDDBP\Controllers\Cron;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class ReportPurger {
	use SingletonTrait;

	const HOOK = 'wpddb_purge_old_reports';

	public function __construct() {
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	public function run() {
		global $wpdb;
		$t      = $wpdb->prefix . 'wpddb_test_reports';
		// expires_at is stored in UTC, so compare against UTC "now"; site time
		// purged reports early east of UTC and late west of it.
		$now    = current_time( 'mysql', true );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attachment_id FROM `{$t}` WHERE expires_at <= %s ORDER BY id ASC LIMIT 200",
				$now
			),
			ARRAY_A
		) ?: [];
		if ( ! $rows ) {
			return;
		}
		$ids = [];
		foreach ( $rows as $r ) {
			$att = (int) $r['attachment_id'];
			if ( $att ) {
				wp_delete_attachment( $att, true );
			}
			$ids[] = (int) $r['id'];
		}
		if ( $ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$t}` WHERE id IN ({$placeholders})", $ids ) );
		}
		error_log( '[wpddbp] Purged ' . count( $ids ) . ' expired report(s).' );
	}
}
