<?php
/**
 * Doctor Stats block — dynamic CSS generator (Tier A, Phase 5).
 *
 * Per-instance overrides: responsive card columns, an accent override (a CSS var on
 * the cards/strip), card surface, and number/label/context colours + number
 * typography. Selector set mirrored by edit.js previewCss.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpddbp_assemble_responsive_block' ) ) {
	function wpddbp_assemble_responsive_block( $selector, $base, $md, $sm ) {
		$out = '';
		if ( '' !== $base ) { $out .= "{$selector}{{$base}}"; }
		if ( '' !== $md ) { $out .= "@media (max-width: 1024px){{$selector}{{$md}}}"; }
		if ( '' !== $sm ) { $out .= "@media (max-width: 767px){{$selector}{{$sm}}}"; }
		return $out;
	}
}

function wpddbp_generate_doctor_stats_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// ---- card columns ----
	if ( 'strip' !== ( $attributes['layoutType'] ?? 'cards' ) ) {
		$lg = (int) ( $attributes['columnsLg'] ?? 4 );
		$md = (int) ( $attributes['columnsMd'] ?? 2 );
		$sm = (int) ( $attributes['columnsSm'] ?? 1 );
		$g  = "{$sel} .wpddbp-ds-grid";
		$css .= "{$g}{grid-template-columns:repeat({$lg},minmax(0,1fr));}@media (max-width:1024px){{$g}{grid-template-columns:repeat({$md},minmax(0,1fr));}}@media (max-width:600px){{$g}{grid-template-columns:repeat({$sm},minmax(0,1fr));}}";
	}

	// ---- accent override (CSS var on cards + strip) ----
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-ds-card, {$sel} .wpddbp-ds-strip{--ds-accent:" . $attributes['accentColor'] . ';}';
	}

	// ---- card surface ----
	$cbase = $generate_color_css( $attributes['cardBg'] ?? '', 'background' );
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
		$css .= "{$sel} .wpddbp-ds-card{{$cbase}}";
	}

	// ---- number (colour + typography) ----
	$n_sel = "{$sel} .wpddbp-ds-num";
	$nbase = '';
	$nmd   = '';
	$nsm   = '';
	if ( ! empty( $attributes['numberTypography'] ) ) {
		$t     = $generate_typography_css( $n_sel, $attributes['numberTypography'] );
		$nbase .= $t['base']; $nmd .= $t['md']; $nsm .= $t['sm'];
	}
	$nbase .= $generate_color_css( $attributes['numberColor'] ?? '', 'color' );
	$css   .= wpddbp_assemble_responsive_block( $n_sel, $nbase, $nmd, $nsm );

	// ---- label + context colours ----
	if ( ! empty( $attributes['labelColor'] ) ) {
		$css .= "{$sel} .wpddbp-ds-label{color:" . $attributes['labelColor'] . ';}';
	}
	if ( ! empty( $attributes['contextColor'] ) ) {
		$css .= "{$sel} .wpddbp-ds-ctx{color:" . $attributes['contextColor'] . ';}';
	}

	return $css;
}
