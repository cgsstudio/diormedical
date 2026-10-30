<?php
/**
 * Testimonials block — server render (Phase 5).
 *
 * Author-entered patient testimonials in 3 layouts (quote-cards grid / featured
 * spotlight with a thumbnail rail / masonry wall). Grounded: all content is author-
 * typed (DocBooker has no review CPT). The spotlight's hero-switching is `view.js`.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = in_array( ( $attributes['layoutType'] ?? 'quote-cards' ), array( 'quote-cards', 'featured-spotlight', 'wall-mosaic' ), true ) ? $attributes['layoutType'] : 'quote-cards';
$align    = in_array( ( $attributes['align'] ?? '' ), array( 'wide', 'full' ), true ) ? $attributes['align'] : '';
$items    = ( ! empty( $attributes['items'] ) && is_array( $attributes['items'] ) ) ? $attributes['items'] : array();

if ( empty( $items ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="wpddbp-tm-empty">' . esc_html__( 'Testimonials: add at least one quote in the block settings.', 'doc-booker-pro' ) . '</div>';
	}
	return;
}

$show = function ( $key ) use ( $attributes ) { return ! isset( $attributes[ $key ] ) || $attributes[ $key ]; };

if ( ! function_exists( 'wpddbp_tm_initials' ) ) {
	/** Initials from a name (drops a leading "Dr."), up to 2 letters. */
	function wpddbp_tm_initials( $name ) {
		$name  = trim( preg_replace( '/^\s*dr\.?\s+/i', '', wp_strip_all_tags( (string) $name ) ) );
		$parts = preg_split( '/\s+/', $name );
		$parts = array_values( array_filter( $parts ) );
		if ( empty( $parts ) ) {
			return '';
		}
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = ( count( $parts ) > 1 ) ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		return strtoupper( $first . $last );
	}

	/** 5-star row (filled to the rating), with the numeric value. */
	function wpddbp_tm_stars( $rating ) {
		$r   = max( 0, min( 5, (int) round( (float) $rating ) ) );
		$out = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = ( $i <= $r ) ? 'currentColor' : 'none';
			$out .= '<svg viewBox="0 0 24 24" fill="' . $fill . '" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M12 2 14.9 8.6 22 9.5l-5.2 4.8L18.1 22 12 18.3 5.9 22l1.3-7.7L2 9.5l7.1-.9L12 2Z"/></svg>';
		}
		$val = rtrim( rtrim( number_format( (float) $rating, 1, '.', '' ), '0' ), '.' );
		return '<span class="wpddbp-tm-stars" aria-label="' . esc_attr( sprintf( __( '%s out of 5', 'doc-booker-pro' ), $val ) ) . '">' . $out . '<span class="wpddbp-tm-rval">' . esc_html( $val ) . '</span></span>';
	}

	/** Avatar (image) or initials seal. */
	function wpddbp_tm_avatar( $item, $show_avatar ) {
		if ( ! $show_avatar ) {
			return '';
		}
		$url = isset( $item['image']['url'] ) ? $item['image']['url'] : '';
		if ( '' !== $url ) {
			return '<span class="wpddbp-tm-av"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $item['name'] ?? '' ) . '" loading="lazy"></span>';
		}
		return '<span class="wpddbp-tm-av wpddbp-tm-av--seal">' . esc_html( wpddbp_tm_initials( $item['name'] ?? '' ) ) . '</span>';
	}

	/** Verified check (author-set). */
	function wpddbp_tm_verified( $item, $show_verified ) {
		if ( ! $show_verified || empty( $item['verified'] ) ) {
			return '';
		}
		return '<span class="wpddbp-tm-check" title="' . esc_attr__( 'Verified patient', 'doc-booker-pro' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg></span>';
	}

	/** Doctor chip — seal + name + specialty (+ optional link). */
	function wpddbp_tm_doctor( $item, $show_chip ) {
		if ( ! $show_chip || '' === trim( (string) ( $item['doctorName'] ?? '' ) ) ) {
			return '';
		}
		$spec = ( '' !== trim( (string) ( $item['doctorSpecialty'] ?? '' ) ) ) ? '<span class="wpddbp-tm-spec">' . esc_html( $item['doctorSpecialty'] ) . '</span>' : '';
		$inner = '<span class="wpddbp-tm-seal">' . esc_html( wpddbp_tm_initials( $item['doctorName'] ) ) . '</span>'
			. '<b>' . esc_html( $item['doctorName'] ) . '</b>' . $spec;
		$url = trim( (string) ( $item['doctorUrl'] ?? '' ) );
		if ( '' !== $url ) {
			return '<a class="wpddbp-tm-doc" href="' . esc_url( $url ) . '">' . $inner . '<span class="wpddbp-tm-arrow" aria-hidden="true">&rarr;</span></a>';
		}
		return '<span class="wpddbp-tm-doc">' . $inner . '</span>';
	}

	/** Identity row (avatar + name + verified + role/date). */
	function wpddbp_tm_ident( $item, $show, $show_avatar ) {
		$meta_bits = array();
		if ( '' !== trim( (string) ( $item['role'] ?? '' ) ) ) {
			$meta_bits[] = esc_html( $item['role'] );
		}
		if ( $show( 'showDate' ) && '' !== trim( (string) ( $item['date'] ?? '' ) ) ) {
			$meta_bits[] = esc_html( $item['date'] );
		}
		$meta = ! empty( $meta_bits ) ? '<span class="wpddbp-tm-imeta">' . implode( ' <span class="wpddbp-tm-dot">·</span> ', $meta_bits ) . '</span>' : '';
		return '<div class="wpddbp-tm-ident">' . wpddbp_tm_avatar( $item, $show_avatar )
			. '<span class="wpddbp-tm-itext"><span class="wpddbp-tm-name">' . esc_html( $item['name'] ?? '' ) . wpddbp_tm_verified( $item, $show( 'showVerified' ) ) . '</span>' . $meta . '</span></div>';
	}
}

