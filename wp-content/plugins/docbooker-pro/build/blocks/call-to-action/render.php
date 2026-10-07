<?php
/**
 * Call to Action — server render (6 layouts).
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'v1';
if ( ! in_array( $layout, array( 'v1', 'v2', 'v3', 'v4', 'v5', 'v6' ), true ) ) {
	$layout = 'v1';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$eyebrow = $attributes['eyebrowText'] ?? '';
$title   = $attributes['titleText'] ?? '';
$accent  = $attributes['accentText'] ?? '';
$desc    = $attributes['descriptionText'] ?? '';
$trust   = $attributes['trustText'] ?? '';
$badge   = $attributes['badgeText'] ?? '';
$author  = $attributes['quoteAuthor'] ?? '';
$role    = $attributes['quoteRole'] ?? '';
$meta    = $attributes['metaText'] ?? '';

$d_eyebrow = $attributes['displayEyebrow'] ?? true;
$d_title   = $attributes['displayTitle'] ?? true;
$d_accent  = $attributes['displayAccent'] ?? true;
$d_desc    = $attributes['displayDescription'] ?? true;
$d_trust   = $attributes['displayTrust'] ?? true;
$d_btn     = $attributes['displayButton'] ?? true;
$d_sbtn    = $attributes['displaySecondaryButton'] ?? true;

$split_url = $attributes['splitImage']['url'] ?? '';

$build_link = function ( $url, $new_tab, $nofollow ) {
	$href   = $url ? esc_url( $url ) : '#';
	$target = $new_tab ? ' target="_blank"' : '';
	$rel    = array();
	if ( $new_tab ) {
		$rel[] = 'noopener';
		$rel[] = 'noreferrer';
	}
	if ( $nofollow ) {
		$rel[] = 'nofollow';
	}
	$rel_attr = $rel ? ' rel="' . esc_attr( implode( ' ', array_unique( $rel ) ) ) . '"' : '';
	return $href . '"' . $target . $rel_attr;
};

$accent_html = ( $d_accent && '' !== $accent ) ? '<span class="wpddbp-cta-accent">' . esc_html( $accent ) . '</span>' : '';
$title_inner = esc_html( $title ) . ( ( '' !== $title && '' !== $accent_html ) ? ' ' : '' ) . $accent_html;

$title_html = '';
if ( $d_title && ( '' !== $title || '' !== $accent_html ) ) {
	$title_html = '<h2 class="wpddbp-cta-title">' . $title_inner . '</h2>';
}
$title_h3_html = '';
if ( $d_title && ( '' !== $title || '' !== $accent_html ) ) {
	$title_h3_html = '<h3 class="wpddbp-cta-title">' . $title_inner . '</h3>';
}
$eyebrow_html = ( $d_eyebrow && '' !== $eyebrow ) ? '<span class="wpddbp-cta-eyebrow">' . esc_html( $eyebrow ) . '</span>' : '';
$lede_html    = ( $d_desc && '' !== $desc ) ? '<p class="wpddbp-cta-lede">' . esc_html( $desc ) . '</p>' : '';
$trust_html   = ( $d_trust && '' !== $trust ) ? '<span class="wpddbp-cta-trust">' . esc_html( $trust ) . '</span>' : '';

$primary_html = '';
if ( $d_btn && '' !== ( $attributes['buttonText'] ?? '' ) ) {
	$link = $build_link( $attributes['buttonURL'] ?? '', ! empty( $attributes['buttonNewTab'] ), ! empty( $attributes['buttonNoFollow'] ) );
	$primary_html = '<a class="wpddbp-cta-btn wpddbp-cta-btn--primary" href="' . $link . '>' . esc_html( $attributes['buttonText'] ) . ' <span aria-hidden="true">&rarr;</span></a>';
}
$ghost_html = '';
if ( $d_sbtn && '' !== ( $attributes['secondaryButtonText'] ?? '' ) ) {
	$link = $build_link( $attributes['secondaryButtonURL'] ?? '', ! empty( $attributes['secondaryButtonNewTab'] ), ! empty( $attributes['secondaryButtonNoFollow'] ) );
	$ghost_html = '<a class="wpddbp-cta-btn wpddbp-cta-btn--ghost" href="' . $link . '>' . esc_html( $attributes['secondaryButtonText'] ) . '</a>';
}
$ctas_html = ( '' !== $primary_html || '' !== $ghost_html ) ? '<div class="wpddbp-cta-ctas">' . $primary_html . $ghost_html . '</div>' : '';

$v2_noimg = false;
switch ( $layout ) {
	case 'v2':
		$body_v2 = '<div class="wpddbp-cta-body">' . $eyebrow_html . $title_html . $lede_html . $ctas_html . $trust_html . '</div>';
		if ( $split_url ) {
			$badge_html = ( '' !== $badge ) ? '<span class="wpddbp-cta-badge">' . esc_html( $badge ) . '</span>' : '';
			$inner      = '<div class="wpddbp-cta-photo" style="background-image:url(\'' . esc_url( $split_url ) . '\')">' . $badge_html . '</div>' . $body_v2;
		} else {
			// No split image → drop the empty photo panel and center the body (no grey placeholder box).
			$v2_noimg = true;
			$inner    = $body_v2;
		}
		break;

	case 'v3':
		$sub_html = ( $d_desc && '' !== $desc ) ? '<div class="wpddbp-cta-sub">' . esc_html( $desc ) . '</div>' : '';
		$inner    = '<div class="wpddbp-cta-v3-text"><span class="wpddbp-cta-pulse" aria-hidden="true"></span><div>' . $title_h3_html . $sub_html . '</div></div>'
			. '<div class="wpddbp-cta-cta">' . $primary_html . $trust_html . '</div>';
		break;

	case 'v4':
		$num_html = ( '' !== $meta ) ? '<span class="wpddbp-cta-num">' . esc_html( $meta ) . '</span>' : '';
		$inner    = '<div class="wpddbp-cta-v4-inner"><div class="wpddbp-cta-v4-accent">' . $num_html . '</div>'
			. '<div>' . $eyebrow_html . $title_html . $lede_html . $ctas_html . '</div></div>';
		break;

	case 'v5':
		$quote_html = '';
		if ( $d_title && ( '' !== $title || '' !== $accent_html ) ) {
			$quote_html = '<blockquote class="wpddbp-cta-quote">' . $title_inner . '</blockquote>';
		}
		$attr_html = '';
		if ( '' !== $author || '' !== $role ) {
			$attr_html = '<div class="wpddbp-cta-attribution"><span class="wpddbp-cta-attr-rule"></span>'
				. ( '' !== $author ? '<span class="wpddbp-cta-who">' . esc_html( $author ) . '</span>' : '' )
				. ( '' !== $role ? '<span class="wpddbp-cta-role">' . esc_html( $role ) . '</span>' : '' )
				. '</div>';
		}
		$inner = '<p class="wpddbp-cta-glyph" aria-hidden="true">&ldquo;</p>'
			. '<div class="wpddbp-cta-v5-body">' . $quote_html . $attr_html . $primary_html . '</div>';
		break;

	case 'v6':
		$icons = array(
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 9h18"/><path d="M8 3v4M16 3v4"/></svg>',
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>',
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
		);
		$go_labels = array( 'Open the calendar', 'Call now', 'Find directions' );
		$paths     = array(
			array( $attributes['path1Label'] ?? '', $attributes['path1Context'] ?? '', $attributes['path1URL'] ?? '' ),
			array( $attributes['path2Label'] ?? '', $attributes['path2Context'] ?? '', $attributes['path2URL'] ?? '' ),
			array( $attributes['path3Label'] ?? '', $attributes['path3Context'] ?? '', $attributes['path3URL'] ?? '' ),
		);
		$cols = '';
		foreach ( $paths as $i => $p ) {
			$href  = $p[2] ? esc_url( $p[2] ) : '#';
			$cols .= '<a class="wpddbp-cta-col" href="' . $href . '">'
				. '<span class="wpddbp-cta-col-num">0' . ( $i + 1 ) . '</span>'
				. '<span class="wpddbp-cta-col-icon">' . $icons[ $i ] . '</span>'
				. '<h4 class="wpddbp-cta-col-label">' . esc_html( $p[0] ) . '</h4>'
				. '<p class="wpddbp-cta-col-context">' . esc_html( $p[1] ) . '</p>'
				. '<span class="wpddbp-cta-go">' . esc_html( $go_labels[ $i ] ) . ' <span aria-hidden="true">&rarr;</span></span>'
				. '</a>';
		}
		$inner = '<header class="wpddbp-cta-head">' . $title_h3_html . $lede_html . '</header><div class="wpddbp-cta-grid">' . $cols . '</div>';
		break;

	case 'v1':
	default:
		$inner = '<div>' . $eyebrow_html . $title_html . $lede_html . $ctas_html . '</div>';
		break;
}

$align_class .= $v2_noimg ? ' is-noimg' : '';
printf(
	'<div id="%1$s" class="wpddbp-cta wpddbp-cta--%2$s%3$s">%4$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	esc_attr( $align_class ),
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url() parts + fixed markup.
);
