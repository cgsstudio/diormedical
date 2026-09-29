<?php

namespace WpDreamers\WPDDB\Controllers\Frontend;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\ShortcodeQuery;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class DocShortcode {
	use SingletonTrait;

	private $shortCodeId;
	public $postType;
	public $taxonomy;

	public function __construct() {
		add_shortcode( 'wpddbdoctor', [ $this, 'render_shortcode' ] );
		add_shortcode( 'wpddbclinic', [ $this, 'render_shortcode' ] );
        add_shortcode('wpddb_doctor_booking_form', [ $this, 'render_doctor_book_now_shortcode' ]);
	}

	public function render_shortcode( $atts, $content = null, $shortcode_tag = '' ) {
		$shortCodeId = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;

		// Validate shortcode ID
		if ( ! $shortCodeId || is_null( get_post( $shortCodeId ) ) ) {
			return '';
		}
        $html = null;
		$this->shortCodeId = $shortCodeId;

		// Determine the type (doctor or clinic) based on the shortcode tag
		$this->postType = ( $shortcode_tag === 'wpddbdoctor' ) ? wpddb()->post_type_doctor : wpddb()->post_type_clinic;
		$this->taxonomy = ( $shortcode_tag === 'wpddbdoctor' ) ? wpddb()->doctor_category : '';
		$metaMethod     = ( $shortcode_tag === 'wpddbdoctor' ) ? 'doctorMetaScBuilder' : 'clinicMetaScBuilder';
        $cssGeneratorMethod = ( $shortcode_tag === 'wpddbdoctor' ) ? 'doctor_shortcode_css_generator' : 'clinic_shortcode_css_generator';
		$template       = $this->postType === wpddb()->post_type_doctor ? 'doctor' : 'clinic';
		// Get shortcode metadata
		$settingsMeta = get_post_meta( $shortCodeId );
		$metas        = Helper::$metaMethod( $settingsMeta );
        $metas['post_type'] = $this->postType;

		// Initialize variables
		$layoutID       =  esc_attr( $shortcode_tag ) . '-shortcode-container-' . absint( wp_rand() );
        $content_limit_class = function_exists('wpddbp') && isset($metas['content_limit']) ?  'wpddb-limit lines-'.$metas['content_limit'] : '';
		$containerClass =  esc_attr( $shortcode_tag ) . '-shortcode-container wpddb-grid ' . esc_attr( $shortcode_tag ) . '-items ' . esc_attr( $metas['layout'] );
        $containerClass = apply_filters(
            'wpddb_shortcode_container_class',
            $containerClass,
            $shortcode_tag,
            $metas
        );
		$rowClass       =  esc_attr( $shortcode_tag ) . '-shortcode-wrapper columns-' . esc_attr( $metas['grid_columns'].' '.$content_limit_class );
        $html .= Helper::$cssGeneratorMethod($layoutID,$settingsMeta);
		// Start building HTML
		$html .= '<div class="' . esc_attr( $containerClass ) . '" id="' . esc_attr( $layoutID ) . '">';

		// Query posts
		$query = ( new ShortcodeQuery() )
			->buildQueryArgs( $shortCodeId, $metas, $this->postType, $this->taxonomy )
			->get_wpddb_shortcode_posts();

		$temp = Helper::wp_set_temp_query( $query );

		if ( $query->have_posts() ) {
            $metas['template'] =  'shortcode/' . $template . '/layouts/' . $metas['layout'];
            $html .= '<div class="doc-booker-pro-posts-container" data-current-page="1"   data-total-pages="'. esc_attr( $query->max_num_pages ).'"  data-settings="' . esc_attr( wp_json_encode( $metas ) ) . '" >';
            ob_start();
            do_action( 'wpddb_shortcode_results_info', $query, $shortcode_tag, $metas );
            $html .= ob_get_clean();
			$html .= '<div class="' . esc_attr( $rowClass ) . '">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$metas[ $shortcode_tag . '_id' ] = get_the_ID();
				$html                            .= Helper::render_template( 'shortcode/' . $template . '/layouts/' . $metas['layout'], $metas, true );
			}

			$html .= '</div>';

			if ( 'on' === $metas['more_btn'] ) {
				$html .= Helper::get_more_btn_html( $metas['more_btn_url'], $metas['more_btn_text'] );
			}
            ob_start();
            do_action( 'wpddb_shortcode_pagination', $query, $shortcode_tag, $metas );
            $html .= ob_get_clean();
            $html .= '</div>';
		} else {
			$html .= '<p>' . esc_html__( 'No posts found.', 'doc-booker' ) . '</p>';
		}

		$html .= '</div>';

		Helper::wp_reset_temp_query( $temp );

		return $html;
	}

    public function render_doctor_book_now_shortcode($atts, $content = null) {
        global $wpddb_needs_doctor_booking_script;
        $wpddb_needs_doctor_booking_script = true;
        $html                      = null;
        $html                      .= '<div class="wpddb-doctor-booking-form-wrapper">';
        $html                      .= '<h2 class="wpddb-booking-form-title">' . esc_html__( 'Book Your Appointment', 'doc-booker' ) . '</h2>';
        $html                      .= '<div id="wpddb-doctor-booking-form" class="wpddb-doctor-booking-form"></div>';
        $html                      .= '</div>';
        return $html;
    }
}
