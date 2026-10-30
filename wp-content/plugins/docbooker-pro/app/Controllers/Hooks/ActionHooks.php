<?php

namespace WpDreamers\WPDDBP\Controllers\Hooks;
// Do not allow directly accessing this file.
use GymBuilder\Inc\Controllers\Helpers\Functions;
use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDBP\Controllers\Helper\Helper;
use WpDreamers\WPDDBP\Traits\SingletonTrait;
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Helper\Helper as FreeHelper;
use WpDreamers\WPDDB\Controllers\Hooks\ActionHooks as FreeActionHooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class ActionHooks{
    use SingletonTrait;
    public function __construct(){
        add_action('wpddb_save_clinic_post_meta_pro',[$this,'save_wpddb_clinic_post_meta_pro'],10,2);
        add_action('wpddb_save_doctor_post_meta_pro',[$this,'save_wpddb_doctor_post_meta_pro'],10,2);

        add_action( 'wpddb_save_doctor_shortcode_post_meta_pro',[$this,'save_wpddb_doctor_shortcode_post_meta_pro'],10,2);
        add_action( 'wpddb_save_clinic_shortcode_post_meta_pro',[$this,'save_wpddb_clinic_shortcode_post_meta_pro'],10,2);

        add_action( 'wpddb_doctor_shortcode_render_after_designation',[$this,'render_doctor_shortcode_render_after_designation'],10);
        add_action( 'wpddb_doctor_social_icons',[$this,'render_doctor_social_icons'],10);

        add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_speciality'], 26 );
        add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_degree'], 27 );
        add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_workplace'], 28 );
        add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_social_icons'], 40 );

        // Portrait card (free Layout 1): featured label and rating on the photo,
        // experience/patients/rating above the buttons, the fee on Book.
        add_action( 'wpddb_doctor_card_media', [ __CLASS__, 'doctor_card_badges' ], 20 );
        add_action( 'wpddb_doctor_card_before_actions', [ __CLASS__, 'doctor_card_stats' ], 10 );
        add_filter( 'wpddb_doctor_card_book_label', [ __CLASS__, 'doctor_card_book_label' ], 10, 2 );
        
        add_action('wpddb_shortcode_pagination',[__CLASS__,'shortcode_pagination'],10,3);
        add_action('wpddb_shortcode_results_info',[__CLASS__,'shortcode_results_info'],10,3);
        add_action('wpddb_shortcode_results_info',[__CLASS__,'shortcode_render_filtering'],11,3);
        remove_action( 'wpddb_clinic_thumbnail',[FreeActionHooks::class,'render_clinic_thumbnail'],10,2);
        add_action( 'wpddb_clinic_thumbnail',[__CLASS__,'render_clinic_thumbnail'],11,2);
        add_action( 'wpddb_after_main_content_wrapper',[__CLASS__,'render_clinic_gallery_popup']);

        add_action( 'wpddb_doctor_info_meta', [ __CLASS__, 'render_doctor_details_social_icons' ], 11 );
        add_action( 'wpddb_doctor_after_content', [ __CLASS__, 'render_after_doctor_meta_info' ] );

        // Extended doctor profile fields on the single-doctor page (Pro meta extension — Phase 3).
        add_action( 'wpddb_doctor_info_meta', [ __CLASS__, 'render_doctor_extended_info_meta' ], 12 );
        add_action( 'wpddb_doctor_after_content', [ __CLASS__, 'render_doctor_quote' ], 12 );

        // Extended clinic profile fields on the single-clinic page (Pro clinic meta extension — Phase 4).
        add_action( 'wpddb_clinic_after_title', [ __CLASS__, 'render_clinic_after_title_meta' ], 12 );
        // Clinic socials: the meta was saved + editable but never rendered anywhere.
        add_action( 'wpddb_clinic_after_content', [ __CLASS__, 'render_clinic_social_icons' ], 13 );
        add_action( 'wpddb_clinic_after_content', [ __CLASS__, 'render_clinic_extended_info_meta' ], 12 );

        // Clinic page running order. Free hooks its map at priority 10, which put the map
        // above everything else; re-prioritising it to 30 (rather than editing free) leaves
        // the rest in their original order and drops the map to the end:
        //   12 extended meta  ·  13 socials  ·  15 doctors  ·  30 map
        // Same remove/re-add pattern Pro already uses for the clinic thumbnail above.
        remove_action( 'wpddb_clinic_after_content', [ FreeActionHooks::class, 'render_clinic_map' ], 10 );
        add_action( 'wpddb_clinic_after_content', [ FreeActionHooks::class, 'render_clinic_map' ], 30 );

        // "Doctors at this clinic", department-filtered over AJAX — sits directly before
        // the map. Free is untouched: this only hooks the action free already fires.
        add_action( 'wpddb_clinic_after_content', [ __CLASS__, 'render_clinic_doctors' ], 15 );
        add_action( 'wp_ajax_wpddbp_clinic_doctors', [ __CLASS__, 'ajax_clinic_doctors' ] );
        add_action( 'wp_ajax_nopriv_wpddbp_clinic_doctors', [ __CLASS__, 'ajax_clinic_doctors' ] );

        add_action('wpddb_save_settings',[__CLASS__,'save_wpddb_settings_pro'],10);

    }

    /**
     * Render the extended doctor profile fields in the single-doctor info-meta
     * area (Pro meta extension — Phase 3). Every field empty-checks, so a doctor
     * with no extended data shows zero visual change. Theme-inherited typography
     * plus a tiny one-time scoped style for the ribbon / chips / strip.
     */
    /**
     * Rating as "4.9", or '' when the doctor has none.
     */
    private static function card_rating( $post_id ) {
        $rating = (float) get_post_meta( $post_id, 'wpddb_doctor_rating', true );
        return $rating > 0 ? rtrim( rtrim( number_format( $rating, 1, '.', '' ), '0' ), '.' ) : '';
    }

    public static function doctor_card_badges( $post_id ) {
        $featured = (string) get_post_meta( $post_id, 'wpddb_doctor_featured_label', true );
        $rating   = self::card_rating( $post_id );
        if ( '' !== $featured ) {
            echo '<span class="doctor-card-badge">' . esc_html( $featured ) . '</span>';
        }
        if ( '' !== $rating ) {
            /* translators: %s: rating such as 4.9 */
            echo '<span class="doctor-card-rating" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'doc-booker-pro' ), $rating ) ) . '">' . esc_html( $rating ) . '</span>';
        }
    }

    public static function doctor_card_stats( $post_id ) {
        // The editor stores 0 when a number is left empty; that means "not set".
        $experience = (int) get_post_meta( $post_id, 'wpddb_doctor_experience_years', true );
        $patients   = (string) get_post_meta( $post_id, 'wpddb_doctor_patient_count', true );
        $rating     = self::card_rating( $post_id );
        $reviews    = (int) get_post_meta( $post_id, 'wpddb_doctor_review_count', true );
        $items      = [];
        if ( $experience > 0 ) {
            /* translators: %d: years of experience */
            $items[] = [ sprintf( _n( '%d yr', '%d yrs', $experience, 'doc-booker-pro' ), $experience ), __( 'Experience', 'doc-booker-pro' ) ];
        }
        if ( '' !== $patients && '0' !== $patients ) {
            $items[] = [ $patients, __( 'Patients', 'doc-booker-pro' ) ];
        }
        if ( '' !== $rating ) {
            $items[] = [
                '★ ' . $rating,
                $reviews > 0
                    /* translators: %s: number of reviews */
                    ? sprintf( _n( '%s review', '%s reviews', $reviews, 'doc-booker-pro' ), number_format_i18n( $reviews ) )
                    : __( 'Rating', 'doc-booker-pro' ),
            ];
        }
        if ( ! $items ) {
            return;
        }
        echo '<ul class="doctor-card-stats">';
        foreach ( $items as $item ) {
            echo '<li><b>' . esc_html( $item[0] ) . '</b><span>' . esc_html( $item[1] ) . '</span></li>';
        }
        echo '</ul>';
    }

    /**
     * "Book · USD 45" when online payment is on and the doctor has a fee.
     */
    public static function doctor_card_book_label( $label, $post_id ) {
        if ( 'on' !== ( WpddbOptions::get_option( 'online_booking_payment', 'wpddb_global_settings' ) ?: 'on' ) ) {
            return $label;
        }
        $fee = \WpDreamers\WPDDBP\Controllers\Payments\BookingPayment::doctor_fee( $post_id );
        if ( $fee <= 0 ) {
            return $label;
        }
        $currency = strtoupper( WpddbOptions::get_option( 'currency', 'wpddb_global_settings' ) ?: 'USD' );
        $amount   = rtrim( rtrim( number_format_i18n( $fee, 2 ), '0' ), '.,' );
        $money    = in_array( WpddbOptions::get_option( 'currency_position', 'wpddb_global_settings' ), [ 'right', 'right_space' ], true ) ? $amount . ' ' . $currency : $currency . ' ' . $amount;
        /* translators: %s: booking fee with currency, e.g. USD 45 */
        return sprintf( __( 'Book · %s', 'doc-booker-pro' ), $money );
    }

    public static function render_doctor_extended_info_meta( $post_id = 0 ) {
        $post_id = $post_id ?: get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        $featured     = get_post_meta( $post_id, 'wpddb_doctor_featured_label', true );
        $credentials  = get_post_meta( $post_id, 'wpddb_doctor_credentials', true );
        // The editor stores 0 when the field is left empty; that means "not set".
        $experience   = (int) get_post_meta( $post_id, 'wpddb_doctor_experience_years', true );
        $experience   = $experience > 0 ? (string) $experience : '';
        $rating       = (float) get_post_meta( $post_id, 'wpddb_doctor_rating', true );
        $review_count = (int) get_post_meta( $post_id, 'wpddb_doctor_review_count', true );
        $patient      = get_post_meta( $post_id, 'wpddb_doctor_patient_count', true );
        $languages    = get_post_meta( $post_id, 'wpddb_doctor_languages', true );
        $alma_mater   = get_post_meta( $post_id, 'wpddb_doctor_alma_mater', true );

        $has_strip = ( '' !== (string) $experience ) || ( $rating > 0 ) || ( '' !== (string) $patient ) || ( ! empty( $languages ) && is_array( $languages ) );
        if ( '' === (string) $featured && empty( $credentials ) && ! $has_strip && '' === (string) $alma_mater ) {
            return;
        }

        self::print_extended_meta_styles();
        echo '<div class="wpddb-doctor-extended-meta">';

        if ( '' !== (string) $featured ) {
            echo '<span class="wpddb-doctor-featured-label">' . esc_html( $featured ) . '</span>';
        }

        if ( ! empty( $credentials ) && is_array( $credentials ) ) {
            echo '<div class="wpddb-doctor-credentials">';
            foreach ( $credentials as $cred ) {
                echo '<span class="wpddb-doctor-credential-chip">' . esc_html( $cred ) . '</span>';
            }
            echo '</div>';
        }

        if ( $has_strip ) {
            echo '<ul class="wpddb-doctor-profile-depth">';
            if ( '' !== (string) $experience ) {
                echo '<li><strong>' . esc_html( $experience ) . '</strong><span>' . esc_html__( 'Years experience', 'doc-booker-pro' ) . '</span></li>';
            }
            if ( $rating > 0 ) {
                $rating_str = rtrim( rtrim( number_format( $rating, 2, '.', '' ), '0' ), '.' );
                $rating_txt = '&#9733; ' . esc_html( $rating_str );
                if ( $review_count > 0 ) {
                    /* translators: %s: number of reviews */
                    $rating_txt .= ' <span class="wpddb-doctor-reviews">' . esc_html( sprintf( _n( '%s review', '%s reviews', $review_count, 'doc-booker-pro' ), number_format_i18n( $review_count ) ) ) . '</span>';
                }
                echo '<li><strong>' . $rating_txt . '</strong><span>' . esc_html__( 'Patient rating', 'doc-booker-pro' ) . '</span></li>';
            }
            if ( '' !== (string) $patient ) {
                echo '<li><strong>' . esc_html( $patient ) . '</strong><span>' . esc_html__( 'Patients seen', 'doc-booker-pro' ) . '</span></li>';
            }
            if ( ! empty( $languages ) && is_array( $languages ) ) {
                echo '<li><strong>' . esc_html( implode( ' · ', array_map( 'sanitize_text_field', $languages ) ) ) . '</strong><span>' . esc_html__( 'Languages', 'doc-booker-pro' ) . '</span></li>';
            }
            echo '</ul>';
        }

        if ( '' !== (string) $alma_mater ) {
            echo '<p class="wpddb-doctor-alma-mater"><span>' . esc_html__( 'Alma mater', 'doc-booker-pro' ) . ':</span> ' . esc_html( $alma_mater ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render the doctor quote as an italic pull-quote after the bio.
     */
    public static function render_doctor_quote() {
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        $quote = get_post_meta( $post_id, 'wpddb_doctor_quote', true );
        if ( '' === (string) $quote ) {
            return;
        }
        self::print_extended_meta_styles();
        echo '<blockquote class="wpddb-doctor-quote">' . esc_html( $quote ) . '</blockquote>';
    }

    /**
     * Print the scoped styles for the extended-meta UI exactly once per request.
     */
    protected static function print_extended_meta_styles() {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        echo '<style id="wpddb-doctor-extended-meta-css">'
            . '.wpddb-doctor-extended-meta{margin:14px 0}'
            . '.wpddb-doctor-featured-label{display:inline-block;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#fff;background:#0a4b78;padding:4px 12px;border-radius:999px;margin-bottom:10px}'
            . '.wpddb-doctor-credentials{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}'
            . '.wpddb-doctor-credential-chip{font-size:12px;font-weight:600;color:#0a4b78;background:rgba(10,75,120,.08);border:1px solid rgba(10,75,120,.18);padding:2px 10px;border-radius:4px}'
            . '.wpddb-doctor-profile-depth{list-style:none;display:flex;flex-wrap:wrap;gap:22px;margin:14px 0;padding:0}'
            . '.wpddb-doctor-profile-depth li{display:flex;flex-direction:column;gap:2px}'
            . '.wpddb-doctor-profile-depth strong{font-size:18px;line-height:1.1;color:inherit}'
            . '.wpddb-doctor-profile-depth span{font-size:12px;opacity:.7}'
            . '.wpddb-doctor-reviews{font-size:12px;font-weight:400;opacity:.7}'
            . '.wpddb-doctor-alma-mater{margin:10px 0;font-size:14px}'
            . '.wpddb-doctor-alma-mater span{font-weight:600}'
            . '.wpddb-doctor-quote{font-style:italic;font-size:18px;line-height:1.55;border-left:3px solid #0a4b78;padding:6px 0 6px 18px;margin:18px 0;opacity:.92}'
            . '</style>';
    }

    /* ---------------------------------------------------------------------
     * Extended clinic profile — single-clinic page render (Phase 4)
     * ------------------------------------------------------------------- */

    /** Human label for a clinic type. */
    protected static function clinic_type_label( $type ) {
        $map = array(
            'hospital'         => __( 'Hospital', 'doc-booker-pro' ),
            'clinic'           => __( 'Clinic', 'doc-booker-pro' ),
            'urgent_care'      => __( 'Urgent Care', 'doc-booker-pro' ),
            'specialty_center' => __( 'Specialty Center', 'doc-booker-pro' ),
            'pavilion'         => __( 'Pavilion', 'doc-booker-pro' ),
        );
        return $map[ $type ] ?? __( 'Clinic', 'doc-booker-pro' );
    }

    /** Specialists practising at this clinic (override wins, else derived count). */
    public static function clinic_specialist_count( $clinic_id ) {
        $override = (int) get_post_meta( $clinic_id, 'wpddb_clinic_specialist_count_override', true );
        if ( $override > 0 ) {
            return $override;
        }
        $q = new \WP_Query(
            array(
                'post_type'      => 'wpddb_doctor',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'meta_query'     => array(
                    array(
                        'key'     => 'wpddb_doctor_available_clinic',
                        'value'   => '"' . (int) $clinic_id . '"',
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        return (int) $q->found_posts;
    }

    /** Format a "HH:MM" time to a localized 12-hour string. */
    protected static function clinic_fmt_time( $t ) {
        $t = trim( (string) $t );
        if ( '' === $t || ! preg_match( '/^\d{1,2}:\d{2}$/', $t ) ) {
            return $t;
        }
        $ts = strtotime( $t );
        return $ts ? date_i18n( 'g:i A', $ts ) : $t;
    }

    /** Identity row + tagline, hooked after the single-clinic title. */
    public static function render_clinic_after_title_meta() {
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        $type      = get_post_meta( $post_id, 'wpddb_clinic_type', true ) ?: 'clinic';
        $founded   = get_post_meta( $post_id, 'wpddb_clinic_founded_year', true );
        $emergency = get_post_meta( $post_id, 'wpddb_clinic_emergency_24_7', true );
        $tagline   = get_post_meta( $post_id, 'wpddb_clinic_tagline', true );

        self::print_clinic_extended_styles();
        echo '<div class="wpddb-clinic-id-row">';
        echo '<span class="wpddb-clinic-type wpddb-clinic-type--' . esc_attr( $type ) . '">' . esc_html( self::clinic_type_label( $type ) ) . '</span>';
        if ( $emergency ) {
            echo '<span class="wpddb-clinic-247"><i></i>' . esc_html__( '24/7 Emergency', 'doc-booker-pro' ) . '</span>';
        }
        if ( $founded ) {
            /* translators: %s: founding year. */
            echo '<span class="wpddb-clinic-founded">' . esc_html( sprintf( __( 'est. %s', 'doc-booker-pro' ), $founded ) ) . '</span>';
        }
        echo '</div>';
        if ( '' !== (string) $tagline ) {
            echo '<p class="wpddb-clinic-tagline">' . esc_html( $tagline ) . '</p>';
        }
    }

    /** Stats + services + hours + accreditations + credits, after the content. */
    public static function render_clinic_extended_info_meta() {
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        $services     = get_post_meta( $post_id, 'wpddb_clinic_services', true );
        $certs        = get_post_meta( $post_id, 'wpddb_clinic_certifications', true );
        $hours        = get_post_meta( $post_id, 'wpddb_clinic_hours', true );
        $capacity     = (int) get_post_meta( $post_id, 'wpddb_clinic_patient_capacity', true );
        $architect    = (string) get_post_meta( $post_id, 'wpddb_clinic_architect_credit', true );
        $photographer = (string) get_post_meta( $post_id, 'wpddb_clinic_photographer_credit', true );
        $spec_count   = self::clinic_specialist_count( $post_id );

        $has_services = ! empty( $services ) && is_array( $services );
        $has_certs    = ! empty( $certs ) && is_array( $certs );
        $has_hours    = ! empty( $hours ) && is_array( $hours );
        $has_stats    = $capacity > 0 || $spec_count > 0;
        $has_credits  = '' !== $architect || '' !== $photographer;

        if ( ! $has_services && ! $has_certs && ! $has_hours && ! $has_stats && ! $has_credits ) {
            return;
        }

        self::print_clinic_extended_styles();
        echo '<div class="wpddb-clinic-extended">';

        if ( $has_stats ) {
            echo '<ul class="wpddb-clinic-stats">';
            if ( $spec_count > 0 ) {
                echo '<li><strong>' . esc_html( number_format_i18n( $spec_count ) ) . '</strong><span>' . esc_html__( 'Specialists', 'doc-booker-pro' ) . '</span></li>';
            }
            if ( $capacity > 0 ) {
                echo '<li><strong>' . esc_html( number_format_i18n( $capacity ) ) . '</strong><span>' . esc_html__( 'Patient capacity', 'doc-booker-pro' ) . '</span></li>';
            }
            echo '</ul>';
        }

        if ( $has_services ) {
            echo '<div class="wpddb-clinic-section"><h3>' . esc_html__( 'Services', 'doc-booker-pro' ) . '</h3><div class="wpddb-clinic-services">';
            foreach ( $services as $s ) {
                echo '<span class="wpddb-clinic-service">' . esc_html( $s ) . '</span>';
            }
            echo '</div></div>';
        }

        if ( $has_hours ) {
            $day_labels = array(
                'monday'    => __( 'Monday', 'doc-booker-pro' ),
                'tuesday'   => __( 'Tuesday', 'doc-booker-pro' ),
                'wednesday' => __( 'Wednesday', 'doc-booker-pro' ),
                'thursday'  => __( 'Thursday', 'doc-booker-pro' ),
                'friday'    => __( 'Friday', 'doc-booker-pro' ),
                'saturday'  => __( 'Saturday', 'doc-booker-pro' ),
                'sunday'    => __( 'Sunday', 'doc-booker-pro' ),
            );
            echo '<div class="wpddb-clinic-section"><h3>' . esc_html__( 'Hours', 'doc-booker-pro' ) . '</h3><table class="wpddb-clinic-hours"><tbody>';
            // wp_date() respects the site's timezone + locale, unlike date().
            $today_key = strtolower( wp_date( 'l' ) );
            foreach ( $hours as $h ) {
                $day    = isset( $h['day'] ) ? $h['day'] : '';
                $label  = $day_labels[ $day ] ?? ucfirst( (string) $day );
                $closed = ! empty( $h['is_closed'] );

                // Row state is styling info the markup didn't carry: without it, "Closed"
                // reads the same as an open day and today isn't findable at a glance.
                $row_class = [];
                if ( $day && strtolower( (string) $day ) === $today_key ) {
                    $row_class[] = 'is-today';
                }
                if ( $closed ) {
                    $row_class[] = 'is-closed';
                }

                echo '<tr' . ( $row_class ? ' class="' . esc_attr( implode( ' ', $row_class ) ) . '"' : '' ) . '>';
                echo '<th>' . esc_html( $label ) . '</th><td>';
                if ( $closed ) {
                    echo esc_html__( 'Closed', 'doc-booker-pro' );
                } else {
                    echo esc_html( self::clinic_fmt_time( $h['open'] ?? '' ) . ' – ' . self::clinic_fmt_time( $h['close'] ?? '' ) );
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        if ( $has_certs ) {
            echo '<div class="wpddb-clinic-section"><h3>' . esc_html__( 'Accreditations', 'doc-booker-pro' ) . '</h3><ul class="wpddb-clinic-certs">';
            foreach ( $certs as $c ) {
                $name = isset( $c['name'] ) ? $c['name'] : '';
                if ( '' === $name ) {
                    continue;
                }
                $sub = trim( ( isset( $c['issuer'] ) ? $c['issuer'] : '' ) . ( ! empty( $c['year'] ) ? ' · ' . $c['year'] : '' ), ' ·' );
                echo '<li><strong>' . esc_html( $name ) . '</strong>' . ( '' !== $sub ? '<span>' . esc_html( $sub ) . '</span>' : '' ) . '</li>';
            }
            echo '</ul></div>';
        }

        if ( $has_credits ) {
            $bits = array();
            if ( '' !== $architect ) {
                $bits[] = $architect;
            }
            if ( '' !== $photographer ) {
                $bits[] = $photographer;
            }
            echo '<p class="wpddb-clinic-credits">' . esc_html( implode( ' · ', $bits ) ) . '</p>';
        }

        echo '</div>';
    }

    /** Scoped styles for the extended-clinic UI, once per request. */
    /**
     * Formerly printed an inline <style> for the clinic extended meta.
     *
     * Those rules now live in `src/sass/public/components/_clinic-single.scss`, so they
     * can derive from --wpddb-primary-color (the admin's Style settings) instead of
     * hardcoding a separate navy/bronze palette — and so an inline block can't outrank
     * the stylesheet. Kept as a no-op because the renderers call it in two places.
     */
    protected static function print_clinic_extended_styles() {
        return;
    }

    public function save_wpddb_clinic_post_meta_pro($post_id, $post_data) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!empty($post_data['wpddb_clinic_social_icons'])) {
            $clinic_social_icons = json_decode(stripslashes($post_data['wpddb_clinic_social_icons']), true);
            if (!empty($clinic_social_icons) && is_array($clinic_social_icons)) {
                $sanitized = array_map(function ($item) {
                    return [
                        'social_name' => sanitize_text_field($item['social_name']),
                        'social_url' => sanitize_url($item['social_url']),
                    ];
                }, $clinic_social_icons);
                update_post_meta($post_id, 'wpddb_clinic_social_icons', $sanitized);
            } else {
                delete_post_meta($post_id, 'wpddb_clinic_social_icons');
            }
        }
        $gallery_images = json_decode(stripslashes($post_data['wpddb_clinic_gallery_images']), true);
        if (!empty($gallery_images) && is_array($gallery_images)) {
            $sanitized_images = array_map('esc_url_raw', $gallery_images);
            update_post_meta($post_id, 'wpddb_clinic_gallery_images', $sanitized_images);
        } else {
            delete_post_meta($post_id, 'wpddb_clinic_gallery_images');
        }

        // Extended clinic profile fields (Pro clinic meta extension — Phase 4).
        if ( isset( $post_data['wpddb_clinic_type'] ) ) {
            $allowed_types = array( 'hospital', 'clinic', 'urgent_care', 'specialty_center', 'pavilion' );
            $type          = sanitize_key( $post_data['wpddb_clinic_type'] );
            update_post_meta( $post_id, 'wpddb_clinic_type', in_array( $type, $allowed_types, true ) ? $type : 'clinic' );
        }
        if ( isset( $post_data['wpddb_clinic_founded_year'] ) ) {
            $year = (int) $post_data['wpddb_clinic_founded_year'];
            if ( $year >= 1800 && $year <= (int) gmdate( 'Y' ) ) {
                update_post_meta( $post_id, 'wpddb_clinic_founded_year', $year );
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_founded_year' );
            }
        }
        if ( isset( $post_data['wpddb_clinic_patient_capacity'] ) ) {
            $cap = (int) $post_data['wpddb_clinic_patient_capacity'];
            if ( $cap > 0 ) {
                update_post_meta( $post_id, 'wpddb_clinic_patient_capacity', min( 100000, $cap ) );
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_patient_capacity' );
            }
        }
        update_post_meta( $post_id, 'wpddb_clinic_emergency_24_7', ( ! empty( $post_data['wpddb_clinic_emergency_24_7'] ) && '0' !== $post_data['wpddb_clinic_emergency_24_7'] ) ? 1 : 0 );
        if ( isset( $post_data['wpddb_clinic_tagline'] ) ) {
            $tagline = sanitize_text_field( $post_data['wpddb_clinic_tagline'] );
            if ( '' !== $tagline ) {
                update_post_meta( $post_id, 'wpddb_clinic_tagline', mb_substr( $tagline, 0, 140 ) );
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_tagline' );
            }
        }
        if ( isset( $post_data['wpddb_clinic_specialist_count_override'] ) ) {
            $ov = (int) $post_data['wpddb_clinic_specialist_count_override'];
            if ( $ov > 0 ) {
                update_post_meta( $post_id, 'wpddb_clinic_specialist_count_override', min( 100000, $ov ) );
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_specialist_count_override' );
            }
        }
        foreach ( array( 'wpddb_clinic_architect_credit', 'wpddb_clinic_photographer_credit' ) as $credit_key ) {
            if ( isset( $post_data[ $credit_key ] ) ) {
                $credit = sanitize_text_field( $post_data[ $credit_key ] );
                if ( '' !== $credit ) {
                    update_post_meta( $post_id, $credit_key, mb_substr( $credit, 0, 120 ) );
                } else {
                    delete_post_meta( $post_id, $credit_key );
                }
            }
        }
        // Services — array of strings.
        if ( isset( $post_data['wpddb_clinic_services'] ) ) {
            $services = json_decode( stripslashes( $post_data['wpddb_clinic_services'] ), true );
            if ( ! empty( $services ) && is_array( $services ) ) {
                $services = array_values( array_filter( array_map( 'sanitize_text_field', $services ), function ( $s ) {
                    return '' !== $s;
                } ) );
                update_post_meta( $post_id, 'wpddb_clinic_services', array_slice( $services, 0, 40 ) );
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_services' );
            }
        }
        // Certifications — array of { name, issuer, year }.
        if ( isset( $post_data['wpddb_clinic_certifications'] ) ) {
            $certs = json_decode( stripslashes( $post_data['wpddb_clinic_certifications'] ), true );
            if ( ! empty( $certs ) && is_array( $certs ) ) {
                $clean = array();
                foreach ( $certs as $c ) {
                    $name = isset( $c['name'] ) ? sanitize_text_field( $c['name'] ) : '';
                    if ( '' === $name ) {
                        continue;
                    }
                    $clean[] = array(
                        'name'   => $name,
                        'issuer' => isset( $c['issuer'] ) ? sanitize_text_field( $c['issuer'] ) : '',
                        'year'   => isset( $c['year'] ) && (int) $c['year'] > 0 ? (int) $c['year'] : '',
                    );
                }
                if ( ! empty( $clean ) ) {
                    update_post_meta( $post_id, 'wpddb_clinic_certifications', array_slice( $clean, 0, 20 ) );
                } else {
                    delete_post_meta( $post_id, 'wpddb_clinic_certifications' );
                }
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_certifications' );
            }
        }
        // Hours — array of 7 { day, is_closed, open, close }.
        if ( isset( $post_data['wpddb_clinic_hours'] ) ) {
            $hours = json_decode( stripslashes( $post_data['wpddb_clinic_hours'] ), true );
            if ( ! empty( $hours ) && is_array( $hours ) ) {
                $valid_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
                $clean      = array();
                foreach ( $hours as $h ) {
                    $day = isset( $h['day'] ) ? sanitize_key( $h['day'] ) : '';
                    if ( ! in_array( $day, $valid_days, true ) ) {
                        continue;
                    }
                    $clean[] = array(
                        'day'       => $day,
                        'is_closed' => ! empty( $h['is_closed'] ),
                        'open'      => isset( $h['open'] ) ? preg_replace( '/[^0-9:]/', '', $h['open'] ) : '',
                        'close'     => isset( $h['close'] ) ? preg_replace( '/[^0-9:]/', '', $h['close'] ) : '',
                    );
                }
                if ( ! empty( $clean ) ) {
                    update_post_meta( $post_id, 'wpddb_clinic_hours', $clean );
                } else {
                    delete_post_meta( $post_id, 'wpddb_clinic_hours' );
                }
            } else {
                delete_post_meta( $post_id, 'wpddb_clinic_hours' );
            }
        }
    }

    public function save_wpddb_doctor_post_meta_pro($post_id, $post_data) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($post_data['wpddb_doctor_booking_fee'])){
            $booking_fee = max(0, floatval($post_data['wpddb_doctor_booking_fee']));
            update_post_meta($post_id, 'wpddb_doctor_booking_fee', $booking_fee);
        }
        if (  isset( $post_data['wpddb_doctor_services'] ) ) {
            $services = explode(',', $post_data['wpddb_doctor_services']);

            // explode() on an empty field yields [''], so drop blanks — otherwise an
            // empty list saves as one empty service.
            $sanitized_services = array_values( array_filter( array_map(function($item) {
                $item = trim($item);
                return sanitize_text_field($item);
            }, $services) ) );
            update_post_meta( $post_id, 'wpddb_doctor_services', $sanitized_services );
        }

        // Only when the field was sent, so a save without it can't wipe it.
        $doctor_awards = isset( $post_data['wpddb_doctor_awards'] ) ? json_decode( stripslashes( $post_data['wpddb_doctor_awards'] ), true ) : null;
        if ( ! isset( $post_data['wpddb_doctor_awards'] ) ) {
            // not sent: leave the stored value alone
        } elseif (!empty($doctor_awards) && is_array($doctor_awards)) {
            $awards_sanitized_data = array_map(function ($item) {
                return [
                    'award_text' => sanitize_text_field($item['award_text']),
                    'award_image' => sanitize_url($item['award_image']),
                ];
            }, $doctor_awards);
            update_post_meta($post_id, 'wpddb_doctor_awards', $awards_sanitized_data);
        }else{
            delete_post_meta($post_id, 'wpddb_doctor_awards');
        }
        // Only when the field was sent, so a save without it can't wipe it.
        $doctor_social_icons = isset( $post_data['wpddb_doctor_social_icons'] ) ? json_decode( stripslashes( $post_data['wpddb_doctor_social_icons'] ), true ) : null;
        if ( ! isset( $post_data['wpddb_doctor_social_icons'] ) ) {
            // not sent: leave the stored value alone
        } elseif (!empty($doctor_social_icons) && is_array($doctor_social_icons)) {
            $social_icons_sanitized_data = array_map(function ($item) {
                return [
                    'social_name' => sanitize_text_field($item['social_name']),
                    'social_url' => sanitize_url($item['social_url']),
                ];
            }, $doctor_social_icons);
            update_post_meta($post_id, 'wpddb_doctor_social_icons', $social_icons_sanitized_data);
        }else{
            delete_post_meta($post_id, 'wpddb_doctor_social_icons');
        }

        // ---- Extended doctor profile fields (Pro doctor meta extension — Phase 3) ----
        if ( isset( $post_data['wpddb_doctor_experience_years'] ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_experience_years', max( 0, min( 80, intval( $post_data['wpddb_doctor_experience_years'] ) ) ) );
        }
        if ( isset( $post_data['wpddb_doctor_featured_label'] ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_featured_label', sanitize_text_field( mb_substr( (string) $post_data['wpddb_doctor_featured_label'], 0, 32 ) ) );
        }
        if ( isset( $post_data['wpddb_doctor_alma_mater'] ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_alma_mater', sanitize_text_field( mb_substr( (string) $post_data['wpddb_doctor_alma_mater'], 0, 120 ) ) );
        }
        if ( isset( $post_data['wpddb_doctor_rating'] ) ) {
            $doctor_rating = max( 0, min( 5, (float) $post_data['wpddb_doctor_rating'] ) );
            update_post_meta( $post_id, 'wpddb_doctor_rating', round( $doctor_rating, 2 ) );
        }
        if ( isset( $post_data['wpddb_doctor_review_count'] ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_review_count', max( 0, intval( $post_data['wpddb_doctor_review_count'] ) ) );
        }
        if ( isset( $post_data['wpddb_doctor_patient_count'] ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_patient_count', sanitize_text_field( mb_substr( (string) $post_data['wpddb_doctor_patient_count'], 0, 24 ) ) );
        }
        if ( isset( $post_data['wpddb_doctor_quote'] ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_quote', sanitize_textarea_field( mb_substr( (string) $post_data['wpddb_doctor_quote'], 0, 280 ) ) );
        }

        $doctor_languages = isset( $post_data['wpddb_doctor_languages'] ) ? json_decode( stripslashes( $post_data['wpddb_doctor_languages'] ), true ) : null;
        if ( ! empty( $doctor_languages ) && is_array( $doctor_languages ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_languages', array_values( array_filter( array_map( 'sanitize_text_field', $doctor_languages ) ) ) );
        } else {
            delete_post_meta( $post_id, 'wpddb_doctor_languages' );
        }

        $doctor_credentials = isset( $post_data['wpddb_doctor_credentials'] ) ? json_decode( stripslashes( $post_data['wpddb_doctor_credentials'] ), true ) : null;
        if ( ! empty( $doctor_credentials ) && is_array( $doctor_credentials ) ) {
            update_post_meta( $post_id, 'wpddb_doctor_credentials', array_values( array_filter( array_map( 'sanitize_text_field', $doctor_credentials ) ) ) );
        } else {
            delete_post_meta( $post_id, 'wpddb_doctor_credentials' );
        }
    }

    public function save_wpddb_doctor_shortcode_post_meta_pro($post_id, $post_data) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $pro_fields = [
            'pagination_type'         => 'sanitize_text_field',
            'pagination_per_page'     => 'absint',
            'show_department'         => 'sanitize_text_field',
            'show_designation'        => 'sanitize_text_field',
            'show_speciality'         => 'sanitize_text_field',
            'show_degree'             => 'sanitize_text_field',
            'show_workplace'          => 'sanitize_text_field',
            'show_content'            => 'sanitize_text_field',
            'show_social_icons'       => 'sanitize_text_field',
            'show_filtering'          => 'sanitize_text_field',
            'content_limit'           => 'absint',
            'img_border'              => 'absint',
            'img_border_color'        => 'sanitize_text_field',
            'img_border_radius'       => 'sanitize_text_field',

            'department_font_size'    => 'absint',
            'department_font_weight'  => 'absint',
            'department_alignment'    => 'sanitize_text_field',

            'content_font_size'       => 'absint',
            'content_font_weight'     => 'absint',
            'content_alignment'       => 'sanitize_text_field',

            'title_font_size'         => 'absint',
            'title_font_weight'       => 'absint',
            'title_alignment'         => 'sanitize_text_field',

            'designation_color'       => 'sanitize_text_field',
            'designation_font_size'   => 'absint',
            'designation_font_weight' => 'absint',
            'designation_alignment'   => 'sanitize_text_field',

            'speciality_color'       => 'sanitize_text_field',
            'speciality_font_size'   => 'absint',
            'speciality_font_weight' => 'absint',
            'speciality_alignment'   => 'sanitize_text_field',

            'degree_color'           => 'sanitize_text_field',
            'degree_font_size'       => 'absint',
            'degree_font_weight'     => 'absint',
            'degree_alignment'       => 'sanitize_text_field',

            'workplace_color'        => 'sanitize_text_field',
            'workplace_font_size'    => 'absint',
            'workplace_font_weight'  => 'absint',
            'workplace_alignment'    => 'sanitize_text_field',
        ];
        foreach ( $pro_fields as $field => $sanitize ) {
            $key = 'wpddb_doctor_' . $field;
            if ( isset( $post_data[ $key ] ) ) {
                update_post_meta( $post_id, $key, call_user_func( $sanitize, $post_data[ $key ] ) );
            }
        }
    }

    public function save_wpddb_clinic_shortcode_post_meta_pro($post_id, $post_data) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $pro_fields = [
            'pagination_type'         => 'sanitize_text_field',
            'pagination_per_page'     => 'absint',
            'show_content'            => 'sanitize_text_field',
            'show_map_btn'            => 'sanitize_text_field',
            'content_limit'           => 'absint',
            'img_border'              => 'absint',
            'img_border_color'        => 'sanitize_text_field',
            'img_border_radius'       => 'sanitize_text_field',
            'title_font_size'         =>'absint',
            'title_font_weight'       =>'absint',
            'title_alignment'         =>'sanitize_text_field',
            'content_font_size'       =>'absint',
            'content_font_weight'     =>'absint',
            'content_alignment'       =>'sanitize_text_field',
            'phone_color'             =>'sanitize_text_field',
            'phone_font_size'         =>'absint',
            'phone_font_weight'       =>'absint',
            'phone_alignment'         =>'sanitize_text_field',
        ];
        foreach ( $pro_fields as $field => $sanitize ) {
            $key = 'wpddb_clinic_' . $field;
            if ( isset( $post_data[ $key ] ) ) {
                update_post_meta( $post_id, $key, call_user_func( $sanitize, $post_data[ $key ] ) );
            }
        }
    }

    public static function render_doctor_shortcode_render_after_designation($args) {
        if (isset($args['show_speciality']) && $args['show_speciality'] == 'on') {
            $doctor_speciality = get_post_meta($args['wpddbdoctor_id'], 'wpddb_doctor_speciality', true);
            if ($doctor_speciality) {
                FreeHelper::print_html('<div class="doctor-speciality">' . $doctor_speciality . '</div>');
            }
        }
        if (isset($args['show_degree']) && $args['show_degree'] == 'on') {
            $doctor_degree = get_post_meta($args['wpddbdoctor_id'], 'wpddb_doctor_degree', true);
            if ($doctor_degree) {
                FreeHelper::print_html('<div class="doctor-degree">' . $doctor_degree . '</div>');
            }
        }
        if (isset($args['show_workplace']) && $args['show_workplace'] == 'on') {
            $doctor_workplace = get_post_meta($args['wpddbdoctor_id'], 'wpddb_doctor_workplace', true);
            if ($doctor_workplace) {
                FreeHelper::print_html('<div class="doctor-workplace">' . $doctor_workplace . '</div>');
            }
        }
    }

    public static function render_doctor_social_icons( $args ) {
        if ( isset( $args['show_social_icons'] ) && $args['show_social_icons'] == 'on' ) {
            $social_icons = get_post_meta( $args['wpddbdoctor_id'], 'wpddb_doctor_social_icons', true );
            if ( ! empty( $social_icons ) && is_array( $social_icons ) ) {
                echo '<div class="wpddb-doctor-social-icons"><ul>';
                foreach ( $social_icons as $icon ) {
                    if ( empty( $icon['social_url'] ) || empty( $icon['social_name'] ) ) {
                        continue;
                    }
                    echo '<li class="' . esc_attr( $icon['social_name'] ) . '"><a href="' . esc_url( $icon['social_url'] ) . '" target="_blank" rel="noopener noreferrer">';
                    echo Helper::get_social_icon_svg( $icon['social_name'] );
                    echo '</a></li>';
                }
                echo '</ul></div>';
            }
        }
    }

    public static function doctor_loop_item_speciality() {
        $show_speciality         = WpddbOptions::get_option('show_speciality', 'wpddb_doctor_settings') ?: 'off';
        $show_speciality = $show_speciality == 'on';
        $doctor_speciality = get_post_meta( get_the_ID(), 'wpddb_doctor_speciality', true );
        if ($show_speciality && $doctor_speciality){
            ?>
            <div class="doctor-speciality"><?php FreeHelper::print_html($doctor_speciality); ?></div>
        <?php }
    }

    public static function doctor_loop_item_degree() {
        $show_degree         = WpddbOptions::get_option('show_degree', 'wpddb_doctor_settings') ?: 'off';
        $show_degree = $show_degree == 'on';
        $doctor_degree = get_post_meta( get_the_ID(), 'wpddb_doctor_degree', true );
        if ($show_degree && $doctor_degree){
            ?>
            <div class="doctor-degree"><?php FreeHelper::print_html($doctor_degree); ?></div>
        <?php }
    }
    public static function doctor_loop_item_workplace() {
        $show_workplace         = WpddbOptions::get_option('show_workplace', 'wpddb_doctor_settings') ?: 'off';
        $show_workplace = $show_workplace == 'on';
        $doctor_workplace = get_post_meta( get_the_ID(), 'wpddb_doctor_workplace', true );
        if ($show_workplace && $doctor_workplace){
            ?>
            <div class="doctor-workplace"><?php FreeHelper::print_html($doctor_workplace); ?></div>
        <?php }
    }

    public static function doctor_loop_item_social_icons() {
        $show_social_icons        = WpddbOptions::get_option('show_social_icons', 'wpddb_doctor_settings') ?: 'off';
        $social_icons = get_post_meta( get_the_ID(), 'wpddb_doctor_social_icons', true );
        if ($show_social_icons == 'on' && !empty($social_icons) && is_array($social_icons)) {
            ?>
            <div class="wpddb-doctor-social-icons">
                <ul>
                    <?php
                    foreach ($social_icons as $icon) {
                        if (empty($icon['social_url']) || empty($icon['social_name'])) {
                            continue;
                        }
                        echo '<li class="' . esc_attr($icon['social_name']) . '"><a href="' . esc_url($icon['social_url']) . '" target="_blank" rel="noopener noreferrer">';
                        echo Helper::get_social_icon_svg($icon['social_name']);
                        echo '</a></li>';
                    }
                    ?>
                </ul>
            </div>
            <?php
        }
    }

    public static function shortcode_pagination($query, $shortcode_tag, $metas) {
        $html = '';
        $pagination = $metas['more_btn'] != 'on';
        $pagination_none_type = $metas['pagination_type'] != 'none';
        $pagination_type = $metas['pagination_type'];
        $post_type_label = $metas['post_type'] ?? 'post';
        if ( $pagination &&  $pagination_none_type) {
            $htmlUtility = null;
            $postPp         = $query->query_vars['posts_per_page'];
            $page           = $query->query_vars['paged'] ?: 1;
            $totalPage      = $query->max_num_pages;
            $foundPost = $query->found_posts;

            if( ! empty( $metas['posts_per_page']) ) {
                $posts_limit = (int) $metas['posts_per_page'];

                if($posts_limit < $foundPost) {
                    $foundPost = $posts_limit;
                    $totalPage = ceil( (int) $foundPost / (int) $postPp );
                }
            }


            $totalPage = absint( $totalPage );

            if($totalPage <= 1) {
                return $html;
            }
            if ($post_type_label === wpddb()->post_type_doctor) {
                $post_type_label = 'Doctors';
            } else {
                $post_type_label = 'Clinics';
            }
            $pagination_html = '<div class="pagination-container">';


            switch ($pagination_type) {
                case 'ajax_pagination':
                    $pagination_html .= Helper::render_number_pagination($page, $totalPage);
                    break;
                case 'load_more':
                    $pagination_html .= Helper::render_load_more_pagination($page, $totalPage, $post_type_label);
                    break;

                case 'load_more_scroll':
                    $pagination_html .= Helper::render_infinite_scroll_indicator();
                    break;
            }

            $pagination_html .= '</div>';

            // Loading overlay
            $pagination_html .= '<div class="loading-overlay">';
            $pagination_html .= '<div class="loading-spinner">';
            $pagination_html .= '<div class="spinner"></div>';
            $pagination_html .= '<span class="loading-text">' . esc_html__( 'Loading...', 'doc-booker-pro' ) . '</span>';
            $pagination_html .= '</div>';
            $pagination_html .= '</div>';

            $htmlUtility = $pagination_html;


            if ( $htmlUtility ) {
                $html .= "<div class='wpddb-pagination-wrapper'>" . $htmlUtility . "</div>";
            }
        }
        FreeHelper::print_html($html);
    }

    public static function shortcode_results_info($query, $shortcode_tag, $metas) {
        $html = '';

        $postPp    = $query->query_vars['posts_per_page'];
        $page      = $query->query_vars['paged'] ?: 1;
        $foundPost = $query->found_posts;

        if( ! empty( $metas['posts_per_page']) ) {
            $posts_limit = (int) $metas['posts_per_page'];
            if($posts_limit < $foundPost) {
                $foundPost = $posts_limit;
            }
        }

        $start = (($page - 1) * $postPp) + 1;
        $end   = min($page * $postPp, $foundPost);

        if ($shortcode_tag === 'wpddbdoctor') {
            $post_type_label = 'doctors';
        } elseif ($shortcode_tag === 'wpddbclinic') {
            $post_type_label = 'clinics';
        } else {
            $post_type_label = 'results';
        }

        $html .= '<div class="results-info">';
        $html .= "Showing {$start}-{$end} of {$foundPost} {$post_type_label}";
        $html .= '</div>';

        FreeHelper::print_html($html);
    }

    public static function shortcode_render_filtering($query, $shortcode_tag, $metas) {

        if($shortcode_tag !== 'wpddbdoctor' || $metas['show_filtering'] == 'off') {
            return;
        }

        $html = '';

        $clinics = get_posts([
            'post_type'      => wpddb()->post_type_clinic,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $departments = get_terms([
            'taxonomy'   => wpddb()->doctor_category,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if(isset($metas['more_btn']) && $metas['more_btn'] == 'on' ){
            $html .= '<div class="loading-overlay">';
            $html .= '<div class="loading-spinner">';
            $html .= '<div class="spinner"></div>';
            $html .= '<span class="loading-text">' . esc_html__( 'Loading...', 'doc-booker-pro' ) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<div class="wpddb-doctor-filter-container">';

        $html .= '<h3 class="filter-title">' . esc_html__( 'Filter Doctors', 'doc-booker-pro' ) . '</h3>
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="wpddb-clinic-filter" class="filter-label">' . esc_html__( 'Select Clinic', 'doc-booker-pro' ) . '</label>
                        <select id="wpddb-clinic-filter" class="filter-select">
                            <option value="">' . esc_html__( 'All Clinics', 'doc-booker-pro' ) . '</option>';

       if ( ! empty( $clinics ) ) {
           foreach ( $clinics as $clinic ) {
               $clinic_id   = esc_attr( $clinic->ID );
               $clinic_name = esc_html( $clinic->post_title );
               $html .= "<option value=\"{$clinic_id}\">{$clinic_name}</option>";
           }
       }

        $html .= '</select></div>
                <div class="filter-group">
                    <label for="wpddb-department-filter" class="filter-label">' . esc_html__( 'Select Department', 'doc-booker-pro' ) . '</label>
                    <select id="wpddb-department-filter" class="filter-select">
                        <option value="">' . esc_html__( 'All Departments', 'doc-booker-pro' ) . '</option>';

       if ( ! empty( $departments ) && ! is_wp_error( $departments )  ) {
           foreach ( $departments as $department ) {
                $dept_id   = esc_attr( $department->term_id );
                $dept_name = esc_html( $department->name );
               $html .= "<option value=\"{$dept_id}\">{$dept_name}</option>";
           }
       }

        $html .= '</select></div>
                <div class="filter-actions">
                    <button type="button" class="wpddb-clear-filters-btn">' . esc_html__( 'Clear All', 'doc-booker-pro' ) . '</button>
                </div>
        </div>
        <div class="filter-status no-filters">
            <span class="status-dot"></span>
            <span class="status-text">' . esc_html__( 'No filters applied', 'doc-booker-pro' ) . '</span>
        </div>';

        $html .= '</div>';
        

        FreeHelper::print_html($html);
    }
    public static function render_clinic_thumbnail($post_id, $thumb_size) {
        $clinic_gallery_view    = WpddbOptions::get_option('details_gallery_view','wpddb_clinic_settings') ?:'slider';
        $gallery = get_post_meta( $post_id, 'wpddb_clinic_gallery_images', true );
        if ( ! empty( $gallery ) && is_array( $gallery ) && 'grid' == $clinic_gallery_view) {
            FreeHelper::print_html(Helper::display_custom_gallery($post_id));
        } elseif(! empty( $gallery ) && is_array( $gallery ) && 'slider' == $clinic_gallery_view){
            FreeHelper::print_html(Helper::display_clinic_slider_gallery($post_id));
        }else {
            if ( has_post_thumbnail( $post_id ) ) {
                echo get_the_post_thumbnail( $post_id, $thumb_size );
            } else {
                echo '<img class="wp-post-image wpddb-media no-image" src="' . esc_url( wpddb()->get_assets_uri('public/img/no-image-1210x600.jpg') ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '">';
            }
        }
    }
    public static function render_clinic_gallery_popup(){
        if(! Clinic::is_single_clinic()){
            return;
        }
        ?>
        <div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="pswp__bg"></div>
            <div class="pswp__scroll-wrap">
                <div class="pswp__container">
                    <div class="pswp__item"></div>
                    <div class="pswp__item"></div>
                    <div class="pswp__item"></div>
                </div>
                <div class="pswp__ui pswp__ui--hidden">
                    <div class="pswp__top-bar">
                        <div class="pswp__counter"></div>
                        <button class="pswp__button pswp__button--close" title="<?php esc_attr_e( 'Close (Esc)', 'doc-booker-pro' ); ?>"></button>
                        <button class="pswp__button pswp__button--share" title="<?php esc_attr_e( 'Share', 'doc-booker-pro' ); ?>"></button>
                        <button class="pswp__button pswp__button--fs" title="<?php esc_attr_e( 'Toggle fullscreen', 'doc-booker-pro' ); ?>"></button>
                        <button class="pswp__button pswp__button--zoom" title="<?php esc_attr_e( 'Zoom in/out', 'doc-booker-pro' ); ?>"></button>
                        <div class="pswp__preloader">
                            <div class="pswp__preloader__icn">
                                <div class="pswp__preloader__cut">
                                    <div class="pswp__preloader__donut"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                        <div class="pswp__share-tooltip"></div>
                    </div>
                    <button class="pswp__button pswp__button--arrow--left" title="<?php esc_attr_e( 'Previous (arrow left)', 'doc-booker-pro' ); ?>"></button>
                    <button class="pswp__button pswp__button--arrow--right" title="<?php esc_attr_e( 'Next (arrow right)', 'doc-booker-pro' ); ?>"></button>
                    <div class="pswp__caption">
                        <div class="pswp__caption__center"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Clinic social links on the single-clinic page.
     *
     * The `wpddb_clinic_social_icons` meta was already saved (save_wpddb_clinic_post_meta_pro)
     * and editable in the clinic meta box, but nothing ever rendered it — links entered by
     * an admin went nowhere. Mirrors render_doctor_details_social_icons().
     */
    public static function render_clinic_social_icons() {
        $social_icons = get_post_meta( get_the_ID(), 'wpddb_clinic_social_icons', true );
        if ( empty( $social_icons ) || ! is_array( $social_icons ) ) {
            return;
        }
        $links = array_filter( $social_icons, function ( $icon ) {
            return ! empty( $icon['social_url'] ) && ! empty( $icon['social_name'] );
        } );
        if ( ! $links ) {
            return; // the meta ships as blank rows by default — don't print an empty list
        }
        ?>
        <div class="wpddb-clinic-social-icons">
            <ul>
                <?php foreach ( $links as $icon ) { ?>
                    <li class="<?php echo esc_attr( $icon['social_name'] ); ?>">
                        <a href="<?php echo esc_url( $icon['social_url'] ); ?>" target="_blank" rel="noopener noreferrer"
                           aria-label="<?php echo esc_attr( ucfirst( $icon['social_name'] ) ); ?>">
                            <?php
                            // allHtml=true is required: print_html()'s default wp_kses_post()
                            // strips <svg> entirely, leaving empty icon buttons. Safe here —
                            // the markup is a hardcoded switch in Helper::get_social_icon_svg(),
                            // never user input (the doctor renderer echoes it raw for the same reason).
                            FreeHelper::print_html( Helper::get_social_icon_svg( $icon['social_name'] ), true );
                            ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
        <?php
    }

    public static function render_doctor_details_social_icons(){
        $social_icons = get_post_meta( get_the_ID(), 'wpddb_doctor_social_icons', true );
        if (!empty($social_icons) && is_array($social_icons)) {
        ?>
            <div class="wpddb-doctor-details-social-icons">
                <ul>
                    <?php
                    foreach ($social_icons as $icon) {
                        if (empty($icon['social_url']) || empty($icon['social_name'])) {
                            continue;
                        }
                        echo '<li class="' . esc_attr($icon['social_name']) . '"><a href="' . esc_url($icon['social_url']) . '" target="_blank" rel="noopener noreferrer">';
                        echo Helper::get_social_icon_svg($icon['social_name']);
                        echo '</a></li>';
                    }
                    ?>
                </ul>
            </div>
        <?php
        }
    }
    /**
     * Doctors linked to a clinic, optionally narrowed to one department.
     *
     * The doctor→clinic link is `wpddb_doctor_available_clinic`, a serialised list of
     * clinic IDs as strings (e.g. ["10","11"]) — matched with a LIKE on the quoted id,
     * the same way the free plugin's own clinic lookups do it.
     *
     * @param int $clinic_id
     * @param int $department 0 = all departments.
     * @return int[] Doctor post IDs.
     */
    public static function get_clinic_doctor_ids( $clinic_id, $department = 0 ) {
        $clinic_id = absint( $clinic_id );
        if ( ! $clinic_id ) {
            return [];
        }

        $args = [
            'post_type'              => wpddb()->post_type_doctor,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'     => 'wpddb_doctor_available_clinic',
                    'value'   => '"' . $clinic_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ];

        $department = absint( $department );
        if ( $department ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => wpddb()->doctor_category,
                    'field'    => 'term_id',
                    'terms'    => $department,
                ],
            ];
        }

        return get_posts( apply_filters( 'wpddbp_clinic_doctors_query_args', $args, $clinic_id, $department ) );
    }

    /**
     * Departments that actually have doctors at this clinic, with per-department counts.
     * Only departments with at least one doctor here are offered as filters.
     */
    public static function get_clinic_doctor_departments( $clinic_id ) {
        $ids = self::get_clinic_doctor_ids( $clinic_id );
        if ( ! $ids ) {
            return [];
        }
        $out = [];
        foreach ( $ids as $doctor_id ) {
            foreach ( (array) wp_get_post_terms( $doctor_id, wpddb()->doctor_category ) as $term ) {
                if ( ! isset( $out[ $term->term_id ] ) ) {
                    $out[ $term->term_id ] = [ 'id' => $term->term_id, 'name' => $term->name, 'count' => 0 ];
                }
                $out[ $term->term_id ]['count']++;
            }
        }
        usort( $out, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
        return $out;
    }

    /** One doctor card. Shared by the initial render and the AJAX refresh so both match. */
    public static function render_clinic_doctor_card( $doctor_id ) {
        $terms = wp_get_post_terms( $doctor_id, wpddb()->doctor_category );
        $dept  = ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0]->name : '';
        $degree = get_post_meta( $doctor_id, 'wpddb_doctor_degree', true );
        $spec   = get_post_meta( $doctor_id, 'wpddb_doctor_speciality', true );
        $sub    = $degree ?: $spec;

        $thumb = has_post_thumbnail( $doctor_id )
            ? get_the_post_thumbnail( $doctor_id, 'medium', [ 'alt' => esc_attr( get_the_title( $doctor_id ) ), 'loading' => 'lazy' ] )
            : '<img class="wpddbp-doc-noimg" src="' . esc_url( wpddb()->get_assets_uri( 'public/img/no-image-1210x600.jpg' ) ) . '" alt="" loading="lazy">';

        ob_start();
        ?>
        <a class="wpddbp-doc-card" href="<?php echo esc_url( get_permalink( $doctor_id ) ); ?>">
            <?php FreeHelper::print_html( $thumb ); ?>
            <div class="wpddbp-doc-body">
                <span class="wpddbp-doc-name"><?php echo esc_html( get_the_title( $doctor_id ) ); ?></span>
                <?php if ( $sub ) { ?>
                    <span class="wpddbp-doc-sub"><?php echo esc_html( $sub ); ?></span>
                <?php } ?>
                <span class="wpddbp-doc-dept<?php echo $dept ? '' : ' is-none'; ?>">
                    <?php echo esc_html( $dept ? $dept : __( 'No department', 'doc-booker-pro' ) ); ?>
                </span>
            </div>
        </a>
        <?php
        return ob_get_clean();
    }

    /** How many doctor cards per page / per "Load more" batch. */
    public static function clinic_doctors_per_page() {
        return max( 1, (int) apply_filters( 'wpddbp_clinic_doctors_per_page', 10 ) );
    }

    /** The grid body — reused verbatim by the AJAX handler so both pages match. */
    public static function render_clinic_doctor_grid( $ids ) {
        if ( ! $ids ) {
            return '<div class="wpddbp-doc-empty">' . esc_html__( 'No doctors in this department at this clinic.', 'doc-booker-pro' ) . '</div>';
        }
        $html = '';
        foreach ( $ids as $id ) {
            $html .= self::render_clinic_doctor_card( $id );
        }
        return $html;
    }

    /**
     * One page of doctors for a clinic/department.
     *
     * Slices an id-only query rather than running a second paged query: a clinic has a
     * handful of doctors, and it keeps `total` (needed for the counts + "has more")
     * accurate from the same source the filters are built from.
     *
     * @return array{ids:int[], total:int, has_more:bool}
     */
    public static function get_clinic_doctors_page( $clinic_id, $department = 0, $page = 1 ) {
        $per_page = self::clinic_doctors_per_page();
        $page     = max( 1, absint( $page ) );
        $all      = self::get_clinic_doctor_ids( $clinic_id, $department );
        $total    = count( $all );

        return [
            'ids'      => array_slice( $all, ( $page - 1 ) * $per_page, $per_page ),
            'total'    => $total,
            'has_more' => $total > $page * $per_page,
        ];
    }

    /** Section on the single-clinic page. Renders nothing when the clinic has no doctors. */
    public static function render_clinic_doctors( $post_id = 0 ) {
        $clinic_id = $post_id ? absint( $post_id ) : get_the_ID();
        $first     = self::get_clinic_doctors_page( $clinic_id, 0, 1 );
        if ( ! $first['total'] ) {
            return;
        }
        $departments = self::get_clinic_doctor_departments( $clinic_id );
        ?>
        <div class="wpddbp-clinic-doctors" data-clinic="<?php echo esc_attr( $clinic_id ); ?>">
            <h3 class="wpddb-doctor-inner-title"><?php esc_html_e( 'Doctors at this clinic', 'doc-booker-pro' ); ?></h3>

            <?php if ( count( $departments ) > 1 ) { ?>
                <div class="wpddbp-doc-filters" role="group" aria-label="<?php esc_attr_e( 'Filter doctors by department', 'doc-booker-pro' ); ?>">
                    <button type="button" data-dept="0" aria-pressed="true">
                        <?php esc_html_e( 'All', 'doc-booker-pro' ); ?>
                        <span class="n"><?php echo esc_html( $first['total'] ); ?></span>
                    </button>
                    <?php foreach ( $departments as $dept ) { ?>
                        <button type="button" data-dept="<?php echo esc_attr( $dept['id'] ); ?>" aria-pressed="false">
                            <?php echo esc_html( $dept['name'] ); ?>
                            <span class="n"><?php echo esc_html( $dept['count'] ); ?></span>
                        </button>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="wpddbp-doc-grid" aria-live="polite">
                <?php FreeHelper::print_html( self::render_clinic_doctor_grid( $first['ids'] ) ); ?>
            </div>

            <?php
            // Hidden until there's a second page. Once the list is exhausted the button
            // stays put but flips to a disabled "No more doctors" — same convention as
            // the plugin's own Helper::render_load_more_pagination() ("No More Results").
            // Labels ride on data-attrs so the strings stay translatable in PHP.
            ?>
            <div class="wpddbp-doc-more"<?php echo $first['has_more'] ? '' : ' hidden'; ?>>
                <button type="button" class="wpddbp-doc-loadmore" data-page="1"
                    data-text-more="<?php esc_attr_e( 'Load more doctors', 'doc-booker-pro' ); ?>"
                    data-text-done="<?php esc_attr_e( 'No more doctors', 'doc-booker-pro' ); ?>">
                    <span class="wpddbp-doc-spinner" aria-hidden="true"></span>
                    <span class="wpddbp-doc-loadmore-text"><?php esc_html_e( 'Load more doctors', 'doc-booker-pro' ); ?></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: one page of doctor cards for a clinic + department.
     *
     * `page` 1 replaces the grid (department switch); page > 1 is appended ("Load more").
     */
    public static function ajax_clinic_doctors() {
        $nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, wpddb()->nonceText ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', 'doc-booker-pro' ) ], 403 );
        }

        $clinic_id = isset( $_POST['clinic_id'] ) ? absint( $_POST['clinic_id'] ) : 0;
        if ( ! $clinic_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid clinic.', 'doc-booker-pro' ) ], 400 );
        }

        // 0 (or absent) = all departments — unlike the free endpoint, which demands a category.
        $department = isset( $_POST['department'] ) ? absint( $_POST['department'] ) : 0;
        $page       = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $result     = self::get_clinic_doctors_page( $clinic_id, $department, $page );

        wp_send_json_success( [
            'html'     => self::render_clinic_doctor_grid( $result['ids'] ),
            'total'    => $result['total'],
            'page'     => max( 1, $page ),
            'has_more' => $result['has_more'],
            'shown'    => count( $result['ids'] ),
        ] );
    }

    public static function render_after_doctor_meta_info(){
        $doctor_services = get_post_meta( get_the_ID(), 'wpddb_doctor_services', true );
        // Doctors saved before the empty-value filter below hold [''] rather than [],
        // which is truthy and would render the heading above a blank bullet.
        $doctor_services = is_array( $doctor_services )
            ? array_values( array_filter( array_map( 'trim', $doctor_services ) ) )
            : [];
        $doctor_awards = get_post_meta( get_the_ID(), 'wpddb_doctor_awards', true );
        if ( ! empty( $doctor_services ) ) {
        ?>
            <div class="wpddb-doctor-services">
                <h3 class="wpddb-doctor-services-title"><?php esc_html_e('Services','doc-booker-pro'); ?></h3>
                <ul>
                    <?php
                    foreach ($doctor_services as $service) {
                        echo '<li><span class="icon"><svg fill="#2ff906" width="20px" height="20px" viewBox="0 0 24 24" id="check-mark-circle-2" xmlns="http://www.w3.org/2000/svg" class="icon line" stroke="#2ff906"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path id="primary" d="M20.94,11A8.26,8.26,0,0,1,21,12a9,9,0,1,1-9-9,8.83,8.83,0,0,1,4,1" style="fill: none; stroke: #8ce665; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.5;"></path><polyline id="primary-2" data-name="primary" points="21 5 12 14 8 10" style="fill: none; stroke: #8ce665; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.5;"></polyline></g></svg></span><span class="text">' . esc_html($service) . '</span></li>';
                    }
                    ?>
                </ul>
            </div>
        <?php
        }
        if (!empty($doctor_awards) && is_array($doctor_awards)) {
        ?>
            <div class="wpddb-doctor-awards">
                <h3 class="wpddb-doctor-awards-title"><?php esc_html_e('Awards','doc-booker-pro'); ?></h3>
                <div class="awards-list-wrapper">
                    <?php
                    foreach ($doctor_awards as $award) {
                        if (empty($award['award_image']) && empty($award['award_text'])) {
                            continue;
                        }
                        ?>
                        <div class="award-list">
                            <?php if (!empty($award['award_image'])){?>
                                <img src="<?php echo esc_url($award['award_image']); ?>"  alt="<?php echo esc_attr($award['award_text']); ?>">
                            <?php } ?>
                            <?php if (!empty($award['award_text'])){?>
                                <div class="award-text"><?php echo esc_html($award['award_text']); ?></div>
                            <?php } ?>
                        </div>
                    <?php }
                    ?>
                </div>
            </div>
        <?php
        }
    }
    public static function save_wpddb_settings_pro($settings){
        if (empty($settings['wpddb_global_settings'])) {
            return;
        }
        $incoming = $settings['wpddb_global_settings'];
        $previous = get_option('wpddb_global_settings', []);
        $multi_clinic_new  = sanitize_text_field($incoming['multi_clinic_manage_enable'] ?? 'off');
        $receptionist_new  = sanitize_text_field($incoming['receptionist_manage_enable'] ?? 'off');
        $retention_days    = max(1, min(365, (int) ($incoming['report_retention_days'] ?? 30)));

        $global_settings = [
            'online_booking_payment'       => sanitize_text_field($incoming['online_booking_payment'] ?? 'on'),
            'payment_gateway'              => sanitize_text_field($incoming['payment_gateway'] ?? 'stripe'),

            'stripe_mode'                  => sanitize_text_field($incoming['stripe_mode'] ?? 'test'),
            'stripe_test_publishable_key'  => sanitize_text_field($incoming['stripe_test_publishable_key'] ?? ''),
            'stripe_live_publishable_key'  => sanitize_text_field($incoming['stripe_live_publishable_key'] ?? ''),
            'stripe_test_secret_key'       => sanitize_text_field($incoming['stripe_test_secret_key'] ?? ''),
            'stripe_live_secret_key'       => sanitize_text_field($incoming['stripe_live_secret_key'] ?? ''),

            'razorpay_mode'                => sanitize_text_field($incoming['razorpay_mode'] ?? 'test'),
            'razorpay_test_key_id'         => sanitize_text_field($incoming['razorpay_test_key_id'] ?? ''),
            'razorpay_test_secret_key'     => sanitize_text_field($incoming['razorpay_test_secret_key'] ?? ''),
            'razorpay_live_key_id'         => sanitize_text_field($incoming['razorpay_live_key_id'] ?? ''),
            'razorpay_live_secret_key'     => sanitize_text_field($incoming['razorpay_live_secret_key'] ?? ''),

            'currency'                     => sanitize_text_field($incoming['currency'] ?? 'USD'),
            'currency_position'            => sanitize_text_field($incoming['currency_position'] ?? 'left'),

            // Patients may choose to pay at the clinic instead of online.
            'pay_at_clinic'                => 'on' === ($incoming['pay_at_clinic'] ?? 'off') ? 'on' : 'off',

            // Multi-Clinic toggle (Pro-only feature)
            'multi_clinic_manage_enable'   => $multi_clinic_new === 'on' ? 'on' : 'off',

            // Receptionist (front-desk) management toggle
            'receptionist_manage_enable'   => $receptionist_new === 'on' ? 'on' : 'off',

            // Lab Report Retention (days)
            'report_retention_days'        => $retention_days,
        ];

        update_option('wpddb_global_settings', $global_settings);

        // React to multi-clinic toggle change: install/remove the wpddb_clinic_manager role.
        $previous_mc = $previous['multi_clinic_manage_enable'] ?? 'off';
        if ($multi_clinic_new === 'on' && $previous_mc !== 'on') {
            \WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole::install();
        } elseif ($multi_clinic_new !== 'on' && $previous_mc === 'on') {
            \WpDreamers\WPDDBP\Controllers\MultiClinic\UserRole::uninstall();
        }

        // React to receptionist toggle change: install/remove the wpddb_receptionist role.
        $previous_rc = $previous['receptionist_manage_enable'] ?? 'off';
        if ($receptionist_new === 'on' && $previous_rc !== 'on') {
            \WpDreamers\WPDDBP\Controllers\Receptionist\UserRole::install();
        } elseif ($receptionist_new !== 'on' && $previous_rc === 'on') {
            \WpDreamers\WPDDBP\Controllers\Receptionist\UserRole::uninstall();
        }

        // ── SMS / OTP provider settings (wpddb_sms_settings) ────────────────────
        // The UI only edits 6 keys: enabled, provider_url, method, headers_json,
        // payload_template, success_path. The remaining knobs (user_agent,
        // sslverify, timeout) are preserved from whatever Installation.php
        // seeded — admins shouldn't normally need to touch them.
        if (!empty($settings['wpddb_sms_settings']) && is_array($settings['wpddb_sms_settings'])) {
            $sms      = $settings['wpddb_sms_settings'];
            $previous = get_option('wpddb_sms_settings', []);

            $method = strtoupper(sanitize_text_field($sms['method'] ?? 'POST'));
            if (!in_array($method, ['POST', 'GET'], true)) {
                $method = 'POST';
            }

            // Validate headers_json — fall back to last-known-good or default if it isn't JSON.
            //   - wp_unslash() reverses WP's auto-addslashes on $_POST so the user's literal
            //     quotes survive (e.g. {"Authorization":"Basic <base64>"}).
            //   - JSON_UNESCAPED_SLASHES keeps "application/json" readable (not "application\/json").
            //   - JSON_UNESCAPED_UNICODE keeps non-ASCII (e.g. Bengali, Arabic) readable instead
            //     of \u-encoded escapes.
            $headers_json_raw = (string) ($sms['headers_json'] ?? '');
            $headers_json     = '';
            if ($headers_json_raw !== '') {
                $decoded = json_decode(wp_unslash($headers_json_raw), true);
                if (!is_array($decoded)) {
                    $headers_json = $previous['headers_json'] ?? '{"Content-Type":"application/json"}';
                } else {
                    $headers_json = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            } else {
                $headers_json = $previous['headers_json'] ?? '{"Content-Type":"application/json"}';
            }

            // enabled can arrive as bool or "true"/"false"/"on"/"off" string.
            $raw_enabled = $sms['enabled'] ?? false;
            if (is_string($raw_enabled)) {
                $enabled = !in_array(strtolower($raw_enabled), ['false', '0', '', 'off'], true);
            } else {
                $enabled = (bool) $raw_enabled;
            }

            // Merge UI-edited keys on top of the existing record so non-UI keys
            // (user_agent / sslverify / timeout) are preserved verbatim.
            $sms_settings = array_merge( $previous, [
                'enabled'                  => $enabled,
                'provider_url'             => esc_url_raw(trim((string) ($sms['provider_url'] ?? ''))),
                'method'                   => $method,
                'headers_json'             => $headers_json,
                'payload_template'         => wp_unslash((string) ($sms['payload_template'] ?? '')),
                'success_path'             => sanitize_text_field($sms['success_path'] ?? ''),
                'otp_message_template'     => wp_unslash((string) ($sms['otp_message_template'] ?? '')),
                'booking_message_template' => wp_unslash((string) ($sms['booking_message_template'] ?? '')),
            ] );

            // Belt-and-suspenders defaults in case Installation.php didn't seed
            // (e.g. plugin upgraded from a very old version that pre-dates SMS).
            $sms_settings += [
                'user_agent' => 'Mozilla/5.0 (compatible; DocBookerPro/1.0)',
                'sslverify'  => true,
                'timeout'    => 15,
            ];
            // Empty template fields → fall back to defaults so a save with the
            // textarea cleared doesn't silently break later sends.
            if ( $sms_settings['otp_message_template'] === '' ) {
                $sms_settings['otp_message_template'] = 'Your DocBooker OTP is {{code}}';
            }
            if ( $sms_settings['booking_message_template'] === '' ) {
                $sms_settings['booking_message_template'] = 'Hi {{patient}}, your booking with {{doctor}} on {{time}} at {{clinic}} ({{clinic_address}}) is confirmed. Ref: {{order_code}}';
            }

            update_option('wpddb_sms_settings', $sms_settings);
        }
    }



}