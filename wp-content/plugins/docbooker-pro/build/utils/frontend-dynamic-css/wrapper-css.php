<?php
/**
 * Wrapper — dynamic CSS generator.
 *
 * Duplicated + renamed from the reference blocks plugin (architecture §3 / §9a).
 * Two-level width model: the wrapper stays full-bleed (so bg/padding span the
 * viewport) while each DIRECT CHILD is capped to the chosen width and centered.
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

function wpddbp_generate_wrapper_block_css( $attributes, $unique_id, $generate_spacing_css ) {
	$selector = "#{$unique_id}";
	$base     = '';
	$md       = '';
	$sm       = '';

	if ( ! empty( $attributes['wrapperMargin'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['wrapperMargin'], 'margin' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}
	if ( ! empty( $attributes['wrapperPadding'] ) ) {
		$s     = $generate_spacing_css( $selector, $attributes['wrapperPadding'], 'padding' );
		$base .= $s['base'];
		$md   .= $s['md'];
		$sm   .= $s['sm'];
	}

	// Constrained-content mode (lg-only): cap each direct child + center it, while
	// the wrapper itself stays full-bleed (capping the wrapper would fight .alignfull
	// and lose the full-bleed background).
	$constrained_w = '';
	$constrained_u = 'px';
	if ( ! empty( $attributes['contentWidth'] ) && 'custom' === $attributes['contentWidth'] ) {
		$w = $attributes['innerContentWidth']['lg'] ?? '';
		$u = $attributes['innerContentWidth']['unit'] ?? 'px';
		if ( '' !== $w && null !== $w ) {
			$constrained_w = $w;
			$constrained_u = $u;
		}
	}

	$bg = $attributes['mainWrapBG'] ?? null;
	if ( ! empty( $bg ) && ! empty( $bg['openBGColor'] ) ) {
		if ( ( $bg['type'] ?? 'classic' ) === 'classic' ) {
			if ( ! empty( $bg['classic']['color'] ) ) {
				$base .= 'background-color: ' . $bg['classic']['color'] . ';';
			}
			$img = $bg['classic']['img']['imgURL'] ?? '';
			if ( ! empty( $img ) ) {
				$base .= "background-image: url('" . esc_url_raw( $img ) . "');";
				$prop = $bg['classic']['imgProperty'] ?? array();
				if ( ! empty( $prop['imgPosition']['lg'] ) ) {
					$base .= 'background-position: ' . $prop['imgPosition']['lg'] . ';';
				}
				if ( ! empty( $prop['imgAttachment']['lg'] ) ) {
					$base .= 'background-attachment: ' . $prop['imgAttachment']['lg'] . ';';
				}
				if ( ! empty( $prop['imgRepeat']['lg'] ) ) {
					$base .= 'background-repeat: ' . $prop['imgRepeat']['lg'] . ';';
				}
				if ( ! empty( $prop['imgSize']['lg'] ) ) {
					$base .= 'background-size: ' . $prop['imgSize']['lg'] . ';';
				}
			}
		} elseif ( ( $bg['type'] ?? '' ) === 'gradient' && ! empty( $bg['gradient'] ) ) {
			$base .= 'background: ' . $bg['gradient'] . ';';
		}
	}

	$css = wpddbp_assemble_responsive_block( $selector, $base, $md, $sm );

	if ( '' !== $constrained_w ) {
		$css .= "{$selector} > *{max-width:{$constrained_w}{$constrained_u};margin-left:auto;margin-right:auto;}";
	}

	// customCSS — appended verbatim (per-instance escape hatch for inner layout).
	if ( ! empty( $attributes['customCSS'] ) ) {
		$css .= (string) $attributes['customCSS'];
	}

	return $css;
}
