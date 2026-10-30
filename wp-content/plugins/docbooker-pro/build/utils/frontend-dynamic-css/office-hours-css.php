<?php
/**
 * Office Hours — dynamic CSS generator.
 *
 * Built from scratch on the shared helper closures (no reference counterpart).
 * Editorial baselines live in style.scss; this emits per-instance overrides only.
 * Wrapper background / padding / radius / shadow target the visual
 * `.wpddbp-oh-surface` card; wrapper margin targets the block root. Selector set
 * is mirrored by edit.js's preview overlay.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_office_hours_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel     = "#{$unique_id}";
	$surface = "#{$unique_id} .wpddbp-oh-surface";
	$css     = '';

	// ---- wrapper margin on the root ----
	if ( ! empty( $attributes['wrapperMargin'] ) ) {
		$s    = $generate_spacing_css( $sel, $attributes['wrapperMargin'], 'margin' );
		$css .= wpddbp_assemble_responsive_block( $sel, $s['base'], $s['md'], $s['sm'] );
	}

	// ---- surface: background / padding / radius / shadow ----
	$base = '';
	$md   = '';
	$sm   = '';
	if ( ! empty( $attributes['wrapperPadding'] ) ) {
		$s     = $generate_spacing_css( $surface, $attributes['wrapperPadding'], 'padding' );
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
	$css .= wpddbp_assemble_responsive_block( $surface, $base, $md, $sm );

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
	$day_sel   = "{$sel} .wpddbp-oh-day, {$sel} .wpddbp-oh-chip-day";
	$hours_sel = "{$sel} .wpddbp-oh-hours, {$sel} .wpddbp-oh-chip-hours";
	$css .= $emit( "{$sel} .wpddbp-oh-eyebrow", $attributes['eyebrowColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-oh-heading", $attributes['headingColor'] ?? '', $attributes['headingTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-oh-headline", $attributes['headlineColor'] ?? '', $attributes['headlineTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-oh-detail", $attributes['detailColor'] ?? '', null );
	$css .= $emit( $day_sel, $attributes['dayColor'] ?? '', $attributes['dayTypography'] ?? null );
	$css .= $emit( $hours_sel, $attributes['hoursColor'] ?? '', $attributes['hoursTypography'] ?? null );

	// ---- conditional colors ----
	if ( ! empty( $attributes['accentColor'] ) ) {
		$css .= "{$sel} .wpddbp-oh-accent{color:" . $attributes['accentColor'] . ';}';
	}
	if ( ! empty( $attributes['closedColor'] ) ) {
		$c    = $attributes['closedColor'];
		$css .= "{$sel} .wpddbp-oh-hours.is-closed{color:{$c};}{$sel} .wpddbp-oh-chip-hours.is-closed{color:{$c};}";
	}
	if ( ! empty( $attributes['rowBackground'] ) ) {
		$css .= "{$sel} .wpddbp-oh-row{background:" . $attributes['rowBackground'] . ';}';
	}
	if ( ! empty( $attributes['dividerColor'] ) ) {
		$c    = $attributes['dividerColor'];
		$css .= "{$sel} .wpddbp-oh-row{border-color:{$c};}{$sel} .wpddbp-oh-table td{border-color:{$c};}";
	}

	// ---- today highlight ----
	if ( ! empty( $attributes['todayBackground'] ) ) {
		$c    = $attributes['todayBackground'];
		$css .= "{$sel} .wpddbp-oh-row.is-today{background:{$c};}{$sel} .wpddbp-oh-chip.is-today{background:{$c};}";
	}
	if ( ! empty( $attributes['todayColor'] ) ) {
		$c    = $attributes['todayColor'];
		$css .= "{$sel} .is-today .wpddbp-oh-day{color:{$c};}{$sel} .is-today .wpddbp-oh-hours{color:{$c};}{$sel} .wpddbp-oh-chip.is-today .wpddbp-oh-chip-day{color:{$c};}{$sel} .wpddbp-oh-chip.is-today .wpddbp-oh-chip-hours{color:{$c};}";
	}
	if ( ! empty( $attributes['todayAccent'] ) ) {
		$c    = $attributes['todayAccent'];
		$css .= "{$sel} .wpddbp-oh-today-tag{background:{$c};}{$sel} .wpddbp-oh-chip-flag{background:{$c};}";
	}

	// ---- status pill (typography + radius + open/closed states + border) ----
	$pill_sel = "{$sel} .wpddbp-oh-status";
	$pbase    = '';
	$pmd      = '';
	$psm      = '';
	if ( ! empty( $attributes['statusTypography'] ) ) {
		$t     = $generate_typography_css( $pill_sel, $attributes['statusTypography'] );
		$pbase .= $t['base'];
		$pmd   .= $t['md'];
		$psm   .= $t['sm'];
	}
	if ( ! empty( $attributes['statusRadius'] ) ) {
		$pbase .= $generate_border_radius_css( $attributes['statusRadius'] );
	}
	$pbase .= $generate_color_css( $attributes['statusBorderColor'] ?? '', 'border-color' );
	$css   .= wpddbp_assemble_responsive_block( $pill_sel, $pbase, $pmd, $psm );

	if ( ! empty( $attributes['openTextColor'] ) || ! empty( $attributes['openBackground'] ) ) {
		$o  = '';
		$o .= $generate_color_css( $attributes['openTextColor'] ?? '', 'color' );
		$o .= $generate_color_css( $attributes['openBackground'] ?? '', 'background' );
		$css .= "{$pill_sel}.is-open{{$o}}";
	}
	if ( ! empty( $attributes['closedTextColor'] ) || ! empty( $attributes['closedBackground'] ) ) {
		$o  = '';
		$o .= $generate_color_css( $attributes['closedTextColor'] ?? '', 'color' );
		$o .= $generate_color_css( $attributes['closedBackground'] ?? '', 'background' );
		$css .= "{$pill_sel}.is-closed{{$o}}";
	}

	// ---- contact rows ----
	if ( ! empty( $attributes['contactIconColor'] ) ) {
		$css .= "{$sel} .wpddbp-oh-contact-ic{color:" . $attributes['contactIconColor'] . ';}';
	}
	if ( ! empty( $attributes['contactLabelColor'] ) ) {
		$css .= "{$sel} .wpddbp-oh-contact-label{color:" . $attributes['contactLabelColor'] . ';}';
	}
	if ( ! empty( $attributes['contactValueColor'] ) ) {
		$css .= "{$sel} .wpddbp-oh-contact-value{color:" . $attributes['contactValueColor'] . ';}';
	}

	// ---- buttons ----
	$btn = '';
	$btn .= $generate_color_css( $attributes['btnColor'] ?? '', 'color' );
	$btn .= $generate_color_css( $attributes['btnBackground'] ?? '', 'background' );
	if ( ! empty( $attributes['btnRadius'] ) ) {
		$rad = $generate_border_radius_css( $attributes['btnRadius'] );
	} else {
		$rad = '';
	}
	if ( '' !== $btn ) {
		$css .= "{$sel} .wpddbp-oh-btn--primary{{$btn}}";
	}
	if ( '' !== $rad ) {
		$css .= "{$sel} .wpddbp-oh-btn{{$rad}}";
	}
	$btnh = '';
	$btnh .= $generate_color_css( $attributes['btnHoverColor'] ?? '', 'color' );
	$btnh .= $generate_color_css( $attributes['btnHoverBackground'] ?? '', 'background' );
	if ( '' !== $btnh ) {
		$css .= "{$sel} .wpddbp-oh-btn--primary:hover{{$btnh}}";
	}

	return $css;
}
