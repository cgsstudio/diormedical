<?php
/**
 * Departments block — REST endpoint (Tier B, Phase 3).
 *
 *   GET /doc-booker-blocks/v1/departments/options → inspector source picker + editor preview
 *
 * Returns every `wpddb_doctor_department` term augmented with the resolved
 * icon_svg + accent (read-time defaults applied), so the editor preview + the
 * FormTokenField source picker render without any client-side icon resolution.
 * Public read-only data of a public taxonomy → permission_callback __return_true
 * (architecture §13). Standalone rule (§3): own `doc-booker-blocks/v1` namespace.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/departments-data.php';

if ( ! function_exists( 'wpddbp_blocks_register_department_routes' ) ) {
	function wpddbp_blocks_register_department_routes() {
		register_rest_route(
			'doc-booker-blocks/v1',
			'/departments/options',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => 'wpddbp_blocks_departments_options',
			)
		);
	}
	add_action( 'rest_api_init', 'wpddbp_blocks_register_department_routes' );
}

if ( ! function_exists( 'wpddbp_blocks_departments_options' ) ) {
	function wpddbp_blocks_departments_options() {
		$terms = get_terms( array( 'taxonomy' => 'wpddb_doctor_department', 'hide_empty' => false ) );
		$out   = array();
		if ( ! is_wp_error( $terms ) ) {
			// Resolve with term accent (the editor preview shows author colours; the
			// global-accent override is a render-time concern handled per-block).
			$resolve_attrs = array( 'useTermAccentColor' => true );
			foreach ( $terms as $t ) {
				$icon_meta = get_term_meta( $t->term_id, 'wpddb_doctor_department_icon', true );
				$out[]     = array(
					'id'          => (int) $t->term_id,
					'name'        => $t->name,
					'slug'        => $t->slug,
					'count'       => (int) $t->count,
					'description' => trim( (string) $t->description ),
					'icon_svg'    => wpddbp_dept_resolve_icon_svg( $icon_meta, $t->name ),
					'accent'      => wpddbp_dept_resolve_accent( $resolve_attrs, $t ),
				);
			}
		}

		return rest_ensure_response( array( 'departments' => $out ) );
	}
}
