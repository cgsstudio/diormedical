<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\AffiliateBuilder;
use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\LogoAdapter;
use Hostinger\AiTheme\Builder\ProductCategoryManager;
use WP_Post;
use WP_Theme_JSON;
use WP_Theme_JSON_Resolver;

defined( 'ABSPATH' ) || exit;

class EnvelopeCleaner {

    private AffiliateBuilder $affiliate_builder;

    public function __construct( ?AffiliateBuilder $affiliate_builder = null ) {
        $this->affiliate_builder = $affiliate_builder ?? new AffiliateBuilder();
    }

    public function clean(): void {
        $pages = get_posts(
            array(
                'post_type'   => 'page',
                'post_status' => 'any',
                'numberposts' => -1,
                'fields'      => 'ids',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'    => '_hostinger_ai_generated',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'meta_value'  => '1',
            )
        );

        foreach ( $pages as $page_id ) {
            wp_delete_post( (int) $page_id, true );
        }

        $menu_id = (int) get_option( BundleSchema::OPTION_MENU, 0 );
        if ( $menu_id > 0 ) {
            wp_delete_post( $menu_id, true );
        }

        $this->delete_previous_products();
        $this->affiliate_builder->clear_catalog();
        ( new ProductCategoryManager() )->clear_created_categories();

        foreach ( (array) get_option( BundleSchema::OPTION_BLOG_POSTS, array() ) as $post_id ) {
            wp_delete_post( (int) $post_id, true );
        }
        foreach ( (array) get_option( BundleSchema::OPTION_BLOG_CATS, array() ) as $term_id ) {
            wp_delete_term( (int) $term_id, 'category' );
        }

        $this->remove_sample_hello_world_post();

        remove_theme_mod( 'custom_logo' );
        LogoAdapter::discard_variants();

        delete_option( BundleSchema::OPTION_PAGES );
        delete_option( BundleSchema::OPTION_MENU );
        delete_option( BundleSchema::OPTION_PRODUCTS );
        delete_option( BundleSchema::OPTION_BLOG_POSTS );
        delete_option( BundleSchema::OPTION_BLOG_CATS );
        delete_option( BundleSchema::OPTION_ORIGINAL_LOGO );
        delete_option( BundleSchema::OPTION_CREATED_PAGES );

        $this->reset_user_global_styles();
    }

    private function remove_sample_hello_world_post(): void {
        $hello_world = get_page_by_path( 'hello-world', OBJECT, 'post' );
        if ( ! $hello_world instanceof WP_Post ) {
            return;
        }

        wp_delete_post( (int) $hello_world->ID, true );
    }

    // Force-delete stamped products, including trash (`any` does not cover it).
    private function delete_previous_products(): void {
        $tracked = array_map( 'intval', (array) get_option( BundleSchema::OPTION_PRODUCTS, array() ) );

        $stamped = get_posts(
            array(
                'post_type'   => 'product',
                'post_status' => array( 'any', 'trash' ),
                'numberposts' => -1,
                'fields'      => 'ids',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'    => '_hostinger_ai_generated',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'meta_value'  => '1',
            )
        );

        $product_ids = array_unique( array_merge( $tracked, array_map( 'intval', $stamped ) ) );

        foreach ( $product_ids as $product_id ) {
            if ( $product_id > 0 ) {
                wp_delete_post( $product_id, true );
            }
        }
    }

    // User Global Styles win over the theme layer and would keep a stale palette.
    private function reset_user_global_styles(): void {
        if ( ! class_exists( WP_Theme_JSON_Resolver::class ) ) {
            return;
        }

        $post_id = (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
        if ( $post_id <= 0 ) {
            return;
        }

        $empty = wp_json_encode(
            array(
                'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
                'isGlobalStylesUserThemeJSON' => true,
            )
        );

        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => wp_slash( (string) $empty ),
            )
        );

        WP_Theme_JSON_Resolver::clean_cached_data();
    }
}
