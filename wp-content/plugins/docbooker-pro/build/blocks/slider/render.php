<?php
/**
 * Slider — server render (3 editorial layouts: hero / cards / split).
 *
 * Emits the markup + a `data-config` JSON blob the frontend view.js feeds to
 * Swiper. Per-instance style overrides come from the bootstrap CSS injector via
 * wpddbp_generate_slider_block_css(); the hero image overlay is set inline as a
 * `--wpddbp-overlay` custom property.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'hero';
if ( ! in_array( $layout, array( 'hero', 'cards', 'split' ), true ) ) {
	$layout = 'hero';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$pos_h = in_array( $attributes['contentPosH'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $attributes['contentPosH'] : 'left';
$pos_v = in_array( $attributes['contentPosV'] ?? 'middle', array( 'top', 'middle', 'bottom' ), true ) ? $attributes['contentPosV'] : 'middle';

$slides = is_array( $attributes['slides'] ?? null ) ? $attributes['slides'] : array();

$d_sub   = $attributes['displaySubtitle'] ?? true;
$d_title = $attributes['displayTitle'] ?? true;
$d_desc  = $attributes['displayDescription'] ?? true;
$d_btn   = $attributes['displayButton'] ?? true;
$d_head  = $attributes['displayHeader'] ?? true;

$show_nav  = (bool) ( $attributes['showNav'] ?? true );
$show_page = (bool) ( $attributes['showPagination'] ?? true );

$cfg = array(
	'layoutType'     => $layout,
	'effect'         => $attributes['effect'] ?? 'fade',
	'speed'          => (int) ( $attributes['speed'] ?? 700 ),
	'loop'           => (bool) ( $attributes['loop'] ?? true ),
	'autoplay'       => (bool) ( $attributes['autoplay'] ?? true ),
	'autoplayDelay'  => (int) ( $attributes['autoplayDelay'] ?? 6000 ),
	'pauseOnHover'   => (bool) ( $attributes['pauseOnHover'] ?? true ),
	'showNav'        => $show_nav,
	'showPagination' => $show_page,
	'paginationType' => $attributes['paginationType'] ?? 'bullets',
	'keyboard'       => (bool) ( $attributes['keyboard'] ?? false ),
	'mousewheel'     => (bool) ( $attributes['mousewheel'] ?? false ),
	'slidesPerView'  => (float) ( $attributes['slidesPerView'] ?? 2 ),
	'spaceBetween'   => (int) ( $attributes['spaceBetween'] ?? 24 ),
);
$cfg_json = wp_json_encode( $cfg );

$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

$build_link = function ( $url, $new_tab, $nofollow ) {
	$href   = $url ? esc_url( $url ) : '#';
	$target = $new_tab ? ' target="_blank"' : '';
	$rel    = array();
	if ( $new_tab ) {
		$rel[] = 'noopener';
		$rel[] = 'noreferrer';
	}
	if ( $nofollow ) {
		$rel[] = 'nofollow';
	}
	$rel_attr = $rel ? ' rel="' . esc_attr( implode( ' ', array_unique( $rel ) ) ) . '"' : '';
	return array( $href, $target, $rel_attr );
};

// shared content fragment (unified element classes)
$content = function ( $slide ) use ( $d_sub, $d_title, $d_desc, $d_btn, $allowed_tags, $build_link ) {
	$sub   = $slide['subtitle'] ?? '';
	$title = $slide['title'] ?? '';
	$tag   = in_array( $slide['titleTag'] ?? 'h3', $allowed_tags, true ) ? $slide['titleTag'] : 'h3';
	$desc  = $slide['description'] ?? '';
	$bt    = $slide['buttonText'] ?? '';
	$out   = '';
	if ( $d_sub && '' !== $sub ) {
		$out .= '<span class="wpddbp-sl-eyebrow">' . esc_html( $sub ) . '</span>';
	}
	if ( $d_title && '' !== $title ) {
		$out .= '<' . $tag . ' class="wpddbp-sl-title">' . esc_html( $title ) . '</' . $tag . '>';
	}
	if ( $d_desc && '' !== $desc ) {
		$out .= '<p class="wpddbp-sl-desc">' . esc_html( $desc ) . '</p>';
	}
	if ( $d_btn && '' !== $bt ) {
		list( $href, $tgt, $rel ) = $build_link( $slide['buttonURL'] ?? '', ! empty( $slide['buttonNewTab'] ), ! empty( $slide['buttonNoFollow'] ) );
		$out .= '<a class="wpddbp-sl-btn" href="' . $href . '"' . $tgt . $rel . '>' . esc_html( $bt ) . ' <span class="wpddbp-sl-arrow" aria-hidden="true">&rarr;</span></a>';
	}
	return $out;
};

// per-layout slides
$slide_html = '';
foreach ( $slides as $slide ) {
	$img = $slide['image']['url'] ?? '';
	$alt = $slide['image']['alt'] ?? '';
	if ( 'hero' === $layout ) {
		$bg = $img ? ' style="background-image:url(\'' . esc_url( $img ) . '\')"' : '';
		$slide_html .= '<div class="swiper-slide"' . $bg . '><div class="wpddbp-sl-hero-content"><div class="wpddbp-sl-hero-inner">' . $content( $slide ) . '</div></div></div>';
	} elseif ( 'cards' === $layout ) {
		$media = $img ? '<img class="wpddbp-sl-media" src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />' : '';
		$slide_html .= '<div class="swiper-slide"><article class="wpddbp-sl-card">' . $media . '<div class="wpddbp-sl-card-body">' . $content( $slide ) . '</div></article></div>';
	} else { // split
		$media = $img ? '<img class="wpddbp-sl-media" src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />' : '';
		$slide_html .= '<div class="swiper-slide"><div class="wpddbp-sl-split-slide">' . $media . $content( $slide ) . '</div></div>';
	}
}

$prev_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>';
$next_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
$nav      = $show_nav ? '<button type="button" class="wpddbp-slider-nav wpddbp-slider-nav--prev" aria-label="' . esc_attr__( 'Previous', 'doc-booker-pro' ) . '">' . $prev_svg . '</button><button type="button" class="wpddbp-slider-nav wpddbp-slider-nav--next" aria-label="' . esc_attr__( 'Next', 'doc-booker-pro' ) . '">' . $next_svg . '</button>' : '';
$dots     = $show_page ? '<div class="wpddbp-slider-dots"></div>' : '';
$total    = count( $slides );
$count    = '<span class="wpddbp-sl-count"><b data-current>1</b> / <span data-total>' . (int) $total . '</span></span>';

// header text (cards / split)
$head_text = '';
if ( $d_head ) {
	$he = $attributes['headerEyebrow'] ?? '';
	$ht = $attributes['headerTitle'] ?? '';
	$hd = $attributes['headerDescription'] ?? '';
	if ( '' !== $he ) {
		$head_text .= '<span class="wpddbp-sl-head-eyebrow">' . esc_html( $he ) . '</span>';
	}
	if ( '' !== $ht ) {
		$head_text .= '<h2 class="wpddbp-sl-head-title">' . esc_html( $ht ) . '</h2>';
	}
	if ( '' !== $hd ) {
		$head_text .= '<p class="wpddbp-sl-head-desc">' . esc_html( $hd ) . '</p>';
	}
}

// assemble inner per layout
if ( 'cards' === $layout ) {
	$head = ( '' !== $head_text || $show_nav )
		? '<div class="wpddbp-sl-head"><div>' . $head_text . '</div>' . ( $show_nav ? '<div class="wpddbp-sl-head-nav">' . $nav . '</div>' : '' ) . '</div>'
		: '';
	$inner = $head . '<div class="swiper"><div class="swiper-wrapper">' . $slide_html . '</div></div>' . ( $dots ? '<div class="wpddbp-sl-foot">' . $dots . '</div>' : '' );
} elseif ( 'split' === $layout ) {
	$aside = '<div class="wpddbp-sl-aside"><div class="wpddbp-sl-aside-head">' . $head_text . '</div><div class="wpddbp-sl-aside-foot">' . $count . ( $show_nav ? '<div class="wpddbp-sl-aside-nav">' . $nav . '</div>' : '' ) . '</div></div>';
	$inner = $aside . '<div class="swiper"><div class="swiper-wrapper">' . $slide_html . '</div>' . $dots . '</div>';
} else { // hero
	$inner = '<div class="swiper"><div class="swiper-wrapper">' . $slide_html . '</div></div>' . $nav . '<div class="wpddbp-sl-foot">' . $count . $dots . '</div>';
}

$data_attrs = '';
if ( 'hero' === $layout ) {
	$data_attrs = ' data-pos="' . esc_attr( $pos_h ) . '" data-v="' . esc_attr( $pos_v ) . '"';
	if ( ! empty( $attributes['overlayColor'] ) ) {
		$data_attrs .= ' style="--wpddbp-overlay:' . esc_attr( $attributes['overlayColor'] ) . '"';
	}
}

printf(
	'<div id="%1$s" class="wpddbp-slider wpddbp-slider--%2$s%3$s" data-config="%4$s"%5$s>%6$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	esc_attr( $align_class ),
	esc_attr( $cfg_json ),
	$data_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr() applied to each value above.
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() + our own SVG markup.
);
