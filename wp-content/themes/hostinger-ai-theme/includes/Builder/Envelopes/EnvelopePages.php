<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Builder\MediaSideloader;
use Hostinger\AiTheme\Builder\Seo;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Throwable;

defined( 'ABSPATH' ) || exit;

class EnvelopePages {

    private MediaSideloader $media;

    private Seo $seo;

    private EnvelopeBookingForm $booking_form;

    private EnvelopeAffiliateShortcodes $affiliate_shortcodes;

    private EnvelopeHostingerEcommerce $hostinger_ecommerce;

    public function __construct(
        MediaSideloader $media,
        ?Seo $seo = null,
        ?EnvelopeBookingForm $booking_form = null,
        ?EnvelopeAffiliateShortcodes $affiliate_shortcodes = null,
        ?EnvelopeHostingerEcommerce $hostinger_ecommerce = null
    ) {
        $this->media                = $media;
        $this->seo                  = $seo ?? new Seo();
        $this->booking_form         = $booking_form ?? new EnvelopeBookingForm();
        $this->affiliate_shortcodes = $affiliate_shortcodes ?? new EnvelopeAffiliateShortcodes();
        $this->hostinger_ecommerce  = $hostinger_ecommerce ?? new EnvelopeHostingerEcommerce();
    }

    public function create( array $envelope, string $editor, array $media_map, ?callable $on_page = null ): array {
        $post_ids     = array();
        $id_map       = array();
        $warnings     = array();
        $home_page_id = (string) ( $envelope['home_page_id'] ?? '' );
        $is_booking   = $this->booking_form->is_booking_envelope( $envelope );

        $explicit_front = 0;
        $home_candidate = 0;

        foreach ( $envelope['pages'] as $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }

            if ( $on_page !== null ) {
                $on_page();
            }

            try {
                $post_id = $this->create_page( $page, $editor, $media_map, $is_booking );
            } catch ( Throwable $e ) {
                $post_id = 0;
            }

            if ( $post_id <= 0 ) {
                $warnings[] = sprintf(
                    'Page failed to apply: %s',
                    (string) ( $page['title'] ?? ( $page['id'] ?? 'unknown' ) )
                );
                continue;
            }

            $post_ids[] = $post_id;
            $this->apply_page_seo( $post_id, $page, $envelope );
            $envelope_id = (string) ( $page['id'] ?? '' );
            if ( $envelope_id !== '' ) {
                $id_map[ $envelope_id ] = $post_id;
                if ( $envelope_id === $home_page_id ) {
                    $explicit_front = $post_id;
                }
            }
            if ( ! empty( $page['is_home'] ) && $home_candidate === 0 ) {
                $home_candidate = $post_id;
            }
        }

        $front_id = $explicit_front;
        if ( $front_id === 0 ) {
            $front_id = $home_candidate !== 0 ? $home_candidate : ( $post_ids[0] ?? 0 );
        }

        if ( $front_id > 0 ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $front_id );
        }

        return array(
            'post_ids' => $post_ids,
            'id_map'   => $id_map,
            'front_id' => $front_id,
            'warnings' => $warnings,
        );
    }

    private function create_page( array $page, string $editor, array $media_map, bool $is_booking ): int {
        $title            = (string) ( $page['title'] ?? '' );
        $slug             = (string) ( $page['slug'] ?? '' );
        $content          = is_array( $page['content'] ?? null ) ? $page['content'] : array();
        $template_content = HostingerEcommerceHelper::is_active()
            ? $this->hostinger_ecommerce->content_for_page( $page, $editor )
            : null;
        if ( $template_content !== null ) {
            $content = $template_content;
        }
        $add_booking_form = $is_booking && $this->booking_form->contains_catalog_shortcode( $content, $editor );

        $postarr = array(
            'post_title'   => sanitize_text_field( $title !== '' ? $title : 'Page' ),
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        );
        if ( $slug !== '' ) {
            $postarr['post_name'] = sanitize_title( $slug );
        }

        $fallback_keyword = sanitize_text_field( $title );

        $page_id = $editor === 'elementor'
            ? $this->create_elementor_page( $postarr, $page, $content, $media_map, $add_booking_form, $fallback_keyword )
            : $this->create_gutenberg_page( $postarr, $content, $media_map, $add_booking_form, $fallback_keyword );

        if ( $page_id <= 0 ) {
            return 0;
        }

        update_post_meta( $page_id, '_wp_page_template', 'no-title' );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );

        if ( $slug !== '' && sanitize_title( $slug ) === 'shop' && get_option( 'hostinger_ai_woo', false ) ) {
            update_option( 'woocommerce_shop_page_id', $page_id );
        }

        return $page_id;
    }

    private function create_elementor_page(
        array $postarr,
        array $page,
        array $content,
        array $media_map,
        bool $add_booking_form,
        string $fallback_keyword
    ): int {
        $rewritten = $this->media->rewrite_elementor( $content, $media_map );
        $rewritten = EnvelopeCustomization::bind_elementor_globals( $rewritten );

        if ( $add_booking_form ) {
            $rewritten[] = $this->booking_form->elementor_form( $page );
        }

        $rewritten = $this->rewrite_shortcodes( $rewritten, 'elementor', $fallback_keyword );

        $page_id = Helper::insert_trusted_post( $postarr );
        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }
        $page_id = (int) $page_id;

        Helper::mark_elementor_page( $page_id );
        Helper::save_elementor_data(
            $page_id,
            (string) wp_json_encode( $rewritten, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        );
        update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );

        return $page_id;
    }

    private function create_gutenberg_page(
        array $postarr,
        array $content,
        array $media_map,
        bool $add_booking_form,
        string $fallback_keyword
    ): int {
        $rewritten = $this->media->rewrite_gutenberg( $content, $media_map );

        if ( $add_booking_form ) {
            $rewritten = $this->booking_form->append_gutenberg_form( $rewritten );
        }

        $rewritten = $this->rewrite_shortcodes( $rewritten, 'gutenberg', $fallback_keyword );

        $postarr['post_content'] = serialize_blocks( $rewritten );

        $page_id = Helper::insert_trusted_post( $postarr );
        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }

        return (int) $page_id;
    }

    private function rewrite_shortcodes( array $nodes, string $editor, string $fallback_keyword ): array {
        if ( HostingerEcommerceHelper::is_active() ) {
            $nodes = $this->hostinger_ecommerce->rewrite_products_shortcodes( $nodes, $editor );
        }

        return $this->affiliate_shortcodes->rewrite( $nodes, $editor, $fallback_keyword );
    }

    // Envelope pages have no seo section; without this, og/meta tags are empty.
    private function apply_page_seo( int $page_id, array $page, array $envelope ): void {
        $title = trim( (string) ( $page['title'] ?? '' ) );
        if ( $title !== '' ) {
            $this->seo->load_seo_title( $page_id, $title );
        }

        $description = trim( (string) ( $page['excerpt'] ?? '' ) );
        if ( $description === '' ) {
            $brand       = is_array( $envelope['brand'] ?? null ) ? $envelope['brand'] : array();
            $description = trim( (string) ( $brand['tagline'] ?? '' ) );
        }
        if ( $description !== '' ) {
            $this->seo->load_seo_description( $page_id, $description );
        }

        $this->seo->add_seo_meta_tags( $page_id );
    }
}
