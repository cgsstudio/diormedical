<?php
/**
 * Doctors block — REST endpoints (Tier B, Phase 3).
 *
 *   GET  /doc-booker-blocks/v1/doctors/options      → inspector source pickers
 *   POST /doc-booker-blocks/v1/doctors/load         → filter-aware grid (AJAX + load-more)
 *   GET  /doc-booker-blocks/v1/filter/departments   → cascading department list (shared)
 *
 * Public read-only data of public CPTs → permission_callback __return_true
 * (architecture §13). Standalone rule (§3): own `doc-booker-blocks/v1` namespace.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/doctors-data.php';

if ( ! function_exists( 'wpddbp_blocks_register_doctor_routes' ) ) {
	function wpddbp_blocks_register_doctor_routes() {
		$ns = 'doc-booker-blocks/v1';

		register_rest_route(
			$ns,
			'/doctors/options',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => 'wpddbp_blocks_doctors_options',
			)
		);

		register_rest_route(
			$ns,
			'/doctors/load',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'wpddbp_blocks_doctors_load',
			)
		);

		register_rest_route(
			$ns,
			'/filter/departments',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => 'wpddbp_blocks_filter_departments',
				'args'                => array(
					'clinic_id' => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
				),
			)
		);
	}
	add_action( 'rest_api_init', 'wpddbp_blocks_register_doctor_routes' );
}

if ( ! function_exists( 'wpddbp_blocks_doctors_options' ) ) {
	function wpddbp_blocks_doctors_options() {
		$doctors = get_posts(
			array(
				'post_type'      => 'wpddb_doctor',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$doctor_opts = array();
		foreach ( $doctors as $id ) {
			$doctor_opts[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ) );
		}

		$terms    = get_terms( array( 'taxonomy' => 'wpddb_doctor_department', 'hide_empty' => false ) );
		$dept_opts = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$dept_opts[] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'count' => (int) $t->count );
			}
		}

		$has_clinics = (int) wp_count_posts( 'wpddb_clinic' )->publish > 0;

		return rest_ensure_response(
			array(
				'doctors'     => $doctor_opts,
				'departments' => $dept_opts,
				'has_clinics' => $has_clinics,
			)
		);
	}
}

if ( ! function_exists( 'wpddbp_blocks_doctors_load' ) ) {
	function wpddbp_blocks_doctors_load( $request ) {
		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$attrs  = isset( $body['attributes'] ) && is_array( $body['attributes'] ) ? $body['attributes'] : array();
		$page   = max( 1, (int) ( $body['page'] ?? 1 ) );
		if ( isset( $body['per_page'] ) ) {
			$attrs['perPage'] = max( 1, (int) $body['per_page'] );
		}
		$filter = array(
			'clinic_id'     => isset( $body['filter']['clinic_id'] ) ? (int) $body['filter']['clinic_id'] : 0,
			'department_id' => isset( $body['filter']['department_id'] ) ? (int) $body['filter']['department_id'] : 0,
		);

		$result = wpddbp_doctors_render_grid( $attrs, $filter, $page );
		return rest_ensure_response( $result );
	}
}

if ( ! function_exists( 'wpddbp_blocks_filter_departments' ) ) {
	function wpddbp_blocks_filter_departments( $request ) {
		$clinic_id = absint( $request->get_param( 'clinic_id' ) );
		$cache_key = 'wpddbp_filter_deps_' . $clinic_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$out = array();

		if ( $clinic_id <= 0 ) {
			$terms = get_terms( array( 'taxonomy' => 'wpddb_doctor_department', 'hide_empty' => true ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$out[] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'count' => (int) $t->count );
				}
			}
		} else {
			// Doctors available at this clinic, then count their departments.
			$doctor_ids = get_posts(
				array(
					'post_type'      => 'wpddb_doctor',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => 'wpddb_doctor_available_clinic',
							'value'   => '"' . $clinic_id . '"',
							'compare' => 'LIKE',
						),
					),
				)
			);
			$counts = array();
			$names  = array();
			foreach ( $doctor_ids as $did ) {
				$terms = wp_get_post_terms( $did, 'wpddb_doctor_department' );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $t ) {
					$counts[ $t->term_id ] = ( $counts[ $t->term_id ] ?? 0 ) + 1;
					$names[ $t->term_id ]  = $t->name;
				}
			}
			foreach ( $counts as $tid => $c ) {
				$out[] = array( 'id' => (int) $tid, 'name' => $names[ $tid ], 'count' => (int) $c );
			}
			usort( $out, function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			} );
		}

		set_transient( $cache_key, $out, 15 * MINUTE_IN_SECONDS );
		return rest_ensure_response( $out );
	}
}

/** Bust the cascading-department cache when a doctor is saved/deleted. */
if ( ! function_exists( 'wpddbp_blocks_bust_filter_cache' ) ) {
	function wpddbp_blocks_bust_filter_cache( $post_id ) {
		if ( 'wpddb_doctor' !== get_post_type( $post_id ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpddbp\\_filter\\_deps\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpddbp\\_filter\\_deps\\_%'" );
	}
	add_action( 'save_post_wpddb_doctor', 'wpddbp_blocks_bust_filter_cache' );
	add_action( 'deleted_post', 'wpddbp_blocks_bust_filter_cache' );
}
