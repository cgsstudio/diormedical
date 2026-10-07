<?php
/**
 * Doctor Schedule block — server render (Phase 5).
 *
 * Resolves a single doctor (picked / current page / ?doctor= URL override) and
 * renders one of 5 weekly-timetable layouts (week-grid / day-pills / spotlight /
 * timeline / compact-list) from the real `wpddb_doctor_schedule` meta. Each offered
 * slot is the shared booking-button helper → the live Pro booking flow. Per-instance
 * CSS is injected on singular views by the blocks bootstrap.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/doctor-schedule-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = in_array( ( $attributes['layoutType'] ?? 'week-grid' ), array( 'week-grid', 'day-pills', 'spotlight', 'timeline', 'compact-list' ), true ) ? $attributes['layoutType'] : 'week-grid';
$align    = in_array( ( $attributes['align'] ?? '' ), array( 'wide', 'full' ), true ) ? $attributes['align'] : '';

$widget = wpddbp_schedule_render( $attributes, $block_id );

if ( '' === $widget ) {
	return;
}

$classes = 'wpddbp-sch-wrap wpddbp-sch-wrap--' . esc_attr( $layout );
if ( 'compact-list' === $layout ) {
	$density  = in_array( ( $attributes['density'] ?? 'sidebar' ), array( 'sidebar', 'inline', 'footstrip' ), true ) ? $attributes['density'] : 'sidebar';
	$classes .= ' wpddbp-sch-wrap--density-' . esc_attr( $density );
}
if ( '' !== $align ) {
	$classes .= ' align' . esc_attr( $align );
}

// Booking modal for slot clicks. Each slot carries its own day/time/clinic, so a click
// can open the booking form already on that slot instead of navigating to the doctor's
// page and losing the choice. view.js only takes over when window.wpddbpMountBookingForm
// exists (i.e. a booking-form block is on the page); otherwise the slot's href stands.
$sch_cfg = wp_json_encode( array(
	'apiUrl'   => esc_url_raw( rest_url() ),
	'nonce'    => wp_create_nonce( 'wp_rest' ),
	'hasPro'   => function_exists( 'wpddbp' ),
	'doctorId' => (int) $doctor_id,
) );

$modal_el = '<div class="wpddbp-sch-modal" data-sch-modal hidden aria-hidden="true">'
	. '<div class="wpddbp-sch-modal-backdrop" data-sch-modal-close></div>'
	. '<div class="wpddbp-sch-modal-card" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Book an appointment', 'doc-booker-pro' ) . '">'
	. '<div class="wpddbp-sch-modal-head">'
	. '<span class="wpddbp-sch-modal-title">' . esc_html__( 'Book an appointment', 'doc-booker-pro' ) . '</span>'
	. '<button type="button" class="wpddbp-sch-modal-x" data-sch-modal-close aria-label="' . esc_attr__( 'Close', 'doc-booker-pro' ) . '">&times;</button>'
	. '</div>'
	. '<div class="wpddbp-sch-modal-body"></div>'
	. '</div></div>';

echo '<div id="' . $block_id . '" class="' . $classes . '" data-sch-config="' . esc_attr( $sch_cfg ) . '">'
	. $widget // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helpers.
	. $modal_el // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() above.
	. '</div>';
