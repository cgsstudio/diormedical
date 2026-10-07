<?php
/**
 * Doctor Schedule block — weekly-timetable reader + variant renderers (Tier B/C, Phase 5).
 *
 * A single doctor's RECURRING weekly availability in 5 layouts (week-grid /
 * day-pills / spotlight / timeline / compact-list). Everything shown is REAL data:
 *
 *   - the weekly pattern from `wpddb_doctor_schedule` post meta — the canonical
 *     shape is [{ day, available, clinics:[{ id, name, timings:[{ time, is_bookable }] }] }];
 *     only WORKING days are stored, so absent days render as "off/closed".
 *   - `is_bookable` is the admin's "offered" toggle (per-date booked-state needs the
 *     bookings table + a concrete date, which a recurring weekly view has no business
 *     inventing — so we DROP the mockups' fabricated booked/hot/filling/recommended/
 *     duration decorations, exactly as the Quick Book block dropped its day-pills, see
 *     memory `docbooker-real-features-grounding`).
 *   - clinic holiday ranges from `wpddb_clinics_info` → a grounded "Closed M j – M j" note.
 *   - the doctor identity (name / dept / rating / fee / image) via `wpddbp_doctors_card_data()`.
 *
 * Every OFFERED slot is the shared booking-button helper, so a click routes into the
 * live Pro booking flow (Stripe / Razorpay / fallback) exactly like the doctor page.
 *
 * Standalone rule (architecture §3): namespaced `wpddbp_schedule_*`, reads the real
 * CPT/meta directly — no cross-plugin imports.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/doctors-data.php';

/* =============================================================================
 * Resolution + small value helpers
 * ============================================================================= */

/**
 * Resolve the doctor post id for a Schedule instance.
 * Priority: ?{urlParam}= override (opt-in) > current doctor page > picked doctor.
 *
 * @param array $attrs Block attributes.
 * @return int Doctor post id (0 when none resolvable).
 */
