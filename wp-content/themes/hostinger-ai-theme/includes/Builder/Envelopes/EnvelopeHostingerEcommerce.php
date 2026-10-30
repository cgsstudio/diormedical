<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\Elementor\KitIds;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Builder\HostingerEcommerceBuilder;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;

defined( 'ABSPATH' ) || exit;

class EnvelopeHostingerEcommerce {

    public const ENVELOPE_ID = 'hostinger-ecommerce-shop';

    public function content_for_page( array $page, string $editor ): ?array {
        if ( ( $page['content_template'] ?? '' ) !== self::ENVELOPE_ID ) {
            return null;
        }

        $title = sanitize_text_field( (string) ( $page['title'] ?? '' ) );
        if ( $title === '' ) {
            $title = __( 'Shop', 'hostinger-ai-theme' );
        }

        return $editor === 'elementor'
            ? $this->elementor_shop_content( $title )
            : $this->gutenberg_shop_content( $title );
    }

    public function ensure_shop_page( array &$envelope, string $editor, array &$pages ): void {
        $shop_page_id = $this->find_existing_shop_page( $envelope, $pages['id_map'] );

        if ( $shop_page_id <= 0 ) {
            $shop_page_id = $this->create_shop_page( $editor );
        }

        if ( $shop_page_id <= 0 ) {
            return;
        }

        $this->track_page( $shop_page_id, $pages );

        update_option( HostingerEcommerceHelper::PRODUCTS_PAGE_ID_OPTION, $shop_page_id );

        $this->ensure_cart_page( $pages );

        $pages['id_map'][ self::ENVELOPE_ID ] = $shop_page_id;
        $envelope['menu']                     = $this->relink_shop_menu_items( $envelope['menu'] ?? array() );
    }

    public function ensure_cart_page( array &$pages ): void {
        $cart_page_id = (int) get_option( HostingerEcommerceHelper::CART_PAGE_ID_OPTION, 0 );

        if ( $cart_page_id <= 0 || get_post_status( $cart_page_id ) !== 'publish' ) {
            $cart_page_id = $this->create_cart_page();
        }

        if ( $cart_page_id <= 0 ) {
            return;
        }

        update_option( HostingerEcommerceHelper::CART_PAGE_ID_OPTION, $cart_page_id );

        $this->track_page( $cart_page_id, $pages );
    }

    public function rewrite_products_shortcodes( array $nodes, string $editor ): array {
        foreach ( $nodes as $index => $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( $editor === 'elementor' && ( $node['widgetType'] ?? '' ) === 'shortcode' ) {
                $shortcode = $node['settings']['shortcode'] ?? '';
                if ( is_string( $shortcode ) ) {
                    $node['settings']['shortcode'] = $this->replace_products_shortcode( $shortcode );
                }
            }

            if ( $editor !== 'elementor' && ( $node['blockName'] ?? '' ) === 'core/shortcode' ) {
                if ( isset( $node['innerHTML'] ) && is_string( $node['innerHTML'] ) ) {
                    $node['innerHTML'] = $this->replace_products_shortcode( $node['innerHTML'] );
                }

                if ( isset( $node['innerContent'] ) && is_array( $node['innerContent'] ) ) {
                    $node['innerContent'] = array_map(
                        fn( $chunk ) => is_string( $chunk ) ? $this->replace_products_shortcode( $chunk ) : $chunk,
                        $node['innerContent']
                    );
                }
            }

            foreach ( array( 'elements', 'innerBlocks' ) as $children_key ) {
                if ( isset( $node[ $children_key ] ) && is_array( $node[ $children_key ] ) ) {
                    $node[ $children_key ] = $this->rewrite_products_shortcodes( $node[ $children_key ], $editor );
                }
            }

            $nodes[ $index ] = $node;
        }

        return $nodes;
    }

    private function replace_products_shortcode( string $shortcode ): string {
        return (string) preg_replace(
            '/\[products\b[^\]]*\]/i',
            HostingerEcommerceBuilder::PRODUCTS_SHORTCODE,
            $shortcode
        );
    }

    private function track_page( int $page_id, array &$pages ): void {
        if ( in_array( $page_id, $pages['post_ids'], true ) ) {
            return;
        }

        $pages['post_ids'][] = $page_id;
        update_option( BundleSchema::OPTION_PAGES, $pages['post_ids'] );
    }

    private function create_cart_page(): int {
        $title   = __( 'Cart', 'hostinger-ai-theme' );
        $page_id = Helper::insert_trusted_post(
            array(
                'post_title'   => $title,
                'post_name'    => 'cart',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '[' . HostingerEcommerceHelper::CART_SHORTCODE . ']',
                'meta_input'   => array(
                    GenerationConstant::META_KEY => '1',
                ),
            )
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }
        $page_id = (int) $page_id;

        $this->mark_generated_page( $page_id );
        CreatedPages::record( 'cart', $title, $page_id );

        return $page_id;
    }

