<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\ColorUtils;
use WP_Theme_JSON_Data;

defined( 'ABSPATH' ) || exit;

class EnvelopeThemeJson {

    use ColorUtils;

    private const PAGE_COLOR_ROLES = array(
        'page-background' => array(
            'sources'  => array( 'page-background', 'base', 'surface' ),
            'name'     => 'Page background',
            'fallback' => '#ffffff',
        ),
        'page-text'       => array(
            'sources'  => array( 'page-text', 'contrast', 'ink' ),
            'name'     => 'Page text',
            'fallback' => '#000000',
        ),
    );

    // Page-text is on-surface ink; using it on a dark primary makes buttons unreadable.
    private const BUTTON_TEXT_ROLE = array(
        'sources'  => array( 'button-text', 'ink-inverse', 'light' ),
        'name'     => 'Button text',
        'fallback' => '#ffffff',
    );

    public function init(): void {
        add_filter( 'wp_theme_json_data_theme', array( $this, 'merge_theme_json' ), 1000 );
    }

    public function merge_theme_json( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
        if ( ! BundleSchema::is_active() ) {
            return $theme_json;
        }

        $delta = get_option( BundleSchema::OPTION_THEME_JSON, array() );

        if ( empty( $delta ) || ! is_array( $delta ) ) {
            return $theme_json;
        }

        return $theme_json->update_with( $this->with_page_color_presets( $delta ) );
    }

    // WP replaces the whole palette; envelope uses base/contrast, so page-background / page-text would vanish.
    private function with_page_color_presets( array $delta ): array {
        $palette = $delta['settings']['color']['palette'] ?? null;

        if ( ! is_array( $palette ) || empty( $palette ) ) {
            return $delta;
        }

        $page_colors = $this->get_page_colors_from_palette( $this->get_colors_by_slug( $palette ) );

        $delta['settings']['color']['palette'] = $this->merge_page_colors( $palette, $page_colors );

        return $delta;
    }

    private function get_colors_by_slug( array $palette ): array {
        $colors = array();

        foreach ( $palette as $entry ) {
            if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) ) {
                $colors[ (string) $entry['slug'] ] = (string) $entry['color'];
            }
        }

        return $colors;
    }

    private function get_page_colors_from_palette( array $colors ): array {
        $page_colors = array();

        foreach ( self::PAGE_COLOR_ROLES as $slug => $role ) {
            $page_colors[ $slug ] = $this->first_valid_color( $colors, $role['sources'], $role['fallback'] );
        }

        $page_colors['page-text'] = $this->readable_against( $page_colors['page-background'], $page_colors['page-text'] );

        $primary = $this->first_valid_color( $colors, array( 'primary', 'color3' ), '#000000' );

        $page_colors['button-text'] = $this->readable_against(
            $primary,
            $this->first_valid_color(
                $colors,
                self::BUTTON_TEXT_ROLE['sources'],
                self::BUTTON_TEXT_ROLE['fallback']
            )
        );

        return $page_colors;
    }

    private function merge_page_colors( array $palette, array $page_colors ): array {
        $managed_slugs = array_merge( array_keys( self::PAGE_COLOR_ROLES ), array( 'button-text' ) );
        $merged        = array();

        foreach ( $palette as $entry ) {
            if ( ! is_array( $entry ) || ! in_array( $entry['slug'] ?? '', $managed_slugs, true ) ) {
                $merged[] = $entry;
            }
        }

        foreach ( self::PAGE_COLOR_ROLES as $slug => $role ) {
            $merged[] = array(
                'slug'  => $slug,
                'color' => $page_colors[ $slug ],
                'name'  => $role['name'],
            );
        }

        $merged[] = array(
            'slug'  => 'button-text',
            'color' => $page_colors['button-text'],
            'name'  => self::BUTTON_TEXT_ROLE['name'],
        );

        return $merged;
    }

    private function first_valid_color( array $colors, array $sources, string $fallback ): string {
        foreach ( $sources as $slug ) {
            $color = $colors[ $slug ] ?? '';
            if ( $color !== '' && $this->is_valid_hex_color( $color ) ) {
                return $color;
            }
        }

        return $fallback;
    }

    private function readable_against( string $background, string $text ): string {
        if ( $this->calculate_contrast_ratio( $background, $text ) >= $this->get_required_contrast_ratio() ) {
            return $text;
        }

        $on_black = $this->calculate_contrast_ratio( $background, '#000000' );
        $on_white = $this->calculate_contrast_ratio( $background, '#ffffff' );

        return $on_black >= $on_white ? '#000000' : '#ffffff';
    }
}
