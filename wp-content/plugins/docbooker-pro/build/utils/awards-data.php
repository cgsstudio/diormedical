<?php
/**
 * Doctor Awards block — shared data + 2 layout renderers (Tier B, Phase 3).
 *
 * Reads each doctor's real `wpddb_doctor_awards` meta (array of {award_text, award_image},
 * stored by DocBooker Pro's doctor-meta save) and the doctor's identity (name, department,
 * thumbnail, credentials, experience, quote). Two layouts:
 *   - trophy-wall    : practice-wide bronze masonry — awards flattened across the selected
 *                      doctors, each card attributed to its doctor.
 *   - credential-list: per-doctor ledger — each doctor = an identity strip + their awards.
 *
 * Grounding (architecture §10b + grounding memory): award data is only text + image, so we
 * extract a trailing year from the text and DROP the mockups' fabricated awarding-body /
 * citation / board-cert / publication content. Standalone (§3): namespaced `wpddbp_awards_*`.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

/** Bronze editorial icon set (inner SVG markup, 24×24 stroke). */
function wpddbp_awards_icon( $name ) {
	$icons = array(
		'trophy'      => '<path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4v2a4 4 0 0 0 4 4M17 5h3v2a4 4 0 0 1-4 4"/><path d="M10 15v3M14 15v3M8 21h8l-1-3H9l-1 3Z"/>',
		'medal'       => '<circle cx="12" cy="15" r="6"/><path d="M9 9 6 3M15 9l3-6"/><path d="M12 12.5v5M9.5 15h5"/>',
		'rosette'     => '<path d="M12 2l2.9 6.9L22 10l-5.5 4.8L18 22l-6-3.5L6 22l1.5-7.2L2 10l7.1-1.1z"/>',
		'ribbon'      => '<circle cx="12" cy="9" r="5"/><path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5"/>',
		'certificate' => '<rect x="4" y="4" width="16" height="13" rx="2"/><path d="M8 9h8M8 12h5"/><circle cx="16.5" cy="16.5" r="2.5"/><path d="M15 18.2l-1 3.8 2.5-1.5L19 22l-1-3.8"/>',
		'check'       => '<path d="M5 13l4 4L19 7"/>',
		'laurel'      => '<path d="M12 3c-2 3-3 6.5-3 11 0 5 1.3 8.5 3 11 1.7-2.5 3-6 3-11 0-4.5-1-8-3-11Z"/><path d="M9 8C7 8.6 5.5 10 4.6 12 6.6 13 8.5 12.4 9.6 11M15 8c2 .6 3.5 2 4.4 4-2 1-3.9.4-5-1"/>',
	);
	$inner = isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['rosette'];
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** Parse an award_text into { title, year } — extracts a trailing/embedded 4-digit year. */
function wpddbp_awards_parse( $text ) {
	$text = trim( (string) $text );
	$year = '';
	if ( preg_match( '/\b(19|20)\d{2}\b/', $text, $m ) ) {
		$year  = $m[0];
		$title = preg_replace( '/[\s,()\-\x{2013}\x{2014}]*\b' . preg_quote( $year, '/' ) . '\b[\s,()\-\x{2013}\x{2014}]*/u', ' ', $text );
		$title = trim( (string) $title, " ,-()\t\n\r\0\x0B\xe2\x80\x93\xe2\x80\x94" );
		$title = trim( preg_replace( '/\s{2,}/', ' ', $title ) );
		if ( '' === $title ) {
			$title = $text;
		}
	} else {
		$title = $text;
	}
	return array( 'title' => $title, 'year' => $year );
}

/** A boolean display toggle, defaulting true when unset. */
function wpddbp_awards_show( $attrs, $key ) {
	return ! isset( $attrs[ $key ] ) || $attrs[ $key ];
}

/** Resolve the doctor IDs for the chosen source. */
function wpddbp_awards_doctor_ids( $attrs ) {
	$source = $attrs['source'] ?? 'all';

	if ( 'current' === $source ) {
		$id = get_the_ID();
		if ( $id && 'wpddb_doctor' === get_post_type( $id ) ) {
			return array( (int) $id );
		}
		// Not on a doctor page → fall through to all.
	}

	$args = array(
		'post_type'      => 'wpddb_doctor',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => in_array( ( $attrs['orderBy'] ?? 'menu_order' ), array( 'date', 'title', 'menu_order', 'rand' ), true ) ? $attrs['orderBy'] : 'menu_order',
		'order'          => ( strtoupper( $attrs['order'] ?? 'ASC' ) === 'DESC' ) ? 'DESC' : 'ASC',
	);

	if ( 'specific' === $source && ! empty( $attrs['doctorIds'] ) && is_array( $attrs['doctorIds'] ) ) {
		$args['post__in'] = array_map( 'intval', $attrs['doctorIds'] );
		$args['orderby']  = 'post__in';
	}

	$ids = get_posts( $args );
	return is_array( $ids ) ? $ids : array();
}

/** Normalized doctor identity for attribution + the credential strip. */
function wpddbp_awards_doctor_info( $post_id ) {
	$thumb_id = get_post_thumbnail_id( $post_id );
	$terms    = wp_get_post_terms( $post_id, 'wpddb_doctor_department' );
	$dept     = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : '';
	$creds    = get_post_meta( $post_id, 'wpddb_doctor_credentials', true );

	return array(
		'id'          => (int) $post_id,
		'name'        => get_the_title( $post_id ),
		'permalink'   => get_permalink( $post_id ),
		'thumb'       => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
		'dept'        => $dept,
		'designation' => (string) get_post_meta( $post_id, 'wpddb_doctor_designation', true ),
		'credentials' => is_array( $creds ) ? $creds : array(),
		'degree'      => (string) get_post_meta( $post_id, 'wpddb_doctor_degree', true ),
		'workplace'   => (string) get_post_meta( $post_id, 'wpddb_doctor_workplace', true ),
		'alma_mater'  => (string) get_post_meta( $post_id, 'wpddb_doctor_alma_mater', true ),
		'experience'  => (string) get_post_meta( $post_id, 'wpddb_doctor_experience_years', true ),
		'quote'       => (string) get_post_meta( $post_id, 'wpddb_doctor_quote', true ),
	);
}

/** Collect doctors (with awards) → [ doctor_info + 'awards' => [{title,year,image,raw}] ]. */
function wpddbp_awards_collect( $attrs ) {
	$ids = wpddbp_awards_doctor_ids( $attrs );
	$out = array();
	foreach ( $ids as $id ) {
		$awards = get_post_meta( $id, 'wpddb_doctor_awards', true );
		if ( ! is_array( $awards ) ) {
			continue;
		}
		$clean = array();
		foreach ( $awards as $a ) {
			$text = isset( $a['award_text'] ) ? trim( (string) $a['award_text'] ) : '';
			$img  = isset( $a['award_image'] ) ? trim( (string) $a['award_image'] ) : '';
			if ( '' === $text && '' === $img ) {
				continue;
			}
			$p       = wpddbp_awards_parse( $text );
			$clean[] = array( 'title' => $p['title'], 'year' => $p['year'], 'image' => $img, 'raw' => $text );
		}
		if ( empty( $clean ) ) {
			continue;
		}
		$info           = wpddbp_awards_doctor_info( $id );
		$info['awards'] = $clean;
		$out[]          = $info;
	}
	return $out;
}

/** Doctor attribution row (avatar + name + department). */
function wpddbp_awards_by_row( $doc, $attrs ) {
	if ( ! wpddbp_awards_show( $attrs, 'showDoctor' ) ) {
		return '';
	}
	$av = $doc['thumb']
		? '<span class="wpddbp-awards-av"><img src="' . esc_url( $doc['thumb'] ) . '" alt="' . esc_attr( $doc['name'] ) . '" loading="lazy"></span>'
		: '<span class="wpddbp-awards-av wpddbp-awards-av--ph" aria-hidden="true"></span>';
	$sub = ( '' !== $doc['dept'] ) ? '<span>' . esc_html( $doc['dept'] ) . '</span>' : '';
	return '<span class="wpddbp-awards-by">' . $av . '<span class="wpddbp-awards-who"><b>' . esc_html( $doc['name'] ) . '</b>' . $sub . '</span></span>';
}

/** Big bronze year element (or '' when no year / disabled). */
function wpddbp_awards_year_html( $aw, $attrs, $class = 'wpddbp-awards-year' ) {
	if ( ! wpddbp_awards_show( $attrs, 'showYear' ) || '' === $aw['year'] ) {
		return '';
	}
	return '<span class="' . esc_attr( $class ) . '">' . esc_html( $aw['year'] ) . '</span>';
}

/* ---------------------------------------------------------------------------
 * Layout: trophy-wall (masonry of award cards, archetypes by index)
 * ------------------------------------------------------------------------- */

function wpddbp_awards_card( $aw, $doc, $attrs, $type ) {
	$title = '<h3 class="wpddbp-awards-title">' . esc_html( $aw['title'] ) . '</h3>';
	$dept  = ( '' !== $doc['dept'] ) ? '<span class="wpddbp-awards-org">' . esc_html( $doc['dept'] ) . '</span>' : '';
	$by    = wpddbp_awards_by_row( $doc, $attrs );
	$link  = wpddbp_awards_show( $attrs, 'showProfileLink' )
		? '<a class="wpddbp-awards-more" href="' . esc_url( $doc['permalink'] ) . '">' . esc_html( $attrs['profileLinkText'] ?? __( 'View profile', 'doc-booker-pro' ) ) . '</a>'
		: '';
	$foot  = ( '' !== $by || '' !== $link ) ? '<div class="wpddbp-awards-footrow">' . $by . $link . '</div>' : '';
	$cardlink = '<a class="wpddbp-awards-cardlink" href="' . esc_url( $doc['permalink'] ) . '" tabindex="-1" aria-hidden="true">' . esc_html( $aw['title'] ) . '</a>';

	$has_img = wpddbp_awards_show( $attrs, 'showAwardImage' ) && '' !== $aw['image'];

	if ( $has_img ) {
		$stamp = wpddbp_awards_year_html( $aw, $attrs, 'wpddbp-awards-yrstamp' );
		$inner = '<div class="wpddbp-awards-photo"><img src="' . esc_url( $aw['image'] ) . '" alt="' . esc_attr( $aw['title'] ) . '" loading="lazy">' . ( '' !== $doc['dept'] ? '<span class="wpddbp-awards-orgfloat">' . esc_html( $doc['dept'] ) . '</span>' : '' ) . $stamp . '</div>'
			. '<div class="wpddbp-awards-body">' . $title . $foot . '</div>';
		return '<article class="wpddbp-awards-card wpddbp-aw--image">' . $inner . $cardlink . '</article>';
	}

	switch ( $type ) {
		case 'hero':
			$inner = '<span class="wpddbp-awards-ribbontag">' . esc_html__( 'Headline honor', 'doc-booker-pro' ) . ( '' !== $aw['year'] ? ' &middot; ' . esc_html( $aw['year'] ) : '' ) . '</span>'
				. '<span class="wpddbp-awards-trophy">' . wpddbp_awards_icon( 'trophy' ) . '</span>'
				. $dept . '<h2 class="wpddbp-awards-title">' . esc_html( $aw['title'] ) . '</h2>'
				. ( '' !== $aw['year'] && wpddbp_awards_show( $attrs, 'showYear' ) ? '<div class="wpddbp-awards-yearbig"><small>' . esc_html__( 'Honored', 'doc-booker-pro' ) . '</small>' . esc_html( $aw['year'] ) . '</div>' : '' )
				. $foot;
			return '<article class="wpddbp-awards-card wpddbp-aw--hero">' . $inner . $cardlink . '</article>';

		case 'plaque':
			$inner = '<span class="wpddbp-awards-medal">' . wpddbp_awards_icon( 'medal' ) . '</span>'
				. $dept . $title
				. ( '' !== $aw['year'] && wpddbp_awards_show( $attrs, 'showYear' ) ? '<div class="wpddbp-awards-yrtag">' . esc_html( $aw['year'] ) . '</div>' : '' )
				. $foot;
			return '<article class="wpddbp-awards-card wpddbp-aw--plaque">' . $inner . $cardlink . '</article>';

		case 'inline':
			$inner = '<div class="wpddbp-awards-inrow"><span class="wpddbp-awards-iconsq">' . wpddbp_awards_icon( 'rosette' ) . '</span>'
				. '<div class="wpddbp-awards-ininfo">' . $title . $dept . '</div></div>'
				. '<div class="wpddbp-awards-metaend">' . $by . wpddbp_awards_year_html( $aw, $attrs, 'wpddbp-awards-yr' ) . '</div>';
			return '<article class="wpddbp-awards-card wpddbp-aw--inline">' . $inner . $cardlink . '</article>';

		case 'stroke':
		default:
			$inner = '<div class="wpddbp-awards-iconrow"><span class="wpddbp-awards-strokeico">' . wpddbp_awards_icon( 'ribbon' ) . '</span>' . wpddbp_awards_year_html( $aw, $attrs, 'wpddbp-awards-yr' ) . '</div>'
				. $dept . $title . $foot;
			return '<article class="wpddbp-awards-card wpddbp-aw--stroke">' . $inner . $cardlink . '</article>';
	}
}

function wpddbp_awards_render_trophy( $attrs, $doctors ) {
	$flat = array();
	foreach ( $doctors as $doc ) {
		foreach ( $doc['awards'] as $aw ) {
			$flat[] = array( 'aw' => $aw, 'doc' => $doc );
		}
	}
	$limit = max( 0, (int) ( $attrs['limit'] ?? 0 ) );
	if ( $limit > 0 ) {
		$flat = array_slice( $flat, 0, $limit );
	}
	if ( empty( $flat ) ) {
		return '<p class="wpddbp-awards-empty">' . esc_html( $attrs['emptyText'] ?? __( 'No awards to display yet.', 'doc-booker-pro' ) ) . '</p>';
	}

	$no_img_cycle = array( 'stroke', 'plaque', 'inline' );
	$ni           = 0;
	$html         = '';
	foreach ( $flat as $i => $item ) {
		$has_img = wpddbp_awards_show( $attrs, 'showAwardImage' ) && '' !== $item['aw']['image'];
		if ( $has_img ) {
			$type = 'image';
		} elseif ( 0 === $i ) {
			$type = 'hero';
		} else {
			$type = $no_img_cycle[ $ni % count( $no_img_cycle ) ];
			$ni++;
		}
		$html .= wpddbp_awards_card( $item['aw'], $item['doc'], $attrs, $type );
	}
	return '<div class="wpddbp-awards-wall">' . $html . '</div>';
}

/* ---------------------------------------------------------------------------
 * Layout: credential-list (per-doctor identity strip + awards ledger)
 * ------------------------------------------------------------------------- */

function wpddbp_awards_render_credlist( $attrs, $doctors ) {
	$limit = max( 0, (int) ( $attrs['limit'] ?? 0 ) );
	if ( $limit > 0 ) {
		$doctors = array_slice( $doctors, 0, $limit );
	}
	if ( empty( $doctors ) ) {
		return '<p class="wpddbp-awards-empty">' . esc_html( $attrs['emptyText'] ?? __( 'No awards to display yet.', 'doc-booker-pro' ) ) . '</p>';
	}

	$out = '';
	foreach ( $doctors as $doc ) {
		// ---- identity strip ----
		$av = $doc['thumb']
			? '<span class="wpddbp-awards-cl-avatar"><img src="' . esc_url( $doc['thumb'] ) . '" alt="' . esc_attr( $doc['name'] ) . '" loading="lazy"></span>'
			: '<span class="wpddbp-awards-cl-avatar wpddbp-awards-cl-avatar--ph" aria-hidden="true"></span>';

		$post = '';
		if ( wpddbp_awards_show( $attrs, 'showCredentials' ) && ! empty( $doc['credentials'] ) ) {
			$post = '<span class="wpddbp-awards-cl-post">' . esc_html( implode( ' · ', array_map( 'sanitize_text_field', $doc['credentials'] ) ) ) . '</span>';
		} elseif ( '' !== $doc['designation'] ) {
			$post = '<span class="wpddbp-awards-cl-post">' . esc_html( $doc['designation'] ) . '</span>';
		}

		$sub_bits = array();
		if ( '' !== $doc['dept'] ) {
			$sub_bits[] = '<span class="wpddbp-awards-cl-spec">' . esc_html( $doc['dept'] ) . '</span>';
		}
		if ( '' !== $doc['workplace'] ) {
			$sub_bits[] = '<span>' . esc_html( $doc['workplace'] ) . '</span>';
		}
		if ( '' !== $doc['alma_mater'] ) {
			$sub_bits[] = '<span>' . esc_html( $doc['alma_mater'] ) . '</span>';
		}
		$sub = ! empty( $sub_bits ) ? '<div class="wpddbp-awards-cl-sub">' . implode( '<span class="wpddbp-awards-cl-dot"></span>', $sub_bits ) . '</div>' : '';

		$stat = ( wpddbp_awards_show( $attrs, 'showExperience' ) && '' !== $doc['experience'] )
			? '<div class="wpddbp-awards-cl-stat"><span class="wpddbp-awards-cl-num">' . esc_html( $doc['experience'] ) . '</span><span class="wpddbp-awards-cl-lbl">' . esc_html( $attrs['experienceLabel'] ?? __( 'Years in practice', 'doc-booker-pro' ) ) . '</span></div>'
			: '';

		$strip = '<div class="wpddbp-awards-cl-doc">' . $av
			. '<div class="wpddbp-awards-cl-who"><div class="wpddbp-awards-cl-name">' . esc_html( $doc['name'] ) . $post . '</div>' . $sub . '</div>'
			. $stat . '</div>';

		// ---- optional about-quote ----
		$quote = ( wpddbp_awards_show( $attrs, 'showQuote' ) && '' !== $doc['quote'] )
			? '<p class="wpddbp-awards-cl-quote">' . esc_html( $doc['quote'] ) . '</p>'
			: '';

		// ---- awards ledger ----
		$count = count( $doc['awards'] );
		$head  = '<div class="wpddbp-awards-cl-sechead"><h2 class="wpddbp-awards-cl-sectitle">' . esc_html__( 'Awards & recognition', 'doc-booker-pro' ) . '</h2><span class="wpddbp-awards-cl-seccount">' . esc_html( sprintf( /* translators: %d: award count. */ _n( 'Total %d', 'Total %d', $count, 'doc-booker-pro' ), $count ) ) . '</span></div>';
		$items = '';
		foreach ( $doc['awards'] as $aw ) {
			$yr = ( wpddbp_awards_show( $attrs, 'showYear' ) && '' !== $aw['year'] )
				? '<div class="wpddbp-awards-cl-yr">' . esc_html( $aw['year'] ) . '</div>'
				: '';
			$seal = ( wpddbp_awards_show( $attrs, 'showAwardImage' ) && '' !== $aw['image'] )
				? '<span class="wpddbp-awards-cl-seal wpddbp-awards-cl-seal--img"><img src="' . esc_url( $aw['image'] ) . '" alt="" loading="lazy"></span>'
				: '<span class="wpddbp-awards-cl-seal">' . wpddbp_awards_icon( 'medal' ) . '</span>';
			$items .= '<li>' . $seal . '<div class="wpddbp-awards-cl-body"><p class="wpddbp-awards-cl-itemtitle">' . esc_html( $aw['title'] ) . '</p></div>' . $yr . '</li>';
		}
		$ledger = $head . '<ul class="wpddbp-awards-cl-creds">' . $items . '</ul>';

		// ---- footer ----
		$foot = '';
		if ( wpddbp_awards_show( $attrs, 'showProfileLink' ) ) {
			$foot = '<div class="wpddbp-awards-cl-actions">'
				. ( ! empty( $attrs['enablePrint'] ) ? '<button type="button" class="wpddbp-awards-cl-btn wpddbp-awards-print">' . esc_html__( 'Print', 'doc-booker-pro' ) . '</button>' : '' )
				. '<a class="wpddbp-awards-cl-btn wpddbp-awards-cl-btn--primary" href="' . esc_url( $doc['permalink'] ) . '">' . esc_html( $attrs['profileLinkText'] ?? __( 'View profile', 'doc-booker-pro' ) ) . ' <span aria-hidden="true">&rarr;</span></a>'
				. '</div>';
		}

		$out .= '<section class="wpddbp-awards-cl-card">' . $strip . $quote . $ledger . $foot . '</section>';
	}

	return '<div class="wpddbp-awards-credlist">' . $out . '</div>';
}

/**
 * Render the whole block body. Used by render.php.
 *
 * @param array $attrs Block attributes.
 * @return string
 */
function wpddbp_awards_render( $attrs ) {
	$layout  = $attrs['layoutType'] ?? 'trophy-wall';
	$doctors = wpddbp_awards_collect( $attrs );
	return ( 'credential-list' === $layout )
		? wpddbp_awards_render_credlist( $attrs, $doctors )
		: wpddbp_awards_render_trophy( $attrs, $doctors );
}
