<?php
/**
 * Doctors block — shared data + card renderers (Tier B, Phase 3).
 *
 * One source of truth for: the WP_Query (inspector source + §11a clinic/department
 * filter), the normalized per-doctor data array, the per-card renderers, and the
 * grid renderer. Both `render.php` (initial server paint) and the REST `/doctors/load`
 * handler (AJAX filter + load-more) call `wpddbp_doctors_render_grid()` so markup is
 * identical. Standalone rule (architecture §3): namespaced `wpddbp_`, reads the real
 * CPT/meta/taxonomy directly (department tax `wpddb_doctor_department`; clinic linkage
 * `wpddb_doctor_available_clinic` serialized-array meta queried via LIKE).
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/doctors-book-button.php';

/**
 * Build the WP_Query for a doctors block, honoring the inspector source attrs and
 * the optional §11a filter (clinic_id via meta LIKE, department_id via tax_query).
 *
 * @param array $attrs  Block attributes.
 * @param array $filter [ 'clinic_id' => int, 'department_id' => int ].
 * @param int   $page   1-based page.
 * @return WP_Query
 */
function wpddbp_doctors_query( $attrs, $filter = array(), $page = 1 ) {
	$per_page = max( 1, (int) ( $attrs['perPage'] ?? 9 ) );
	$orderby  = in_array( ( $attrs['orderBy'] ?? 'date' ), array( 'date', 'title', 'menu_order', 'rand' ), true ) ? $attrs['orderBy'] : 'date';
	$order    = ( strtoupper( $attrs['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

	$args = array(
		'post_type'           => 'wpddb_doctor',
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'paged'               => max( 1, (int) $page ),
		'orderby'             => $orderby,
		'order'               => $order,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
	);

	if ( ! empty( $attrs['doctorIds'] ) && is_array( $attrs['doctorIds'] ) ) {
		$args['post__in'] = array_map( 'intval', $attrs['doctorIds'] );
		$args['orderby']  = 'post__in';
	}

	// ---- department: inspector source + filter (relation AND across the two) ----
	$dept_ids    = ( ! empty( $attrs['departmentIds'] ) && is_array( $attrs['departmentIds'] ) ) ? array_map( 'intval', $attrs['departmentIds'] ) : array();
	$filter_dept = (int) ( $filter['department_id'] ?? 0 );
	$tax_clauses = array();
	if ( ! empty( $dept_ids ) ) {
		$tax_clauses[] = array(
			'taxonomy' => 'wpddb_doctor_department',
			'field'    => 'term_id',
			'terms'    => $dept_ids,
			'operator' => ( ( $attrs['departmentRelation'] ?? 'OR' ) === 'AND' ) ? 'AND' : 'IN',
		);
	}
	if ( $filter_dept > 0 ) {
		$tax_clauses[] = array(
			'taxonomy' => 'wpddb_doctor_department',
			'field'    => 'term_id',
			'terms'    => array( $filter_dept ),
		);
	}
	if ( ! empty( $tax_clauses ) ) {
		if ( count( $tax_clauses ) > 1 ) {
			$tax_clauses['relation'] = 'AND';
		}
		$args['tax_query'] = $tax_clauses;
	}

	// ---- clinic filter (serialized-array meta, LIKE on the quoted id) ----
	$clinic = (int) ( $filter['clinic_id'] ?? 0 );
	if ( $clinic > 0 ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'wpddb_doctor_available_clinic',
				'value'   => '"' . $clinic . '"',
				'compare' => 'LIKE',
			),
		);
	}

	return new WP_Query( $args );
}

/**
 * Normalize one doctor post into the data array the card renderers consume.
 *
 * @param int   $post_id Doctor post ID.
 * @param array $attrs   Block attributes (for bio source/limit).
 * @return array
 */
function wpddbp_doctors_card_data( $post_id, $attrs = array() ) {
	$post_id = (int) $post_id;

	$img_id  = get_post_thumbnail_id( $post_id );
	$image   = $img_id ? wp_get_attachment_image_url( $img_id, 'medium_large' ) : '';

	$terms = wp_get_post_terms( $post_id, 'wpddb_doctor_department' );
	$dept  = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : null;

	$bio_source = ( ( $attrs['bioSource'] ?? 'excerpt' ) === 'content' ) ? 'content' : 'excerpt';
	$word_limit = max( 5, (int) ( $attrs['bioWordLimit'] ?? 24 ) );
	$raw_bio    = ( 'content' === $bio_source ) ? get_post_field( 'post_content', $post_id ) : get_the_excerpt( $post_id );
	$bio        = wp_trim_words( wp_strip_all_tags( $raw_bio ), $word_limit, '…' );

	$languages   = get_post_meta( $post_id, 'wpddb_doctor_languages', true );
	$credentials = get_post_meta( $post_id, 'wpddb_doctor_credentials', true );
	$socials     = get_post_meta( $post_id, 'wpddb_doctor_social_icons', true );

	return array(
		'id'            => $post_id,
		'name'          => get_the_title( $post_id ),
		'permalink'     => get_permalink( $post_id ),
		'image'         => $image,
		'dept_name'     => $dept ? $dept->name : '',
		'dept_slug'     => $dept ? $dept->slug : '',
		'designation'   => (string) get_post_meta( $post_id, 'wpddb_doctor_designation', true ),
		'speciality'    => (string) get_post_meta( $post_id, 'wpddb_doctor_speciality', true ),
		'degree'        => (string) get_post_meta( $post_id, 'wpddb_doctor_degree', true ),
		'workplace'     => (string) get_post_meta( $post_id, 'wpddb_doctor_workplace', true ),
		'bio'           => $bio,
		'experience'    => (string) get_post_meta( $post_id, 'wpddb_doctor_experience_years', true ),
		'rating'        => (float) get_post_meta( $post_id, 'wpddb_doctor_rating', true ),
		'review_count'  => (int) get_post_meta( $post_id, 'wpddb_doctor_review_count', true ),
		'patient_count' => (string) get_post_meta( $post_id, 'wpddb_doctor_patient_count', true ),
		'languages'     => is_array( $languages ) ? $languages : array(),
		'credentials'   => is_array( $credentials ) ? $credentials : array(),
		'alma_mater'    => (string) get_post_meta( $post_id, 'wpddb_doctor_alma_mater', true ),
		'featured_label'=> (string) get_post_meta( $post_id, 'wpddb_doctor_featured_label', true ),
		'quote'         => (string) get_post_meta( $post_id, 'wpddb_doctor_quote', true ),
		'fee'           => wpddbp_doctor_booking_fee( $post_id ),
		'socials'       => is_array( $socials ) ? $socials : array(),
	);
}

/** A boolean display toggle, defaulting true when unset. */
function wpddbp_doctors_show( $attrs, $key ) {
	return ! isset( $attrs[ $key ] ) || $attrs[ $key ];
}

/** Inline SVG for a social channel (by stored social_name); generic link icon fallback. */
function wpddbp_doctors_social_svg( $name ) {
	$n   = strtolower( (string) $name );
	$map = array(
		'linkedin'  => '<path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.36V9h3.4v1.56h.05c.47-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43A2.06 2.06 0 1 1 5.34 3.3a2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45Z"/>',
		'twitter'   => '<path d="M18.24 3H21l-6.5 7.43L22 21h-6.39l-4.94-6.46L4.97 21H2.21l7.02-8.02L2 3h6.55l4.47 5.92L18.24 3Z"/>',
		'x'         => '<path d="M18.24 3H21l-6.5 7.43L22 21h-6.39l-4.94-6.46L4.97 21H2.21l7.02-8.02L2 3h6.55l4.47 5.92L18.24 3Z"/>',
		'facebook'  => '<path d="M22 12a10 10 0 1 0-11.6 9.9V15h-2.4v-3h2.4V9.8c0-2.4 1.4-3.7 3.6-3.7 1 0 2.1.2 2.1.2V8.6h-1.2c-1.2 0-1.6.7-1.6 1.5V12h2.7l-.4 3h-2.3v6.9A10 10 0 0 0 22 12Z"/>',
		'youtube'   => '<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z"/>',
		'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.17.05 1.97.24 2.67.5.7.27 1.3.64 1.87 1.2.56.57.93 1.16 1.2 1.86.26.7.45 1.5.5 2.67.06 1.3.07 1.7.07 4.9s-.01 3.6-.07 4.9c-.05 1.17-.24 1.97-.5 2.67a5 5 0 0 1-1.2 1.87 5 5 0 0 1-1.87 1.2c-.7.26-1.5.45-2.67.5-1.3.06-1.7.07-4.9.07s-3.6-.01-4.9-.07c-1.17-.05-1.97-.24-2.67-.5a5 5 0 0 1-1.87-1.2 5 5 0 0 1-1.2-1.87c-.26-.7-.45-1.5-.5-2.67C2.21 15.6 2.2 15.2 2.2 12s.01-3.6.07-4.9c.05-1.17.24-1.97.5-2.67.27-.7.64-1.3 1.2-1.86A5 5 0 0 1 5.84 1.2c.7-.26 1.5-.45 2.67-.5C9.81 2.2 10.2 2.2 12 2.2Zm0 4.9a4.9 4.9 0 1 0 0 9.8 4.9 4.9 0 0 0 0-9.8Zm0 8.08a3.18 3.18 0 1 1 0-6.36 3.18 3.18 0 0 1 0 6.36Zm6.24-8.28a1.14 1.14 0 1 1-2.28 0 1.14 1.14 0 0 1 2.28 0Z"/>',
		'email'     => '<path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm9 8.5L21 7H3l9 6.5Z"/>',
		'website'   => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 6h-2.95a15.7 15.7 0 0 0-1.38-3.56A8 8 0 0 1 18.93 8ZM12 4c.83 1.2 1.48 2.53 1.91 4h-3.82c.43-1.47 1.08-2.8 1.91-4ZM4.26 14a7.8 7.8 0 0 1 0-4h3.38a16.6 16.6 0 0 0 0 4H4.26Zm.81 2h2.95c.34 1.27.8 2.46 1.38 3.56A8 8 0 0 1 5.07 16Zm2.95-8H5.07a8 8 0 0 1 4.33-3.56A15.7 15.7 0 0 0 8.02 8ZM12 20c-.83-1.2-1.48-2.53-1.91-4h3.82A13.8 13.8 0 0 1 12 20Zm2.36-6H9.64a14.6 14.6 0 0 1 0-4h4.72a14.6 14.6 0 0 1 0 4Zm.27 5.56c.58-1.1 1.04-2.29 1.38-3.56h2.95a8 8 0 0 1-4.33 3.56ZM16.36 14a16.6 16.6 0 0 0 0-4h3.38a7.8 7.8 0 0 1 0 4h-3.38Z"/>',
	);
	$path = isset( $map[ $n ] ) ? $map[ $n ] : '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" fill="none" stroke="currentColor" stroke-width="2"/>';
	return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
}

/** Render the socials cluster (respecting per-channel toggles) or ''. */
function wpddbp_doctors_socials_html( $d, $attrs ) {
	if ( ! wpddbp_doctors_show( $attrs, 'showSocials' ) || empty( $d['socials'] ) ) {
		return '';
	}
	$out = '';
	foreach ( $d['socials'] as $s ) {
		$url  = isset( $s['social_url'] ) ? trim( $s['social_url'] ) : '';
		$name = isset( $s['social_name'] ) ? $s['social_name'] : '';
		if ( '' === $url ) {
			continue;
		}
		$out .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $name ) . '">' . wpddbp_doctors_social_svg( $name ) . '</a>';
	}
	return ( '' !== $out ) ? '<span class="wpddbp-doctors-socials">' . $out . '</span>' : '';
}

