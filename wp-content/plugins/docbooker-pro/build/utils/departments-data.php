<?php
/**
 * Departments block — shared data + term-meta resolver + 3 layout renderers (Tier B, Phase 3).
 *
 * Renders the `wpddb_doctor_department` taxonomy as a directory in 3 layouts
 * (grid / pill-cloud / icon-cards). Reads the Pro term meta `wpddb_doctor_department_icon`
 * + `wpddb_doctor_department_accent_color` (shipped by DocBooker Pro's DepartmentTermMeta
 * controller). Standalone rule (architecture §3): the icon library + palette + resolver are
 * DUPLICATED here (namespaced `wpddbp_dept_*`) — never imported from the Pro controller —
 * but read the SAME meta keys, so a term's medallion matches everywhere it appears.
 *
 * Every visible value is real term data; empty meta resolves at read time to a palette
 * colour (term_id % 8) + a name-based smart icon, so the block never looks broken.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Term-meta resolver (standalone duplicate of Pro's DepartmentTermMeta — §3)
 * ========================================================================= */

/** Palette rotation for terms with no author-set accent (term_id % 8). */
function wpddbp_dept_palette() {
	return array( '#c54a52', '#3f7a64', '#a26a3a', '#0a4b78', '#6c3a5f', '#14b8a6', '#b88a2c', '#c47d6a' );
}

