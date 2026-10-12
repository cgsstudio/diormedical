<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-list-element-types', [
    'label' => __('List Breakdance Element Types', domain: 'novamira-pro'),
    'description' => __(
        'Lists the element types registered in THIS Breakdance install — core EssentialElements, Breakdance Pro, and any third-party add-on — so you can pick a valid `type` for add-element. Each row is compact: `slug` (the exact, case-sensitive string add-element expects, e.g. `EssentialElements\\Heading`), `label` (the editor name, e.g. "Image" for `EssentialElements\\Image2`), and `category`. The editor resolves elements by exact slug, so casing matters — this is the source of truth for it. Filter with `category`, `namespace`, or `search` to narrow; `total` is the filtered count, `catalogue_total` the count in scope before category/namespace/search narrowing (it grows when `include_hidden` is set). Always-hidden internals (the deprecated "Image V1", the MissingElement placeholder, preset/internal nodes) are omitted by default — pass `include_hidden=true` to include them. The heavy per-element control/property schema is NOT returned here: add the element once and read it back with `get-element` to inspect its property shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'description' => 'Filter by category (e.g. `basic`, `woocommerce`, `forms`). Case-insensitive. See `categories` in the response for the full vocabulary.',
            ],
            'namespace' => [
                'type' => 'string',
                'description' => 'Filter by slug namespace prefix (e.g. `EssentialElements`). Use to isolate a single add-on\'s elements.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against the slug and the label (e.g. `image`, `loop`, `heading`).',
            ],
            'include_hidden' => [
                'type' => 'boolean',
                'description' => 'Include always-hidden internal/deprecated elements (Image V1, MissingElement, presets). Defaults to false.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                    ],
                ],
            ],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'total' => ['type' => 'integer'],
            'catalogue_total' => ['type' => 'integer'],
        ],
        'required' => ['elements', 'categories', 'total', 'catalogue_total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_list_element_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this when an add-element call fails with "Unknown element type" or when unsure of a slug\'s exact casing. `categories` in the response is the category vocabulary across the listed elements — use those values for the `category` filter. The per-element control schema is intentionally omitted; add-element then get-element to inspect one element\'s properties.',
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
function breakdance_list_element_types(array $input): array
{
    $include_hidden = bd_coerce_bool($input['include_hidden'] ?? false);
    $types = bd_registered_element_types();
    if (!$include_hidden) {
        $types = array_filter($types, static fn(array $meta): bool => $meta['hidden'] === false);
    }
    if ($types === []) {
        return ['elements' => [], 'categories' => [], 'total' => 0, 'catalogue_total' => 0];
    }

    $category = isset($input['category']) && is_string($input['category']) ? $input['category'] : null;
    $namespace = isset($input['namespace']) && is_string($input['namespace']) ? $input['namespace'] : null;
    $search = isset($input['search']) && is_string($input['search']) ? $input['search'] : null;

    // The full category vocabulary is reported regardless of filtering so the
    // caller learns valid `category` values even from a narrowed response.
    $categories = [];
    foreach ($types as $meta) {
        if ($meta['category'] === '') {
            continue;
        }
        $categories[$meta['category']] = true;
    }

    $rows = [];
    foreach ($types as $slug => $meta) {
        if (!bd_element_type_matches_filters($slug, $meta, $category, $namespace, $search)) {
            continue;
        }
        $rows[] = ['slug' => $slug, 'label' => $meta['label'], 'category' => $meta['category']];
    }

    return [
        'elements' => $rows,
        'categories' => array_keys($categories),
        'total' => count($rows),
        'catalogue_total' => count($types),
    ];
}

/**
 * Whether a registered element row passes the optional category / namespace /
 * search filters. Extracted so the callback stays under the complexity gate.
 *
 * @param array{label: string, category: string, hidden: bool} $meta
 */
function bd_element_type_matches_filters(
    string $slug,
    array $meta,
    ?string $category,
    ?string $namespace,
    ?string $search,
): bool {
    if ($category !== null && strcasecmp($meta['category'], $category) !== 0) {
        return false;
    }
    if (
        $namespace !== null
        && $namespace !== ''
        && !str_starts_with($slug, rtrim($namespace, characters: '\\') . '\\')
        && strcasecmp($slug, $namespace) !== 0
    ) {
        return false;
    }
    if (
        $search !== null
        && $search !== ''
        && stripos($slug, $search) === false
        && stripos($meta['label'], $search) === false
    ) {
        return false;
    }
    return true;
}
