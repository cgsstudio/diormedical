<?php
/**
 * Testimonials block — dynamic CSS generator (Tier A, Phase 5).
 *
 * Per-instance overrides: responsive columns (grid + mosaic column-count), accent +
 * star CSS vars, card surface, and quote (colour + typography) + name colour. Selector
 * set mirrored by edit.js previewCss.
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

function wpddbp_generate_testimonials_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// ---- columns (grid + mosaic) ----
	if ( 'featured-spotlight' !== ( $attributes['layoutType'] ?? 'quote-cards' ) ) {
		$lg = (int) ( $attributes['columnsLg'] ?? 3 );
		$md = (int) ( $attributes['columnsMd'] ?? 2 );
		$sm = (int) ( $attributes['columnsSm'] ?? 1 );
		$g  = "{$sel} .wpddbp-tm-grid";
		$css .= "{$g}{grid-template-columns:repeat({$lg},minmax(0,1fr));}@media (max-width:900px){{$g}{grid-template-columns:repeat({$md},minmax(0,1fr));}}@media (max-width:600px){{$g}{grid-template-columns:repeat({$sm},minmax(0,1fr));}}";
		$w  = "{$sel} .wpddbp-tm-wall";
		$css .= "{$w}{column-count:{$lg};}@media (max-width:900px){{$w}{column-count:{$md};}}@media (max-width:600px){{$w}{column-count:{$sm};}}";
	}

	// ---- accent + star CSS vars ----
	$rootvars = '';
	if ( ! empty( $attributes['accentColor'] ) ) { $rootvars .= '--wpddbp-tm-accent:' . $attributes['accentColor'] . ';'; }
	if ( ! empty( $attributes['starColor'] ) ) { $rootvars .= '--tm-star:' . $attributes['starColor'] . ';'; }
	if ( '' !== $rootvars ) { $css .= "{$sel} .wpddbp-tm{{$rootvars}}"; }

	// ---- card surface ----
	$card_sel = "{$sel} .wpddbp-tm-card, {$sel} .wpddbp-tm-mcard, {$sel} .wpddbp-tm-heropanel, {$sel} .wpddbp-tm-thumb";
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

	// ---- quote (colour + typography) ----
	$q_sel = "{$sel} .wpddbp-tm-quote, {$sel} .wpddbp-tm-pull";
	$qbase = '';
	$qmd   = '';
	$qsm   = '';
	if ( ! empty( $attributes['quoteTypography'] ) ) {
		$t     = $generate_typography_css( $q_sel, $attributes['quoteTypography'] );
		$qbase .= $t['base']; $qmd .= $t['md']; $qsm .= $t['sm'];
	}
	$qbase .= $generate_color_css( $attributes['quoteColor'] ?? '', 'color' );
	$css   .= wpddbp_assemble_responsive_block( $q_sel, $qbase, $qmd, $qsm );

	// ---- name colour ----
	if ( ! empty( $attributes['nameColor'] ) ) {
		$css .= "{$sel} .wpddbp-tm-name{color:" . $attributes['nameColor'] . ';}';
	}

	return $css;
}
