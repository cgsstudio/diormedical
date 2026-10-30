<?php

namespace WpDreamers\WPDDBP\Controllers\Hooks;

use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

class FilterHooks{
    use SingletonTrait;
    public function __construct() {
        add_filter('wpddb_extend_meta_fields',[$this,'extend_meta_fields'],10,2);
        add_filter( 'wpddb_pro_shortcode_doctor_meta', [$this, 'pro_doctor_shortcode_meta_fields'], 10, 2 );
        add_filter( 'wpddb_pro_shortcode_clinic_meta', [$this, 'pro_clinic_shortcode_meta_fields'], 10, 2 );

        add_filter( 'wpddb_pro_doctor_settings_save',[$this,'pro_doctor_settings_save'],10,2);
        add_filter( 'wpddb_pro_clinic_settings_save',[$this,'pro_clinic_settings_save'],10,2);
        add_filter( 'wpddb_pro_style_settings_save',[$this,'pro_style_settings_save'],10,2);

        add_filter( 'wpddb_doctor_meta_sc_builder',[$this,'pro_doctor_meta_sc_builder'],10,2);
        add_filter( 'wpddb_clinic_meta_sc_builder',[$this,'pro_clinic_meta_sc_builder'],10,2);
        add_filter('wpddb_doctor_shortcode_custom_css',[$this,'generate_doctor_shortcode_custom_css'],10,3);
        add_filter('wpddb_clinic_shortcode_custom_css',[$this,'generate_clinic_shortcode_custom_css'],10,3);
        
        add_filter('wpddb_settings_dynamic_css',[$this,'generate_settings_dynamic_css']);

        add_filter('wpddb_get_all_settings_options', [__CLASS__, 'added_pro_settings_options']);

        add_filter('wpddb_' . wpddb()->post_type_doctor . 'shortcode_query',[__CLASS__,'pro_generate_shortcode_query'],10,2);
        add_filter('wpddb_' . wpddb()->post_type_clinic . 'shortcode_query',[__CLASS__,'pro_generate_shortcode_query'],10,2);

        add_filter('wpddb_bookings_query_where', [__CLASS__, 'pro_bookings_query_where'], 10, 2);
    }

    /**
     * Pro-only WHERE fragments for the booking management filters.
     *
     * Each fragment is returned as ['sql' => '...', 'args' => [...]] and joined
     * with AND by the free plugin. Free supplies sanitized args.
     */
    public static function pro_bookings_query_where( array $clauses, array $args ) {
        if ( ! empty( $args['payment_status'] ) ) {
            // 'free' is a UI-side label that maps to "no payment record"
            if ( $args['payment_status'] === 'free' ) {
                $clauses[] = [ 'sql' => "(b.payment_status IS NULL OR b.payment_status = '')", 'args' => [] ];
            } elseif ( in_array( $args['payment_status'], [ 'succeeded', 'completed', 'pending', 'failed', 'requires_action', 'refunded' ], true ) ) {
                $clauses[] = [ 'sql' => 'b.payment_status = %s', 'args' => [ $args['payment_status'] ] ];
            }
        }

        if ( ! empty( $args['payment_by'] ) && in_array( $args['payment_by'], [ 'stripe', 'razorpay', 'offline', 'clinic' ], true ) ) {
            $clauses[] = [ 'sql' => 'b.payment_by = %s', 'args' => [ $args['payment_by'] ] ];
        }

        if ( $args['amount_min'] !== null ) {
            $clauses[] = [ 'sql' => 'b.amount_paid >= %f', 'args' => [ (float) $args['amount_min'] ] ];
        }
        if ( $args['amount_max'] !== null ) {
            $clauses[] = [ 'sql' => 'b.amount_paid <= %f', 'args' => [ (float) $args['amount_max'] ] ];
        }

        return $clauses;
    }
    public function extend_meta_fields($data, $post_id){
        $doctor_services = get_post_meta( $post_id, 'wpddb_doctor_services', true );
        if ( ! empty( $doctor_services ) && is_array( $doctor_services ) ) {
            $data['doctor_services'] = implode( ', ', $doctor_services );
        } else {
            $data['doctor_services'] = '';
        }

        $data['doctor_awards'] = get_post_meta( $post_id, 'wpddb_doctor_awards', true ) ?: [];
        $data['doctor_social_icons'] = get_post_meta( $post_id, 'wpddb_doctor_social_icons', true ) ?: [];
        $data['doctor_booking_fee'] = get_post_meta( $post_id, 'wpddb_doctor_booking_fee', true ) ?: '';

        // Extended doctor profile fields (Pro doctor meta extension — Phase 3).
        $data['doctor_experience_years'] = get_post_meta( $post_id, 'wpddb_doctor_experience_years', true ) ?: '';
        $data['doctor_languages']        = get_post_meta( $post_id, 'wpddb_doctor_languages', true ) ?: [];
        $data['doctor_featured_label']   = get_post_meta( $post_id, 'wpddb_doctor_featured_label', true ) ?: '';
        $data['doctor_credentials']      = get_post_meta( $post_id, 'wpddb_doctor_credentials', true ) ?: [];
        $data['doctor_alma_mater']       = get_post_meta( $post_id, 'wpddb_doctor_alma_mater', true ) ?: '';
        $data['doctor_rating']           = get_post_meta( $post_id, 'wpddb_doctor_rating', true ) ?: '';
        $data['doctor_review_count']     = get_post_meta( $post_id, 'wpddb_doctor_review_count', true ) ?: '';
        $data['doctor_patient_count']    = get_post_meta( $post_id, 'wpddb_doctor_patient_count', true ) ?: '';
        $data['doctor_quote']            = get_post_meta( $post_id, 'wpddb_doctor_quote', true ) ?: '';

        $data['clinic_social_icons'] = get_post_meta( $post_id, 'wpddb_clinic_social_icons', true ) ?: [];
        $data['clinic_gallery_images'] = get_post_meta( $post_id, 'wpddb_clinic_gallery_images', true ) ?: [];

        // Extended clinic profile fields (Pro clinic meta extension — Phase 4).
        $data['clinic_type']                      = get_post_meta( $post_id, 'wpddb_clinic_type', true ) ?: 'clinic';
        $data['clinic_founded_year']              = get_post_meta( $post_id, 'wpddb_clinic_founded_year', true ) ?: '';
        $data['clinic_patient_capacity']          = get_post_meta( $post_id, 'wpddb_clinic_patient_capacity', true ) ?: '';
        $data['clinic_emergency_24_7']            = get_post_meta( $post_id, 'wpddb_clinic_emergency_24_7', true ) ? '1' : '';
        $data['clinic_tagline']                   = get_post_meta( $post_id, 'wpddb_clinic_tagline', true ) ?: '';
        $data['clinic_services']                  = get_post_meta( $post_id, 'wpddb_clinic_services', true ) ?: [];
        $data['clinic_certifications']            = get_post_meta( $post_id, 'wpddb_clinic_certifications', true ) ?: [];
        $data['clinic_hours']                     = get_post_meta( $post_id, 'wpddb_clinic_hours', true ) ?: [];
        $data['clinic_specialist_count_override'] = get_post_meta( $post_id, 'wpddb_clinic_specialist_count_override', true ) ?: '';
        $data['clinic_architect_credit']          = get_post_meta( $post_id, 'wpddb_clinic_architect_credit', true ) ?: '';
        $data['clinic_photographer_credit']       = get_post_meta( $post_id, 'wpddb_clinic_photographer_credit', true ) ?: '';

        return $data;
    }

