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

	public static function doctor_loop_item_department( $post_id ) {
        $show_department = true;

        if ( function_exists( 'wpddbp' ) ) {
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

        if ( function_exists( 'wpddbp' ) ) {
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
        if ( function_exists( 'wpddbp' ) ) {
            $show_map_btn         = WpddbOptions::get_option('show_map_btn', 'wpddb_clinic_settings') ?: 'on';
            $show_map_btn = $show_map_btn == 'on';
            $map_btn_text           = WpddbOptions::get_option( 'map_btn_text','wpddb_clinic_settings') ?:'';
            if ($show_map_btn && $map_btn_text) {
                Clinic::clinic_details_button(get_the_ID(),$map_btn_text);
            }
        }
        if (!function_exists( 'wpddbp' ) && $show_map_btn){
            Clinic::clinic_details_button(get_the_ID());
        }
    }

	public static function doctor_loop_item_description( $post_id ) {
        $show_content = true;
        $content = get_the_content();
        if ( empty( $content ) ) {
            return;
        }

        if ( function_exists( 'wpddbp' )  ) {
            $show_content   = WpddbOptions::get_option('show_content', 'wpddb_doctor_settings') ?: 'on';
            $show_content = $show_content == 'on';
            if ($show_content){
                the_content();
            }
            return;
        }
        if ( !function_exists( 'wpddbp' ) && $show_content){
            $content = Doctor::get_the_content( get_the_ID() );

            $trimmed = wp_trim_words( $content, 20, '..' );
            echo '<p class="wpddb-doctor-des">' . wp_kses_post( $trimmed ) . '</p>';
        }
	}
	public static function clinic_loop_item_description( $post_id ) {
        $show_content = true;
        $content = get_the_content();
        if ( empty( $content ) ) {
            return;
        }

        if ( function_exists( 'wpddbp' )  ) {
            $show_content   = WpddbOptions::get_option('show_content', 'wpddb_clinic_settings') ?: 'on';
            $show_content = $show_content == 'on';
            if ($show_content){
                the_content();
            }
            return;
        }
        if ( !function_exists( 'wpddbp' ) && $show_content){
            $content = Clinic::get_the_content( get_the_ID() );

            $trimmed = wp_trim_words( $content, 20, '..' );
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