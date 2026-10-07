<?php
/**
 * Per-doctor booking button + fee formatting helpers.
 *
 * Consumed by the data-driven booking-capable blocks (Doctors, Quick Book,
 * Doctor Schedule, Booking Form — Phases 3 & 5). Phase 1 ships the helper so
 * those phases can call it without re-plumbing; it renders nothing on its own.
 *
 * Decision chain (architecture §12.2):
 *   1. Payment ENABLED (wpddb_global_settings.online_booking_payment === 'on')
 *      AND the doctor has wpddb_doctor_booking_fee > 0
 *        → payment button carrying data-gateway / data-doctor-id / data-booking-fee
 *          so block JS can mount the active gateway (Stripe Elements / Razorpay modal).
 *   2. Otherwise (payment off, or no fee)
 *        → fallback "request" link to the doctor's single page #booking anchor.
 *
 * Standalone rule (architecture §3): namespaced wpddbp_, never imported from
 * the reference blocks plugin. Reads the free/Pro settings via the real
 * \WpDreamers\WPDDB\Controllers\WpddbOptions accessor (guarded with class_exists).
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpddbp_get_global_setting' ) ) {
	/**
	 * Read a key from the shared `wpddb_global_settings` option, via the free
	 * plugin's accessor when available, falling back to a raw get_option().
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default when missing/empty.
	 * @return mixed
	 */
	function wpddbp_get_global_setting( $key, $default = '' ) {
		if ( class_exists( '\WpDreamers\WPDDB\Controllers\WpddbOptions' ) ) {
			$val = \WpDreamers\WPDDB\Controllers\WpddbOptions::get_option( $key, 'wpddb_global_settings', $default );
			return ( '' === $val || null === $val ) ? $default : $val;
		}
		$settings = get_option( 'wpddb_global_settings', array() );
		return ( is_array( $settings ) && isset( $settings[ $key ] ) && '' !== $settings[ $key ] )
			? $settings[ $key ]
			: $default;
	}
}

if ( ! function_exists( 'wpddbp_payment_enabled' ) ) {
	/**
	 * Whether online booking payment is switched on in DocBooker settings.
	 *
	 * @return bool
	 */
	function wpddbp_payment_enabled() {
		return 'on' === wpddbp_get_global_setting( 'online_booking_payment', '' );
	}
}

if ( ! function_exists( 'wpddbp_active_gateway' ) ) {
	/**
	 * The active payment gateway slug ('stripe' | 'razorpay').
	 *
	 * @return string
	 */
	function wpddbp_active_gateway() {
		$g = wpddbp_get_global_setting( 'payment_gateway', 'stripe' );
		return in_array( $g, array( 'stripe', 'razorpay' ), true ) ? $g : 'stripe';
	}
}

if ( ! function_exists( 'wpddbp_payment_gateway_configured' ) ) {
	/**
	 * Whether the active gateway has the credentials it needs for the current mode.
	 *
	 * Empty keys ⇒ the card form can't initialise (Stripe.js throws on an empty
	 * publishable key), so callers should fall back to request-only rather than
	 * present a broken payment step.
	 *
	 * @return bool
	 */
	function wpddbp_payment_gateway_configured() {
		if ( 'razorpay' === wpddbp_active_gateway() ) {
			$mode = wpddbp_get_global_setting( 'razorpay_mode', 'test' );
			$id   = 'live' === $mode ? 'razorpay_live_key_id'     : 'razorpay_test_key_id';
			$sec  = 'live' === $mode ? 'razorpay_live_secret_key' : 'razorpay_test_secret_key';
			return '' !== wpddbp_get_global_setting( $id, '' ) && '' !== wpddbp_get_global_setting( $sec, '' );
		}
		$mode = wpddbp_get_global_setting( 'stripe_mode', 'test' );
		$pub  = 'live' === $mode ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key';
		$sec  = 'live' === $mode ? 'stripe_live_secret_key'      : 'stripe_test_secret_key';
		return '' !== wpddbp_get_global_setting( $pub, '' ) && '' !== wpddbp_get_global_setting( $sec, '' );
	}
}

if ( ! function_exists( 'wpddbp_doctor_booking_fee' ) ) {
	/**
	 * The configured booking fee for a doctor (0 when unset).
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return float
	 */
	function wpddbp_doctor_booking_fee( $doctor_id ) {
		return (float) get_post_meta( (int) $doctor_id, 'wpddb_doctor_booking_fee', true );
	}
}

