<?php
/**
 * Quick Book block — doctor resolution + variant renderers (Tier C, Phase 5).
 *
 * A compact single-doctor booking widget in 3 variants (sidebar / sticky / hero).
 * Everything visible is REAL doctor meta (name / department / rating / fee / image)
 * — the approved mockup's decorative day-pills, time-slots and inline name+phone
 * fields are intentionally dropped (fabricated availability; grounding rule, see
 * memory `docbooker-real-features-grounding`). The single interaction is the shared
 * booking-button helper, which routes to the live Pro flow (Stripe / Razorpay /
 * fallback) exactly like the doctor-detail page.
 *
 * Standalone rule (architecture §3): namespaced `wpddbp_quickbook_*`, reads the CPT
 * via the already-standalone `wpddbp_doctors_card_data()` + booking helpers.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/doctors-data.php';

/**
 * Resolve the doctor post id for a Quick Book instance.
 * Priority: ?{urlParam}= override (opt-in) > picked doctor > current doctor page.
 *
 * @param array $attrs Block attributes.
 * @return int Doctor post id (0 when none resolvable).
 */
function wpddbp_quickbook_resolve_doctor_id( $attrs ) {
	// 1. URL param override (e.g. ?doctor=12) — opt-in via the inspector.
	if ( ! empty( $attrs['urlParamOverride'] ) ) {
		$param = sanitize_key( $attrs['urlParam'] ?? 'doctor' );
		if ( '' !== $param && isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pid = (int) $_GET[ $param ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $pid > 0 && 'wpddb_doctor' === get_post_type( $pid ) ) {
				return $pid;
			}
		}
	}

	// 2. Current-doctor source.
	if ( 'current' === ( $attrs['source'] ?? 'specific' ) ) {
		$id = get_the_ID();
		return ( $id && 'wpddb_doctor' === get_post_type( $id ) ) ? (int) $id : 0;
	}

	// 3. Specific doctor (falling back to the current doctor page when unset).
	$pid = (int) ( $attrs['doctorId'] ?? 0 );
	if ( ! $pid ) {
		$id = get_the_ID();
		if ( $id && 'wpddb_doctor' === get_post_type( $id ) ) {
			$pid = (int) $id;
		}
	}
	return $pid;
}

/** Avatar with a live presence dot (or '' when hidden / no image). */
function wpddbp_quickbook_avatar( $d, $attrs ) {
	if ( empty( $attrs['showAvatar'] ) ) {
		return '';
	}
	$img = $d['image']
		? '<img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">'
		: '';
	return '<span class="wpddbp-qb-av">' . $img . '<span class="wpddbp-qb-live" aria-hidden="true"></span></span>';
}

/** Star + numeric rating (+ optional review count). Only when rating > 0. */
function wpddbp_quickbook_rating( $d, $attrs ) {
	if ( empty( $attrs['showRating'] ) || $d['rating'] <= 0 ) {
		return '';
	}
	$val     = rtrim( rtrim( number_format( (float) $d['rating'], 2, '.', '' ), '0' ), '.' );
	$reviews = ( $d['review_count'] > 0 )
		? '<em>&middot; ' . esc_html( number_format_i18n( $d['review_count'] ) ) . ' ' . esc_html__( 'reviews', 'doc-booker-pro' ) . '</em>'
		: '';
	return '<span class="wpddbp-qb-rating">'
		. '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>'
		. '<b>' . esc_html( $val ) . '</b>' . $reviews . '</span>';
}

/** Standalone fee callout (label + formatted amount). Only when fee > 0. */
function wpddbp_quickbook_fee( $d, $attrs ) {
	if ( empty( $attrs['showFee'] ) || $d['fee'] <= 0 ) {
		return '';
	}
	$from = ( '' !== ( $attrs['feeLabel'] ?? '' ) )
		? '<span class="wpddbp-qb-from">' . esc_html( $attrs['feeLabel'] ) . '</span>'
		: '';
	return '<span class="wpddbp-qb-fee">' . $from . '<b>' . esc_html( wpddbp_format_fee( $d['fee'] ) ) . '</b></span>';
}

/** Author-set status chip (e.g. "Accepting new patients"); never auto-generated. */
function wpddbp_quickbook_tag( $attrs ) {
	if ( empty( $attrs['showTag'] ) || '' === trim( (string) ( $attrs['tagText'] ?? '' ) ) ) {
		return '';
	}
	return '<span class="wpddbp-qb-tag">' . esc_html( $attrs['tagText'] ) . '</span>';
}

