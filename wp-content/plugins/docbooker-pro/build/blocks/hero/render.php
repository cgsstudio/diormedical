<?php
/**
 * Hero — server render (5 layouts).
 *
 * image-forward / mirror (framed image + floating stat/rating/credential cards +
 * trust strip), centered-overlay (full-bleed image + scrim), split-action
 * (grounded booking entry card; tabs enhanced by view.js), minimal (no image).
 * All author text escaped; floating-card icons come from the ported icon map.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/info-box-icons.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'image-forward';
$allowed  = array( 'image-forward', 'mirror', 'centered-overlay', 'split-action', 'minimal' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'image-forward';
}

$align       = $attributes['align'] ?? 'full';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$on_dark = ( 'centered-overlay' === $layout );

/* ---- atoms ---- */
$d_eyebrow = ! isset( $attributes['showEyebrow'] ) || $attributes['showEyebrow'];
$eyebrow   = $attributes['eyebrowText'] ?? '';
$centered  = ( 'centered-overlay' === $layout ) || ( 'center' === ( $attributes['contentAlign'] ?? 'left' ) );
$eyebrow_el = ( $d_eyebrow && '' !== $eyebrow )
	? '<p class="wpddbp-hero-eyebrow' . ( $centered ? ' is-centered' : '' ) . ( $on_dark ? ' is-light' : '' ) . '">' . esc_html( $eyebrow ) . '</p>'
	: '';

$h_tag   = $attributes['headingTag'] ?? 'h1';
if ( ! in_array( $h_tag, array( 'h1', 'h2', 'h3', 'h4' ), true ) ) {
	$h_tag = 'h1';
}
$h_text   = $attributes['headingText'] ?? '';
$h_accent = $attributes['headingAccent'] ?? '';
$accent_el = ( '' !== $h_accent ) ? ' <em class="wpddbp-hero-accent">' . esc_html( $h_accent ) . '</em>' : '';
$heading_el = ( '' !== $h_text || '' !== $h_accent )
	? '<' . $h_tag . ' class="wpddbp-hero-heading">' . esc_html( $h_text ) . $accent_el . '</' . $h_tag . '>'
	: '';

$d_lede  = ! isset( $attributes['showLede'] ) || $attributes['showLede'];
$lede    = $attributes['ledeText'] ?? '';
$lede_el = ( $d_lede && '' !== $lede ) ? '<p class="wpddbp-hero-lede">' . esc_html( $lede ) . '</p>' : '';

$btn = function ( $text, $url, $newtab, $nofollow, $variant ) use ( $on_dark ) {
	if ( '' === trim( (string) $text ) ) {
		return '';
	}
	$href   = $url ? esc_url( $url ) : '#';
	$rel    = array();
	if ( $newtab ) {
		$rel[] = 'noopener';
		$rel[] = 'noreferrer';
	}
	if ( $nofollow ) {
		$rel[] = 'nofollow';
	}
	$rel_attr = $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '';
	$target   = $newtab ? ' target="_blank"' : '';
	$dark     = $on_dark ? ' is-on-dark' : '';
	$arrow    = ( 'primary' === $variant ) ? ' <span class="wpddbp-hero-arrow" aria-hidden="true">&rarr;</span>' : '';
	return '<a class="wpddbp-hero-btn wpddbp-hero-btn--' . $variant . $dark . '" href="' . $href . '"' . $target . $rel_attr . '>' . esc_html( $text ) . $arrow . '</a>';
};
$ctas = '';
if ( ! isset( $attributes['showPrimaryBtn'] ) || $attributes['showPrimaryBtn'] ) {
	$ctas .= $btn( $attributes['primaryBtnText'] ?? '', $attributes['primaryBtnURL'] ?? '', ! empty( $attributes['primaryBtnNewTab'] ), ! empty( $attributes['primaryBtnNoFollow'] ), 'primary' );
}
if ( ! isset( $attributes['showSecondaryBtn'] ) || $attributes['showSecondaryBtn'] ) {
	$ctas .= $btn( $attributes['secondaryBtnText'] ?? '', $attributes['secondaryBtnURL'] ?? '', ! empty( $attributes['secondaryBtnNewTab'] ), ! empty( $attributes['secondaryBtnNoFollow'] ), 'ghost' );
}
$ctas_el = ( '' !== $ctas ) ? '<div class="wpddbp-hero-ctas">' . $ctas . '</div>' : '';

