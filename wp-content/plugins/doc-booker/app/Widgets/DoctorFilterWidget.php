<?php

namespace WpDreamers\WPDDB\Widgets;
use WP_Query;
use WP_Widget;
use WpDreamers\WPDDB\Controllers\Admin\Models\Widgets\WidgetFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class DoctorFilterWidget extends WP_Widget {
    public function __construct() {
        $id = 'wpddb_doctor_filter_widget';
        parent::__construct(
            $id,
            esc_html__( 'DocBooker: Doctor Filter', 'doc-booker' ),
            [
                'description'                 => esc_html__( 'DocBooker Doctor Filter widget.', 'doc-booker' ),
                'customize_selective_refresh' => true,
            ] );
    }
    public function widget( $args, $instance ) {


        $title     = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
        $filter_style       = ( ! empty( $instance['widget_style'] ) ) ? wp_kses_post( $instance['widget_style'] ) : 'vertical';
        echo wp_kses_post( $args['before_widget'] );
        if ( $title ) {
            echo wp_kses_post( $args['before_title'] ) . $title . wp_kses_post( $args['after_title'] );
        }
        echo '<div class="wpddb-doctor-filter-widget-wrapper '.esc_attr($filter_style).'">';
        ?>
        <form action="<?php echo esc_url(get_post_type_archive_link(wpddb()->post_type_doctor)); ?>" method="get" id="wpddb-doctor-filter-form" class="wpddb-doctor-filter-form">
            <div class="wpddb-field-wrapper">
                <div class="wpddb-field">
                    <label for="wpddb_doctor_category"><?php esc_html_e('Select Doctor Department','doc-booker'); ?></label>
                    <select name="wpddb_doctor_category" id="wpddb_doctor_category">
                        <option value=""><?php esc_html_e('Select Doctor Department','doc-booker'); ?></option>
                        <?php
                        $selected_cat = isset($_GET['wpddb_doctor_category']) ? absint($_GET['wpddb_doctor_category']) : '';
                        $terms = get_terms([
                            'taxonomy' => wpddb()->doctor_category,
                            'hide_empty' => false,
                        ]);
                        if ($terms) {
                            foreach ($terms as $term) {
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    $term->term_id,
                                    selected($selected_cat, $term->term_id, false),
                                    esc_html($term->name)
                                );
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="wpddb-field">
                    <label for="wpddb_clinic_id"><?php esc_html_e('Select Clinic','doc-booker'); ?></label>
                    <select name="wpddb_clinic_id" id="wpddb_clinic_id" <?php echo $selected_cat ? '' : 'disabled'; ?>>
                        <option value=""><?php esc_html_e('Select Clinic','doc-booker'); ?></option>
                        <?php
                        // If already selected, populate clinics on load
                        if ($selected_cat && !empty($_GET['wpddb_clinic_id'])) {
                            $selected_clinic = absint($_GET['wpddb_clinic_id']);
                            $doctors = get_posts([
                                'post_type' => wpddb()->post_type_doctor,
                                'post_status' => 'publish',
                                'tax_query' => [[
                                    'taxonomy' => wpddb()->doctor_category,
                                    'field'    => 'term_id',
                                    'terms'    => $selected_cat,
                                ]],
                                'posts_per_page' => -1,
                            ]);
                            $clinic_ids = [];
                            foreach ($doctors as $doctor) {
                                $clinics = get_post_meta($doctor->ID, 'wpddb_doctor_available_clinic', true);
                                if (is_array($clinics)) {
                                    foreach ($clinics as $cid) {
                                        if (!in_array($cid, $clinic_ids)) {
                                            $clinic_ids[] = $cid;
                                        }
                                    }
                                }
                            }
                            foreach ($clinic_ids as $clinic_id) {
                                $title = get_the_title($clinic_id);
                                printf('<option value="%d" %s>%s</option>', $clinic_id, selected($selected_clinic, $clinic_id, false), esc_html($title));
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="wpddb-field">
                    <label for="wpddb_doctor_id"><?php esc_html_e('Select Doctor','doc-booker'); ?></label>
                    <select name="wpddb_doctor_id" id="wpddb_doctor_id" <?php echo !empty($_GET['wpddb_clinic_id']) ? '' : 'disabled'; ?>>
                        <option value=""><?php esc_html_e('Select Doctor','doc-booker'); ?></option>
                        <?php

                        if (!empty($_GET['wpddb_doctor_category']) && !empty($_GET['wpddb_clinic_id'])) {
                            $selected_doctor = absint($_GET['wpddb_doctor_id']);
                            $clinic_id = absint($_GET['wpddb_clinic_id']);
                            $query = new WP_Query([
                                'post_type'      => wpddb()->post_type_doctor,
                                'post_status'    => 'publish',
                                'posts_per_page' => -1,
                                'meta_query'     => [
                                    [
                                        'key'     => 'wpddb_doctor_available_clinic',
                                        'value'   => '"' . $clinic_id . '"',
                                        'compare' => 'LIKE',
                                    ],
                                ],
                                'tax_query'      => [
                                    [
                                        'taxonomy' => wpddb()->doctor_category,
                                        'field'    => 'term_id',
                                        'terms'    => $selected_cat,
                                    ],
                                ],
                            ]);
                            if ( $query->have_posts() ) {
                                foreach ( $query->posts as $doctor ) {
                                    printf('<option value="%d" %s>%s</option>', $doctor->ID, selected($selected_doctor, $doctor->ID, false), esc_html($doctor->post_title));
                                }
                                wp_reset_postdata();
                            }

                        }
                        ?>
                    </select>
                </div>
            </div>

            <button class="wpddb-filter-submit" type="submit"><?php esc_html_e('Filter','doc-booker'); ?></button>
        </form>


        <?php echo '</div>';
        echo wp_kses_post( $args['after_widget'] );


    }
    public function update( $new_instance, $old_instance ) {
        $instance                   = [];
        $instance['title']          = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['widget_style']       = ( ! empty( $new_instance['widget_style'] ) ) ? wp_kses_post( $new_instance['widget_style'] ) : 'vertical';


        return $instance;
    }
    public function form( $instance ) {
        $defaults = [
            'title'          => '',
            'widget_style'         => 'vertical',
            
        ];
        $instance = wp_parse_args( (array) $instance, $defaults );

        $fields = [
            'title'          => [
                'label' => esc_html__( 'Title', 'doc-booker' ),
                'type'  => 'text',
            ],
            'widget_style'          => [
                'label'   => esc_html__( 'Widget Style', 'doc-booker' ),
                'type'    => 'select',
                'options' => array(
                    'inline'  => 'Inline',
                    'vertical' => 'Vertical',
                ),
            ],
            
        ];

        WidgetFields::display( $fields, $instance, $this );
    }
}