/** Star + numeric rating (only when rating > 0 and showRating). */
function wpddbp_doctors_rating_html( $d, $attrs ) {
	if ( ! wpddbp_doctors_show( $attrs, 'showRating' ) || $d['rating'] <= 0 ) {
		return '';
	}
	$rating = rtrim( rtrim( number_format( $d['rating'], 2, '.', '' ), '0' ), '.' );
	$star   = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 7 7.5.7-5.7 5L18.5 22 12 18.3 5.5 22l1.7-7.3-5.7-5L9 9z"/></svg>';
	$reviews = ( wpddbp_doctors_show( $attrs, 'showReviewCount' ) && $d['review_count'] > 0 )
		? ' <small>· ' . esc_html( number_format_i18n( $d['review_count'] ) ) . '</small>'
		: '';
	return '<span class="wpddbp-doctors-rating">' . $star . esc_html( $rating ) . $reviews . '</span>';
}

/** Fee badge (label + formatted amount) or ''. */
function wpddbp_doctors_fee_html( $d, $attrs ) {
	if ( ! wpddbp_doctors_show( $attrs, 'showFee' ) || $d['fee'] <= 0 ) {
		return '';
	}
	return '<span class="wpddbp-doctors-fee"><span>' . esc_html__( 'From', 'doc-booker-pro' ) . '</span><b>' . esc_html( wpddbp_format_fee( $d['fee'] ) ) . '</b></span>';
}

