<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Constants\GenerationConstant;

defined( 'ABSPATH' ) || exit;

class EnvelopeWooCommerce {

    public function create_products( $woocommerce, array $media_map, array &$warnings ): int {
        if ( empty( $woocommerce ) || ! is_array( $woocommerce ) ) {
            return 0;
        }

        $products = new EnvelopeProducts();
        if ( ! $products->is_available() ) {
            $warnings[] = 'WooCommerce is not active — products and categories were skipped.';
            return 0;
        }

        $product_ids = $products->create( $woocommerce, $media_map );
        update_option( BundleSchema::OPTION_PRODUCTS, $product_ids );

        return count( $product_ids );
    }

    // Cart / checkout / my-account are not in the envelope.
    public function ensure_store_pages(): void {
        if ( ! get_option( 'hostinger_ai_woo', false ) ) {
            return;
        }

        $this->create_store_page(
            'cart.html',
            __( 'Cart', 'hostinger-ai-theme' ),
            'woocommerce_cart_page_id'
        );
        $this->create_store_page(
            'checkout.html',
            __( 'Checkout', 'hostinger-ai-theme' ),
            'woocommerce_checkout_page_id'
        );
        $this->create_store_page(
            'my-account.html',
            __( 'My Account', 'hostinger-ai-theme' ),
            'woocommerce_myaccount_page_id'
        );
    }

    private function create_store_page( string $template_file, string $title, string $option_name ): void {
        $editor = (string) get_option( 'hostinger_ai_builder_type', '' );

        if ( $editor === 'elementor' ) {
            $this->create_elementor_store_page( $template_file, $title, $option_name );
            return;
        }

        $this->create_gutenberg_store_page( $template_file, $title, $option_name );
    }

    private function create_gutenberg_store_page( string $template_file, string $title, string $option_name ): void {
        $content = $this->load_template( 'woo', $template_file );
        if ( $content === '' ) {
            return;
        }

        $page_id = $this->insert_store_page( $title, $content );
        if ( $page_id <= 0 ) {
            return;
        }

        update_option( $option_name, $page_id );
        CreatedPages::record( sanitize_title( $title ), $title, $page_id );
    }

    private function create_elementor_store_page( string $template_file, string $title, string $option_name ): void {
        $json_content = $this->load_template( 'elementor', str_replace( '.html', '.json', $template_file ) );
        if ( $json_content === '' ) {
            return;
        }

        $page_id = $this->insert_store_page( $title, '' );
        if ( $page_id <= 0 ) {
            return;
        }

        Helper::mark_elementor_page( $page_id );
        Helper::save_elementor_data( $page_id, $json_content );

        update_option( $option_name, $page_id );
        CreatedPages::record( sanitize_title( $title ), $title, $page_id );
    }

    private function insert_store_page( string $title, string $content ): int {
        $page_id = Helper::insert_trusted_post(
            array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => array(
                    GenerationConstant::META_KEY => '1',
                ),
            )
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }

        return (int) $page_id;
    }

    private function load_template( string $directory, string $file ): string {
        $template_path = dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $file;

        if ( ! file_exists( $template_path ) ) {
            return '';
        }

        $content = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return $content !== false ? $content : '';
    }
}
