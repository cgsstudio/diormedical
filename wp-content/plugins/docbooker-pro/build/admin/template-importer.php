<?php
/**
 * Template Importer — Pro-side REST (DocBooker Pro, Phase 6).
 *
 * FREEMIUM split (see docs/phases/phase-6-template-importer/README.md):
 *   • The FREE plugin (doc-booker) owns the admin "Templates" submenu + React
 *     grid + the Pro-upsell gate. It localizes `importUrl` →
 *     rest_url('doc-booker-blocks/v1/templates/import/start') and, when Pro is
 *     active, POSTs `{ templateId }` and reads `edit_url` from the response.
 *   • THIS file (Pro) owns ONLY the real import: the REST endpoints + the
 *     block-markup templates. No admin menu, no asset enqueue here.
 *
 * Endpoints (mirror gym-builder-block-tools, renamed to doc-booker-blocks):
 *   POST /doc-booker-blocks/v1/templates/import/start  — one-shot full import
 *        (the Free UI's contract) AND session initialiser.
 *   POST /doc-booker-blocks/v1/templates/import/step   — run a single step of an
 *        existing session (idempotent), for a future progressive UI / retries.
 *
 * 5-step pipeline, state in a 5-min transient (`wpddbp_imp_<uuid>`):
 *   validate → prepare → images → create → finalize.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/template-utils.php';

/* ───────────────── REST registration ───────────────── */

add_action( 'rest_api_init', 'wpddbp_register_template_routes' );
function wpddbp_register_template_routes() {
	register_rest_route(
		'doc-booker-blocks/v1',
		'/templates/import/start',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'wpddbp_import_caps',
			'callback'            => 'wpddbp_rest_import_start',
			'args'                => array(
				'templateId' => array( 'type' => 'string', 'required' => false ),
				'slug'       => array( 'type' => 'string', 'required' => false ),
				'options'    => array( 'type' => 'object', 'default' => array() ),
			),
		)
	);
	register_rest_route(
		'doc-booker-blocks/v1',
		'/templates/import/step',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'wpddbp_import_caps',
			'callback'            => 'wpddbp_rest_import_step',
			'args'                => array(
				'import_id'  => array( 'type' => 'string', 'required' => true ),
				'step_index' => array( 'type' => 'integer', 'required' => true ),
			),
		)
	);
}

function wpddbp_import_caps() {
	return current_user_can( 'manage_options' );
}

/* The 5 import steps (ordered). */
function wpddbp_import_steps() {
	return array(
		array( 'key' => 'validate', 'label' => __( 'Validating template', 'doc-booker-pro' ) ),
		array( 'key' => 'prepare',  'label' => __( 'Loading block markup', 'doc-booker-pro' ) ),
		array( 'key' => 'images',   'label' => __( 'Processing images',    'doc-booker-pro' ) ),
		array( 'key' => 'create',   'label' => __( 'Creating draft page',  'doc-booker-pro' ) ),
		array( 'key' => 'finalize', 'label' => __( 'Finalising',           'doc-booker-pro' ) ),
	);
}

/**
 * Resolve the requested template slug from either `templateId` (Free UI) or
 * `slug` (gym-builder-style / future callers). Normalised to a-z0-9_-.
 */
function wpddbp_resolve_slug( WP_REST_Request $request ) {
	$raw = $request->get_param( 'templateId' );
	if ( $raw === null || $raw === '' ) {
		$raw = $request->get_param( 'slug' );
	}
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $raw ) );
}

/**
 * Resolve composer "pin a doctor" intent-markers into real IDs.
 *
 * A hero in dynamic SINGLE-DOCTOR mode needs concrete acDoctorIds/acClinicId,
 * but a composer can't know the importing site's IDs. It ships markers instead:
 *
 *   "acPinDoctor": true              → pin one real doctor at import time
 *   "acPinDepartment": "Pediatrics"  → prefer a doctor from this department
 *
 * This swaps the markers for the newest published doctor (department-matched
 * when possible) plus that doctor's first clinic, and strips the markers so
 * the editor and render only ever see real block attributes. Heroes without
 * the marker — e.g. the dental template's selector hero, which is dynamic but
 * deliberately unpinned — pass through untouched. If the site has no doctors
 * yet, the markers are stripped and the hero stays dynamic-unpinned, which
 * renders as the normal selector card.
 */