/* ---- trust strip ---- */
$trust_el = '';
if ( ( ! isset( $attributes['showTrustStrip'] ) || $attributes['showTrustStrip'] ) && 'centered-overlay' !== $layout ) {
	$items = isset( $attributes['trustItems'] ) && is_array( $attributes['trustItems'] ) ? $attributes['trustItems'] : array();
	if ( ! empty( $items ) ) {
		$rows = '';
		foreach ( $items as $it ) {
			$num  = esc_html( $it['number'] ?? '' );
			$unit = ( '' !== ( $it['unit'] ?? '' ) ) ? '<em>' . esc_html( $it['unit'] ) . '</em>' : '';
			$rows .= '<div class="wpddbp-hero-ts-item"><span class="wpddbp-hero-ts-num">' . $num . $unit . '</span><span class="wpddbp-hero-ts-label">' . esc_html( $it['label'] ?? '' ) . '</span></div>';
		}
		$no_div   = ( isset( $attributes['trustDivider'] ) && ! $attributes['trustDivider'] ) ? ' no-divider' : '';
		$trust_el = '<div class="wpddbp-hero-trust' . ( $on_dark ? ' is-light' : '' ) . $no_div . '" aria-label="' . esc_attr__( 'Statistics', 'doc-booker-pro' ) . '">' . $rows . '</div>';
	}
}

/* ---- floating cards ---- */
$fcards_el = '';
if ( ( ! isset( $attributes['showFloatingCards'] ) || $attributes['showFloatingCards'] ) && ( 'image-forward' === $layout || 'mirror' === $layout ) ) {
	$cards = isset( $attributes['floatingCards'] ) && is_array( $attributes['floatingCards'] ) ? $attributes['floatingCards'] : array();
	$i     = 0;
	foreach ( $cards as $c ) {
		$type = $c['type'] ?? 'stat';
		$pos  = in_array( ( $c['position'] ?? 'tl' ), array( 'tl', 'tr', 'bl', 'br' ), true ) ? $c['position'] : 'tl';
		$flt  = ! empty( $c['float'] ) ? ( ( 0 === $i % 2 ) ? ' is-float-a' : ' is-float-b' ) : '';
		$val  = esc_html( $c['value'] ?? '' );
		$lbl  = esc_html( $c['label'] ?? '' );
		if ( 'rating' === $type ) {
			$inner = '<span class="wpddbp-hero-fc-stars" aria-hidden="true">★★★★★</span><span class="wpddbp-hero-fc-value">' . $val . '</span><span class="wpddbp-hero-fc-meta">' . $lbl . '</span>';
		} elseif ( 'credential' === $type ) {
			$inner = '<span class="wpddbp-hero-fc-dot" aria-hidden="true"></span><span class="wpddbp-hero-fc-text">' . $val . '</span>';
		} else {
			$icon  = wpddbp_get_info_box_icon_svg( $c['icon'] ?? 'activity' );
			$inner = '<span class="wpddbp-hero-fc-icon" aria-hidden="true">' . $icon . '</span><span class="wpddbp-hero-fc-value">' . $val . '</span><span class="wpddbp-hero-fc-label">' . $lbl . '</span>';
		}
		$fcards_el .= '<div class="wpddbp-hero-fcard wpddbp-hero-fcard--' . esc_attr( $type ) . ' is-' . esc_attr( $pos ) . $flt . '" aria-hidden="true">' . $inner . '</div>';
		$i++;
	}
}

/* ---- media frame ---- */
$img_url = $attributes['imageUrl'] ?? '';
$img_alt = esc_attr( $attributes['imageAlt'] ?? '' );
$aspect  = esc_attr( $attributes['imageAspectRatio'] ?? '4 / 5' );
$img_tag = ( '' !== $img_url ) ? '<img src="' . esc_url( $img_url ) . '" alt="' . $img_alt . '" loading="lazy">' : '';
$media_el = '<div class="wpddbp-hero-media">'
	. '<span class="wpddbp-hero-blob wpddbp-hero-blob-1" aria-hidden="true"></span>'
	. '<span class="wpddbp-hero-blob wpddbp-hero-blob-2" aria-hidden="true"></span>'
	. '<span class="wpddbp-hero-ring" aria-hidden="true"></span>'
	. '<div class="wpddbp-hero-frame" style="aspect-ratio:' . $aspect . '">' . $img_tag . '</div>'
	. $fcards_el
	. '</div>';

/* ---- caption (centered) ---- */
$caption_el = ( ! empty( $attributes['showCaption'] ) && '' !== ( $attributes['captionText'] ?? '' ) )
	? '<p class="wpddbp-hero-cap">' . esc_html( $attributes['captionText'] ) . '</p>'
	: '';

