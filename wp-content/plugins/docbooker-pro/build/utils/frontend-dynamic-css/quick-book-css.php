<?php
/**
 * Quick Book block — dynamic CSS generator (Tier C, Phase 5).
 *
 * Per-instance overrides on top of style.scss. The accent is emitted as a CSS
 * custom property on the widget root so every accent-tinted atom (dot, live ring,
 * trust dot, sticky bar gradient) re-colours from one control. Selector-for-selector
 * parity with edit.js previewCss.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_quick_book_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// ---- accent (CSS var on the widget root) ----
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-qb{--wpddbp-qb-accent:" . $attributes['accentColor'] . ';}';
	}

	// ---- card surfaces (all three variant roots) ----
	$card_sel = "{$sel} .wpddbp-qb--sidebar, {$sel} .wpddbp-qb--hero, {$sel} .wpddbp-qb--sticky";
	$cbase    = $generate_color_css( $attributes['cardBg'] ?? '', 'background' );
	if ( ! empty( $attributes['cardBorderColor'] ) ) {
		$cbase .= 'border-color:' . $attributes['cardBorderColor'] . ';';
	}
	if ( ! empty( $attributes['cardRadius'] ) ) {
		$cbase .= $generate_border_radius_css( $attributes['cardRadius'] );
	}
	if ( ! empty( $attributes['cardBoxShadow'] ) ) {
		$cbase .= $generate_box_shadow_css( $attributes['cardBoxShadow'] );
	}
	if ( '' !== $cbase ) {
		$css .= "{$card_sel}{{$cbase}}";
	}

	// ---- text colours ----
	if ( ! empty( $attributes['nameColor'] ) ) {
		$css .= "{$sel} .wpddbp-qb-name{color:" . $attributes['nameColor'] . ';}';
	}
	if ( ! empty( $attributes['eyebrowColor'] ) ) {
		$css .= "{$sel} .wpddbp-qb-eye{color:" . $attributes['eyebrowColor'] . ';}';
	}
	if ( ! empty( $attributes['feeColor'] ) ) {
		$css .= "{$sel} .wpddbp-qb-fee b{color:" . $attributes['feeColor'] . ';}';
	}

	// ---- booking button (normal + hover) ----
	$btn = $generate_color_css( $attributes['btnColor'] ?? '', 'color' );
	$btn .= $generate_color_css( $attributes['btnBackground'] ?? '', 'background' );
	if ( '' !== $btn ) {
		$css .= "{$sel} .wpddbp-qb-cta{{$btn}}";
	}
	$btnh = $generate_color_css( $attributes['btnHoverColor'] ?? '', 'color' );
	$btnh .= $generate_color_css( $attributes['btnHoverBackground'] ?? '', 'background' );
	if ( '' !== $btnh ) {
		$css .= "{$sel} .wpddbp-qb-cta:hover{{$btnh}}";
	}

	return $css;
}
