<?php
namespace WpDreamers\WPDDBP\Controllers;

use WP_Query;
use WpDreamers\WPDDBP\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Helper\Helper as FreeHelper;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

if (!defined('ABSPATH')) {
    exit('This script cannot be accessed directly.');
}

class AjaxPagination
{

    use SingletonTrait;

    public function __construct() {
        add_action('wp_ajax_load_posts', array($this, 'handle_load_posts'));
        add_action('wp_ajax_nopriv_load_posts', array($this, 'handle_load_posts'));
    }

    /**
     * Handle AJAX load posts request
     */
    public function handle_load_posts() {
        // Verify nonce for security
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : '';
        if (!wp_verify_nonce($nonce, wpddb()->nonceText)) {
            wp_send_json_error('Security check failed');
            return;
        }

        $settings=[];
        // Sanitize and validate input
        $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        // Anyone can call this, so a page is capped rather than taken as asked.
        $posts_per_page = isset($_POST['posts_per_page']) ? min(50, max(1, absint($_POST['posts_per_page']))) : 10;
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
        $layout = isset($_POST['layout']) ? sanitize_text_field($_POST['layout']) : 'layout-1';
        $template = isset($_POST['template']) ? sanitize_text_field(wp_unslash($_POST['template'])) : '';

        // Only the two shortcode post types page through here, and only with
        // their own shortcode layouts: the template name ends up in an include.
        $kinds = [ wpddb()->post_type_doctor => 'doctor', wpddb()->post_type_clinic => 'clinic' ];
        if (!isset($kinds[$post_type])) {
            wp_send_json_error('Invalid request');
            return;
        }
        if ('' !== $template && !preg_match('#^shortcode/' . $kinds[$post_type] . '/layouts/[a-z0-9_-]+$#', $template)) {
            wp_send_json_error('Invalid request');
            return;
        }
        $order_by = isset($_POST['order_by']) ? sanitize_text_field($_POST['order_by']) : 'date';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

        // Handle search and filter parameters
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        $categories = isset($_POST['categories']) ? sanitize_text_field($_POST['categories']) : '';
        $post_in = isset($_POST['post_in']) ? sanitize_text_field($_POST['post_in']) : '';
        $post_not_in = isset($_POST['post_not_in']) ? sanitize_text_field($_POST['post_not_in']) : '';

        $settings['custom_image_size'] = isset( $_REQUEST['thumb_size'] )
            ? Helper::sanitize_custom_img_size( $_REQUEST['thumb_size'] )
            : [];
        $settings['show_content'] = isset($_POST['show_content']) ? sanitize_text_field($_POST['show_content']) : 'on';
        $settings['show_degree'] = isset($_POST['show_degree']) ? sanitize_text_field($_POST['show_degree']) : 'off';
        $settings['show_department'] = isset($_POST['show_department']) ? sanitize_text_field($_POST['show_department']) : 'on';
        $settings['show_designation'] = isset($_POST['show_designation']) ? sanitize_text_field($_POST['show_designation']) : 'on';
        $settings['show_social_icons'] = isset($_POST['show_social_icons']) ? sanitize_text_field($_POST['show_social_icons']) : 'off';
        $settings['show_speciality'] = isset($_POST['show_speciality']) ? sanitize_text_field($_POST['show_speciality']) : 'off';
        $settings['show_workplace'] = isset($_POST['show_workplace']) ? sanitize_text_field($_POST['show_workplace']) : 'off';

        $selected_clinic = isset($_POST['selected_clinic']) ? absint($_POST['selected_clinic']) : 0;
        $selected_department = isset($_POST['selected_department']) ? absint($_POST['selected_department']) : 0;

        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $page,
        );

        if (!empty($order_by) && 'none' !== $order_by) {
            $args['orderby'] = $order_by;
        }
        if (!empty($order)) {
            $args['order'] = $order;
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if (!empty($categories)) {
            $category_ids = explode(',', $categories);
            $category_ids = array_map('absint', $category_ids);
            $category_ids = array_filter($category_ids);

            if (!empty($category_ids)) {
                $taxonomy = $this->get_taxonomy_by_post_type($post_type);

                if ($taxonomy) {
                    $args['tax_query'][] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $category_ids,
                        'operator' => 'IN'
                    );
                }
            }
        }

        if (!empty($post_in)) {
            $post_ids = explode(',', $post_in);
            $post_ids = array_map('absint', $post_ids);
            $post_ids = array_filter($post_ids);
            if (!empty($post_ids)) {
                $args['post__in'] = $post_ids;
            }
        }

        if (!empty($post_not_in)) {
            $excluded_ids = explode(',', $post_not_in);
            $excluded_ids = array_map('absint', $excluded_ids);
            $excluded_ids = array_filter($excluded_ids);
            if (!empty($excluded_ids)) {
                $args['post__not_in'] = $excluded_ids;
            }
        }

        // Add custom meta queries for doctor/clinic post types
//        $args = $this->add_custom_meta_queries($args, $post_type, $_POST);

