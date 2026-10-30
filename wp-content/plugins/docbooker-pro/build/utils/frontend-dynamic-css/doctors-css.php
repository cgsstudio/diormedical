<?php
/**
 * Doctors block — dynamic CSS generator (Tier B, Phase 3).
 *
 * Built from scratch on the shared helper closures. Editorial baselines live in
 * style.scss; this emits per-instance overrides + the responsive grid columns
 * (mini grid / card+hover-reveal masonry). Selector set mirrored by edit.js.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_doctors_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel  = "#{$unique_id}";
	$grid = "#{$unique_id} .wpddbp-doctors-grid";
	$css  = '';

	// ---- grid columns + gap ----
	$layout = $attributes['layoutType'] ?? 'list';
	$lg     = (int) ( $attributes['columnsLg'] ?? 3 );
	$md     = (int) ( $attributes['columnsMd'] ?? 2 );
	$sm     = (int) ( $attributes['columnsSm'] ?? 1 );
	if ( 'card' === $layout || 'hover-reveal' === $layout ) {
		$css .= "{$grid}{column-count:{$lg};}";
		$css .= "@media (max-width:1024px){{$grid}{column-count:{$md};}}";
		$css .= "@media (max-width:767px){{$grid}{column-count:{$sm};}}";
	} elseif ( 'mini' === $layout ) {
		// minmax(0,1fr) (not 1fr) so a card's min-content can't blow the tracks past the
		// container — otherwise the 2-col md grid overruns the viewport (~768px) horizontally.
		$css .= "{$grid}{grid-template-columns:repeat({$lg},minmax(0,1fr));}";
		$css .= "@media (max-width:1024px){{$grid}{grid-template-columns:repeat({$md},minmax(0,1fr));}}";
		$css .= "@media (max-width:767px){{$grid}{grid-template-columns:repeat({$sm},minmax(0,1fr));}}";
	}
	$gap = $attributes['gap'] ?? array();
	if ( isset( $gap['lg'] ) && '' !== (string) $gap['lg'] ) {
		$unit = $gap['unit'] ?? 'px';
		$css .= "{$grid}{gap:" . (int) $gap['lg'] . $unit . ';}';
	}

	// ---- card ----
	$card_sel = "{$sel} .wpddbp-doctors-card";
	$cbase    = '';
	if ( ! empty( $attributes['cardPadding'] ) ) {
		$s     = $generate_spacing_css( $card_sel, $attributes['cardPadding'], 'padding' );
		$css  .= wpddbp_assemble_responsive_block( $card_sel, $s['base'], $s['md'], $s['sm'] );
	}
	$cbase .= $generate_color_css( $attributes['cardBg'] ?? '', 'background' );
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
	if ( ! empty( $attributes['imageBorderRadius'] ) ) {
		$r = $generate_border_radius_css( $attributes['imageBorderRadius'] );
		if ( '' !== $r ) {
			$css .= "{$card_sel} img{{$r}}";
		}
	}

	// ---- text (color + typography) ----
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
	$css .= $emit( "{$sel} .wpddbp-doctors-name", $attributes['nameColor'] ?? '', $attributes['nameTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-doctors-role", $attributes['designationColor'] ?? '', $attributes['designationTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-doctors-bio", $attributes['bioColor'] ?? '', $attributes['bioTypography'] ?? null );

	// ---- conditional colors ----
	if ( ! empty( $attributes['deptColor'] ) ) {
		$css .= "{$sel} .wpddbp-doctors-dept{color:" . $attributes['deptColor'] . ';}';
	}
	if ( ! empty( $attributes['deptBg'] ) ) {
		$css .= "{$sel} .wpddbp-doctors-dept{background:" . $attributes['deptBg'] . ';}';
	}
	if ( ! empty( $attributes['feeColor'] ) ) {
		$css .= "{$sel} .wpddbp-doctors-fee b{color:" . $attributes['feeColor'] . ';}';
	}
	if ( ! empty( $attributes['socialColor'] ) ) {
		$css .= "{$sel} .wpddbp-doctors-socials a{color:" . $attributes['socialColor'] . ';}';
	}
	if ( ! empty( $attributes['socialHoverColor'] ) ) {
		$css .= "{$sel} .wpddbp-doctors-socials a:hover{color:" . $attributes['socialHoverColor'] . ';}';
	}

	// ---- book button ----
	$btn_sel = "{$sel} .wpddbp-doctors-book";
	$bb      = '';
	$bb     .= $generate_color_css( $attributes['btnColor'] ?? '', 'color' );
	$bb     .= $generate_color_css( $attributes['btnBackground'] ?? '', 'background' );
	if ( ! empty( $attributes['btnRadius'] ) ) {
		$bb .= $generate_border_radius_css( $attributes['btnRadius'] );
	}
	if ( '' !== $bb ) {
		$css .= "{$btn_sel}{{$bb}}";
	}
	$bbh = '';
	$bbh .= $generate_color_css( $attributes['btnHoverColor'] ?? '', 'color' );
	$bbh .= $generate_color_css( $attributes['btnHoverBackground'] ?? '', 'background' );
	if ( '' !== $bbh ) {
		$css .= "{$btn_sel}:hover{{$bbh}}";
	}

	// ---- load more button ----
	$lm = '';
	$lm .= $generate_color_css( $attributes['loadMoreColor'] ?? '', 'color' );
	$lm .= $generate_color_css( $attributes['loadMoreBackground'] ?? '', 'background' );
	if ( '' !== $lm ) {
		$css .= "{$sel} .wpddbp-doctors-loadmore{{$lm}}";
	}

	// ---- filter bar ----
	$fb = '';
	$fb .= $generate_color_css( $attributes['filterBg'] ?? '', 'background' );
	if ( ! empty( $attributes['filterRadius'] ) ) {
		$fb .= $generate_border_radius_css( $attributes['filterRadius'] );
	}
	if ( '' !== $fb ) {
		$css .= "{$sel} .wpddbp-filter-bar{{$fb}}";
	}
	if ( ! empty( $attributes['filterLabelColor'] ) ) {
		$css .= "{$sel} .wpddbp-filter-bar__label{color:" . $attributes['filterLabelColor'] . ';}';
	}
	$fs = '';
	$fs .= $generate_color_css( $attributes['filterSelectColor'] ?? '', 'color' );
	$fs .= $generate_color_css( $attributes['filterSelectBg'] ?? '', 'background' );
	if ( '' !== $fs ) {
		$css .= "{$sel} .wpddbp-filter-bar__select{{$fs}}";
	}
	if ( ! empty( $attributes['filterSpinnerColor'] ) ) {
		$css .= "{$sel} .wpddbp-filter-bar__spinner{border-top-color:" . $attributes['filterSpinnerColor'] . ';}';
	}

	return $css;
}
