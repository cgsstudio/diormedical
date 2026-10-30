<?php

namespace Hostinger\AiTheme\Admin;

use Hostinger\AiTheme\Constants\BuilderType;

defined( 'ABSPATH' ) || exit;

class SiteEditorElementorCta {
    private const SCRIPT_HANDLE      = 'hostinger-ai-site-editor';
    private const EDIT_MODE_META     = '_elementor_edit_mode';
    private const EDIT_MODE_BUILDER  = 'builder';
    private const SITE_EDITOR_SCREEN = 'site-editor';

    private bool $is_elementor_active;

    public function __construct( bool $is_elementor_active ) {
        $this->is_elementor_active = $is_elementor_active;

        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_cta_script' ) );
    }

    public function enqueue_cta_script(): void {
        if ( ! $this->is_site_editor_screen() ) {
            return;
        }

        $front_page_id = $this->get_elementor_front_page_id();

        if ( ! $front_page_id ) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            HOSTINGER_AI_WEBSITES_ASSETS_URL . '/js/site-editor.min.js',
            array( 'wp-data', 'wp-dom-ready', 'wp-notices' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'hostingerAiSiteEditor',
            array(
                'frontPageId'  => $front_page_id,
                'elementorUrl' => add_query_arg(
                    array(
                        'post'   => $front_page_id,
                        'action' => 'elementor',
                    ),
                    admin_url( 'post.php' )
                ),
                'translations' => self::get_translations(),
            )
        );
    }

    public static function get_translations(): array {
        return array(
            'message'     => __( 'This page is built with Elementor. The block editor cannot display Elementor content.', 'hostinger-ai-theme' ),
            'buttonLabel' => __( 'Edit with Elementor', 'hostinger-ai-theme' ),
        );
    }

    private function get_elementor_front_page_id(): int {
        if ( ! $this->is_elementor_active ) {
            return 0;
        }

        if ( get_option( 'hostinger_ai_builder_type', BuilderType::GUTENBERG ) !== BuilderType::ELEMENTOR ) {
            return 0;
        }

        $front_page_id = (int) get_option( 'page_on_front' );

        if ( ! $front_page_id ) {
            return 0;
        }

        $edit_mode = get_post_meta( $front_page_id, self::EDIT_MODE_META, true );

        if ( $edit_mode !== self::EDIT_MODE_BUILDER ) {
            return 0;
        }

        return $front_page_id;
    }

    private function is_site_editor_screen(): bool {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();

        return $screen && $screen->id === self::SITE_EDITOR_SCREEN;
    }
}
