<?php

namespace WpDreamers\WPDDB\Controllers\Hooks;
// Do not allow directly accessing this file.
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Controllers\Pagination;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
class TemplateHooks {
	public static function init(  ) {
		add_filter( 'body_class', [__CLASS__, 'body_class'] );
		add_action( 'wpddb_before_main_content_wrapper', [__CLASS__, 'output_main_wrapper_start'], 8 );
		add_action( 'wpddb_before_main_content', [__CLASS__, 'output_content_wrapper'], 10 );
		add_action( 'wpddb_after_main_content', [__CLASS__, 'output_content_wrapper_end'], 10 );
		add_action( 'wpddb_after_main_content_wrapper', [__CLASS__, 'output_main_wrapper_end'], 12 );

		//doctor thumbnail hook
		add_action( 'wpddb_doctor_loop_item_start', [__CLASS__, 'doctor_thumbnail'], 10 );


		//doctor loop item content hook
		add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_wrapper_start'], 10 );
		add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_department'], 15 );
		add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_title'], 20 );
		add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_designation'], 25 );
		add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_description'], 30 );
		add_action( 'wpddb_doctor_loop_item', [__CLASS__, 'doctor_loop_item_wrapper_end'], 100 );
		add_action('wpddb_doctor_loop_item_after_content',[__CLASS__,'wpddb_doctor_pagination'],10);

		//clinic thumbnail hook
		add_action( 'wpddb_clinic_loop_item_start', [__CLASS__, 'clinic_thumbnail'], 10 );

		//clinic loop item content hook
		add_action( 'wpddb_clinic_loop_item', [__CLASS__, 'clinic_loop_item_wrapper_start'], 10 );
		add_action( 'wpddb_clinic_loop_item', [__CLASS__, 'clinic_loop_item_title'], 20 );
		add_action( 'wpddb_clinic_loop_item', [__CLASS__, 'clinic_loop_item_hotline'], 30 );
		add_action( 'wpddb_clinic_loop_item', [__CLASS__, 'clinic_loop_item_description'], 40 );
		add_action( 'wpddb_clinic_loop_item', [__CLASS__, 'clinic_loop_item_details_button'], 50 );
		add_action( 'wpddb_clinic_loop_item', [__CLASS__, 'clinic_loop_item_wrapper_end'], 100 );
		add_action('wpddb_clinic_loop_item_after_content',[__CLASS__,'wpddb_clinic_pagination'],10);
	}

	public static function body_class( $classes ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$classes = (array) $classes;
		if (is_plugin_active('doc-booker/doc-booker.php')){
			$classes[]='wpddb';
		}
		if ( Doctor::is_doctor() || Clinic::is_clinic()) {
			$classes[] = 'wpddb-archive';
		}
		if ( Helper::is_blog_theme() === true ) {
			$classes[] = 'block-theme';
		}

		return $classes;
	}
	public static function output_main_wrapper_start() {
		Helper::print_html( '<div class="wpddb-wrapper">' );
	}

	public static function output_main_wrapper_end() {
		Helper::print_html( '</div>' );
	}

	public static function output_content_wrapper() {
		Helper::render_template( 'global/wrapper-start' );
	}

	public static function output_content_wrapper_end() {
		Helper::render_template( 'global/wrapper-end' );
	}
	/**
	 * Hooks of the portrait card. Registered on every request, not only on
	 * front-end page views: Pro's shortcode pagination draws cards in an AJAX
	 * request, where the rest of these template hooks are not loaded.
	 */
	public static function card_hooks() {
		add_action( 'wpddb_doctor_card_media', [ __CLASS__, 'doctor_card_department' ], 10 );
		add_action( 'wpddb_doctor_card_footer', [ __CLASS__, 'doctor_card_footer' ], 10 );
		// Directory row (Layout 2): working days sit under the description.
		add_action( 'wpddb_doctor_loop_item', [ __CLASS__, 'doctor_row_days' ], 90 );
	}

