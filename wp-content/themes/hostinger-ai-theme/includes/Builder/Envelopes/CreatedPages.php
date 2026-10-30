<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\BundleSchema;

defined( 'ABSPATH' ) || exit;

// Restore still reads the legacy created pages map.
class CreatedPages {

    public static function record_all( array $pages, array $id_map ): void {
        $created = array();

        foreach ( $pages as $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }

            $envelope_id = (string) ( $page['id'] ?? '' );
            $post_id     = $envelope_id !== '' ? (int) ( $id_map[ $envelope_id ] ?? 0 ) : 0;

            if ( $post_id <= 0 ) {
                continue;
            }

            $key = $envelope_id !== '' ? sanitize_key( $envelope_id ) : (string) $post_id;

            $created[ $key ] = array(
                'title'   => (string) ( $page['title'] ?? get_the_title( $post_id ) ),
                'page_id' => $post_id,
            );
        }

        update_option( BundleSchema::OPTION_CREATED_PAGES, $created );
    }

    public static function record( string $key, string $title, int $page_id ): void {
        if ( $key === '' || $page_id <= 0 ) {
            return;
        }

        $created = get_option( BundleSchema::OPTION_CREATED_PAGES, array() );

        if ( ! is_array( $created ) ) {
            $created = array();
        }

        $created[ $key ] = array(
            'title'   => $title,
            'page_id' => $page_id,
        );

        update_option( BundleSchema::OPTION_CREATED_PAGES, $created );
    }
}
