<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-list-dynamic-data-fields', [
    'label' => __('List Oxygen Dynamic Data Fields', domain: 'novamira-pro'),
    'description' => __(
        'Lists every dynamic-data source Oxygen can resolve at render time: post fields, site info, ACF/Metabox/JetEngine integration fields, Featured Image, Author, Current User, URL & Query, and so on. Each row is a compact summary: `slug` (the id used when binding a control to dynamic data), `label`, `category`, `subcategory`, `return_types` (e.g. `string`, `image_url`, `url`), and `pro_only`. `pro_only` comes from the Soflyy catalogue, whose base field class defaults to true — most integration fields (Meta Box, ACF, ...) carry it even when core-adjacent. It gates the builder-UI picker only, NOT front-end rendering; on free-license sites still prefer non-pro fields so the user could recreate the binding in the builder UI. The full `controls` tree per field is heavy — use `get-dynamic-data-field` for that.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => ['type' => 'string', 'description' => 'Filter by category (e.g. `Post`, `Author`, `ACF`).'],
            'return_type' => [
                'type' => 'string',
                'description' => 'Filter by return type (e.g. `string`, `image_url`, `url`, `array`).',
            ],
            'post_type' => [
                'type' => 'string',
                'description' => 'Limit to fields available on this CPT (matches the builder\'s post-type filter).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'fields' => ['type' => 'array'],
            'categories' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'repeater_sources' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'The fields that can drive a DynamicDataLoop (each `{slug, label, return_type}`). These are NOT scalar bind fields — they are repeater / flexible-content sources; pass the `slug` as `repeater_field` to oxygen-set-loop on a DynamicDataLoop element. Empty when no repeater provider (e.g. an ACF Pro repeater) is configured for the post type.',
            ],
        ],
        'required' => ['fields', 'categories', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_list_dynamic_data_fields',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use `category` to narrow the catalogue (e.g. "Post", "User", "ACF"). `categories` in the response is the full set of category names — handy when you don\'t know the exact label. To BIND a field, put the shortcode `[breakdance_dynamic field="<slug>"]` in the target control VALUE (e.g. a text element\'s `content.content.text`) via edit-element / set-content — not in a `*_dynamic_meta` sibling key, which the renderer ignores. Always bind to a registered field slug — nearly every value has one. AVOID the `phpreturn` (PHP) field: it evaluates arbitrary PHP on every render and traps logic in the page instead of the CMS; use it only after confirming no registered field covers the value.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function oxygen_list_dynamic_data_fields(array $input): array
{
    $post_type = isset($input['post_type']) && is_string($input['post_type']) ? $input['post_type'] : null;
    if (!class_exists('Breakdance\\DynamicData\\DynamicDataController')) {
        return ['fields' => [], 'categories' => [], 'total' => 0, 'repeater_sources' => []];
    }
    $all_fields = \Breakdance\DynamicData\DynamicDataController::getInstance()->getAllFields($post_type);

    // `categories` is the FULL post-type-scoped vocabulary, derived BEFORE the
    // category/return_type filter — otherwise a filtered call would hide every
    // label except the one it filtered on, defeating discovery.
    $categories = [];
    foreach ($all_fields as $field) {
        $summary = ox_dd_field_summary($field);
        $categories[$summary['category']] = true;
    }

    $filtered = ox_filter_dd_fields(
        $all_fields,
        isset($input['category']) && is_string($input['category']) ? $input['category'] : null,
        isset($input['return_type']) && is_string($input['return_type']) ? $input['return_type'] : null,
    );
    $rows = [];
    foreach ($filtered as $field) {
        $rows[] = ox_dd_field_summary($field);
    }

    return [
        'fields' => $rows,
        'categories' => array_keys($categories),
        'total' => count($rows),
        'repeater_sources' => ox_repeater_sources(),
    ];
}

/**
 * The fields that can drive a DynamicDataLoop — repeater and flexible-content
 * sources. These come from the engine's own `getFieldsByReturnType()` (the
 * source the loop's field chooser uses), NOT `getAllFields()` (which lists
 * only scalar bind fields), so a repeater is invisible in `fields` but appears
 * here. Empty when no repeater provider is configured. Each row is
 * `{slug, label, return_type}`.
 *
 * @return list<array{slug: string, label: string, return_type: string}>
 */
function ox_repeater_sources(): array
{
    $controller = \Breakdance\DynamicData\DynamicDataController::getInstance();
    if (!method_exists($controller, 'getFieldsByReturnType')) {
        return [];
    }
    $out = [];
    foreach (['repeater', 'flexible_content'] as $return_type) {
        /** @var iterable<object> $fields */
        $fields = $controller->getFieldsByReturnType($return_type);
        foreach ($fields as $field) {
            if (!method_exists($field, 'slug') || !method_exists($field, 'label')) {
                continue;
            }
            /** @var mixed $slug */
            $slug = $field->slug();
            /** @var mixed $label */
            $label = $field->label();
            $out[] = [
                'slug' => is_string($slug) ? $slug : '',
                'label' => is_string($label) ? $label : '',
                'return_type' => $return_type,
            ];
        }
    }
    return $out;
}

/**
 * Apply optional `category` and `return_type` filters to the
 * dynamic-data field list. Pure — does not mutate the input.
 *
 * @param list<array<string, mixed>> $fields
 * @return list<array<string, mixed>>
 */
function ox_filter_dd_fields(array $fields, ?string $category, ?string $return_type): array
{
    if ($category !== null) {
        $fields = array_values(array_filter(
            $fields,
            static fn(array $f): bool => isset($f['category']) && (string) $f['category'] === $category,
        ));
    }
    if ($return_type === null) {
        return $fields;
    }
    return array_values(array_filter(
        $fields,
        static fn(array $f): bool => (
            isset($f['returnTypes'])
            && is_array($f['returnTypes'])
            && in_array($return_type, $f['returnTypes'], strict: true)
        ),
    ));
}

/**
 * Compact summary row used by list-dynamic-data-fields.
 *
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function ox_dd_field_summary(array $field): array
{
    return [
        'slug' => isset($field['slug']) && is_string($field['slug']) ? $field['slug'] : '',
        'label' => isset($field['label']) && is_string($field['label']) ? $field['label'] : '',
        'category' => isset($field['category']) && is_string($field['category']) ? $field['category'] : '',
        'subcategory' => isset($field['subcategory']) && is_string($field['subcategory']) ? $field['subcategory'] : '',
        'return_types' => isset($field['returnTypes']) && is_array($field['returnTypes'])
            ? array_values($field['returnTypes'])
            : [],
        'pro_only' => isset($field['proOnly']) && $field['proOnly'] === true,
    ];
}
