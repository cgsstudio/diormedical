<?php
/**
 * Departments block — dynamic CSS generator (Tier B, Phase 3).
 *
 * Per-instance overrides on top of style.scss. Grid columns apply to the grid +
 * icon-cards layouts (pill-cloud is flex-wrap, no columns). Card style controls
 * target both card elements. Selector set mirrored by edit.js previewCss.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_departments_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel  = "#{$unique_id}";
	$grid = "#{$unique_id} .wpddbp-dept-grid";
	$css  = '';

	$layout = $attributes['layoutType'] ?? 'grid';

	// ---- grid columns (grid + icon-cards) ----
	if ( 'pill-cloud' !== $layout ) {
		$lg = (int) ( $attributes['columnsLg'] ?? 4 );
		$md = (int) ( $attributes['columnsMd'] ?? 3 );
		$sm = (int) ( $attributes['columnsSm'] ?? 1 );
		$css .= "{$grid}{grid-template-columns:repeat({$lg},minmax(0,1fr));}";
		$css .= "@media (max-width:1024px){{$grid}{grid-template-columns:repeat({$md},minmax(0,1fr));}}";
		$css .= "@media (max-width:767px){{$grid}{grid-template-columns:repeat({$sm},minmax(0,1fr));}}";
	}

	$gap = $attributes['gap'] ?? array();
	if ( isset( $gap['lg'] ) && '' !== (string) $gap['lg'] ) {
		$unit = $gap['unit'] ?? 'px';
		$css .= "{$grid}{gap:" . (int) $gap['lg'] . $unit . ';}';
		$css .= "#{$unique_id} .wpddbp-dept-cloud{gap:" . (int) $gap['lg'] . $unit . ';}';
	}

	// ---- card (grid card + icon card share the controls) ----
	$card_sel = "{$sel} .wpddbp-dept-card, {$sel} .wpddbp-dept-icard";
	$cbase    = '';
	$cbase   .= $generate_color_css( $attributes['cardBg'] ?? '', 'background' );
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
	if ( ! empty( $attributes['cardPadding'] ) ) {
		// Grid card padding lives on the body; icon card padding on the card itself.
		$s1   = $generate_spacing_css( "{$sel} .wpddbp-dept-card .wpddbp-dept-body", $attributes['cardPadding'], 'padding' );
		$css .= wpddbp_assemble_responsive_block( "{$sel} .wpddbp-dept-card .wpddbp-dept-body", $s1['base'], $s1['md'], $s1['sm'] );
		$s2   = $generate_spacing_css( "{$sel} .wpddbp-dept-icard", $attributes['cardPadding'], 'padding' );
		$css .= wpddbp_assemble_responsive_block( "{$sel} .wpddbp-dept-icard", $s2['base'], $s2['md'], $s2['sm'] );
	}

	// ---- text: name + description (color + typography) ----
	$emit = function ( $full_sel, $color, $typo ) use ( $generate_typography_css, $generate_color_css ) {
		$base = '';
		$md   = '';
		$sm   = '';
		if ( ! empty( $typo ) ) {
			$t     = $generate_typography_css( $full_sel, $typo );
			$base .= $t['base'];
			$md   .= $t['md'];
			$sm   .= $t['sm'];
		}
		$base .= $generate_color_css( $color ?? '', 'color' );
		return wpddbp_assemble_responsive_block( $full_sel, $base, $md, $sm );
	};
	$css .= $emit( "{$sel} .wpddbp-dept-name", $attributes['nameColor'] ?? '', $attributes['nameTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-dept-tagline", $attributes['descriptionColor'] ?? '', $attributes['descriptionTypography'] ?? null );

	// ---- count colour ----
	if ( ! empty( $attributes['countColor'] ) ) {
		$css .= "{$sel} .wpddbp-dept-count, {$sel} .wpddbp-dept-count b, {$sel} .wpddbp-dept-icard-count, {$sel} .wpddbp-dept-icard-count b{color:" . $attributes['countColor'] . ';}';
	}

	return $css;
}