    public function pro_doctor_shortcode_meta_fields($meta, $post_id) {
        $pro_fields = [
            'pagination_type',
            'pagination_per_page',
            'show_department',
            'show_designation',
            'show_speciality',
            'show_degree',
            'show_workplace',
            'show_social_icons',
            'show_filtering',
            'show_content',
            'content_limit',
            'img_border',
            'img_border_color',
            'img_border_radius',

            'department_font_size',
            'department_font_weight',
            'department_alignment',

            'content_font_size',
            'content_font_weight',
            'content_alignment',

            'title_font_size',
            'title_font_weight',
            'title_alignment',

            'designation_color',
            'designation_font_size',
            'designation_font_weight',
            'designation_alignment',

            'speciality_color',
            'speciality_font_size',
            'speciality_font_weight',
            'speciality_alignment',

            'degree_color',
            'degree_font_size',
            'degree_font_weight',
            'degree_alignment',

            'workplace_color',
            'workplace_font_size',
            'workplace_font_weight',
            'workplace_alignment',
        ];


        foreach ( $pro_fields as $field ) {
            $key = 'doctor_' . $field;
            $value = get_post_meta( $post_id, 'wpddb_' . $key, true );
            $meta[ $key ] = $value ?: '';
        }

        return $meta;
    }

    public function pro_clinic_shortcode_meta_fields($meta, $post_id) {
        $pro_fields = [
            'pagination_type',
            'pagination_per_page',
            'show_content',
            'show_map_btn',
            'content_limit',
            'img_border',
            'img_border_color',
            'img_border_radius',
            'title_font_size',
            'title_font_weight',
            'title_alignment',
            'content_font_size',
            'content_font_weight',
            'content_alignment',
            'phone_color',
            'phone_font_size',
            'phone_font_weight',
            'phone_alignment',
        ];
        foreach ( $pro_fields as $field ) {
            $key = 'clinic_' . $field;
            $value = get_post_meta( $post_id, 'wpddb_' . $key, true );
            $meta[ $key ] = $value ?: '';
        }

        return $meta;
    }

    public function pro_doctor_settings_save($settings, $data) {
        $settings['img_border']         = absint( $data['img_border'] ?? '' );
        $settings['img_border_color']         = sanitize_text_field( $data['img_border_color'] ?? '' );
        $settings['img_border_radius']         = sanitize_text_field( $data['img_border_radius'] ?? '' );
        $settings['show_content']         = sanitize_text_field( $data['show_content'] ?? 'off' );
        $settings['show_department']         = sanitize_text_field( $data['show_department'] ?? 'on' );
        $settings['show_degree']         = sanitize_text_field( $data['show_degree'] ?? 'off' );
        $settings['show_workplace']         = sanitize_text_field( $data['show_workplace'] ?? 'off' );
        $settings['show_speciality']         = sanitize_text_field( $data['show_speciality'] ?? 'off' );
        $settings['show_designation']         = sanitize_text_field( $data['show_designation'] ?? 'off' );
        $settings['show_social_icons']         = sanitize_text_field( $data['show_social_icons'] ?? 'off' );
        $settings['content_limit']         = absint( $data['content_limit'] ?? '3' );
        return $settings;
    }
    public function pro_clinic_settings_save($settings, $data) {
        $settings['img_border']         = absint( $data['img_border'] ?? '' );
        $settings['img_border_color']         = sanitize_text_field( $data['img_border_color'] ?? '' );
        $settings['img_border_radius']         = sanitize_text_field( $data['img_border_radius'] ?? '' );
        $settings['show_content']         = sanitize_text_field( $data['show_content'] ?? 'off' );
        $settings['show_map_btn']         = sanitize_text_field( $data['show_map_btn'] ?? 'off' );
        $settings['map_btn_text']         = sanitize_text_field( $data['map_btn_text'] ?? '' );
        $settings['content_limit']         = absint( $data['content_limit'] ?? '3' );
        $settings['details_gallery_view']         = sanitize_text_field( $data['details_gallery_view'] ?? 'slider' );
        return $settings;
    }