/* ---- action card (split-action) ---- */
$ac_el = '';
if ( 'split-action' === $layout ) {
	$dynamic = ( 'dynamic' === ( $attributes['acDataMode'] ?? 'static' ) );

	// Resolve REAL featured data (doctor / department / clinic / earliest slot).
	$hero_doctors = array();
	$primary_id   = 0;
	$dept         = array( 'id' => 0, 'name' => '' );
	$clinic       = array( 'id' => 0, 'name' => '' );
	$slot         = null;
	$slot_label   = '';
	if ( $dynamic ) {
		require_once dirname( __DIR__, 2 ) . '/utils/hero-booking-data.php';
		$doc_ids = wpddbp_hero_doctor_ids( $attributes['acDoctorIds'] ?? array() );
		foreach ( $doc_ids as $did ) {
			$hero_doctors[] = array( 'id' => (int) $did, 'name' => get_the_title( $did ) );
		}
		$primary_id = $doc_ids ? (int) $doc_ids[0] : 0;
		if ( $primary_id ) {
			$dept = wpddbp_hero_doctor_department( $primary_id );
			$cid  = (int) ( $attributes['acClinicId'] ?? 0 );
			if ( $cid ) {
				$clinic = array( 'id' => $cid, 'name' => get_the_title( $cid ) );
			} else {
				$dc     = wpddbp_hero_doctor_clinics( $primary_id );
				$clinic = $dc ? reset( $dc ) : array( 'id' => 0, 'name' => '' );
			}
			$slot       = wpddbp_hero_earliest_slot( $primary_id, (int) $clinic['id'] );
			$slot_label = wpddbp_hero_format_slot( $slot );
		}
	}

	$single_doctor = $dynamic && count( $hero_doctors ) === 1;

	// Field display values: dynamic real data overrides the static placeholders.
	$v_dept   = ( $dynamic && '' !== $dept['name'] )   ? $dept['name']            : ( $attributes['acField1Value'] ?? '' );
	$v_clinic = ( $dynamic && '' !== $clinic['name'] ) ? $clinic['name']          : ( $attributes['acField2Value'] ?? '' );
	$v_doctor = ( $dynamic && $primary_id )            ? get_the_title( $primary_id ) : ( $attributes['acField3Value'] ?? '' );
	$v_time   = ( $dynamic && '' !== $slot_label )     ? $slot_label              : ( $attributes['acField4Value'] ?? '' );

	// A field renders as an interactive dropdown only in dynamic mode; `$kind` empty ⇒ fixed (no dropdown).
	$ac_field = function ( $label, $value, $icon, $kind ) use ( $dynamic ) {
		$ic          = wpddbp_get_info_box_icon_svg( $icon );
		$interactive = ( '' !== $kind );
		$attr        = $interactive
			? ' data-hero-select="' . esc_attr( $kind ) . '" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false"'
			: '';
		// Chevron: on interactive dropdowns, and on static decorative fields (keeps the
		// original look). Hidden only on dynamic FIXED fields (single-doctor mode).
		$chev  = ( $interactive || ! $dynamic ) ? '<span class="wpddbp-hero-ac-chev" aria-hidden="true">&#9662;</span>' : '';
		$state = $interactive ? ' is-interactive' : ( $dynamic ? ' is-fixed' : '' );
		return '<div class="wpddbp-hero-ac-field"><label>' . esc_html( $label ) . '</label>'
			. '<div class="wpddbp-hero-ac-select' . $state . '"' . $attr . '>'
			. '<span class="wpddbp-hero-ac-ic" aria-hidden="true">' . $ic . '</span>'
			. '<span class="wpddbp-hero-ac-val">' . esc_html( $value ) . '</span>'
			. $chev . '</div></div>';
	};

	// In dynamic mode fields become dropdowns.
	//
	// Single featured doctor: the admin has pinned WHO (and, via acClinicId, WHERE), so
	// dept/clinic/doctor stay fixed — but the visitor must still choose WHEN. The date
	// field therefore stays a dropdown whenever we're dynamic, which is what the block
	// inspector already promises ("the doctor dropdown is hidden and visitors go straight
	// to picking a time"). Previously $k_date was blanked here too, so the time field
	// rendered with a chevron but never opened.
	$k_dept   = ( $dynamic && ! $single_doctor ) ? 'dept' : '';
	$k_clinic = ( $dynamic && ! $single_doctor ) ? 'clinic' : '';
	$k_doctor = ( $dynamic && ! $single_doctor ) ? 'doctor' : '';
	$k_date   = $dynamic ? 'date' : '';

	$ac_btn = $btn( $attributes['acButtonText'] ?? '', $attributes['acButtonURL'] ?? '', ! empty( $attributes['acButtonNewTab'] ), false, 'primary' );

	if ( $single_doctor ) {
		// One doctor: drop the tabs + department pane; show just [Doctor fixed] + [Earliest time].
		$tabs_html  = '';
		$panes_html = '<div class="wpddbp-hero-ac-pane is-active" data-hero-pane="doctor">'
			. $ac_field( $attributes['acField3Label'] ?? '', $v_doctor, $attributes['acField3Icon'] ?? 'user', '' )
			. $ac_field( $attributes['acField4Label'] ?? '', $v_time, $attributes['acField4Icon'] ?? 'calendar', $k_date )
			. '</div>';
	} else {
		$tabs_html  = '<div class="wpddbp-hero-ac-tabs" role="tablist">'
			. '<button class="wpddbp-hero-ac-tab" role="tab" aria-selected="true" data-hero-tab="dept">' . esc_html( $attributes['acTab1Label'] ?? '' ) . '</button>'
			. '<button class="wpddbp-hero-ac-tab" role="tab" aria-selected="false" data-hero-tab="doctor">' . esc_html( $attributes['acTab2Label'] ?? '' ) . '</button>'
			. '</div>';
		$panes_html = '<div class="wpddbp-hero-ac-pane is-active" data-hero-pane="dept">'
			. $ac_field( $attributes['acField1Label'] ?? '', $v_dept, $attributes['acField1Icon'] ?? 'heart', $k_dept )
			. $ac_field( $attributes['acField2Label'] ?? '', $v_clinic, $attributes['acField2Icon'] ?? 'map-pin', $k_clinic )
			. '</div>'
			. '<div class="wpddbp-hero-ac-pane" data-hero-pane="doctor" hidden>'
			. $ac_field( $attributes['acField3Label'] ?? '', $v_doctor, $attributes['acField3Icon'] ?? 'user', $k_doctor )
			. $ac_field( $attributes['acField4Label'] ?? '', $v_time, $attributes['acField4Icon'] ?? 'calendar', $k_date )
			. '</div>';
	}

	// Config for view.js (dropdowns + inline modal). Booking-form reads ?doctor/?dept/?clinic.
	$hero_cfg = array(
		'apiUrl'        => esc_url_raw( rest_url() ),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
		'hasPro'        => function_exists( 'wpddbp' ),
		'dynamic'       => $dynamic,
		'inlineBooking' => ! empty( $attributes['acInlineBooking'] ),
		'singleDoctor'  => $single_doctor,
		'doctors'       => $hero_doctors,
		// Did the ADMIN actually pin this list? With acDoctorIds empty, $hero_doctors is
		// just "every published doctor" (wpddbp_hero_doctor_ids falls back to that) — the
		// frontend must be able to tell the two apart, or it treats "all" as a curated
		// list and stops narrowing by the visitor's department/clinic.
		'doctorsPinned' => ! empty( array_filter( (array) ( $attributes['acDoctorIds'] ?? array() ) ) ),
		'primaryDoctor' => $primary_id,
		'clinicId'      => (int) $clinic['id'],
		'deptId'        => (int) $dept['id'],
		'slot'          => $slot,
		'buttonUrl'     => (string) ( $attributes['acButtonURL'] ?? '#' ),
	);
	$cfg_attr = $dynamic ? ' data-hero-config="' . esc_attr( wp_json_encode( $hero_cfg ) ) . '"' : '';

	$ac_el = '<div class="wpddbp-hero-ac' . ( $dynamic ? ' is-dynamic' : '' ) . ( $single_doctor ? ' is-single' : '' ) . '" data-tabs' . $cfg_attr . ' role="region" aria-label="' . esc_attr__( 'Start your booking', 'doc-booker-pro' ) . '">'
		. '<div class="wpddbp-hero-ac-head"><h3 class="wpddbp-hero-ac-title">' . esc_html( $attributes['acTitle'] ?? '' ) . '</h3><span class="wpddbp-hero-ac-live"><span class="wpddbp-hero-ac-dot" aria-hidden="true"></span>' . esc_html( $attributes['acLiveText'] ?? '' ) . '</span></div>'
		. $tabs_html . $panes_html . $ac_btn
		. '<div class="wpddbp-hero-ac-foot"><span class="wpddbp-hero-ac-dot" aria-hidden="true"></span>' . esc_html( $attributes['acFootText'] ?? '' ) . '</div>'
		. '</div>';

	// Inline booking modal — the hero mounts the booking-form here on demand.
	if ( $dynamic && ! empty( $attributes['acInlineBooking'] ) ) {
		// Make sure the booking-form block's runtime (view.js + styles) is loaded even
		// on pages that don't otherwise contain a booking-form block.
		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			$bf = WP_Block_Type_Registry::get_instance()->get_registered( 'doc-booker-pro/booking-form' );
			if ( $bf ) {
				foreach ( (array) $bf->view_script_handles as $h ) { wp_enqueue_script( $h ); }
				foreach ( (array) $bf->style_handles as $h ) { wp_enqueue_style( $h ); }
			}
		}
		$ac_el .= '<div class="wpddbp-hero-modal" data-hero-modal hidden aria-hidden="true">'
			. '<div class="wpddbp-hero-modal-backdrop" data-hero-modal-close></div>'
			. '<div class="wpddbp-hero-modal-card" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Book an appointment', 'doc-booker-pro' ) . '">'
			. '<div class="wpddbp-hero-modal-head">'
			. '<span class="wpddbp-hero-modal-title">' . esc_html__( 'Book an appointment', 'doc-booker-pro' ) . '</span>'
			. '<button type="button" class="wpddbp-hero-modal-x" data-hero-modal-close aria-label="' . esc_attr__( 'Close', 'doc-booker-pro' ) . '">&times;</button>'
			. '</div>'
			. '<div class="wpddbp-hero-modal-body"></div>'
			. '</div></div>';
	}
}

