<?php
/**
 * Countdown — dynamic CSS generator.
 *
 * Duplicated + renamed from the reference blocks plugin (architecture §3).
 * Variant baselines live in style.scss; this emits per-instance overrides only.
 * Wrapper background / padding / radius target the visual `.wpddbp-cd-body`
 * card; wrapper margin targets the block root.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_countdown_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css ) {
	$sel  = "#{$unique_id}";
	$body = "#{$unique_id} .wpddbp-cd-body";
	$css  = '';

	// ---- wrapper: background / padding / radius on the card body ----
	$base = '';
	$md   = '';
	$sm   = '';
	if ( ! empty( $attributes['wrapperPadding'] ) ) {
		$s     = $generate_spacing_css( $body, $attributes['wrapperPadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	$base .= $generate_color_css( $attributes['wrapperBackground'] ?? '', 'background' );
	if ( ! empty( $attributes['wrapperBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['wrapperBorderRadius'] );
	}
	$css .= wpddbp_assemble_responsive_block( $body, $base, $md, $sm );

	// ---- wrapper margin on the block root ----
	if ( ! empty( $attributes['wrapperMargin'] ) ) {
		$s    = $generate_spacing_css( $sel, $attributes['wrapperMargin'], 'margin' );
		$css .= wpddbp_assemble_responsive_block( $sel, $s['base'], $s['md'], $s['sm'] );
	}

	// ---- text + number elements ----
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
	$css .= $emit( '.wpddbp-cd-eyebrow', 'eyebrowColor', null, null );
	$css .= $emit( '.wpddbp-cd-title', 'titleColor', 'titleTypography', 'titleMargin' );
	$css .= $emit( '.wpddbp-cd-lede', 'descriptionColor', 'descriptionTypography', null );
	$css .= $emit( '.wpddbp-cd-num', 'numberColor', 'numberTypography', null );
	$css .= $emit( '.wpddbp-cd-label', 'labelColor', null, null );

	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-cd-accent{color:" . $attributes['accentColor'] . ';}';
	}
	if ( ! empty( $attributes['segmentBackground'] ) ) {
		$b    = $attributes['segmentBackground'];
		$css .= "{$sel} .wpddbp-cd-card{background:{$b};}{$sel} .wpddbp-cd-box{background:{$b};}{$sel} .wpddbp-cd-tile{background:{$b};}";
	}

	return $css;
}
