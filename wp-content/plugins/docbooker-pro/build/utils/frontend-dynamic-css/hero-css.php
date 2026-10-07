<?php
/**
 * Hero — dynamic CSS generator.
 *
 * Built from scratch on the shared helper closures (no reference counterpart).
 * Editorial baselines live in style.scss; this emits per-instance overrides only.
 * Section background / padding / margin / radius / shadow target the block root;
 * the media frame, ring, blob, scrim, trust strip, floating cards and CTAs each
 * get their own targeted rules. Selector set is mirrored by edit.js's overlay.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_hero_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// Cap the inner content to the shared 1240px section width so a full-bleed hero
	// lines up with the other (contained) sections on the page. Emitted per-instance
	// with #id specificity so it reliably wins the cascade.
	$css .= "{$sel} .wpddbp-hero-inner{max-width:1240px;margin-left:auto;margin-right:auto;}";

	// ---- section root: bg / padding / margin / radius / shadow ----
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
	if ( ! empty( $attributes['wrapperBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['wrapperBorderRadius'] );
	}
	if ( ! empty( $attributes['wrapperBoxShadow'] ) ) {
		$base .= $generate_box_shadow_css( $attributes['wrapperBoxShadow'] );
	}
	$css .= wpddbp_assemble_responsive_block( $sel, $base, $md, $sm );

	// ---- media frame ----
	$frame = '';
	if ( ! empty( $attributes['frameRadius'] ) ) {
		$frame .= $generate_border_radius_css( $attributes['frameRadius'] );
	}
	if ( ! empty( $attributes['frameBoxShadow'] ) ) {
		$frame .= $generate_box_shadow_css( $attributes['frameBoxShadow'] );
	}
	if ( '' !== $frame ) {
		$css .= "{$sel} .wpddbp-hero-frame{{$frame}}";
	}
	if ( ! empty( $attributes['ringColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-ring{border-color:" . $attributes['ringColor'] . ';}';
	}
	if ( ! empty( $attributes['blobColor'] ) ) {
		$c    = $attributes['blobColor'];
		$css .= "{$sel} .wpddbp-hero-blob-1{background:radial-gradient(circle at 30% 30%,{$c},transparent 70%);}";
	}
	if ( ! empty( $attributes['scrimColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-scrim{background:" . $attributes['scrimColor'] . ';}';
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
	$css .= $emit( "{$sel} .wpddbp-hero-eyebrow", $attributes['eyebrowColor'] ?? '', $attributes['eyebrowTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-hero-lede", $attributes['ledeColor'] ?? '', $attributes['ledeTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-hero-ts-num", $attributes['trustNumberColor'] ?? '', $attributes['trustNumberTypography'] ?? null );

	// heading (color + typography + margin)
	$hbase = '';
	$hmd   = '';
	$hsm   = '';
	if ( ! empty( $attributes['headingTypography'] ) ) {
		$t     = $generate_typography_css( "{$sel} .wpddbp-hero-heading", $attributes['headingTypography'] );
		$hbase .= $t['base'];
		$hmd   .= $t['md'];
		$hsm   .= $t['sm'];
	}
	if ( ! empty( $attributes['headingMargin'] ) ) {
		$s     = $generate_spacing_css( "{$sel} .wpddbp-hero-heading", $attributes['headingMargin'], 'margin' );
		$hbase .= $s['base'];
		$hmd   .= $s['md'];
		$hsm   .= $s['sm'];
	}
	$hbase .= $generate_color_css( $attributes['headingColor'] ?? '', 'color' );
	$css   .= wpddbp_assemble_responsive_block( "{$sel} .wpddbp-hero-heading", $hbase, $hmd, $hsm );

	// ---- conditional colors ----
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-accent{color:" . $attributes['accentColor'] . ';}';
	}
	if ( ! empty( $attributes['trustLabelColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-ts-label{color:" . $attributes['trustLabelColor'] . ';}';
	}
	if ( ! empty( $attributes['trustDividerColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-ts-item{border-color:" . $attributes['trustDividerColor'] . ';}';
	}

	// ---- floating cards ----
	$fc = $generate_color_css( $attributes['cardBackground'] ?? '', 'background' );
	if ( ! empty( $attributes['cardRadius'] ) ) {
		$fc .= $generate_border_radius_css( $attributes['cardRadius'] );
	}
	if ( ! empty( $attributes['cardBoxShadow'] ) ) {
		$fc .= $generate_box_shadow_css( $attributes['cardBoxShadow'] );
	}
	if ( '' !== $fc ) {
		$css .= "{$sel} .wpddbp-hero-fcard{{$fc}}";
	}
	if ( ! empty( $attributes['cardValueColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-fc-value{color:" . $attributes['cardValueColor'] . ';}';
	}
	if ( ! empty( $attributes['cardLabelColor'] ) ) {
		$c    = $attributes['cardLabelColor'];
		$css .= "{$sel} .wpddbp-hero-fc-label{color:{$c};}{$sel} .wpddbp-hero-fc-meta{color:{$c};}";
	}
	if ( ! empty( $attributes['cardIconColor'] ) || ! empty( $attributes['cardIconBackground'] ) ) {
		$o  = '';
		$o .= $generate_color_css( $attributes['cardIconColor'] ?? '', 'color' );
		$o .= $generate_color_css( $attributes['cardIconBackground'] ?? '', 'background' );
		$css .= "{$sel} .wpddbp-hero-fc-icon{{$o}}";
	}

	// ---- buttons ----
	if ( ! empty( $attributes['btnRadius'] ) ) {
		$rad = $generate_border_radius_css( $attributes['btnRadius'] );
		if ( '' !== $rad ) {
			$css .= "{$sel} .wpddbp-hero-btn{{$rad}}";
		}
	}
	$pb = '';
	$pb .= $generate_color_css( $attributes['btnColor'] ?? '', 'color' );
	$pb .= $generate_color_css( $attributes['btnBackground'] ?? '', 'background' );
	if ( '' !== $pb ) {
		$css .= "{$sel} .wpddbp-hero-btn--primary{{$pb}}";
	}
	$pbh = '';
	$pbh .= $generate_color_css( $attributes['btnHoverColor'] ?? '', 'color' );
	$pbh .= $generate_color_css( $attributes['btnHoverBackground'] ?? '', 'background' );
	if ( '' !== $pbh ) {
		$css .= "{$sel} .wpddbp-hero-btn--primary:hover{{$pbh}}";
	}
	if ( ! empty( $attributes['ghostColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-btn--ghost{color:" . $attributes['ghostColor'] . ';}';
	}
	if ( ! empty( $attributes['ghostHoverColor'] ) ) {
		$css .= "{$sel} .wpddbp-hero-btn--ghost:hover{color:" . $attributes['ghostHoverColor'] . ';}';
	}

	return $css;
}
