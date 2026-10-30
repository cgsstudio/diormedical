<?php
/**
 * Advanced Heading — server render (8 layout variants).
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'v1';
if ( ! in_array( $layout, array( 'v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8' ), true ) ) {
	$layout = 'v1';
}

$title       = $attributes['titleText'] ?? '';
$subtitle    = $attributes['subtitleText'] ?? '';
$accent      = $attributes['accentText'] ?? '';
$content     = $attributes['contentText'] ?? '';
$meta        = $attributes['metaText'] ?? '';
$number      = $attributes['numberText'] ?? '';
$attribution = $attributes['attributionText'] ?? '';
$button      = $attributes['buttonText'] ?? '';
$button_url  = $attributes['buttonUrl'] ?? '';
$button_new  = ! empty( $attributes['buttonNewTab'] );

$d_title   = $attributes['displayTitle'] ?? true;
$d_sub     = $attributes['displaySubTitle'] ?? true;
$d_accent  = $attributes['displayAccent'] ?? true;
$d_content = $attributes['displayContent'] ?? true;
$d_meta    = $attributes['displayMeta'] ?? true;
$d_button  = $attributes['displayButton'] ?? false;

// Pre-built, escaped fragments (text via esc_html, tags fixed).
$accent_html  = ( $d_accent && '' !== $accent ) ? '<span class="wpddbp-ah-accent">' . esc_html( $accent ) . '</span>' : '';
$title_html   = '';
if ( $d_title && ( '' !== $title || '' !== $accent_html ) ) {
	$title_html = '<h2 class="wpddbp-ah-title">' . esc_html( $title ) . ( ( '' !== $title && '' !== $accent_html ) ? ' ' : '' ) . $accent_html . '</h2>';
}
$eyebrow_html = ( $d_sub && '' !== $subtitle ) ? '<span class="wpddbp-ah-eyebrow">' . esc_html( $subtitle ) . '</span>' : '';
$lede_html    = ( $d_content && '' !== $content ) ? '<p class="wpddbp-ah-lede">' . esc_html( $content ) . '</p>' : '';
$meta_html    = ( $d_meta && '' !== $meta ) ? '<p class="wpddbp-ah-meta">' . esc_html( $meta ) . '</p>' : '';

$button_html = '';
if ( $d_button && '' !== $button ) {
	$href        = ( '' !== $button_url ) ? esc_url( $button_url ) : '#';
	$target      = $button_new ? ' target="_blank" rel="noopener noreferrer"' : '';
	$button_html = '<div class="wpddbp-button-wrap"><a class="wpddbp-button" href="' . $href . '"' . $target . '>' . esc_html( $button ) . '</a></div>';
}

switch ( $layout ) {
	case 'v2':
		$topline = '<div class="wpddbp-ah-topline">';
		if ( $d_sub && '' !== $subtitle ) {
			$topline .= '<span class="wpddbp-ah-vol">' . esc_html( $subtitle ) . '</span>';
		}
		$topline .= '<span class="wpddbp-ah-spacer"></span>';
		if ( $d_meta && '' !== $meta ) {
			$topline .= '<span class="wpddbp-ah-issue">' . esc_html( $meta ) . '</span>';
		}
		$topline .= '</div>';
		$inner    = $topline . '<div class="wpddbp-ah-body">' . $title_html . $lede_html . '</div>' . $button_html;
		break;

	case 'v3':
		$inner = $eyebrow_html
			. '<div class="wpddbp-ah-body">' . $title_html
			. '<div class="wpddbp-ah-right">' . $lede_html . $meta_html . $button_html . '</div>'
			. '</div>';
		break;

	case 'v4':
		$inner = $eyebrow_html . $title_html . $meta_html . $button_html;
		break;

	case 'v5':
		$stack = '<div class="wpddbp-ah-stack">';
		if ( $d_sub && '' !== $subtitle ) {
			$stack .= '<p class="wpddbp-ah-ln wpddbp-ah-ln-1">' . esc_html( $subtitle ) . '</p>';
		}
		if ( $d_title && '' !== $title ) {
			$stack .= '<p class="wpddbp-ah-ln wpddbp-ah-ln-2"><span class="wpddbp-ah-hi">' . esc_html( $title ) . '</span></p>';
		}
		if ( $d_content && '' !== $content ) {
			$stack .= '<p class="wpddbp-ah-ln wpddbp-ah-ln-3">' . esc_html( $content ) . '</p>';
		}
		if ( $d_accent && '' !== $accent ) {
			$stack .= '<p class="wpddbp-ah-ln wpddbp-ah-ln-4">' . esc_html( $accent ) . '</p>';
		}
		$stack .= '</div>';
		$inner  = $stack . $button_html;
		break;

	case 'v6':
		$num   = ( '' !== $number ) ? $number : '01.';
		$inner = '<div class="wpddbp-ah-body"><p class="wpddbp-ah-num">' . esc_html( $num ) . '</p>'
			. '<div class="wpddbp-ah-right">' . $title_html . '<div class="wpddbp-ah-rule"></div>'
			. $lede_html . $meta_html . $button_html . '</div></div>';
		break;

	case 'v7':
		$q_title = '';
		if ( $d_title && ( '' !== $title || '' !== $accent_html ) ) {
			$q_title = '<p class="wpddbp-ah-title">' . $accent_html . ( ( '' !== $accent_html && '' !== $title ) ? ' ' : '' ) . esc_html( $title ) . '</p>';
		}
		$who       = ( '' !== $attribution ) ? $attribution : $meta;
		$attr_html = ( '' !== $who ) ? '<div class="wpddbp-ah-attribution"><span class="wpddbp-ah-line"></span><span>' . esc_html( $who ) . '</span></div>' : '';
		$inner     = '<div class="wpddbp-ah-quote-wrap"><p class="wpddbp-ah-glyph" aria-hidden="true">&ldquo;</p>'
			. '<blockquote class="wpddbp-ah-quoteblock">' . $q_title . $attr_html . $button_html . '</blockquote></div>';
		break;

	case 'v8':
		$inner = $eyebrow_html . $title_html . '<div class="wpddbp-ah-rule"></div>' . $button_html;
		break;

	case 'v1':
	default:
		$ornament = '<div class="wpddbp-ah-ornament" aria-hidden="true"><span class="wpddbp-ah-line"></span><span class="wpddbp-ah-glyph">&#10086;</span><span class="wpddbp-ah-line"></span></div>';
		$inner    = $eyebrow_html . $title_html . $lede_html . $ornament . $button_html;
		break;
}

printf(
	'<div id="%1$s" class="wpddbp-advanced-heading wpddbp-advanced-heading--%2$s">%3$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url() parts + fixed tags.
);
