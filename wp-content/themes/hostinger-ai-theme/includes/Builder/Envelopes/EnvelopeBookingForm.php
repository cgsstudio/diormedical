<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Data\WebsiteTypeHelper;

defined( 'ABSPATH' ) || exit;

class EnvelopeBookingForm {

    public function is_booking_envelope( array $envelope ): bool {
        $woocommerce  = is_array( $envelope['woocommerce'] ?? null ) ? $envelope['woocommerce'] : array();
        $catalog_kind = $woocommerce['catalog_kind'] ?? '';
        $catalog_kind = is_string( $catalog_kind ) ? sanitize_key( $catalog_kind ) : '';

        if ( $catalog_kind === 'service' ) {
            return true;
        }

        foreach ( $woocommerce['products'] ?? array() as $product ) {
            if ( is_array( $product ) && ( $product['catalog_kind'] ?? '' ) === 'service' ) {
                return true;
            }
        }

        return WebsiteTypeHelper::has_website_type( 'booking' );
    }

    public function contains_catalog_shortcode( array $nodes, string $editor ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            $shortcode = '';

            if ( $editor === 'elementor' && ( $node['widgetType'] ?? '' ) === 'shortcode' ) {
                $shortcode = $node['settings']['shortcode'] ?? '';
            }

            if ( $editor !== 'elementor' && ( $node['blockName'] ?? '' ) === 'core/shortcode' ) {
                $shortcode = $node['innerHTML'] ?? '';
            }

            if ( is_string( $shortcode )
                && str_contains( $shortcode, '[products' )
                && str_contains( $shortcode, 'hostinger-ai-services' )
                && ! preg_match( '/\blimit\s*=\s*["\']1["\']/i', $shortcode )
            ) {
                return true;
            }

            foreach ( array( 'elements', 'innerBlocks' ) as $children_key ) {
                $children = $node[ $children_key ] ?? array();

                if ( is_array( $children ) && $this->contains_catalog_shortcode( $children, $editor ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function append_gutenberg_form( array $blocks ): array {
        $booking_blocks = parse_blocks( '<!-- wp:hostinger-ai-theme/booking-block /-->' );

        if ( ! empty( $booking_blocks[0] ) ) {
            $blocks[] = $booking_blocks[0];
        }

        return $blocks;
    }

    public function elementor_form( array $page ): array {
        $seed = (string) ( $page['id'] ?? ( $page['slug'] ?? ( $page['title'] ?? 'services' ) ) );

        return array(
            'id'       => substr( md5( 'hostinger-booking-section-' . $seed ), 0, 7 ),
            'elType'   => 'container',
            'isInner'  => false,
            'settings' => array(
                'content_width'  => 'boxed',
                'flex_direction' => 'column',
                'padding'        => array(
                    'unit'     => 'rem',
                    'top'      => '5',
                    'right'    => '0',
                    'bottom'   => '5',
                    'left'     => '0',
                    'isLinked' => false,
                ),
            ),
            'elements' => array(
                array(
                    'id'         => substr( md5( 'hostinger-booking-widget-' . $seed ), 0, 7 ),
                    'elType'     => 'widget',
                    'widgetType' => 'shortcode',
                    'isInner'    => true,
                    'settings'   => array(
                        'shortcode' => '[hostinger_booking_form]',
                    ),
                    'elements'   => array(),
                ),
            ),
        );
    }
}