/* ---- copy column ---- */
$copy_el = '<div class="wpddbp-hero-copy">' . $eyebrow_el . $heading_el . $lede_el . ( 'split-action' === $layout ? '' : $ctas_el ) . $trust_el . '</div>';

/* ---- assemble per layout ---- */
switch ( $layout ) {
	case 'mirror':
		$inner = '<div class="wpddbp-hero-inner wpddbp-hero-grid">' . $media_el . $copy_el . '</div>';
		break;

	case 'centered-overlay':
		$bg     = ( '' !== $img_url ) ? '<img class="wpddbp-hero-bg" src="' . esc_url( $img_url ) . '" alt="' . $img_alt . '" loading="lazy">' : '';
		$op     = max( 0, min( 100, (int) ( $attributes['scrimOpacity'] ?? 72 ) ) ) / 100;
		$scrim  = '<span class="wpddbp-hero-scrim" aria-hidden="true" style="opacity:' . esc_attr( $op ) . '"></span>';
		$inner  = $bg . $scrim . '<div class="wpddbp-hero-inner wpddbp-hero-centered">' . $eyebrow_el . $heading_el . $lede_el . $ctas_el . '</div>' . $caption_el;
		break;

	case 'split-action':
		$ac_thumb = ( '' !== $img_url )
			? '<div class="wpddbp-hero-ac-thumb"><img src="' . esc_url( $img_url ) . '" alt="' . $img_alt . '" loading="lazy"></div>'
			: '';
		$inner = '<div class="wpddbp-hero-inner wpddbp-hero-grid">' . $copy_el . '<div class="wpddbp-hero-actionwrap">' . $ac_thumb . $ac_el . '</div></div>';
		break;

	case 'minimal':
		$inner = '<span class="wpddbp-hero-shape wpddbp-hero-shape-1" aria-hidden="true"></span><span class="wpddbp-hero-shape wpddbp-hero-shape-2" aria-hidden="true"></span>'
			. '<div class="wpddbp-hero-inner wpddbp-hero-minimal">' . $eyebrow_el . $heading_el . $lede_el . $ctas_el . $trust_el . '</div>';
		break;

	case 'image-forward':
	default:
		$inner = '<div class="wpddbp-hero-inner wpddbp-hero-grid">' . $copy_el . $media_el . '</div>';
		break;
}

$min_h     = (int) ( $attributes['sectionMinHeight'] ?? 0 );
$min_style = ( $min_h > 0 ) ? ' style="min-height:' . esc_attr( $min_h ) . 'px"' : '';

$root_class = 'wpddbp-hero wpddbp-hero--' . $layout . $align_class
	. ( $centered ? ' is-centered' : '' )
	. ( '' === $img_url ? ' has-no-image' : '' );

printf(
	'<div id="%1$s" class="%2$s"%3$s>%4$s</div>',
	esc_attr( $block_id ),
	esc_attr( $root_class ),
	$min_style, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr above.
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() above.
);
