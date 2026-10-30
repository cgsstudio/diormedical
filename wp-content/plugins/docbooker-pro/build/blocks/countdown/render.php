<?php
/**
 * Countdown — server render (6 skins + expired state).
 *
 * Emits a timezone-aware UTC `data-end` ISO timestamp; the frontend view.js
 * ticks the numbers, rings, dot-rows and active-box sliver down to it and swaps
 * in the status pill at zero. Initial numbers / ring offsets are pre-computed so
 * there is no flash before the script boots.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'banner-gradient';
$allowed  = array( 'banner-gradient', 'banner-cream', 'banner-sticky', 'boxed-paper', 'boxed-tiles', 'boxed-rings' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'banner-gradient';
}
$kinds = array(
	'banner-gradient' => 'card',
	'banner-cream'    => 'card',
	'banner-sticky'   => 'mini',
	'boxed-paper'     => 'box',
	'boxed-tiles'     => 'tile',
	'boxed-rings'     => 'ring',
);
$kind = $kinds[ $layout ];

$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

/* ---- deadline (site timezone in, UTC ISO out) ---- */
$end_raw = trim( (string) ( $attributes['endDate'] ?? '' ) );
try {
	$tz = wp_timezone();
	$end_dt = ( '' !== $end_raw )
		? new DateTimeImmutable( $end_raw, $tz )
		: ( new DateTimeImmutable( 'now', $tz ) )->modify( '+7 days' );
} catch ( Exception $e ) {
	$end_dt = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+7 days' );
}
$now_ts     = time();
$diff_s     = $end_dt->getTimestamp() - $now_ts;
$is_expired = $diff_s <= 0;
$end_iso    = $end_dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );

$rem  = max( 0, $diff_s );
$vals = array(
	'days'    => (int) floor( $rem / 86400 ),
	'hours'   => (int) floor( ( $rem % 86400 ) / 3600 ),
	'minutes' => (int) floor( ( $rem % 3600 ) / 60 ),
	'seconds' => (int) ( $rem % 60 ),
);
$fracs = array(
	'days'    => min( $vals['days'] / 7, 1 ),
	'hours'   => $vals['hours'] / 24,
	'minutes' => $vals['minutes'] / 60,
	'seconds' => $vals['seconds'] / 60,
);
$ring_circ = 2 * M_PI * 54;

/* ---- visible units ---- */
$units = array(
	'days'    => array( 'display' => 'displayDays', 'label' => 'labelDays', 'def' => 'Days' ),
	'hours'   => array( 'display' => 'displayHours', 'label' => 'labelHours', 'def' => 'Hours' ),
	'minutes' => array( 'display' => 'displayMinutes', 'label' => 'labelMinutes', 'def' => 'Minutes' ),
	'seconds' => array( 'display' => 'displaySeconds', 'label' => 'labelSeconds', 'def' => 'Seconds' ),
);
$visible = array();
foreach ( $units as $key => $u ) {
	if ( ! isset( $attributes[ $u['display'] ] ) || $attributes[ $u['display'] ] ) {
		$visible[ $key ] = $u;
	}
}
if ( empty( $visible ) ) {
	$visible = array( 'seconds' => $units['seconds'] );
}

$pad      = function ( $v ) {
	return str_pad( (string) (int) $v, 2, '0', STR_PAD_LEFT );
};
$label_of = function ( $key, $u ) use ( $attributes ) {
	$v = $attributes[ $u['label'] ] ?? '';
	return ( '' !== $v ) ? $v : $u['def'];
};

/* ---- build the timer ---- */
$keys  = array_keys( $visible );
$last  = end( $keys );
$timer = '';
$i     = 0;
foreach ( $visible as $key => $u ) {
	$num   = '<span class="wpddbp-cd-num" data-unit="' . esc_attr( $key ) . '">' . esc_html( $pad( $vals[ $key ] ) ) . '</span>';
	$label = '<span class="wpddbp-cd-label">' . esc_html( $label_of( $key, $u ) ) . '</span>';
	$sep   = ( $i > 0 ) ? '<span class="wpddbp-cd-sep" aria-hidden="true">:</span>' : '';

	if ( 'card' === $kind ) {
		$timer .= $sep . '<div class="wpddbp-cd-seg"><div class="wpddbp-cd-card">' . $num . '</div>' . $label . '</div>';
	} elseif ( 'mini' === $kind ) {
		$abbr   = esc_html( mb_substr( $label_of( $key, $u ), 0, 1 ) );
		$timer .= $sep . '<span class="wpddbp-cd-mini-unit">' . $num . '<span class="wpddbp-cd-u">' . $abbr . '</span></span>';
	} elseif ( 'box' === $kind ) {
		$active   = ( $key === $last ) ? ' is-active' : '';
		$tickw    = ( $key === $last ) ? ' style="--tickw:' . esc_attr( round( $fracs[ $key ] * 100, 1 ) ) . '%"' : '';
		$tick     = '<span class="wpddbp-cd-tick" aria-hidden="true"></span>';
		$timer   .= $sep . '<div class="wpddbp-cd-box' . $active . '"' . $tickw . '>' . $num . $label . $tick . '</div>';
	} elseif ( 'tile' === $kind ) {
		$on    = (int) round( $fracs[ $key ] * 4 );
		$dots  = '';
		for ( $k = 0; $k < 4; $k++ ) {
			$dots .= ( $k < $on ) ? '<i class="on"></i>' : '<i></i>';
		}
		$timer .= '<div class="wpddbp-cd-tile">' . $num . $label . '<span class="wpddbp-cd-dots" aria-hidden="true">' . $dots . '</span></div>';
	} else { // ring
		$offset = $ring_circ * ( 1 - $fracs[ $key ] );
		$style  = ' style="stroke-dasharray:' . esc_attr( round( $ring_circ, 2 ) ) . ';stroke-dashoffset:' . esc_attr( round( $offset, 2 ) ) . '"';
		$timer .= '<div class="wpddbp-cd-ring r-' . esc_attr( $key ) . '"><div class="ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="track" cx="60" cy="60" r="54"></circle><circle class="prog" cx="60" cy="60" r="54" data-ring="' . esc_attr( $key ) . '"' . $style . '></circle></svg>' . $num . '</div>' . $label . '</div>';
	}
	$i++;
}
$timer_el = '<div class="wpddbp-cd-timer">' . $timer . '</div>';

