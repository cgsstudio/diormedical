<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Beaver Global Styles stores typography per named element (not as reusable
 * "font tokens" — Beaver has no global-font token collection). These are the
 * editable elements; each maps to a `<element>_typography` key on the global
 * styles object.
 */
const BB_GS_TYPOGRAPHY_ELEMENTS = ['text', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'link', 'button'];

wp_register_ability('novamira/beaver-builder-get-global-typography', [
    'label' => __('Get Beaver Builder Global Typography', domain: 'novamira-pro'),
    'description' => __(
        'Returns Beaver Builder\'s Global Styles typography — the site-wide default typography per element (text, h1-h6, link, button). This is Beaver\'s reusable type system (there is no global-font token list; typography is set per element here). Read it first to learn the exact `typography` object shape before edit-global-typography, and to set page styling that inherits global defaults instead of hardcoding font size/weight on every heading.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'element' => [
                'type' => 'string',
                'enum' => BB_GS_TYPOGRAPHY_ELEMENTS,
                'description' => 'Optional: return only this element\'s typography. Omit for all elements.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => ['type' => 'array', 'items' => ['type' => 'string']],
            'typography' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_get_global_typography',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Beaver has no reusable font tokens; this is its per-element global typography (text/h1-h6/link/button). Read it to learn the `typography` object shape, then edit-global-typography to set the site-wide default rather than hardcoding font sizes on every node. Pair with Global Colors for a full design-token pass.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-edit-global-typography', [
    'label' => __('Edit Beaver Builder Global Typography', domain: 'novamira-pro'),
    'description' => __(
        'Sets the Global Styles typography for one element (text, h1-h6, link, button). Pass `element` and a `typography` object in Beaver\'s typography shape — call get-global-typography first to read the exact current shape (font_family, font_weight, font_size, line_height, etc.) and send it back modified. The change applies site-wide to every page that inherits global typography.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'element' => [
                'type' => 'string',
                'enum' => BB_GS_TYPOGRAPHY_ELEMENTS,
                'description' => 'Which element\'s global typography to set.',
            ],
            'typography' => [
                'type' => 'object',
                'description' => 'Beaver typography object (font_family, font_weight, font_size{length,unit}, line_height{length,unit}, …). Read get-global-typography for the exact shape.',
            ],
        ],
        'required' => ['element', 'typography'],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_edit_global_typography',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Site-wide default typography for one element. Read get-global-typography first and send the same shape back modified. Setting global typography is preferable to repeating font size/weight on every heading node.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_get_global_typography(array $input): array|WP_Error
{
    if (!bb_global_styles_available()) {
        return bb_gs_unavailable_error();
    }

    $only = bb_string_arg($input['element'] ?? '');
    if ($only !== '' && !in_array($only, BB_GS_TYPOGRAPHY_ELEMENTS, strict: true)) {
        return bb_error(
            code: 'beaver_builder_invalid_typography_element',
            message: 'Unknown `element`. Use one of: ' . implode(', ', BB_GS_TYPOGRAPHY_ELEMENTS) . '.',
            data: ['status' => 400],
        );
    }

    $settings = bb_gs_settings();
    $elements = $only !== '' ? [$only] : BB_GS_TYPOGRAPHY_ELEMENTS;
    $typography = [];
    foreach ($elements as $element) {
        $value = bb_object_property(object: $settings, property: $element . '_typography');
        $typography[$element] = bb_jsonable(value: $value, max_field_bytes: 4096);
    }

    return ['elements' => $elements, 'typography' => (object) $typography];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_edit_global_typography(array $input): array|WP_Error
{
    if (!bb_global_styles_available()) {
        return bb_gs_unavailable_error();
    }

    $element = bb_string_arg($input['element'] ?? '');
    if (!in_array($element, BB_GS_TYPOGRAPHY_ELEMENTS, strict: true)) {
        return bb_error(
            code: 'beaver_builder_invalid_typography_element',
            message: 'Pass `element` as one of: ' . implode(', ', BB_GS_TYPOGRAPHY_ELEMENTS) . '.',
            data: ['status' => 400],
        );
    }

    /** @var mixed $typography */
    $typography = $input['typography'] ?? null;
    if (!is_array($typography) && !$typography instanceof \stdClass) {
        return bb_error(
            code: 'beaver_builder_invalid_typography',
            message: 'Pass a `typography` object (read get-global-typography for the shape).',
            data: ['status' => 400],
        );
    }

    \FLBuilderGlobalStyles::save_settings([$element . '_typography' => (array) $typography]);

    $value = bb_object_property(object: bb_gs_settings(), property: $element . '_typography');
    return [
        'edited' => true,
        'element' => $element,
        'typography' => bb_jsonable(value: $value, max_field_bytes: 4096),
    ];
}
