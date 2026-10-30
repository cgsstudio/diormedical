<?php
/**
 * Clinics block — dynamic CSS generator (Tier B, Phase 4).
 *
 * Per-instance overrides on top of style.scss. Columns apply to card (masonry
 * column-count), compact + gallery (grid-template-columns); feature is a single
 * column and map is a fixed two-pane board. Selector set mirrored by edit.js.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_clinics_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	$layout = $attributes['layoutType'] ?? 'card';
	$lg     = (int) ( $attributes['columnsLg'] ?? 3 );
	$md     = (int) ( $attributes['columnsMd'] ?? 2 );
	$sm     = (int) ( $attributes['columnsSm'] ?? 1 );

	if ( 'card' === $layout ) {
		$g = "{$sel} .wpddbp-clinics-grid";
		$css .= "{$g}{column-count:{$lg};}@media (max-width:1024px){{$g}{column-count:{$md};}}@media (max-width:767px){{$g}{column-count:{$sm};}}";
	} elseif ( 'compact' === $layout ) {
		$g = "{$sel} .wpddbp-clinics-compactgrid";
		$css .= "{$g}{grid-template-columns:repeat({$lg},minmax(0,1fr));}@media (max-width:767px){{$g}{grid-template-columns:1fr;}}";
	} elseif ( 'gallery' === $layout ) {
		$g = "{$sel} .wpddbp-clinics-gallerygrid";
		$gcols = max( 1, min( 3, $lg ) );
		$css .= "{$g}{grid-template-columns:repeat({$gcols},minmax(0,1fr));}@media (max-width:1024px){{$g}{grid-template-columns:repeat(" . max( 1, min( 2, $md ) ) . ",minmax(0,1fr));}}@media (max-width:767px){{$g}{grid-template-columns:1fr;}}";
	}

	$gap = $attributes['gap'] ?? array();
	if ( isset( $gap['lg'] ) && '' !== (string) $gap['lg'] ) {
		$unit = $gap['unit'] ?? 'px';
		$gv   = (int) $gap['lg'] . $unit;
		$css .= "{$sel} .wpddbp-clinics-grid{column-gap:{$gv};}";
		$css .= "{$sel} .wpddbp-clinics-compactgrid,{$sel} .wpddbp-clinics-gallerygrid,{$sel} .wpddbp-clinics-featurelist{gap:{$gv};}";
	}

	// ---- card / row surfaces ----
	$card_sel = "{$sel} .wpddbp-clinics-card, {$sel} .wpddbp-clinics-crow, {$sel} .wpddbp-clinics-gcard, {$sel} .wpddbp-clinics-frow";
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

	// ---- name (color + typography) ----
	$name_sel = "{$sel} .wpddbp-clinics-name";
	$base = '';
	$mdc  = '';
	$smc  = '';
	if ( ! empty( $attributes['nameTypography'] ) ) {
		$t    = $generate_typography_css( $name_sel, $attributes['nameTypography'] );
		$base .= $t['base'];
		$mdc  .= $t['md'];
		$smc  .= $t['sm'];
	}
	$base .= $generate_color_css( $attributes['nameColor'] ?? '', 'color' );
	$css  .= wpddbp_assemble_responsive_block( $name_sel, $base, $mdc, $smc );

	// ---- tagline colour ----
	if ( ! empty( $attributes['taglineColor'] ) ) {
		$css .= "{$sel} .wpddbp-clinics-tagline, {$sel} .wpddbp-clinics-flede{color:" . $attributes['taglineColor'] . ';}';
	}

	return $css;
}