if ( ! function_exists( 'wpddbp_format_fee' ) ) {
	/**
	 * Format a monetary amount using the site's configured currency + position.
	 * Never hardcodes "$" (architecture §12.5). Phase 5 refines decimal counts
	 * via the free plugin's Helper::currency_list(); this compact map covers the
	 * common cases until then.
	 *
	 * @param float|int|string $amount Amount.
	 * @return string
	 */
	function wpddbp_format_fee( $amount ) {
		$currency = wpddbp_get_global_setting( 'currency', 'USD' );
		$position = wpddbp_get_global_setting( 'currency_position', 'left' );

		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'INR' => '₹',
			'AUD' => '$',
			'CAD' => '$',
			'JPY' => '¥',
			'BDT' => '৳',
		);
		$symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : ( $currency . ' ' );

		// Currencies conventionally shown without minor units.
		$zero_decimal = in_array( $currency, array( 'JPY', 'INR' ), true );
		$number       = number_format_i18n( (float) $amount, $zero_decimal ? 0 : 2 );

		return ( 'right' === $position ) ? ( $number . ' ' . $symbol ) : ( $symbol . $number );
	}
}

if ( ! function_exists( 'wpddbp_format_fee_parts' ) ) {
	/**
	 * Like wpddbp_format_fee() but returns the symbol + number + position
	 * separately, so a caller (e.g. the Doctor Fee Badge) can style the
	 * currency symbol independently of the amount. Same currency/decimal
	 * conventions as wpddbp_format_fee().
	 *
	 * @param float|int|string $amount Amount.
	 * @return array { symbol, number, position }
	 */
	function wpddbp_format_fee_parts( $amount ) {
		$currency = wpddbp_get_global_setting( 'currency', 'USD' );
		$position = wpddbp_get_global_setting( 'currency_position', 'left' );

		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'INR' => '₹',
			'AUD' => '$',
			'CAD' => '$',
			'JPY' => '¥',
			'BDT' => '৳',
		);
		$symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : ( $currency . ' ' );

		$zero_decimal = in_array( $currency, array( 'JPY', 'INR' ), true );
		$number       = number_format_i18n( (float) $amount, $zero_decimal ? 0 : 2 );

		return array(
			'symbol'   => $symbol,
			'number'   => $number,
			'position' => ( 'right' === $position ) ? 'right' : 'left',
		);
	}
}

if ( ! function_exists( 'wpddbp_render_doctor_book_button' ) ) {
	/**
	 * Render the booking CTA for a single doctor.
	 *
	 * @param int   $doctor_id Doctor post ID.
	 * @param array $args {
	 *     @type string $label     Button label. Default "Book Now".
	 *     @type string $class     Extra CSS classes.
	 *     @type bool   $show_fee  Append the formatted fee to the label. Default false.
	 * }
	 * @return string Button HTML ('' when the doctor doesn't exist).
	 */
	function wpddbp_render_doctor_book_button( $doctor_id, $args = array() ) {
		$doctor_id = (int) $doctor_id;
		if ( $doctor_id <= 0 || ! get_post( $doctor_id ) ) {
			return '';
		}

		$defaults = array(
			'label'    => __( 'Book Now', 'doc-booker-pro' ),
			'class'    => '',
			'show_fee' => false,
			// Extra data-* for callers that know more than the doctor (e.g. a schedule slot
			// knows its day/time/clinic). Emitted verbatim as data-{key}="{value}".
			'data'     => array(),
		);
		$args = wp_parse_args( $args, $defaults );

		$data_attr = '';
		foreach ( (array) $args['data'] as $dk => $dv ) {
			if ( '' === $dv || null === $dv ) {
				continue;
			}
			$data_attr .= ' data-' . esc_attr( $dk ) . '="' . esc_attr( $dv ) . '"';
		}

		$fee         = wpddbp_doctor_booking_fee( $doctor_id );
		$has_payment = wpddbp_payment_enabled() && wpddbp_payment_gateway_configured() && $fee > 0;
		$doctor_url  = get_permalink( $doctor_id );
		$href        = esc_url( ( $doctor_url ? $doctor_url : '' ) . '#booking' );

		$label_html = esc_html( $args['label'] );
		if ( $args['show_fee'] && $fee > 0 ) {
			$label_html .= ' <span class="wpddbp-fee">' . esc_html( wpddbp_format_fee( $fee ) ) . '</span>';
		}

		if ( $has_payment ) {
			$gateway = wpddbp_active_gateway();
			$cls     = trim( 'wpddbp-doctor-book-btn wpddbp-with-payment ' . $args['class'] );
			return sprintf(
				'<a href="%1$s" class="%2$s" data-gateway="%3$s" data-doctor-id="%4$d" data-booking-fee="%5$s"%7$s>%6$s</a>',
				$href,
				esc_attr( $cls ),
				esc_attr( $gateway ),
				$doctor_id,
				esc_attr( (string) $fee ),
				$label_html, // already escaped above.
				$data_attr // each value escaped above.
			);
		}

		// Fallback: capture as a request on the doctor's single page.
		$cls = trim( 'wpddbp-doctor-book-btn wpddbp-fallback ' . $args['class'] );
		return sprintf(
			'<a href="%1$s" class="%2$s" data-doctor-id="%3$d"%5$s>%4$s</a>',
			$href,
			esc_attr( $cls ),
			$doctor_id,
			$label_html, // already escaped above.
			$data_attr // each value escaped above.
		);
	}
}
