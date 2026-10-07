<?php
/**
 * Office Hours — server render (6 layouts + live status).
 *
 * Resolves the clinic timezone (site / UTC) and emits the weekly hours as a
 * dow-keyed JSON `data-hours` blob plus `data-tz` / `data-tz-offset` — cache-safe
 * because no server "now" is embedded; the frontend view.js resolves the clinic
 * clock itself (Intl, offset fallback). The same open/closed logic is mirrored
 * here in PHP for the no-flash / no-JS initial paint (pill, headline, today row,
 * today highlight). All author text is escaped.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'ruled-table';
$allowed  = array( 'ruled-table', 'compact-list', 'chip-strip', 'split-card', 'status-band', 'stacked-card' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'ruled-table';
}

$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

/* ---- options ---- */
$time_format   = ( '24' === ( $attributes['timeFormat'] ?? '12' ) ) ? '24' : '12';
$status_mode   = $attributes['statusMode'] ?? 'auto';
$countdown     = ( 'in' === ( $attributes['countdownStyle'] ?? 'at' ) ) ? 'in' : 'at';
$open_label    = $attributes['openLabel'] ?? 'Open now';
$closed_label  = $attributes['closedLabel'] ?? 'Closed';
$show_status   = ! isset( $attributes['displayStatus'] ) || $attributes['displayStatus'];
$show_next     = ! isset( $attributes['showNextHint'] ) || $attributes['showNextHint'];
$highlight     = ! isset( $attributes['highlightToday'] ) || $attributes['highlightToday'];
$dim_closed    = ! isset( $attributes['dimClosed'] ) || $attributes['dimClosed'];

/* ---- timezone (cache-safe: emit tz/offset, not a server "now") ---- */
$tz_mode = ( 'utc' === ( $attributes['timezone'] ?? 'site' ) ) ? 'utc' : 'site';
$eff_tz  = ( 'utc' === $tz_mode ) ? new DateTimeZone( 'UTC' ) : wp_timezone();
try {
	$now_dt = new DateTimeImmutable( 'now', $eff_tz );
} catch ( Exception $e ) {
	$now_dt = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	$eff_tz = new DateTimeZone( 'UTC' );
}
$today_dow  = (int) $now_dt->format( 'w' );
$now_min    = (int) $now_dt->format( 'G' ) * 60 + (int) $now_dt->format( 'i' );
$tz_name    = $eff_tz->getName();
$intl_ok    = ( 'UTC' === $tz_name || false !== strpos( $tz_name, '/' ) );
$data_tz    = $intl_ok ? $tz_name : '';
$offset_min = (int) round( $now_dt->getOffset() / 60 );

/* ---- weekly hours -> dow map + JSON for the frontend ---- */
$DAYS = array(
	array( 'dow' => 1, 'full' => 'Monday', 'short' => 'Mon' ),
	array( 'dow' => 2, 'full' => 'Tuesday', 'short' => 'Tue' ),
	array( 'dow' => 3, 'full' => 'Wednesday', 'short' => 'Wed' ),
	array( 'dow' => 4, 'full' => 'Thursday', 'short' => 'Thu' ),
	array( 'dow' => 5, 'full' => 'Friday', 'short' => 'Fri' ),
	array( 'dow' => 6, 'full' => 'Saturday', 'short' => 'Sat' ),
	array( 'dow' => 0, 'full' => 'Sunday', 'short' => 'Sun' ),
);
$week       = isset( $attributes['weeklyHours'] ) && is_array( $attributes['weeklyHours'] ) ? $attributes['weeklyHours'] : array();
$by_dow     = array();
$hours_json = array();
foreach ( $DAYS as $i => $d ) {
	$item            = ( isset( $week[ $i ] ) && is_array( $week[ $i ] ) ) ? $week[ $i ] : array( 'closed' => true );
	$by_dow[ $d['dow'] ] = $item;
	$hours_json[ (string) $d['dow'] ] = array(
		'closed' => ! empty( $item['closed'] ),
		'open'   => $item['open'] ?? '',
		'close'  => $item['close'] ?? '',
		'split'  => ! empty( $item['split'] ),
		'open2'  => $item['open2'] ?? '',
		'close2' => $item['close2'] ?? '',
	);
}