/** Eyebrow ("Quick Book" + sage dot). */
function wpddbp_quickbook_eyebrow( $attrs ) {
	$text = trim( (string) ( $attrs['eyebrowText'] ?? '' ) );
	if ( '' === $text ) {
		return '';
	}
	return '<span class="wpddbp-qb-eye"><span class="wpddbp-qb-dot" aria-hidden="true"></span>' . esc_html( $text ) . '</span>';
}

/**
 * Trust line. Gateway-aware + grounded: if the author typed a trust line it wins;
 * otherwise, when online payment is enabled we name the real active gateway.
 */
function wpddbp_quickbook_trust( $attrs ) {
	if ( empty( $attrs['showTrust'] ) ) {
		return '';
	}
	$text = trim( (string) ( $attrs['trustText'] ?? '' ) );
	if ( '' === $text && wpddbp_payment_enabled() ) {
		$gateway = wpddbp_active_gateway();
		/* translators: %s: payment gateway name (Stripe / Razorpay). */
		$text = sprintf( __( 'Secured by %s', 'doc-booker-pro' ), ucfirst( $gateway ) );
	}
	if ( '' === $text ) {
		return '';
	}
	return '<span class="wpddbp-qb-trust"><span class="wpddbp-qb-tdot" aria-hidden="true"></span>' . esc_html( $text ) . '</span>';
}

/** The booking CTA (real booking-button helper → Stripe / Razorpay / fallback). */
function wpddbp_quickbook_cta( $d, $attrs ) {
	return wpddbp_render_doctor_book_button(
		$d['id'],
		array(
			'label'    => $attrs['buttonText'] ?? __( 'Book Now', 'doc-booker-pro' ),
			'class'    => 'wpddbp-qb-cta',
			'show_fee' => ! empty( $attrs['showFeeInButton'] ),
		)
	);
}

/** "Pick a different doctor" link (author-set URL). */
function wpddbp_quickbook_pick_link( $attrs ) {
	if ( empty( $attrs['showPickLink'] ) || '' === trim( (string) ( $attrs['pickLinkUrl'] ?? '' ) ) ) {
		return '';
	}
	$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
	return '<a class="wpddbp-qb-pick" href="' . esc_url( $attrs['pickLinkUrl'] ) . '">'
		. esc_html( $attrs['pickLinkText'] ?? __( 'Pick a different doctor', 'doc-booker-pro' ) ) . $arrow . '</a>';
}

/** Department label (real taxonomy term) or ''. */
function wpddbp_quickbook_dept( $d, $attrs ) {
	if ( empty( $attrs['showDept'] ) || '' === $d['dept_name'] ) {
		return '';
	}
	return '<span class="wpddbp-qb-role">' . esc_html( $d['dept_name'] ) . '</span>';
}

/** Doctor display name. */
function wpddbp_quickbook_name( $d, $tag = 'span' ) {
	return '<' . $tag . ' class="wpddbp-qb-name">' . esc_html( $d['name'] ) . '</' . $tag . '>';
}

/**
 * Render the Quick Book widget for the resolved doctor.
 *
 * @param array  $attrs    Block attributes.
 * @param array  $d        Normalized doctor data (from wpddbp_doctors_card_data()).
 * @param string $block_id Unique id (unused in markup, kept for parity).
 * @return string
 */