function wpddbp_resolve_pinned_doctors( $content ) {
	if ( strpos( $content, 'acPinDoctor' ) === false ) {
		return $content;
	}
	return preg_replace_callback(
		'/<!-- wp:doc-booker-pro\/hero (\{.*?\}) (\/?-->)/s',
		function ( $m ) {
			$attrs = json_decode( $m[1], true );
			if ( ! is_array( $attrs ) || empty( $attrs['acPinDoctor'] ) ) {
				return $m[0];
			}
			$dept = isset( $attrs['acPinDepartment'] ) ? (string) $attrs['acPinDepartment'] : '';
			unset( $attrs['acPinDoctor'], $attrs['acPinDepartment'] );

			$base = array(
				'post_type'      => 'wpddb_doctor',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			);
			$doctor_id = 0;
			if ( '' !== $dept ) {
				$term = get_term_by( 'name', $dept, 'wpddb_doctor_department' );
				if ( $term ) {
					$ids = get_posts( array_merge( $base, array(
						'tax_query' => array(
							array(
								'taxonomy' => 'wpddb_doctor_department',
								'field'    => 'term_id',
								'terms'    => (int) $term->term_id,
							),
						),
					) ) );
					$doctor_id = $ids ? (int) $ids[0] : 0;
				}
			}
			if ( ! $doctor_id ) {
				$ids       = get_posts( $base );
				$doctor_id = $ids ? (int) $ids[0] : 0;
			}
			if ( $doctor_id ) {
				$attrs['acDoctorIds'] = array( $doctor_id );
				// The doctor's clinics live in a serialized array of id strings.
				$clinics = get_post_meta( $doctor_id, 'wpddb_doctor_available_clinic', true );
				$clinic  = ( is_array( $clinics ) && $clinics ) ? (int) reset( $clinics ) : 0;
				if ( $clinic && 'publish' === get_post_status( $clinic ) ) {
					$attrs['acClinicId'] = $clinic;
				}
			}
			return '<!-- wp:doc-booker-pro/hero ' . serialize_block_attributes( $attrs ) . ' ' . $m[2];
		},
		$content
	);
}

/* ───────────────── /start — one-shot full import ─────────────────
   The Free UI fires this once and expects the final payload (edit_url, …)
   straight back. So /start creates the session AND runs every step in
   sequence. The image-sideload step is a no-op unless options.import_images
   is set, so this stays fast (no network). The session transient is still
   written after each step, so a future progressive UI could also drive
   /step against the same id.
   ──────────────────────────────────────────────────────────────────── */
function wpddbp_rest_import_start( WP_REST_Request $request ) {
	$slug     = wpddbp_resolve_slug( $request );
	$options  = (array) $request->get_param( 'options' );
	$registry = wpddbp_get_registry();

	if ( $slug === '' || ! isset( $registry[ $slug ] ) ) {
		return new WP_REST_Response( array( 'message' => __( 'Unknown template.', 'doc-booker-pro' ) ), 404 );
	}

	$import_id   = wpddbp_uuid();
	$session_key = 'wpddbp_imp_' . $import_id;
	$session     = array(
		'slug'       => $slug,
		'options'    => array( 'import_images' => ! empty( $options['import_images'] ) ),
		'content'    => null,
		'post_id'    => null,
		'theme_tip'  => '',
		'completed'  => array(),
		'started_at' => time(),
	);
	set_transient( $session_key, $session, 5 * MINUTE_IN_SECONDS );

	$steps = wpddbp_import_steps();
	$total = count( $steps );

	// Run every step. Any failure returns its error response immediately.
	foreach ( array_keys( $steps ) as $index ) {
		$result = wpddbp_execute_step( $session, $index, $registry );
		if ( is_wp_error( $result ) ) {
			delete_transient( $session_key );
			$data = $result->get_error_data();
			$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), $code );
		}
		// $session is updated by reference inside wpddbp_execute_step.
		set_transient( $session_key, $session, 5 * MINUTE_IN_SECONDS );
	}

	// Final payload — what the Free UI reads (edit_url) + everything a richer UI may want.
	$payload = wpddbp_finalize_payload( $session, $total );
	$payload['import_id']   = $import_id;
	$payload['total_steps'] = $total;
	$payload['message']     = __( 'Template imported.', 'doc-booker-pro' );
	return new WP_REST_Response( $payload, 200 );
}

/* ───────────────── /step — single step (idempotent) ───────────────── */
function wpddbp_rest_import_step( WP_REST_Request $request ) {
	$import_id  = sanitize_text_field( $request->get_param( 'import_id' ) );
	$step_index = (int) $request->get_param( 'step_index' );

	$session_key = 'wpddbp_imp_' . $import_id;
	$session     = get_transient( $session_key );
	if ( ! $session || ! is_array( $session ) ) {
		return new WP_REST_Response( array( 'message' => __( 'Import session expired or not found.', 'doc-booker-pro' ) ), 404 );
	}

	$steps = wpddbp_import_steps();
	$total = count( $steps );
	if ( ! isset( $steps[ $step_index ] ) ) {
		return new WP_REST_Response( array( 'message' => __( 'Invalid step index.', 'doc-booker-pro' ) ), 400 );
	}

	// Idempotency: replaying a completed step returns its cached result.
	if ( in_array( $step_index, $session['completed'], true ) ) {
		return new WP_REST_Response(
			wpddbp_step_payload( $step_index, $steps[ $step_index ], __( 'Already completed', 'doc-booker-pro' ), $session, $total ),
			200
		);
	}

	$registry = wpddbp_get_registry();
	$result   = wpddbp_execute_step( $session, $step_index, $registry );
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
		return new WP_REST_Response( array( 'message' => $result->get_error_message() ), $code );
	}

	set_transient( $session_key, $session, 5 * MINUTE_IN_SECONDS );
	return new WP_REST_Response(
		wpddbp_step_payload( $step_index, $steps[ $step_index ], __( 'Done', 'doc-booker-pro' ), $session, $total ),
		200
	);
}