        if ($post_type === wpddb()->post_type_doctor) {
            $args = $this->apply_doctor_filters($args, $selected_clinic, $selected_department);
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {

            $posts_html = $this->generate_posts_html($query,$settings,$template, $post_type);

            $response_data = array(
                'posts_html' => $posts_html,
                'current_page' => $page,
                'total_pages' => $query->max_num_pages,
                'total_posts' => $query->found_posts,
                'posts_per_page' => $posts_per_page,
                'post_type' => $post_type,
                'has_more' => $page < $query->max_num_pages
            );

            wp_reset_postdata();
            wp_send_json_success($response_data);
        } else {
            wp_reset_postdata();

            $error_data = array(
                'message' => 'No posts found',
                'current_page' => $page,
                'total_pages' => $query->max_num_pages,
                'total_posts' => $query->found_posts,
                'post_type' => $post_type,
            );

            wp_send_json_error($error_data);
        }
    }

    /**
     * Generate HTML for posts based on layout and template
     */
    private function generate_posts_html($query, $settings, $template, $post_type) {
        $html = '';

        while ($query->have_posts()) {
            $query->the_post();
            if ($post_type == wpddb()->post_type_doctor){
                $settings['wpddbdoctor_id'] = get_the_ID();
            }else{
                $settings['wpddbclinic_id'] = get_the_ID();
            }
            if (!empty($template)) {
                $html                            .= FreeHelper::render_template( $template, $settings, true );
            } else {
                $html .= $this->generate_default_post_html($post_type);
            }
        }

        return $html;
    }

    /**
     * Generate default post HTML based on layout
     */
    private function generate_default_post_html( $post_type) {
        $post_id = get_the_ID();
        $title = get_the_title();
        $excerpt = get_the_excerpt();
        $permalink = get_permalink();
        $thumbnail = get_the_post_thumbnail($post_id, 'medium');
        $item_class = $post_type == wpddb()->post_type_doctor ? 'doctor' : 'clinic';

        $html = '<div class="wpddb-shortcode-item '.esc_attr($item_class).'-item posts-item">';


        if ($thumbnail) {
            $html .= '<div class=" '.$item_class.'-thumb"><a href="' . esc_url($permalink) . '">' . $thumbnail . '</a></div>';
        }
        $html .= '<div class=" '.$item_class.'-content">';
        $html .= '<h3 class="post-title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';
        if ($excerpt) {
            $html .= '<div class="post-excerpt">' . wp_kses_post($excerpt) . '</div>';
        }

        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }


    /**
     * Add custom meta queries based on post type and filters
     */
    private function add_custom_meta_queries($args, $post_type, $post_data) {
        // Handle both doctor and wpddb_doctor post types
        if ( $post_type === 'wpddb_doctor') {
            // Doctor specific filters
            if (!empty($post_data['doctor_specialty'])) {
                $args['meta_query'][] = array(
                    'key' => 'doctor_specialty',
                    'value' => sanitize_text_field($post_data['doctor_specialty']),
                    'compare' => 'LIKE'
                );
            }

            if (!empty($post_data['doctor_location'])) {
                $args['meta_query'][] = array(
                    'key' => 'doctor_location',
                    'value' => sanitize_text_field($post_data['doctor_location']),
                    'compare' => 'LIKE'
                );
            }

            if (!empty($post_data['min_experience'])) {
                $args['meta_query'][] = array(
                    'key' => 'doctor_experience',
                    'value' => absint($post_data['min_experience']),
                    'type' => 'NUMERIC',
                    'compare' => '>='
                );
            }
        } elseif ($post_type === 'wpddb_clinic') {
            // Clinic specific filters
            if (!empty($post_data['clinic_location'])) {
                $args['meta_query'][] = array(
                    'key' => 'clinic_address',
                    'value' => sanitize_text_field($post_data['clinic_location']),
                    'compare' => 'LIKE'
                );
            }

            if (!empty($post_data['clinic_services'])) {
                $args['meta_query'][] = array(
                    'key' => 'clinic_services',
                    'value' => sanitize_text_field($post_data['clinic_services']),
                    'compare' => 'LIKE'
                );
            }
        }

        // Set relation for multiple meta queries
        if (isset($args['meta_query']) && count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }

        return $args;
    }

    private function apply_doctor_filters($args, $selected_clinic, $selected_department) {
        $meta_query = $args['meta_query'] ?? [];
        $tax_query = $args['tax_query'] ?? [];

        if ($selected_clinic > 0) {
            $meta_query[] = [
                'key' => 'wpddb_doctor_available_clinic',
                'value' => '"' . $selected_clinic . '"',
                'compare' => 'LIKE'
            ];
        }

        if ($selected_department > 0) {
            $tax_query[] = [
                'taxonomy' => wpddb()->doctor_category,
                'field' => 'term_id',
                'terms' => $selected_department,
                'operator' => 'IN'
            ];
        }

        if (count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    /**
     * Get appropriate taxonomy for post type
     */
    private function get_taxonomy_by_post_type($post_type) {
        $taxonomies = array(
            'wpddb_doctor' => wpddb()->doctor_category,
            'post' => 'category'
        );

        return isset($taxonomies[$post_type]) ? $taxonomies[$post_type] : 'category';
    }


}