	public static function doctor_thumbnail( $post_id ) {
		$doctor_args['doctor_id'] = $post_id;
		Helper::render_template( 'doctor/loop/thumbnail', $doctor_args );
	}
	public static function clinic_thumbnail( $post_id ) {
		$clinic_args['clinic_id'] = $post_id;
		Helper::render_template( 'clinic/loop/thumbnail', $clinic_args );
	}
	public static function doctor_loop_item_wrapper_start() {
		Helper::print_html(apply_filters( 'wpddb_doctor_loop_item_wrapper_start', '<div class="doctor-content">')) ;
	}
	public static function clinic_loop_item_wrapper_start() {
		Helper::print_html(apply_filters( 'wpddb_clinic_loop_item_wrapper_start', '<div class="clinic-content">')) ;
	}

	/**
	 * Set while a doctor card is drawn: 'archive' (portrait card on the Doctors
	 * page), 'shortcode' (portrait card in the doctor shortcode), 'row' (the
	 * directory row of Layout 2, both places), false otherwise. On the portrait
	 * card the department sits on the photo, so the usual line under it is
	 * skipped; the shortcode places its own department hook on the photo.
	 *
	 * @var string|false
	 */
	public static $card = false;

	/**
	 * Same rule as the department line: always in the free plugin, and with Pro
	 * when Doctor Settings → Show Department is on.
	 *
	 * @return bool
	 */
	private static function show_department() {
		if ( ! wpddb()->has_pro() ) {
			return true;
		}
		return ( WpddbOptions::get_option( 'show_department', 'wpddb_doctor_settings' ) ?: 'on' ) === 'on';
	}

	public static function doctor_card_department( $post_id ) {
		if ( 'archive' === self::$card && self::show_department() ) {
			Doctor::get_category_html_format( $post_id );
		}
	}