/* ---- shared logic (PHP mirror of utils/officeHours.js) ---- */
$to_min = function ( $t ) {
	if ( '' === $t || null === $t ) {
		return null;
	}
	$p = explode( ':', (string) $t );
	if ( count( $p ) < 2 || ! is_numeric( $p[0] ) || ! is_numeric( $p[1] ) ) {
		return null;
	}
	return (int) $p[0] * 60 + (int) $p[1];
};
$shifts_of = function ( $item ) use ( $to_min ) {
	if ( empty( $item ) || ! empty( $item['closed'] ) ) {
		return array();
	}
	$out = array();
	$o1  = $to_min( $item['open'] ?? '' );
	$c1  = $to_min( $item['close'] ?? '' );
	if ( null !== $o1 && null !== $c1 && $c1 > $o1 ) {
		$out[] = array( $o1, $c1 );
	}
	if ( ! empty( $item['split'] ) ) {
		$o2 = $to_min( $item['open2'] ?? '' );
		$c2 = $to_min( $item['close2'] ?? '' );
		if ( null !== $o2 && null !== $c2 && $c2 > $o2 ) {
			$out[] = array( $o2, $c2 );
		}
	}
	return $out;
};
$fmt_time = function ( $min, $fmt ) {
	if ( null === $min ) {
		return '';
	}
	$h  = intdiv( $min, 60 ) % 24;
	$m  = $min % 60;
	$mm = str_pad( (string) $m, 2, '0', STR_PAD_LEFT );
	if ( '24' === $fmt ) {
		return $h . ':' . $mm;
	}
	$ap  = $h >= 12 ? 'PM' : 'AM';
	$h12 = $h % 12;
	if ( 0 === $h12 ) {
		$h12 = 12;
	}
	return $h12 . ':' . $mm . ' ' . $ap;
};
$hours_text = function ( $item, $fmt, $closed_text ) use ( $shifts_of, $fmt_time ) {
	$sh = $shifts_of( $item );
	if ( empty( $sh ) ) {
		return $closed_text;
	}
	$parts = array();
	foreach ( $sh as $s ) {
		$parts[] = $fmt_time( $s[0], $fmt ) . ' – ' . $fmt_time( $s[1], $fmt );
	}
	return implode( ' · ', $parts );
};
$day_full = array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' );
$fmt_dur  = function ( $t ) {
	$h = intdiv( $t, 60 );
	$m = $t % 60;
	if ( $h <= 0 ) {
		return $m . 'm';
	}
	return $h . 'h ' . str_pad( (string) $m, 2, '0', STR_PAD_LEFT ) . 'm';
};
$st = ( function () use ( $by_dow, $today_dow, $now_min, $status_mode, $shifts_of ) {
	$today   = $by_dow[ $today_dow ] ?? null;
	$is_open = false;
	$closes  = null;
	foreach ( $shifts_of( $today ) as $s ) {
		if ( $now_min >= $s[0] && $now_min < $s[1] ) {
			$is_open = true;
			$closes  = $s[1];
		}
	}
	if ( 'open' === $status_mode ) {
		return array( 'isOpen' => true, 'closesAt' => $closes, 'next' => null );
	}
	if ( 'closed' === $status_mode ) {
		$is_open = false;
	}
	$next = null;
	if ( ! $is_open ) {
		$later = array();
		foreach ( $shifts_of( $today ) as $s ) {
			if ( $s[0] > $now_min ) {
				$later[] = $s[0];
			}
		}
		if ( ! empty( $later ) ) {
			sort( $later );
			$next = array( 'offset' => 0, 'dow' => $today_dow, 'min' => $later[0] );
		} else {
			for ( $i = 1; $i <= 7; $i++ ) {
				$d  = ( $today_dow + $i ) % 7;
				$sh = $shifts_of( $by_dow[ $d ] ?? null );
				if ( ! empty( $sh ) ) {
					$opens = array();
					foreach ( $sh as $s ) {
						$opens[] = $s[0];
					}
					sort( $opens );
					$next = array( 'offset' => $i, 'dow' => $d, 'min' => $opens[0] );
					break;
				}
			}
		}
	}
	return array( 'isOpen' => $is_open, 'closesAt' => $closes, 'next' => $next );
} )();

