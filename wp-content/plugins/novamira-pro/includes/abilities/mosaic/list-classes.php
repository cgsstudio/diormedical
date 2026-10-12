<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-list-classes', [
    'label' => __('List Mosaic Classes', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Classes of a Mosaic theme — the reusable styles of Mosaic\'s Class system that elements attach through the `classes` input of mosaic-add-element / mosaic-edit-element. Compact summary per Class: id, theme_id, name, label, emitted_name (the class name Mosaic renders on elements), ordering and sub_class_count. Sub classes refine a Class and render as `<class>--<sub class>`; use mosaic-get-class for a Class\'s styles and its sub-class tree. An element type\'s built-in styles are Variants, listed by mosaic-list-variants.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => [
                'type' => 'string',
                'description' => 'Theme UUID — required, Classes are theme-scoped.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 100,
            ],
            'offset' => [
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'required' => ['theme_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'classes' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['classes', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_classes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Attach a Class to an element with the `classes` input (UUID or emitted_name) of mosaic-add-element / mosaic-edit-element — never through `css_classes`, which is for classes that come from outside Mosaic. Create one with mosaic-create-class.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_list_classes(array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    if ($theme_id === '') {
        return mo_error('mo_invalid_input', __('theme_id is required.', domain: 'novamira-pro'));
    }
    $limit = mo_clamp_limit(isset($input['limit']) && is_int($input['limit']) ? $input['limit'] : null);
    $offset = isset($input['offset']) && is_int($input['offset']) ? max(0, $input['offset']) : 0;

    $index = mo_class_index($theme_id);
    $classes = [];
    foreach (array_slice(array_values($index['classes']), $offset, $limit) as $entry) {
        $classes[] = mo_present_class_summary($entry, mo_class_descendant_count(
            $index['sub_classes'],
            (string) $entry['id'],
        ));
    }
    return ['classes' => $classes, 'total' => count($index['classes'])];
}
