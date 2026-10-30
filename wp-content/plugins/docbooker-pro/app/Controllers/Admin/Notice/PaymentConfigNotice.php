<?php
namespace WpDreamers\WPDDBP\Controllers\Admin\Notice;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Warns admins when online booking payment is switched ON but the active gateway's
 * API keys are missing for the current mode.
 *
 * Without keys the booking form's payment step can't initialise (Stripe.js throws
 * on an empty publishable key), so the form falls back to request-only. This notice
 * tells the admin why — and where to fix it.
 */
class PaymentConfigNotice {
    use SingletonTrait;

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'maybe_render' ] );
    }

    public function maybe_render() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( 'wpddb_global_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $get = static function ( $key, $default = '' ) use ( $settings ) {
            return ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) ? $settings[ $key ] : $default;
        };

        // Mirror the REST /payment-config defaults: payment defaults ON, gateway stripe.
        if ( 'on' !== $get( 'online_booking_payment', 'on' ) ) {
            return;
        }

        $gateway = $get( 'payment_gateway', 'stripe' );

        if ( 'razorpay' === $gateway ) {
            $mode  = $get( 'razorpay_mode', 'test' );
            $id    = 'live' === $mode ? $get( 'razorpay_live_key_id' )     : $get( 'razorpay_test_key_id' );
            $sec   = 'live' === $mode ? $get( 'razorpay_live_secret_key' ) : $get( 'razorpay_test_secret_key' );
            $label = 'Razorpay';
        } else {
            $mode  = $get( 'stripe_mode', 'test' );
            $id    = 'live' === $mode ? $get( 'stripe_live_publishable_key' ) : $get( 'stripe_test_publishable_key' );
            $sec   = 'live' === $mode ? $get( 'stripe_live_secret_key' )      : $get( 'stripe_test_secret_key' );
            $label = 'Stripe';
        }

        if ( '' !== $id && '' !== $sec ) {
            return; // Configured — no warning.
        }

        $settings_url = admin_url( 'edit.php?post_type=wpddb_doctor&page=settings' );

        printf(
            '<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
            esc_html__( 'DocBooker: online payment is on but not configured.', 'doc-booker-pro' ),
            sprintf(
                /* translators: 1: gateway name (Stripe/Razorpay), 2: mode (test/live) */
                esc_html__( 'Your %1$s %2$s API keys are missing, so the booking form\'s payment step can\'t load — it currently falls back to "Request appointment" with no payment taken.', 'doc-booker-pro' ),
                esc_html( $label ),
                esc_html( $mode )
            ),
            '<a href="' . esc_url( $settings_url ) . '" class="button button-primary">'
                . esc_html__( 'Add payment keys', 'doc-booker-pro' ) . '</a>'
        );
    }
}