/* ---- shared text fragments ---- */
$d_eye   = $attributes['displayEyebrow'] ?? true;
$d_title = $attributes['displayTitle'] ?? true;
$d_desc  = $attributes['displayDescription'] ?? true;
$eyebrow = $attributes['eyebrowText'] ?? '';
$title   = $attributes['titleText'] ?? '';
$accent  = $attributes['accentText'] ?? '';
$desc    = $attributes['descriptionText'] ?? '';

$eyebrow_el = ( $d_eye && '' !== $eyebrow )
	? '<span class="wpddbp-cd-eyebrow"><span class="wpddbp-cd-dot" aria-hidden="true"></span>' . esc_html( $eyebrow ) . '</span>'
	: '';
$accent_html = ( '' !== $accent ) ? '<em class="wpddbp-cd-accent">' . esc_html( $accent ) . '</em>' : '';
$title_el    = ( $d_title && ( '' !== $title || '' !== $accent_html ) )
	? '<h2 class="wpddbp-cd-title">' . esc_html( $title ) . ( ( '' !== $title && '' !== $accent_html ) ? ' ' : '' ) . $accent_html . '</h2>'
	: '';
$lede_el = ( $d_desc && '' !== $desc ) ? '<p class="wpddbp-cd-lede">' . esc_html( $desc ) . '</p>' : '';

$button_el = '';
if ( ! empty( $attributes['displayButton'] ) && '' !== ( $attributes['buttonText'] ?? '' ) ) {
	$url    = $attributes['buttonURL'] ?? '';
	$href   = $url ? esc_url( $url ) : '#';
	$target = ! empty( $attributes['buttonNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	$button_el = '<a class="wpddbp-cd-btn" href="' . $href . '"' . $target . '>' . esc_html( $attributes['buttonText'] ) . ' <span class="wpddbp-cd-arrow" aria-hidden="true">&rarr;</span></a>';
}

/* ---- assemble the body per family ---- */
if ( 'banner-sticky' === $layout ) {
	$body = '<div class="wpddbp-cd-body"><div class="wpddbp-cd-strip-left">' . $eyebrow_el . $title_el . $timer_el . '</div><div class="wpddbp-cd-strip-right">' . $button_el . '</div></div>';
} elseif ( 'banner-gradient' === $layout || 'banner-cream' === $layout ) {
	$body = '<div class="wpddbp-cd-body"><div class="wpddbp-cd-copy">' . $eyebrow_el . $title_el . $lede_el . '</div><div class="wpddbp-cd-side">' . $timer_el . $button_el . '</div></div>';
} else {
	$cta  = ( '' !== $button_el ) ? '<div class="wpddbp-cd-cta">' . $button_el . '</div>' : '';
	$body = '<div class="wpddbp-cd-body"><div class="wpddbp-cd-hero">' . $eyebrow_el . $title_el . $lede_el . $timer_el . $cta . '</div></div>';
}

$expired_msg = $attributes['expiredMessage'] ?? '';
$expired_el  = '<div class="wpddbp-cd-expired"><span class="wpddbp-cd-pill"><span class="wpddbp-cd-pulse" aria-hidden="true"></span>' . esc_html( $expired_msg ) . '</span></div>';

$root_class = 'wpddbp-cd wpddbp-cd--' . $layout . $align_class . ( $is_expired ? ' is-expired' : '' );

printf(
	'<div id="%1$s" class="%2$s" data-end="%3$s" role="timer" aria-label="%4$s">%5$s%6$s</div>',
	esc_attr( $block_id ),
	esc_attr( $root_class ),
	esc_attr( $end_iso ),
	esc_attr__( 'Countdown timer', 'doc-booker-pro' ),
	$body, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() above.
	$expired_el // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html() above.
);