    public function pro_style_settings_save($settings, $data) {
        $settings['wpddb_doctor_title_font_size']       = absint( $data['wpddb_doctor_title_font_size'] ?? '' );
        $settings['wpddb_doctor_title_font_weight']       = absint( $data['wpddb_doctor_title_font_weight'] ?? '700' );
        $settings['wpddb_doctor_title_alignment']       = sanitize_text_field( $data['wpddb_doctor_title_alignment'] ?? 'default' );


        $settings['wpddb_doctor_content_font_size']       = absint( $data['wpddb_doctor_content_font_size'] ?? '' );
        $settings['wpddb_doctor_content_font_weight']       = absint( $data['wpddb_doctor_content_font_weight'] ?? '400' );
        $settings['wpddb_doctor_content_alignment']       = sanitize_text_field( $data['wpddb_doctor_content_alignment'] ?? 'default' );

        $settings['wpddb_doctor_department_color']       = sanitize_text_field( $data['wpddb_doctor_department_color'] ?? '' );
        $settings['wpddb_doctor_department_font_size']       = absint( $data['wpddb_doctor_department_font_size'] ?? '' );
        $settings['wpddb_doctor_department_font_weight']       = absint( $data['wpddb_doctor_department_font_weight'] ?? '500' );
        $settings['wpddb_doctor_department_alignment']       = sanitize_text_field( $data['wpddb_doctor_department_alignment'] ?? 'default' );


        $settings['wpddb_doctor_designation_font_size']       = absint( $data['wpddb_doctor_designation_font_size'] ?? '' );
        $settings['wpddb_doctor_designation_font_weight']       = absint( $data['wpddb_doctor_designation_font_weight'] ?? '500' );
        $settings['wpddb_doctor_designation_alignment']       = sanitize_text_field( $data['wpddb_doctor_designation_alignment'] ?? 'default' );


        $settings['wpddb_doctor_speciality_font_size']       = absint( $data['wpddb_doctor_speciality_font_size'] ?? '' );
        $settings['wpddb_doctor_speciality_font_weight']       = absint( $data['wpddb_doctor_speciality_font_weight'] ?? '500' );
        $settings['wpddb_doctor_speciality_alignment']       = sanitize_text_field( $data['wpddb_doctor_speciality_alignment'] ?? 'default' );

        $settings['wpddb_doctor_degree_font_size']       = absint( $data['wpddb_doctor_degree_font_size'] ?? '' );
        $settings['wpddb_doctor_degree_font_weight']       = absint( $data['wpddb_doctor_degree_font_weight'] ?? '500' );
        $settings['wpddb_doctor_degree_alignment']       = sanitize_text_field( $data['wpddb_doctor_degree_alignment'] ?? 'default' );

        $settings['wpddb_doctor_workplace_font_size']       = absint( $data['wpddb_doctor_workplace_font_size'] ?? '' );
        $settings['wpddb_doctor_workplace_font_weight']       = absint( $data['wpddb_doctor_workplace_font_weight'] ?? '500' );
        $settings['wpddb_doctor_workplace_alignment']       = sanitize_text_field( $data['wpddb_doctor_workplace_alignment'] ?? 'default' );

        $settings['wpddb_clinic_title_font_size']       = absint( $data['wpddb_clinic_title_font_size'] ?? '' );
        $settings['wpddb_clinic_title_font_weight']       = absint( $data['wpddb_clinic_title_font_weight'] ?? '700' );
        $settings['wpddb_clinic_title_alignment']       = sanitize_text_field( $data['wpddb_clinic_title_alignment'] ?? 'default' );

        $settings['wpddb_clinic_content_font_size']       = absint( $data['wpddb_clinic_content_font_size'] ?? '' );
        $settings['wpddb_clinic_content_font_weight']       = absint( $data['wpddb_clinic_content_font_weight'] ?? '400' );
        $settings['wpddb_clinic_content_alignment']       = sanitize_text_field( $data['wpddb_clinic_content_alignment'] ?? 'default' );

        $settings['wpddb_clinic_btn_color']       = sanitize_text_field( $data['wpddb_clinic_btn_color'] ?? '' );
        $settings['wpddb_clinic_btn_hover_color']       = sanitize_text_field( $data['wpddb_clinic_btn_hover_color'] ?? '' );
        $settings['wpddb_clinic_btn_font_size']       = absint( $data['wpddb_clinic_btn_font_size'] ?? '' );
        $settings['wpddb_clinic_btn_font_weight']       = absint( $data['wpddb_clinic_btn_font_weight'] ?? '500' );


        return $settings;
    }

    public function pro_doctor_meta_sc_builder($metas,$raw_meta ) {

        $metas['show_department']      =   ! empty( $raw_meta['wpddb_doctor_show_department'][0] ) ?  $raw_meta['wpddb_doctor_show_department'][0] : 'on';
        $metas['show_designation']     =   ! empty( $raw_meta['wpddb_doctor_show_designation'][0] ) ?  $raw_meta['wpddb_doctor_show_designation'][0] : 'on';
        $metas['show_speciality']      =   ! empty( $raw_meta['wpddb_doctor_show_speciality'][0] ) ?  $raw_meta['wpddb_doctor_show_speciality'][0] : '';
        $metas['show_degree']          =   ! empty( $raw_meta['wpddb_doctor_show_degree'][0] ) ?  $raw_meta['wpddb_doctor_show_degree'][0] : '';
        $metas['show_workplace']       =   ! empty( $raw_meta['wpddb_doctor_show_workplace'][0] ) ?  $raw_meta['wpddb_doctor_show_workplace'][0] : '';
        $metas['show_social_icons']    =   ! empty( $raw_meta['wpddb_doctor_show_social_icons'][0] ) ?  $raw_meta['wpddb_doctor_show_social_icons'][0] : '';
        $metas['show_filtering']    =   ! empty( $raw_meta['wpddb_doctor_show_filtering'][0] ) ?  $raw_meta['wpddb_doctor_show_filtering'][0] : '';
        $metas['show_content']         =   ! empty( $raw_meta['wpddb_doctor_show_content'][0] ) ?  $raw_meta['wpddb_doctor_show_content'][0] : 'on';
        $metas['content_limit']        =   ! empty( $raw_meta['wpddb_doctor_content_limit'][0] ) ?  $raw_meta['wpddb_doctor_content_limit'][0] : '3';
        $metas['pagination_type']      =   ! empty( $raw_meta['wpddb_doctor_pagination_type'][0] ) ?  $raw_meta['wpddb_doctor_pagination_type'][0] : 'none';
        $metas['pagination_per_page']      =   ! empty( $raw_meta['wpddb_doctor_pagination_per_page'][0] ) ?  $raw_meta['wpddb_doctor_pagination_per_page'][0] : '9';
        return $metas;
    }

