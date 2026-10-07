<?php
/**
 * Booking confirmation SMS — listens to the free plugin's
 * `wpddb_booking_created` action, resolves entity names from the IDs in the
 * payload, substitutes them into the admin-configured
 * booking_message_template, and dispatches via Sms::send_message().
 */

namespace WpDreamers\WPDDBP\Controllers\PatientPortal;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class BookingSms {
	use SingletonTrait;

	public function __construct() {
		add_action( 'wpddb_booking_created', [ $this, 'on_booking_created' ], 20, 1 );
	}

	public function on_booking_created( $data ) {
		if ( ! is_array( $data ) || empty( $data['patient_phone'] ) ) {
			return;
		}
		$settings = get_option( 'wpddb_sms_settings', [] );
		if ( empty( $settings['enabled'] ) ) {
			return; // Master toggle off — nothing to do.
		}

		$template = (string) ( $settings['booking_message_template'] ?? '' );
		if ( trim( $template ) === '' ) {
			return; // Admin cleared the template — treat as opt-out.
		}

		// Resolve display names from IDs so the message reads naturally.
		$doctor_id     = (int) ( $data['doctor_id'] ?? 0 );
		$clinic_id     = (int) ( $data['clinic_id'] ?? 0 );
		$doctor_name   = $doctor_id ? get_the_title( $doctor_id ) : '';
		$clinic_name   = $clinic_id ? get_the_title( $clinic_id ) : '';

		// Best-effort clinic address from the free plugin's per-doctor clinics_info
		// meta (which is where Stripe/Razorpay also reads address from). If empty,
		// fall back to a clinic CPT meta key, finally to ''.
		$clinic_address = '';
		if ( $doctor_id && $clinic_id ) {
			$clinics_info = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );
			if ( is_array( $clinics_info ) && isset( $clinics_info[ $clinic_id ]['address'] ) ) {
				$clinic_address = (string) $clinics_info[ $clinic_id ]['address'];
			}
		}
		if ( $clinic_address === '' && $clinic_id ) {
			$alt = get_post_meta( $clinic_id, 'wpddb_clinic_address', true );
			if ( is_string( $alt ) && $alt !== '' ) {
				$clinic_address = $alt;
			}
		}

		// Compose a friendly date/time string. Honor the WordPress site timezone.
		$human_time = $this->format_when( $data['booking_date'] ?? null, $data['day'] ?? '', $data['time'] ?? '' );

		$body = strtr( $template, [
			'{{patient}}'        => (string) ( $data['patient_name'] ?? '' ),
			'{{doctor}}'         => $doctor_name,
			'{{time}}'           => $human_time,
			'{{order_code}}'     => (string) ( $data['booking_id'] ?? '' ),
			'{{clinic}}'         => $clinic_name,
			'{{clinic_address}}' => $clinic_address,
		] );

		Sms::send_message( (string) $data['patient_phone'], $body, [ 'context' => 'booking' ] );
	}

	/**
	 * "15 May 2026 at 2:00 PM" for date-based bookings, or "Monday 2:00 PM"
	 * for legacy weekday-only bookings.
	 */
	private function format_when( $booking_date, $day, $time ) {
		$time = (string) $time;
		if ( ! empty( $booking_date ) && strtotime( (string) $booking_date ) ) {
			$pretty_date = wp_date( 'j M Y', strtotime( $booking_date ) );
			return $pretty_date . ' at ' . $time;
		}
		return trim( ucfirst( (string) $day ) . ' ' . $time );
	}
}