$detail_text = '';
if ( $st['isOpen'] ) {
	if ( null !== $st['closesAt'] ) {
		$detail_text = ( 'in' === $countdown )
			? 'Closes in ' . $fmt_dur( max( 0, $st['closesAt'] - $now_min ) ) . ' · ' . $fmt_time( $st['closesAt'], $time_format )
			: 'Closes at ' . $fmt_time( $st['closesAt'], $time_format );
	}
} elseif ( ! empty( $st['next'] ) ) {
	$o    = $st['next']['offset'];
	$when = ( 0 === $o ) ? 'today' : ( ( 1 === $o ) ? 'tomorrow' : $day_full[ $st['next']['dow'] ] );
	$detail_text = 'Opens ' . $when . ' at ' . $fmt_time( $st['next']['min'], $time_format );
}
$next_hint = '';
if ( $show_next ) {
	if ( $st['isOpen'] ) {
		$next_hint = ( null !== $st['closesAt'] ) ? 'until ' . $fmt_time( $st['closesAt'], $time_format ) : '';
	} elseif ( ! empty( $st['next'] ) ) {
		$o         = $st['next']['offset'];
		$when      = ( 0 === $o ) ? 'today' : ( ( 1 === $o ) ? 'tomorrow' : $day_full[ $st['next']['dow'] ] );
		$next_hint = 'opens ' . $when . ' ' . $fmt_time( $st['next']['min'], $time_format );
	}
}

/* ---- inline SVG icons ---- */
$svg = function ( $paths ) {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};
$ic_clock = $svg( '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' );
$ic_cal   = $svg( '<rect x="3" y="4" width="18" height="17" rx="1.5"/><path d="M3 9h18M8 3v3M16 3v3"/>' );
$ic_pin   = $svg( '<path d="M12 22s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>' );
$ic_phone = $svg( '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/>' );
$ic_mail  = $svg( '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>' );
$ic_info  = $svg( '<circle cx="12" cy="12" r="9"/><path d="M12 8v.01M11 12h1v4h1"/>' );
$ic_dir   = $svg( '<path d="M9 20l-6 2V6l6-2 6 2 6-2v16l-6 2-6-2z"/><path d="M9 4v16M15 6v16"/>' );

/* ---- shared fragments ---- */
$eyebrow_text = $attributes['eyebrowText'] ?? '';
$heading_text = $attributes['headingText'] ?? '';
$heading_acc  = $attributes['headingAccent'] ?? '';
$d_eyebrow    = ! isset( $attributes['displayEyebrow'] ) || $attributes['displayEyebrow'];
$d_heading    = ! isset( $attributes['displayHeading'] ) || $attributes['displayHeading'];

$eyebrow_el = ( $d_eyebrow && '' !== $eyebrow_text )
	? '<span class="wpddbp-oh-eyebrow">' . esc_html( $eyebrow_text ) . '</span>'
	: '';
$accent_el  = ( '' !== $heading_acc ) ? ' <em class="wpddbp-oh-accent">' . esc_html( $heading_acc ) . '</em>' : '';
$heading_el = ( $d_heading && ( '' !== $heading_text || '' !== $heading_acc ) )
	? '<h3 class="wpddbp-oh-heading">' . esc_html( $heading_text ) . $accent_el . '</h3>'
	: '';
