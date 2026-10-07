<?php
/**
 * Doctor Schedule block — dynamic CSS generator (Tier B/C, Phase 5).
 *
 * Per-instance overrides on top of style.scss. The accent + the two clinic key
 * colours are emitted as CSS custom properties on the widget root so every tinted
 * atom (dots, today highlight, slot hover) recolours from one place. Selector set is
 * mirrored verbatim by edit.js previewCss.
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

function wpddbp_generate_doctor_schedule_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	$root = "{$sel} .wpddbp-sch";

	// ---- accent + clinic key colours (CSS vars on the widget root) ----
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$root}{--wpddbp-sch-accent:" . $attributes['accentColor'] . ';}';
	}
	if ( ! empty( $attributes['clinicColor1'] ) ) {
		$css .= "{$root}{--wpddbp-sch-c0:" . $attributes['clinicColor1'] . ';}';
	}
	if ( ! empty( $attributes['clinicColor2'] ) ) {
		$css .= "{$root}{--wpddbp-sch-c1:" . $attributes['clinicColor2'] . ';}';
	}

	// ---- surface (boxed layouts: week-grid / spotlight / compact sidebar+inline) ----
	$surf = "{$sel} .wpddbp-sch-surface";
	$sbase = $generate_color_css( $attributes['surfaceBg'] ?? '', 'background' );
	if ( ! empty( $attributes['surfaceBorderColor'] ) ) {
		$sbase .= 'border-color:' . $attributes['surfaceBorderColor'] . ';';
	}
	if ( ! empty( $attributes['surfaceRadius'] ) ) {
		$sbase .= $generate_border_radius_css( $attributes['surfaceRadius'] );
	}
	if ( ! empty( $attributes['surfaceBoxShadow'] ) ) {
		$sbase .= $generate_box_shadow_css( $attributes['surfaceBoxShadow'] );
	}
	if ( '' !== $sbase ) {
		$css .= "{$surf}{{$sbase}}";
	}

	// ---- eyebrow colour ----
	if ( ! empty( $attributes['eyebrowColor'] ) ) {
		$css .= "{$sel} .wpddbp-sch-eyebrow{color:" . $attributes['eyebrowColor'] . ';}';
	}

	// ---- doctor name (colour + typography) ----
	$name_sel = "{$sel} .wpddbp-sch-docname, {$sel} .wpddbp-sch-idname, {$sel} .wpddbp-sch-cname, {$sel} .wpddbp-sch-fsname";
	$nbase = '';
	$nmd   = '';
	$nsm   = '';
	if ( ! empty( $attributes['nameTypography'] ) ) {
		$t     = $generate_typography_css( $name_sel, $attributes['nameTypography'] );
		$nbase .= $t['base'];
		$nmd   .= $t['md'];
		$nsm   .= $t['sm'];
	}
	$nbase .= $generate_color_css( $attributes['nameColor'] ?? '', 'color' );
	$css   .= wpddbp_assemble_responsive_block( $name_sel, $nbase, $nmd, $nsm );

	// ---- day labels (colour + typography) ----
	$day_sel = "{$sel} .wpddbp-sch-dname, {$sel} .wpddbp-sch-panelday, {$sel} .wpddbp-sch-chname, {$sel} .wpddbp-sch-crowday, {$sel} .wpddbp-sch-fsday";
	$dbase = '';
	$dmd   = '';
	$dsm   = '';
	if ( ! empty( $attributes['dayTypography'] ) ) {
		$t     = $generate_typography_css( $day_sel, $attributes['dayTypography'] );
		$dbase .= $t['base'];
		$dmd   .= $t['md'];
		$dsm   .= $t['sm'];
	}
	$dbase .= $generate_color_css( $attributes['dayColor'] ?? '', 'color' );
	$css   .= wpddbp_assemble_responsive_block( $day_sel, $dbase, $dmd, $dsm );

	// ---- slots (normal + hover) ----
	$slot = $generate_color_css( $attributes['slotColor'] ?? '', 'color' );
	$slot .= $generate_color_css( $attributes['slotBg'] ?? '', 'background' );
	if ( ! empty( $attributes['slotBorderColor'] ) ) {
		$slot .= 'border-color:' . $attributes['slotBorderColor'] . ';';
	}
	if ( ! empty( $attributes['slotRadius'] ) ) {
		$slot .= $generate_border_radius_css( $attributes['slotRadius'] );
	}
	if ( '' !== $slot ) {
		$css .= "{$sel} .wpddbp-sch-slot{{$slot}}";
	}
	$slot_h = $generate_color_css( $attributes['slotHoverColor'] ?? '', 'color' );
	$slot_h .= $generate_color_css( $attributes['slotHoverBg'] ?? '', 'background' );
	if ( '' !== $slot_h ) {
		$css .= "{$sel} .wpddbp-sch-slot:hover{{$slot_h}}";
	}

	return $css;
}