    private function find_existing_shop_page( array $envelope, array $id_map ): int {
        foreach ( $envelope['pages'] ?? array() as $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }

            $is_shop_page = ( $page['content_template'] ?? '' ) === self::ENVELOPE_ID
                || sanitize_title( (string) ( $page['slug'] ?? '' ) ) === 'shop';

            if ( ! $is_shop_page ) {
                continue;
            }

            $envelope_id = (string) ( $page['id'] ?? '' );
            $post_id     = $envelope_id !== '' ? (int) ( $id_map[ $envelope_id ] ?? 0 ) : 0;

            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        return 0;
    }

    private function elementor_shop_content( string $title ): array {
        return array(
            array(
                'id'       => '7ec0a11',
                'elType'   => 'container',
                'settings' => array(
                    'padding'        => array(
                        'unit'     => 'rem',
                        'top'      => '2.25',
                        'right'    => '2.25',
                        'bottom'   => '2.25',
                        'left'     => '2.25',
                        'isLinked' => false,
                    ),
                    'padding_mobile' => array(
                        'unit'     => 'rem',
                        'top'      => '1',
                        'right'    => '1',
                        'bottom'   => '1',
                        'left'     => '1',
                        'isLinked' => false,
                    ),
                ),
                'elements' => array(
                    array(
                        'id'         => '7ec0a12',
                        'elType'     => 'widget',
                        'settings'   => array(
                            'title'       => $title,
                            'header_size' => 'h1',
                            '__globals__' => array(
                                'title_color' => 'globals/colors?id=' . KitIds::PAGE_TEXT,
                            ),
                            '_padding'    => array(
                                'unit'     => 'px',
                                'top'      => '0',
                                'right'    => '24',
                                'bottom'   => '0',
                                'left'     => '24',
                                'isLinked' => false,
                            ),
                        ),
                        'elements'   => array(),
                        'widgetType' => 'heading',
                    ),
                    array(
                        'id'         => '7ec0a13',
                        'elType'     => 'widget',
                        'settings'   => array(
                            'shortcode' => HostingerEcommerceBuilder::PRODUCTS_SHORTCODE,
                        ),
                        'elements'   => array(),
                        'widgetType' => 'shortcode',
                    ),
                ),
                'isInner'  => false,
            ),
        );
    }

    private function gutenberg_shop_content( string $title ): array {
        $heading = '<h1 class="wp-block-heading">' . esc_html( $title ) . '</h1>';

        return array(
            array(
                'blockName'    => 'core/heading',
                'attrs'        => array( 'level' => 1 ),
                'innerBlocks'  => array(),
                'innerHTML'    => $heading,
                'innerContent' => array( $heading ),
            ),
            array(
                'blockName'    => 'core/shortcode',
                'attrs'        => array(),
                'innerBlocks'  => array(),
                'innerHTML'    => HostingerEcommerceBuilder::PRODUCTS_SHORTCODE,
                'innerContent' => array( HostingerEcommerceBuilder::PRODUCTS_SHORTCODE ),
            ),
        );
    }

    private function create_shop_page( string $editor ): int {
        $title   = __( 'Shop', 'hostinger-ai-theme' );
        $postarr = array(
            'post_title'   => $title,
            'post_name'    => 'shop',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        $page_id = $editor === 'elementor'
            ? $this->create_elementor_shop_page( $postarr )
            : $this->create_gutenberg_shop_page( $postarr );

        if ( $page_id <= 0 ) {
            return 0;
        }

        $this->mark_generated_page( $page_id );
        CreatedPages::record( 'shop', $title, $page_id );

        return $page_id;
    }

    private function create_elementor_shop_page( array $postarr ): int {
        $json = $this->load_shop_template();
        if ( $json === '' ) {
            return 0;
        }

        $page_id = Helper::insert_trusted_post( $postarr );
        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }
        $page_id = (int) $page_id;

        Helper::mark_elementor_page( $page_id );
        update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
        Helper::save_elementor_data( $page_id, $json );

        return $page_id;
    }

    private function create_gutenberg_shop_page( array $postarr ): int {
        $postarr['post_content'] = HostingerEcommerceBuilder::PRODUCTS_BLOCK;

        $page_id = Helper::insert_trusted_post( $postarr );
        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }

        return (int) $page_id;
    }

    private function mark_generated_page( int $page_id ): void {
        update_post_meta( $page_id, '_wp_page_template', 'no-title' );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );
    }

    private function load_shop_template(): string {
        $path = dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'elementor' . DIRECTORY_SEPARATOR . HostingerEcommerceBuilder::SHOP_ELEMENTOR_TEMPLATE;

        if ( ! file_exists( $path ) ) {
            return '';
        }

        $content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return $content !== false ? $content : '';
    }

    private function relink_shop_menu_items( array $menu ): array {
        foreach ( $menu as &$item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $is_shop = sanitize_title( (string) ( $item['product_category'] ?? '' ) ) === 'shop'
                || ( ! isset( $item['page_id'] ) && sanitize_title( (string) ( $item['title'] ?? '' ) ) === 'shop' );

            if ( $is_shop ) {
                unset( $item['product_category'] );
                $item['page_id'] = self::ENVELOPE_ID;
            }

            if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
                $item['children'] = $this->relink_shop_menu_items( $item['children'] );
            }
        }

        return $menu;
    }
}
