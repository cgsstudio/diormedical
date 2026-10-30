<?php
/**
 * Booking Form block — server render (Phase 5).
 *
 * Emits the React mount node + a JSON config. The `view.js` app renders the
 * mockup-matching wizard and drives the EXISTING DocBooker booking flow via the
 * unchanged REST endpoints (departments / doctors / schedule / availability /
 * bookings) and the single active payment gateway (Stripe or Razorpay) via
 * `/payment-config`. No backend is modified by this block.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = in_array( ( $attributes['layoutType'] ?? 'wizard' ), array( 'wizard', 'inline' ), true ) ? $attributes['layoutType'] : 'wizard';
$align    = in_array( ( $attributes['align'] ?? '' ), array( 'wide', 'full' ), true ) ? $attributes['align'] : '';

// ---- resolve preselect defaults (+ optional ?clinic= / ?dept= / ?doctor= override) ----
$def_clinic = (int) ( $attributes['defaultClinicId'] ?? 0 );
$def_dept   = (int) ( $attributes['defaultDepartmentId'] ?? 0 );
$def_doctor = (int) ( $attributes['defaultDoctorId'] ?? 0 );

$def_date = '';

if ( ! empty( $attributes['allowUrlParamOverride'] ) ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['clinic'] ) ) { $def_clinic = (int) $_GET['clinic']; }
	if ( isset( $_GET['dept'] ) )   { $def_dept   = (int) $_GET['dept']; }
	if ( isset( $_GET['doctor'] ) ) { $def_doctor = (int) $_GET['doctor']; }
	// `date` lets an upstream picker (e.g. the hero booking card) preselect the day so
	// the visitor isn't asked for it twice. Strictly validated: anything that isn't a
	// real YYYY-MM-DD is ignored rather than handed to the calendar.
	if ( isset( $_GET['date'] ) ) {
		$raw = sanitize_text_field( wp_unslash( $_GET['date'] ) );
		$dt  = DateTime::createFromFormat( 'Y-m-d', $raw );
		if ( $dt && $dt->format( 'Y-m-d' ) === $raw ) {
			$def_date = $raw;
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
// On a single-doctor page with no explicit doctor, default to the current doctor.
if ( ! $def_doctor ) {
	$cur = get_the_ID();
	if ( $cur && 'wpddb_doctor' === get_post_type( $cur ) ) {
		$def_doctor = (int) $cur;
	}
}

$config = array(
	'apiUrl'   => esc_url_raw( rest_url() ),
	'nonce'    => wp_create_nonce( 'wp_rest' ),
	'hasPro'   => function_exists( 'wpddbp' ),
	'layout'   => $layout,
	'defaults' => array(
		'clinicId'       => $def_clinic,
		'departmentId'   => $def_dept,
		'doctorId'       => $def_doctor,
		'date'           => $def_date,
		'clinicName'     => $def_clinic ? get_the_title( $def_clinic ) : '',
		'doctorName'     => $def_doctor ? get_the_title( $def_doctor ) : '',
		'departmentName' => $def_dept && ! is_wp_error( get_term( $def_dept ) ) && get_term( $def_dept ) ? get_term( $def_dept )->name : '',
	),
	'steps'    => array(
		'clinic'     => ! empty( $attributes['enableClinicStep'] ),
		'department' => ! empty( $attributes['enableDepartmentStep'] ),
		'doctor'     => ! empty( $attributes['enableDoctorStep'] ),
	),
	'hoursFormat'          => ( '24' === ( $attributes['hoursFormat'] ?? '12' ) ) ? '24' : '12',
	'askNotes'             => ! empty( $attributes['askNotes'] ),
	'allowPayAtAppointment' => ! empty( $attributes['allowPayAtAppointment'] ),
	'showTrustStrip'       => ! empty( $attributes['showTrustStrip'] ),
	'showWhatsNext'        => ! empty( $attributes['showWhatsNext'] ),
	'text'                 => array(
		'eyebrow'              => (string) ( $attributes['eyebrowText'] ?? '' ),
		'title'                => (string) ( $attributes['titleText'] ?? '' ),
		'lede'                 => (string) ( $attributes['ledeText'] ?? '' ),
		'confirmationHeadline' => (string) ( $attributes['confirmationHeadline'] ?? '' ),
		'confirmationMessage'  => (string) ( $attributes['confirmationMessage'] ?? '' ),
		'requestButtonLabel'   => (string) ( $attributes['requestButtonLabel'] ?? '' ),
		'payAtAppointmentLabel' => (string) ( $attributes['payAtAppointmentLabel'] ?? '' ),
	),
);

$classes = 'wpddbp-bf-wrap wpddbp-bf-wrap--' . esc_attr( $layout );
if ( '' !== $align ) {
	$classes .= ' align' . esc_attr( $align );
}

echo '<div id="' . $block_id . '" class="' . $classes . '">'
	. '<div class="wpddbp-bf" data-wpddbp-bf-config="' . esc_attr( wp_json_encode( $config ) ) . '">'
	. '<div class="wpddbp-bf-loading" aria-busy="true">' . esc_html__( 'Loading booking…', 'doc-booker-pro' ) . '</div>'
	. '</div>'
	. '</div>';
