<?php
/**
 * Slider — dynamic CSS generator.
 *
 * Duplicated + renamed from the reference blocks plugin (architecture §3).
 * Editorial baselines live in style.scss; this emits per-instance overrides.
 * The hero image overlay is handled inline in render.php (a `--wpddbp-overlay`
 * custom property), so it is intentionally not emitted here.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_slider_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel    = "#{$unique_id}";
	$layout = $attributes['layoutType'] ?? 'hero';
	$css    = '';

	// ---- wrapper: height (hero), border-radius, box-shadow ----
	if ( 'hero' === $layout && ! empty( $attributes['wrapperHeight'] ) ) {
		$h    = $attributes['wrapperHeight'];
		$unit = isset( $h['unit'] ) && '' !== $h['unit'] ? $h['unit'] : 'px';
		if ( isset( $h['lg'] ) && '' !== $h['lg'] && null !== $h['lg'] ) {
			$css .= "{$sel}{height:{$h['lg']}{$unit};}";
		}
		if ( isset( $h['md'] ) && '' !== $h['md'] && null !== $h['md'] ) {
			$css .= "@media (max-width:1024px){{$sel}{height:{$h['md']}{$unit};}}";
		}
		if ( isset( $h['sm'] ) && '' !== $h['sm'] && null !== $h['sm'] ) {
			$css .= "@media (max-width:767px){{$sel}{height:{$h['sm']}{$unit};}}";
		}
	}
	$wrap = '';
	if ( ! empty( $attributes['wrapperBorderRadius'] ) ) {
		$wrap .= $generate_border_radius_css( $attributes['wrapperBorderRadius'] );
	}
	if ( ! empty( $attributes['wrapperBoxShadow'] ) ) {
		$wrap .= $generate_box_shadow_css( $attributes['wrapperBoxShadow'] );
	}
	if ( '' !== $wrap ) {
		$css .= "{$sel}{{$wrap}}";
	}

	if ( 'cards' === $layout && ! empty( $attributes['cardBackground'] ) ) {
		$css .= "{$sel} .wpddbp-sl-card{background:" . $attributes['cardBackground'] . ';}';
	}

	// ---- section header colors ----
	if ( ! empty( $attributes['headerEyebrowColor'] ) ) {
		$css .= "{$sel} .wpddbp-sl-head-eyebrow{color:" . $attributes['headerEyebrowColor'] . ';}';
	}
	if ( ! empty( $attributes['headerTitleColor'] ) ) {
		$css .= "{$sel} .wpddbp-sl-head-title{color:" . $attributes['headerTitleColor'] . ';}';
	}
	if ( ! empty( $attributes['headerDescriptionColor'] ) ) {
		$css .= "{$sel} .wpddbp-sl-head-desc{color:" . $attributes['headerDescriptionColor'] . ';}';
	}

	// ---- content text elements ----
	$emit = function ( $subsel, $color_key, $typo_key ) use ( $attributes, $unique_id, $generate_typography_css, $generate_color_css ) {
		$s    = "#{$unique_id} {$subsel}";
		$base = '';
		$md   = '';
		$sm   = '';
		if ( $typo_key && ! empty( $attributes[ $typo_key ] ) ) {
			$t     = $generate_typography_css( $s, $attributes[ $typo_key ] );
			$base .= $t['base'];
			$md   .= $t['md'];
			$sm   .= $t['sm'];
		}
		$base .= $generate_color_css( $attributes[ $color_key ] ?? '', 'color' );
		return wpddbp_assemble_responsive_block( $s, $base, $md, $sm );
	};
	$css .= $emit( '.wpddbp-sl-eyebrow', 'subtitleColor', 'subtitleTypography' );
	$css .= $emit( '.wpddbp-sl-title', 'titleColor', 'titleTypography' );
	$css .= $emit( '.wpddbp-sl-desc', 'descriptionColor', 'descriptionTypography' );

	// ---- button: typography + padding + radius + normal/hover colors ----
	$btn_sel = "#{$unique_id} .wpddbp-sl-btn";
	$base    = '';
	$md      = '';
	$sm      = '';
	if ( ! empty( $attributes['buttonTypography'] ) ) {
		$t     = $generate_typography_css( $btn_sel, $attributes['buttonTypography'] );
		$base .= $t['base'];
		$md   .= $t['md'];
		$sm   .= $t['sm'];
	}
	if ( ! empty( $attributes['buttonPadding'] ) ) {
		$sp    = $generate_spacing_css( $btn_sel, $attributes['buttonPadding'], 'padding' );
		$base .= $sp['base'];
		$md   .= $sp['md'];
		$sm   .= $sp['sm'];
	}
	if ( ! empty( $attributes['buttonBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['buttonBorderRadius'] );
	}
	$base .= $generate_color_css( $attributes['buttonColor'] ?? '', 'color' );
	$base .= $generate_color_css( $attributes['buttonBackground'] ?? '', 'background-color' );
	$css  .= wpddbp_assemble_responsive_block( $btn_sel, $base, $md, $sm );

	$hover = $generate_color_css( $attributes['buttonHoverColor'] ?? '', 'color' )
		. $generate_color_css( $attributes['buttonHoverBackground'] ?? '', 'background-color' );
	if ( '' !== $hover ) {
		$css .= "{$btn_sel}:hover{{$hover}}";
	}

	return $css;
}
