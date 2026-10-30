<?php
namespace WpDreamers\WPDDBP\Controllers\Payments;

use Razorpay\Api\Api;
use WP_Error;
use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDBP\Controllers\Helper\Helper;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The one place that decides what a booking costs and whether it was paid.
 *
 * The browser only ever says which doctor and which payment it used. The price
 * comes from the doctor's fee, and a payment counts only once the gateway itself
 * confirms it — the right amount, in the right currency, for the right doctor,
 * and not already spent on another booking.
 */
class BookingPayment {

	use SingletonTrait;

	/** How long a verified Razorpay payment waits for its booking. */
	const RAZORPAY_TTL = 2 * HOUR_IN_SECONDS;

	public function __construct() {
		add_filter( 'wpddb_booking_payment', [ $this, 'verify_booking_payment' ], 10, 3 );
	}

	/**
	 * The doctor's booking fee in display units; 0 when none is set.
	 *
	 * @param int $doctor_id
	 * @return float
	 */
	public static function doctor_fee( $doctor_id ) {
		return max( 0, (float) get_post_meta( absint( $doctor_id ), 'wpddb_doctor_booking_fee', true ) );
	}

	/**
	 * The store currency, upper case.
	 *
	 * @return string
	 */
	public static function currency() {
		return strtoupper( WpddbOptions::get_option( 'currency', 'wpddb_global_settings' ) ?: 'USD' );
	}

	/**
	 * An amount in the currency's smallest unit (cents, paise; yen stay yen).
	 *
	 * @param float  $amount
	 * @param string $currency
	 * @return int
	 */
	public static function to_minor( $amount, $currency ) {
		$currencies = Helper::currency_list();
		$decimals   = isset( $currencies[ $currency ]['decimals'] ) ? (int) $currencies[ $currency ]['decimals'] : 2;

		return (int) round( (float) $amount * pow( 10, $decimals ) );
	}

	/**
	 * The gateway in use, or '' when online payment is off or its keys are missing.
	 *
	 * @return string 'stripe', 'razorpay' or ''.
	 */
	public static function active_gateway() {
		$on = ( WpddbOptions::get_option( 'online_booking_payment', 'wpddb_global_settings' ) ?: 'on' ) === 'on';
		if ( ! $on ) {
			return '';
		}

		$gateway = WpddbOptions::get_option( 'payment_gateway', 'wpddb_global_settings' ) ?: 'stripe';
		if ( 'stripe' === $gateway ) {
			$keys = self::stripe_keys();
			return $keys['publishable'] && $keys['secret'] ? 'stripe' : '';
		}
		if ( 'razorpay' === $gateway ) {
			$keys = self::razorpay_keys();
			return $keys['id'] && $keys['secret'] ? 'razorpay' : '';
		}

		return '';
	}

	/**
	 * @return array{publishable:string, secret:string}
	 */
	public static function stripe_keys() {
		$mode = WpddbOptions::get_option( 'stripe_mode', 'wpddb_global_settings' ) ?: 'test';

		return [
			'publishable' => (string) WpddbOptions::get_option( "stripe_{$mode}_publishable_key", 'wpddb_global_settings' ),
			'secret'      => (string) WpddbOptions::get_option( "stripe_{$mode}_secret_key", 'wpddb_global_settings' ),
		];
	}

	/**
	 * Settings save the secret as razorpay_<mode>_secret_key. Earlier releases read
	 * razorpay_<mode>_key_secret, which was never written, so that name is only a
	 * fallback.
	 *
	 * @return array{id:string, secret:string}
	 */
	public static function razorpay_keys() {
		$mode   = WpddbOptions::get_option( 'razorpay_mode', 'wpddb_global_settings' ) ?: 'test';
		$secret = WpddbOptions::get_option( "razorpay_{$mode}_secret_key", 'wpddb_global_settings' )
			?: WpddbOptions::get_option( "razorpay_{$mode}_key_secret", 'wpddb_global_settings' );

		return [
			'id'     => (string) WpddbOptions::get_option( "razorpay_{$mode}_key_id", 'wpddb_global_settings' ),
			'secret' => (string) $secret,
		];
	}

	/**
	 * Patients may choose to pay at the clinic instead of online.
	 *
	 * @return bool
	 */
	public static function pay_at_clinic_allowed() {
		return 'on' === WpddbOptions::get_option( 'pay_at_clinic', 'wpddb_global_settings' );
	}

	/**
	 * Whether booking this doctor has to be paid for online.
	 *
	 * @param int $doctor_id
	 * @return bool
	 */
	public static function is_required( $doctor_id ) {
		return '' !== self::active_gateway() && self::doctor_fee( $doctor_id ) > 0;
	}

