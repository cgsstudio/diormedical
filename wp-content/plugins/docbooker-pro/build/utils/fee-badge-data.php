<?php
/**
 * Doctor Fee Badge block — data + renderer (Phase 3).
 *
 * A small, repeatable badge that surfaces a doctor's real booking fee
 * (`wpddb_doctor_booking_fee`) anywhere on the page, in 3 variants (pill /
 * starburst / flat) × 3 sizes. Currency-aware via the shared standalone fee
 * helpers (wpddbp_doctor_booking_fee + wpddbp_format_fee_parts). Standalone
 * rule (§3): namespaced `wpddbp_feebadge_*`, no reference-plugin imports.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/doctors-book-button.php';

/** 32-point starburst polygon (lifted from the approved mockup). */
function wpddbp_feebadge_burst_points() {
	return '50,2 56,14 68,8 70,22 84,22 80,35 94,40 86,52 96,62 84,68 88,82 74,82 74,96 62,90 56,98 50,86 44,98 38,90 26,96 26,82 12,82 16,68 4,62 14,52 6,40 20,35 16,22 30,22 32,8 44,14';
}

/** Resolve the fee + the doctor's booking link from the chosen source. */
function wpddbp_feebadge_resolve( $attrs ) {
	$source    = $attrs['source'] ?? 'specific';
	$fee       = 0.0;
	$link      = '';
	$doctor_id = 0;

	if ( 'manual' === $source ) {
		$fee = (float) ( $attrs['manualAmount'] ?? 0 );
	} else {
		if ( 'current' === $source ) {
			$id = get_the_ID();
			if ( $id && 'wpddb_doctor' === get_post_type( $id ) ) {
				$doctor_id = (int) $id;
			}
		} else { // specific (fall back to the current doctor page when none picked)
			$doctor_id = (int) ( $attrs['doctorId'] ?? 0 );
			if ( ! $doctor_id ) {
				$id = get_the_ID();
				if ( $id && 'wpddb_doctor' === get_post_type( $id ) ) {
					$doctor_id = (int) $id;
				}
			}
		}
		if ( $doctor_id ) {
			$fee  = wpddbp_doctor_booking_fee( $doctor_id );
			$link = (string) get_permalink( $doctor_id );
		}
	}

	return array( 'fee' => $fee, 'link' => $link, 'doctor_id' => $doctor_id );
}

/** The amount markup: currency symbol (styled apart) + number, or the on-request text. */
function wpddbp_feebadge_amount_html( $fee, $attrs ) {
	if ( $fee > 0 ) {
		$p   = wpddbp_format_fee_parts( $fee );
		$cur = '<span class="wpddbp-feebadge-cur">' . esc_html( trim( $p['symbol'] ) ) . '</span>';
		$num = esc_html( $p['number'] );
		$in  = ( 'right' === $p['position'] ) ? ( $num . ' ' . $cur ) : ( $cur . $num );
		return '<span class="wpddbp-feebadge-amt">' . $in . '</span>';
	}
	return '<span class="wpddbp-feebadge-amt wpddbp-feebadge-amt--req">' . esc_html( $attrs['onRequestText'] ?? __( 'On request', 'doc-booker-pro' ) ) . '</span>';
}

/**
 * Render the fee badge.
 *
 * @param array  $attrs    Block attributes.
 * @param string $block_id Unique id (for the starburst gradient id).
 * @return string
 */
function wpddbp_feebadge_render( $attrs, $block_id ) {
	$style = in_array( ( $attrs['badgeStyle'] ?? 'pill' ), array( 'pill', 'starburst', 'flat' ), true ) ? $attrs['badgeStyle'] : 'pill';
	$size  = in_array( ( $attrs['size'] ?? 'md' ), array( 'sm', 'md', 'lg' ), true ) ? $attrs['size'] : 'md';

	$r   = wpddbp_feebadge_resolve( $attrs );
	$fee = $r['fee'];
	$amt = wpddbp_feebadge_amount_html( $fee, $attrs );

	// "From" label only reads right when there's an amount.
	$from = ( ! empty( $attrs['showFrom'] ) && $fee > 0 )
		? '<span class="wpddbp-feebadge-from">' . esc_html( $attrs['fromLabel'] ?? __( 'From', 'doc-booker-pro' ) ) . '</span>'
		: '';

	$base_cls = 'wpddbp-feebadge wpddbp-feebadge--' . $style . ' wpddbp-feebadge--' . $size;

	if ( 'starburst' === $style ) {
		$grad_id = 'wpddbp-burst-' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $block_id );
		$c1      = ! empty( $attrs['burstColor1'] ) ? $attrs['burstColor1'] : '#b87a45';
		$c2      = ! empty( $attrs['burstColor2'] ) ? $attrs['burstColor2'] : '#7e4a23';
		$shape   = '<svg class="wpddbp-feebadge-shape" viewBox="0 0 100 100" aria-hidden="true">'
			. '<defs><linearGradient id="' . esc_attr( $grad_id ) . '" x1="0" y1="0" x2="1" y2="1">'
			. '<stop offset="0" stop-color="' . esc_attr( $c1 ) . '"/><stop offset="1" stop-color="' . esc_attr( $c2 ) . '"/>'
			. '</linearGradient></defs>'
			. '<polygon fill="url(#' . esc_attr( $grad_id ) . ')" points="' . esc_attr( wpddbp_feebadge_burst_points() ) . '"/></svg>';
		$content = $shape . '<span class="wpddbp-feebadge-inner">' . $from . $amt . '</span>';
	} elseif ( 'flat' === $style ) {
		$content = $from . $amt;
	} else { // pill
		$icon = ! empty( $attrs['showIcon'] )
			? '<span class="wpddbp-feebadge-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 12l-6-6H6a2 2 0 0 0-2 2v6l8 8 8-8z"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/></svg></span>'
			: '';
		$content = $icon . $from . $amt;
	}

	$link = $r['link'];
	if ( ! empty( $attrs['linkToBooking'] ) && '' !== $link ) {
		return '<a class="' . esc_attr( $base_cls ) . '" href="' . esc_url( $link . '#booking' ) . '">' . $content . '</a>';
	}
	return '<span class="' . esc_attr( $base_cls ) . '">' . $content . '</span>';
}
