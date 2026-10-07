<?php
/**
 * Info Box — dynamic CSS generator.
 *
 * Duplicated + renamed from the reference blocks plugin (architecture §3).
 * Variant baselines live in style.scss; this emits per-instance overrides only.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpddbp_assemble_responsive_block' ) ) {
	function wpddbp_assemble_responsive_block( $selector, $base, $md, $sm ) {
		$out = '';
		if ( '' !== $base ) {
			$out .= "{$selector}{{$base}}";
		}
		if ( '' !== $md ) {
			$out .= "@media (max-width: 1024px){{$selector}{{$md}}}";
		}
		if ( '' !== $sm ) {
			$out .= "@media (max-width: 767px){{$selector}{{$sm}}}";
		}
		return $out;
	}
}

function wpddbp_generate_info_box_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_border_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// ---- wrapper ----
	$base = '';
	$md   = '';
	$sm   = '';
	if ( ! empty( $attributes['wrapperPadding'] ) ) {
		$s     = $generate_spacing_css( $sel, $attributes['wrapperPadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	if ( ! empty( $attributes['wrapperMargin'] ) ) {
		$s     = $generate_spacing_css( $sel, $attributes['wrapperMargin'], 'margin' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	$base .= $generate_color_css( $attributes['wrapperBackground'] ?? '', 'background' );
	if ( ! empty( $attributes['wrapperBorder'] ) ) {
		$base .= $generate_border_css( $attributes['wrapperBorder'] );
	}
	if ( ! empty( $attributes['wrapperBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['wrapperBorderRadius'] );
	}
	if ( ! empty( $attributes['wrapperBoxShadow'] ) ) {
		$base .= $generate_box_shadow_css( $attributes['wrapperBoxShadow'] );
	}
	$css .= wpddbp_assemble_responsive_block( $sel, $base, $md, $sm );

	// ---- icon color (icon / badge / watermark) ----
	if ( ! empty( $attributes['iconColor'] ) ) {
		$c    = $attributes['iconColor'];
		$css .= "{$sel} .wpddbp-ib-icon{color:{$c};}{$sel} .wpddbp-ib-badge{color:{$c};}{$sel} .wpddbp-ib-watermark{color:{$c};}";
	}

	// ---- text elements ----
	$emit = function ( $subsel, $color_key, $typo_key, $margin_key ) use ( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css ) {
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
		if ( $margin_key && ! empty( $attributes[ $margin_key ] ) ) {
			$sp    = $generate_spacing_css( $s, $attributes[ $margin_key ], 'margin' );
			$base .= $sp['base'];
			$md   .= $sp['md'];
			$sm   .= $sp['sm'];
		}
		$base .= $generate_color_css( $attributes[ $color_key ] ?? '', 'color' );
		return wpddbp_assemble_responsive_block( $s, $base, $md, $sm );
	};
	$css .= $emit( '.wpddbp-ib-eyebrow', 'eyebrowColor', null, null );
	$css .= $emit( '.wpddbp-ib-title', 'titleColor', 'titleTypography', 'titleMargin' );
	$css .= $emit( '.wpddbp-ib-desc', 'descriptionColor', 'descriptionTypography', 'descriptionMargin' );
	$css .= $emit( '.wpddbp-ib-link', 'linkColor', null, null );

	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-ib-accent{color:" . $attributes['accentColor'] . ';}';
	}

	return $css;
}
