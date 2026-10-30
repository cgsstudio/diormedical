<?php
/**
 * Doctor Fee Badge block — dynamic CSS generator (Phase 3).
 *
 * Per-instance overrides on top of style.scss. The starburst gradient colours
 * are render-time (inline SVG stops), so the generator only handles the pill +
 * flat colours and the shared amount typography. Mirrored by edit.js previewCss.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

function wpddbp_generate_fee_badge_block_css( $attributes, $unique_id, $generate_typography_css, $generate_spacing_css, $generate_color_css, $generate_border_radius_css, $generate_box_shadow_css ) {
	$sel = "#{$unique_id}";
	$css = '';

	// ---- pill colours ----
	$pill = '';
	$pill .= $generate_color_css( $attributes['pillBg'] ?? '', 'background' );
	$pill .= $generate_color_css( $attributes['pillColor'] ?? '', 'color' );
	if ( '' !== $pill ) {
		$css .= "{$sel} .wpddbp-feebadge--pill{{$pill}}";
	}

	// ---- flat colours ----
	if ( ! empty( $attributes['flatLabelColor'] ) ) {
		$css .= "{$sel} .wpddbp-feebadge--flat .wpddbp-feebadge-from{color:" . $attributes['flatLabelColor'] . ';}';
	}
	if ( ! empty( $attributes['flatAmountColor'] ) ) {
		$css .= "{$sel} .wpddbp-feebadge--flat .wpddbp-feebadge-amt{color:" . $attributes['flatAmountColor'] . ';}';
		$css .= "{$sel} .wpddbp-feebadge--flat:hover .wpddbp-feebadge-amt{color:" . $attributes['flatAmountColor'] . ';}';
	}

	// ---- shared amount typography ----
	if ( ! empty( $attributes['amountTypography'] ) ) {
		$t    = $generate_typography_css( "{$sel} .wpddbp-feebadge-amt", $attributes['amountTypography'] );
		$css .= wpddbp_assemble_responsive_block( "{$sel} .wpddbp-feebadge-amt", $t['base'], $t['md'], $t['sm'] );
	}

	return $css;
}
