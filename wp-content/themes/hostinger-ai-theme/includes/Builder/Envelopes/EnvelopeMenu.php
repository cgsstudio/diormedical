<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\NavigationBuilder;
use Hostinger\AiTheme\Builder\ProductCategoryManager;
use WP_Term;

defined( 'ABSPATH' ) || exit;

class EnvelopeMenu {

    public function build( array $menu, array $id_map, array $post_ids ): int {
        $pages = array();

        foreach ( $menu as $item ) {
            $page = is_array( $item ) ? $this->map_menu_item( $item, $id_map ) : null;
            if ( $page === null ) {
                continue;
            }

            $pages[] = $page;
        }

        if ( empty( $pages ) ) {
            foreach ( $post_ids as $post_id ) {
                $pages[] = array(
                    'title'   => get_the_title( $post_id ),
                    'page_id' => $post_id,
                );
            }
        }

        if ( empty( $pages ) ) {
            return 0;
        }

        return ( new NavigationBuilder( $pages ) )->updateMenus();
    }

    private function map_menu_item( array $item, array $id_map ): ?array {
        $page = $this->map_product_category_menu_item( $item );

        if ( $page === null ) {
            $envelope_id = (string) ( $item['page_id'] ?? '' );
            if ( $envelope_id === '' || ! isset( $id_map[ $envelope_id ] ) ) {
                return null;
            }

            $post_id = $id_map[ $envelope_id ];
            $page    = array(
                'title'   => (string) ( $item['title'] ?? get_the_title( $post_id ) ),
                'page_id' => $post_id,
            );
        }

        $children = array();

        foreach ( $item['children'] ?? array() as $child ) {
            $mapped_child = is_array( $child ) ? $this->map_menu_item( $child, $id_map ) : null;
            if ( $mapped_child !== null ) {
                $children[] = $mapped_child;
            }
        }

        if ( ! empty( $children ) ) {
            $page['children'] = $children;
        }

        return $page;
    }

    private function map_product_category_menu_item( array $item ): ?array {
        if ( ! array_key_exists( 'product_category', $item ) ) {
            return null;
        }

        $slug = sanitize_title( (string) $item['product_category'] );
        if ( $slug === '' ) {
            return null;
        }

        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( ! $term instanceof WP_Term ) {
            $name  = (string) ( $item['title'] ?? ucwords( str_replace( '-', ' ', $slug ) ) );
            $terms = ( new ProductCategoryManager() )->ensure_category_terms(
                array(
                    array(
                        'name' => $name,
                        'slug' => $slug,
                    ),
                )
            );
            $term  = $terms[0] ?? null;
        }

        if ( ! $term instanceof WP_Term ) {
            return null;
        }

        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) {
            return null;
        }

        return array(
            'title'            => (string) ( $item['title'] ?? $term->name ),
            'product_category' => $term->slug,
            'term_id'          => (int) $term->term_id,
            'url'              => $url,
        );
    }
}