$name_text  = trim( $heading_text . ' ' . $heading_acc );
$name_eyebrow = ( '' !== $name_text ) ? '<span class="wpddbp-oh-eyebrow">' . esc_html( $name_text ) . '</span>' : '';

$pill = '';
if ( $show_status ) {
	$cls     = $st['isOpen'] ? 'is-open' : 'is-closed';
	$lbl     = $st['isOpen'] ? $open_label : $closed_label;
	$next_el = ( $show_next ) ? '<span class="wpddbp-oh-next" data-oh-next>' . esc_html( $next_hint ) . '</span>' : '';
	$pill    = '<span class="wpddbp-oh-status ' . $cls . '" data-oh-status aria-live="polite"><span class="wpddbp-oh-dot" aria-hidden="true"></span><span class="wpddbp-oh-status-label" data-oh-label>' . esc_html( $lbl ) . '</span>' . $next_el . '</span>';
}

$headline_el = '<h2 class="wpddbp-oh-headline" data-oh-headline>We&rsquo;re <em class="wpddbp-oh-accent">' . ( $st['isOpen'] ? 'open' : 'closed' ) . '</em> right now.</h2>';
$detail_el   = '<p class="wpddbp-oh-detail" data-oh-detail>' . esc_html( $detail_text ) . '</p>';

/* day-label per item (custom override else day name) */
$label_of = function ( $i, $form ) use ( $week, $DAYS ) {
	$custom = ( isset( $week[ $i ]['label'] ) && '' !== $week[ $i ]['label'] ) ? $week[ $i ]['label'] : '';
	if ( '' !== $custom ) {
		return $custom;
	}
	return ( 'short' === $form ) ? $DAYS[ $i ]['short'] : $DAYS[ $i ]['full'];
};
$row_state = function ( $i ) use ( $by_dow, $DAYS, $today_dow, $highlight, $dim_closed ) {
	$dow = $DAYS[ $i ]['dow'];
	$cls = '';
	if ( $highlight && $dow === $today_dow ) {
		$cls .= ' is-today';
	}
	if ( ! empty( $by_dow[ $dow ]['closed'] ) ) {
		$cls .= ' is-closed-day';
	}
	return $cls;
};

/* table rows */
$build_table = function () use ( $DAYS, $week, $by_dow, $label_of, $row_state, $hours_text, $time_format ) {
	$rows = '';
	foreach ( $DAYS as $i => $d ) {
		$closed = ! empty( $by_dow[ $d['dow'] ]['closed'] );
		$hours  = $closed
			? '<span class="wpddbp-oh-hours is-closed">Closed</span>'
			: '<span class="wpddbp-oh-hours">' . esc_html( $hours_text( $by_dow[ $d['dow'] ], $time_format, 'Closed' ) ) . '</span>';
		$rows  .= '<tr class="wpddbp-oh-row' . $row_state( $i ) . '" data-dow="' . esc_attr( $d['dow'] ) . '">'
			. '<td class="wpddbp-oh-daycell"><span class="wpddbp-oh-day" data-oh-dayhost>' . esc_html( $label_of( $i, 'full' ) ) . '</span></td>'
			. '<td class="wpddbp-oh-hourscell">' . $hours . '</td></tr>';
	}
	return '<table class="wpddbp-oh-table"><tbody>' . $rows . '</tbody></table>';
};

/* compact list rows */
$build_list = function () use ( $DAYS, $week, $by_dow, $label_of, $row_state, $hours_text, $time_format ) {
	$rows = '';
	foreach ( $DAYS as $i => $d ) {
		$closed = ! empty( $by_dow[ $d['dow'] ]['closed'] );
		$hours  = $closed
			? '<span class="wpddbp-oh-hours is-closed">Closed</span>'
			: '<span class="wpddbp-oh-hours">' . esc_html( $hours_text( $by_dow[ $d['dow'] ], $time_format, 'Closed' ) ) . '</span>';
		$rows  .= '<div class="wpddbp-oh-row' . $row_state( $i ) . '" data-dow="' . esc_attr( $d['dow'] ) . '">'
			. '<span class="wpddbp-oh-day" data-oh-dayhost><span class="wpddbp-oh-bullet" aria-hidden="true"></span>' . esc_html( $label_of( $i, 'short' ) ) . '</span>'
			. $hours . '</div>';
	}
	return '<div class="wpddbp-oh-list">' . $rows . '</div>';
};