/* ───────────────── Per-step executor (shared by /start + /step) ─────────────────
   Mutates $session by reference. Returns true on success, WP_Error on failure
   (with an HTTP status in the error data). Marks the step complete.
   ──────────────────────────────────────────────────────────────────── */
function wpddbp_execute_step( array &$session, $step_index, array $registry ) {
	$steps = wpddbp_import_steps();
	if ( ! isset( $steps[ $step_index ] ) ) {
		return new WP_Error( 'wpddbp_bad_step', __( 'Invalid step index.', 'doc-booker-pro' ), array( 'status' => 400 ) );
	}
	if ( in_array( $step_index, $session['completed'], true ) ) {
		return true; // already done — idempotent.
	}

	$tpl = $registry[ $session['slug'] ];

	switch ( $steps[ $step_index ]['key'] ) {

		case 'validate':
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'wpddbp_forbidden', __( 'Permission denied.', 'doc-booker-pro' ), array( 'status' => 403 ) );
			}
			break;

		case 'prepare':
			$content = wpddbp_load_template_html( $session['slug'] );
			if ( is_wp_error( $content ) ) {
				return new WP_Error( $content->get_error_code(), $content->get_error_message(), array( 'status' => 422 ) );
			}
			$session['content'] = $content;
			break;

		case 'images':
			if ( ! empty( $session['options']['import_images'] ) && ! empty( $session['content'] ) ) {
				$session['content'] = wpddbp_sideload_images_into_content( $session['content'] );
			}
			break;

		case 'create':
			// Swap composer intent-markers (acPinDoctor/acPinDepartment) for a real
			// doctor + clinic from THIS site before the content is inserted.
			$session['content'] = wpddbp_resolve_pinned_doctors( (string) $session['content'] );
			$page_title = isset( $tpl['page_title'] ) ? $tpl['page_title'] : ( $tpl['title'] ?? __( 'Imported Page', 'doc-booker-pro' ) );
			$post_id    = wp_insert_post(
				array(
					'post_title'   => $page_title,
					// wp_slash: wp_insert_post runs wp_unslash on its input, which would strip the
					// \" escaping inside block-attribute JSON (FAQ Q&A, slider captions, etc.) and
					// break those blocks. Slashing here cancels that unslash.
					'post_content' => wp_slash( (string) $session['content'] ),
					'post_status'  => 'draft',
					'post_type'    => 'page',
					'meta_input'   => array_merge(
						array( '_wpddbp_template_source' => $session['slug'] ),
						wpddbp_theme_layout_meta()
					),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return new WP_Error( 'wpddbp_insert_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
			}
			$session['post_id'] = (int) $post_id;
			break;

		case 'finalize':
			$session['theme_tip'] = wpddbp_compose_theme_tip();
			break;
	}

	$session['completed'][] = $step_index;
	return true;
}

/* ───────────────── Payload builders ───────────────── */

function wpddbp_step_payload( $step_index, $step_meta, $message, $session, $total ) {
	$res = array(
		'step_index' => $step_index,
		'key'        => $step_meta['key'],
		'label'      => $step_meta['label'],
		'ok'         => true,
		'message'    => $message,
		'progress'   => $total > 0 ? ( $step_index + 1 ) / $total : 1,
	);
	if ( $step_meta['key'] === 'finalize' && ! empty( $session['post_id'] ) ) {
		$res = array_merge( $res, wpddbp_finalize_payload( $session, $total ) );
	}
	return $res;
}

function wpddbp_finalize_payload( $session, $total ) {
	$post_id = (int) ( $session['post_id'] ?? 0 );
	return array(
		'ok'        => true,
		'post_id'   => $post_id,
		'edit_url'  => $post_id ? get_edit_post_link( $post_id, 'raw' ) : '',
		'view_url'  => $post_id ? get_permalink( $post_id ) : '',
		'theme_tip' => isset( $session['theme_tip'] ) ? $session['theme_tip'] : '',
		'progress'  => 1,
	);
}