    public static function pro_clinic_meta_sc_builder($metas,$raw_meta) {

        $metas['show_content']         =   ! empty( $raw_meta['wpddb_clinic_show_content'][0] ) ?  $raw_meta['wpddb_clinic_show_content'][0] : 'on';
        $metas['show_map_btn']         =   ! empty( $raw_meta['wpddb_clinic_show_map_btn'][0] ) ?  $raw_meta['wpddb_clinic_show_map_btn'][0] : 'on';
        $metas['content_limit']        =   ! empty( $raw_meta['wpddb_clinic_content_limit'][0] ) ?  $raw_meta['wpddb_clinic_content_limit'][0] : '3';
        $metas['pagination_type']      =   ! empty( $raw_meta['wpddb_clinic_pagination_type'][0] ) ?  $raw_meta['wpddb_clinic_pagination_type'][0] : 'none';
        $metas['pagination_per_page']      =   ! empty( $raw_meta['wpddb_clinic_pagination_per_page'][0] ) ?  $raw_meta['wpddb_clinic_pagination_per_page'][0] : '9';
        return $metas;
    }
    public  function generate_doctor_shortcode_custom_css($css,$ID, $scMeta) {
        $title_font_size = ! empty( $scMeta['wpddb_doctor_title_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_title_font_size'][0] )
            : null;
        $title_font_weight = ! empty( $scMeta['wpddb_doctor_title_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_title_font_weight'][0] )
            : null;
        $title_alignment = ! empty( $scMeta['wpddb_doctor_title_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_title_alignment'][0] )
            : null;
        $content_font_size = ! empty( $scMeta['wpddb_doctor_content_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_content_font_size'][0] )
            : null;
        $content_font_weight = ! empty( $scMeta['wpddb_doctor_content_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_content_font_weight'][0] )
            : null;
        $content_alignment = ! empty( $scMeta['wpddb_doctor_content_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_content_alignment'][0] )
            : null;
        $department_font_size = ! empty( $scMeta['wpddb_doctor_department_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_department_font_size'][0] )
            : null;
        $department_font_weight = ! empty( $scMeta['wpddb_doctor_department_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_department_font_weight'][0] )
            : null;
        $department_alignment = ! empty( $scMeta['wpddb_doctor_department_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_department_alignment'][0] )
            : null;
        $designation_color = ! empty( $scMeta['wpddb_doctor_designation_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_doctor_designation_color'][0] )
            : null;
        $designation_font_size = ! empty( $scMeta['wpddb_doctor_designation_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_designation_font_size'][0] )
            : null;
        $designation_font_weight = ! empty( $scMeta['wpddb_doctor_designation_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_designation_font_weight'][0] )
            : null;
        $designation_alignment = ! empty( $scMeta['wpddb_doctor_designation_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_designation_alignment'][0] )
            : null;
        $speciality_color = ! empty( $scMeta['wpddb_doctor_speciality_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_doctor_speciality_color'][0] )
            : null;
        $speciality_font_size = ! empty( $scMeta['wpddb_doctor_speciality_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_speciality_font_size'][0] )
            : null;
        $speciality_font_weight = ! empty( $scMeta['wpddb_doctor_speciality_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_speciality_font_weight'][0] )
            : null;
        $speciality_alignment = ! empty( $scMeta['wpddb_doctor_speciality_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_speciality_alignment'][0] )
            : null;
        $degree_color = ! empty( $scMeta['wpddb_doctor_degree_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_doctor_degree_color'][0] )
            : null;
        $degree_font_size = ! empty( $scMeta['wpddb_doctor_degree_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_degree_font_size'][0] )
            : null;
        $degree_font_weight = ! empty( $scMeta['wpddb_doctor_degree_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_degree_font_weight'][0] )
            : null;
        $degree_alignment = ! empty( $scMeta['wpddb_doctor_degree_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_degree_alignment'][0] )
            : null;
        $workplace_color = ! empty( $scMeta['wpddb_doctor_workplace_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_doctor_workplace_color'][0] )
            : null;
        $workplace_font_size = ! empty( $scMeta['wpddb_doctor_workplace_font_size'][0] )
            ? absint( $scMeta['wpddb_doctor_workplace_font_size'][0] )
            : null;
        $workplace_font_weight = ! empty( $scMeta['wpddb_doctor_workplace_font_weight'][0] )
            ? absint( $scMeta['wpddb_doctor_workplace_font_weight'][0] )
            : null;
        $workplace_alignment = ! empty( $scMeta['wpddb_doctor_workplace_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_workplace_alignment'][0] )
            : null;
        $doctor_border_width = ! empty( $scMeta['wpddb_doctor_img_border'][0] )
            ? absint( $scMeta['wpddb_doctor_img_border'][0] )
            : null;
        $doctor_border_radius = ! empty( $scMeta['wpddb_doctor_img_border_radius'][0] )
            ? sanitize_text_field( $scMeta['wpddb_doctor_img_border_radius'][0] )
            : null;
        $doctor_border_color = ! empty( $scMeta['wpddb_doctor_img_border_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_doctor_img_border_color'][0] )
            : null;

        if ( $title_font_size || $title_font_weight || $title_alignment ) {
            $css .= "#{$ID} .doctor-item .wpddb-doctor-title {";
            if( $title_font_size ){
                $css .= 'font-size:' . esc_html($title_font_size) . 'px;';
            }
            if( $title_font_weight ){
                $css .= 'font-weight:' . esc_html($title_font_weight) . ';';
            }
            if( $title_alignment && $title_alignment !='default' ){
                $css .= 'text-align:' . esc_html($title_alignment) . ';';
            }
            $css .= '}';
        }

        if ( $content_font_size || $content_font_weight || $content_alignment ) {
            $css .= "#{$ID} .doctor-item p {";
            if ($content_font_size) {
                $css .= 'font-size:' . esc_html($content_font_size) . 'px;';
            }
            if ($content_font_weight) {
                $css .= 'font-weight:' . esc_html($content_font_weight) . ';';
            }
            if ($content_alignment && $content_alignment !='default') {
                $css .= 'text-align:' . esc_html($content_alignment) . ';';
            }
            $css .= '}';
        }


        if ( $department_font_size || $department_font_weight || $department_alignment ) {
            $css .= "#{$ID} .doctor-item .doctor-department {";
            if($department_font_size){
                $css .= 'font-size:' . esc_html($department_font_size) . 'px;';
            }
            if ($department_font_weight) {
                $css .= 'font-weight:' . esc_html($department_font_weight) . ';';
            }
            if ($department_alignment && $department_alignment !='default') {
                $css .= 'text-align:' . esc_html($department_alignment) . ';';
            }
            $css .= '}';
        }

        if ($designation_color || $designation_font_size || $designation_font_weight || $designation_alignment) {
            $css .= "#{$ID} .doctor-item .doctor-designation {";
            if ($designation_color){
                $css .= 'color:' . esc_html($designation_color) . ';';
            }
            if ($designation_font_size) {
                $css .= 'font-size:' . esc_html($designation_font_size) . 'px;';
            }
            if ($designation_font_weight) {
                $css .= 'font-weight:' . esc_html($designation_font_weight) . ';';
            }
            if ($designation_alignment && $designation_alignment !='default') {
                $css .= 'text-align:' . esc_html($designation_alignment) . ';';
            }
            $css .= '}';
        }
        if ($degree_color || $degree_font_size || $degree_font_weight || $degree_alignment) {
            $css .= "#{$ID} .doctor-item .doctor-degree {";
            if ($degree_color){
                $css .= 'color:' . esc_html($degree_color) . ';';
            }
            if ($degree_font_size) {
                $css .= 'font-size:' . esc_html($degree_font_size) . 'px;';
            }
            if ($degree_font_weight) {
                $css .= 'font-weight:' . esc_html($degree_font_weight) . ';';
            }
            if ($degree_alignment && $degree_alignment !='default') {
                $css .= 'text-align:' . esc_html($degree_alignment) . ';';
            }
            $css .= '}';
        }

        if($workplace_color || $workplace_font_size || $workplace_font_weight || $workplace_alignment){
            $css .= "#{$ID} .doctor-item .doctor-workplace {";
            if ($workplace_color){
                $css .= 'color:' . esc_html($workplace_color) . ';';
            }
            if ($workplace_font_size) {
                $css .= 'font-size:' . esc_html($workplace_font_size) . 'px;';
            }
            if ($workplace_font_weight) {
                $css .= 'font-weight:' . esc_html($workplace_font_weight) . ';';
            }
            if ($workplace_alignment && $workplace_alignment !='default') {
                $css .= 'text-align:' . esc_html($workplace_alignment) . ';';
            }
            $css .= '}';
        }

        if($speciality_color || $speciality_font_size || $speciality_font_weight || $speciality_alignment){
            $css .= "#{$ID} .doctor-item .doctor-speciality {";
            if ($speciality_color){
                $css .= 'color:' . esc_html($speciality_color) . ';';
            }
            if ($speciality_font_size) {
                $css .= 'font-size:' . esc_html($speciality_font_size) . 'px;';
            }
            if ($speciality_font_weight) {
                $css .= 'font-weight:' . esc_html($speciality_font_weight) . ';';
            }
            if ($speciality_alignment && $speciality_alignment !='default') {
                $css .= 'text-align:' . esc_html($speciality_alignment) . ';';
            }
            $css .= '}';
        }
        if (!empty($doctor_border_width) && !empty($doctor_border_color)){
            $css .= "#{$ID} .doctor-item .doctor-thumb {";
            $css .= 'border:' . esc_html($doctor_border_width) . 'px solid ' . esc_html($doctor_border_color) . ';';
            $css .= '}';
        }
        if (!empty($doctor_border_radius)){
            $css .= "#{$ID} .doctor-item .doctor-thumb {";
            $css .= 'border-radius:' . esc_html( is_numeric( $doctor_border_radius ) ? $doctor_border_radius . 'px' : $doctor_border_radius ) .';overflow:hidden';
            $css .= '}';
        }
        return $css;
    }
    public function generate_clinic_shortcode_custom_css( $css,$ID, $scMeta ) {
        $title_font_size = ! empty( $scMeta['wpddb_clinic_title_font_size'][0] )
            ? absint( $scMeta['wpddb_clinic_title_font_size'][0] )
            : null;
        $title_font_weight = ! empty( $scMeta['wpddb_clinic_title_font_weight'][0] )
            ? absint( $scMeta['wpddb_clinic_title_font_weight'][0] )
            : null;
        $title_alignment = ! empty( $scMeta['wpddb_clinic_title_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_clinic_title_alignment'][0] )
            : null;
        $content_font_size = ! empty( $scMeta['wpddb_clinic_content_font_size'][0] )
            ? absint( $scMeta['wpddb_clinic_content_font_size'][0] )
            : null;
        $content_font_weight = ! empty( $scMeta['wpddb_clinic_content_font_weight'][0] )
            ? absint( $scMeta['wpddb_clinic_content_font_weight'][0] )
            : null;
        $content_alignment = ! empty( $scMeta['wpddb_clinic_content_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_clinic_content_alignment'][0] )
            : null;
        $img_border_width = ! empty( $scMeta['wpddb_clinic_img_border'][0] )
            ? absint( $scMeta['wpddb_clinic_img_border'][0] )
            : null;
        $img_border_radius = ! empty( $scMeta['wpddb_clinic_img_border_radius'][0] )
            ? sanitize_text_field( $scMeta['wpddb_clinic_img_border_radius'][0] )
            : null;
        $img_border_color = ! empty( $scMeta['wpddb_clinic_img_border_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_clinic_img_border_color'][0] )
            : null;
        $phone_color = ! empty( $scMeta['wpddb_clinic_phone_color'][0] )
            ? sanitize_hex_color( $scMeta['wpddb_clinic_phone_color'][0] )
            : null;
        $phone_font_size = ! empty( $scMeta['wpddb_clinic_phone_font_size'][0] )
            ? absint( $scMeta['wpddb_clinic_phone_font_size'][0] )
            : null;
        $phone_font_weight = ! empty( $scMeta['wpddb_clinic_phone_font_weight'][0] )
            ? absint( $scMeta['wpddb_clinic_phone_font_weight'][0] )
            : null;
        $phone_alignment = ! empty( $scMeta['wpddb_clinic_phone_alignment'][0] )
            ? sanitize_text_field( $scMeta['wpddb_clinic_phone_alignment'][0] )
            : null;

        if ( $title_font_size || $title_font_weight || $title_alignment ) {
            $css .= "#{$ID} .clinic-item .wpddb-clinic-title {";
            if ($title_font_size) {
                $css .= 'font-size:' . esc_html($title_font_size) . 'px;';
            }
            if ($title_font_weight) {
                $css .= 'font-weight:' . esc_html($title_font_weight);
            }
            if ($title_alignment && $title_alignment !='default') {
                $css .= 'text-align:' . esc_html($title_alignment) . ';';
            }
            $css .= '}';
        }
        if ( $content_font_size || $content_font_weight || $content_alignment ) {
            $css .= "#{$ID} .clinic-item p{";
            if ($content_font_size) {
                $css .= 'font-size:' . esc_html($content_font_size) . 'px;';
            }
            if ($content_font_weight) {
                $css .= 'font-weight:' . esc_html($content_font_weight);
            }
            if ($content_alignment && $content_alignment !='default') {
                $css .= 'text-align:' . esc_html($content_alignment) . ';';
            }
            $css .= '}';
        }
        if (!empty($img_border_width) && !empty($img_border_color)){
            $css .= "#{$ID} .clinic-item .clinic-thumb img {";
            $css .= 'border:' . esc_html($img_border_width) . 'px solid ' . esc_html($img_border_color) . ';';
            $css .= '}';
        }
        if (!empty($img_border_radius)){
            $css .= "#{$ID} .clinic-item .clinic-thumb img{";
            $css .= 'border-radius:' . esc_html( is_numeric( $img_border_radius ) ? $img_border_radius . 'px' : $img_border_radius ) . ';overflow:hidden;';
            $css .= '}';
        }
        if ( $phone_color || $phone_font_size || $phone_font_weight || $phone_alignment ) {
            $css .= "#{$ID} .clinic-item .wpddb-clinic-hotline .item {";
            if ( $phone_color ) {
                $css .= 'color:' . esc_html($phone_color) . ';';
            }
            if ( $phone_font_size ) {
                $css .= 'font-size:' . esc_html($phone_font_size) . 'px;';
            }
            if ( $phone_font_weight ) {
                $css .= 'font-weight:' . esc_html($phone_font_weight);
            }
            if ( $phone_alignment && $phone_alignment !='default' ) {
                $css .= 'text-align:' . esc_html($phone_alignment) . ';';
            }
            $css .= '}';
        }

        return $css;
    }

