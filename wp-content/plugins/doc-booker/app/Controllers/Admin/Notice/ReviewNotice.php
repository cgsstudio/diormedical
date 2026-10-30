<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Notice;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

/**
 * Asks for a WordPress.org review only from an admin whose site really uses the
 * plugin: 7+ days after activation, at least one published doctor and one
 * booking. "Remind me later" waits 30 days; "Already did" and "No thanks" end it.
 * Shown on the Dashboard and the plugin's list screens, never on the same page
 * load as the Pro offer.
 */
class ReviewNotice {
    use SingletonTrait;

    const NONCE  = 'wpddb_review_notice';
    const SPARE  = 'wpddb_review_spare_me';
    const REMIND = 'wpddb_review_remind_me';
    const RATED  = 'wpddb_review_rated';
    const IN_USE = 'wpddb_review_site_in_use';
    // Set for a day after the owner answers, so the Pro offer doesn't follow straight away.
    const QUIET  = 'wpddb_notice_quiet';

    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_action' ], 5 );
        // Before ProNotice (also admin_init@10), so it can claim the notice slot.
        add_action( 'admin_init', [ $this, 'maybe_show' ], 9 );
    }

    public function maybe_show() {
        if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_option( self::SPARE ) || get_option( self::RATED ) ) {
            return;
        }

        $now       = time();
        $activated = (int) get_option( 'wpddb_plugin_activation_time' );
        if ( ! $activated || $now < $activated + 7 * DAY_IN_SECONDS ) {
            return;
        }
        $remind = (int) get_option( self::REMIND );
        if ( $remind && $now < $remind + 30 * DAY_IN_SECONDS ) {
            return;
        }
        if ( ! $this->site_is_in_use() ) {
            return;
        }

        // One DocBooker notice per page: the Pro offer stands aside while this one waits.
        $GLOBALS['docbooker__notice'] = 'review';
        add_action( 'admin_notices', [ $this, 'render' ] );
    }

    /**
     * At least one published doctor and one booking. Cached for a day so the
     * counts are not repeated on every admin page.
     *
     * @return bool
     */
    private function site_is_in_use() {
        $cached = get_transient( self::IN_USE );
        if ( false !== $cached ) {
            return 'yes' === $cached;
        }
        global $wpdb;
        $doctors = (int) ( wp_count_posts( wpddb()->post_type_doctor )->publish ?? 0 );
        $table   = $wpdb->prefix . 'wpddb_bookings';
        $booked  = $doctors && $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
            && (int) $wpdb->get_var( "SELECT EXISTS( SELECT 1 FROM `{$table}` )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
        set_transient( self::IN_USE, $booked ? 'yes' : 'no', DAY_IN_SECONDS );
        return (bool) $booked;
    }

    public function handle_action() {
        if ( empty( $_GET['wpddb_review'] ) || empty( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE ) ) {
            return;
        }
        switch ( sanitize_key( wp_unslash( $_GET['wpddb_review'] ) ) ) {
            case 'rated':
                update_option( self::RATED, '1', false );
                break;
            case 'later':
                update_option( self::REMIND, time(), false );
                break;
            case 'never':
                update_option( self::SPARE, '1', false );
                break;
            default:
                return;
        }
        set_transient( self::QUIET, 1, DAY_IN_SECONDS );
        wp_safe_redirect( remove_query_arg( [ 'wpddb_review', '_wpnonce' ] ) );
        exit;
    }

    private function action_url( $action ) {
        $url = remove_query_arg( [ 'wpddb_review', '_wpnonce' ] );
        return wp_nonce_url( add_query_arg( 'wpddb_review', $action, $url ), self::NONCE );
    }

    public function render() {
        if ( ! Helper::is_plugin_list_screen( true ) ) {
            return;
        }
        ?>
        <div class="notice notice-info wpddb-review-notice">
            <p><strong><?php esc_html_e( 'Is DocBooker helping your clinic?', 'doc-booker' ); ?></strong></p>
            <p><?php esc_html_e( 'Patients are booking with DocBooker on your site. A short review on WordPress.org helps other clinics find it, and tells us what to improve.', 'doc-booker' ); ?></p>
            <p>
                <a class="button button-primary" target="_blank" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/doc-booker/reviews/#new-post' ); ?>"><?php esc_html_e( 'Write a review', 'doc-booker' ); ?></a>
                <a class="button" href="<?php echo esc_url( $this->action_url( 'rated' ) ); ?>"><?php esc_html_e( 'I already did', 'doc-booker' ); ?></a>
                <a class="button" href="<?php echo esc_url( $this->action_url( 'later' ) ); ?>"><?php esc_html_e( 'Remind me later', 'doc-booker' ); ?></a>
                <a class="button-link" href="<?php echo esc_url( $this->action_url( 'never' ) ); ?>"><?php esc_html_e( 'No, thanks', 'doc-booker' ); ?></a>
            </p>
        </div>
        <style>
            .wpddb-review-notice .button-link { margin-left: 4px; vertical-align: middle; }
        </style>
        <?php
    }
}
