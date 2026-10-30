<?php
/**
 * Services — dynamic CSS generator.
 *
 * Built from scratch on the shared helper closures (no reference counterpart).
 * Editorial baselines live in style.scss; this emits per-instance overrides +
 * the responsive grid columns (grid layout) across all four layouts.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_services_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
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

	// ---- grid columns (responsive) ----
	if ( 'grid' === ( $attributes['layoutType'] ?? 'grid' ) ) {
		$lg   = (int) ( $attributes['columnsLg'] ?? 3 );
		$mdc  = (int) ( $attributes['columnsMd'] ?? 2 );
		$smc  = (int) ( $attributes['columnsSm'] ?? 1 );
		$css .= "{$sel} .wpddbp-svc-grid{grid-template-columns:repeat({$lg},1fr);}";
		$css .= "@media (max-width:1024px){{$sel} .wpddbp-svc-grid{grid-template-columns:repeat({$mdc},1fr);}}";
		$css .= "@media (max-width:767px){{$sel} .wpddbp-svc-grid{grid-template-columns:repeat({$smc},1fr);}}";
	}

	// ---- text emit (color + typography) ----
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
	$css .= $emit( "{$sel} .wpddbp-svc-eyebrow", $attributes['eyebrowColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-svc-heading", $attributes['headingColor'] ?? '', $attributes['headingTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-svc-aside", $attributes['asideColor'] ?? '', null );
	$css .= $emit( "{$sel} .wpddbp-svc-title, {$sel} .wpddbp-svc-cell h4", $attributes['titleColor'] ?? '', $attributes['titleTypography'] ?? null );
	$css .= $emit( "{$sel} .wpddbp-svc-desc, {$sel} .wpddbp-svc-cell p", $attributes['descriptionColor'] ?? '', $attributes['descriptionTypography'] ?? null );

	// ---- card / row ----
	// Background excludes the flagship card so a global tint doesn't erase its
	// deep-ocean identity; radius + shadow apply to every card.
	if ( ! empty( $attributes['cardBackground'] ) ) {
		$c    = $attributes['cardBackground'];
		$css .= "{$sel} .wpddbp-svc-card:not(.is-flagship){background:{$c};}{$sel} .wpddbp-svc-list{background:{$c};}{$sel} .wpddbp-svc-twocol{background:{$c};}";
	}
	$card_sel = "{$sel} .wpddbp-svc-card, {$sel} .wpddbp-svc-list, {$sel} .wpddbp-svc-twocol";
	$cbase    = '';
	if ( ! empty( $attributes['cardBorderRadius'] ) ) {
		$cbase .= $generate_border_radius_css( $attributes['cardBorderRadius'] );
	}
	if ( ! empty( $attributes['cardBoxShadow'] ) ) {
		$cbase .= $generate_box_shadow_css( $attributes['cardBoxShadow'] );
	}
	if ( '' !== $cbase ) {
		$css .= "{$card_sel}{{$cbase}}";
	}

	// ---- conditional rules ----
	if ( ! empty( $attributes['dividerColor'] ) ) {
		$c    = $attributes['dividerColor'];
		$css .= "{$sel} .wpddbp-svc-item{border-color:{$c};}{$sel} .wpddbp-svc-cell{border-color:{$c};}{$sel} .wpddbp-svc-foot{border-color:{$c};}";
	}
	if ( ! empty( $attributes['iconColor'] ) ) {
		$css .= "{$sel} .wpddbp-svc-ic{color:" . $attributes['iconColor'] . ';}';
	}
	if ( ! empty( $attributes['iconBackground'] ) ) {
		$css .= "{$sel} .wpddbp-svc-ic{background:" . $attributes['iconBackground'] . ';}';
	}
	if ( ! empty( $attributes['tagColor'] ) ) {
		$c    = $attributes['tagColor'];
		$css .= "{$sel} .wpddbp-svc-tag{color:{$c};}{$sel} .wpddbp-svc-content .wpddbp-svc-eyebrow{color:{$c};}";
	}
	if ( ! empty( $attributes['priceColor'] ) ) {
		$c    = $attributes['priceColor'];
		$css .= "{$sel} .wpddbp-svc-price{color:{$c};}{$sel} .wpddbp-svc-price-chip{color:{$c};}";
	}
	if ( ! empty( $attributes['numberColor'] ) ) {
		$css .= "{$sel} .wpddbp-svc-num{color:" . $attributes['numberColor'] . ';}';
	}
	if ( ! empty( $attributes['bulletMarkerColor'] ) ) {
		$css .= "{$sel} .wpddbp-svc-bullets li svg{color:" . $attributes['bulletMarkerColor'] . ';}';
	}
	if ( ! empty( $attributes['linkColor'] ) ) {
		$c    = $attributes['linkColor'];
		$css .= "{$sel} .wpddbp-svc-more{color:{$c};}{$sel} .wpddbp-svc-link{color:{$c};}{$sel} .wpddbp-svc-book{color:{$c};}";
	}
	if ( ! empty( $attributes['linkHoverColor'] ) ) {
		$c    = $attributes['linkHoverColor'];
		$css .= "{$sel} .wpddbp-svc-more:hover{color:{$c};}{$sel} .wpddbp-svc-link:hover{color:{$c};}{$sel} .wpddbp-svc-card:hover .wpddbp-svc-more{color:{$c};}";
	}

	$fb = '';
	if ( ! empty( $attributes['footerBtnColor'] ) ) {
		$fb .= 'color:' . $attributes['footerBtnColor'] . ';';
	}
	if ( ! empty( $attributes['footerBtnBackground'] ) ) {
		$fb .= 'background:' . $attributes['footerBtnBackground'] . ';';
	}
	if ( '' !== $fb ) {
		$css .= "{$sel} .wpddbp-svc-btn{{$fb}}";
	}
	$fbh = '';
	if ( ! empty( $attributes['footerBtnHoverColor'] ) ) {
		$fbh .= 'color:' . $attributes['footerBtnHoverColor'] . ';';
	}
	if ( ! empty( $attributes['footerBtnHoverBackground'] ) ) {
		$fbh .= 'background:' . $attributes['footerBtnHoverBackground'] . ';';
	}
	if ( '' !== $fbh ) {
		$css .= "{$sel} .wpddbp-svc-btn:hover{{$fbh}}";
	}

	return $css;
}
