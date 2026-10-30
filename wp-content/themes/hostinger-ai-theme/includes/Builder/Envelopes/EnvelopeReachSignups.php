<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

use Hostinger\AiTheme\Builder\HostingerReachBuilder;

defined( 'ABSPATH' ) || exit;

class EnvelopeReachSignups {

    private HostingerReachBuilder $reach_builder;

    public function __construct( ?HostingerReachBuilder $reach_builder = null ) {
        $this->reach_builder = $reach_builder ?? new HostingerReachBuilder();
    }

    // Enable Reach's Elementor widget, or drop the signup if it still cannot render.
    public function prepare( array $envelope, string $editor, array &$warnings ): array {
        $this->reach_builder->generate_form();

        if ( $editor !== 'elementor' ) {
            return $envelope;
        }

        $this->reach_builder->activate_elementor_integration();

        if ( $this->reach_builder->can_render_elementor_form() ) {
            return $envelope;
        }

        $removed = 0;

        foreach ( $envelope['pages'] as $index => $page ) {
            $content = is_array( $page['content'] ?? null ) ? $page['content'] : array();
            $kept    = array_values(
                array_filter( $content, fn( $node ) => ! $this->contains_reach_widget( $node ) )
            );

            if ( count( $kept ) === count( $content ) || $kept === array() ) {
                continue;
            }

            $removed += count( $content ) - count( $kept );

            $envelope['pages'][ $index ]['content'] = $kept;
        }

        if ( $removed > 0 ) {
            $warnings[] = 'Newsletter signup skipped: Hostinger Reach is not active on this site.';
        }

        return $envelope;
    }

    private function contains_reach_widget( $node ): bool {
        if ( ! is_array( $node ) ) {
            return false;
        }

        if ( ( $node['widgetType'] ?? '' ) === HostingerReachBuilder::PLUGIN_SLUG ) {
            return true;
        }

        foreach ( $node['elements'] ?? array() as $child ) {
            if ( $this->contains_reach_widget( $child ) ) {
                return true;
            }
        }

        return false;
    }
}
