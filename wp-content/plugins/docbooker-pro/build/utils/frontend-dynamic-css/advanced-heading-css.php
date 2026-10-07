<?php
/**
 * Advanced Heading — dynamic CSS generator.
 *
 * Duplicated + renamed from the reference blocks plugin (architecture §3). Each
 * per-element generator collects three buckets: base (lg / always), md
 * (<=1024px), sm (<=767px). `@media` stays at the top level so output is valid.
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

function wpddbp_generate_title_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css ) {
	$selector = "#{$unique_id} .wpddbp-ah-title";
	$base     = '';
	$md       = '';
	$sm       = '';

	if ( ! empty( $attributes['titleTypography'] ) ) {
		$t     = $generate_typography_css( $selector, $attributes['titleTypography'] );
		$base .= $t['base'];
		$md   .= $t['md'];
		$sm   .= $t['sm'];
	}
	if ( ! empty( $attributes['titleMargin'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['titleMargin'], 'margin' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	if ( ! empty( $attributes['titlePadding'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['titlePadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	$base .= $generate_color_css( $attributes['titleColor'] ?? '', 'color' );

	return wpddbp_assemble_responsive_block( $selector, $base, $md, $sm );
}

function wpddbp_generate_subtitle_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css ) {
	$selector = "#{$unique_id} .wpddbp-ah-eyebrow";
	$base     = '';
	$md       = '';
	$sm       = '';

	if ( ! empty( $attributes['subtitleTypography'] ) ) {
		$t     = $generate_typography_css( $selector, $attributes['subtitleTypography'] );
		$base .= $t['base'];
		$md   .= $t['md'];
		$sm   .= $t['sm'];
	}
	if ( ! empty( $attributes['subtitleMargin'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['subtitleMargin'], 'margin' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	if ( ! empty( $attributes['subtitlePadding'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['subtitlePadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	$base .= $generate_color_css( $attributes['subtitleColor'] ?? '', 'color' );

	return wpddbp_assemble_responsive_block( $selector, $base, $md, $sm );
}

function wpddbp_generate_content_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css ) {
	$selector = "#{$unique_id} .wpddbp-ah-lede";
	$base     = '';
	$md       = '';
	$sm       = '';

	if ( ! empty( $attributes['contentTypography'] ) ) {
		$t     = $generate_typography_css( $selector, $attributes['contentTypography'] );
		$base .= $t['base'];
		$md   .= $t['md'];
		$sm   .= $t['sm'];
	}
	if ( ! empty( $attributes['contentMargin'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['contentMargin'], 'margin' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	if ( ! empty( $attributes['contentPadding'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['contentPadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	$base .= $generate_color_css( $attributes['contentColor'] ?? '', 'color' );

	return wpddbp_assemble_responsive_block( $selector, $base, $md, $sm );
}

function wpddbp_generate_button_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css ) {
	$selector       = "#{$unique_id} .wpddbp-button";
	$wrap_selector  = "#{$unique_id} .wpddbp-button-wrap";
	$hover_selector = "#{$unique_id} .wpddbp-button:hover";
	$base           = '';
	$md             = '';
	$sm             = '';
	$wrap_base      = '';
	$wrap_md        = '';
	$wrap_sm        = '';
	$hover_base     = '';

	if ( ! empty( $attributes['buttonTypography'] ) ) {
		$t     = $generate_typography_css( $selector, $attributes['buttonTypography'] );
		$base .= $t['base'];
		$md   .= $t['md'];
		$sm   .= $t['sm'];
	}
	// buttonMargin renders on the wrapper (matches the editor preview, which
	// targets .wpddbp-button-wrap for margin).
	if ( ! empty( $attributes['buttonMargin'] ) ) {
		$s         = $generate_spacing_css( $wrap_selector, $attributes['buttonMargin'], 'margin' );
		$wrap_base .= $s['base'];
		$wrap_md   .= $s['md'];
		$wrap_sm   .= $s['sm'];
	}
	if ( ! empty( $attributes['buttonPadding'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['buttonPadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	$base       .= $generate_color_css( $attributes['buttonColor'] ?? '', 'color' );
	$base       .= $generate_color_css( $attributes['buttonBackground'] ?? '', 'background-color' );
	$hover_base .= $generate_color_css( $attributes['buttonHoverColor'] ?? '', 'color' );
	$hover_base .= $generate_color_css( $attributes['buttonHoverBackground'] ?? '', 'background-color' );

	if ( ! empty( $attributes['buttonBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['buttonBorderRadius'] );
	}

	$out  = '';
	$out .= wpddbp_assemble_responsive_block( $selector, $base, $md, $sm );
	$out .= wpddbp_assemble_responsive_block( $wrap_selector, $wrap_base, $wrap_md, $wrap_sm );
	if ( '' !== $hover_base ) {
		$out .= "{$hover_selector}{{$hover_base}}";
	}
	return $out;
}

function wpddbp_generate_heading_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css ) {
	$css  = '';
	$css .= wpddbp_generate_title_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css );
	$css .= wpddbp_generate_subtitle_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css );
	$css .= wpddbp_generate_content_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css );
	$css .= wpddbp_generate_button_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css );

	// Accent (highlighted word) color override.
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "#{$unique_id} .wpddbp-ah-accent{color:{$attributes['accentColor']};}";
	}

	return $css;
}
