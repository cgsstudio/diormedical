<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

defined( 'ABSPATH' ) || exit;

class EnvelopeContactDetails {

    private const CONTACT_FIELD_CLASS = 'hostinger-ai-contact-field';

    private const CONTACT_GROUP_CLASS = 'hostinger-ai-contact-details';

    private const MAP_CLASS = 'hostinger-ai-map';

    // Substitute owner contact details; drop rows and the map the owner never supplied.
    public function prepare( array $envelope, string $editor ): array {
        $values = $this->contact_display_values();

        $context = array(
            'tokens'      => $this->contact_token_replacements( $values ),
            'drop_fields' => array_keys( array_filter( $values, fn( $value ) => $value === '' ) ),
            'drop_map'    => $values['address'] === '',
        );

        foreach ( $envelope['pages'] as $index => $page ) {
            if ( ! is_array( $page ) || ! is_array( $page['content'] ?? null ) ) {
                continue;
            }

            $envelope['pages'][ $index ]['content'] = $this->apply_to_nodes(
                $page['content'],
                $editor,
                $context
            );
        }

        return $envelope;
    }

    private function contact_display_values(): array {
        $contact = get_option( 'hostinger_ai_contact', array() );

        if ( ! is_array( $contact ) ) {
            $contact = array();
        }

        $country_code = ltrim( (string) ( $contact['phone_country_code'] ?? '' ), '+' );
        $phone        = trim( (string) ( $contact['phone'] ?? '' ) );

        return array(
            'email'   => trim( (string) ( $contact['email'] ?? '' ) ),
            'phone'   => $phone === '' ? '' : '(+' . $country_code . ') ' . $phone,
            'address' => trim( (string) ( $contact['address'] ?? '' ) ),
        );
    }

    private function contact_token_replacements( array $values ): array {
        $dialable = preg_replace( '/[^0-9+]/', '', $values['phone'] ?? '' );

        return array(
            'trans-contact_email'   => esc_html( $values['email'] ),
            'trans-contact_phone'   => esc_html( $values['phone'] ),
            'trans-contact_address' => esc_html( $values['address'] ),
            'trans-encoded_email'   => esc_attr( $values['email'] ),
            'trans-encoded_phone'   => esc_attr( (string) $dialable ),
            'trans-encoded_address' => rawurlencode( $values['address'] ),
        );
    }

    private function apply_to_nodes( array $nodes, string $editor, array $context ): array {
        $kept = array();

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                $kept[] = $node;
                continue;
            }

            if ( $this->node_is_dropped( $node, $editor, $context ) ) {
                continue;
            }

            $processed = $this->apply_to_node( $node, $editor, $context );

            if ( $this->wrapper_is_emptied( $processed, $editor ) ) {
                continue;
            }

            $kept[] = $processed;
        }

        return array_values( $kept );
    }

    private function apply_to_node( array $node, string $editor, array $context ): array {
        if ( $editor === 'elementor' ) {
            if ( is_array( $node['settings'] ?? null ) ) {
                $node['settings'] = $this->substitute_tokens( $node['settings'], $context['tokens'] );
            }

            if ( is_array( $node['elements'] ?? null ) ) {
                $node['elements'] = $this->apply_to_nodes( $node['elements'], $editor, $context );
            }

            return $node;
        }

        $children = is_array( $node['innerBlocks'] ?? null ) ? $node['innerBlocks'] : array();

        if ( $children !== array() ) {
            $survives = array();
            $kept     = array();

            foreach ( $children as $child ) {
                $dropped   = is_array( $child ) && $this->node_is_dropped( $child, $editor, $context );
                $processed = $child;

                if ( ! $dropped && is_array( $child ) ) {
                    $processed = $this->apply_to_node( $child, $editor, $context );
                    $dropped   = $this->wrapper_is_emptied( $processed, $editor );
                }

                $survives[] = ! $dropped;

                if ( ! $dropped ) {
                    $kept[] = $processed;
                }
            }

            if ( in_array( false, $survives, true ) ) {
                $node['innerContent'] = BlockInnerContent::prune(
                    is_array( $node['innerContent'] ?? null ) ? $node['innerContent'] : array(),
                    $survives
                );
            }

            $node['innerBlocks'] = array_values( $kept );
        }

        foreach ( array( 'attrs', 'innerHTML', 'innerContent' ) as $key ) {
            if ( array_key_exists( $key, $node ) ) {
                $node[ $key ] = $this->substitute_tokens( $node[ $key ], $context['tokens'] );
            }
        }

        return $node;
    }

    private function wrapper_is_emptied( array $node, string $editor ): bool {
        if ( $editor === 'elementor' ) {
            $classes = (string) ( $node['settings']['_css_classes'] ?? '' );

            return str_contains( $classes, self::CONTACT_GROUP_CLASS )
                && ( $node['elements'] ?? array() ) === array();
        }

        $classes = (string) ( $node['attrs']['className'] ?? '' );

        return str_contains( $classes, self::CONTACT_GROUP_CLASS )
            && ( $node['innerBlocks'] ?? array() ) === array();
    }

    private function node_is_dropped( array $node, string $editor, array $context ): bool {
        $markup = $editor === 'elementor'
            ? (string) ( $node['settings']['editor'] ?? '' ) . (string) ( $node['settings']['html'] ?? '' )
            : (string) ( $node['innerHTML'] ?? '' );

        if ( $markup === '' ) {
            return false;
        }

        if ( $context['drop_map'] && str_contains( $markup, self::MAP_CLASS ) ) {
            return true;
        }

        foreach ( $context['drop_fields'] as $field ) {
            if ( str_contains( $markup, self::CONTACT_FIELD_CLASS . '--' . $field ) ) {
                return true;
            }
        }

        return false;
    }

    private function substitute_tokens( $value, array $tokens ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                $value[ $key ] = $this->substitute_tokens( $item, $tokens );
            }

            return $value;
        }

        if ( is_string( $value ) ) {
            return strtr( $value, $tokens );
        }

        return $value;
    }
}
