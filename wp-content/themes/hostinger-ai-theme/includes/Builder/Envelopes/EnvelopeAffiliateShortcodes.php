<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\AffiliateBuilder;

defined( 'ABSPATH' ) || exit;

class EnvelopeAffiliateShortcodes {

    private AffiliateBuilder $affiliate_builder;

    public function __construct( ?AffiliateBuilder $affiliate_builder = null ) {
        $this->affiliate_builder = $affiliate_builder ?? new AffiliateBuilder();
    }

    public function rewrite( array $nodes, string $editor, string $fallback_keyword ): array {
        $result = array();

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                $result[] = $node;
                continue;
            }

            $keyword = $this->marker_keyword( $node, $editor );

            if ( $keyword !== null ) {
                foreach ( $this->replacement_nodes( $keyword, $editor, $fallback_keyword ) as $replacement ) {
                    $result[] = $replacement;
                }

                continue;
            }

            $result[] = $this->rewrite_children( $node, $editor, $fallback_keyword );
        }

        return $result;
    }

    private function rewrite_children( array $node, string $editor, string $fallback_keyword ): array {
        if ( $editor === 'elementor' ) {
            if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
                $node['elements'] = $this->rewrite( $node['elements'], $editor, $fallback_keyword );
            }

            return $node;
        }

        if ( ! isset( $node['innerBlocks'] ) || ! is_array( $node['innerBlocks'] ) || $node['innerBlocks'] === array() ) {
            return $node;
        }

        $survives = array();
        $kept     = array();

        foreach ( $node['innerBlocks'] as $child ) {
            $keyword = is_array( $child ) ? $this->marker_keyword( $child, $editor ) : null;

            if ( $keyword !== null ) {
                $replacements = $this->replacement_nodes( $keyword, $editor, $fallback_keyword );

                foreach ( $replacements as $replacement ) {
                    $kept[] = $replacement;
                }

                $survives[] = $replacements !== array();

                continue;
            }

            $survives[] = true;
            $kept[]     = is_array( $child ) ? $this->rewrite_children( $child, $editor, $fallback_keyword ) : $child;
        }

        if ( in_array( false, $survives, true ) ) {
            $node['innerContent'] = BlockInnerContent::prune(
                is_array( $node['innerContent'] ?? null ) ? $node['innerContent'] : array(),
                $survives
            );
        }

        $node['innerBlocks'] = array_values( $kept );

        return $node;
    }

    private function replacement_nodes( string $keyword, string $editor, string $fallback_keyword ): array {
        if ( $keyword === '' ) {
            $keyword = $fallback_keyword;
        }

        if ( $keyword === '' ) {
            return array();
        }

        if ( $editor === 'elementor' ) {
            $widget = $this->affiliate_builder->generate_elementor_widget( $keyword );

            return $widget === array() ? array() : array( $widget );
        }

        $blocks = array();

        foreach ( parse_blocks( $this->affiliate_builder->generate_shortcode( $keyword ) ) as $block ) {
            if ( ! empty( $block['blockName'] ) ) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function marker_keyword( array $node, string $editor ): ?string {
        $shortcode = '';

        if ( $editor === 'elementor' && ( $node['widgetType'] ?? '' ) === 'shortcode' ) {
            $shortcode = $node['settings']['shortcode'] ?? '';
        }

        if ( $editor !== 'elementor' && ( $node['blockName'] ?? '' ) === 'core/shortcode' ) {
            $shortcode = $node['innerHTML'] ?? '';

            if ( ( ! is_string( $shortcode ) || $shortcode === '' ) && isset( $node['innerContent'] ) && is_array( $node['innerContent'] ) ) {
                $shortcode = implode( '', array_filter( $node['innerContent'], 'is_string' ) );
            }
        }

        if ( ! is_string( $shortcode ) || ! preg_match( '/\[hostinger_affiliate\b[^\]]*\]/i', $shortcode, $matches ) ) {
            return null;
        }

        $atts    = shortcode_parse_atts( trim( $matches[0], '[]' ) );
        $keyword = is_array( $atts ) && isset( $atts['keyword'] ) ? (string) $atts['keyword'] : '';

        return sanitize_text_field( $keyword );
    }
}
