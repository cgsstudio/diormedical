<?php

namespace WpDreamers\WPDDB\Controllers\Model;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

defined( 'ABSPATH' ) || exit;

class QueryBuilder {
	public function __construct() {
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 11 );
	}

	public function pre_get_posts( $query ) {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		if ( wpddb()->post_type_doctor === $query->get( 'post_type' ) ) {

			$posts_per_page  = WpddbOptions::get_option( 'doctor_posts_per_page', 'wpddb_doctor_settings' ) ?: '9';
			$post_in         = WpddbOptions::get_option( "include_doctor", "wpddb_doctor_settings" ) ?: [];
			$post_not_in     = WpddbOptions::get_option( "exclude_doctor", "wpddb_doctor_settings" ) ?: [];
			$post_categories = WpddbOptions::get_option( "doctor_categories", "wpddb_doctor_settings" ) ?: [];
			$post_orderby    = WpddbOptions::get_option( "doctor_orderBy", "wpddb_doctor_settings" ) ?: [];
			$post_order      = WpddbOptions::get_option( "doctor_order", "wpddb_doctor_settings" ) ?: [];

			if ( ! $query->is_single() && ! empty( $post_in ) &&  empty($_GET['wpddb_doctor_category']) && empty($_GET['wpddb_clinic_id'])) {
				$query->set( 'post__in', $post_in );
			}
			if ( ! $query->is_single() && ! empty( $post_not_in ) ) {
				$query->set( 'post__not_in', $post_not_in );
			}
			if ( ! empty( $post_categories ) ) {
				$tax_query = array(
					array(
						'taxonomy' => wpddb()->doctor_category,
						'field'    => 'term_id',
						'terms'    => $post_categories,
						'operator' => 'IN',
					)
				);
				$query->set( 'tax_query', $tax_query );
			}
            if (!empty($_GET['wpddb_doctor_id'])) {
                $query->set('p', absint($_GET['wpddb_doctor_id']));
                return;
            }

            if (!empty($_GET['wpddb_doctor_category'])) {
                $tax_query = $query->get('tax_query') ?: [];
                $tax_query[] = [
                    'taxonomy' => wpddb()->doctor_category,
                    'field'    => 'term_id',
                    'terms'    => [absint($_GET['wpddb_doctor_category'])],
                ];
                $query->set('tax_query', $tax_query);
            }

            if (!empty($_GET['wpddb_clinic_id'])) {
                $meta_query = $query->get('meta_query') ?: [];

                $meta_query[] = [
                    'key'     => 'wpddb_doctor_available_clinic',
                    'value'   => '"' . absint($_GET['wpddb_clinic_id']) . '"',
                    'compare' => 'LIKE',
                ];

                $query->set('meta_query', $meta_query);
            }
			if ( ! empty( $post_orderby ) ) {
				$query->set( 'orderby', $post_orderby );
			}
			if ( ! empty( $post_order ) ) {
				$query->set( 'order', $post_order );
			}
			$query->set( 'posts_per_page', $posts_per_page );

			return;
		}
		if ( wpddb()->post_type_clinic === $query->get( 'post_type' ) ) {

			$clinic_posts_per_page  = WpddbOptions::get_option( 'clinic_posts_per_page', 'wpddb_clinic_settings' ) ?: '9';
			$clinic_post_in         = WpddbOptions::get_option( "include_clinic", "wpddb_clinic_settings" ) ?: [];
			$clinic_post_not_in     = WpddbOptions::get_option( "exclude_clinic", "wpddb_clinic_settings" ) ?: [];
			$clinic_post_orderby    = WpddbOptions::get_option( "clinic_orderBy", "wpddb_clinic_settings" ) ?: [];
			$clinic_post_order      = WpddbOptions::get_option( "clinic_order", "wpddb_clinic_settings" ) ?: [];

			if ( ! empty( $clinic_post_in ) ) {
				$query->set( 'post__in', $clinic_post_in );
			}
			if ( ! empty( $clinic_post_not_in ) ) {
				$query->set( 'post__not_in', $clinic_post_not_in );
			}
			if ( ! empty( $clinic_post_orderby ) ) {
				$query->set( 'orderby', $clinic_post_orderby );
			}
			if ( ! empty( $clinic_post_order ) ) {
				$query->set( 'order', $clinic_post_order );
			}
			$query->set( 'posts_per_page', $clinic_posts_per_page );

			return;
		}
	}
}