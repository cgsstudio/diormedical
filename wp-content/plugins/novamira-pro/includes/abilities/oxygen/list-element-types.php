<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-list-element-types', [
    'label' => __('List Oxygen Element Types', domain: 'novamira-pro'),
    'description' => __(
        'Lists the element types this Oxygen 6 install\'s BUILDER actually offers, live, reproducing the engine\'s own visibility logic: an element whose `availableIn()` does not include the current mode is excluded entirely (in Oxygen mode only elements declaring `oxygen` exist in the builder), and elements the builder hides from the add panel — `alwaysHide` internals plus the `builder_hidden_elements` option, which Oxygen seeds by default with `EssentialElements\\Section`, `Div`, `Heading`, `Text`, `TextLink`, `WrapperLink`, `Icon`, `RichText`, `CodeBlock`, `Image2` — are omitted unless `include_hidden=true`. The visible base set is the native `OxygenElements\\*` elements (Container, Text, RichText, Image, PostsLoop, PhpCode, HtmlCode, CssCode, JavaScriptCode, oEmbed, Shortcode, ContainerShortcode, WpWidget, DynamicDataLoop, TermLoopBuilder, …), plus any oxygen-declared `EssentialElements\\*` / third-party elements. Each row is compact: `slug` (the exact, case-sensitive string add-element expects — for the built-in elements this is the fully-qualified class name, e.g. `OxygenElements\\PhpCode`), `label` (the editor name), `category`, and `is_oxygen_native` (true for the base `OxygenElements\\*` elements). The editor resolves elements by exact slug, so casing matters — this is the source of truth for it. Filter with `category`, `namespace`, `search`, or `only_oxygen_native=true`. NOTE: the raw-code elements (PhpCode, HtmlCode, CssCode, JavaScriptCode, CodeBlock) are write-gated — add-element / set-content refuse them unless the user explicitly approved `allow_code_elements: true`. The heavy per-element control/property schema is NOT returned here: read `get-element-schema` for that.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
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
                'description' => 'Filter by slug namespace prefix (e.g. `OxygenElements`, `EssentialElements`). Use to isolate one origin.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against the slug and the label.',
            ],
            'include_hidden' => [
                'type' => 'boolean',
                'description' => 'Include elements the builder hides from the add panel: always-hidden internals (MissingElement, presets) AND the ones hidden via the `builder_hidden_elements` option (Oxygen mode hides several EssentialElements duplicates by default). Hidden elements still render if already present in a tree. Defaults to false. Elements not available in this mode (`availableIn`) are never listed.',
            ],
            'only_oxygen_native' => [
                'type' => 'boolean',
                'description' => 'Return only the native `OxygenElements\\*` elements. Defaults to false. Equivalent to `namespace=OxygenElements`.',
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
                        'is_oxygen_native' => ['type' => 'boolean'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_list_element_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this when unsure of a slug\'s exact casing or when planning which element to add — it is the LIVE registry, so it reflects exactly what this install can build with (base OxygenElements plus, if the extension is active, EssentialElements). `categories` in the response is the category vocabulary across the listed elements. The per-element control schema is intentionally omitted; use get-element-schema to inspect one element\'s properties.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Whether a slug is a native `OxygenElements\*` element (base Oxygen 6),
 * as opposed to an `EssentialElements\*` (extension) or third-party slug.
 */
function ox_is_oxygen_native_slug(string $slug): bool
{
    return str_starts_with($slug, 'OxygenElements\\');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function oxygen_list_element_types(array $input): array
{
    $types = ox_element_types_scoped($input);
    if ($types === []) {
        return ['elements' => [], 'categories' => [], 'total' => 0, 'catalogue_total' => 0];
    }

    $rows = ox_element_types_filtered_rows($types, $input);
    return [
        'elements' => $rows,
        'categories' => ox_element_types_category_vocabulary($types),
        'total' => count($rows),
        'catalogue_total' => count($types),
    ];
}

/**
 * The element-type map narrowed by the always-applied scopes:
 * the engine's per-mode `availableIn` filter (an element whose
 * `availableIn()` omits the current mode does not exist in this builder
 * at all, so it is NEVER listed), `include_hidden` (skip panel-hidden
 * elements by default — both `alwaysHide` internals and the ones the
 * `builder_hidden_elements` option hides, which Oxygen mode seeds with
 * Section/Div/Heading/Text/RichText/CodeBlock/… by default), and
 * `only_oxygen_native` (keep only the native `OxygenElements\*` set).
 *
 * @param array<string, mixed> $input
 * @return array<string, array{label: string, category: string, hidden: bool, available: bool}>
 */
function ox_element_types_scoped(array $input): array
{
    $include_hidden = ox_coerce_bool($input['include_hidden'] ?? false);
    $only_native = ox_coerce_bool($input['only_oxygen_native'] ?? false);
    // The mode filter is unconditional: the builder drops these elements
    // entirely (not merely from the add panel), so listing them would
    // advertise types the editor cannot even resolve in this mode.
    $types = array_filter(ox_registered_element_types(), static fn(array $meta): bool => $meta['available'] === true);
    if (!$include_hidden) {
        $types = array_filter($types, static fn(array $meta): bool => $meta['hidden'] === false);
    }
    if (!$only_native) {
        return $types;
    }
    return array_filter(
        $types,
        static fn(array $_meta, string $slug): bool => ox_is_oxygen_native_slug($slug),
        ARRAY_FILTER_USE_BOTH,
    );
}

/**
 * Project the scoped type map into the flat row list expected in the
 * response, honouring the optional `category`, `namespace`, and
 * `search` filters.
 *
 * @param array<string, array{label: string, category: string, hidden: bool, available: bool}> $types
 * @param array<string, mixed> $input
 * @return list<array{slug: string, label: string, category: string, is_oxygen_native: bool}>
 */
function ox_element_types_filtered_rows(array $types, array $input): array
{
    $category = isset($input['category']) && is_string($input['category']) ? $input['category'] : null;
    $namespace = isset($input['namespace']) && is_string($input['namespace']) ? $input['namespace'] : null;
    $search = isset($input['search']) && is_string($input['search']) ? $input['search'] : null;

    $rows = [];
    foreach ($types as $slug => $meta) {
        if (!ox_element_type_matches_filters($slug, $meta, $category, $namespace, $search)) {
            continue;
        }
        $rows[] = [
            'slug' => $slug,
            'label' => $meta['label'],
            'category' => $meta['category'],
            'is_oxygen_native' => ox_is_oxygen_native_slug($slug),
        ];
    }
    return $rows;
}

/**
 * Build the sorted list of category slugs that appear across the scoped
 * types. Reported regardless of filtering so the caller can learn valid
 * `category` values from a narrowed response.
 *
 * @param array<string, array{label: string, category: string, hidden: bool, available: bool}> $types
 * @return list<string>
 */
function ox_element_types_category_vocabulary(array $types): array
{
    $categories = [];
    foreach ($types as $meta) {
        if ($meta['category'] === '') {
            continue;
        }
        $categories[$meta['category']] = true;
    }
    return array_keys($categories);
}

/**
 * Whether a registered element row passes the optional category /
 * namespace / search filters. Extracted so the callback stays under the
 * complexity gate.
 *
 * @param array{label: string, category: string, hidden: bool, available: bool} $meta
 */
function ox_element_type_matches_filters(
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