/* week list (li) for split / stacked */
$build_weeklist = function ( $form ) use ( $DAYS, $week, $by_dow, $label_of, $row_state, $hours_text, $time_format ) {
	$rows = '';
	foreach ( $DAYS as $i => $d ) {
		$closed = ! empty( $by_dow[ $d['dow'] ]['closed'] );
		$hours  = $closed
			? '<span class="wpddbp-oh-hours is-closed">Closed</span>'
			: '<span class="wpddbp-oh-hours">' . esc_html( $hours_text( $by_dow[ $d['dow'] ], $time_format, 'Closed' ) ) . '</span>';
		$rows  .= '<li class="wpddbp-oh-row' . $row_state( $i ) . '" data-dow="' . esc_attr( $d['dow'] ) . '">'
			. '<span class="wpddbp-oh-day" data-oh-dayhost>' . esc_html( $label_of( $i, $form ) ) . '</span>'
			. $hours . '</li>';
	}
	return '<ul class="wpddbp-oh-weeklist">' . $rows . '</ul>';
};

/* day chips for chip-strip / status-band */
$build_chips = function () use ( $DAYS, $week, $by_dow, $label_of, $row_state, $shifts_of, $fmt_time, $time_format ) {
	$chips = '';
	foreach ( $DAYS as $i => $d ) {
		$sh = $shifts_of( $by_dow[ $d['dow'] ] );
		if ( empty( $sh ) ) {
			$hrs = '<span class="wpddbp-oh-chip-hours is-closed">Closed</span>';
		} else {
			$lines = '';
			foreach ( $sh as $s ) {
				$lines .= '<span class="wpddbp-oh-chip-split">' . esc_html( $fmt_time( $s[0], $time_format ) . '–' . $fmt_time( $s[1], $time_format ) ) . '</span>';
			}
			$hrs = '<span class="wpddbp-oh-chip-hours">' . $lines . '</span>';
		}
		$chips .= '<div class="wpddbp-oh-chip' . $row_state( $i ) . '" data-dow="' . esc_attr( $d['dow'] ) . '">'
			. '<span class="wpddbp-oh-chip-day">' . esc_html( $label_of( $i, 'short' ) ) . '</span>'
			. '<span class="wpddbp-oh-chip-rule" aria-hidden="true"></span>'
			. $hrs . '</div>';
	}
	return $chips;
};

/* contact list (card variants) */
$d_contact = ! isset( $attributes['displayContact'] ) || $attributes['displayContact'];
$address   = $attributes['addressText'] ?? '';
$phone     = $attributes['phoneText'] ?? '';
$email     = $attributes['emailText'] ?? '';
$build_contact = function () use ( $d_contact, $address, $phone, $email, $ic_pin, $ic_phone, $ic_mail ) {
	if ( ! $d_contact ) {
		return '';
	}
	$items = '';
	$row   = function ( $icon, $label, $value_html ) {
		return '<li class="wpddbp-oh-contact-item"><span class="wpddbp-oh-contact-ic" aria-hidden="true">' . $icon . '</span>'
			. '<span class="wpddbp-oh-contact-body"><span class="wpddbp-oh-contact-label">' . esc_html( $label ) . '</span>' . $value_html . '</span></li>';
	};
	if ( '' !== $address ) {
		$items .= $row( $ic_pin, 'Address', '<span class="wpddbp-oh-contact-value">' . esc_html( $address ) . '</span>' );
	}
	if ( '' !== $phone ) {
		$tel    = preg_replace( '/[^0-9+]/', '', $phone );
		$items .= $row( $ic_phone, 'Phone', '<a class="wpddbp-oh-contact-value" href="' . esc_url( 'tel:' . $tel ) . '">' . esc_html( $phone ) . '</a>' );
	}
	if ( '' !== $email ) {
		$items .= $row( $ic_mail, 'Email', '<a class="wpddbp-oh-contact-value" href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' );
	}
	return ( '' !== $items ) ? '<ul class="wpddbp-oh-contact">' . $items . '</ul>' : '';
};