    public function generate_settings_dynamic_css($css) {
        $doctor_title_font_size     = WpddbOptions::get_option( 'wpddb_doctor_title_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_title_font_weight   = WpddbOptions::get_option( 'wpddb_doctor_title_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_title_alignment     = WpddbOptions::get_option( 'wpddb_doctor_title_alignment', 'wpddb_style_settings' ) ?: '';
        $doctor_content_font_size   = WpddbOptions::get_option( 'wpddb_doctor_content_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_content_font_weight = WpddbOptions::get_option( 'wpddb_doctor_content_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_content_alignment   = WpddbOptions::get_option( 'wpddb_doctor_content_alignment', 'wpddb_style_settings' ) ?: '';
        $doctor_department_color    = WpddbOptions::get_option( 'wpddb_doctor_department_color', 'wpddb_style_settings' ) ?: '';
        $doctor_department_font_size   = WpddbOptions::get_option( 'wpddb_doctor_department_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_department_font_weight = WpddbOptions::get_option( 'wpddb_doctor_department_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_department_alignment   = WpddbOptions::get_option( 'wpddb_doctor_department_alignment', 'wpddb_style_settings' ) ?: '';
        $doctor_designation_color      = WpddbOptions::get_option( 'wpddb_doctor_designation_color', 'wpddb_style_settings' ) ?: '';
        $doctor_designation_font_size  = WpddbOptions::get_option( 'wpddb_doctor_designation_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_designation_font_weight= WpddbOptions::get_option( 'wpddb_doctor_designation_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_designation_alignment  = WpddbOptions::get_option( 'wpddb_doctor_designation_alignment', 'wpddb_style_settings' ) ?: '';
        $doctor_speciality_color       = WpddbOptions::get_option( 'wpddb_doctor_speciality_color', 'wpddb_style_settings' ) ?: '';
        $doctor_speciality_font_size   = WpddbOptions::get_option( 'wpddb_doctor_speciality_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_speciality_font_weight = WpddbOptions::get_option( 'wpddb_doctor_speciality_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_speciality_alignment   = WpddbOptions::get_option( 'wpddb_doctor_speciality_alignment', 'wpddb_style_settings' ) ?: '';
        $doctor_workplace_color        = WpddbOptions::get_option( 'wpddb_doctor_workplace_color', 'wpddb_style_settings' ) ?: '';
        $doctor_workplace_font_size    = WpddbOptions::get_option( 'wpddb_doctor_workplace_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_workplace_font_weight  = WpddbOptions::get_option( 'wpddb_doctor_workplace_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_workplace_alignment    = WpddbOptions::get_option( 'wpddb_doctor_workplace_alignment', 'wpddb_style_settings' ) ?: '';
        $doctor_degree_color           = WpddbOptions::get_option( 'wpddb_doctor_degree_color', 'wpddb_style_settings' ) ?: '';
        $doctor_degree_font_size       = WpddbOptions::get_option( 'wpddb_doctor_degree_font_size', 'wpddb_style_settings' ) ?: '';
        $doctor_degree_font_weight     = WpddbOptions::get_option( 'wpddb_doctor_degree_font_weight', 'wpddb_style_settings' ) ?: '';
        $doctor_degree_alignment       = WpddbOptions::get_option( 'wpddb_doctor_degree_alignment', 'wpddb_style_settings' ) ?: '';
        $clinic_title_font_size        = WpddbOptions::get_option( 'wpddb_clinic_title_font_size', 'wpddb_style_settings' ) ?: '';
        $clinic_title_font_weight      = WpddbOptions::get_option( 'wpddb_clinic_title_font_weight', 'wpddb_style_settings' ) ?: '';
        $clinic_title_alignment        = WpddbOptions::get_option( 'wpddb_clinic_title_alignment', 'wpddb_style_settings' ) ?: '';
        $clinic_content_font_size      = WpddbOptions::get_option( 'wpddb_clinic_content_font_size', 'wpddb_style_settings' ) ?: '';
        $clinic_content_font_weight    = WpddbOptions::get_option( 'wpddb_clinic_content_font_weight', 'wpddb_style_settings' ) ?: '';
        $clinic_content_alignment      = WpddbOptions::get_option( 'wpddb_clinic_content_alignment', 'wpddb_style_settings' ) ?: '';
        $clinic_btn_color              = WpddbOptions::get_option( 'wpddb_clinic_btn_color', 'wpddb_style_settings' ) ?: '';
        $clinic_btn_hover_color        = WpddbOptions::get_option( 'wpddb_clinic_btn_hover_color', 'wpddb_style_settings' ) ?: '';
        $clinic_btn_font_weight        = WpddbOptions::get_option( 'wpddb_clinic_btn_font_weight', 'wpddb_style_settings' ) ?: '';
        $clinic_btn_font_size          = WpddbOptions::get_option( 'wpddb_clinic_btn_font_size', 'wpddb_style_settings' ) ?: '';
        // The doctor image border lives in Doctor Settings (it was read from Clinic Settings).
        $doctor_img_border_color       = WpddbOptions::get_option( 'img_border_color', 'wpddb_doctor_settings' ) ?: '';
        $doctor_img_border_width       = WpddbOptions::get_option( 'img_border', 'wpddb_doctor_settings' ) ?: '';
        $doctor_img_border_radius      = WpddbOptions::get_option( 'img_border_radius', 'wpddb_doctor_settings' ) ?: '';
        $clinic_img_border_color       = WpddbOptions::get_option( 'img_border_color', 'wpddb_clinic_settings' ) ?: '';
        $clinic_img_border_width       = WpddbOptions::get_option( 'img_border', 'wpddb_clinic_settings' ) ?: '';
        $clinic_img_border_radius      = WpddbOptions::get_option( 'img_border_radius', 'wpddb_clinic_settings' ) ?: '';

        if ($doctor_title_font_size || $doctor_title_font_weight || $doctor_title_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item .wpddb-doctor-title{";
            if ($doctor_title_font_size){
                $css .= 'font-size:' . esc_html($doctor_title_font_size) . 'px;';
            }
            if ($doctor_title_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_title_font_weight) . ';';
            }
            if ($doctor_title_alignment && $doctor_title_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_title_alignment) . ';';
            }
            $css .= '}';
        }
        if ($doctor_content_font_size || $doctor_content_font_weight || $doctor_content_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item p{";
            if ($doctor_content_font_size){
                $css .= 'font-size:' . esc_html($doctor_content_font_size) . 'px;';
            }
            if ($doctor_content_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_content_font_weight) . ';';
            }
            if ($doctor_content_alignment && $doctor_content_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_content_alignment) . ';';
            }
            $css .= '}';
        }

        if ($doctor_department_color || $doctor_department_font_size || $doctor_department_font_weight || $doctor_department_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item .doctor-department a{";
            if ($doctor_department_color){
                $css .= 'color:' . esc_html($doctor_department_color) . ';';
            }
            if ($doctor_department_font_size){
                $css .= 'font-size:' . esc_html($doctor_department_font_size) . 'px;';
            }
            if ($doctor_department_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_department_font_weight) . ';';
            }
            if ($doctor_department_alignment && $doctor_department_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_department_alignment) . ';';
            }
            $css .= '}';
        }

        if ($doctor_degree_color || $doctor_degree_font_size || $doctor_degree_font_weight || $doctor_degree_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item .doctor-degree{";
            if ($doctor_degree_color){
                $css .= 'color:' . esc_html($doctor_degree_color) . ';';
            }
            if ($doctor_degree_font_size){
                $css .= 'font-size:' . esc_html($doctor_degree_font_size) . 'px;';
            }
            if ($doctor_degree_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_degree_font_weight) . ';';
            }
            if ($doctor_degree_alignment && $doctor_degree_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_degree_alignment) . ';';
            }
            $css .= '}';
        }
        if ($doctor_designation_color || $doctor_designation_font_size || $doctor_designation_font_weight || $doctor_designation_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item .doctor-designation{";
            if ($doctor_designation_color){
                $css .= 'color:' . esc_html($doctor_designation_color) . ';';
            }
            if ($doctor_designation_font_size){
                $css .= 'font-size:' . esc_html($doctor_designation_font_size) . 'px;';
            }
            if ($doctor_designation_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_designation_font_weight) . ';';
            }
            if ($doctor_designation_alignment && $doctor_designation_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_designation_alignment) . ';';
            }
            $css .= '}';
        }

        if ($doctor_speciality_color || $doctor_speciality_font_size || $doctor_speciality_font_weight || $doctor_speciality_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item .doctor-speciality{";
            if ($doctor_speciality_color){
                $css .= 'color:' . esc_html($doctor_speciality_color) . ';';
            }
            if ($doctor_speciality_font_size){
                $css .= 'font-size:' . esc_html($doctor_speciality_font_size) . 'px;';
            }
            if ($doctor_speciality_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_speciality_font_weight) . ';';
            }
            if ($doctor_speciality_alignment && $doctor_speciality_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_speciality_alignment) . ';';
            }
            $css .= '}';
        }

        if ($doctor_workplace_color || $doctor_workplace_font_size || $doctor_workplace_font_weight || $doctor_workplace_alignment) {
            $css .= ".wpddb-doctor-items .doctor-item .doctor-workplace{";
            if ($doctor_workplace_color){
                $css .= 'color:' . esc_html($doctor_workplace_color) . ';';
            }
            if ($doctor_workplace_font_size){
                $css .= 'font-size:' . esc_html($doctor_workplace_font_size) . 'px;';
            }
            if ($doctor_workplace_font_weight){
                $css .= 'font-weight:' . esc_html($doctor_workplace_font_weight) . ';';
            }
            if ($doctor_workplace_alignment && $doctor_workplace_alignment != 'default'){
                $css .= 'text-align:' . esc_html($doctor_workplace_alignment) . ';';
            }
            $css .= '}';
        }
        if ($doctor_img_border_width && $doctor_img_border_color){
            $css .= ".wpddb-doctor-items .doctor-item .doctor-thumb img {";
            $css .= 'border:' . esc_html($doctor_img_border_width) . 'px solid ' . esc_html($doctor_img_border_color) . ';';
            $css .= '}';
        }
        if ($doctor_img_border_radius){
            $css .= ".wpddb-doctor-items .doctor-item .doctor-thumb img{";
            $css .= 'border-radius:' . esc_html( is_numeric( $doctor_img_border_radius ) ? $doctor_img_border_radius . 'px' : $doctor_img_border_radius ) . ';overflow:hidden;';
            $css .= '}';
        }
        if ($clinic_title_font_size || $clinic_title_font_weight || $clinic_title_alignment) {
            $css .= ".wpddb-clinic-items .clinic-item .wpddb-clinic-title{";
            if ($clinic_title_font_size){
                $css .= 'font-size:' . esc_html($clinic_title_font_size) . 'px;';
            }
            if ($clinic_title_font_weight){
                $css .= 'font-weight:' . esc_html($clinic_title_font_weight) . ';';
            }
            if ($clinic_title_alignment && $clinic_title_alignment != 'default'){
                $css .= 'text-align:' . esc_html($clinic_title_alignment) . ';';
            }
            $css .= '}';
        }
        if($clinic_content_font_size || $clinic_content_font_weight || $clinic_content_alignment){
            $css .= ".wpddb-clinic-items .clinic-item p{";
            if ($clinic_content_font_size){
                $css .= 'font-size:' . esc_html($clinic_content_font_size) . 'px;';
            }
            if ($clinic_content_font_weight){
                $css .= 'font-weight:' . esc_html($clinic_content_font_weight) . ';';
            }
            if ($clinic_content_alignment && $clinic_content_alignment != 'default'){
                $css .= 'text-align:' . esc_html($clinic_content_alignment) . ';';
            }
            $css .= '}';
        }
        if($clinic_btn_color || $clinic_btn_font_size || $clinic_btn_font_weight){
            // The button is a#wpddb-primary-btn, styled by `.wpddb #wpddb-primary-btn`:
            // without the id here that rule wins and the setting never shows.
            $css .= ".wpddb-clinic-items .clinic-item .clinic-details-btn a,.wpddb-clinic-items .clinic-item .clinic-details-btn a#wpddb-primary-btn{";
            if ($clinic_btn_color){
                $css .= 'color:' . esc_html($clinic_btn_color) . ';';
            }
            if ($clinic_btn_font_size){
                $css .= 'font-size:' . esc_html($clinic_btn_font_size) . 'px;';
            }
            if ($clinic_btn_font_weight){
                $css .= 'font-weight:' . esc_html($clinic_btn_font_weight) . ';';
            }
            $css .= '}';
        }
        if($clinic_btn_hover_color){
            $css .= ".wpddb-clinic-items .clinic-item .clinic-details-btn a:hover,.wpddb-clinic-items .clinic-item .clinic-details-btn a#wpddb-primary-btn:hover{";
            $css .= 'color:' . esc_html($clinic_btn_hover_color) . ';';
            $css .= '}';
        }
        if ($clinic_img_border_width && $clinic_img_border_color){
            $css .= ".wpddb-clinic-items .clinic-item .clinic-thumb img {";
            $css .= 'border:' . esc_html($clinic_img_border_width) . 'px solid ' . esc_html($clinic_img_border_color) . ';';
            $css .= '}';
        }
        if ($clinic_img_border_radius){
            $css .= ".wpddb-clinic-items .clinic-item .clinic-thumb img{";
            $css .= 'border-radius:' . esc_html( is_numeric( $clinic_img_border_radius ) ? $clinic_img_border_radius . 'px' : $clinic_img_border_radius ) . ';overflow:hidden;';
            $css .= '}';
        }

        return $css;
    }