/** Wrap inner SVG markup in the shared 24×24 stroke <svg>. */
function wpddbp_dept_wrap_svg( $inner ) {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** Built-in medical icon library: name → inner SVG markup. */
function wpddbp_dept_icon_library() {
	return array(
		'heart-pulse' => '<path d="M20.4 9.4a4.3 4.3 0 0 0-7.6-2.7A4.3 4.3 0 0 0 5.2 9.4c0 .5.05.9.15 1.3"/><path d="M4.6 11.6c1 2.6 3.6 5 7.4 7.7 1.7-1.2 3.2-2.3 4.4-3.4"/><path d="M14 12h2.2l1-1.6 1.6 3.1 1-1.9H22"/>',
		'heart'       => '<path d="M12 20s-7-4.35-7-9.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 7 3.5C19 15.65 12 20 12 20z"/>',
		'brain'       => '<path d="M9.5 4.6A2.6 2.6 0 0 0 7 7.2 2.6 2.6 0 0 0 5.6 12 3 3 0 0 0 9 16.8M14.5 4.6A2.6 2.6 0 0 1 17 7.2 2.6 2.6 0 0 1 18.4 12 3 3 0 0 1 15 16.8"/><path d="M12 4.4v14"/>',
		'bone'        => '<path d="M7.5 16.5 16.5 7.5"/><path d="M6.7 6.2a1.9 1.9 0 1 0 1.5 1.7 1.9 1.9 0 1 0-1.5-1.7z"/><path d="M17.3 17.8a1.9 1.9 0 1 0-1.5-1.7 1.9 1.9 0 1 0 1.5 1.7z"/>',
		'eye'         => '<path d="M2.6 12S6 5.8 12 5.8 21.4 12 21.4 12 18 18.2 12 18.2 2.6 12 2.6 12z"/><circle cx="12" cy="12" r="2.6"/>',
		'tooth'       => '<path d="M8.2 3.6C6.2 3.6 4.7 5 4.7 7.4c0 1.4.5 2.9.8 4.8.4 2.2.6 6 1.8 6 1 0 1-2.8 1.5-4.4.3-.9.6-1.4 1.2-1.4s.9.5 1.2 1.4c.5 1.6.5 4.4 1.5 4.4 1.2 0 1.4-3.8 1.8-6 .3-1.9.8-3.4.8-4.8 0-2.4-1.5-3.8-3.5-3.8-1.2 0-1.7.6-2.8.6s-1.6-.6-2.8-.6z"/>',
		'lung'        => '<path d="M12 4v6"/><path d="M9.6 8.4c0 1.7-1.6 2.3-2.5 3.9-.8 1.4-1.1 3.3-1.1 5 0 1.2 1.4 1.6 2.3.8.9-.8 1.3-1.8 1.3-3.4V9.6c0-.8-.2-1.2-1.2-1.2z"/><path d="M14.4 8.4c0 1.7 1.6 2.3 2.5 3.9.8 1.4 1.1 3.3 1.1 5 0 1.2-1.4 1.6-2.3.8-.9-.8-1.3-1.8-1.3-3.4V9.6c0-.8.2-1.2 1.2-1.2z"/>',
		'baby'        => '<circle cx="12" cy="8" r="3.6"/><path d="M6.4 20a5.6 5.6 0 0 1 11.2 0"/><path d="M10.4 7.6h.01M13.6 7.6h.01"/>',
		'pregnancy'   => '<circle cx="12" cy="4.8" r="1.9"/><path d="M12 7.2v4.4"/><path d="M12 8.6c2.4 0 3.9 1.8 3.9 4.4 0 1.5-1 2.5-2.5 2.5H12"/><path d="M12 12.4v8.4"/>',
		'stethoscope' => '<path d="M6 3v4.5a4 4 0 0 0 8 0V3"/><path d="M5 3h2M13 3h2"/><path d="M10 15.5v-4"/><path d="M10 15.5a6.5 6.5 0 0 0 6.5 6.5 4 4 0 0 0 4-4v-2.6"/><circle cx="20.4" cy="11.4" r="2.1"/>',
		'pill'        => '<path d="M10.7 3.8 3.8 10.7a4.9 4.9 0 0 0 6.9 6.9l6.9-6.9a4.9 4.9 0 0 0-6.9-6.9z"/><path d="M7.3 7.3 14.2 14.2"/>',
		'syringe'     => '<path d="M18.5 5.5 19.8 4.2M19.2 4.9l-7 7"/><path d="M16.4 8.3 6.8 17.9l-3.1 1 1-3.1L14.3 6.2z"/><path d="M11.2 9.2l1.6 1.6M9 11.4l1.6 1.6"/>',
		'microscope'  => '<path d="M6 19h11"/><path d="M9 19v-2.5"/><path d="M8 16.5h3l1-1.6-3.2-3.2L7.4 13.3z"/><path d="M10.8 9.6 14.4 6"/><path d="M13.3 4.9l3.2 3.2"/><path d="M13.4 18.5A6.4 6.4 0 0 0 18 8.6"/>',
		'ear'         => '<path d="M8.2 18.5C6.6 17 6 15.4 6 12.4a6 6 0 0 1 12 0c0 2.1-1.6 3.1-3.1 3.7-1 .4-1.4 1-1.4 2A2.1 2.1 0 0 1 11 19.5"/><path d="M9.5 9.2a2.5 2.5 0 0 1 5 .1"/>',
		'skin'        => '<path d="M4.5 14.5A8 8 0 0 1 16 7.2"/><path d="M9.2 21a6.4 6.4 0 0 1-3.2-5"/><circle cx="16" cy="15" r="4"/><path d="M19 18l2.4 2.4"/>',
		'kidney'      => '<path d="M14.4 4.6c2.6 0 4.1 2.4 4.1 5.8s-2 7.6-4.6 7.6c-1.3 0-1.7-1.3-1.7-2.7s-.4-2-1.3-2.4C9.4 11.9 8.5 10.2 8.5 8.3c0-2.1 1.4-3.7 3.2-3.7 1.4 0 1.5 1 2.7 1z"/>',
		'stomach'     => '<path d="M9.4 4v3.6c0 1.4.9 2.3.9 3.7 0 2.8-1.8 3.7-1.8 6"/><path d="M9.4 7.6c1.9 0 3.8.5 5.2 2.4s2.3 3.2 2.3 5.1a2.9 2.9 0 0 1-5.8 0"/>',
		'liver'       => '<path d="M4 8.2c4-1.4 11.8-1.4 15.8 0 1 .4 0 3.8-1.9 5.2-1.4 1-1.9 1.9-2.4 3.3-.4 1.1-1 1.8-2.4 1.8-1.9 0-1.9-1.9-3.3-3.3S5 12.9 4.5 11 4 8.2 4 8.2z"/><path d="M11 9.4c.6 1.3 1.8 1.9 3.3 1.7"/>',
		'family'      => '<circle cx="8" cy="7.2" r="2.4"/><circle cx="16.2" cy="7.6" r="2"/><path d="M3.6 19.6c0-2.8 1.9-4.6 4.4-4.6s4.4 1.8 4.4 4.6"/><path d="M13.6 19.2c0-2.3 1.4-3.9 3.3-3.9 1.6 0 3 1.1 3.5 3"/>',
		'wheelchair'  => '<circle cx="11" cy="17.8" r="4"/><path d="M11 13.8 9.4 9h-2"/><path d="M9.6 11.4H15l2.2 4.2H19"/><circle cx="13" cy="5.4" r="1.3"/>',
		'clipboard'   => '<rect x="6" y="4.5" width="12" height="16.5" rx="2"/><path d="M9.2 4.5a1.4 1.4 0 0 1 1.4-1.5h2.8a1.4 1.4 0 0 1 1.4 1.5V5.5H9.2z"/><path d="M9.2 11h6M9.2 14.6h6M9.2 18.2h3.5"/>',
		'nose'        => '<path d="M12 4v7.2c0 1.4-1.4 1.9-1.4 3.3"/><path d="M14.4 16c0 1.5-1.1 2.6-2.6 2.6S9 17.5 9 16c0-1 .6-1.6 1.6-1.9"/><path d="M14.4 16c.9-.2 1.4-.9 1.4-1.7"/>',
	);
}

/** Guess an icon-library name from a department term name. */
function wpddbp_dept_smart_icon( $name ) {
	$name = strtolower( (string) $name );
	$map  = array(
		'cardio' => 'heart-pulse', 'heart' => 'heart-pulse', 'neuro' => 'brain', 'psych' => 'brain', 'mental' => 'brain',
		'ortho' => 'bone', 'bone' => 'bone', 'joint' => 'bone', 'ophthal' => 'eye', 'eye' => 'eye', 'optom' => 'eye', 'vision' => 'eye',
		'dent' => 'tooth', 'oral' => 'tooth', 'pulmon' => 'lung', 'respir' => 'lung', 'lung' => 'lung', 'chest' => 'lung',
		'pediatr' => 'baby', 'paediatr' => 'baby', 'child' => 'baby', 'neonat' => 'baby',
		'obstet' => 'pregnancy', 'gyne' => 'pregnancy', 'gynae' => 'pregnancy', 'women' => 'pregnancy', 'matern' => 'pregnancy',
		'derm' => 'skin', 'skin' => 'skin', 'ent' => 'ear', 'otolar' => 'ear', 'ear' => 'ear',
		'nephro' => 'kidney', 'kidney' => 'kidney', 'urolog' => 'kidney', 'gastro' => 'stomach', 'stomach' => 'stomach', 'digest' => 'stomach',
		'hepat' => 'liver', 'liver' => 'liver', 'patho' => 'microscope', 'lab' => 'microscope', 'oncolog' => 'microscope',
		'pharm' => 'pill', 'immun' => 'syringe', 'vaccin' => 'syringe', 'physio' => 'wheelchair', 'rehab' => 'wheelchair',
		'geriatr' => 'family', 'family' => 'family', 'general' => 'stethoscope', 'internal' => 'stethoscope', 'primary' => 'stethoscope',
	);
	foreach ( $map as $needle => $icon ) {
		if ( '' !== $name && false !== strpos( $name, $needle ) ) {
			return $icon;
		}
	}
	return 'stethoscope';
}

/** Resolve the inline <svg> for a term: stored name/SVG → smart name default → generic. */
function wpddbp_dept_resolve_icon_svg( $stored, $name ) {
	$stored = trim( (string) $stored );
	if ( '' !== $stored && false !== strpos( $stored, '<' ) ) {
		return ( false !== strpos( $stored, '<svg' ) ) ? $stored : wpddbp_dept_wrap_svg( $stored );
	}
	$library = wpddbp_dept_icon_library();
	$key     = ( '' !== $stored && isset( $library[ $stored ] ) ) ? $stored : wpddbp_dept_smart_icon( $name );
	$inner   = isset( $library[ $key ] ) ? $library[ $key ] : $library['stethoscope'];
	return wpddbp_dept_wrap_svg( $inner );
}

/** Resolve the accent colour: author hex, else block global, else palette rotation. */
function wpddbp_dept_resolve_accent( $attrs, $term ) {
	if ( empty( $attrs['useTermAccentColor'] ) ) {
		$global = sanitize_hex_color( (string) ( $attrs['globalAccentColor'] ?? '' ) );
		if ( $global ) {
			return $global;
		}
	} else {
		$stored = get_term_meta( $term->term_id, 'wpddb_doctor_department_accent_color', true );
		$hex    = sanitize_hex_color( (string) $stored );
		if ( $hex ) {
			return $hex;
		}
	}
	$palette = wpddbp_dept_palette();
	return $palette[ (int) $term->term_id % count( $palette ) ];
}

/* =========================================================================
 * Term query + normalized card data
 * ========================================================================= */

/** Build the get_terms() args from the inspector source attrs. */
function wpddbp_departments_query( $attrs ) {
	$orderby = in_array( ( $attrs['orderBy'] ?? 'count' ), array( 'count', 'name', 'slug', 'term_id' ), true ) ? $attrs['orderBy'] : 'count';
	$order   = ( strtoupper( $attrs['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';
	$limit   = max( 0, (int) ( $attrs['limit'] ?? 0 ) );

	$args = array(
		'taxonomy'   => 'wpddb_doctor_department',
		'hide_empty' => ! empty( $attrs['hideEmpty'] ),
		'number'     => $limit,
		'orderby'    => $orderby,
		'order'      => $order,
	);

	if ( ! empty( $attrs['departmentIds'] ) && is_array( $attrs['departmentIds'] ) ) {
		$args['include'] = array_map( 'intval', $attrs['departmentIds'] );
		$args['orderby'] = 'include';
		$args['number']  = 0;
	}

	$terms = get_terms( $args );
	return is_wp_error( $terms ) ? array() : $terms;
}

/** Normalize one department term into the renderer data array. */
function wpddbp_departments_term_data( $term, $attrs ) {
	$icon_meta = get_term_meta( $term->term_id, 'wpddb_doctor_department_icon', true );
	$link      = get_term_link( $term );

	return array(
		'id'          => (int) $term->term_id,
		'name'        => $term->name,
		'slug'        => $term->slug,
		'link'        => is_wp_error( $link ) ? '#' : $link,
		'description' => trim( (string) $term->description ),
		'count'       => (int) $term->count,
		'icon_svg'    => wpddbp_dept_resolve_icon_svg( $icon_meta, $term->name ),
		'accent'      => wpddbp_dept_resolve_accent( $attrs, $term ),
	);
}

/** A boolean display toggle, defaulting true when unset. */
function wpddbp_departments_show( $attrs, $key ) {
	return ! isset( $attrs[ $key ] ) || $attrs[ $key ];
}

/** Count + suffix, e.g. "8 specialists". */
function wpddbp_departments_count_text( $d, $attrs ) {
	$suffix = $attrs['countSuffix'] ?? __( 'specialists', 'doc-booker-pro' );
	return number_format_i18n( $d['count'] ) . ' ' . $suffix;
}

/* =========================================================================
 * Per-layout renderers
 * ========================================================================= */

/** GRID — uniform accent-tinted cards with a medallion icon + hover tagline. */
function wpddbp_departments_card_grid( $d, $attrs, $index ) {
	$idx = $attrs['showIndex'] ?? true ? '<span class="wpddbp-dept-idx">' . esc_html( sprintf( '%02d', $index + 1 ) ) . '</span>' : '';

	$medal = '';
	if ( wpddbp_departments_show( $attrs, 'showIcon' ) ) {
		$medal = '<div class="wpddbp-dept-wash"><span class="wpddbp-dept-lines" aria-hidden="true"></span><span class="wpddbp-dept-medal" aria-hidden="true">' . $d['icon_svg'] . '</span></div>';
	}

	$name  = '<h3 class="wpddbp-dept-name">' . esc_html( $d['name'] ) . '</h3>';
	$count = wpddbp_departments_show( $attrs, 'showCount' )
		? '<div class="wpddbp-dept-count"><b>' . esc_html( number_format_i18n( $d['count'] ) ) . '</b> ' . esc_html( $attrs['countSuffix'] ?? __( 'specialists', 'doc-booker-pro' ) ) . '</div>'
		: '';
	$tagline = ( wpddbp_departments_show( $attrs, 'showDescription' ) && '' !== $d['description'] )
		? '<p class="wpddbp-dept-tagline">' . esc_html( $d['description'] ) . '</p>'
		: '';
	$explore = wpddbp_departments_show( $attrs, 'showExplore' )
		? '<div class="wpddbp-dept-explore"><span class="wpddbp-dept-explore-label">' . esc_html( $attrs['exploreText'] ?? __( 'Explore', 'doc-booker-pro' ) ) . '</span><span class="wpddbp-dept-arrow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span></div>'
		: '';

	return '<a class="wpddbp-dept-card" href="' . esc_url( $d['link'] ) . '" style="--dept-accent:' . esc_attr( $d['accent'] ) . '" aria-label="' . esc_attr( $d['name'] ) . '">'
		. $idx . $medal
		. '<div class="wpddbp-dept-body">' . $name . $count . $tagline . $explore . '</div>'
		. '</a>';
}

/** ICON-CARDS — impact cards, accent-driven, optional dark/soft featured leads. */
function wpddbp_departments_card_icon( $d, $attrs, $index ) {
	$feature_first = ! empty( $attrs['featureFirst'] );
	$variant = '';
	if ( $feature_first && 0 === $index ) {
		$variant = ' wpddbp-dept-icard--dark';
	} elseif ( $feature_first && 1 === $index ) {
		$variant = ' wpddbp-dept-icard--soft';
	}

	$idx = ( $attrs['showIndex'] ?? true ) ? '<span class="wpddbp-dept-icard-idx">' . esc_html( sprintf( '%02d', $index + 1 ) ) . '</span>' : '';
	$flag = ( $feature_first && 0 === $index )
		? '<span class="wpddbp-dept-icard-flag"><span class="wpddbp-dept-icard-dot"></span>' . esc_html__( 'Flagship specialty', 'doc-booker-pro' ) . '</span>'
		: '';

	$tile = '';
	if ( wpddbp_departments_show( $attrs, 'showIcon' ) ) {
		$pip = wpddbp_departments_show( $attrs, 'showCount' )
			? '<span class="wpddbp-dept-icard-pip"><b>' . esc_html( number_format_i18n( $d['count'] ) ) . '</b></span>'
			: '';
		$tile = '<div class="wpddbp-dept-icard-iconzone"><div class="wpddbp-dept-icard-tile" aria-hidden="true">' . $d['icon_svg'] . $pip . '</div></div>';
	}

	$name    = '<h3 class="wpddbp-dept-name">' . esc_html( $d['name'] ) . '</h3>';
	$tagline = ( wpddbp_departments_show( $attrs, 'showDescription' ) && '' !== $d['description'] )
		? '<p class="wpddbp-dept-tagline">' . esc_html( $d['description'] ) . '</p>'
		: '';
	$count = wpddbp_departments_show( $attrs, 'showCount' )
		? '<span class="wpddbp-dept-icard-count"><b>' . esc_html( number_format_i18n( $d['count'] ) ) . '</b> ' . esc_html( $attrs['countSuffix'] ?? __( 'specialists', 'doc-booker-pro' ) ) . '</span>'
		: '';
	$link = wpddbp_departments_show( $attrs, 'showExplore' )
		? '<span class="wpddbp-dept-icard-link">' . esc_html( $attrs['exploreText'] ?? __( 'View specialists', 'doc-booker-pro' ) ) . ' <span class="wpddbp-dept-icard-arr">&rarr;</span></span>'
		: '';
	$foot = ( '' !== $count || '' !== $link ) ? '<div class="wpddbp-dept-icard-foot">' . $count . $link . '</div>' : '';

	return '<a class="wpddbp-dept-icard' . $variant . '" href="' . esc_url( $d['link'] ) . '" style="--dept-accent:' . esc_attr( $d['accent'] ) . '" aria-label="' . esc_attr( $d['name'] ) . '">'
		. $flag . $idx . $tile
		. '<div class="wpddbp-dept-icard-titlezone">' . $name . '</div>' . $tagline . $foot
		. '</a>';
}

/** PILL-CLOUD — accent-tinted pills sized by doctor count; top count = featured ink. */
function wpddbp_departments_card_pill( $d, $attrs, $index, $size, $is_top ) {
	$rotations = array( '', ' wpddbp-dept-pill--rl1', '', ' wpddbp-dept-pill--rr1', ' wpddbp-dept-pill--rl2', '', ' wpddbp-dept-pill--rr05' );
	$rot       = $rotations[ $index % count( $rotations ) ];
	$featured  = $is_top ? ' wpddbp-dept-pill--featured' : '';

	$ico = wpddbp_departments_show( $attrs, 'showIcon' )
		? '<span class="wpddbp-dept-pill-ico">' . $d['icon_svg'] . '</span>'
		: '';
	$count = wpddbp_departments_show( $attrs, 'showCount' )
		? '<span class="wpddbp-dept-pill-count">' . esc_html( number_format_i18n( $d['count'] ) ) . '</span>'
		: '';

	return '<a class="wpddbp-dept-pill wpddbp-dept-pill--' . esc_attr( $size ) . $featured . $rot . '" href="' . esc_url( $d['link'] ) . '" style="--dept-accent:' . esc_attr( $d['accent'] ) . '" data-name="' . esc_attr( strtolower( $d['name'] ) ) . '" aria-label="' . esc_attr( sprintf( '%1$s — %2$s', $d['name'], wpddbp_departments_count_text( $d, $attrs ) ) ) . '">'
		. $ico . '<span class="wpddbp-dept-pill-name">' . esc_html( $d['name'] ) . '</span>' . $count
		. '</a>';
}

/** Bucket a count into a pill size tier relative to the shown set. */
function wpddbp_departments_pill_size( $count, $min, $max ) {
	if ( $max <= $min ) {
		return 'md';
	}
	$r = ( $count - $min ) / ( $max - $min );
	if ( $r >= 0.72 ) {
		return 'xl';
	}
	if ( $r >= 0.45 ) {
		return 'lg';
	}
	if ( $r >= 0.18 ) {
		return 'md';
	}
	return 'sm';
}

/**
 * Render the whole departments block body (the grid/cloud + optional search).
 * Used by render.php. Returns '' when there are no terms.
 *
 * @param array $attrs Block attributes.
 * @return string
 */
function wpddbp_departments_render( $attrs ) {
	$layout = $attrs['layoutType'] ?? 'grid';
	$terms  = wpddbp_departments_query( $attrs );

	if ( empty( $terms ) ) {
		return '<p class="wpddbp-dept-empty">' . esc_html__( 'No departments found.', 'doc-booker-pro' ) . '</p>';
	}

	$cards = array();
	foreach ( $terms as $term ) {
		$cards[] = wpddbp_departments_term_data( $term, $attrs );
	}

	if ( 'pill-cloud' === $layout ) {
		$counts = array_map( function ( $c ) { return $c['count']; }, $cards );
		$min    = min( $counts );
		$max    = max( $counts );
		$top    = $max;
		$html   = '';
		$top_used = false;
		foreach ( $cards as $i => $d ) {
			$size   = wpddbp_departments_pill_size( $d['count'], $min, $max );
			$is_top = ( ! $top_used && $d['count'] === $top && $max > $min );
			if ( $is_top ) {
				$top_used = true;
			}
			$html .= wpddbp_departments_card_pill( $d, $attrs, $i, $size, $is_top );
		}
		return '<div class="wpddbp-dept-cloud">' . $html . '</div>';
	}

	$html = '';
	foreach ( $cards as $i => $d ) {
		$html .= ( 'icon-cards' === $layout )
			? wpddbp_departments_card_icon( $d, $attrs, $i )
			: wpddbp_departments_card_grid( $d, $attrs, $i );
	}
	return '<div class="wpddbp-dept-grid">' . $html . '</div>';
}

/** The optional pill-cloud client-side search field. */
function wpddbp_departments_render_search( $attrs ) {
	if ( empty( $attrs['showSearch'] ) || 'pill-cloud' !== ( $attrs['layoutType'] ?? 'grid' ) ) {
		return '';
	}
	$placeholder = $attrs['searchPlaceholder'] ?? __( 'Filter specialties…', 'doc-booker-pro' );
	return '<label class="wpddbp-dept-search">'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>'
		. '<input type="text" class="wpddbp-dept-search-input" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr__( 'Filter departments by name', 'doc-booker-pro' ) . '">'
		. '<span class="wpddbp-dept-search-empty" hidden>' . esc_html__( 'No matches.', 'doc-booker-pro' ) . '</span>'
		. '</label>';
}
