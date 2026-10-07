<?php
/**
 * Services — server render (4 layouts: grid [3 card styles] / alternating / numbered / two-column).
 *
 * Author-managed repeater (not a CPT). No frontend JS — every interaction is CSS
 * hover / :focus-within. Per-instance styling + grid columns come from
 * wpddbp_generate_services_block_css().
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . '../../utils/info-box-icons.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'grid';
if ( ! in_array( $layout, array( 'grid', 'alternating', 'numbered', 'two-column' ), true ) ) {
	$layout = 'grid';
}
$card_style = in_array( $attributes['cardStyle'] ?? 'hairline', array( 'hairline', 'filled', 'badge' ), true ) ? $attributes['cardStyle'] : 'hairline';

$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();

$d_icon = $attributes['showIcon'] ?? true;
$d_tag  = $attributes['showTag'] ?? true;
$d_desc = $attributes['showDescription'] ?? true;
$d_bul  = $attributes['showBullets'] ?? true;
$d_price = $attributes['showPrice'] ?? true;
$d_link = $attributes['showLink'] ?? true;
$d_num  = $attributes['showNumbers'] ?? true;
$d_img  = $attributes['showImage'] ?? true;

$arrow = '<span class="wpddbp-svc-arrow" aria-hidden="true">&rarr;</span>';
$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
$go    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$cam   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.2"/><path d="M8 5l1.5-2h5L16 5"/></svg>';

$link_attr = function ( $url, $new_tab ) {
	$href   = $url ? esc_url( $url ) : '#';
	$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
	return 'href="' . $href . '"' . $target;
};
$icon_html = function ( $name, $class ) use ( $d_icon ) {
	if ( ! $d_icon || empty( $name ) ) {
		return '';
	}
	return '<span class="' . esc_attr( $class ) . '">' . wpddbp_get_info_box_icon_svg( $name ) . '</span>';
};
$more_html = function ( $it ) use ( $d_link, $arrow ) {
	if ( ! $d_link || '' === ( $it['linkText'] ?? '' ) ) {
		return '';
	}
	return '<span class="wpddbp-svc-more">' . esc_html( $it['linkText'] ) . ' ' . $arrow . '</span>';
};

/* ---- section header ---- */
$header = '';
if ( ( $attributes['displayHeader'] ?? true ) && in_array( $layout, array( 'grid', 'alternating', 'numbered' ), true ) ) {
	$htag = in_array( $attributes['headingTag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $attributes['headingTag'] : 'h2';
	$inner_h = '';
	if ( '' !== ( $attributes['eyebrowText'] ?? '' ) ) {
		$inner_h .= '<p class="wpddbp-svc-eyebrow">' . esc_html( $attributes['eyebrowText'] ) . '</p>';
	}
	if ( '' !== ( $attributes['headingText'] ?? '' ) ) {
		$inner_h .= '<' . $htag . ' class="wpddbp-svc-heading">' . esc_html( $attributes['headingText'] ) . '</' . $htag . '>';
	}
	$aside = ( '' !== ( $attributes['asideText'] ?? '' ) ) ? '<p class="wpddbp-svc-aside">' . esc_html( $attributes['asideText'] ) . '</p>' : '';
	if ( '' !== $inner_h || '' !== $aside ) {
		$header = '<div class="wpddbp-svc-head"><div>' . $inner_h . '</div>' . $aside . '</div>';
	}
}

/* ---- assemble inner ---- */
if ( 'grid' === $layout ) {
	$cards = '';
	foreach ( $items as $i => $it ) {
		$la    = $link_attr( $it['linkURL'] ?? '', ! empty( $it['linkNewTab'] ) );
		$title = ( '' !== ( $it['title'] ?? '' ) ) ? '<h3 class="wpddbp-svc-title">' . esc_html( $it['title'] ) . '</h3>' : '';
		$desc  = ( $d_desc && '' !== ( $it['description'] ?? '' ) ) ? '<p class="wpddbp-svc-desc">' . esc_html( $it['description'] ) . '</p>' : '';
		$tag   = ( $d_tag && '' !== ( $it['tag'] ?? '' ) ) ? '<span class="wpddbp-svc-tag">' . esc_html( $it['tag'] ) . '</span>' : '';

		if ( 'badge' === $card_style ) {
			$med  = ( $d_icon && ! empty( $it['icon'] ) ) ? '<span class="wpddbp-svc-medallion t-' . ( $i % 4 ) . '">' . wpddbp_get_info_box_icon_svg( $it['icon'] ) . '</span>' : '';
			$book = ( $d_link && '' !== ( $it['linkText'] ?? '' ) ) ? '<span class="wpddbp-svc-reveal"><span class="wpddbp-svc-book">' . esc_html( $it['linkText'] ) . ' ' . $arrow . '</span></span>' : '';
			$cards .= '<a class="wpddbp-svc-card" ' . $la . '>' . $med . $title . $desc . $book . '</a>';
		} elseif ( 'filled' === $card_style ) {
			$flag  = ! empty( $it['flagship'] ) ? '<span class="wpddbp-svc-flag">Flagship</span>' : '';
			$price = ( $d_price && '' !== ( $it['price'] ?? '' ) ) ? '<span class="wpddbp-svc-price">' . esc_html( $it['price'] ) . ( '' !== ( $it['priceSuffix'] ?? '' ) ? ' <small>' . esc_html( $it['priceSuffix'] ) . '</small>' : '' ) . '</span>' : '<span></span>';
			$more  = $more_html( $it );
			$foot  = ( '' !== $more || ( $d_price && '' !== ( $it['price'] ?? '' ) ) ) ? '<div class="wpddbp-svc-foot">' . $price . $more . '</div>' : '';
			$cls   = 'wpddbp-svc-card' . ( ! empty( $it['flagship'] ) ? ' is-flagship' : '' );
			$cards .= '<a class="' . $cls . '" ' . $la . '>' . $flag . $icon_html( $it['icon'] ?? '', 'wpddbp-svc-ic' ) . $tag . $title . $desc . $foot . '</a>';
		} else {
			$top   = '<div class="wpddbp-svc-top">' . $icon_html( $it['icon'] ?? '', 'wpddbp-svc-ic' ) . $tag . '</div>';
			$cards .= '<a class="wpddbp-svc-card" ' . $la . '>' . $top . $title . $desc . $more_html( $it ) . '</a>';
		}
	}
	$inner = $header . '<div class="wpddbp-svc-grid is-' . esc_attr( $card_style ) . '">' . $cards . '</div>';
} elseif ( 'alternating' === $layout ) {
	$rows = '';
	foreach ( $items as $i => $it ) {
		$media = '';
		if ( $d_img ) {
			$tint   = array( '', 'alt', 'tri' )[ $i % 3 ];
			$img    = ( '' !== ( $it['image']['url'] ?? '' ) ) ? '<img src="' . esc_url( $it['image']['url'] ) . '" alt="' . esc_attr( $it['image']['alt'] ?? '' ) . '" loading="lazy" />' : '';
			$style  = ( '' !== ( $it['image']['url'] ?? '' ) ) ? ' style="background:none"' : '';
			$captxt = ( '' !== ( $it['image']['alt'] ?? '' ) ) ? $it['image']['alt'] : ( $it['title'] ?? '' );
			$chip   = '<span class="wpddbp-svc-photo-chip">' . $cam . ' ' . esc_html( $captxt ) . '</span>';
			$media  = '<div class="wpddbp-svc-media"><div class="wpddbp-svc-photo ' . esc_attr( $tint ) . '"' . $style . '>' . $img . $chip . '</div></div>';
		}
		$rowhead = '<div class="wpddbp-svc-rowhead">' . $icon_html( $it['icon'] ?? '', 'wpddbp-svc-ic' ) . ( ( $d_tag && '' !== ( $it['tag'] ?? '' ) ) ? '<span class="wpddbp-svc-eyebrow">' . esc_html( $it['tag'] ) . '</span>' : '' ) . '</div>';
		$title   = ( '' !== ( $it['title'] ?? '' ) ) ? '<h3 class="wpddbp-svc-title">' . esc_html( $it['title'] ) . '</h3>' : '';
		$desc    = ( $d_desc && '' !== ( $it['description'] ?? '' ) ) ? '<p class="wpddbp-svc-desc">' . esc_html( $it['description'] ) . '</p>' : '';
		$bullets = '';
		if ( $d_bul && '' !== ( $it['bullets'] ?? '' ) ) {
			$parts = array_filter( array_map( 'trim', preg_split( '/\n+/', $it['bullets'] ) ) );
			if ( $parts ) {
				$bullets = '<ul class="wpddbp-svc-bullets">';
				foreach ( $parts as $b ) {
					$bullets .= '<li>' . $check . ' ' . esc_html( $b ) . '</li>';
				}
				$bullets .= '</ul>';
			}
		}
		$meta = '<div class="wpddbp-svc-meta">';
		if ( $d_price && '' !== ( $it['price'] ?? '' ) ) {
			$meta .= '<span class="wpddbp-svc-price-chip">From <b>' . esc_html( $it['price'] ) . '</b></span>';
		}
		if ( $d_link && '' !== ( $it['linkText'] ?? '' ) ) {
			$meta .= '<a class="wpddbp-svc-link" ' . $link_attr( $it['linkURL'] ?? '', ! empty( $it['linkNewTab'] ) ) . '>' . esc_html( $it['linkText'] ) . ' ' . $arrow . '</a>';
		}
		$meta .= '</div>';
		$rows .= '<article class="wpddbp-svc-row">' . $media . '<div class="wpddbp-svc-content">' . $rowhead . $title . $desc . $bullets . $meta . '</div></article>';
	}
	$inner = $header . '<div class="wpddbp-svc-rows">' . $rows . '</div>';
} elseif ( 'numbered' === $layout ) {
	$rows = '';
	foreach ( $items as $i => $it ) {
		$num   = '<span class="wpddbp-svc-num">' . ( $d_num ? esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) : '' ) . '</span>';
		$ic    = $d_icon ? '<span class="wpddbp-svc-ic">' . wpddbp_get_info_box_icon_svg( $it['icon'] ?? '' ) . '</span>' : '<span aria-hidden="true"></span>';
		$title = ( '' !== ( $it['title'] ?? '' ) ) ? '<h3 class="wpddbp-svc-title">' . esc_html( $it['title'] ) . '</h3>' : '';
		$desc  = ( $d_desc && '' !== ( $it['description'] ?? '' ) ) ? '<p class="wpddbp-svc-desc">' . esc_html( $it['description'] ) . '</p>' : '';
		$goel  = $d_link ? '<span class="wpddbp-svc-go">' . $go . '</span>' : '';
		$rows .= '<a class="wpddbp-svc-item" ' . $link_attr( $it['linkURL'] ?? '', ! empty( $it['linkNewTab'] ) ) . '>' . $num . $ic . '<span class="wpddbp-svc-text">' . $title . $desc . '</span>' . $goel . '</a>';
	}
	$inner = $header . '<div class="wpddbp-svc-list">' . $rows . '</div>';
} else {
	// two-column
	$cells = '';
	foreach ( $items as $it ) {
		$title = ( '' !== ( $it['title'] ?? '' ) ) ? '<h4>' . esc_html( $it['title'] ) . '</h4>' : '';
		$desc  = ( $d_desc && '' !== ( $it['description'] ?? '' ) ) ? '<p>' . esc_html( $it['description'] ) . '</p>' : '';
		$cells .= '<div class="wpddbp-svc-cell">' . $icon_html( $it['icon'] ?? '', 'wpddbp-svc-ic' ) . '<div>' . $title . $desc . '</div></div>';
	}
	$head_h = '<div class="wpddbp-svc-tc-head"><h3>' . esc_html( $attributes['headingText'] ?? 'What we treat.' ) . '</h3>' . ( '' !== ( $attributes['asideText'] ?? '' ) ? '<p>' . esc_html( $attributes['asideText'] ) . '</p>' : '' ) . '</div>';
	$foot   = '';
	if ( ( $attributes['displayFooter'] ?? true ) && ( '' !== ( $attributes['footerNote'] ?? '' ) || '' !== ( $attributes['footerButtonText'] ?? '' ) ) ) {
		$note = ( '' !== ( $attributes['footerNote'] ?? '' ) ) ? '<span class="wpddbp-svc-note">' . esc_html( $attributes['footerNote'] ) . '</span>' : '';
		$btn  = ( '' !== ( $attributes['footerButtonText'] ?? '' ) ) ? '<a class="wpddbp-svc-btn" ' . $link_attr( $attributes['footerButtonURL'] ?? '', ! empty( $attributes['footerButtonNewTab'] ) ) . '>' . esc_html( $attributes['footerButtonText'] ) . ' ' . $arrow . '</a>' : '';
		$foot = '<div class="wpddbp-svc-tc-foot">' . $note . $btn . '</div>';
	}
	$inner = '<div class="wpddbp-svc-twocol">' . $head_h . '<div class="wpddbp-svc-tc-grid">' . $cells . '</div>' . $foot . '</div>';
}

printf(
	'<div id="%1$s" class="wpddbp-svc wpddbp-svc--%2$s%3$s">%4$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	esc_attr( $align_class ),
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() + our own inline SVG markup.
);
