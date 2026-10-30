<?php
/**
 * Doctor Awards block — dynamic CSS generator (Tier B, Phase 3).
 *
 * Per-instance overrides on top of style.scss. `accentColor` drives the whole
 * bronze system via the `--awards-accent` custom property on the block root.
 * trophy-wall column-count is responsive; credential-list is single column.
 * Selector set mirrored by edit.js previewCss.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_awards_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel  = "#{$unique_id}";
	$wall = "#{$unique_id} .wpddbp-awards-wall";
	$css  = '';

	// ---- accent (drives the bronze system) ----
	$root = '';
	if ( ! empty( $attributes['accentColor'] ) ) {
		$root .= '--awards-accent:' . $attributes['accentColor'] . ';';
	}
	if ( '' !== $root ) {
		$css .= "{$sel}{{$root}}";
	}

	// ---- trophy-wall masonry columns + gap ----
	if ( 'credential-list' !== ( $attributes['layoutType'] ?? 'trophy-wall' ) ) {
		$lg = (int) ( $attributes['columnsLg'] ?? 3 );
		$md = (int) ( $attributes['columnsMd'] ?? 2 );
		$sm = (int) ( $attributes['columnsSm'] ?? 1 );
		$css .= "{$wall}{column-count:{$lg};}";
		$css .= "@media (max-width:1024px){{$wall}{column-count:{$md};}}";
		$css .= "@media (max-width:767px){{$wall}{column-count:{$sm};}}";
	}
	$gap = $attributes['gap'] ?? array();
	if ( isset( $gap['lg'] ) && '' !== (string) $gap['lg'] ) {
		$unit = $gap['unit'] ?? 'px';
		$css .= "{$wall}{column-gap:" . (int) $gap['lg'] . $unit . ';}';
		$css .= "{$sel} .wpddbp-awards-credlist{gap:" . (int) $gap['lg'] . $unit . ';}';
	}

	// ---- card (trophy card + credlist card) ----
	$card_sel = "{$sel} .wpddbp-awards-card, {$sel} .wpddbp-awards-cl-card";
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

	// ---- title (color + typography) ----
	$title_sel = "{$sel} .wpddbp-awards-title, {$sel} .wpddbp-awards-cl-itemtitle";
	$base = '';
	$md   = '';
	$sm   = '';
	if ( ! empty( $attributes['titleTypography'] ) ) {
		$t     = $generate_typography_css( $title_sel, $attributes['titleTypography'] );
		$base .= $t['base'];
		$md   .= $t['md'];
		$sm   .= $t['sm'];
	}
	$base .= $generate_color_css( $attributes['titleColor'] ?? '', 'color' );
	$css  .= wpddbp_assemble_responsive_block( $title_sel, $base, $md, $sm );

	// ---- year colour ----
	if ( ! empty( $attributes['yearColor'] ) ) {
		$css .= "{$sel} .wpddbp-awards-year, {$sel} .wpddbp-awards-yr, {$sel} .wpddbp-awards-yrtag, {$sel} .wpddbp-awards-yearbig, {$sel} .wpddbp-awards-yrstamp, {$sel} .wpddbp-awards-cl-yr{color:" . $attributes['yearColor'] . ';}';
	}

	// ---- doctor name colour ----
	if ( ! empty( $attributes['doctorColor'] ) ) {
		$css .= "{$sel} .wpddbp-awards-who b, {$sel} .wpddbp-awards-cl-name{color:" . $attributes['doctorColor'] . ';}';
	}

	return $css;
}
