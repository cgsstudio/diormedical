<?php
/**
 * Call to Action — dynamic CSS generator.
 *
 * Duplicated + renamed from the reference blocks plugin (architecture §3).
 * Emits per-instance wrapper bg/overlay/spacing/radius + per-element
 * color/typography/margin + button styling. Variant baselines live in style.scss.
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

function wpddbp_generate_cta_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// ---- wrapper: spacing + background + radius ----
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
	$bg = $attributes['wrapperBG'] ?? null;
	if ( ! empty( $bg['openBGColor'] ) ) {
		if ( ( $bg['type'] ?? 'classic' ) === 'classic' ) {
			if ( ! empty( $bg['classic']['color'] ) ) {
				$base .= 'background-color:' . $bg['classic']['color'] . ';';
			}
			$img = $bg['classic']['img']['imgURL'] ?? '';
			if ( ! empty( $img ) ) {
				$base .= "background-image:url('" . esc_url_raw( $img ) . "');background-size:cover;background-position:center;";
			}
		} elseif ( ( $bg['type'] ?? '' ) === 'gradient' && ! empty( $bg['gradient'] ) ) {
			$base .= 'background:' . $bg['gradient'] . ';';
		}
	}
	if ( ! empty( $attributes['wrapperBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['wrapperBorderRadius'] );
	}
	$css .= wpddbp_assemble_responsive_block( $sel, $base, $md, $sm );

	if ( ! empty( $attributes['wrapperOverlay'] ) ) {
		$css .= "{$sel}::before{background:" . $attributes['wrapperOverlay'] . ' !important;}';
	}

	// ---- text elements (color + typography + margin) ----
	$emit_text = function ( $subsel, $color_key, $typo_key, $margin_key ) use ( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css ) {
		$s    = "#{$unique_id} {$subsel}";
		$base = '';
		$md   = '';
		$sm   = '';
		if ( ! empty( $attributes[ $typo_key ] ) ) {
			$t     = $generate_typography_css( $s, $attributes[ $typo_key ] );
			$base .= $t['base'];
			$md   .= $t['md'];
			$sm   .= $t['sm'];
		}
		if ( ! empty( $attributes[ $margin_key ] ) ) {
			$sp    = $generate_spacing_css( $s, $attributes[ $margin_key ], 'margin' );
			$base .= $sp['base'];
			$md   .= $sp['md'];
			$sm   .= $sp['sm'];
		}
		$base .= $generate_color_css( $attributes[ $color_key ] ?? '', 'color' );
		return wpddbp_assemble_responsive_block( $s, $base, $md, $sm );
	};
	$css .= $emit_text( '.wpddbp-cta-eyebrow', 'eyebrowColor', 'eyebrowTypography', 'eyebrowMargin' );
	$css .= $emit_text( '.wpddbp-cta-title', 'titleColor', 'titleTypography', 'titleMargin' );
	$css .= $emit_text( '.wpddbp-cta-lede', 'descriptionColor', 'descriptionTypography', 'descriptionMargin' );

	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-cta-accent{color:" . $attributes['accentColor'] . ';}';
	}

	// ---- primary button ----
	$bsel = "#{$unique_id} .wpddbp-cta-btn--primary";
	$bb   = '';
	$bmd  = '';
	$bsm  = '';
	if ( ! empty( $attributes['buttonTypography'] ) ) {
		$t    = $generate_typography_css( $bsel, $attributes['buttonTypography'] );
		$bb  .= $t['base'];
		$bmd .= $t['md'];
		$bsm .= $t['sm'];
	}
	if ( ! empty( $attributes['buttonPadding'] ) ) {
		$sp   = $generate_spacing_css( $bsel, $attributes['buttonPadding'], 'padding' );
		$bb  .= $sp['base'];
		$bmd .= $sp['md'];
		$bsm .= $sp['sm'];
	}
	$bb .= $generate_color_css( $attributes['buttonColor'] ?? '', 'color' );
	$bb .= $generate_color_css( $attributes['buttonBackground'] ?? '', 'background-color' );
	if ( ! empty( $attributes['buttonBorderRadius'] ) ) {
		$bb .= $generate_border_radius_css( $attributes['buttonBorderRadius'] );
	}
	$css .= wpddbp_assemble_responsive_block( $bsel, $bb, $bmd, $bsm );

	$hover  = '';
	$hover .= $generate_color_css( $attributes['buttonHoverColor'] ?? '', 'color' );
	$hover .= $generate_color_css( $attributes['buttonHoverBackground'] ?? '', 'background-color' );
	if ( '' !== $hover ) {
		$css .= "{$bsel}:hover{{$hover}}";
	}

	return $css;
}
