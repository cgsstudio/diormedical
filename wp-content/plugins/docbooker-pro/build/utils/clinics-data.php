<?php
/**
 * Clinics block — shared data + 5 layout renderers (Tier B, Phase 4).
 *
 * Renders the `wpddb_clinic` CPT in 5 layouts (card / feature / gallery / map /
 * compact), reading the existing Free meta (address / hotline / email / lat-lng /
 * thumbnail / gallery / socials) + the Pro extension (type / founded / capacity /
 * 24-7 / tagline / services / certifications / hours / credits) + a derived
 * specialist count. Standalone rule (§3): the type-label map, derived-count query,
 * hours 12h-format + open-now, and maps-url are DUPLICATED here (`wpddbp_clinics_*`),
 * reading the SAME meta keys the Pro `ActionHooks` writes — never imported.
 *
 * Grounded: every visible value is real meta — the mockups' fabricated "distance"
 * (e.g. "0.8 mi", no geolocation) is dropped.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Standalone resolvers (duplicate of Pro ActionHooks clinic helpers — §3)
 * ========================================================================= */

/** Human label for a clinic type. */
function wpddbp_clinics_type_label( $type ) {
	$map = array(
		'hospital'         => __( 'Hospital', 'doc-booker-pro' ),
		'clinic'           => __( 'Clinic', 'doc-booker-pro' ),
		'urgent_care'      => __( 'Urgent Care', 'doc-booker-pro' ),
		'specialty_center' => __( 'Specialty Center', 'doc-booker-pro' ),
		'pavilion'         => __( 'Pavilion', 'doc-booker-pro' ),
	);
	return $map[ $type ] ?? __( 'Clinic', 'doc-booker-pro' );
}

/** Specialists at this clinic (override wins, else derived from linked doctors). */
function wpddbp_clinics_specialist_count( $clinic_id ) {
	$override = (int) get_post_meta( $clinic_id, 'wpddb_clinic_specialist_count_override', true );
	if ( $override > 0 ) {
		return $override;
	}
	$q = new WP_Query(
		array(
			'post_type'      => 'wpddb_doctor',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array(
				array(
					'key'     => 'wpddb_doctor_available_clinic',
					'value'   => '"' . (int) $clinic_id . '"',
					'compare' => 'LIKE',
				),
			),
		)
	);
	return (int) $q->found_posts;
}

/** Format a "HH:MM" 24h time to a localized 12h string. */
function wpddbp_clinics_fmt_time( $t ) {
	$t = trim( (string) $t );
	if ( '' === $t || ! preg_match( '/^\d{1,2}:\d{2}$/', $t ) ) {
		return $t;
	}
	$ts = strtotime( $t );
	return $ts ? date_i18n( 'g:i A', $ts ) : $t;
}

/** Today's hours row + open-now (server local time; simple — clinics aren't second-critical). */
function wpddbp_clinics_today( $hours ) {
	$out = array( 'label' => '', 'open_now' => false, 'has' => false );
	if ( empty( $hours ) || ! is_array( $hours ) ) {
		return $out;
	}
	$today = strtolower( date_i18n( 'l' ) ); // monday … sunday
	foreach ( $hours as $h ) {
		if ( strtolower( $h['day'] ?? '' ) !== $today ) {
			continue;
		}
		$out['has'] = true;
		if ( ! empty( $h['is_closed'] ) ) {
			$out['label'] = __( 'Closed today', 'doc-booker-pro' );
			return $out;
		}
		$open  = $h['open'] ?? '';
		$close = $h['close'] ?? '';
		$out['label'] = wpddbp_clinics_fmt_time( $open ) . ' – ' . wpddbp_clinics_fmt_time( $close );
		// open-now check
		if ( preg_match( '/^\d{1,2}:\d{2}$/', $open ) && preg_match( '/^\d{1,2}:\d{2}$/', $close ) ) {
			$now = (int) current_time( 'Hi' );
			$o   = (int) str_replace( ':', '', $open );
			$c   = (int) str_replace( ':', '', $close );
			$out['open_now'] = ( $now >= $o && $now <= $c );
		}
		return $out;
	}
	return $out;
}

/** A directions/maps URL from coords (preferred) or the address. */
function wpddbp_clinics_maps_url( $d ) {
	if ( '' !== $d['lat'] && '' !== $d['lng'] ) {
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $d['lat'] . ',' . $d['lng'] );
	}
	if ( '' !== $d['address'] ) {
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $d['address'] );
	}
	return '';
}