	/**
	 * A configured Razorpay client, or null.
	 *
	 * @return Api|null
	 */
	public static function razorpay_api() {
		$keys = self::razorpay_keys();

		return $keys['id'] && $keys['secret'] ? new Api( $keys['id'], $keys['secret'] ) : null;
	}

	/**
	 * Remember a Razorpay payment whose signature and order checked out, so the
	 * booking that follows can claim it once.
	 *
	 * @param string $payment_id
	 * @param int    $doctor_id
	 * @param int    $amount_minor
	 * @param string $currency
	 */
	public static function remember_razorpay_payment( $payment_id, $doctor_id, $amount_minor, $currency ) {
		set_transient(
			'wpddbp_rzp_' . md5( $payment_id ),
			[
				'doctor_id' => (int) $doctor_id,
				'amount'    => (int) $amount_minor,
				'currency'  => strtoupper( $currency ),
			],
			self::RAZORPAY_TTL
		);
	}

	/**
	 * Free's wpddb_booking_payment filter: decide what the booking records as paid.
	 *
	 * @param array|WP_Error|null $result  Whatever an earlier listener decided.
	 * @param array               $params  The booking request.
	 * @param int                 $doctor_id
	 * @return array|WP_Error|null Payment fields, an error to refuse the booking, or null for "no payment".
	 */
	public function verify_booking_payment( $result, $params, $doctor_id ) {
		if ( null !== $result || ! self::is_required( $doctor_id ) ) {
			return $result;
		}

		// The patient chose to pay at the clinic, and the clinic allows it:
		// nothing to verify, and free records the booking as pay at the clinic.
		if ( 'clinic' === ( $params['paymentMethod'] ?? '' ) && self::pay_at_clinic_allowed() ) {
			return null;
		}

		$transaction_id = isset( $params['transactionId'] ) ? sanitize_text_field( (string) $params['transactionId'] ) : '';
		if ( '' === $transaction_id ) {
			return new WP_Error( 'payment_required', __( 'Please pay the booking fee to confirm this appointment.', 'doc-booker-pro' ), [ 'status' => 402 ] );
		}

		if ( self::transaction_used( $transaction_id ) ) {
			return new WP_Error( 'payment_used', __( 'This payment has already been used for another booking.', 'doc-booker-pro' ), [ 'status' => 409 ] );
		}

		$fee      = self::doctor_fee( $doctor_id );
		$currency = self::currency();
		$expected = self::to_minor( $fee, $currency );
		$gateway  = self::active_gateway();

		$ok = 'stripe' === $gateway
			? $this->stripe_payment_matches( $transaction_id, $doctor_id, $expected, $currency )
			: $this->razorpay_payment_matches( $transaction_id, $doctor_id, $expected, $currency );

		if ( ! $ok ) {
			return new WP_Error( 'payment_not_verified', __( 'We could not confirm this payment. Please try again or contact the clinic.', 'doc-booker-pro' ), [ 'status' => 402 ] );
		}

		return [
			'transaction_id' => $transaction_id,
			'amount_paid'    => $fee,
			'payment_by'     => $gateway,
			'payment_status' => 'completed',
		];
	}

	/**
	 * @param string $transaction_id
	 * @return bool
	 */
	private static function transaction_used( $transaction_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';

		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE transaction_id = %s", $transaction_id ) );
	}

	/**
	 * Ask Stripe whether this PaymentIntent paid the full fee for this doctor.
	 */
	private function stripe_payment_matches( $intent_id, $doctor_id, $expected, $currency ) {
		if ( 0 !== strpos( $intent_id, 'pi_' ) ) {
			return false;
		}

		try {
			\Stripe\Stripe::setApiKey( self::stripe_keys()['secret'] );
			$intent = \Stripe\PaymentIntent::retrieve( $intent_id );
		} catch ( \Exception $e ) {
			error_log( 'DocBooker Pro: could not retrieve Stripe payment ' . $intent_id . ': ' . $e->getMessage() );
			return false;
		}

		return 'succeeded' === $intent->status
			&& (int) $intent->amount_received === $expected
			&& strtolower( $intent->currency ) === strtolower( $currency )
			&& isset( $intent->metadata['doctor_id'] )
			&& (int) $intent->metadata['doctor_id'] === (int) $doctor_id;
	}

	/**
	 * Claim a Razorpay payment that verify-payment already checked with Razorpay.
	 */
	private function razorpay_payment_matches( $payment_id, $doctor_id, $expected, $currency ) {
		$key  = 'wpddbp_rzp_' . md5( $payment_id );
		$seen = get_transient( $key );
		if ( ! is_array( $seen ) ) {
			return false;
		}

		$ok = (int) $seen['doctor_id'] === (int) $doctor_id
			&& (int) $seen['amount'] === $expected
			&& $seen['currency'] === strtoupper( $currency );

		if ( $ok ) {
			delete_transient( $key ); // one booking per payment
		}

		return $ok;
	}
}
