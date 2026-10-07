<?php
/**
 * Clinics block — REST endpoint (Tier B, Phase 4).
 *
 *   GET /doc-booker-blocks/v1/clinics/options → inspector source picker
 *
 * Returns every published clinic (id + title) for the editor's "Specific clinics"
 * FormTokenField. Public read-only data of a public CPT → permission_callback
 * __return_true (§13). Standalone (§3): own `doc-booker-blocks/v1` namespace.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/clinics-data.php';

if ( ! function_exists( 'wpddbp_blocks_register_clinics_routes' ) ) {
	function wpddbp_blocks_register_clinics_routes() {
		register_rest_route(
			'doc-booker-blocks/v1',
			'/clinics/options',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => 'wpddbp_blocks_clinics_options',
			)
		);
	}
	add_action( 'rest_api_init', 'wpddbp_blocks_register_clinics_routes' );
}

if ( ! function_exists( 'wpddbp_blocks_clinics_options' ) ) {
	function wpddbp_blocks_clinics_options() {
		$ids = get_posts(
			array(
				'post_type'      => 'wpddb_clinic',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$clinics = array();
		foreach ( $ids as $id ) {
			$clinics[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ) );
		}
		return rest_ensure_response( array( 'clinics' => $clinics ) );
	}
}
