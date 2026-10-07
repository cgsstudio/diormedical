<?php
/**
 * FAQ — dynamic CSS generator.
 *
 * Built from scratch on the shared helper closures (no reference counterpart).
 * Editorial baselines live in style.scss; this emits per-instance overrides for
 * the heading, question, answer, toggle/number, card, divider, category and help
 * button across all six layouts.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_faq_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
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
	if ( ! empty( $attributes['wrapperBorderRadius'] ) ) {
		$base .= $generate_border_radius_css( $attributes['wrapperBorderRadius'] );
	}
	$css .= wpddbp_assemble_responsive_block( $sel, $base, $md, $sm );

	// ---- text elements (color + typography) ----
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
	$css .= $emit( "{$sel} .wpddbp-faq-eyebrow", $attributes['eyebrowColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-faq-heading", $attributes['headingColor'] ?? '', $attributes['headingTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-faq-subheading", $attributes['subheadingColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-faq-q", $attributes['questionColor'] ?? '', $attributes['questionTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-faq-panel-inner p, {$sel} .wpddbp-faq-qa-card p", $attributes['answerColor'] ?? '', $attributes['answerTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-faq-q-num", $attributes['numberColor'] ?? '', null );

	// ---- conditional rules ----
	if ( ! empty( $attributes['questionOpenColor'] ) ) {
		$c    = $attributes['questionOpenColor'];
		$css .= "{$sel} .wpddbp-faq-q:hover{color:{$c};}{$sel} .wpddbp-faq-q[aria-expanded=\"true\"]{color:{$c};}";
	}
	if ( ! empty( $attributes['linkColor'] ) ) {
		$c    = $attributes['linkColor'];
		$css .= "{$sel} .wpddbp-faq-panel-inner a{color:{$c};}{$sel} .wpddbp-faq-qa-card a{color:{$c};}";
	}
	if ( ! empty( $attributes['iconColor'] ) ) {
		$c    = $attributes['iconColor'];
		$css .= "{$sel} .wpddbp-faq-q-icon{color:{$c};}{$sel} .wpddbp-faq-chevron{color:{$c};}{$sel} .wpddbp-faq-pm{color:{$c};}";
	}
	if ( ! empty( $attributes['iconOpenColor'] ) ) {
		$c    = $attributes['iconOpenColor'];
		$css .= "{$sel} .wpddbp-faq-q[aria-expanded=\"true\"] .wpddbp-faq-chevron{color:{$c};}{$sel} .wpddbp-faq-q[aria-expanded=\"true\"] .wpddbp-faq-pm{color:{$c};}";
	}
	if ( ! empty( $attributes['cardBackground'] ) ) {
		$c    = $attributes['cardBackground'];
		$css .= "{$sel} .wpddbp-faq-item{background:{$c};}{$sel} .wpddbp-faq-qa-card{background:{$c};}";
	}
	if ( ! empty( $attributes['cardBorderRadius'] ) ) {
		$r = $generate_border_radius_css( $attributes['cardBorderRadius'] );
		if ( '' !== $r ) {
			$css .= "{$sel} .wpddbp-faq-item{{$r}}{$sel} .wpddbp-faq-qa-card{{$r}}";
		}
	}
	if ( ! empty( $attributes['cardBoxShadow'] ) ) {
		$sh = $generate_box_shadow_css( $attributes['cardBoxShadow'] );
		if ( '' !== $sh ) {
			$css .= "{$sel} .wpddbp-faq-item{{$sh}}{$sel} .wpddbp-faq-qa-card{{$sh}}";
		}
	}
	if ( ! empty( $attributes['openAccentColor'] ) ) {
		$css .= "{$sel} .wpddbp-faq-item.is-open{border-color:" . $attributes['openAccentColor'] . ';}';
	}
	if ( ! empty( $attributes['dividerColor'] ) ) {
		$c    = $attributes['dividerColor'];
		$css .= "{$sel} .wpddbp-faq-item{border-color:{$c};}{$sel} .wpddbp-faq-list{border-top-color:{$c};}";
	}
	if ( ! empty( $attributes['catActiveColor'] ) ) {
		$c    = $attributes['catActiveColor'];
		$css .= "{$sel} .wpddbp-faq-cat-btn[aria-selected=\"true\"]{background:{$c};}{$sel} .wpddbp-faq-tab[aria-selected=\"true\"]{background:{$c};}";
	}

	$hb = '';
	if ( ! empty( $attributes['helpBtnColor'] ) ) {
		$hb .= 'color:' . $attributes['helpBtnColor'] . ';';
	}
	if ( ! empty( $attributes['helpBtnBackground'] ) ) {
		$hb .= 'background:' . $attributes['helpBtnBackground'] . ';';
	}
	if ( '' !== $hb ) {
		$css .= "{$sel} .wpddbp-faq-help-btn{{$hb}}";
	}
	$hbh = '';
	if ( ! empty( $attributes['helpBtnHoverColor'] ) ) {
		$hbh .= 'color:' . $attributes['helpBtnHoverColor'] . ';';
	}
	if ( ! empty( $attributes['helpBtnHoverBackground'] ) ) {
		$hbh .= 'background:' . $attributes['helpBtnHoverBackground'] . ';';
	}
	if ( '' !== $hbh ) {
		$css .= "{$sel} .wpddbp-faq-help-btn:hover{{$hbh}}";
	}

	return $css;
}
