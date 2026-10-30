<?php
/**
 * FAQ — server render (6 layouts: hairline / carded / split / cat-nav / cat-tabs / masonry).
 *
 * Emits accessible <button aria-expanded> rows inside [data-accordion] groups
 * that the frontend view.js drives (single / multi open). Categorized layouts
 * group items by their `category` field and emit category nav / tabs + panes.
 * Per-instance styling comes from wpddbp_generate_faq_block_css().
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . '../../utils/info-box-icons.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'hairline';
$allowed  = array( 'hairline', 'carded', 'split', 'cat-nav', 'cat-tabs', 'masonry' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'hairline';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$is_cat     = ( 'cat-nav' === $layout || 'cat-tabs' === $layout );
$open_mode  = ( ( $attributes['openMode'] ?? 'single' ) === 'multi' ) ? 'multi' : 'single';
$icon_style = ( ( $attributes['iconStyle'] ?? 'chevron' ) === 'plusminus' ) ? 'plusminus' : 'chevron';
$glyph      = ( 'carded' === $layout || $is_cat ) ? 'plusminus' : $icon_style;
$first_open = $attributes['firstOpen'] ?? true;
$show_icon  = $attributes['showItemIcon'] ?? true;
$show_num   = $attributes['showItemNumbers'] ?? true;

$items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();

$toggle_html = ( 'plusminus' === $glyph )
	? '<span class="wpddbp-faq-toggle wpddbp-faq-toggle--plusminus"><svg class="wpddbp-faq-pm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/><path class="v" d="M12 5v14"/></svg></span>'
	: '<span class="wpddbp-faq-toggle wpddbp-faq-toggle--chevron"><svg class="wpddbp-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></span>';

$paras = function ( $answer ) {
	$parts = preg_split( '/\n\s*\n/', (string) $answer );
	$out   = '';
	foreach ( $parts as $p ) {
		$p = trim( $p );
		if ( '' !== $p ) {
			$out .= '<p>' . esc_html( $p ) . '</p>';
		}
	}
	return $out;
};

$acc_item = function ( $item, $idx, $uid_base, $opts ) use ( $toggle_html, $paras, $first_open, $show_num, $show_icon ) {
	$tid     = $uid_base . '-t' . $idx;
	$pid     = $uid_base . '-p' . $idx;
	$is_open = ( $first_open && 0 === $idx );
	$question = esc_html( $item['question'] ?? '' );

	$num  = ( ! empty( $opts['showNum'] ) && $show_num ) ? '<span class="wpddbp-faq-q-num">' . esc_html( str_pad( (string) ( $idx + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>' : '';
	$icon = ( ! empty( $opts['showIcon'] ) && $show_icon && ! empty( $item['icon'] ) ) ? '<span class="wpddbp-faq-q-icon">' . wpddbp_get_info_box_icon_svg( $item['icon'] ) . '</span>' : '';

	if ( ! empty( $opts['signLeft'] ) ) {
		$q_inner = $toggle_html . '<span class="wpddbp-faq-q-text">' . $question . '</span>';
	} else {
		$q_inner = $num . $icon . '<span class="wpddbp-faq-q-text">' . $question . '</span>' . $toggle_html;
	}
	$panel_style = $is_open ? ' style="max-height:none"' : '';

	return '<div class="wpddbp-faq-item' . ( $is_open ? ' is-open' : '' ) . '">'
		. '<button class="wpddbp-faq-q" type="button" id="' . esc_attr( $tid ) . '" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $pid ) . '">' . $q_inner . '</button>'
		. '<div class="wpddbp-faq-panel" id="' . esc_attr( $pid ) . '" role="region" aria-labelledby="' . esc_attr( $tid ) . '"' . $panel_style . '><div class="wpddbp-faq-panel-inner">' . $paras( $item['answer'] ?? '' ) . '</div></div>'
		. '</div>';
};

$derive_cats = function ( $items ) {
	$map = array();
	foreach ( $items as $item ) {
		$cat = trim( (string) ( $item['category'] ?? '' ) );
		if ( '' === $cat ) {
			$cat = 'General';
		}
		if ( ! isset( $map[ $cat ] ) ) {
			$map[ $cat ] = array( 'label' => $cat, 'icon' => $item['icon'] ?? 'help-circle', 'items' => array() );
		}
		$map[ $cat ]['items'][] = $item;
	}
	return array_values( $map );
};

/* ---- header (hairline / carded / masonry) ---- */
$header = '';
if ( in_array( $layout, array( 'hairline', 'carded', 'masonry' ), true ) ) {
	$htag = in_array( $attributes['headingTag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $attributes['headingTag'] : 'h2';
	if ( ( $attributes['displayEyebrow'] ?? true ) && '' !== ( $attributes['eyebrowText'] ?? '' ) ) {
		$header .= '<span class="wpddbp-faq-eyebrow">' . esc_html( $attributes['eyebrowText'] ) . '</span>';
	}
	if ( ( $attributes['displayHeading'] ?? true ) && '' !== ( $attributes['headingText'] ?? '' ) ) {
		$header .= '<' . $htag . ' class="wpddbp-faq-heading">' . esc_html( $attributes['headingText'] ) . '</' . $htag . '>';
	}
	if ( ( $attributes['displaySubheading'] ?? false ) && '' !== ( $attributes['subheadingText'] ?? '' ) ) {
		$header .= '<p class="wpddbp-faq-subheading">' . esc_html( $attributes['subheadingText'] ) . '</p>';
	}
	if ( '' !== $header ) {
		$header = '<div class="wpddbp-faq-head">' . $header . '</div>';
	}
}

/* ---- assemble inner per layout ---- */
if ( 'hairline' === $layout ) {
	$rows = '';
	foreach ( $items as $i => $it ) {
		$rows .= $acc_item( $it, $i, $block_id, array( 'showNum' => true ) );
	}
	$inner = $header . '<div class="wpddbp-faq-list" data-accordion data-mode="' . esc_attr( $open_mode ) . '">' . $rows . '</div>';
} elseif ( 'carded' === $layout ) {
	$rows = '';
	foreach ( $items as $i => $it ) {
		$rows .= $acc_item( $it, $i, $block_id, array( 'showIcon' => true ) );
	}
	$inner = $header . '<div class="wpddbp-faq-cards" data-accordion data-mode="' . esc_attr( $open_mode ) . '">' . $rows . '</div>';
} elseif ( 'split' === $layout ) {
	$intro = '';
	if ( $attributes['displayIntro'] ?? true ) {
		$intro = '<aside class="wpddbp-faq-intro">';
		if ( '' !== ( $attributes['introKicker'] ?? '' ) ) {
			$intro .= '<span class="wpddbp-faq-intro-kicker">' . esc_html( $attributes['introKicker'] ) . '</span>';
		}
		if ( '' !== ( $attributes['introTitle'] ?? '' ) ) {
			$intro .= '<h2 class="wpddbp-faq-intro-title">' . esc_html( $attributes['introTitle'] ) . '</h2>';
		}
		if ( '' !== ( $attributes['introText'] ?? '' ) ) {
			$intro .= '<p class="wpddbp-faq-intro-text">' . esc_html( $attributes['introText'] ) . '</p>';
		}
		if ( $attributes['displayHelp'] ?? true ) {
			$phone_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>';
			$pin_svg   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.6-9.2-9C1.4 8.6 4 4 8 5c2 .5 3.2 2 4 3 .8-1 2-2.5 4-3 4-1 6.6 3.6 5.2 7-2.2 4.4-9.2 9-9.2 9z"/></svg>';
			$intro    .= '<div class="wpddbp-faq-help">';
			if ( '' !== ( $attributes['helpPhone'] ?? '' ) ) {
				$intro .= '<div class="wpddbp-faq-help-row">' . $phone_svg . '<span><strong>' . esc_html( $attributes['helpPhone'] ) . '</strong><span class="sub">' . esc_html( $attributes['helpPhoneNote'] ?? '' ) . '</span></span></div>';
			}
			if ( '' !== ( $attributes['helpMessageTitle'] ?? '' ) ) {
				$intro .= '<div class="wpddbp-faq-help-row">' . $pin_svg . '<span><strong>' . esc_html( $attributes['helpMessageTitle'] ) . '</strong><span class="sub">' . esc_html( $attributes['helpMessageNote'] ?? '' ) . '</span></span></div>';
			}
			if ( '' !== ( $attributes['helpButtonText'] ?? '' ) ) {
				$href   = ! empty( $attributes['helpButtonURL'] ) ? esc_url( $attributes['helpButtonURL'] ) : '#';
				$target = ! empty( $attributes['helpButtonNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
				$intro .= '<a class="wpddbp-faq-help-btn" href="' . $href . '"' . $target . '>' . esc_html( $attributes['helpButtonText'] ) . ' <span class="wpddbp-faq-arrow" aria-hidden="true">&rarr;</span></a>';
			}
			$intro .= '</div>';
		}
		$intro .= '</aside>';
	}
	$rows = '';
	foreach ( $items as $i => $it ) {
		$rows .= $acc_item( $it, $i, $block_id, array() );
	}
	$inner = $intro . '<div class="wpddbp-faq-acc"><div class="wpddbp-faq-list" data-accordion data-mode="' . esc_attr( $open_mode ) . '">' . $rows . '</div></div>';
} elseif ( 'masonry' === $layout ) {
	$cards = '';
	foreach ( $items as $it ) {
		$tag = '';
		if ( '' !== ( $it['category'] ?? '' ) ) {
			$tag = '<span class="wpddbp-faq-qa-tag">' . ( ! empty( $it['icon'] ) ? wpddbp_get_info_box_icon_svg( $it['icon'] ) : '' ) . esc_html( $it['category'] ) . '</span>';
		}
		$cards .= '<article class="wpddbp-faq-qa-card">' . $tag . '<h4>' . esc_html( $it['question'] ?? '' ) . '</h4>' . $paras( $it['answer'] ?? '' ) . '</article>';
	}
	if ( $attributes['showCtaCard'] ?? true ) {
		$cards .= '<article class="wpddbp-faq-qa-card wpddbp-faq-qa-card--cta">';
		if ( '' !== ( $attributes['ctaTag'] ?? '' ) ) {
			$cards .= '<span class="wpddbp-faq-qa-tag">' . esc_html( $attributes['ctaTag'] ) . '</span>';
		}
		if ( '' !== ( $attributes['ctaTitle'] ?? '' ) ) {
			$cards .= '<h4>' . esc_html( $attributes['ctaTitle'] ) . '</h4>';
		}
		if ( '' !== ( $attributes['ctaText'] ?? '' ) ) {
			$cards .= '<p>' . esc_html( $attributes['ctaText'] ) . '</p>';
		}
		if ( '' !== ( $attributes['ctaLinkText'] ?? '' ) ) {
			$cards .= '<a class="wpddbp-faq-ask-link" href="' . ( ! empty( $attributes['ctaLinkURL'] ) ? esc_url( $attributes['ctaLinkURL'] ) : '#' ) . '">' . esc_html( $attributes['ctaLinkText'] ) . ' <span class="wpddbp-faq-arrow" aria-hidden="true">&rarr;</span></a>';
		}
		$cards .= '</article>';
	}
	$inner = $header . '<div class="wpddbp-faq-masonry">' . $cards . '</div>';
} else {
	// cat-nav / cat-tabs
	$cats  = $derive_cats( $items );
	$panes = '<div class="wpddbp-faq-panes">';
	foreach ( $cats as $ci => $cat ) {
		$uid_base = $block_id . '-c' . $ci;
		$count    = count( $cat['items'] );
		$panes   .= '<div class="wpddbp-faq-pane' . ( 0 === $ci ? ' is-active' : '' ) . '" data-faq-pane="' . (int) $ci . '">';
		$panes   .= '<div class="wpddbp-faq-pane-head"><h4>' . esc_html( $cat['label'] ) . '</h4><span class="wpddbp-faq-pane-sub">' . (int) $count . ' ' . ( 1 === $count ? esc_html__( 'question', 'doc-booker-pro' ) : esc_html__( 'questions', 'doc-booker-pro' ) ) . '</span></div>';
		$panes   .= '<div class="wpddbp-faq-group" data-accordion data-mode="' . esc_attr( $open_mode ) . '">';
		foreach ( $cat['items'] as $ii => $it ) {
			$panes .= $acc_item( $it, $ii, $uid_base, array( 'signLeft' => true ) );
		}
		$panes .= '</div></div>';
	}
	$panes .= '</div>';

	if ( 'cat-nav' === $layout ) {
		$nav = '<div class="wpddbp-faq-nav">';
		if ( '' !== ( $attributes['navTitle'] ?? '' ) ) {
			$nav .= '<h3 class="wpddbp-faq-nav-title">' . esc_html( $attributes['navTitle'] ) . '</h3>';
		}
		$nav .= '<div class="wpddbp-faq-cat-list" role="tablist">';
		foreach ( $cats as $ci => $cat ) {
			$nav .= '<button class="wpddbp-faq-cat-btn" type="button" role="tab" aria-selected="' . ( 0 === $ci ? 'true' : 'false' ) . '" data-faq-cat="' . (int) $ci . '">';
			if ( $show_icon ) {
				$nav .= '<span class="wpddbp-faq-cat-ico">' . wpddbp_get_info_box_icon_svg( $cat['icon'] ) . '</span>';
			}
			$nav .= '<span class="wpddbp-faq-cat-text">' . esc_html( $cat['label'] ) . '</span><span class="wpddbp-faq-q-count">' . (int) count( $cat['items'] ) . '</span></button>';
		}
		$nav  .= '</div></div>';
		$inner = $nav . $panes;
	} else {
		$tabs = '<div class="wpddbp-faq-tabs" role="tablist">';
		foreach ( $cats as $ci => $cat ) {
			$tabs .= '<button class="wpddbp-faq-tab" type="button" role="tab" aria-selected="' . ( 0 === $ci ? 'true' : 'false' ) . '" data-faq-cat="' . (int) $ci . '">';
			if ( $show_icon ) {
				$tabs .= '<span class="wpddbp-faq-tab-ico">' . wpddbp_get_info_box_icon_svg( $cat['icon'] ) . '</span>';
			}
			$tabs .= esc_html( $cat['label'] ) . ' <span class="wpddbp-faq-q-count">' . (int) count( $cat['items'] ) . '</span></button>';
		}
		$tabs .= '</div>';
		$inner = $tabs . $panes;
	}
}

printf(
	'<div id="%1$s" class="wpddbp-faq wpddbp-faq--%2$s%3$s">%4$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	esc_attr( $align_class ),
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() + our own inline SVG markup.
);