/* CTAs */
$build_ctas = function ( $on_dark = false ) use ( $attributes, $ic_dir, $ic_phone ) {
	$out      = '';
	$dark_cls = $on_dark ? ' wpddbp-oh-btn--ondark' : '';
	if ( ( ! isset( $attributes['displayPrimaryBtn'] ) || $attributes['displayPrimaryBtn'] ) && '' !== ( $attributes['primaryBtnText'] ?? '' ) ) {
		$href   = ! empty( $attributes['primaryBtnURL'] ) ? esc_url( $attributes['primaryBtnURL'] ) : '#';
		$target = ! empty( $attributes['primaryBtnNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
		$out   .= '<a class="wpddbp-oh-btn wpddbp-oh-btn--primary' . $dark_cls . '" href="' . $href . '"' . $target . '>' . $ic_dir . esc_html( $attributes['primaryBtnText'] ) . ' <span class="wpddbp-oh-arrow" aria-hidden="true">&rarr;</span></a>';
	}
	if ( ( ! isset( $attributes['displaySecondaryBtn'] ) || $attributes['displaySecondaryBtn'] ) && '' !== ( $attributes['secondaryBtnText'] ?? '' ) ) {
		$href   = ! empty( $attributes['secondaryBtnURL'] ) ? esc_url( $attributes['secondaryBtnURL'] ) : '#';
		$target = ! empty( $attributes['secondaryBtnNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
		$out   .= '<a class="wpddbp-oh-btn wpddbp-oh-btn--secondary' . $dark_cls . '" href="' . $href . '"' . $target . '>' . $ic_phone . esc_html( $attributes['secondaryBtnText'] ) . '</a>';
	}
	return ( '' !== $out ) ? '<div class="wpddbp-oh-ctas">' . $out . '</div>' : '';
};

/* footnote */
$footnote = $attributes['footnoteText'] ?? '';
$d_foot   = ! isset( $attributes['displayFootnote'] ) || $attributes['displayFootnote'];
$foot_el  = ( $d_foot && '' !== $footnote ) ? '<div class="wpddbp-oh-foot">' . $ic_info . esc_html( $footnote ) . '</div>' : '';

/* today row (split-card) */
$today_item   = $by_dow[ $today_dow ] ?? null;
$today_hours  = $hours_text( $today_item, $time_format, 'Closed today' );
$today_row_el = '<div class="wpddbp-oh-today-row">' . $ic_clock
	. '<span class="wpddbp-oh-today-label" data-oh-today-label>' . esc_html( $day_full[ $today_dow ] ) . '</span>'
	. '<span class="wpddbp-oh-today-hours" data-oh-today-hours>' . esc_html( $today_hours ) . '</span></div>';

$week_title = esc_html( $attributes['weekListTitle'] ?? '' );
$week_note  = esc_html( $attributes['weekListNote'] ?? '' );

/* ---- assemble per layout ---- */
switch ( $layout ) {
	case 'compact-list':
		$title = ( '' !== $heading_text ) ? esc_html( $heading_text ) : 'Opening Hours';
		$body  = '<div class="wpddbp-oh-head"><h4 class="wpddbp-oh-heading">' . $ic_clock . $title . '</h4>' . $pill . '</div>' . $build_list();
		$body  = '<section class="wpddbp-oh-surface wpddbp-oh-panel">' . $body . '</section>';
		break;

	case 'chip-strip':
		$body = '<section class="wpddbp-oh-surface wpddbp-oh-card">'
			. '<div class="wpddbp-oh-banner"><div class="wpddbp-oh-titlewrap">' . $eyebrow_el . $heading_el . '</div>' . $pill . '</div>'
			. '<div class="wpddbp-oh-strip">' . $build_chips() . '</div>'
			. '</section>';
		break;

	case 'split-card':
		$left  = '<div class="wpddbp-oh-split-left">' . $name_eyebrow . $pill . $headline_el . $detail_el . $today_row_el . $build_contact() . $build_ctas() . '</div>';
		$right = '<aside class="wpddbp-oh-split-right">'
			. ( '' !== $week_title ? '<h3 class="wpddbp-oh-weektitle">' . $week_title . '</h3>' : '' )
			. ( '' !== $week_note ? '<p class="wpddbp-oh-weeknote">' . $week_note . '</p>' : '' )
			. $build_weeklist( 'full' ) . '</aside>';
		$body  = '<section class="wpddbp-oh-surface wpddbp-oh-split">' . $left . $right . '</section>';
		break;

	case 'status-band':
		$body = '<section class="wpddbp-oh-surface wpddbp-oh-band">'
			. '<div class="wpddbp-oh-band-top"><div class="wpddbp-oh-band-statuswrap">' . $name_eyebrow . $pill . $headline_el . $detail_el . '</div>'
			. '<div class="wpddbp-oh-band-cta">' . $build_ctas( true ) . '</div></div>'
			. '<div class="wpddbp-oh-daychips">' . $build_chips() . '</div>'
			. '</section>';
		break;

	case 'stacked-card':
		$name = ( '' !== $name_text ) ? '<h3 class="wpddbp-oh-heading">' . esc_html( $name_text ) . '</h3>' : '';
		$body = '<aside class="wpddbp-oh-surface wpddbp-oh-stacked">'
			. '<div class="wpddbp-oh-stacked-head">' . $pill . $name . $detail_el . '</div>'
			. $build_contact()
			. '<div class="wpddbp-oh-stacked-hours">' . ( '' !== $week_title ? '<h5 class="wpddbp-oh-weektitle">' . $week_title . '</h5>' : '' ) . $build_weeklist( 'short' ) . '</div>'
			. '<div class="wpddbp-oh-stacked-foot">' . $build_ctas() . '</div>'
			. '</aside>';
		break;

	case 'ruled-table':
	default:
		$body = '<section class="wpddbp-oh-surface wpddbp-oh-card">'
			. '<div class="wpddbp-oh-head"><div class="wpddbp-oh-titlewrap">' . $eyebrow_el . $heading_el . '</div>' . $pill . '</div>'
			. $build_table()
			. $foot_el
			. '</section>';
		break;
}

$root_class = 'wpddbp-oh wpddbp-oh--' . $layout . $align_class
	. ( $dim_closed ? ' is-dim-closed' : '' )
	. ( $st['isOpen'] ? ' is-open' : ' is-closed' );

printf(
	'<div id="%1$s" class="%2$s" data-hours="%3$s" data-tz="%4$s" data-tz-offset="%5$s" data-status-mode="%6$s" data-time-format="%7$s" data-countdown="%8$s" data-open-label="%9$s" data-closed-label="%10$s" data-next-hint="%11$s" data-highlight-today="%12$s">%13$s</div>',
	esc_attr( $block_id ),
	esc_attr( $root_class ),
	esc_attr( wp_json_encode( $hours_json ) ),
	esc_attr( $data_tz ),
	esc_attr( (string) $offset_min ),
	esc_attr( $status_mode ),
	esc_attr( $time_format ),
	esc_attr( $countdown ),
	esc_attr( $open_label ),
	esc_attr( $closed_label ),
	esc_attr( $show_next ? '1' : '0' ),
	esc_attr( $highlight ? '1' : '0' ),
	$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html()/esc_url()/esc_attr() above.
);
