<?php
/**
 * Info Box — server render (9 skins).
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . '../../utils/info-box-icons.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'circle';
$allowed  = array( 'circle', 'tile', 'watermark', 'pill', 'numbered', 'rule', 'image-top', 'image-split', 'image-overlay' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'circle';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$icon_name = $attributes['iconName'] ?? 'heart';
$image_url = $attributes['image']['url'] ?? '';
$eyebrow   = $attributes['eyebrowText'] ?? '';
$number    = $attributes['numberText'] ?? '';
$title     = $attributes['titleText'] ?? '';
$accent    = $attributes['accentText'] ?? '';
$desc      = $attributes['descriptionText'] ?? '';
$link_text = $attributes['linkText'] ?? '';

$d_icon  = $attributes['displayIcon'] ?? true;
$d_eye   = $attributes['displayEyebrow'] ?? true;
$d_title = $attributes['displayTitle'] ?? true;
$d_desc  = $attributes['displayDescription'] ?? true;
$d_link  = $attributes['displayLink'] ?? true;

$icon_svg = $d_icon ? wpddbp_get_info_box_icon_svg( $icon_name ) : '';
$icon_el  = ( '' !== $icon_svg ) ? '<span class="wpddbp-ib-icon">' . $icon_svg . '</span>' : '';
$badge_el = ( '' !== $icon_svg ) ? '<span class="wpddbp-ib-badge">' . $icon_svg . '</span>' : '';
$wm_el    = ( '' !== $icon_svg ) ? '<span class="wpddbp-ib-watermark" aria-hidden="true">' . $icon_svg . '</span>' : '';

$accent_html = ( '' !== $accent ) ? '<em class="wpddbp-ib-accent">' . esc_html( $accent ) . '</em>' : '';
$title_el    = ( $d_title && ( '' !== $title || '' !== $accent_html ) )
	? '<h3 class="wpddbp-ib-title">' . esc_html( $title ) . ( ( '' !== $title && '' !== $accent_html ) ? ' ' : '' ) . $accent_html . '</h3>'
	: '';
$eyebrow_el = ( $d_eye && '' !== $eyebrow ) ? '<span class="wpddbp-ib-eyebrow">' . esc_html( $eyebrow ) . '</span>' : '';
$desc_el    = ( $d_desc && '' !== $desc ) ? '<p class="wpddbp-ib-desc">' . esc_html( $desc ) . '</p>' : '';

$link_el = '';
if ( $d_link && '' !== $link_text ) {
	$url    = $attributes['linkURL'] ?? '';
	$href   = $url ? esc_url( $url ) : '#';
	$target = ! empty( $attributes['linkNewTab'] ) ? ' target="_blank"' : '';
	$rel    = array();
	if ( ! empty( $attributes['linkNewTab'] ) ) {
		$rel[] = 'noopener';
		$rel[] = 'noreferrer';
	}
	if ( ! empty( $attributes['linkNoFollow'] ) ) {
		$rel[] = 'nofollow';
	}
	$rel_attr = $rel ? ' rel="' . esc_attr( implode( ' ', array_unique( $rel ) ) ) . '"' : '';
	$link_el  = '<a class="wpddbp-ib-link" href="' . $href . '"' . $target . $rel_attr . '>' . esc_html( $link_text ) . ' <span aria-hidden="true">&rarr;</span></a>';
}

// ---- image-led band extras: floating stat tile, checklist, secondary CTA ----
$tile_el = '';
if ( ! empty( $attributes['displayStatTile'] ) && ( '' !== ( $attributes['statTileValue'] ?? '' ) || '' !== ( $attributes['statTileLabel'] ?? '' ) ) ) {
	$tile_icon = wpddbp_get_info_box_icon_svg( $attributes['statTileIcon'] ?? 'shield' );
	$tile_el   = '<span class="wpddbp-ib-tile" aria-hidden="true">'
		. ( '' !== $tile_icon ? '<span class="wpddbp-ib-tile-ic">' . $tile_icon . '</span>' : '' )
		. '<span class="wpddbp-ib-tile-txt">'
		. '<span class="wpddbp-ib-tile-val">' . esc_html( $attributes['statTileValue'] ?? '' ) . '</span>'
		. '<span class="wpddbp-ib-tile-lab">' . esc_html( $attributes['statTileLabel'] ?? '' ) . '</span>'
		. '</span></span>';
}

$second_link_el = '';
if ( ! empty( $attributes['displaySecondaryLink'] ) && '' !== ( $attributes['secondaryLinkText'] ?? '' ) ) {
	$s_url    = $attributes['secondaryLinkURL'] ?? '';
	$s_href   = $s_url ? esc_url( $s_url ) : '#';
	$s_target = ! empty( $attributes['secondaryLinkNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	$second_link_el = '<a class="wpddbp-ib-link wpddbp-ib-link--ghost" href="' . $s_href . '"' . $s_target . '>' . esc_html( $attributes['secondaryLinkText'] ) . '</a>';
}

$media_el = function ( $with_badge ) use ( $image_url, $badge_el, $tile_el ) {
	$style = $image_url ? ' style="background-image:url(\'' . esc_url( $image_url ) . '\')"' : '';
	return '<div class="wpddbp-ib-media"><div class="wpddbp-ib-media-img"' . $style . '></div>' . $tile_el . ( $with_badge ? $badge_el : '' ) . '</div>';
};

switch ( $layout ) {
	case 'tile':
		$inner = $icon_el . $eyebrow_el . $title_el . $desc_el . $link_el;
		break;
	case 'watermark':
		$inner = $wm_el . $eyebrow_el . $title_el . $desc_el . $link_el;
		break;
	case 'pill':
		$inner = $icon_el . '<div class="wpddbp-ib-body">' . $title_el . $desc_el . $link_el . '</div>';
		break;
	case 'numbered':
		$num   = ( '' !== $number ) ? $number : '01';
		$inner = '<span class="wpddbp-ib-num">' . esc_html( $num ) . '</span><div class="wpddbp-ib-body">' . $eyebrow_el . $title_el . $desc_el . $link_el . '</div>';
		break;
	case 'rule':
		$inner = '<span class="wpddbp-ib-bar" aria-hidden="true"></span>' . $icon_el . '<div class="wpddbp-ib-body">' . $title_el . $desc_el . $link_el . '</div>';
		break;
	case 'image-top':
		$inner = $media_el( true ) . '<div class="wpddbp-ib-body">' . $eyebrow_el . $title_el . $desc_el . $link_el . '</div>';
		break;
	case 'image-split':
		$cta_el = ( '' !== $second_link_el )
			? '<div class="wpddbp-ib-cta">' . $link_el . $second_link_el . '</div>'
			: $link_el;
		$inner  = $media_el( true ) . '<div class="wpddbp-ib-body">' . $eyebrow_el . $title_el . $desc_el . $cta_el . '</div>';
		break;
	case 'image-overlay':
		$inner = $media_el( false ) . '<div class="wpddbp-ib-scrim" aria-hidden="true"></div>' . $badge_el . '<div class="wpddbp-ib-body">' . $eyebrow_el . $title_el . $desc_el . $link_el . '</div>';
		break;
	case 'circle':
	default:
		$inner = $icon_el . $title_el . $desc_el . $link_el;
		break;
}

printf(
	'<div id="%1$s" class="wpddbp-ib wpddbp-ib--%2$s%3$s">%4$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	esc_attr( $align_class ),
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url() + our own inline SVG icon markup.
);
