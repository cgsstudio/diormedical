<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\Helper;
use WP_Rewrite;

defined( 'ABSPATH' ) || exit;

class EnvelopeSiteSettings {

    public function set_mode( string $editor ): void {
        update_option( BundleSchema::OPTION_MODE, BundleSchema::MODE_ENVELOPE );
        update_option( 'hostinger_ai_builder_type', $editor );
    }

    // Envelope language is often `en`; WordPress needs a locale like `en_US`.
    public function apply_language( $language ): void {
        if ( ! is_array( $language ) ) {
            return;
        }

        $code = $language['code'] ?? '';
        if ( ! is_string( $code ) || $code === '' ) {
            return;
        }

        $code   = sanitize_text_field( $code );
        $stored = (string) get_option( 'hostinger_ai_selected_language', '' );

        if ( $this->is_installable_locale( $stored ) && ! $this->is_installable_locale( $code ) ) {
            return;
        }

        update_option( 'hostinger_ai_selected_language', $code );
    }

    public function install_selected_language(): void {
        $locale = (string) get_option( 'hostinger_ai_selected_language', '' );

        if ( ! $this->is_installable_locale( $locale ) ) {
            return;
        }

        Helper::install_and_set_language( $locale );
    }

    public function apply_single_page_chrome( bool $single_page ): void {
        $options = get_option( 'hostinger_ai_theme_display_options', array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        if ( $single_page ) {
            $options['hide_header'] = 1;
            update_option( 'hostinger_ai_theme_display_options', $options );
            return;
        }

        unset( $options['hide_header'] );
        update_option( 'hostinger_ai_theme_display_options', $options );
    }

    public function apply_brand( array $brand, array $media_map ): string {
        $name = $brand['name'] ?? '';
        if ( is_string( $name ) && $name !== '' ) {
            update_option( 'blogname', sanitize_text_field( $name ) );
        }

        $tagline = $brand['tagline'] ?? null;
        if ( is_string( $tagline ) ) {
            update_option( 'blogdescription', sanitize_text_field( $tagline ) );
        }

        $logo_src = $brand['logo']['signed_url'] ?? '';
        if ( is_string( $logo_src ) && isset( $media_map[ $logo_src ] ) ) {
            $attachment_id = (int) $media_map[ $logo_src ]['id'];
            set_theme_mod( 'custom_logo', $attachment_id );
            update_option( BundleSchema::OPTION_ORIGINAL_LOGO, $attachment_id );

            return (string) $media_map[ $logo_src ]['url'];
        }

        return '';
    }

    public function ensure_pretty_permalinks(): void {
        if ( get_option( 'permalink_structure' ) ) {
            return;
        }

        global $wp_rewrite;

        update_option( 'permalink_structure', '/%postname%/' );

        if ( $wp_rewrite instanceof WP_Rewrite ) {
            $wp_rewrite->set_permalink_structure( '/%postname%/' );
        }
    }

    // `en` is a language tag, not a WP locale.
    private function is_installable_locale( string $locale ): bool {
        return $locale === 'en_US' || str_contains( $locale, '_' );
    }
}