    public static function pro_generate_shortcode_query($args, $metas) {
        $pagination = $metas['more_btn'] != 'on';
        $pagination_type = $metas['pagination_type'] != 'none';
        $limit      = ( ( empty( $metas['posts_per_page'] ) || $metas['posts_per_page'] === '-1' ) ? 10000000 : (int) $metas['posts_per_page'] );

        if ( $pagination && $pagination_type) {

            $posts_per_page = ( ! empty( $metas['pagination_per_page'] ) ? intval( $metas['pagination_per_page'] ) : $limit );

            if ( $posts_per_page > $limit ) {
                $posts_per_page = $limit;
            }

            $args['posts_per_page'] = $posts_per_page;

            if ( is_front_page() ) {
                $paged = ( get_query_var( 'page' ) ) ? get_query_var( 'page' ) : 1;
            } else {
                $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
            }

            $offset              = $posts_per_page * ( (int) $paged - 1 );
            $args['paged'] = $paged;

            if ( intval( $args['posts_per_page'] ) > $limit - $offset ) {
                $args['posts_per_page'] = $limit - $offset;
                $args['offset']         = $offset;
            }
        }

        return $args;
    }

    public static function added_pro_settings_options($options) {
        $options[]= 'wpddb_global_settings';
        $options[]= 'wpddb_sms_settings';
        return $options;
    }
}