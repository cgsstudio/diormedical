<?php
/**
 * Doctor Awards block — REST endpoint (Tier B, Phase 3).
 *
 *   GET /doc-booker-blocks/v1/awards/options → inspector source picker
 *
 * Returns every published doctor with a flag for whether they have any awards,
 * so the editor's "Specific doctors" picker can prioritise awarded doctors.
 * Public read-only data of a public CPT → permission_callback __return_true (§13).
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/awards-data.php';

if ( ! function_exists( 'wpddbp_blocks_register_awards_routes' ) ) {
	function wpddbp_blocks_register_awards_routes() {
		register_rest_route(
			'doc-booker-blocks/v1',
			'/awards/options',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => 'wpddbp_blocks_awards_options',
			)
		);
	}
	add_action( 'rest_api_init', 'wpddbp_blocks_register_awards_routes' );
}

if ( ! function_exists( 'wpddbp_blocks_awards_options' ) ) {
	function wpddbp_blocks_awards_options() {
		$ids = get_posts(
			array(
				'post_type'      => 'wpddb_doctor',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$doctors        = array();
		$with_awards    = 0;
		$total_awards   = 0;
		foreach ( $ids as $id ) {
			$awards = get_post_meta( $id, 'wpddb_doctor_awards', true );
			$n      = 0;
			if ( is_array( $awards ) ) {
				foreach ( $awards as $a ) {
					if ( ! empty( $a['award_text'] ) || ! empty( $a['award_image'] ) ) {
						$n++;
					}
				}
			}
			if ( $n > 0 ) {
				$with_awards++;
				$total_awards += $n;
			}
			$doctors[] = array(
				'id'          => (int) $id,
				'title'       => get_the_title( $id ),
				'award_count' => $n,
			);
		}

		return rest_ensure_response(
			array(
				'doctors'      => $doctors,
				'with_awards'  => $with_awards,
				'total_awards' => $total_awards,
			)
		);
	}
}