/* =========================================================================
 * Query + normalized card data
 * ========================================================================= */

function wpddbp_clinics_query( $attrs ) {
	$orderby = in_array( ( $attrs['orderBy'] ?? 'date' ), array( 'date', 'title', 'menu_order', 'rand' ), true ) ? $attrs['orderBy'] : 'date';
	$order   = ( strtoupper( $attrs['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';
	$limit   = max( 0, (int) ( $attrs['limit'] ?? 0 ) );

	$args = array(
		'post_type'      => 'wpddb_clinic',
		'post_status'    => 'publish',
		'posts_per_page' => $limit > 0 ? $limit : -1,
		'orderby'        => $orderby,
		'order'          => $order,
	);
	if ( ! empty( $attrs['clinicIds'] ) && is_array( $attrs['clinicIds'] ) ) {
		$args['post__in'] = array_map( 'intval', $attrs['clinicIds'] );
		$args['orderby']  = 'post__in';
	}
	$q = new WP_Query( $args );
	return $q->posts;
}

function wpddbp_clinics_card_data( $post_id, $attrs = array() ) {
	$post_id  = (int) $post_id;
	$img_id   = get_post_thumbnail_id( $post_id );
	$gallery  = get_post_meta( $post_id, 'wpddb_clinic_gallery_images', true );
	$socials  = get_post_meta( $post_id, 'wpddb_clinic_social_icons', true );
	$services = get_post_meta( $post_id, 'wpddb_clinic_services', true );
	$certs    = get_post_meta( $post_id, 'wpddb_clinic_certifications', true );
	$hours    = get_post_meta( $post_id, 'wpddb_clinic_hours', true );
	$type     = get_post_meta( $post_id, 'wpddb_clinic_type', true ) ?: 'clinic';

	$images = array();
	if ( $img_id ) {
		$images[] = wp_get_attachment_image_url( $img_id, 'large' );
	}
	if ( is_array( $gallery ) ) {
		foreach ( $gallery as $g ) {
			if ( '' !== trim( (string) $g ) ) {
				$images[] = $g;
			}
		}
	}

	return array(
		'id'           => $post_id,
		'name'         => get_the_title( $post_id ),
		'permalink'    => get_permalink( $post_id ),
		'image'        => $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : ( ! empty( $images ) ? $images[0] : '' ),
		'images'       => array_values( array_unique( $images ) ),
		'excerpt'      => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 28, '…' ),
		'address'      => (string) get_post_meta( $post_id, 'wpddb_clinic_address', true ),
		'hotline'      => (string) get_post_meta( $post_id, 'wpddb_clinic_hotline', true ),
		'email'        => (string) get_post_meta( $post_id, 'wpddb_clinic_email', true ),
		'lat'          => (string) get_post_meta( $post_id, 'wpddb_clinic_latitude', true ),
		'lng'          => (string) get_post_meta( $post_id, 'wpddb_clinic_longitude', true ),
		'type'         => $type,
		'type_label'   => wpddbp_clinics_type_label( $type ),
		'tagline'      => (string) get_post_meta( $post_id, 'wpddb_clinic_tagline', true ),
		'founded'      => (string) get_post_meta( $post_id, 'wpddb_clinic_founded_year', true ),
		'capacity'     => (int) get_post_meta( $post_id, 'wpddb_clinic_patient_capacity', true ),
		'emergency'    => (bool) get_post_meta( $post_id, 'wpddb_clinic_emergency_24_7', true ),
		'services'     => is_array( $services ) ? $services : array(),
		'certs'        => is_array( $certs ) ? $certs : array(),
		'hours'        => is_array( $hours ) ? $hours : array(),
		'spec_count'   => wpddbp_clinics_specialist_count( $post_id ),
		'architect'    => (string) get_post_meta( $post_id, 'wpddb_clinic_architect_credit', true ),
		'photographer' => (string) get_post_meta( $post_id, 'wpddb_clinic_photographer_credit', true ),
		'socials'      => is_array( $socials ) ? $socials : array(),
	);
}

/** A display toggle, defaulting true when unset. */
function wpddbp_clinics_show( $attrs, $key ) {
	return ! isset( $attrs[ $key ] ) || $attrs[ $key ];
}

/* ---------- shared atoms ---------- */

function wpddbp_clinics_type_chip( $d ) {
	return '<span class="wpddbp-clinics-type wpddbp-clinics-type--' . esc_attr( $d['type'] ) . '">' . esc_html( $d['type_label'] ) . '</span>';
}

function wpddbp_clinics_emergency_badge( $d, $attrs ) {
	if ( ! $d['emergency'] || ! wpddbp_clinics_show( $attrs, 'showEmergency' ) ) {
		return '';
	}
	return '<span class="wpddbp-clinics-247"><i></i>' . esc_html__( '24/7', 'doc-booker-pro' ) . '</span>';
}

function wpddbp_clinics_addr_line( $d ) {
	return ( '' !== $d['address'] )
		? '<span class="wpddbp-clinics-addr"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-7.5 7-13a7 7 0 0 0-14 0c0 5.5 7 13 7 13Z"/><circle cx="12" cy="9" r="2.5"/></svg>' . esc_html( $d['address'] ) . '</span>'
		: '';
}

function wpddbp_clinics_view_link( $d, $attrs, $as_span = false ) {
	if ( ! wpddbp_clinics_show( $attrs, 'showViewLink' ) ) {
		return '';
	}
	$inner = esc_html( $attrs['viewText'] ?? __( 'View clinic', 'doc-booker-pro' ) ) . '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
	// $as_span avoids a nested <a> when the whole card is already an anchor.
	if ( $as_span ) {
		return '<span class="wpddbp-clinics-view">' . $inner . '</span>';
	}
	return '<a class="wpddbp-clinics-view" href="' . esc_url( $d['permalink'] ) . '">' . $inner . '</a>';
}

/* ---------- card (masonry) ---------- */

function wpddbp_clinics_render_card( $d, $attrs, $index ) {
	$archetypes = array( 'flagship', 'standard', 'pavilion', 'standard', 'duo', 'standard' );
	$type       = $archetypes[ $index % count( $archetypes ) ];
	if ( 'urgent_care' === $d['type'] ) {
		$type = 'urgent';
	}

	$img = ( wpddbp_clinics_show( $attrs, 'showImage' ) && '' !== $d['image'] )
		? '<img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">'
		: '';
	$photo = '<div class="wpddbp-clinics-photo">' . $img . wpddbp_clinics_type_chip( $d ) . wpddbp_clinics_emergency_badge( $d, $attrs ) . '</div>';

	$name    = '<h3 class="wpddbp-clinics-name">' . esc_html( $d['name'] ) . '</h3>';
	$tagline = ( wpddbp_clinics_show( $attrs, 'showTagline' ) && '' !== $d['tagline'] ) ? '<p class="wpddbp-clinics-tagline">' . esc_html( $d['tagline'] ) . '</p>' : '';

	$meta_bits = array();
	if ( wpddbp_clinics_show( $attrs, 'showStats' ) && $d['spec_count'] > 0 ) {
		$meta_bits[] = '<span><b>' . esc_html( number_format_i18n( $d['spec_count'] ) ) . '</b> ' . esc_html__( 'specialists', 'doc-booker-pro' ) . '</span>';
	}
	if ( wpddbp_clinics_show( $attrs, 'showStats' ) && '' !== $d['founded'] ) {
		$meta_bits[] = '<span>' . esc_html( sprintf( /* translators: %s year */ __( 'est. %s', 'doc-booker-pro' ), $d['founded'] ) ) . '</span>';
	}
	$meta = ! empty( $meta_bits ) ? '<div class="wpddbp-clinics-cardmeta">' . implode( '<span class="wpddbp-clinics-dot"></span>', $meta_bits ) . '</div>' : '';

	$addr = ( wpddbp_clinics_show( $attrs, 'showAddress' ) ) ? wpddbp_clinics_addr_line( $d ) : '';
	$foot = '<div class="wpddbp-clinics-cardfoot">' . $meta . wpddbp_clinics_view_link( $d, $attrs, true ) . '</div>';

	return '<a class="wpddbp-clinics-card wpddbp-cl--' . esc_attr( $type ) . '" href="' . esc_url( $d['permalink'] ) . '" data-type="' . esc_attr( $d['type'] ) . '">'
		. $photo
		. '<div class="wpddbp-clinics-cardbody">' . $name . $tagline . $addr . $foot . '</div>'
		. '</a>';
}

/* ---------- compact (dense rows) ---------- */

function wpddbp_clinics_render_compact( $d, $attrs ) {
	$thumb = ( wpddbp_clinics_show( $attrs, 'showImage' ) )
		? '<div class="wpddbp-clinics-cthumb">' . wpddbp_clinics_type_chip( $d ) . ( '' !== $d['image'] ? '<img src="' . esc_url( $d['image'] ) . '" alt="" loading="lazy">' : '' ) . '</div>'
		: '';
	$today = wpddbp_clinics_today( $d['hours'] );
	$hours = ( wpddbp_clinics_show( $attrs, 'showHours' ) && $today['has'] )
		? '<span class="wpddbp-clinics-chours' . ( $today['open_now'] ? ' is-open' : '' ) . '">' . ( $today['open_now'] ? '<span class="wpddbp-clinics-pulse"></span><b>' . esc_html__( 'Open', 'doc-booker-pro' ) . '</b> ' : '' ) . esc_html( $today['label'] ) . '</span>'
		: '';
	$addr = ( wpddbp_clinics_show( $attrs, 'showAddress' ) && '' !== $d['address'] ) ? '<span class="wpddbp-clinics-caddr">' . esc_html( $d['address'] ) . '</span>' : '';

	return '<a class="wpddbp-clinics-crow" href="' . esc_url( $d['permalink'] ) . '" data-type="' . esc_attr( $d['type'] ) . '">'
		. $thumb
		. '<span class="wpddbp-clinics-cbody"><span class="wpddbp-clinics-cname">' . esc_html( $d['name'] ) . '</span>' . $addr . $hours . '</span>'
		. '<span class="wpddbp-clinics-carrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>'
		. '</a>';
}

/* ---------- feature (alternating rows) ---------- */

function wpddbp_clinics_render_feature( $d, $attrs ) {
	$img = ( wpddbp_clinics_show( $attrs, 'showImage' ) && '' !== $d['image'] )
		? '<img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">'
		: '<span class="wpddbp-clinics-imgph" aria-hidden="true"></span>';
	$media = '<div class="wpddbp-clinics-fmedia">' . $img . wpddbp_clinics_type_chip( $d ) . wpddbp_clinics_emergency_badge( $d, $attrs ) . '</div>';

	$name    = '<h2 class="wpddbp-clinics-name"><a href="' . esc_url( $d['permalink'] ) . '">' . esc_html( $d['name'] ) . '</a></h2>';
	$tagline = ( wpddbp_clinics_show( $attrs, 'showTagline' ) && '' !== $d['tagline'] ) ? '<p class="wpddbp-clinics-flede">' . esc_html( $d['tagline'] ) . '</p>'
		: ( '' !== $d['excerpt'] ? '<p class="wpddbp-clinics-flede">' . esc_html( $d['excerpt'] ) . '</p>' : '' );

	// stats strip
	$stats = array();
	if ( $d['spec_count'] > 0 ) {
		$stats[] = '<div class="wpddbp-clinics-fstat"><span class="n">' . esc_html( number_format_i18n( $d['spec_count'] ) ) . '</span><span class="l">' . esc_html__( 'Specialists', 'doc-booker-pro' ) . '</span></div>';
	}
	if ( $d['capacity'] > 0 ) {
		$stats[] = '<div class="wpddbp-clinics-fstat"><span class="n">' . esc_html( number_format_i18n( $d['capacity'] ) ) . '</span><span class="l">' . esc_html__( 'Capacity', 'doc-booker-pro' ) . '</span></div>';
	}
	if ( '' !== $d['founded'] ) {
		$stats[] = '<div class="wpddbp-clinics-fstat"><span class="n"><em>est.</em> ' . esc_html( $d['founded'] ) . '</span><span class="l">' . esc_html__( 'Founded', 'doc-booker-pro' ) . '</span></div>';
	}
	$stats[] = '<div class="wpddbp-clinics-fstat"><span class="n">' . ( $d['emergency'] ? esc_html__( 'Yes', 'doc-booker-pro' ) : '<span class="wpddbp-clinics-no">' . esc_html__( 'No', 'doc-booker-pro' ) . '</span>' ) . '</span><span class="l">' . esc_html__( '24/7 ER', 'doc-booker-pro' ) . '</span></div>';
	$stats_html = wpddbp_clinics_show( $attrs, 'showStats' ) ? '<div class="wpddbp-clinics-fstats">' . implode( '', $stats ) . '</div>' : '';

	// contact triad
	// Contact triad — each row honors its own toggle (address/contact/hours).
	$contact = array();
	if ( wpddbp_clinics_show( $attrs, 'showAddress' ) && '' !== $d['address'] ) {
		$contact[] = '<div class="wpddbp-clinics-finfo"><span class="lab">' . esc_html__( 'Address', 'doc-booker-pro' ) . '</span><span class="val">' . esc_html( $d['address'] ) . '</span></div>';
	}
	if ( wpddbp_clinics_show( $attrs, 'showContact' ) && '' !== $d['hotline'] ) {
		$contact[] = '<div class="wpddbp-clinics-finfo"><span class="lab">' . esc_html__( 'Hotline', 'doc-booker-pro' ) . '</span><a class="val" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['hotline'] ) ) . '">' . esc_html( $d['hotline'] ) . '</a></div>';
	}
	$today = wpddbp_clinics_today( $d['hours'] );
	if ( wpddbp_clinics_show( $attrs, 'showHours' ) && $today['has'] ) {
		$contact[] = '<div class="wpddbp-clinics-finfo"><span class="lab">' . esc_html__( 'Today', 'doc-booker-pro' ) . '</span><span class="val">' . esc_html( $today['label'] ) . ( $today['open_now'] ? ' <em class="wpddbp-clinics-open">' . esc_html__( 'Open now', 'doc-booker-pro' ) . '</em>' : '' ) . '</span></div>';
	}
	$contact_html = ! empty( $contact ) ? '<div class="wpddbp-clinics-fcontact">' . implode( '', $contact ) . '</div>' : '';

	$maps    = wpddbp_clinics_maps_url( $d );
	$actions = '<div class="wpddbp-clinics-factions">' . wpddbp_clinics_view_link( $d, $attrs )
		. ( '' !== $maps ? '<a class="wpddbp-clinics-directions" href="' . esc_url( $maps ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get directions', 'doc-booker-pro' ) . ' →</a>' : '' )
		. '</div>';

	return '<article class="wpddbp-clinics-frow" data-type="' . esc_attr( $d['type'] ) . '">'
		. $media
		. '<div class="wpddbp-clinics-fbody">' . wpddbp_clinics_type_chip( $d ) . $name . $tagline . $stats_html . $contact_html . $actions . '</div>'
		. '</article>';
}

/* ---------- gallery (slideshow cards) ---------- */

function wpddbp_clinics_render_gallery( $d, $attrs ) {
	$imgs = wpddbp_clinics_show( $attrs, 'showImage' ) ? $d['images'] : array();
	if ( empty( $imgs ) ) {
		$imgs = array( '' );
	}
	$slides = '';
	foreach ( $imgs as $i => $src ) {
		$slides .= '<div class="wpddbp-clinics-gslide' . ( 0 === $i ? ' is-active' : '' ) . '"' . ( '' !== $src ? ' style="background-image:url(' . esc_url( $src ) . ')"' : '' ) . '></div>';
	}
	$dots = '';
	if ( count( $imgs ) > 1 ) {
		foreach ( $imgs as $i => $src ) {
			$dots .= '<button type="button" class="wpddbp-clinics-gdot' . ( 0 === $i ? ' is-on' : '' ) . '" data-i="' . (int) $i . '" aria-label="' . esc_attr( sprintf( __( 'Photo %d', 'doc-booker-pro' ), $i + 1 ) ) . '"></button>';
		}
	}
	$arrows = ( count( $imgs ) > 1 )
		? '<button type="button" class="wpddbp-clinics-garrow wpddbp-clinics-garrow--prev" aria-label="' . esc_attr__( 'Previous photo', 'doc-booker-pro' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6 9 12l6 6"/></svg></button>'
			. '<button type="button" class="wpddbp-clinics-garrow wpddbp-clinics-garrow--next" aria-label="' . esc_attr__( 'Next photo', 'doc-booker-pro' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>'
		: '';
	$counter = ( count( $imgs ) > 1 ) ? '<div class="wpddbp-clinics-gcounter"><b>01</b>/<span>' . sprintf( '%02d', count( $imgs ) ) . '</span></div>' : '';

	$credit_bits = array();
	if ( '' !== $d['architect'] ) {
		$credit_bits[] = esc_html( $d['architect'] );
	}
	if ( '' !== $d['photographer'] ) {
		$credit_bits[] = esc_html( $d['photographer'] );
	}
	$credit = ! empty( $credit_bits ) ? '<div class="wpddbp-clinics-gcredit">' . implode( '<br>', $credit_bits ) . '</div>' : '';

	$kicker = ( '' !== $d['founded'] ) ? '<div class="wpddbp-clinics-gkicker">' . esc_html( $d['type_label'] ) . '<i>' . esc_html( sprintf( __( 'est. %s', 'doc-booker-pro' ), $d['founded'] ) ) . '</i></div>' : '<div class="wpddbp-clinics-gkicker">' . esc_html( $d['type_label'] ) . '</div>';

	$gaddr = wpddbp_clinics_show( $attrs, 'showAddress' ) ? wpddbp_clinics_addr_line( $d ) : '';
	$gcta  = wpddbp_clinics_show( $attrs, 'showViewLink' )
		? '<a class="wpddbp-clinics-gcta" href="' . esc_url( $d['permalink'] ) . '">' . esc_html( $attrs['viewText'] ?? __( 'View clinic', 'doc-booker-pro' ) ) . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>'
		: '';

	return '<article class="wpddbp-clinics-gcard" data-type="' . esc_attr( $d['type'] ) . '" data-count="' . count( $imgs ) . '">'
		. '<div class="wpddbp-clinics-gslides">' . $slides . '</div>'
		. $counter . wpddbp_clinics_type_chip( $d ) . wpddbp_clinics_emergency_badge( $d, $attrs ) . $credit . $arrows
		. ( '' !== $dots ? '<div class="wpddbp-clinics-gdots">' . $dots . '</div>' : '' )
		. '<div class="wpddbp-clinics-gmeta"><div class="wpddbp-clinics-gnamewrap">' . $kicker
		. '<h2 class="wpddbp-clinics-name">' . esc_html( $d['name'] ) . '</h2>'
		. $gaddr . '</div>'
		. $gcta . '</div>'
		. '</article>';
}

/* ---------- map (list + faux-map pins) ---------- */

function wpddbp_clinics_render_map( $clinics, $attrs ) {
	$rows     = '';
	$map_data = array();
	foreach ( $clinics as $i => $d ) {
		$today = wpddbp_clinics_today( $d['hours'] );
		$sel   = ( 0 === $i ) ? ' is-selected' : '';

		// list row
		$chips = wpddbp_clinics_type_chip( $d ) . wpddbp_clinics_emergency_badge( $d, $attrs );
		$hours = ( wpddbp_clinics_show( $attrs, 'showHours' ) && $today['has'] ) ? '<div class="wpddbp-clinics-mline"><span>' . esc_html( $today['label'] ) . '</span>' . ( $today['open_now'] ? '<span class="wpddbp-clinics-open">' . esc_html__( 'Open now', 'doc-booker-pro' ) . '</span>' : '' ) . '</div>' : '';
		$phone = ( wpddbp_clinics_show( $attrs, 'showContact' ) && '' !== $d['hotline'] ) ? '<div class="wpddbp-clinics-mline"><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['hotline'] ) ) . '" onclick="event.stopPropagation()">' . esc_html( $d['hotline'] ) . '</a></div>' : '';
		$footbits = array();
		if ( '' !== $d['founded'] ) {
			$footbits[] = esc_html( sprintf( __( 'Founded %s', 'doc-booker-pro' ), $d['founded'] ) );
		}
		if ( $d['spec_count'] > 0 ) {
			$footbits[] = esc_html( sprintf( _n( '%s specialist', '%s specialists', $d['spec_count'], 'doc-booker-pro' ), number_format_i18n( $d['spec_count'] ) ) );
		}
		$foot = ( wpddbp_clinics_show( $attrs, 'showStats' ) && ! empty( $footbits ) ) ? '<div class="wpddbp-clinics-mfoot">' . implode( ' · ', $footbits ) . '</div>' : '';

		$mthumb_img = ( wpddbp_clinics_show( $attrs, 'showImage' ) && '' !== $d['image'] ) ? '<img src="' . esc_url( $d['image'] ) . '" alt="" loading="lazy">' : '';
		$maddr      = wpddbp_clinics_show( $attrs, 'showAddress' ) ? wpddbp_clinics_addr_line( $d ) : '';
		$view       = '<a class="wpddbp-clinics-mview" href="' . esc_url( $d['permalink'] ) . '" onclick="event.stopPropagation()">' . esc_html__( 'View clinic', 'doc-booker-pro' ) . ' &rarr;</a>';
		$rows      .= '<div class="wpddbp-clinics-mrow' . $sel . '" role="button" tabindex="0" data-i="' . (int) $i . '" data-permalink="' . esc_url( $d['permalink'] ) . '" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '" aria-label="' . esc_attr( $d['name'] ) . '">'
			. '<span class="wpddbp-clinics-mthumb">' . $mthumb_img . '<span class="wpddbp-clinics-mnum">' . sprintf( '%02d', $i + 1 ) . '</span></span>'
			. '<div class="wpddbp-clinics-mbody"><span class="wpddbp-clinics-mname">' . esc_html( $d['name'] ) . '</span>'
			. '<span class="wpddbp-clinics-mchips">' . $chips . '</span>'
			. $maddr . $hours . $phone . $foot . $view . '</div></div>';

		// marker data for the real (lazy-loaded) Leaflet map
		$map_data[] = array(
			'i'     => (int) $i,
			'name'  => $d['name'],
			'type'  => $d['type_label'],
			'count' => (int) $d['spec_count'],
			'addr'  => $d['address'],
			'lat'   => ( '' !== $d['lat'] ) ? (float) $d['lat'] : null,
			'lng'   => ( '' !== $d['lng'] ) ? (float) $d['lng'] : null,
			'url'   => $d['permalink'],
		);
	}

	// Leaflet assets (bundled by the free plugin); loaded on demand by view.js when the map scrolls into view.
	$leaflet_css = function_exists( 'wpddb' ) ? wpddb()->get_assets_uri( 'vendor/leaflet/css/leaflet.min.css' ) : '';
	$leaflet_js  = function_exists( 'wpddb' ) ? wpddb()->get_assets_uri( 'vendor/leaflet/js/leaflet.min.js' ) : '';

	$map = '<div class="wpddbp-clinics-map" data-clinics="' . esc_attr( wp_json_encode( $map_data ) ) . '"'
		. ' data-leaflet-css="' . esc_url( $leaflet_css ) . '" data-leaflet-js="' . esc_url( $leaflet_js ) . '">'
		. '<span class="wpddbp-clinics-map-ph" aria-hidden="true"></span>'
		. '</div>';

	return '<div class="wpddbp-clinics-board">'
		. '<div class="wpddbp-clinics-listpane"><div class="wpddbp-clinics-listscroll">' . $rows . '</div></div>'
		. '<div class="wpddbp-clinics-mappane">' . $map . '</div>'
		. '</div>';
}

/**
 * Render the whole clinics block body. Used by render.php.
 *
 * @param array $attrs Block attributes.
 * @return string
 */
function wpddbp_clinics_render( $attrs ) {
	$layout = $attrs['layoutType'] ?? 'card';
	$posts  = wpddbp_clinics_query( $attrs );

	if ( empty( $posts ) ) {
		return '<p class="wpddbp-clinics-empty">' . esc_html( $attrs['emptyText'] ?? __( 'No clinics found.', 'doc-booker-pro' ) ) . '</p>';
	}

	$clinics = array();
	foreach ( $posts as $p ) {
		$clinics[] = wpddbp_clinics_card_data( $p->ID, $attrs );
	}

	if ( 'map' === $layout ) {
		return wpddbp_clinics_render_map( $clinics, $attrs );
	}

	$html = '';
	foreach ( $clinics as $i => $d ) {
		if ( 'feature' === $layout ) {
			$html .= wpddbp_clinics_render_feature( $d, $attrs );
		} elseif ( 'gallery' === $layout ) {
			$html .= wpddbp_clinics_render_gallery( $d, $attrs );
		} elseif ( 'compact' === $layout ) {
			$html .= wpddbp_clinics_render_compact( $d, $attrs );
		} else {
			$html .= wpddbp_clinics_render_card( $d, $attrs, $i );
		}
	}

	$wrap_class = 'wpddbp-clinics-' . ( 'card' === $layout ? 'grid' : ( 'compact' === $layout ? 'compactgrid' : ( 'gallery' === $layout ? 'gallerygrid' : 'featurelist' ) ) );
	return '<div class="' . esc_attr( $wrap_class ) . '">' . $html . '</div>';
}
