<?php
/**
 * Process Steps — dynamic CSS generator.
 *
 * Built from scratch on the shared helper closures (no reference counterpart).
 * Editorial baselines live in style.scss; this emits per-instance overrides +
 * the responsive grid columns (big-numeral / filled card layouts).
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_process_steps_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
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

	// ---- grid columns (card layouts) ----
	$layout = $attributes['layoutType'] ?? 'left-rail';
	if ( 'big-numeral' === $layout || 'filled' === $layout ) {
		$lg   = (int) ( $attributes['columnsLg'] ?? 4 );
		$mdc  = (int) ( $attributes['columnsMd'] ?? 2 );
		$smc  = (int) ( $attributes['columnsSm'] ?? 1 );
		$css .= "{$sel} .wpddbp-ps-grid{grid-template-columns:repeat({$lg},1fr);}";
		$css .= "@media (max-width:1024px){{$sel} .wpddbp-ps-grid{grid-template-columns:repeat({$mdc},1fr);}}";
		$css .= "@media (max-width:767px){{$sel} .wpddbp-ps-grid{grid-template-columns:repeat({$smc},1fr);}}";
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
	$css .= $emit( "{$sel} .wpddbp-ps-eyebrow", $attributes['eyebrowColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-ps-heading", $attributes['headingColor'] ?? '', $attributes['headingTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-ps-kicker", $attributes['kickerColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-ps-title", $attributes['titleColor'] ?? '', $attributes['titleTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-ps-desc", $attributes['descriptionColor'] ?? '', $attributes['descriptionTypography'] ?? null );

	// ---- conditional rules ----
	if ( ! empty( $attributes['detailColor'] ) ) {
		$c    = $attributes['detailColor'];
		$css .= "{$sel} .wpddbp-ps-detail{color:{$c};border-color:{$c};}";
	}
	$card_sel = "{$sel} .wpddbp-ps-card, {$sel} .wpddbp-ps-scard";
	$cbase    = $generate_color_css( $attributes['cardBackground'] ?? '', 'background' );
	if ( ! empty( $attributes['cardBorderRadius'] ) ) {
		$cbase .= $generate_border_radius_css( $attributes['cardBorderRadius'] );
	}
	if ( ! empty( $attributes['cardBoxShadow'] ) ) {
		$cbase .= $generate_box_shadow_css( $attributes['cardBoxShadow'] );
	}
	if ( '' !== $cbase ) {
		$css .= "{$card_sel}{{$cbase}}";
	}
	if ( ! empty( $attributes['dividerColor'] ) ) {
		$c    = $attributes['dividerColor'];
		$css .= "{$sel} .wpddbp-ps-card{border-color:{$c};}{$sel} .wpddbp-ps-scard{border-color:{$c};}{$sel} .wpddbp-ps-note{border-color:{$c};}";
	}
	if ( ! empty( $attributes['nodeColor'] ) ) {
		$c    = $attributes['nodeColor'];
		$css .= "{$sel} .wpddbp-ps-node{color:{$c};}{$sel} .wpddbp-ps-disc{color:{$c};}";
	}
	if ( ! empty( $attributes['nodeBackground'] ) ) {
		$c    = $attributes['nodeBackground'];
		$css .= "{$sel} .wpddbp-ps-node{background:{$c};}{$sel} .wpddbp-ps-disc{background:{$c};}";
	}
	if ( ! empty( $attributes['numberColor'] ) ) {
		$c    = $attributes['numberColor'];
		$css .= "{$sel} .wpddbp-ps-bignum{color:{$c};}{$sel} .wpddbp-ps-watermark{color:{$c};}{$sel} .wpddbp-ps-num{color:{$c};}{$sel} .wpddbp-ps-chipnum{color:{$c};}";
	}
	if ( ! empty( $attributes['iconColor'] ) ) {
		$c    = $attributes['iconColor'];
		$css .= "{$sel} .wpddbp-ps-sicon{color:{$c};}{$sel} .wpddbp-ps-cicon{color:{$c};}{$sel} .wpddbp-ps-chip{color:{$c};}";
	}
	if ( ! empty( $attributes['iconBackground'] ) ) {
		$c    = $attributes['iconBackground'];
		$css .= "{$sel} .wpddbp-ps-sicon{background:{$c};}{$sel} .wpddbp-ps-cicon{background:{$c};}";
	}
	if ( ! empty( $attributes['connectorColor'] ) ) {
		$css .= "{$sel} .wpddbp-ps-list::before{background:" . $attributes['connectorColor'] . ';}';
	}
	if ( ! empty( $attributes['currentColor'] ) ) {
		$c    = $attributes['currentColor'];
		$css .= "{$sel} .wpddbp-ps-step.is-current .wpddbp-ps-node{background:{$c};border-color:{$c};}{$sel} .wpddbp-ps-seg.is-current{background:{$c};}{$sel} .wpddbp-ps-lab.is-current .wpddbp-ps-dot{background:{$c};border-color:{$c};}";
	}
	if ( ! empty( $attributes['linkColor'] ) ) {
		$css .= "{$sel} .wpddbp-ps-link{color:" . $attributes['linkColor'] . ';}';
	}
	if ( ! empty( $attributes['linkHoverColor'] ) ) {
		$css .= "{$sel} .wpddbp-ps-link:hover{color:" . $attributes['linkHoverColor'] . ';}';
	}

	$fb = '';
	if ( ! empty( $attributes['footerBtnColor'] ) ) {
		$fb .= 'color:' . $attributes['footerBtnColor'] . ';';
	}
	if ( ! empty( $attributes['footerBtnBackground'] ) ) {
		$fb .= 'background:' . $attributes['footerBtnBackground'] . ';';
	}
	if ( '' !== $fb ) {
		$css .= "{$sel} .wpddbp-ps-btn:not(.wpddbp-ps-btn--ghost){{$fb}}";
	}
	$fbh = '';
	if ( ! empty( $attributes['footerBtnHoverColor'] ) ) {
		$fbh .= 'color:' . $attributes['footerBtnHoverColor'] . ';';
	}
	if ( ! empty( $attributes['footerBtnHoverBackground'] ) ) {
		$fbh .= 'background:' . $attributes['footerBtnHoverBackground'] . ';';
	}
	if ( '' !== $fbh ) {
		$css .= "{$sel} .wpddbp-ps-btn:not(.wpddbp-ps-btn--ghost):hover{{$fbh}}";
	}

	return $css;
}