function wpddbp_quickbook_render_widget( $attrs, $d, $block_id = '' ) {
	$layout = in_array( ( $attrs['layoutType'] ?? 'sidebar' ), array( 'sidebar', 'sticky', 'hero' ), true ) ? $attrs['layoutType'] : 'sidebar';

	$avatar = wpddbp_quickbook_avatar( $d, $attrs );
	$name   = wpddbp_quickbook_name( $d, ( 'sidebar' === $layout ) ? 'span' : 'div' );
	$dept   = wpddbp_quickbook_dept( $d, $attrs );
	$rating = wpddbp_quickbook_rating( $d, $attrs );
	$fee    = wpddbp_quickbook_fee( $d, $attrs );
	$cta    = wpddbp_quickbook_cta( $d, $attrs );
	$eye    = wpddbp_quickbook_eyebrow( $attrs );
	$tag    = wpddbp_quickbook_tag( $attrs );
	$trust  = wpddbp_quickbook_trust( $attrs );
	$pick   = wpddbp_quickbook_pick_link( $attrs );

	if ( 'sticky' === $layout ) {
		$docked = ! empty( $attrs['dockToBottom'] ) ? ' is-docked' : '';
		$stat   = ( '' !== $dept ) ? $dept : $tag;
		$mid    = ( '' !== $rating || '' !== $fee ) ? '<div class="wpddbp-qb-mid">' . $rating . $fee . '</div>' : '';
		return '<div class="wpddbp-qb wpddbp-qb--sticky' . $docked . '">'
			. '<div class="wpddbp-qb-row">'
			. '<div class="wpddbp-qb-doc">' . $avatar . '<div class="wpddbp-qb-who">' . $name . ( '' !== $stat ? '<div class="wpddbp-qb-stat">' . $stat . '</div>' : '' ) . '</div></div>'
			. $mid
			. $cta
			. '</div>'
			. '</div>';
	}

	if ( 'hero' === $layout ) {
		$title = ( '' !== trim( (string) ( $attrs['titleText'] ?? '' ) ) ) ? '<h2 class="wpddbp-qb-title">' . esc_html( $attrs['titleText'] ) . '</h2>' : '';
		$lede  = ( '' !== trim( (string) ( $attrs['ledeText'] ?? '' ) ) ) ? '<p class="wpddbp-qb-lede">' . esc_html( $attrs['ledeText'] ) . '</p>' : '';
		$meta  = ( '' !== $rating || '' !== $fee ) ? '<div class="wpddbp-qb-meta">' . $rating . $fee . '</div>' : '';
		$right = ( '' !== $tag || '' !== $eye ) ? '<div class="wpddbp-qb-cap">' . $tag . $eye . '</div>' : '';
		$titlerow = ( '' !== $title || '' !== $pick ) ? '<div class="wpddbp-qb-titlerow">' . $title . $pick . '</div>' : '';
		$foot = ( '' !== $trust || '' !== $cta ) ? '<div class="wpddbp-qb-foot">' . ( '' !== $trust ? '<div class="wpddbp-qb-truststrip">' . $trust . '</div>' : '' ) . $cta . '</div>' : '';
		return '<div class="wpddbp-qb wpddbp-qb--hero">'
			. '<span class="wpddbp-qb-glow" aria-hidden="true"></span>'
			. '<div class="wpddbp-qb-inner">'
			. '<div class="wpddbp-qb-top">'
			. '<div class="wpddbp-qb-doc">' . $avatar . '<div class="wpddbp-qb-who">' . $name . $dept . $meta . '</div></div>'
			. $right
			. '</div>'
			. $titlerow . $lede . $foot
			. '</div>'
			. '</div>';
	}

	// ── sidebar (default) ──
	$head   = ( '' !== $eye || '' !== $tag ) ? '<div class="wpddbp-qb-head">' . $eye . $tag . '</div>' : '';
	$docrow = '<div class="wpddbp-qb-doc">' . $avatar . '<span class="wpddbp-qb-who">' . $name . $dept . '</span>' . $rating . '</div>';
	$feerow = ( '' !== $fee ) ? '<div class="wpddbp-qb-feerow">' . $fee . '</div>' : '';
	$foot   = ( '' !== $pick || '' !== $trust ) ? '<div class="wpddbp-qb-foot">' . $pick . $trust . '</div>' : '';
	return '<div class="wpddbp-qb wpddbp-qb--sidebar">'
		. $head . $docrow . $feerow . $cta . $foot
		. '</div>';
}

/**
 * Top-level render entry. Resolves the doctor and returns the widget HTML, or a
 * soft empty-state (only shown to editors) when no doctor is resolvable.
 *
 * @param array  $attrs    Block attributes.
 * @param string $block_id Unique id.
 * @return string
 */
function wpddbp_quickbook_render( $attrs, $block_id = '' ) {
	$doctor_id = wpddbp_quickbook_resolve_doctor_id( $attrs );

	if ( $doctor_id <= 0 || ! get_post( $doctor_id ) || 'wpddb_doctor' !== get_post_type( $doctor_id ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return '<div class="wpddbp-qb wpddbp-qb--empty">' . esc_html__( 'Quick Book: choose a doctor in the block settings.', 'doc-booker-pro' ) . '</div>';
		}
		return '';
	}

	$d = wpddbp_doctors_card_data( $doctor_id, array() );
	return wpddbp_quickbook_render_widget( $attrs, $d, $block_id );
}