/** Book + profile actions. */
function wpddbp_doctors_actions_html( $d, $attrs ) {
	$out = '';
	if ( wpddbp_doctors_show( $attrs, 'showBookButton' ) ) {
		$out .= wpddbp_render_doctor_book_button( $d['id'], array( 'label' => $attrs['bookButtonText'] ?? __( 'Book now', 'doc-booker-pro' ), 'class' => 'wpddbp-doctors-book' ) );
	}
	if ( wpddbp_doctors_show( $attrs, 'showProfileLink' ) ) {
		$out .= '<a class="wpddbp-doctors-profile" href="' . esc_url( $d['permalink'] ) . '">' . esc_html( $attrs['profileLinkText'] ?? __( 'View profile', 'doc-booker-pro' ) ) . '</a>';
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Per-layout card renderers (list / mini / feature / card / hover-reveal).
 * $index is the absolute position across pages so the masonry archetypes
 * (card) and tile variants/aspect-ratios (hover-reveal) cycle continuously
 * through load-more.
 * ------------------------------------------------------------------------- */

function wpddbp_doctors_render_card( $d, $attrs, $index = 0 ) {
	switch ( $attrs['layoutType'] ?? 'list' ) {
		case 'mini':
			return wpddbp_doctors_card_mini( $d, $attrs );
		case 'feature':
			return wpddbp_doctors_card_feature( $d, $attrs );
		case 'card':
			return wpddbp_doctors_card_card( $d, $attrs, $index );
		case 'hover-reveal':
			return wpddbp_doctors_card_hover_reveal( $d, $attrs, $index );
		case 'list':
		default:
			return wpddbp_doctors_card_list( $d, $attrs );
	}
}

/** Split a doctor name into a lead (all but the last word) + the last word. */
function wpddbp_doctors_name_parts( $name ) {
	$name  = trim( wp_strip_all_tags( (string) $name ) );
	$parts = preg_split( '/\s+/', $name );
	if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
		return array( 'lead' => '', 'last' => $name, 'full' => $name );
	}
	$last = array_pop( $parts );
	return array( 'lead' => implode( ' ', $parts ), 'last' => $last, 'full' => $name );
}

/** Map a department slug to a card accent-dot modifier class (cosmetic only). */
function wpddbp_doctors_dept_accent( $slug ) {
	$slug = strtolower( (string) $slug );
	$map  = array(
		'cardiology'            => 'cardio',
		'pediatrics'            => 'peds',
		'paediatrics'           => 'peds',
		'dermatology'           => 'derm',
		'orthopedics'           => 'ortho',
		'orthopaedics'          => 'ortho',
		'neurology'             => 'neuro',
		'family-medicine'       => 'family',
		'family'                => 'family',
		'obstetrics-gynecology' => 'plum',
		'ent'                   => 'ent',
	);
	return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
}

/** Filled-star SVG used by the masonry layouts' inline rating chips. */
function wpddbp_doctors_star_svg() {
	return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 7 7.5.7-5.7 5L18.5 22 12 18.3 5.5 22l1.7-7.3-5.7-5L9 9z"/></svg>';
}

/** Trim a rating float to its shortest decimal form (4.90 -> 4.9, 5.00 -> 5). */
function wpddbp_doctors_rating_value( $rating ) {
	return rtrim( rtrim( number_format( (float) $rating, 2, '.', '' ), '0' ), '.' );
}

function wpddbp_doctors_card_list( $d, $attrs ) {
	$img = ( wpddbp_doctors_show( $attrs, 'showImage' ) )
		? '<div class="wpddbp-doctors-imgcell">' . ( $d['image'] ? '<img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">' : '<span class="wpddbp-doctors-imgph" aria-hidden="true"></span>' ) . '</div>'
		: '';

	$role_bits = array();
	if ( wpddbp_doctors_show( $attrs, 'showDesignation' ) && '' !== $d['designation'] ) {
		$role_bits[] = esc_html( $d['designation'] );
	}
	if ( '' !== $d['degree'] ) {
		$role_bits[] = esc_html( $d['degree'] );
	}
	if ( '' !== $d['experience'] ) {
		$role_bits[] = esc_html( $d['experience'] ) . ' ' . esc_html__( 'yrs', 'doc-booker-pro' );
	}
	$role = ! empty( $role_bits ) ? '<p class="wpddbp-doctors-role">' . implode( ' <span class="wpddbp-doctors-dot"></span> ', $role_bits ) . '</p>' : '';

	$dept = ( wpddbp_doctors_show( $attrs, 'showDept' ) && '' !== $d['dept_name'] ) ? '<span class="wpddbp-doctors-dept">' . esc_html( $d['dept_name'] ) . '</span>' : '';
	$name = wpddbp_doctors_show( $attrs, 'showName' ) ? '<h3 class="wpddbp-doctors-name">' . esc_html( $d['name'] ) . '</h3>' : '';
	$bio  = ( wpddbp_doctors_show( $attrs, 'showBio' ) && '' !== $d['bio'] ) ? '<p class="wpddbp-doctors-bio">' . esc_html( $d['bio'] ) . '</p>' : '';

	$meta_bits = array();
	if ( '' !== $d['workplace'] ) {
		$meta_bits[] = '<span class="wpddbp-doctors-meta-item"><b>' . esc_html( $d['workplace'] ) . '</b></span>';
	}
	if ( ! empty( $d['languages'] ) ) {
		$meta_bits[] = '<span class="wpddbp-doctors-meta-item">' . esc_html( implode( ' · ', array_map( 'sanitize_text_field', $d['languages'] ) ) ) . '</span>';
	}
	$socials = wpddbp_doctors_socials_html( $d, $attrs );
	$meta    = ( ! empty( $meta_bits ) || '' !== $socials ) ? '<div class="wpddbp-doctors-meta">' . implode( '<span class="wpddbp-doctors-sep"></span>', $meta_bits ) . $socials . '</div>' : '';

	$fee     = wpddbp_doctors_fee_html( $d, $attrs );
	$actions = wpddbp_doctors_actions_html( $d, $attrs );
	$action_cell = ( '' !== $fee || '' !== $actions ) ? '<div class="wpddbp-doctors-actioncell">' . $fee . '<div class="wpddbp-doctors-btnstack">' . $actions . '</div></div>' : '';

	return '<article class="wpddbp-doctors-card" data-dept="' . esc_attr( $d['dept_slug'] ) . '">'
		. $img
		. '<div class="wpddbp-doctors-bodycell">' . $dept . $name . $role . $bio . $meta . '</div>'
		. $action_cell
		. '</article>';
}

function wpddbp_doctors_card_mini( $d, $attrs ) {
	$av = ( wpddbp_doctors_show( $attrs, 'showImage' ) )
		? '<span class="wpddbp-doctors-av">' . ( $d['image'] ? '<img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">' : '' ) . '</span>'
		: '';
	$name = wpddbp_doctors_show( $attrs, 'showName' ) ? '<span class="wpddbp-doctors-name">' . esc_html( $d['name'] ) . '</span>' : '';
	$role_bits = array();
	if ( wpddbp_doctors_show( $attrs, 'showDept' ) && '' !== $d['dept_name'] ) {
		$role_bits[] = '<b>' . esc_html( $d['dept_name'] ) . '</b>';
	}
	if ( '' !== $d['degree'] ) {
		$role_bits[] = esc_html( $d['degree'] );
	}
	$role = ! empty( $role_bits ) ? '<span class="wpddbp-doctors-role">' . implode( ' · ', $role_bits ) . '</span>' : '';
	return '<a class="wpddbp-doctors-card" href="' . esc_url( $d['permalink'] ) . '" data-dept="' . esc_attr( $d['dept_slug'] ) . '" aria-label="' . esc_attr( $d['name'] ) . '">'
		. $av
		. '<span class="wpddbp-doctors-who">' . $name . $role . '</span>'
		. '</a>';
}

function wpddbp_doctors_card_feature( $d, $attrs ) {
	$img = '';
	if ( wpddbp_doctors_show( $attrs, 'showImage' ) ) {
		$years = ( '' !== $d['experience'] ) ? '<div class="wpddbp-doctors-years"><b>' . esc_html( $d['experience'] ) . '</b><small>' . esc_html__( 'Years', 'doc-booker-pro' ) . '</small></div>' : '';
		$img   = '<div class="wpddbp-doctors-img">' . ( $d['image'] ? '<img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">' : '<span class="wpddbp-doctors-imgph" aria-hidden="true"></span>' ) . $years . '</div>';
	}

	$dept = ( wpddbp_doctors_show( $attrs, 'showDept' ) && '' !== $d['dept_name'] ) ? '<div class="wpddbp-doctors-dept">' . esc_html( $d['dept_name'] ) . '</div>' : '';
	$name = wpddbp_doctors_show( $attrs, 'showName' ) ? '<h3 class="wpddbp-doctors-name">' . esc_html( $d['name'] ) . '</h3>' : '';
	$role = ( wpddbp_doctors_show( $attrs, 'showDesignation' ) && '' !== $d['designation'] ) ? '<p class="wpddbp-doctors-role">' . esc_html( $d['designation'] ) . '</p>' : '';
	$bio  = ( wpddbp_doctors_show( $attrs, 'showBio' ) && '' !== $d['bio'] ) ? '<p class="wpddbp-doctors-bio">' . esc_html( $d['bio'] ) . '</p>' : '';

	$quals = '';
	$qual_rows = array();
	if ( '' !== $d['alma_mater'] ) {
		$qual_rows[] = '<div class="wpddbp-doctors-qual"><span>' . esc_html__( 'Medical School', 'doc-booker-pro' ) . '</span><b>' . esc_html( $d['alma_mater'] ) . '</b></div>';
	}
	if ( ! empty( $d['credentials'] ) ) {
		$qual_rows[] = '<div class="wpddbp-doctors-qual"><span>' . esc_html__( 'Credentials', 'doc-booker-pro' ) . '</span><b>' . esc_html( implode( ' · ', array_map( 'sanitize_text_field', $d['credentials'] ) ) ) . '</b></div>';
	}
	if ( ! empty( $d['languages'] ) ) {
		$qual_rows[] = '<div class="wpddbp-doctors-qual"><span>' . esc_html__( 'Languages', 'doc-booker-pro' ) . '</span><b>' . esc_html( implode( ', ', array_map( 'sanitize_text_field', $d['languages'] ) ) ) . '</b></div>';
	}
	if ( '' !== $d['workplace'] ) {
		$qual_rows[] = '<div class="wpddbp-doctors-qual"><span>' . esc_html__( 'Practice', 'doc-booker-pro' ) . '</span><b>' . esc_html( $d['workplace'] ) . '</b></div>';
	}
	if ( ! empty( $qual_rows ) ) {
		$quals = '<div class="wpddbp-doctors-quals">' . implode( '', $qual_rows ) . '</div>';
	}

	$socials = wpddbp_doctors_socials_html( $d, $attrs );
	$fee     = wpddbp_doctors_fee_html( $d, $attrs );
	$actions = wpddbp_doctors_actions_html( $d, $attrs );
	$action_row = ( '' !== $actions || '' !== $fee ) ? '<div class="wpddbp-doctors-actions">' . $actions . $fee . '</div>' : '';

	return '<article class="wpddbp-doctors-card" data-dept="' . esc_attr( $d['dept_slug'] ) . '">'
		. $img
		. '<div class="wpddbp-doctors-body">' . $dept . $name . $role . $bio . $quals . $socials . $action_row . '</div>'
		. '</article>';
}

/**
 * Masonry "card" layout. Six editorial archetypes (hero / tall / split / frost /
 * band / ring) cycle by absolute index. Every visible value is real doctor meta —
 * no fabricated availability slots. Atoms reuse the semantic `wpddbp-doctors-*`
 * classes so the StylesPanel per-instance CSS controls apply across all archetypes.
 *
 * @param array $d      Normalized doctor data.
 * @param array $attrs  Block attributes.
 * @param int   $index  Absolute position (drives the 6-archetype cycle).
 * @return string
 */
function wpddbp_doctors_card_card( $d, $attrs, $index = 0 ) {
	$types  = array( 'hero', 'tall', 'split', 'frost', 'band', 'ring' );
	$type   = $types[ $index % 6 ];
	$np     = wpddbp_doctors_name_parts( $d['name'] );
	$accent = wpddbp_doctors_dept_accent( $d['dept_slug'] );
	$alt    = esc_attr( $d['name'] );
	$has_img = wpddbp_doctors_show( $attrs, 'showImage' ) && '' !== $d['image'];
	$img_tag = $has_img ? '<img src="' . esc_url( $d['image'] ) . '" alt="' . $alt . '" loading="lazy">' : '';

	// shared atoms (semantic classes -> StylesPanel CSS applies everywhere)
	$chip = ( wpddbp_doctors_show( $attrs, 'showDept' ) && '' !== $d['dept_name'] )
		? '<span class="wpddbp-doctors-dept' . ( $accent ? ' is-' . esc_attr( $accent ) : '' ) . '">' . esc_html( $d['dept_name'] ) . '</span>'
		: '';
	$rating  = wpddbp_doctors_rating_html( $d, $attrs );
	$fee     = wpddbp_doctors_fee_html( $d, $attrs );
	$bio     = ( wpddbp_doctors_show( $attrs, 'showBio' ) && '' !== $d['bio'] ) ? '<p class="wpddbp-doctors-bio">' . esc_html( $d['bio'] ) . '</p>' : '';
	$socials = wpddbp_doctors_socials_html( $d, $attrs );
	$book    = wpddbp_doctors_show( $attrs, 'showBookButton' )
		? wpddbp_render_doctor_book_button( $d['id'], array( 'label' => $attrs['bookButtonText'] ?? __( 'Book now', 'doc-booker-pro' ), 'class' => 'wpddbp-doctors-book' ) )
		: '';
	$profile = wpddbp_doctors_show( $attrs, 'showProfileLink' )
		? '<a class="wpddbp-doctors-profile" href="' . esc_url( $d['permalink'] ) . '">' . esc_html( $attrs['profileLinkText'] ?? __( 'View profile', 'doc-booker-pro' ) ) . '</a>'
		: '';
	$name_html = wpddbp_doctors_show( $attrs, 'showName' )
		? '<h3 class="wpddbp-doctors-name">' . ( '' !== $np['lead'] ? esc_html( $np['lead'] ) . ' ' : '' ) . '<em>' . esc_html( $np['last'] ) . '</em></h3>'
		: '';
	$role_txt = ( wpddbp_doctors_show( $attrs, 'showDesignation' ) && '' !== $d['designation'] ) ? $d['designation'] : '';
	$role     = ( '' !== $role_txt ) ? '<p class="wpddbp-doctors-role">' . esc_html( $role_txt ) . '</p>' : '';
	$ribbon   = ( '' !== $d['featured_label'] ) ? '<span class="wpddbp-doctors-ribbon">' . esc_html( $d['featured_label'] ) . '</span>' : '';
	$cardlink = '<a class="wpddbp-doctors-cardlink" href="' . esc_url( $d['permalink'] ) . '" tabindex="-1" aria-hidden="true">' . esc_html( $d['name'] ) . '</a>';

	// real stat tiles (experience / patients / languages) — render only what exists
	$stats = array();
	if ( '' !== $d['experience'] ) {
		$stats[] = '<div class="wpddbp-doctors-stat"><span>' . esc_html__( 'Experience', 'doc-booker-pro' ) . '</span><b>' . esc_html( $d['experience'] ) . ' ' . esc_html__( 'yrs', 'doc-booker-pro' ) . '</b></div>';
	}
	if ( '' !== $d['patient_count'] ) {
		$stats[] = '<div class="wpddbp-doctors-stat"><span>' . esc_html__( 'Patients', 'doc-booker-pro' ) . '</span><b>' . esc_html( $d['patient_count'] ) . '</b></div>';
	}
	if ( ! empty( $d['languages'] ) ) {
		$stats[] = '<div class="wpddbp-doctors-stat"><span>' . esc_html__( 'Languages', 'doc-booker-pro' ) . '</span><b>' . esc_html( implode( ' · ', array_map( 'sanitize_text_field', array_slice( $d['languages'], 0, 3 ) ) ) ) . '</b></div>';
	}
	$stats_html = ! empty( $stats ) ? '<div class="wpddbp-doctors-stats">' . implode( '', $stats ) . '</div>' : '';

	switch ( $type ) {
		case 'tall':
			$role_line = ( '' !== $role_txt || '' !== $d['alma_mater'] ) ? '<div class="wpddbp-doctors-roleline">' . esc_html( trim( $role_txt . ( '' !== $d['alma_mater'] ? ' · ' . $d['alma_mater'] : '' ), ' ·' ) ) . '</div>' : '';
			$inner = '<div class="wpddbp-doctors-stage">' . $img_tag . '</div>'
				. ( '' !== $chip ? '<span class="wpddbp-doctors-chipfloat">' . $chip . '</span>' : '' )
				. ( '' !== $fee ? '<span class="wpddbp-doctors-quickfee">' . $fee . '</span>' : '' )
				. '<div class="wpddbp-doctors-info">'
				. $role_line . $name_html
				. ( '' !== $bio ? '<div class="wpddbp-doctors-reveal">' . $bio . '</div>' : '' )
				. '<div class="wpddbp-doctors-actionrow">' . $book . $rating . '</div>'
				. '</div>';
			break;

		case 'split':
			$footer = '';
			if ( '' !== $socials || '' !== $profile || '' !== $book ) {
				$footer = '<div class="wpddbp-doctors-footrow"><div class="wpddbp-doctors-leftcluster">' . $socials . $profile . '</div>' . $book . '</div>';
			}
			$inner = '<div class="wpddbp-doctors-well">' . $img_tag . ( '' !== $chip ? '<span class="wpddbp-doctors-chipfloat">' . $chip . '</span>' : '' ) . '</div>'
				. '<div class="wpddbp-doctors-content">'
				. ( ( '' !== $rating || '' !== $fee ) ? '<div class="wpddbp-doctors-topline">' . $rating . $fee . '</div>' : '' )
				. $name_html . $role . $bio . $footer
				. '</div>';
			break;

		case 'frost':
			$data_row = '';
			$cells    = array();
			if ( '' !== $d['experience'] ) {
				$cells[] = '<div class="wpddbp-doctors-cell"><span>' . esc_html__( 'Exp', 'doc-booker-pro' ) . '</span><b>' . esc_html( $d['experience'] ) . ' ' . esc_html__( 'yrs', 'doc-booker-pro' ) . '</b></div>';
			}
			if ( wpddbp_doctors_show( $attrs, 'showRating' ) && $d['rating'] > 0 ) {
				$cells[] = '<div class="wpddbp-doctors-cell"><span>' . esc_html__( 'Rating', 'doc-booker-pro' ) . '</span><b>' . esc_html( wpddbp_doctors_rating_value( $d['rating'] ) ) . '</b></div>';
			}
			if ( wpddbp_doctors_show( $attrs, 'showFee' ) && $d['fee'] > 0 ) {
				$cells[] = '<div class="wpddbp-doctors-cell"><span>' . esc_html__( 'From', 'doc-booker-pro' ) . '</span><b>' . esc_html( wpddbp_format_fee( $d['fee'] ) ) . '</b></div>';
			}
			if ( ! empty( $cells ) ) {
				$data_row = '<div class="wpddbp-doctors-datarow">' . implode( '', $cells ) . '</div>';
			}
			$inner = '<div class="wpddbp-doctors-bgimg">' . $img_tag . '</div>'
				. ( '' !== $chip ? '<span class="wpddbp-doctors-chipfloat">' . $chip . '</span>' : '' )
				. '<div class="wpddbp-doctors-frostcard">'
				. $name_html . $role . $data_row
				. ( ( '' !== $book || '' !== $profile ) ? '<div class="wpddbp-doctors-actionrow">' . $book . $profile . '</div>' : '' )
				. '</div>';
			break;

		case 'band':
			$pill = ( '' !== $d['experience'] ) ? '<span class="wpddbp-doctors-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2v20M5 12l7-7 7 7"/></svg>' . esc_html( $d['experience'] ) . ' ' . esc_html__( 'yrs', 'doc-booker-pro' ) . '</span>' : '';
			$strip = '<div class="wpddbp-doctors-strip">' . $img_tag . ( '' !== $pill ? '<div class="wpddbp-doctors-stripover">' . $pill . '</div>' : '' ) . '</div>';
			$inner = '<div class="wpddbp-doctors-topband">' . $chip . $rating . '</div>'
				. $name_html . $role . $strip
				. '<div class="wpddbp-doctors-footer">' . $fee . $book . '</div>';
			break;

		case 'ring':
			$quote = ( '' !== $d['quote'] ) ? '<p class="wpddbp-doctors-quote">' . esc_html( $d['quote'] ) . '</p>' : ( '' !== $bio ? $bio : '' );
			$meta_bits = array();
			if ( '' !== $d['experience'] ) {
				$meta_bits[] = '<span class="wpddbp-doctors-metaitem">' . esc_html( $d['experience'] ) . ' ' . esc_html__( 'yrs', 'doc-booker-pro' ) . ( '' !== $d['alma_mater'] ? ' · ' . esc_html( $d['alma_mater'] ) : '' ) . '</span>';
			} elseif ( '' !== $d['alma_mater'] ) {
				$meta_bits[] = '<span class="wpddbp-doctors-metaitem">' . esc_html( $d['alma_mater'] ) . '</span>';
			}
			$meta_row = ! empty( $meta_bits ) ? '<div class="wpddbp-doctors-metarow">' . implode( '<span class="wpddbp-doctors-dotsep"></span>', $meta_bits ) . '</div>' : '';
			$inner = '<div class="wpddbp-doctors-ringportrait"><span class="wpddbp-doctors-ringinside">' . $img_tag . '</span></div>'
				. '<div class="wpddbp-doctors-middle">'
				. ( ( '' !== $chip || '' !== $rating ) ? '<div class="wpddbp-doctors-ringtop">' . $chip . $rating . '</div>' : '' )
				. $name_html . $quote . $meta_row
				. '</div>'
				. ( ( '' !== $fee || '' !== $book ) ? '<div class="wpddbp-doctors-ringfoot">' . $fee . $book . '</div>' : '' );
			break;

		case 'hero':
		default:
			$portrait = '<div class="wpddbp-doctors-portrait">' . $img_tag
				. ( '' !== $chip ? '<span class="wpddbp-doctors-chipfloat">' . $chip . '</span>' : '' )
				. ( '' !== $rating ? '<div class="wpddbp-doctors-glass">' . $rating . ( '' !== $d['patient_count'] ? '<span class="wpddbp-doctors-glassstat">' . esc_html( $d['patient_count'] ) . ' ' . esc_html__( 'patients', 'doc-booker-pro' ) . '</span>' : '' ) . '</div>' : '' )
				. '</div>';
			$cta = ( '' !== $book || '' !== $profile || '' !== $fee ) ? '<div class="wpddbp-doctors-cta">' . $book . $profile . $fee . '</div>' : '';
			$inner = $portrait
				. '<div class="wpddbp-doctors-herotext">'
				. ( '' !== $ribbon ? '<div class="wpddbp-doctors-herotop">' . $ribbon . '</div>' : '' )
				. $name_html . $role . $bio . $stats_html . $cta
				. '</div>';
			break;
	}

	return '<article class="wpddbp-doctors-card wpddbp-card--' . esc_attr( $type ) . '" data-dept="' . esc_attr( $d['dept_slug'] ) . '">' . $inner . $cardlink . '</article>';
}

/**
 * Masonry "hover-reveal" layout. Split-colorblock tiles; the photo clips back and
 * a colored panel slides in on hover / focus / tap revealing the full profile.
 * Eight aspect ratios + three panel variants (primary / alt-sage / warm-paper)
 * cycle by absolute index. All panel content is real meta; no fabricated slots.
 *
 * @param array $d      Normalized doctor data.
 * @param array $attrs  Block attributes.
 * @param int   $index  Absolute position (drives aspect-ratio + variant cycle).
 * @return string
 */
function wpddbp_doctors_card_hover_reveal( $d, $attrs, $index = 0 ) {
	$aspects  = array( 't1', 't2', 't3', 't4', 't5', 't6', 't7', 't8' );
	$variants = array( '', 'alt', '', 'alt', 'warm', '', 'alt', '' );
	$i8       = $index % 8;
	$variant  = $variants[ $i8 ];
	$np       = wpddbp_doctors_name_parts( $d['name'] );
	$alt      = esc_attr( $d['name'] );

	$tile_class = 'wpddbp-doctors-tile wpddbp-tile--' . $aspects[ $i8 ] . ( '' !== $variant ? ' is-' . $variant : '' );

	$photo = ( wpddbp_doctors_show( $attrs, 'showImage' ) && '' !== $d['image'] )
		? '<div class="wpddbp-doctors-photo" style="background-image:url(' . esc_url( $d['image'] ) . ')"></div>'
		: '<div class="wpddbp-doctors-photo wpddbp-doctors-photo--ph"></div>';

	$chip = ( wpddbp_doctors_show( $attrs, 'showDept' ) && '' !== $d['dept_name'] )
		? '<span class="wpddbp-doctors-dept">' . esc_html( $d['dept_name'] ) . '</span>' : '';

	$rest_rating = ( wpddbp_doctors_show( $attrs, 'showRating' ) && $d['rating'] > 0 )
		? '<span class="wpddbp-doctors-restrate">' . wpddbp_doctors_star_svg() . esc_html( wpddbp_doctors_rating_value( $d['rating'] ) ) . '</span>' : '';
	$rest_role = ( wpddbp_doctors_show( $attrs, 'showDesignation' ) && '' !== $d['designation'] ) ? esc_html( $d['designation'] ) : '';
	$restcap   = '<div class="wpddbp-doctors-restcap">'
		. ( wpddbp_doctors_show( $attrs, 'showName' ) ? '<p class="wpddbp-doctors-restname">' . ( '' !== $np['lead'] ? esc_html( $np['lead'] ) . ' ' : '' ) . '<em>' . esc_html( $np['last'] ) . '</em></p>' : '' )
		. ( ( '' !== $rest_role || '' !== $rest_rating ) ? '<p class="wpddbp-doctors-restrole">' . $rest_role . $rest_rating . '</p>' : '' )
		. '</div>';

	$name_html = wpddbp_doctors_show( $attrs, 'showName' )
		? '<h3 class="wpddbp-doctors-name">' . ( '' !== $np['lead'] ? esc_html( $np['lead'] ) . ' ' : '' ) . '<em>' . esc_html( $np['last'] ) . '</em></h3>' : '';

	$badge = '';
	if ( '' !== $d['degree'] ) {
		$badge = $d['degree'];
	} elseif ( ! empty( $d['credentials'] ) ) {
		$badge = $d['credentials'][0];
	}
	$creds_text = '';
	if ( wpddbp_doctors_show( $attrs, 'showDesignation' ) && '' !== $d['designation'] ) {
		$creds_text = $d['designation'];
	} elseif ( '' !== $d['workplace'] ) {
		$creds_text = $d['workplace'];
	} elseif ( '' !== $d['alma_mater'] ) {
		$creds_text = $d['alma_mater'];
	}
	$creds = ( '' !== $badge || '' !== $creds_text )
		? '<p class="wpddbp-doctors-creds">' . ( '' !== $badge ? '<b>' . esc_html( $badge ) . '</b>' : '' ) . esc_html( $creds_text ) . '</p>' : '';

	$quote_txt = ( '' !== $d['quote'] ) ? $d['quote'] : $d['bio'];
	$quote     = ( wpddbp_doctors_show( $attrs, 'showBio' ) && '' !== $quote_txt )
		? '<p class="wpddbp-doctors-quote">' . esc_html( $quote_txt ) . '</p>' : '';

	$stat_items = '';
	if ( '' !== $d['experience'] ) {
		$stat_items .= '<span class="wpddbp-doctors-sitem"><b>' . esc_html( $d['experience'] ) . '</b>' . esc_html__( 'yrs', 'doc-booker-pro' ) . '</span>';
	}
	if ( wpddbp_doctors_show( $attrs, 'showRating' ) && $d['rating'] > 0 ) {
		if ( '' !== $stat_items ) {
			$stat_items .= '<span class="wpddbp-doctors-ssep"></span>';
		}
		$stat_items .= '<span class="wpddbp-doctors-sitem"><span class="wpddbp-doctors-sstar">' . wpddbp_doctors_star_svg() . '</span><b>' . esc_html( wpddbp_doctors_rating_value( $d['rating'] ) ) . '</b></span>';
	}
	if ( '' !== $d['patient_count'] ) {
		if ( '' !== $stat_items ) {
			$stat_items .= '<span class="wpddbp-doctors-ssep"></span>';
		}
		$stat_items .= '<span class="wpddbp-doctors-sitem"><b>' . esc_html( $d['patient_count'] ) . '</b>' . esc_html__( 'patients', 'doc-booker-pro' ) . '</span>';
	}
	$stats = ( '' !== $stat_items ) ? '<div class="wpddbp-doctors-stats">' . $stat_items . '</div>' : '';

	$book = wpddbp_doctors_show( $attrs, 'showBookButton' )
		? wpddbp_render_doctor_book_button( $d['id'], array( 'label' => $attrs['bookButtonText'] ?? __( 'Book now', 'doc-booker-pro' ), 'class' => 'wpddbp-doctors-book' ) ) : '';
	$profile = wpddbp_doctors_show( $attrs, 'showProfileLink' )
		? '<a class="wpddbp-doctors-ghost" href="' . esc_url( $d['permalink'] ) . '">' . esc_html( $attrs['profileLinkText'] ?? __( 'View profile', 'doc-booker-pro' ) ) . '</a>' : '';
	$actions = ( '' !== $book || '' !== $profile ) ? '<div class="wpddbp-doctors-actions">' . $book . $profile . '</div>' : '';

	$panel = '<div class="wpddbp-doctors-panel">'
		. '<span class="wpddbp-doctors-qmark" aria-hidden="true">&ldquo;</span>'
		. '<div>' . $name_html . $creds . $quote . '</div>'
		. '<div>' . $stats . $actions . '</div>'
		. '</div>';

	$cue    = '<span class="wpddbp-doctors-cue" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M9 7h8v8"/></svg></span>';
	$toggle = '<button type="button" class="wpddbp-doctors-tiletoggle" aria-label="' . esc_attr( sprintf( __( 'Reveal %s details', 'doc-booker-pro' ), $d['name'] ) ) . '">' . esc_html__( 'Reveal', 'doc-booker-pro' ) . '</button>';

	return '<article class="' . esc_attr( $tile_class ) . '" data-dept="' . esc_attr( $d['dept_slug'] ) . '" tabindex="0">'
		. $photo . $chip . $cue . $restcap
		. '<div class="wpddbp-doctors-photoframe" aria-hidden="true"></div>'
		. $panel . $toggle
		. '</article>';
}

/**
 * Render the full grid for a doctors block. Used by render.php (initial) + the
 * REST /doctors/load handler (AJAX). Returns the JS-friendly payload.
 *
 * @param array $attrs  Block attributes.
 * @param array $filter §11a filter.
 * @param int   $page   1-based page.
 * @return array { html, found, max_pages, page, has_more }
 */
function wpddbp_doctors_render_grid( $attrs, $filter = array(), $page = 1 ) {
	$q    = wpddbp_doctors_query( $attrs, $filter, $page );
	$html = '';
	if ( $q->have_posts() ) {
		$per_page = max( 1, (int) ( $attrs['perPage'] ?? 9 ) );
		$index    = ( max( 1, (int) $page ) - 1 ) * $per_page;
		while ( $q->have_posts() ) {
			$q->the_post();
			$data  = wpddbp_doctors_card_data( get_the_ID(), $attrs );
			$html .= wpddbp_doctors_render_card( $data, $attrs, $index );
			$index++;
		}
		wp_reset_postdata();
	} else {
		$html = '<p class="wpddbp-doctors-empty">' . esc_html__( 'No doctors found.', 'doc-booker-pro' ) . '</p>';
	}

	return array(
		'html'      => $html,
		'found'     => (int) $q->found_posts,
		'max_pages' => (int) $q->max_num_pages,
		'page'      => (int) $page,
		'has_more'  => ( (int) $page < (int) $q->max_num_pages ),
	);
}

/**
 * Render the §11a filter bar markup (clinic + department selects + reset + spinner
 * + a11y status). Returns '' when both filters are disabled or have no options.
 *
 * @param array  $attrs    Block attributes.
 * @param string $block_id The block's unique id (for label/select id scoping).
 * @return string
 */
function wpddbp_doctors_render_filter_bar( $attrs, $block_id ) {
	$show_clinic = wpddbp_doctors_show( $attrs, 'showClinicFilter' );
	$show_dept   = wpddbp_doctors_show( $attrs, 'showDepartmentFilter' );

	$clinic_html = '';
	if ( $show_clinic ) {
		$clinics = get_posts(
			array(
				'post_type'      => 'wpddb_clinic',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $clinics ) ) {
			$opts = '<option value="0">' . esc_html( $attrs['allClinicsLabel'] ?? __( 'All clinics', 'doc-booker-pro' ) ) . '</option>';
			foreach ( $clinics as $cid ) {
				$opts .= '<option value="' . (int) $cid . '">' . esc_html( get_the_title( $cid ) ) . '</option>';
			}
			$clinic_html = '<label class="wpddbp-filter-bar__label" for="' . esc_attr( $block_id ) . '-clinic">' . esc_html( $attrs['clinicPlaceholder'] ?? __( 'Clinic', 'doc-booker-pro' ) ) . '</label>'
				. '<select class="wpddbp-filter-bar__select" data-filter="clinic" id="' . esc_attr( $block_id ) . '-clinic">' . $opts . '</select>';
		}
	}

	$dept_html = '';
	if ( $show_dept ) {
		$terms = get_terms( array( 'taxonomy' => 'wpddb_doctor_department', 'hide_empty' => true ) );
		$opts  = '<option value="0">' . esc_html( $attrs['allDepartmentsLabel'] ?? __( 'All departments', 'doc-booker-pro' ) ) . '</option>';
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$opts .= '<option value="' . (int) $t->term_id . '">' . esc_html( $t->name ) . '</option>';
			}
		}
		$dept_html = '<label class="wpddbp-filter-bar__label" for="' . esc_attr( $block_id ) . '-dept">' . esc_html( $attrs['departmentPlaceholder'] ?? __( 'Department', 'doc-booker-pro' ) ) . '</label>'
			. '<select class="wpddbp-filter-bar__select" data-filter="department" id="' . esc_attr( $block_id ) . '-dept">' . $opts . '</select>';
	}

	if ( '' === $clinic_html && '' === $dept_html ) {
		return '';
	}

	$reset = wpddbp_doctors_show( $attrs, 'showResetLink' )
		? '<button type="button" class="wpddbp-filter-bar__reset" aria-label="' . esc_attr__( 'Reset clinic and department filters', 'doc-booker-pro' ) . '" hidden>' . esc_html( $attrs['resetLabel'] ?? __( 'Reset', 'doc-booker-pro' ) ) . '</button>'
		: '';
	$persist = ! empty( $attrs['persistFilterInUrl'] ) ? '1' : '0';

	return '<div class="wpddbp-filter-bar" data-block-id="' . esc_attr( $block_id ) . '" data-block="doctors" data-persist-url="' . esc_attr( $persist ) . '">'
		. $clinic_html . $dept_html . $reset
		. '<span class="wpddbp-filter-bar__spinner" hidden aria-hidden="true"></span>'
		. '<span class="wpddbp-filter-bar__status" aria-live="polite"></span>'
		. '</div>';
}