function wpddbp_schedule_resolve_doctor_id( $attrs ) {
	if ( ! empty( $attrs['urlParamOverride'] ) ) {
		$param = sanitize_key( $attrs['urlParam'] ?? 'doctor' );
		if ( '' !== $param && isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pid = (int) $_GET[ $param ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $pid > 0 && 'wpddb_doctor' === get_post_type( $pid ) ) {
				return $pid;
			}
		}
	}

	if ( 'current' === ( $attrs['source'] ?? 'specific' ) ) {
		$id = get_the_ID();
		return ( $id && 'wpddb_doctor' === get_post_type( $id ) ) ? (int) $id : 0;
	}

	$pid = (int) ( $attrs['doctorId'] ?? 0 );
	if ( ! $pid ) {
		$id = get_the_ID();
		if ( $id && 'wpddb_doctor' === get_post_type( $id ) ) {
			$pid = (int) $id;
		}
	}
	// Template/demo fallback: no specific or current doctor → feature the first
	// published doctor that actually has a weekly schedule, so the block is never
	// empty when dropped into a generic page template. Portable across sites.
	if ( ! $pid ) {
		$with_sched = get_posts( array(
			'post_type'      => 'wpddb_doctor',
			'post_status'    => 'publish',
			'posts_per_page' => 25,
			'fields'         => 'ids',
			'meta_key'       => 'wpddb_doctor_schedule', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		) );
		foreach ( (array) $with_sched as $fid ) {
			if ( ! empty( get_post_meta( $fid, 'wpddb_doctor_schedule', true ) ) ) {
				$pid = (int) $fid;
				break;
			}
		}
		if ( ! $pid ) {
			$any = get_posts( array( 'post_type' => 'wpddb_doctor', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
			if ( ! empty( $any ) ) {
				$pid = (int) $any[0];
			}
		}
	}
	return $pid;
}

/** Canonical days, indexed by PHP/JS dow (0 = Sunday … 6 = Saturday). */
function wpddbp_schedule_day_defs() {
	return array(
		0 => array( 'key' => 'sunday',    'full' => __( 'Sunday', 'doc-booker-pro' ),    'short' => __( 'Sun', 'doc-booker-pro' ) ),
		1 => array( 'key' => 'monday',    'full' => __( 'Monday', 'doc-booker-pro' ),    'short' => __( 'Mon', 'doc-booker-pro' ) ),
		2 => array( 'key' => 'tuesday',   'full' => __( 'Tuesday', 'doc-booker-pro' ),   'short' => __( 'Tue', 'doc-booker-pro' ) ),
		3 => array( 'key' => 'wednesday', 'full' => __( 'Wednesday', 'doc-booker-pro' ), 'short' => __( 'Wed', 'doc-booker-pro' ) ),
		4 => array( 'key' => 'thursday',  'full' => __( 'Thursday', 'doc-booker-pro' ),  'short' => __( 'Thu', 'doc-booker-pro' ) ),
		5 => array( 'key' => 'friday',    'full' => __( 'Friday', 'doc-booker-pro' ),    'short' => __( 'Fri', 'doc-booker-pro' ) ),
		6 => array( 'key' => 'saturday',  'full' => __( 'Saturday', 'doc-booker-pro' ),  'short' => __( 'Sat', 'doc-booker-pro' ) ),
	);
}

/** The week order (array of dow ints) for the configured start day. */
function wpddbp_schedule_week_order( $attrs ) {
	$mon = array( 1, 2, 3, 4, 5, 6, 0 );
	$sun = array( 0, 1, 2, 3, 4, 5, 6 );
	return ( 'sunday' === ( $attrs['weekStartsOn'] ?? 'monday' ) ) ? $sun : $mon;
}

/** Time-of-day bands with display labels + sub-ranges. */
function wpddbp_schedule_band_defs() {
	return array(
		'morning'   => array( 'label' => __( 'Morning', 'doc-booker-pro' ),   'range' => __( 'Before noon', 'doc-booker-pro' ) ),
		'afternoon' => array( 'label' => __( 'Afternoon', 'doc-booker-pro' ), 'range' => __( 'Noon – 5 PM', 'doc-booker-pro' ) ),
		'evening'   => array( 'label' => __( 'Evening', 'doc-booker-pro' ),   'range' => __( 'After 5 PM', 'doc-booker-pro' ) ),
	);
}

/** Bucket an hour (0–23) into a band key. */
function wpddbp_schedule_band_of( $hour ) {
	if ( null === $hour ) {
		return 'morning';
	}
	if ( $hour < 12 ) {
		return 'morning';
	}
	if ( $hour < 17 ) {
		return 'afternoon';
	}
	return 'evening';
}

/**
 * Parse a stored time string ("9:00 AM") into display parts honouring the format.
 *
 * @param string $raw Stored time.
 * @param string $fmt '12' | '24'.
 * @return array { disp, num, suffix, hour, min }
 */
function wpddbp_schedule_time_parts( $raw, $fmt = '12' ) {
	$raw = trim( (string) $raw );
	$dt  = false;
	foreach ( array( 'g:i A', 'h:i A', 'g:iA', 'H:i', 'G:i', 'g:i a' ) as $f ) {
		$try = DateTime::createFromFormat( '!' . $f, $raw );
		if ( $try instanceof DateTime ) {
			$dt = $try;
			break;
		}
	}
	if ( ! ( $dt instanceof DateTime ) ) {
		return array( 'disp' => $raw, 'num' => $raw, 'suffix' => '', 'hour' => null, 'min' => 0 );
	}
	$hour = (int) $dt->format( 'G' );
	$min  = (int) $dt->format( 'i' );
	if ( '24' === $fmt ) {
		return array( 'disp' => $dt->format( 'H:i' ), 'num' => $dt->format( 'H:i' ), 'suffix' => '', 'hour' => $hour, 'min' => $min );
	}
	return array(
		'disp'   => $dt->format( 'g:i A' ),
		'num'    => $dt->format( 'g:i' ),
		'suffix' => $dt->format( 'A' ),
		'hour'   => $hour,
		'min'    => $min,
	);
}

/* =============================================================================
 * Weekly model
 * ============================================================================= */

/**
 * Build the normalized weekly model for a doctor.
 *
 * @param int   $doctor_id Doctor post id.
 * @param array $attrs     Block attributes.
 * @return array {
 *   days           => array keyed by dow (only for the configured week order, in order),
 *   order          => array of dow ints,
 *   has_any        => bool (any working day at all),
 *   bands_used     => array of band keys used across the week,
 *   clinics        => array of unique clinic legend rows [{id,name,color_index,holiday}],
 *   group_by_band  => bool,
 * }
 */
function wpddbp_schedule_get_weekly( $doctor_id, $attrs ) {
	$fmt      = ( '24' === ( $attrs['timeFormat'] ?? '12' ) ) ? '24' : '12';
	$raw      = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
	$raw      = is_array( $raw ) ? $raw : array();
	$info     = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );
	$info     = is_array( $info ) ? $info : array();
	$defs     = wpddbp_schedule_day_defs();
	$order    = wpddbp_schedule_week_order( $attrs );
	$show_unav = ! empty( $attrs['showUnavailable'] );

	// Map stored (working) days by lowercase full-name → clinics array.
	$by_name = array();
	foreach ( $raw as $row ) {
		if ( empty( $row['day'] ) ) {
			continue;
		}
		$by_name[ strtolower( trim( $row['day'] ) ) ] = isset( $row['clinics'] ) && is_array( $row['clinics'] ) ? $row['clinics'] : array();
	}

	// Stable clinic colour index by first appearance across the canonical week order.
	$clinic_idx   = array();
	$clinic_meta  = array(); // id => name (first seen)
	$next_idx     = 0;
	foreach ( $order as $dow ) {
		$full = strtolower( $defs[ $dow ]['full'] );
		if ( empty( $by_name[ $full ] ) ) {
			continue;
		}
		foreach ( $by_name[ $full ] as $c ) {
			$cid = (string) ( $c['id'] ?? '' );
			if ( '' === $cid || isset( $clinic_idx[ $cid ] ) ) {
				continue;
			}
			$clinic_idx[ $cid ]  = $next_idx;
			$clinic_meta[ $cid ] = (string) ( $c['name'] ?? '' );
			$next_idx++;
		}
	}

	// Holiday note resolver (active/upcoming only).
	$today_ts = (int) ( strtotime( wp_date( 'Y-m-d' ) ) );
	$holiday_for = function ( $cid ) use ( $info, $today_ts ) {
		if ( empty( $info[ $cid ] ) || empty( $info[ $cid ]['is_holiday'] ) ) {
			return '';
		}
		$start = $info[ $cid ]['holiday_dates']['start_date'] ?? '';
		$end   = $info[ $cid ]['holiday_dates']['end_date'] ?? '';
		if ( '' === $start || '' === $end ) {
			return '';
		}
		if ( strtotime( $end ) < $today_ts ) {
			return ''; // wholly in the past.
		}
		/* translators: 1: start date, 2: end date. */
		return sprintf( __( 'Closed %1$s – %2$s', 'doc-booker-pro' ), wp_date( 'M j', strtotime( $start ) ), wp_date( 'M j', strtotime( $end ) ) );
	};

	$days       = array();
	$bands_used = array();
	$has_any    = false;

	foreach ( $order as $dow ) {
		$def     = $defs[ $dow ];
		$full    = strtolower( $def['full'] );
		$working = ! empty( $by_name[ $full ] );

		$clinics       = array();
		$bookable_cnt  = 0;
		$total_cnt     = 0;
		$first_disp    = '';
		$last_disp     = '';

		if ( $working ) {
			foreach ( $by_name[ $full ] as $c ) {
				$cid     = (string) ( $c['id'] ?? '' );
				$timings = isset( $c['timings'] ) && is_array( $c['timings'] ) ? $c['timings'] : array();
				$norm_t  = array();
				foreach ( $timings as $t ) {
					$bookable = ! empty( $t['is_bookable'] );
					if ( ! $bookable && ! $show_unav ) {
						continue;
					}
					$parts = wpddbp_schedule_time_parts( $t['time'] ?? '', $fmt );
					$band  = wpddbp_schedule_band_of( $parts['hour'] );
					$norm_t[] = array(
						'disp'     => $parts['disp'],
						'num'      => $parts['num'],
						'suffix'   => $parts['suffix'],
						'hour'     => $parts['hour'],
						'min'      => $parts['min'],
						'band'     => $band,
						'bookable' => $bookable,
						'sort'     => ( null === $parts['hour'] ? 9999 : ( $parts['hour'] * 60 + $parts['min'] ) ),
						// The slot needs its own identity to be bookable: `disp` is display-
						// formatted (and may be 24h), whereas the booking API matches the raw
						// stored time. `day` lets the frontend resolve the next real date.
						'raw'      => (string) ( $t['time'] ?? '' ),
						'day'      => $def['full'],
					);
					$total_cnt++;
					if ( $bookable ) {
						$bookable_cnt++;
					}
					if ( ! in_array( $band, $bands_used, true ) ) {
						$bands_used[] = $band;
					}
				}
				usort( $norm_t, function ( $a, $b ) {
					return $a['sort'] - $b['sort'];
				} );
				if ( empty( $norm_t ) ) {
					continue;
				}
				$idx = isset( $clinic_idx[ $cid ] ) ? $clinic_idx[ $cid ] : 0;
				$clinics[] = array(
					'id'          => $cid,
					'name'        => (string) ( $c['name'] ?? '' ),
					'color_index' => $idx,
					'timings'     => $norm_t,
				);
				if ( '' === $first_disp ) {
					$first_disp = $norm_t[0]['disp'];
				}
				$last_disp = $norm_t[ count( $norm_t ) - 1 ]['disp'];
			}
			if ( ! empty( $clinics ) ) {
				$has_any = true;
			}
		}

		$days[ $dow ] = array(
			'dow'           => $dow,
			'key'           => $def['key'],
			'full'          => $def['full'],
			'short'         => $def['short'],
			'working'       => ( $working && ! empty( $clinics ) ),
			'clinics'       => $clinics,
			'bookable_cnt'  => $bookable_cnt,
			'total_cnt'     => $total_cnt,
			'range'         => ( '' !== $first_disp ) ? ( $first_disp === $last_disp ? $first_disp : $first_disp . ' – ' . $last_disp ) : '',
		);
	}

	// Order bands_used canonically.
	$ordered_bands = array();
	foreach ( array_keys( wpddbp_schedule_band_defs() ) as $bk ) {
		if ( in_array( $bk, $bands_used, true ) ) {
			$ordered_bands[] = $bk;
		}
	}
	if ( empty( $ordered_bands ) ) {
		$ordered_bands = array( 'morning' );
	}

	// Legend rows.
	$legend = array();
	foreach ( $clinic_idx as $cid => $idx ) {
		$legend[] = array(
			'id'          => $cid,
			'name'        => $clinic_meta[ $cid ],
			'color_index' => $idx,
			'holiday'     => $holiday_for( $cid ),
		);
	}

	return array(
		'days'          => $days,
		'order'         => $order,
		'has_any'       => $has_any,
		'bands_used'    => $ordered_bands,
		'clinics'       => $legend,
		'group_by_band' => ! empty( $attrs['groupByBand'] ),
	);
}

/* =============================================================================
 * Atom renderers
 * ============================================================================= */

/** Inline var pointing at the clinic colour slot (cycled, 5-colour palette). */
function wpddbp_schedule_clinic_var( $idx ) {
	return 'var(--wpddbp-sch-c' . ( (int) $idx % 5 ) . ')';
}

/**
 * One slot — booking link (offered) or muted span (not offered).
 *
 * The link carries the slot's own day / time / clinic so a click can open the booking
 * modal already on that slot. Without them every slot rendered the identical
 * "{doctor}#booking" href, so clicking 10:00 AM and 8:00 PM did the same thing: bounce to
 * the doctor's page with the choice discarded (and `#booking` isn't even an element there).
 * view.js intercepts these; the href remains the no-JS fallback.
 */
function wpddbp_schedule_slot( $doctor_id, $timing, $attrs, $clinic = array() ) {
	if ( ! empty( $timing['bookable'] ) ) {
		return wpddbp_render_doctor_book_button(
			$doctor_id,
			array(
				'label' => $timing['disp'],
				'class' => 'wpddbp-sch-slot',
				'data'  => array(
					'slot-day'    => (string) ( $timing['day'] ?? '' ),
					'slot-time'   => (string) ( $timing['raw'] ?? '' ),
					'slot-clinic' => (string) ( $clinic['id'] ?? '' ),
				),
			)
		);
	}
	return '<span class="wpddbp-sch-slot is-unavailable" aria-disabled="true">' . esc_html( $timing['disp'] ) . '</span>';
}

/** A clinic group (mark + slots) within a band/day, carrying its colour var. */
function wpddbp_schedule_clinic_group( $doctor_id, $clinic, $timings, $attrs, $show_mark = true, $multi = false ) {
	if ( empty( $timings ) ) {
		return '';
	}
	$slots = '';
	foreach ( $timings as $t ) {
		// Pass the clinic through — the group knows it, the slot needs it to book.
		$slots .= wpddbp_schedule_slot( $doctor_id, $t, $attrs, $clinic );
	}
	$mark = ( $show_mark && $multi && '' !== $clinic['name'] )
		? '<span class="wpddbp-sch-clinicmark"><span class="wpddbp-sch-cdot" aria-hidden="true"></span>' . esc_html( $clinic['name'] ) . '</span>'
		: '';
	return '<div class="wpddbp-sch-cgroup" style="--sch-clinic:' . esc_attr( wpddbp_schedule_clinic_var( $clinic['color_index'] ) ) . '">'
		. $mark . '<div class="wpddbp-sch-slots">' . $slots . '</div></div>';
}

/** Timings of a clinic filtered to a band (or all when $band === 'all'). */
function wpddbp_schedule_band_timings( $clinic, $band ) {
	if ( 'all' === $band ) {
		return $clinic['timings'];
	}
	$out = array();
	foreach ( $clinic['timings'] as $t ) {
		if ( $t['band'] === $band ) {
			$out[] = $t;
		}
	}
	return $out;
}

/** The doctor identity strip shared by week-grid / day-pills / timeline. */
function wpddbp_schedule_doctor_strip( $d, $weekly, $attrs ) {
	if ( empty( $attrs['showDoctorStrip'] ) ) {
		return '';
	}

	$avatar = ( ! empty( $attrs['showAvatar'] ) && '' !== $d['image'] )
		? '<span class="wpddbp-sch-av"><img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy"></span>'
		: '';

	$sub = array();
	if ( '' !== $d['dept_name'] ) {
		$sub[] = '<span class="wpddbp-sch-dept">' . esc_html( $d['dept_name'] ) . '</span>';
	}
	if ( ! empty( $attrs['showCredentials'] ) ) {
		$cred = ! empty( $d['credentials'] ) ? implode( ' · ', array_map( 'sanitize_text_field', $d['credentials'] ) ) : $d['degree'];
		if ( '' !== $cred ) {
			$sub[] = '<span class="wpddbp-sch-cred">' . esc_html( $cred ) . '</span>';
		}
	}
	if ( ! empty( $attrs['showRating'] ) && $d['rating'] > 0 ) {
		$val = wpddbp_doctors_rating_value( $d['rating'] );
		$rc  = ( $d['review_count'] > 0 ) ? ' <small>(' . esc_html( number_format_i18n( $d['review_count'] ) ) . ')</small>' : '';
		$sub[] = '<span class="wpddbp-sch-rating">' . wpddbp_doctors_star_svg() . esc_html( $val ) . $rc . '</span>';
	}
	if ( ! empty( $attrs['showExperience'] ) && '' !== $d['experience'] ) {
		/* translators: %s: number of years. */
		$sub[] = '<span class="wpddbp-sch-exp">' . esc_html( sprintf( __( '%s yrs', 'doc-booker-pro' ), $d['experience'] ) ) . '</span>';
	}
	$sub_html = ! empty( $sub ) ? '<div class="wpddbp-sch-docsub">' . implode( '<span class="wpddbp-sch-dot" aria-hidden="true"></span>', $sub ) . '</div>' : '';

	$right = array();
	if ( ! empty( $attrs['showClinicLegend'] ) && ! empty( $weekly['clinics'] ) ) {
		$items = '';
		foreach ( $weekly['clinics'] as $c ) {
			if ( '' === $c['name'] ) {
				continue;
			}
			$holiday = ( ! empty( $attrs['showHolidayNote'] ) && '' !== $c['holiday'] )
				? '<small class="wpddbp-sch-holiday">' . esc_html( $c['holiday'] ) . '</small>'
				: '';
			$items .= '<span class="wpddbp-sch-legenditem" style="--sch-clinic:' . esc_attr( wpddbp_schedule_clinic_var( $c['color_index'] ) ) . '">'
				. '<span class="wpddbp-sch-cdot" aria-hidden="true"></span>' . esc_html( $c['name'] ) . $holiday . '</span>';
		}
		if ( '' !== $items ) {
			$right[] = '<div class="wpddbp-sch-legend"><span class="wpddbp-sch-legendlabel">' . esc_html__( 'Clinics', 'doc-booker-pro' ) . '</span><div class="wpddbp-sch-legendrow">' . $items . '</div></div>';
		}
	}
	if ( ! empty( $attrs['showFee'] ) && $d['fee'] > 0 ) {
		$right[] = '<div class="wpddbp-sch-feebox"><span class="wpddbp-sch-feelabel">' . esc_html__( 'From', 'doc-booker-pro' ) . '</span><span class="wpddbp-sch-feeamt">' . esc_html( wpddbp_format_fee( $d['fee'] ) ) . '</span></div>';
	}
	$right_html = ! empty( $right ) ? '<div class="wpddbp-sch-docright">' . implode( '', $right ) . '</div>' : '';

	return '<div class="wpddbp-sch-doc">'
		. '<div class="wpddbp-sch-docid">' . $avatar . '<div class="wpddbp-sch-docmeta"><div class="wpddbp-sch-docname">' . esc_html( $d['name'] ) . '</div>' . $sub_html . '</div></div>'
		. $right_html
		. '</div>';
}

/** Optional eyebrow + heading + lede header block. */
function wpddbp_schedule_header( $attrs ) {
	$eye  = ( '' !== trim( (string) ( $attrs['eyebrowText'] ?? '' ) ) ) ? '<span class="wpddbp-sch-eyebrow">' . esc_html( $attrs['eyebrowText'] ) . '</span>' : '';
	$head = ( '' !== trim( (string) ( $attrs['headingText'] ?? '' ) ) ) ? '<h2 class="wpddbp-sch-heading">' . esc_html( $attrs['headingText'] ) . '</h2>' : '';
	$lede = ( '' !== trim( (string) ( $attrs['ledeText'] ?? '' ) ) ) ? '<p class="wpddbp-sch-lede">' . esc_html( $attrs['ledeText'] ) . '</p>' : '';
	if ( '' === $eye && '' === $head && '' === $lede ) {
		return '';
	}
	return '<header class="wpddbp-sch-head">' . $eye . $head . $lede . '</header>';
}

/** Author-set footer link (e.g. full profile / booking page). */
function wpddbp_schedule_footer_link( $attrs ) {
	if ( empty( $attrs['showFooterLink'] ) || '' === trim( (string) ( $attrs['footerLinkUrl'] ?? '' ) ) ) {
		return '';
	}
	$tab   = ! empty( $attrs['footerLinkNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
	return '<div class="wpddbp-sch-foot"><a class="wpddbp-sch-footlink" href="' . esc_url( $attrs['footerLinkUrl'] ) . '"' . $tab . '>' . esc_html( $attrs['footerLinkText'] ?? __( 'View full profile', 'doc-booker-pro' ) ) . $arrow . '</a></div>';
}

/** Day off-state cell/markup body. */
function wpddbp_schedule_off_body( $attrs ) {
	return '<div class="wpddbp-sch-off"><span class="wpddbp-sch-offlabel">' . esc_html( $attrs['closedText'] ?? __( 'Closed', 'doc-booker-pro' ) ) . '</span></div>';
}

/* =============================================================================
 * Layout renderers
 * ============================================================================= */

/** WEEK-GRID — 7 day columns × time-of-day band rows. */
function wpddbp_schedule_render_week_grid( $doctor_id, $weekly, $attrs ) {
	$defs       = wpddbp_schedule_band_defs();
	$bands      = $weekly['group_by_band'] ? $weekly['bands_used'] : array( 'all' );
	$show_band  = ! empty( $attrs['showBandLabels'] ) && $weekly['group_by_band'];
	$cols       = count( $weekly['order'] );
	$multi      = count( $weekly['clinics'] ) > 1;

	// Header row: corner + day heads.
	$heads = '<div class="wpddbp-sch-corner"><span class="wpddbp-sch-cornerlabel">' . esc_html__( 'Week', 'doc-booker-pro' ) . '</span></div>';
	foreach ( $weekly['order'] as $dow ) {
		$day    = $weekly['days'][ $dow ];
		$off    = $day['working'] ? '' : ' is-off';
		$count  = ( ! empty( $attrs['showDayCount'] ) && $day['working'] )
			? '<span class="wpddbp-sch-daycount">' . esc_html( $day['bookable_cnt'] ) . '</span>'
			: '';
		$heads .= '<div class="wpddbp-sch-dayhead' . $off . '" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '">'
			. '<span class="wpddbp-sch-dname">' . esc_html( $day['short'] ) . '</span>' . $count . '</div>';
	}

	$rows = '';
	foreach ( $bands as $band ) {
		$label = '';
		if ( $show_band && isset( $defs[ $band ] ) ) {
			$label = '<span class="wpddbp-sch-band">' . esc_html( $defs[ $band ]['label'] ) . '</span><span class="wpddbp-sch-brange">' . esc_html( $defs[ $band ]['range'] ) . '</span>';
		}
		$rows .= '<div class="wpddbp-sch-tlabel">' . $label . '</div>';
		foreach ( $weekly['order'] as $dow ) {
			$day = $weekly['days'][ $dow ];
			if ( ! $day['working'] ) {
				$rows .= '<div class="wpddbp-sch-cell is-off" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '">' . wpddbp_schedule_off_body( $attrs ) . '</div>';
				continue;
			}
			$groups = '';
			foreach ( $day['clinics'] as $clinic ) {
				$timings = wpddbp_schedule_band_timings( $clinic, $band );
				$groups .= wpddbp_schedule_clinic_group( $doctor_id, $clinic, $timings, $attrs, true, $multi );
			}
			if ( '' === $groups ) {
				$groups = '<div class="wpddbp-sch-none" aria-hidden="true">·</div>';
			}
			$rows .= '<div class="wpddbp-sch-cell" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '">' . $groups . '</div>';
		}
	}

	return '<div class="wpddbp-sch-gridwrap wpddbp-sch-surface"><div class="wpddbp-sch-gridscroll"><div class="wpddbp-sch-grid" style="--sch-cols:' . (int) $cols . '">' . $heads . $rows . '</div></div></div>';
}

/** Render one day's slot body grouped by band (used by day-pills + spotlight panels). */
function wpddbp_schedule_day_panel_body( $doctor_id, $day, $weekly, $attrs ) {
	if ( ! $day['working'] ) {
		return '<div class="wpddbp-sch-panelempty">' . esc_html( $attrs['closedText'] ?? __( 'Closed', 'doc-booker-pro' ) ) . '</div>';
	}
	$defs  = wpddbp_schedule_band_defs();
	$multi = count( $weekly['clinics'] ) > 1;
	$bands = $weekly['group_by_band'] ? $weekly['bands_used'] : array( 'all' );

	$out = '';
	foreach ( $bands as $band ) {
		// Collect clinic groups that have timings in this band.
		$groups = '';
		$count  = 0;
		foreach ( $day['clinics'] as $clinic ) {
			$timings = wpddbp_schedule_band_timings( $clinic, $band );
			$count  += count( $timings );
			$groups .= wpddbp_schedule_clinic_group( $doctor_id, $clinic, $timings, $attrs, true, $multi );
		}
		if ( '' === $groups ) {
			continue;
		}
		$head = '';
		if ( $weekly['group_by_band'] && isset( $defs[ $band ] ) ) {
			$cnt  = ( ! empty( $attrs['showDayCount'] ) ) ? '<span class="wpddbp-sch-todcount">' . esc_html( $count ) . '</span>' : '';
			$head = '<h4 class="wpddbp-sch-todlabel">' . esc_html( $defs[ $band ]['label'] ) . '<span class="wpddbp-sch-todrule" aria-hidden="true"></span>' . $cnt . '</h4>';
		}
		$out .= '<div class="wpddbp-sch-todgroup">' . $head . '<div class="wpddbp-sch-slotgrid">' . $groups . '</div></div>';
	}
	if ( '' === $out ) {
		$out = '<div class="wpddbp-sch-panelempty">' . esc_html( $attrs['closedText'] ?? __( 'Closed', 'doc-booker-pro' ) ) . '</div>';
	}
	return $out;
}

/** DAY-PILLS — a week strip of day pills + the selected day's slot panel. */
function wpddbp_schedule_render_day_pills( $doctor_id, $weekly, $attrs ) {
	// Server default active = first working day (view.js upgrades to today).
	$active = -1;
	foreach ( $weekly['order'] as $i => $dow ) {
		if ( $weekly['days'][ $dow ]['working'] ) {
			$active = $i;
			break;
		}
	}

	$pills  = '';
	$panels = '';
	foreach ( $weekly['order'] as $i => $dow ) {
		$day      = $weekly['days'][ $dow ];
		$is_act   = ( $i === $active );
		$off      = $day['working'] ? '' : ' is-off';
		$sel      = $is_act ? ' is-selected' : '';
		if ( $day['working'] ) {
			/* translators: %d: number of bookable slots. */
			$badge = ( ! empty( $attrs['showDayCount'] ) )
				? '<span class="wpddbp-sch-badge">' . esc_html( sprintf( _n( '%d slot', '%d slots', $day['bookable_cnt'], 'doc-booker-pro' ), $day['bookable_cnt'] ) ) . '</span>'
				: '';
		} else {
			$badge = '<span class="wpddbp-sch-badge is-off">' . esc_html( $attrs['offText'] ?? __( 'Off', 'doc-booker-pro' ) ) . '</span>';
		}
		$pills .= '<button type="button" class="wpddbp-sch-pill' . $off . $sel . '" data-day-index="' . (int) $i . '" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '" role="tab" aria-selected="' . ( $is_act ? 'true' : 'false' ) . '">'
			. '<span class="wpddbp-sch-dname">' . esc_html( $day['short'] ) . '</span>' . $badge . '</button>';

		$ctx = '';
		if ( $day['working'] ) {
			$names = array();
			foreach ( $day['clinics'] as $c ) {
				if ( '' !== $c['name'] ) {
					$names[] = $c['name'];
				}
			}
			$ctx = ! empty( $names ) ? '<span class="wpddbp-sch-panelctx">' . esc_html( implode( ' · ', array_unique( $names ) ) ) . '</span>' : '';
		}
		$panels .= '<div class="wpddbp-sch-panel' . ( $is_act ? ' is-active' : '' ) . '" data-day-panel="' . (int) $i . '" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '"' . ( $is_act ? '' : ' hidden' ) . '>'
			. '<header class="wpddbp-sch-panelhdr"><h3 class="wpddbp-sch-panelday">' . esc_html( $day['full'] ) . '</h3>' . $ctx . '</header>'
			. wpddbp_schedule_day_panel_body( $doctor_id, $day, $weekly, $attrs )
			. '</div>';
	}

	return '<div class="wpddbp-sch-pills" role="tablist">' . $pills . '</div><div class="wpddbp-sch-panels">' . $panels . '</div>';
}

/** Two-letter initials for the monogram fallback portrait (skips a leading honorific). */
function wpddbp_schedule_initials( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return '·';
	}
	$parts = preg_split( '/\s+/', $name );
	// Drop a leading honorific so "Dr. Sarah Chen" → "SC".
	if ( count( $parts ) > 1 && preg_match( '/^(dr|mr|mrs|ms|prof|md)\.?$/i', $parts[0] ) ) {
		array_shift( $parts );
	}
	$grab  = function ( $s ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, 1 ) : substr( $s, 0, 1 );
	};
	$out = $grab( $parts[0] );
	if ( count( $parts ) > 1 ) {
		$out .= $grab( $parts[ count( $parts ) - 1 ] );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out ) : strtoupper( $out );
}

/** SPOTLIGHT — doctor presentation card + a day selector that swaps slot tiles. */
function wpddbp_schedule_render_spotlight( $doctor_id, $d, $weekly, $attrs ) {
	$active = -1;
	foreach ( $weekly['order'] as $i => $dow ) {
		if ( $weekly['days'][ $dow ]['working'] ) {
			$active = $i;
			break;
		}
	}

	// LEFT — presentation. Portrait: real image, else a branded monogram tile, so the
	// stage never collapses to an empty column for a photo-less (e.g. freshly-imported) doctor.
	$portrait = ( ! empty( $attrs['showAvatar'] ) && '' !== $d['image'] )
		? '<div class="wpddbp-sch-portrait"><img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy"></div>'
		: '<div class="wpddbp-sch-portrait wpddbp-sch-portrait--mono"><span class="wpddbp-sch-monogram" aria-hidden="true">' . esc_html( wpddbp_schedule_initials( $d['name'] ) ) . '</span></div>';
	$idsub = array();
	if ( '' !== $d['dept_name'] ) {
		$idsub[] = esc_html( $d['dept_name'] );
	}
	$cred = ! empty( $d['credentials'] ) ? implode( ' · ', array_map( 'sanitize_text_field', $d['credentials'] ) ) : $d['degree'];
	if ( ! empty( $attrs['showCredentials'] ) && '' !== $cred ) {
		$idsub[] = esc_html( $cred );
	}
	$identity = '<div class="wpddbp-sch-identity"><div class="wpddbp-sch-idname">' . esc_html( $d['name'] ) . '</div>'
		. ( ! empty( $idsub ) ? '<div class="wpddbp-sch-idsub">' . implode( ' · ', $idsub ) . '</div>' : '' ) . '</div>';

	$quote = ( ! empty( $attrs['showQuote'] ) && '' !== $d['quote'] )
		? '<p class="wpddbp-sch-quote">' . esc_html( $d['quote'] ) . '</p>'
		: '';

	$stats = '';
	if ( ! empty( $attrs['showStats'] ) ) {
		$cells = array();
		if ( '' !== $d['experience'] ) {
			$cells[] = '<div class="wpddbp-sch-stat"><b>' . esc_html( $d['experience'] ) . '</b><small>' . esc_html__( 'Years', 'doc-booker-pro' ) . '</small></div>';
		}
		if ( ! empty( $attrs['showRating'] ) && $d['rating'] > 0 ) {
			$cells[] = '<div class="wpddbp-sch-stat"><b>' . esc_html( wpddbp_doctors_rating_value( $d['rating'] ) ) . '</b><small>' . esc_html__( 'Rating', 'doc-booker-pro' ) . '</small></div>';
		}
		if ( ! empty( $d['languages'] ) ) {
			$cells[] = '<div class="wpddbp-sch-stat"><b>' . esc_html( implode( ' · ', array_map( 'sanitize_text_field', array_slice( $d['languages'], 0, 3 ) ) ) ) . '</b><small>' . esc_html__( 'Languages', 'doc-booker-pro' ) . '</small></div>';
		}
		if ( ! empty( $cells ) ) {
			$stats = '<div class="wpddbp-sch-stats">' . implode( '', $cells ) . '</div>';
		}
	}

	// Grounded "at a glance" foot — real clinic legend + this-week slot summary + a primary
	// booking CTA. Anchored to the bottom so the stage reads as a complete card even when the
	// doctor has no quote/stats (all values are computed from the real weekly model).
	$open_total = 0;
	$work_days  = 0;
	foreach ( $weekly['days'] as $wday ) {
		if ( ! empty( $wday['working'] ) ) {
			$work_days++;
			$open_total += (int) $wday['bookable_cnt'];
		}
	}

	$legend_html = '';
	if ( ! empty( $attrs['showClinicLegend'] ) && ! empty( $weekly['clinics'] ) ) {
		$items = '';
		foreach ( $weekly['clinics'] as $c ) {
			if ( '' === $c['name'] ) {
				continue;
			}
			$items .= '<span class="wpddbp-sch-legenditem" style="--sch-clinic:' . esc_attr( wpddbp_schedule_clinic_var( $c['color_index'] ) ) . '">'
				. '<span class="wpddbp-sch-cdot" aria-hidden="true"></span>' . esc_html( $c['name'] ) . '</span>';
		}
		if ( '' !== $items ) {
			$legend_html = '<div class="wpddbp-sch-legend"><span class="wpddbp-sch-legendlabel">' . esc_html__( 'Clinics', 'doc-booker-pro' ) . '</span><div class="wpddbp-sch-legendrow">' . $items . '</div></div>';
		}
	}

	$summary_html = '';
	if ( $open_total > 0 ) {
		$summary_html = '<div class="wpddbp-sch-glancesum"><b>' . esc_html( number_format_i18n( $open_total ) ) . '</b> '
			. esc_html( _n( 'open slot', 'open slots', $open_total, 'doc-booker-pro' ) ) . ' · '
			/* translators: %d: number of working days this week. */
			. esc_html( sprintf( _n( '%d day', '%d days', $work_days, 'doc-booker-pro' ), $work_days ) ) . ' '
			. esc_html__( 'this week', 'doc-booker-pro' ) . '</div>';
	}

	$glance = ( '' !== $legend_html || '' !== $summary_html )
		? '<div class="wpddbp-sch-glance">' . $legend_html . $summary_html . '</div>'
		: '';

	$stage_cta = wpddbp_render_doctor_book_button(
		$doctor_id,
		array(
			'label' => __( 'Book an appointment', 'doc-booker-pro' ),
			'class' => 'wpddbp-sch-cta wpddbp-sch-stagecta',
		)
	);

	$foot = ( '' !== $stats || '' !== $glance || '' !== $stage_cta )
		? '<div class="wpddbp-sch-stagefoot">' . $stats . $glance . $stage_cta . '</div>'
		: '';

	$stage = '<div class="wpddbp-sch-stage">' . $portrait . $identity . $quote . $foot . '</div>';

	// RIGHT — day selector + panels (shares the panel toggle mechanism).
	$daysel = '';
	$panels = '';
	foreach ( $weekly['order'] as $i => $dow ) {
		$day    = $weekly['days'][ $dow ];
		$is_act = ( $i === $active );
		$off    = $day['working'] ? '' : ' is-off';
		$sel    = $is_act ? ' is-selected' : '';
		$daysel .= '<button type="button" class="wpddbp-sch-minipill' . $off . $sel . '" data-day-index="' . (int) $i . '" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '" role="tab" aria-selected="' . ( $is_act ? 'true' : 'false' ) . '">'
			. '<span class="wpddbp-sch-dname">' . esc_html( $day['short'] ) . '</span></button>';
		$panels .= '<div class="wpddbp-sch-panel' . ( $is_act ? ' is-active' : '' ) . '" data-day-panel="' . (int) $i . '" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '"' . ( $is_act ? '' : ' hidden' ) . '>'
			. '<header class="wpddbp-sch-panelhdr"><h3 class="wpddbp-sch-panelday">' . esc_html( $day['full'] ) . '</h3></header>'
			. wpddbp_schedule_day_panel_body( $doctor_id, $day, $weekly, $attrs )
			. '</div>';
	}
	$feenote = ( ! empty( $attrs['showFee'] ) && $d['fee'] > 0 )
		? '<div class="wpddbp-sch-feenote">' . esc_html__( 'From', 'doc-booker-pro' ) . ' <b>' . esc_html( wpddbp_format_fee( $d['fee'] ) ) . '</b></div>'
		: '';
	$booking = '<div class="wpddbp-sch-book"><div class="wpddbp-sch-minidays" role="tablist">' . $daysel . '</div><div class="wpddbp-sch-panels">' . $panels . '</div>' . $feenote . '</div>';

	return '<div class="wpddbp-sch-spot wpddbp-sch-surface">' . $stage . $booking . '</div>';
}

/** TIMELINE — a vertical rail; one chapter per canonical day. */
function wpddbp_schedule_render_timeline( $doctor_id, $weekly, $attrs ) {
	$multi = count( $weekly['clinics'] ) > 1;
	$out   = '';
	foreach ( $weekly['order'] as $dow ) {
		$day = $weekly['days'][ $dow ];
		if ( ! $day['working'] ) {
			$out .= '<article class="wpddbp-sch-chapter is-off" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '">'
				. '<span class="wpddbp-sch-chip is-off" aria-hidden="true">' . esc_html( $day['short'] ) . '</span>'
				. '<div class="wpddbp-sch-chcard is-off"><span class="wpddbp-sch-chday">' . esc_html( $day['full'] ) . '</span><span class="wpddbp-sch-chtag">' . esc_html( $attrs['closedText'] ?? __( 'Closed', 'doc-booker-pro' ) ) . '</span></div>'
				. '</article>';
			continue;
		}

		$names = array();
		foreach ( $day['clinics'] as $c ) {
			if ( '' !== $c['name'] ) {
				$names[] = $c['name'];
			}
		}
		$ctx_bits = array();
		if ( ! empty( $names ) ) {
			$ctx_bits[] = esc_html( implode( ' → ', array_unique( $names ) ) );
		}
		if ( '' !== $day['range'] ) {
			$ctx_bits[] = esc_html( $day['range'] );
		}
		if ( ! empty( $attrs['showDayCount'] ) ) {
			/* translators: %d: number of bookable slots. */
			$ctx_bits[] = '<b>' . esc_html( sprintf( _n( '%d slot', '%d slots', $day['bookable_cnt'], 'doc-booker-pro' ), $day['bookable_cnt'] ) ) . '</b>';
		}
		$ctx = ! empty( $ctx_bits ) ? '<div class="wpddbp-sch-chctx">' . implode( '<span class="wpddbp-sch-pipe">·</span>', $ctx_bits ) . '</div>' : '';

		// Slots: clinic-split groups (each clinic on its own line when multi).
		$slots = '';
		foreach ( $day['clinics'] as $clinic ) {
			if ( $multi && '' !== $clinic['name'] ) {
				$slots .= '<div class="wpddbp-sch-csplit" style="--sch-clinic:' . esc_attr( wpddbp_schedule_clinic_var( $clinic['color_index'] ) ) . '"><span class="wpddbp-sch-cdot" aria-hidden="true"></span>' . esc_html( $clinic['name'] ) . '</div>';
			}
			$slots .= wpddbp_schedule_clinic_group( $doctor_id, $clinic, $clinic['timings'], $attrs, false, $multi );
		}

		$out .= '<article class="wpddbp-sch-chapter" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '">'
			. '<span class="wpddbp-sch-chip" aria-hidden="true">' . esc_html( $day['short'] ) . '</span>'
			. '<div class="wpddbp-sch-chcard"><header class="wpddbp-sch-chhead"><h3 class="wpddbp-sch-chname">' . esc_html( $day['full'] ) . '</h3>' . $ctx . '</header><div class="wpddbp-sch-chslots">' . $slots . '</div></div>'
			. '</article>';
	}
	return '<div class="wpddbp-sch-timeline">' . $out . '</div>';
}

/** COMPACT-LIST — dense rows (sidebar / inline) or a dark chip strip (footstrip). */
function wpddbp_schedule_render_compact( $doctor_id, $d, $weekly, $attrs ) {
	$density = in_array( ( $attrs['density'] ?? 'sidebar' ), array( 'sidebar', 'inline', 'footstrip' ), true ) ? $attrs['density'] : 'sidebar';
	$multi   = count( $weekly['clinics'] ) > 1;

	$avatar = ( ! empty( $attrs['showAvatar'] ) && '' !== $d['image'] )
		? '<span class="wpddbp-sch-cav"><img src="' . esc_url( $d['image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy"></span>'
		: '';

	if ( 'footstrip' === $density ) {
		$chips = '';
		foreach ( $weekly['order'] as $dow ) {
			$day = $weekly['days'][ $dow ];
			if ( ! $day['working'] ) {
				continue;
			}
			// First offered slot of the day → one bookable chip.
			$first = null;
			$cidx  = 0;
			foreach ( $day['clinics'] as $clinic ) {
				foreach ( $clinic['timings'] as $t ) {
					if ( ! empty( $t['bookable'] ) ) {
						$first = $t;
						$cidx  = $clinic['color_index'];
						break 2;
					}
				}
			}
			if ( null === $first ) {
				continue;
			}
			$chips .= '<span class="wpddbp-sch-fschip" style="--sch-clinic:' . esc_attr( wpddbp_schedule_clinic_var( $cidx ) ) . '"><span class="wpddbp-sch-cdot" aria-hidden="true"></span><span class="wpddbp-sch-fsday">' . esc_html( $day['short'] ) . '</span>'
				. wpddbp_schedule_slot( $doctor_id, $first, $attrs ) . '</span>';
		}
		$id  = '<div class="wpddbp-sch-fsid">' . $avatar . '<span class="wpddbp-sch-fsname">' . esc_html( $d['name'] ) . '</span></div>';
		$cta = wpddbp_render_doctor_book_button( $doctor_id, array( 'label' => $attrs['ctaText'] ?? __( 'Book an appointment', 'doc-booker-pro' ), 'class' => 'wpddbp-sch-cta' ) );
		return '<div class="wpddbp-sch-compact wpddbp-sch-compact--footstrip">' . $id . '<span class="wpddbp-sch-fslabel">' . esc_html( $attrs['eyebrowText'] ?? __( 'This week', 'doc-booker-pro' ) ) . '</span><div class="wpddbp-sch-fschips">' . $chips . '</div>' . $cta . '</div>';
	}

	// sidebar / inline rows.
	$rows = '';
	foreach ( $weekly['order'] as $dow ) {
		$day = $weekly['days'][ $dow ];
		if ( ! $day['working'] ) {
			continue;
		}
		foreach ( $day['clinics'] as $clinic ) {
			$slots = '';
			foreach ( $clinic['timings'] as $t ) {
				$slots .= wpddbp_schedule_slot( $doctor_id, $t, $attrs );
			}
			if ( '' === $slots ) {
				continue;
			}
			$cl = ( $multi && '' !== $clinic['name'] )
				? '<span class="wpddbp-sch-crowcl"><span class="wpddbp-sch-cdot" aria-hidden="true"></span>' . esc_html( $clinic['name'] ) . '</span>'
				: '';
			$rows .= '<div class="wpddbp-sch-crow" data-day-key="' . esc_attr( $day['key'] ) . '" data-dow="' . (int) $dow . '" style="--sch-clinic:' . esc_attr( wpddbp_schedule_clinic_var( $clinic['color_index'] ) ) . '">'
				. '<span class="wpddbp-sch-crowday">' . esc_html( $day['short'] ) . '</span>' . $cl
				. '<span class="wpddbp-sch-crowslots">' . $slots . '</span></div>';
		}
	}
	if ( '' === $rows ) {
		$rows = '<div class="wpddbp-sch-panelempty">' . esc_html( $attrs['emptyText'] ?? __( 'No availability.', 'doc-booker-pro' ) ) . '</div>';
	}

	$head = '';
	if ( ! empty( $attrs['showDoctorStrip'] ) ) {
		$sub  = array();
		if ( '' !== $d['dept_name'] ) {
			$sub[] = esc_html( $d['dept_name'] );
		}
		if ( ! empty( $attrs['showRating'] ) && $d['rating'] > 0 ) {
			$sub[] = wpddbp_doctors_star_svg() . esc_html( wpddbp_doctors_rating_value( $d['rating'] ) );
		}
		$head = '<div class="wpddbp-sch-chead">' . $avatar . '<span class="wpddbp-sch-cid"><span class="wpddbp-sch-cname">' . esc_html( $d['name'] ) . '</span>'
			. ( ! empty( $sub ) ? '<span class="wpddbp-sch-csub">' . implode( ' · ', $sub ) . '</span>' : '' ) . '</span></div>';
	}
	$fee = ( ! empty( $attrs['showFee'] ) && $d['fee'] > 0 )
		? '<div class="wpddbp-sch-cfee"><span>' . esc_html__( 'From', 'doc-booker-pro' ) . '</span><b>' . esc_html( wpddbp_format_fee( $d['fee'] ) ) . '</b></div>'
		: '';
	$cta = wpddbp_render_doctor_book_button( $doctor_id, array( 'label' => $attrs['ctaText'] ?? __( 'Book an appointment', 'doc-booker-pro' ), 'class' => 'wpddbp-sch-cta' ) );

	return '<div class="wpddbp-sch-compact wpddbp-sch-compact--' . esc_attr( $density ) . ' wpddbp-sch-surface">' . $head . '<div class="wpddbp-sch-clist">' . $rows . '</div>' . $fee . $cta . wpddbp_schedule_footer_link( $attrs ) . '</div>';
}

/* =============================================================================
 * Top-level render
 * ============================================================================= */

/**
 * Render the full Schedule widget for a resolved doctor (the `.wpddbp-sch` node,
 * carrying the cache-safe timezone data attrs view.js needs). render.php wraps it
 * in the `#id` element + adds the per-instance CSS hook.
 *
 * @param array  $attrs    Block attributes.
 * @param string $block_id Unique id (unused inside; kept for parity).
 * @return string
 */
function wpddbp_schedule_render( $attrs, $block_id = '' ) {
	$doctor_id = wpddbp_schedule_resolve_doctor_id( $attrs );

	if ( $doctor_id <= 0 || ! get_post( $doctor_id ) || 'wpddb_doctor' !== get_post_type( $doctor_id ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return '<div class="wpddbp-sch wpddbp-sch--empty">' . esc_html__( 'Doctor Schedule: choose a doctor in the block settings.', 'doc-booker-pro' ) . '</div>';
		}
		return '';
	}

	$d      = wpddbp_doctors_card_data( $doctor_id, array() );
	$weekly = wpddbp_schedule_get_weekly( $doctor_id, $attrs );
	$layout = in_array( ( $attrs['layoutType'] ?? 'week-grid' ), array( 'week-grid', 'day-pills', 'spotlight', 'timeline', 'compact-list' ), true ) ? $attrs['layoutType'] : 'week-grid';

	// Cache-safe timezone attrs (no server "now" embedded; view.js computes today).
	$tz       = wp_timezone();
	$tz_name  = $tz->getName();
	$is_iana  = ( false === strpos( $tz_name, '+' ) && false === strpos( $tz_name, '-' ) && false !== strpos( $tz_name, '/' ) );
	$offset   = (int) round( $tz->getOffset( new DateTimeImmutable( 'now', $tz ) ) / 60 );

	$data_attrs = ' data-sch="1"'
		. ' data-tz="' . esc_attr( $is_iana ? $tz_name : '' ) . '"'
		. ' data-tz-offset="' . (int) $offset . '"'
		. ' data-highlight-today="' . ( ! empty( $attrs['highlightToday'] ) ? '1' : '0' ) . '"'
		. ' data-auto-today="' . ( ! empty( $attrs['autoSelectToday'] ) ? '1' : '0' ) . '"';

	// Empty schedule → soft notice (editors) / nothing (visitors), still inside .wpddbp-sch for styling.
	if ( ! $weekly['has_any'] && 'compact-list' !== $layout ) {
		$strip = wpddbp_schedule_doctor_strip( $d, $weekly, $attrs );
		$body  = '<div class="wpddbp-sch-emptybody">' . esc_html( $attrs['emptyText'] ?? __( "This doctor's weekly schedule isn't available yet.", 'doc-booker-pro' ) ) . '</div>';
		return '<div class="wpddbp-sch wpddbp-sch--' . esc_attr( $layout ) . '"' . $data_attrs . '>' . wpddbp_schedule_header( $attrs ) . $strip . $body . '</div>';
	}

	switch ( $layout ) {
		case 'day-pills':
			$body = wpddbp_schedule_header( $attrs ) . wpddbp_schedule_doctor_strip( $d, $weekly, $attrs ) . wpddbp_schedule_render_day_pills( $doctor_id, $weekly, $attrs ) . wpddbp_schedule_footer_link( $attrs );
			break;
		case 'spotlight':
			$body = wpddbp_schedule_header( $attrs ) . wpddbp_schedule_render_spotlight( $doctor_id, $d, $weekly, $attrs );
			break;
		case 'timeline':
			$body = wpddbp_schedule_header( $attrs ) . wpddbp_schedule_doctor_strip( $d, $weekly, $attrs ) . wpddbp_schedule_render_timeline( $doctor_id, $weekly, $attrs ) . wpddbp_schedule_footer_link( $attrs );
			break;
		case 'compact-list':
			$body = wpddbp_schedule_render_compact( $doctor_id, $d, $weekly, $attrs );
			break;
		case 'week-grid':
		default:
			$body = wpddbp_schedule_header( $attrs ) . wpddbp_schedule_doctor_strip( $d, $weekly, $attrs ) . wpddbp_schedule_render_week_grid( $doctor_id, $weekly, $attrs ) . wpddbp_schedule_footer_link( $attrs );
			break;
	}

	return '<div class="wpddbp-sch wpddbp-sch--' . esc_attr( $layout ) . '"' . $data_attrs . '>' . $body . '</div>';
}