	/**
	 * Working days and the two buttons under a portrait card. Shared by the
	 * Doctors page and the shortcode.
	 *
	 * @param int $post_id Doctor.
	 */
	public static function doctor_card_footer( $post_id ) {
		$link = get_the_permalink( $post_id );
		echo '<div class="doctor-card-footer">';
		// The directory row shows the days in its middle column instead.
		if ( 'row' !== self::$card ) {
			self::doctor_days( $post_id );
		}
		/**
		 * Fires above the card buttons (Pro adds experience, patients and rating).
		 *
		 * @param int $post_id Doctor.
		 */
		do_action( 'wpddb_doctor_card_before_actions', $post_id );
		if ( apply_filters( 'wpddb_doctor_card_show_buttons', true, $post_id ) ) {
			/* translators: %s: doctor's name. */
			$profile_label = sprintf( __( 'View profile of %s', 'doc-booker' ), get_the_title( $post_id ) );
			echo '<div class="doctor-card-actions">';
			echo '<a class="doctor-card-btn is-ghost" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $profile_label ) . '">' . esc_html__( 'View Profile', 'doc-booker' ) . '</a>';
			// Only a doctor with times at an open clinic can be booked.
			if ( Doctor::working_days( $post_id ) ) {
				echo '<a class="doctor-card-btn is-primary" href="' . esc_url( $link . '#wpddb-doctor-details-booking' ) . '">' . esc_html( apply_filters( 'wpddb_doctor_card_book_label', __( 'Book Now', 'doc-booker' ), $post_id ) ) . '</a>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Working-day chips (Mon, Wed …) of a doctor card.
	 *
	 * @param int $post_id Doctor.
	 */
	public static function doctor_days( $post_id ) {
		$days = Doctor::working_days( $post_id );
		if ( ! $days || ! apply_filters( 'wpddb_doctor_card_show_days', true, $post_id ) ) {
			return;
		}
		echo '<ul class="doctor-days" aria-label="' . esc_attr__( 'Working days', 'doc-booker' ) . '">';
		foreach ( $days as $day ) {
			echo '<li>' . esc_html( Doctor::short_day( $day ) ) . '</li>';
		}
		echo '</ul>';
	}

	public static function doctor_row_days( $post_id ) {
		if ( 'row' === self::$card ) {
			self::doctor_days( $post_id );
		}
	}

	public static function doctor_loop_item_department( $post_id ) {
		if ( 'archive' === self::$card ) {
			return;
		}
		// Directory row: department and (with Pro) the featured label and
		// rating share one line of tags above the name.
		if ( 'row' === self::$card ) {
			echo '<div class="doctor-card-tags">';
			if ( self::show_department() ) {
				Doctor::get_category_html_format( $post_id );
			}
			do_action( 'wpddb_doctor_card_media', $post_id );
			echo '</div>';
			return;
		}
        $show_department = true;

        if ( wpddb()->has_pro() ) {
            $show_department         = WpddbOptions::get_option('show_department', 'wpddb_doctor_settings') ?: 'on';
            $show_department = $show_department == 'on';
        }
        if ( $show_department ) {
            Doctor::get_category_html_format(get_the_ID());
        }

    }
	public static function doctor_loop_item_title( $post_id ) {
		?>
		<h3 class="wpddb-doctor-title"><a href="<?php echo esc_url(get_the_permalink($post_id)); ?>"><?php Doctor::the_title( $post_id );?></a></h3>
		<?php
	}

	public static function doctor_loop_item_designation( $post_id ) {

        $show_designation = true;

        if ( wpddb()->has_pro() ) {
            $show_designation         = WpddbOptions::get_option('show_designation', 'wpddb_doctor_settings') ?: 'on';
            $show_designation = $show_designation == 'on';
        }

        if ( $show_designation ) {
            Doctor::doctor_designation( get_the_ID() );
        }

    }
	public static function clinic_loop_item_title( $post_id ) {
		?>
        <h3 class="wpddb-clinic-title"><a href="<?php echo esc_url(get_the_permalink($post_id)); ?>"><?php Clinic::the_title( $post_id );?></a></h3>
		<?php
	}
    public static function clinic_loop_item_hotline( $post_id ) {
	    $clinic_hotline = get_post_meta( $post_id, 'wpddb_clinic_hotline', true );
        if ($clinic_hotline){
		?>
            <div class="wpddb-clinic-hotline">
                <span class="label"><?php esc_html_e('Hotline:','doc-booker'); ?></span>
                <a class="item" href="tel:<?php echo esc_attr($clinic_hotline); ?>"><?php Helper::print_html($clinic_hotline);?></a>
            </div>
		<?php }
	}

	public static function clinic_loop_item_details_button( $post_id ) {
        $show_map_btn = true;
        if ( wpddb()->has_pro() ) {
            $show_map_btn         = WpddbOptions::get_option('show_map_btn', 'wpddb_clinic_settings') ?: 'on';
            $show_map_btn = $show_map_btn == 'on';
            $map_btn_text           = WpddbOptions::get_option( 'map_btn_text','wpddb_clinic_settings') ?:'';
            if ($show_map_btn && $map_btn_text) {
                Clinic::clinic_details_button(get_the_ID(),$map_btn_text);
            }
        }
        if (!wpddb()->has_pro() && $show_map_btn){
            Clinic::clinic_details_button(get_the_ID());
        }
    }

	public static function doctor_loop_item_description( $post_id ) {
        $show_content = true;
        $content = get_the_content();
        if ( empty( $content ) ) {
            return;
        }

        if ( wpddb()->has_pro()  ) {
            $show_content   = WpddbOptions::get_option('show_content', 'wpddb_doctor_settings') ?: 'on';
            $show_content = $show_content == 'on';
            if ($show_content){
                the_content();
            }
            return;
        }
        if ( !wpddb()->has_pro() && $show_content){
            $content = Doctor::get_the_content( get_the_ID() );

            $trimmed = wp_trim_words( $content, 20, '…' );
            echo '<p class="wpddb-doctor-des">' . wp_kses_post( $trimmed ) . '</p>';
        }
	}
	public static function clinic_loop_item_description( $post_id ) {
        $show_content = true;
        $content = get_the_content();
        if ( empty( $content ) ) {
            return;
        }

        if ( wpddb()->has_pro()  ) {
            $show_content   = WpddbOptions::get_option('show_content', 'wpddb_clinic_settings') ?: 'on';
            $show_content = $show_content == 'on';
            if ($show_content){
                the_content();
            }
            return;
        }
        if ( !wpddb()->has_pro() && $show_content){
            $content = Clinic::get_the_content( get_the_ID() );

            $trimmed = wp_trim_words( $content, 20, '…' );
            echo '<p class="wpddb-clinic-des">' . wp_kses_post( $trimmed ) . '</p>';
        }

	}
	public static function doctor_loop_item_wrapper_end() {
		Helper::print_html( '</div>' );
	}
	public static function clinic_loop_item_wrapper_end() {
		Helper::print_html( '</div>' );
	}
	public static function wpddb_doctor_pagination(){
		Pagination::pagination();
	}
	public static function wpddb_clinic_pagination(){
		Pagination::pagination();
	}

}