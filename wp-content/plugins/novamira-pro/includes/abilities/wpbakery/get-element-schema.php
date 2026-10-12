<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Get the compact control schema for a single WPBakery element.
 */

wp_register_ability('novamira/wpbakery-get-element-schema', [
    'label' => __('Get WPBakery Element Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the compact control schema for a single WPBakery element (tag, label, is_container, categories, controls, common_controls). `controls` is the element-specific param map; `common_controls` is the four universal atts (el_id, el_class, css, css_animation) that every WPBakery element accepts on top of its own schema — these never appear in the WPBakery param registry so they would otherwise be invisible to discovery. Call this right before authoring via add-element / edit-element / set-content. Use wpbakery-list-elements first to find the tag.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'tag' => [
                'type' => 'string',
                'description' => 'The element tag (e.g. "vc_btn", "vc_basic_grid").',
            ],
        ],
        'required' => ['tag'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'tag' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'is_container' => ['type' => 'boolean'],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'controls' => [
                'type' => 'object',
                'description' => 'Map of param_name -> compact control schema (type, default, options, dependency).',
            ],
            'common_controls' => [
                'type' => 'object',
                'description' => 'The four universal atts every WPBakery element accepts on top of its own schema (el_id, el_class, css, css_animation). These are passed through by the validator without being in the per-element controls map. Fixed across the 6.5–8.x range.',
            ],
        ],
        'required' => ['tag', 'label', 'is_container', 'categories', 'controls'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_get_element_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the element tag returned by wpbakery-list-elements. Returns the compact control schema for that element so the agent can author the right attribute names with the right types and options. Returns wpbakery_not_found WP_Error when the tag is not in the WPBMap registry.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{tag: string} $input
 * @return array{
 *     tag: string,
 *     label: string,
 *     is_container: bool,
 *     categories: list<string>,
 *     controls: array<string, array<string, mixed>>,
 *     common_controls: array<string, array<string, string>>,
 * }|WP_Error
 */
function wpbakery_get_element_schema(array $input): array|WP_Error
{
    $tag = $input['tag'];
    if ($tag === '') {
        return new WP_Error('wpbakery_invalid_input', __('tag is required.', domain: 'novamira-pro'));
    }

    $settings = \WPBMap::getShortCode($tag);
    if (!is_array($settings) || $settings === []) {
        return new WP_Error('wpbakery_not_found', sprintf(
            /* translators: %s: WPBakery element tag. */
            __('WPBakery element "%s" is not registered.', domain: 'novamira-pro'),
            $tag,
        ));
    }

    $is_container = ($settings['is_container'] ?? false) !== false;
    /** @var list<string>|string $raw_cats */
    $raw_cats = $settings['category'] ?? [];
    $cats = is_array($raw_cats) ? $raw_cats : [$raw_cats];

    $schema = wpb_compact_element($tag);
    /** @var array<string, array<string, mixed>> $controls */
    $controls = $schema !== null && isset($schema['controls']) && is_array($schema['controls'])
        ? $schema['controls']
        : [];

    /** @var mixed $name_raw */
    $name_raw = $settings['name'] ?? $tag;

    return [
        'tag' => $tag,
        'label' => is_string($name_raw) && $name_raw !== '' ? $name_raw : $tag,
        'is_container' => $is_container,
        'categories' => array_values(array_filter(array_map('strval', $cats), static fn(string $c): bool => $c !== '')),
        'controls' => $controls,
        'common_controls' => wpb_common_controls(),
    ];
}

/**
 * The four universal atts every WPBakery element accepts on top of its
 * own schema. They are not in WPBMap's per-element params, so they would
 * otherwise be invisible to schema-based discovery — sub-agent B10
 * tripped on `el_class` not being in vc_btn's controls. The validator
 * (wpb_validate_atts) already passes these through; this helper exists
 * so the schema response advertises them explicitly.
 *
 * Stable across the 6.5–8.x range we support.
 *
 * @return array<string, array<string, string>>
 */
function wpb_common_controls(): array
{
    return [
        'el_id' => [
            't' => 'textfield',
            'description' => 'HTML id attribute applied to the element wrapper. Use for in-page anchors and CSS hooks.',
        ],
        'el_class' => [
            't' => 'textfield',
            'description' => 'Extra CSS classes appended to the element wrapper. Space-separated. Use to bind page-level custom_css rules.',
        ],
        'css' => [
            't' => 'css_editor',
            'description' => 'WPBakery Design Options CSS. Encoded format: `.vc_custom_<hash>{property:value !important;…}`. Auto-generated when users edit Design Options in the admin; rarely worth writing by hand from an agent. Rebuilt into the page-level shortcodes_custom_css meta on save.',
        ],
        'css_animation' => [
            't' => 'dropdown',
            'description' => 'Built-in entrance animation. Common values: none | top-to-bottom | bottom-to-top | left-to-right | right-to-left | appear.',
        ],
    ];
}
