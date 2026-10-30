<?php
/**
 * Doctor Stats block — server render (Phase 5).
 *
 * Author-entered practice statistics in 2 layouts (accent-tinted card grid / compact
 * strip). Numbers are typed by the author (grounded — no fabricated/derived metrics);
 * the optional count-up is a `view.js` animation that reads `data-count`.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/info-box-icons.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = in_array( ( $attributes['layoutType'] ?? 'cards' ), array( 'cards', 'strip' ), true ) ? $attributes['layoutType'] : 'cards';
$align    = in_array( ( $attributes['align'] ?? '' ), array( 'wide', 'full' ), true ) ? $attributes['align'] : '';
$tone     = in_array( ( $attributes['stripTone'] ?? 'cream' ), array( 'cream', 'dark', 'inline' ), true ) ? $attributes['stripTone'] : 'cream';
$stats    = ( ! empty( $attributes['stats'] ) && is_array( $attributes['stats'] ) ) ? $attributes['stats'] : array();
$animate  = ! empty( $attributes['animateCount'] );

if ( empty( $stats ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="wpddbp-ds-empty">' . esc_html__( 'Doctor Stats: add at least one stat in the block settings.', 'doc-booker-pro' ) . '</div>';
	}
	return;
}

$accents = array( 'ocean', 'sage', 'bronze', 'plum', 'gold', 'rose' );
$sufmap  = array( 'plus' => '+', 'star' => '★', 'percent' => '%', 'k' => 'k', 'm' => 'M', 'h' => 'h', 'kplus' => 'k+' );

/** Number markup with a count-up hook (numeric stripped of separators in data-count). */
if ( ! function_exists( 'wpddbp_ds_number' ) ) {
	function wpddbp_ds_number( $stat, $animate, $sufmap ) {
		$raw     = (string) ( $stat['number'] ?? '' );
		$sufkey  = (string) ( $stat['suffix'] ?? '' );
		$suffix  = isset( $sufmap[ $sufkey ] ) ? $sufmap[ $sufkey ] : '';
		$numeric = preg_replace( '/[^0-9.]/', '', $raw );
		$data    = ( $animate && '' !== $numeric ) ? ' data-count="' . esc_attr( $numeric ) . '"' : '';
		$sfx     = ( '' !== $suffix ) ? '<span class="wpddbp-ds-suffix">' . esc_html( $suffix ) . '</span>' : '';
		return '<span class="wpddbp-ds-num"' . $data . '>' . esc_html( $raw ) . $sfx . '</span>';
	}
}

/** Header (eyebrow / title / lede). */
$eye  = ( '' !== trim( (string) ( $attributes['eyebrowText'] ?? '' ) ) ) ? '<span class="wpddbp-ds-eyebrow">' . esc_html( $attributes['eyebrowText'] ) . '</span>' : '';
$ttl  = ( '' !== trim( (string) ( $attributes['titleText'] ?? '' ) ) ) ? '<h2 class="wpddbp-ds-title">' . esc_html( $attributes['titleText'] ) . '</h2>' : '';
$lede = ( '' !== trim( (string) ( $attributes['ledeText'] ?? '' ) ) ) ? '<p class="wpddbp-ds-lede">' . esc_html( $attributes['ledeText'] ) . '</p>' : '';
$head = ( '' !== $eye || '' !== $ttl || '' !== $lede ) ? '<header class="wpddbp-ds-head">' . $eye . $ttl . $lede . '</header>' : '';

/** Optional CTA link (strip). */
$cta = '';
if ( ! empty( $attributes['showCta'] ) && '' !== trim( (string) ( $attributes['ctaUrl'] ?? '' ) ) ) {
	$tab = ! empty( $attributes['ctaNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	$cta = '<a class="wpddbp-ds-cta" href="' . esc_url( $attributes['ctaUrl'] ) . '"' . $tab . '>' . esc_html( $attributes['ctaText'] ?? __( 'See full stats', 'doc-booker-pro' ) ) . ' <span aria-hidden="true">&rarr;</span></a>';
}

$body = '';
if ( 'strip' === $layout ) {
	$units = '';
	foreach ( $stats as $stat ) {
		$ctx = ( ! empty( $attributes['showContext'] ) && '' !== trim( (string) ( $stat['context'] ?? '' ) ) )
			? '<span class="wpddbp-ds-ctx">' . esc_html( $stat['context'] ) . '</span>'
			: '';
		$units .= '<div class="wpddbp-ds-unit">'
			. wpddbp_ds_number( $stat, $animate, $sufmap )
			. '<span class="wpddbp-ds-meta"><span class="wpddbp-ds-label">' . esc_html( $stat['label'] ?? '' ) . '</span>' . $ctx . '</span>'
			. '</div>';
	}
	$body = '<div class="wpddbp-ds-strip wpddbp-ds-strip--' . esc_attr( $tone ) . '"><div class="wpddbp-ds-strack">' . $units . ( '' !== $cta ? '<div class="wpddbp-ds-ctacell">' . $cta . '</div>' : '' ) . '</div></div>';
} else {
	$cards = '';
	foreach ( $stats as $i => $stat ) {
		$accent = in_array( ( $stat['accent'] ?? '' ), $accents, true ) ? $stat['accent'] : $accents[ $i % count( $accents ) ];
		$icon   = ( ! empty( $attributes['showIcons'] ) && '' !== trim( (string) ( $stat['icon'] ?? '' ) ) )
			? '<span class="wpddbp-ds-ico" aria-hidden="true">' . wpddbp_get_info_box_icon_svg( sanitize_key( $stat['icon'] ) ) . '</span>'
			: '';
		$ctx = ( ! empty( $attributes['showContext'] ) && '' !== trim( (string) ( $stat['context'] ?? '' ) ) )
			? '<p class="wpddbp-ds-ctx">' . esc_html( $stat['context'] ) . '</p>'
			: '';
		$cards .= '<article class="wpddbp-ds-card is-' . esc_attr( $accent ) . '">'
			. ( '' !== $icon ? '<div class="wpddbp-ds-cardhead">' . $icon . '</div>' : '' )
			. wpddbp_ds_number( $stat, $animate, $sufmap )
			. '<div class="wpddbp-ds-label">' . esc_html( $stat['label'] ?? '' ) . '</div>'
			. $ctx
			. '</article>';
	}
	$body = '<div class="wpddbp-ds-grid">' . $cards . '</div>' . ( '' !== $cta ? '<div class="wpddbp-ds-gridcta">' . $cta . '</div>' : '' );
}

$classes = 'wpddbp-ds-wrap wpddbp-ds-wrap--' . esc_attr( $layout );
if ( '' !== $align ) {
	$classes .= ' align' . esc_attr( $align );
}

echo '<div id="' . $block_id . '" class="' . $classes . '">'
	. '<div class="wpddbp-ds">' . $head . $body . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*().
	. '</div>';
