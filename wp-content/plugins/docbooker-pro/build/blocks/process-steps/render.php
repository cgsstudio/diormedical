<?php
/**
 * Process Steps — server render (9 layouts).
 *
 * vertical: left-rail / zigzag / stacked · timeline: node-line / icon-disc /
 * segmented · cards: big-numeral / filled / icon-row. No frontend JS — hover +
 * the segmented shimmer are CSS, and is-done / is-current are set here from the
 * `currentStep` attribute. Per-instance styling + grid columns come from
 * wpddbp_generate_process_steps_block_css().
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . '../../utils/info-box-icons.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'left-rail';
$allowed  = array( 'left-rail', 'zigzag', 'stacked', 'node-line', 'icon-disc', 'segmented', 'big-numeral', 'filled', 'icon-row' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'left-rail';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$steps   = is_array( $attributes['steps'] ?? null ) ? $attributes['steps'] : array();
$num_st  = in_array( $attributes['numberingStyle'] ?? 'padded', array( 'numeric', 'padded', 'roman', 'none' ), true ) ? $attributes['numberingStyle'] : 'padded';
$current = (int) ( $attributes['currentStep'] ?? 0 );

$d_icon = $attributes['showIcon'] ?? true;
$d_num  = $attributes['showNumber'] ?? true;
$d_kick = $attributes['showKicker'] ?? true;
$d_desc = $attributes['showDescription'] ?? true;
$d_det  = $attributes['showDetail'] ?? true;
$d_link = $attributes['showLink'] ?? true;

$roman = array( '', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII' );
$num_for = function ( $i ) use ( $num_st, $roman ) {
	$n = $i + 1;
	if ( 'none' === $num_st ) {
		return '';
	}
	if ( 'padded' === $num_st ) {
		return $n < 10 ? '0' . $n : (string) $n;
	}
	if ( 'roman' === $num_st ) {
		return isset( $roman[ $n ] ) ? $roman[ $n ] : (string) $n;
	}
	return (string) $n;
};
$status_of = function ( $i ) use ( $current ) {
	if ( $current <= 0 ) {
		return '';
	}
	if ( $i + 1 < $current ) {
		return ' is-done';
	}
	return ( $i + 1 === $current ) ? ' is-current' : '';
};
$arrow = '<span class="wpddbp-ps-arrow" aria-hidden="true">&rarr;</span>';
$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4 4 10-10"/></svg>';

$icon_svg = function ( $s ) {
	return wpddbp_get_info_box_icon_svg( $s['icon'] ?? '' );
};
$node_inner = function ( $s, $i ) use ( $d_icon, $d_num, $num_for, $icon_svg ) {
	if ( $d_icon && ! empty( $s['icon'] ) ) {
		return '<span class="wpddbp-ps-icon">' . $icon_svg( $s ) . '</span>';
	}
	return $d_num ? esc_html( $num_for( $i ) ) : '';
};
$kicker_el = function ( $s ) use ( $d_kick ) {
	return ( $d_kick && '' !== ( $s['kicker'] ?? '' ) ) ? '<span class="wpddbp-ps-kicker">' . esc_html( $s['kicker'] ) . '</span>' : '';
};
$title_el = function ( $s ) {
	return ( '' !== ( $s['title'] ?? '' ) ) ? '<h3 class="wpddbp-ps-title">' . esc_html( $s['title'] ) . '</h3>' : '';
};
$desc_el = function ( $s ) use ( $d_desc ) {
	return ( $d_desc && '' !== ( $s['description'] ?? '' ) ) ? '<p class="wpddbp-ps-desc">' . esc_html( $s['description'] ) . '</p>' : '';
};
$link_attr = function ( $s ) {
	$href   = ! empty( $s['linkURL'] ) ? esc_url( $s['linkURL'] ) : '#';
	$target = ! empty( $s['linkNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	return 'href="' . $href . '"' . $target;
};
$row_el = function ( $s ) use ( $d_det, $d_link, $arrow, $link_attr ) {
	$det = ( $d_det && '' !== ( $s['detail'] ?? '' ) ) ? '<span class="wpddbp-ps-detail"><span class="dot"></span>' . esc_html( $s['detail'] ) . '</span>' : '';
	$lnk = ( $d_link && '' !== ( $s['linkText'] ?? '' ) ) ? '<a class="wpddbp-ps-link" ' . $link_attr( $s ) . '>' . esc_html( $s['linkText'] ) . ' ' . $arrow . '</a>' : '';
	return ( '' !== $det || '' !== $lnk ) ? '<div class="wpddbp-ps-row">' . $det . $lnk . '</div>' : '';
};

/* ---- header ---- */
$header = '';
if ( $attributes['displayHeader'] ?? true ) {
	$htag = in_array( $attributes['headingTag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $attributes['headingTag'] : 'h2';
	if ( '' !== ( $attributes['eyebrowText'] ?? '' ) ) {
		$header .= '<span class="wpddbp-ps-eyebrow">' . esc_html( $attributes['eyebrowText'] ) . '</span>';
	}
	if ( '' !== ( $attributes['headingText'] ?? '' ) ) {
		$header .= '<' . $htag . ' class="wpddbp-ps-heading">' . esc_html( $attributes['headingText'] ) . '</' . $htag . '>';
	}
	if ( '' !== ( $attributes['subheadingText'] ?? '' ) ) {
		$header .= '<p class="wpddbp-ps-subheading">' . esc_html( $attributes['subheadingText'] ) . '</p>';
	}
	if ( '' !== $header ) {
		$header = '<div class="wpddbp-ps-head">' . $header . '</div>';
	}
}

/* ---- footer ---- */
$footer = '';
if ( ( $attributes['displayFooter'] ?? true ) && 'icon-row' !== $layout && 'segmented' !== $layout ) {
	$b1 = ( '' !== ( $attributes['footerButtonText'] ?? '' ) ) ? '<a class="wpddbp-ps-btn" ' . $link_attr( array( 'linkURL' => $attributes['footerButtonURL'] ?? '', 'linkNewTab' => $attributes['footerButtonNewTab'] ?? false ) ) . '>' . esc_html( $attributes['footerButtonText'] ) . ' ' . $arrow . '</a>' : '';
	$b2 = ( '' !== ( $attributes['footerSecondaryText'] ?? '' ) ) ? '<a class="wpddbp-ps-btn wpddbp-ps-btn--ghost" ' . $link_attr( array( 'linkURL' => $attributes['footerSecondaryURL'] ?? '' ) ) . '>' . esc_html( $attributes['footerSecondaryText'] ) . '</a>' : '';
	if ( '' !== $b1 || '' !== $b2 ) {
		$footer = '<div class="wpddbp-ps-foot">' . $b1 . $b2 . '</div>';
	}
}

/* ---- assemble per layout ---- */
if ( 'left-rail' === $layout || 'zigzag' === $layout ) {
	$rows = '';
	foreach ( $steps as $i => $s ) {
		$badge = ( 'zigzag' === $layout && $d_num ) ? '<span class="wpddbp-ps-num">' . esc_html( $num_for( $i ) ) . '</span>' : '';
		$rows .= '<li class="wpddbp-ps-step"><span class="wpddbp-ps-node" aria-hidden="true">' . $node_inner( $s, $i ) . $badge . '</span><div class="wpddbp-ps-card">' . $kicker_el( $s ) . $title_el( $s ) . $desc_el( $s ) . $row_el( $s ) . '</div></li>';
	}
	$inner = $header . '<ol class="wpddbp-ps-list">' . $rows . '</ol>' . $footer;
} elseif ( 'stacked' === $layout ) {
	$rows = '';
	foreach ( $steps as $i => $s ) {
		$nw    = '<div class="wpddbp-ps-numwrap">' . ( $d_num ? '<span class="wpddbp-ps-bignum" aria-hidden="true">' . esc_html( $num_for( $i ) ) . '</span>' : '' ) . ( ( $d_icon && ! empty( $s['icon'] ) ) ? '<span class="wpddbp-ps-sicon" aria-hidden="true">' . $icon_svg( $s ) . '</span>' : '' ) . '</div>';
		$rows .= '<li class="wpddbp-ps-scard">' . $nw . '<div class="wpddbp-ps-sbody">' . $kicker_el( $s ) . $title_el( $s ) . $desc_el( $s ) . $row_el( $s ) . '</div></li>';
	}
	$inner = $header . '<ol class="wpddbp-ps-list">' . $rows . '</ol>' . $footer;
} elseif ( 'node-line' === $layout ) {
	$rows = '';
	foreach ( $steps as $i => $s ) {
		$badge = $d_num ? '<span class="wpddbp-ps-badge">' . esc_html( $num_for( $i ) ) . '</span>' : '';
		$flag  = ( $i + 1 === $current ) ? '<span class="wpddbp-ps-flag">' . esc_html__( 'You are here', 'doc-booker-pro' ) . '</span>' : '';
		$rows .= '<li class="wpddbp-ps-step' . $status_of( $i ) . '"><span class="wpddbp-ps-node" aria-hidden="true">' . $node_inner( $s, $i ) . $badge . '</span><div class="wpddbp-ps-body">' . $flag . $kicker_el( $s ) . $title_el( $s ) . $desc_el( $s ) . '</div></li>';
	}
	$inner = $header . '<ol class="wpddbp-ps-list">' . $rows . '</ol>' . $footer;
} elseif ( 'icon-disc' === $layout ) {
	$rows = '';
	foreach ( $steps as $i => $s ) {
		$badge = $d_num ? '<span class="wpddbp-ps-num">' . esc_html( $num_for( $i ) ) . '</span>' : '';
		$rows .= '<li class="wpddbp-ps-step' . $status_of( $i ) . '"><div class="wpddbp-ps-body">' . $kicker_el( $s ) . $title_el( $s ) . $desc_el( $s ) . '</div><div class="wpddbp-ps-discwrap"><span class="wpddbp-ps-disc" aria-hidden="true">' . $icon_svg( $s ) . '</span>' . $badge . '</div></li>';
	}
	$inner = $header . '<ol class="wpddbp-ps-list">' . $rows . '</ol>' . $footer;
} elseif ( 'segmented' === $layout ) {
	$cur_title = ( $current > 0 && isset( $steps[ $current - 1 ]['title'] ) ) ? $steps[ $current - 1 ]['title'] : ( isset( $steps[0]['title'] ) ? $steps[0]['title'] : '' );
	$done      = max( 0, $current - 1 );
	$track     = '';
	$labels    = '';
	foreach ( $steps as $i => $s ) {
		$track .= '<span class="wpddbp-ps-seg' . $status_of( $i ) . '"></span>';
		$dot    = ( $i + 1 < $current ) ? $check : esc_html( $num_for( $i ) );
		$sub    = ( $d_desc && '' !== ( $s['title'] ?? '' ) ) ? '<span class="wpddbp-ps-sub">' . esc_html( $s['title'] ) . '</span>' : '';
		$name   = '' !== ( $s['kicker'] ?? '' ) ? $s['kicker'] : ( $s['title'] ?? '' );
		$labels .= '<div class="wpddbp-ps-lab' . $status_of( $i ) . '"><div class="wpddbp-ps-lab-top"><span class="wpddbp-ps-dot" aria-hidden="true">' . $dot . '</span><span class="wpddbp-ps-name">' . esc_html( $name ) . '</span></div>' . $sub . '</div>';
	}
	$status = '<div class="wpddbp-ps-status"><p class="wpddbp-ps-now">' . esc_html__( 'Currently:', 'doc-booker-pro' ) . ' <em>' . esc_html( $cur_title ) . '</em></p><span class="wpddbp-ps-count"><strong>' . (int) $done . '</strong> ' . esc_html__( 'of', 'doc-booker-pro' ) . ' ' . count( $steps ) . ' ' . esc_html__( 'steps complete', 'doc-booker-pro' ) . '</span></div>';
	$note   = '';
	$hint   = $attributes['subheadingText'] ?? '';
	$btn    = ( '' !== ( $attributes['footerButtonText'] ?? '' ) ) ? '<a class="wpddbp-ps-btn" ' . $link_attr( array( 'linkURL' => $attributes['footerButtonURL'] ?? '', 'linkNewTab' => $attributes['footerButtonNewTab'] ?? false ) ) . '>' . esc_html( $attributes['footerButtonText'] ) . ' ' . $arrow . '</a>' : '';
	if ( '' !== $hint || '' !== $btn ) {
		$note = '<div class="wpddbp-ps-note">' . ( '' !== $hint ? '<span class="wpddbp-ps-hint">' . esc_html( $hint ) . '</span>' : '' ) . $btn . '</div>';
	}
	// header for segmented omits the subheading (it's reused as the note hint)
	$seg_header = '';
	if ( $attributes['displayHeader'] ?? true ) {
		$htag = in_array( $attributes['headingTag'] ?? 'h2', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $attributes['headingTag'] : 'h2';
		if ( '' !== ( $attributes['eyebrowText'] ?? '' ) ) {
			$seg_header .= '<span class="wpddbp-ps-eyebrow">' . esc_html( $attributes['eyebrowText'] ) . '</span>';
		}
		if ( '' !== ( $attributes['headingText'] ?? '' ) ) {
			$seg_header .= '<' . $htag . ' class="wpddbp-ps-heading">' . esc_html( $attributes['headingText'] ) . '</' . $htag . '>';
		}
		if ( '' !== $seg_header ) {
			$seg_header = '<div class="wpddbp-ps-head">' . $seg_header . '</div>';
		}
	}
	$inner = $seg_header . $status . '<div class="wpddbp-ps-track" role="progressbar" aria-valuenow="' . (int) $done . '" aria-valuemin="0" aria-valuemax="' . count( $steps ) . '">' . $track . '</div><div class="wpddbp-ps-labels">' . $labels . '</div>' . $note;
} elseif ( 'big-numeral' === $layout ) {
	$cards = '';
	foreach ( $steps as $i => $s ) {
		$wm   = $d_num ? '<span class="wpddbp-ps-watermark" aria-hidden="true">' . esc_html( $num_for( $i ) ) . '</span>' : '';
		$ic   = ( $d_icon && ! empty( $s['icon'] ) ) ? '<span class="wpddbp-ps-cicon" aria-hidden="true">' . $icon_svg( $s ) . '</span>' : '';
		$lnk  = ( $d_link && '' !== ( $s['linkText'] ?? '' ) ) ? '<a class="wpddbp-ps-link" ' . $link_attr( $s ) . '>' . esc_html( $s['linkText'] ) . ' ' . $arrow . '</a>' : '';
		$cards .= '<article class="wpddbp-ps-card">' . $wm . $ic . $kicker_el( $s ) . $title_el( $s ) . $desc_el( $s ) . $lnk . '</article>';
	}
	$inner = $header . '<div class="wpddbp-ps-grid">' . $cards . '</div>';
} elseif ( 'filled' === $layout ) {
	$cards = '';
	foreach ( $steps as $i => $s ) {
		$cls   = ! empty( $s['flagship'] ) ? ' is-flagship' : ' t-' . ( $i % 3 );
		$badge = $d_num ? '<span class="wpddbp-ps-badge">' . esc_html( $num_for( $i ) ) . '</span>' : '';
		$ic    = ( $d_icon && ! empty( $s['icon'] ) ) ? '<span class="wpddbp-ps-cicon" aria-hidden="true">' . $icon_svg( $s ) . '</span>' : '';
		$tag   = ( $d_det && '' !== ( $s['detail'] ?? '' ) ) ? '<span class="wpddbp-ps-tag"><span class="dot"></span>' . esc_html( $s['detail'] ) . '</span>' : '';
		$cards .= '<article class="wpddbp-ps-card' . $cls . '">' . $badge . $ic . $title_el( $s ) . $desc_el( $s ) . $tag . '</article>';
	}
	$inner = $header . '<div class="wpddbp-ps-grid">' . $cards . '</div>';
} else {
	// icon-row
	$items = '';
	$last  = count( $steps ) - 1;
	foreach ( $steps as $i => $s ) {
		$cn    = $d_num ? '<span class="wpddbp-ps-chipnum">' . esc_html( $num_for( $i ) ) . '</span>' : '';
		$meta  = ( '' !== ( $s['meta'] ?? '' ) ) ? '<div class="wpddbp-ps-rowmeta">' . esc_html( $s['meta'] ) . '</div>' : '';
		$items .= '<div class="wpddbp-ps-item" role="listitem"><span class="wpddbp-ps-chip" aria-hidden="true">' . $cn . '<span class="wpddbp-ps-icon">' . $icon_svg( $s ) . '</span></span><div class="wpddbp-ps-text"><div class="wpddbp-ps-rowlabel">' . esc_html( $s['title'] ?? '' ) . '</div>' . $meta . '</div></div>';
		if ( $i < $last ) {
			$items .= '<span class="wpddbp-ps-sep" aria-hidden="true">&rsaquo;</span>';
		}
	}
	$inner = $header . '<div class="wpddbp-ps-strip" role="list">' . $items . '</div>';
}

printf(
	'<div id="%1$s" class="wpddbp-ps wpddbp-ps--%2$s%3$s">%4$s</div>',
	esc_attr( $block_id ),
	esc_attr( $layout ),
	esc_attr( $align_class ),
	$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() + our own inline SVG markup.
);
