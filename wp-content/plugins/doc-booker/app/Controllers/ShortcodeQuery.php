<?php
/**
 * @package GymBuilder
 */

namespace WpDreamers\WPDDB\Controllers;


use \WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ShortcodeQuery {

	private $args = [];


	private $meta = [];



	private $post_type;



	private $taxonomy;


	private $scID;

	public function buildQueryArgs( int $scID, array $meta, string $post_type, string $taxonomy ) {
		$this->scID      = $scID;
		$this->meta      = $meta;
		$this->post_type = $post_type;
		$this->taxonomy  = $taxonomy;

		return $this;
	}

	public function get_wpddb_shortcode_posts() {
		if ( get_query_var( 'paged' ) ) {
			$paged = get_query_var( 'paged' );
		} else if ( get_query_var( 'page' ) ) {
			$paged = get_query_var( 'page' );
		} else {
			$paged = 1;
		}
		$args = [
			'post_type'           => $this->post_type,
			'ignore_sticky_posts' => 1,
			'post_status'         => 'publish',
			'posts_per_page'      => $this->meta['posts_per_page'],
			'paged'               => $paged

		];

		if ( ! empty( $this->meta['post_in'] ) ) {
			$args['post__in'] = $this->meta['post_in'];
		}

		if ( ! empty( $this->meta['post_not_in'] ) ) {
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			$args['post__not_in'] = $this->meta['post_not_in'];
		}

		if ( ! empty( $this->meta['categories'] ) ) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = [
				[
					'taxonomy' => $this->taxonomy,
					'field'    => 'term_id',
					'terms'    => $this->meta['categories'],
				]
			];
		}
		if (  !empty($this->meta['order_by']) && 'none' !== $this->meta['order_by']  ) {
			$args['orderby'] = $this->meta['order_by'];
		}
		if (  !empty($this->meta['order'])  ) {
			$args['order'] = $this->meta['order'];
		}

		$query_args = apply_filters( 'wpddb_' . $this->post_type . 'shortcode_query', $args,$this->meta );

		return new WP_Query( $query_args );
	}

}