/** Header. */
$eye  = ( '' !== trim( (string) ( $attributes['eyebrowText'] ?? '' ) ) ) ? '<span class="wpddbp-tm-eyebrow">' . esc_html( $attributes['eyebrowText'] ) . '</span>' : '';
$ttl  = ( '' !== trim( (string) ( $attributes['titleText'] ?? '' ) ) ) ? '<h2 class="wpddbp-tm-title">' . esc_html( $attributes['titleText'] ) . '</h2>' : '';
$lede = ( '' !== trim( (string) ( $attributes['ledeText'] ?? '' ) ) ) ? '<p class="wpddbp-tm-lede">' . esc_html( $attributes['ledeText'] ) . '</p>' : '';
$head = ( '' !== $eye || '' !== $ttl || '' !== $lede ) ? '<header class="wpddbp-tm-head">' . $eye . $ttl . $lede . '</header>' : '';

$body = '';

if ( 'featured-spotlight' === $layout ) {
	$panels = '';
	$thumbs = '';
	foreach ( $items as $i => $item ) {
		$active = ( 0 === $i ) ? ' is-active' : '';
		$hidden = ( 0 === $i ) ? '' : ' hidden';
		$stars  = $show( 'showRating' ) ? wpddbp_tm_stars( $item['rating'] ?? 5 ) : '';
		$panels .= '<div class="wpddbp-tm-heropanel' . $active . '" data-hero-index="' . (int) $i . '"' . $hidden . '>'
			. '<span class="wpddbp-tm-glyph" aria-hidden="true">&ldquo;</span>'
			. '<blockquote class="wpddbp-tm-pull">' . esc_html( $item['quote'] ?? '' ) . '</blockquote>'
			. '<div class="wpddbp-tm-herometa">' . wpddbp_tm_ident( $item, $show, $show( 'showAvatar' ) ) . $stars . '</div>'
			. wpddbp_tm_doctor( $item, $show( 'showDoctorChip' ) )
			. '</div>';
		$qshort = wp_trim_words( wp_strip_all_tags( $item['quote'] ?? '' ), 18, '…' );
		$thumbs .= '<button type="button" class="wpddbp-tm-thumb' . ( 0 === $i ? ' is-active' : '' ) . '" data-thumb-index="' . (int) $i . '" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '">'
			. '<span class="wpddbp-tm-thead">' . wpddbp_tm_avatar( $item, true ) . '<span class="wpddbp-tm-twho"><span class="wpddbp-tm-name">' . esc_html( $item['name'] ?? '' ) . '</span>'
			. ( '' !== trim( (string) ( $item['doctorSpecialty'] ?? '' ) ) ? '<span class="wpddbp-tm-tspec">' . esc_html( $item['doctorSpecialty'] ) . '</span>' : '' ) . '</span></span>'
			. '<span class="wpddbp-tm-tquote">' . esc_html( $qshort ) . '</span>'
			. ( $show( 'showRating' ) ? wpddbp_tm_stars( $item['rating'] ?? 5 ) : '' )
			. '</button>';
	}
	$body = '<div class="wpddbp-tm-spotlight"><div class="wpddbp-tm-hero">' . $panels . '</div><div class="wpddbp-tm-rail" role="tablist">' . $thumbs . '</div></div>';
} elseif ( 'wall-mosaic' === $layout ) {
	$arch  = array( 'xl', 'photo', 'std', 'ocean', 'short', 'sage' );
	$cards = '';
	foreach ( $items as $i => $item ) {
		$type   = $arch[ $i % count( $arch ) ];
		$photo  = '';
		if ( 'photo' === $type && isset( $item['photo']['url'] ) && '' !== $item['photo']['url'] ) {
			$photo = '<div class="wpddbp-tm-photo"><img src="' . esc_url( $item['photo']['url'] ) . '" alt="' . esc_attr( $item['photo']['alt'] ?? '' ) . '" loading="lazy"></div>';
		}
		$stars  = $show( 'showRating' ) ? wpddbp_tm_stars( $item['rating'] ?? 5 ) : '';
		$cards .= '<article class="wpddbp-tm-mcard is-' . esc_attr( $type ) . '">'
			. $photo
			. '<div class="wpddbp-tm-mbody"><span class="wpddbp-tm-glyph" aria-hidden="true">&ldquo;</span>'
			. $stars
			. '<p class="wpddbp-tm-quote">' . esc_html( $item['quote'] ?? '' ) . '</p>'
			. wpddbp_tm_ident( $item, $show, $show( 'showAvatar' ) )
			. wpddbp_tm_doctor( $item, $show( 'showDoctorChip' ) )
			. '</div></article>';
	}
	$body = '<div class="wpddbp-tm-wall">' . $cards . '</div>';
} else {
	// quote-cards
	$featured_first = ! empty( $attributes['featuredFirst'] );
	$cards          = '';
	foreach ( $items as $i => $item ) {
		$feat   = ( $featured_first && 0 === $i ) ? ' is-featured' : '';
		$chip   = ( $featured_first && 0 === $i ) ? '<span class="wpddbp-tm-featchip"><em>' . esc_html__( 'Featured', 'doc-booker-pro' ) . '</em></span>' : '';
		$stars  = $show( 'showRating' ) ? wpddbp_tm_stars( $item['rating'] ?? 5 ) : '';
		$cards .= '<article class="wpddbp-tm-card' . $feat . '">'
			. '<span class="wpddbp-tm-glyph" aria-hidden="true">&ldquo;</span>' . $chip
			. $stars
			. '<p class="wpddbp-tm-quote">' . esc_html( $item['quote'] ?? '' ) . '</p>'
			. '<div class="wpddbp-tm-hair"></div>'
			. wpddbp_tm_ident( $item, $show, $show( 'showAvatar' ) )
			. wpddbp_tm_doctor( $item, $show( 'showDoctorChip' ) )
			. '</article>';
	}
	$body = '<div class="wpddbp-tm-grid">' . $cards . '</div>';
}

$classes = 'wpddbp-tm-wrap wpddbp-tm-wrap--' . esc_attr( $layout );
if ( '' !== $align ) {
	$classes .= ' align' . esc_attr( $align );
}

echo '<div id="' . $block_id . '" class="' . $classes . '">'
	. '<div class="wpddbp-tm">' . $head . $body . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*().
	. '</div>';
