<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\Elementor\CssCache;
use WP_Rewrite;
use WP_Theme_JSON_Resolver;

defined( 'ABSPATH' ) || exit;

class EnvelopeCacheBuster {

    public function bust( string $editor, array $page_ids ): void {
        if ( class_exists( WP_Theme_JSON_Resolver::class ) ) {
            WP_Theme_JSON_Resolver::clean_cached_data();
        }

        if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
            wp_clean_theme_json_cache();
        }

        $this->bust_rewrite_rules();
        $this->bust_object_and_page_caches();

        if ( $editor === 'elementor' ) {
            CssCache::clear( $page_ids );
        }
    }

    // New pages 404 until rewrite_rules is rebuilt.
    private function bust_rewrite_rules(): void {
        global $wp_rewrite;

        delete_option( 'rewrite_rules' );

        if ( $wp_rewrite instanceof WP_Rewrite ) {
            $wp_rewrite->flush_rules( false );
        }
    }

    // LiteSpeed object cache can keep serving the pre-apply palette.
    private function bust_object_and_page_caches(): void {
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        if ( has_action( 'litespeed_purge_all_object' ) ) {
            do_action( 'litespeed_purge_all_object' );
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }
}
