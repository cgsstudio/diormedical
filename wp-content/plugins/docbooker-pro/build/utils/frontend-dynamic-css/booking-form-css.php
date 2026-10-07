<?php
/**
 * Booking Form block — dynamic CSS generator (Tier C, Phase 5).
 *
 * Per-instance overrides on top of style.scss. Accent + ink are emitted as CSS
 * custom properties on the app root (`.wpddbp-bf`) so the whole wizard re-tints from
 * one control. Selector set mirrored verbatim by edit.js previewCss.
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

function wpddbp_generate_booking_form_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';
	$root = "{$sel} .wpddbp-bf";

	// ---- accent + ink (CSS vars on the app root) ----
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$root}{--wpddbp-bf-accent:" . $attributes['accentColor'] . ';--wpddbp-bf-accent-deep:' . $attributes['accentColor'] . ';}';
	}
	if ( ! empty( $attributes['inkColor'] ) ) {
		$css .= "{$root}{--wpddbp-bf-ink:" . $attributes['inkColor'] . ';}';
	}

	// ---- wrapper surface ----
	$surf  = "{$sel} .wpddbp-bf-surface";
	$sbase = $generate_color_css( $attributes['wrapperBg'] ?? '', 'background' );
	if ( ! empty( $attributes['wrapperBorderColor'] ) ) {
		$sbase .= 'border-color:' . $attributes['wrapperBorderColor'] . ';';
	}
	if ( ! empty( $attributes['wrapperRadius'] ) ) {
		$sbase .= $generate_border_radius_css( $attributes['wrapperRadius'] );
	}
	if ( ! empty( $attributes['wrapperBoxShadow'] ) ) {
		$sbase .= $generate_box_shadow_css( $attributes['wrapperBoxShadow'] );
	}
	if ( '' !== $sbase ) { $css .= "{$surf}{{$sbase}}"; }

	// ---- headings (colour + typography) ----
	$h_sel = "{$sel} .wpddbp-bf-title, {$sel} .wpddbp-bf-headtitle";
	$hbase = '';
	$hmd   = '';
	$hsm   = '';
	if ( ! empty( $attributes['headingTypography'] ) ) {
		$t     = $generate_typography_css( $h_sel, $attributes['headingTypography'] );
		$hbase .= $t['base']; $hmd .= $t['md']; $hsm .= $t['sm'];
	}
	$hbase .= $generate_color_css( $attributes['headingColor'] ?? '', 'color' );
	$css   .= wpddbp_assemble_responsive_block( $h_sel, $hbase, $hmd, $hsm );

	// ---- form fields ----
	$fbase = $generate_color_css( $attributes['fieldBg'] ?? '', 'background' );
	if ( ! empty( $attributes['fieldBorderColor'] ) ) {
		$fbase .= 'border-color:' . $attributes['fieldBorderColor'] . ';';
	}
	if ( '' !== $fbase ) { $css .= "{$sel} .wpddbp-bf-field{{$fbase}}"; }

	// ---- primary CTA (normal + hover) ----
	// !important so an admin colour choice stays above the block's own theme-hardening
	// !important base rule (style.scss). The #id selector here already out-specifies it,
	// but the base is !important, so this must be !important as well to win.
	$cta = '';
	if ( ! empty( $attributes['ctaColor'] ) )      { $cta .= 'color:' . $attributes['ctaColor'] . ' !important;'; }
	if ( ! empty( $attributes['ctaBackground'] ) ) { $cta .= 'background:' . $attributes['ctaBackground'] . ' !important;'; }
	if ( '' !== $cta ) { $css .= "{$sel} .wpddbp-bf-cta{{$cta}}"; }
	$ctah = '';
	if ( ! empty( $attributes['ctaHoverColor'] ) )      { $ctah .= 'color:' . $attributes['ctaHoverColor'] . ' !important;'; }
	if ( ! empty( $attributes['ctaHoverBackground'] ) ) { $ctah .= 'background:' . $attributes['ctaHoverBackground'] . ' !important;'; }
	if ( '' !== $ctah ) { $css .= "{$sel} .wpddbp-bf-cta:hover{{$ctah}}"; }

	return $css;
}
