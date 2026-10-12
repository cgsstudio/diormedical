<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: List registered WPBakery elements (overview only).
 *
 * Per-element control schemas are exposed by the dedicated
 * `wpbakery-get-element-schema` ability — this list is intentionally
 * compact so the agent can browse the catalogue cheaply.
 */

wp_register_ability('novamira/wpbakery-list-elements', [
    'label' => __('List WPBakery Elements', domain: 'novamira-pro'),
    'description' => __(
        'Lists registered WPBakery elements as a compact overview: tag, label, is_container, categories. Call this to browse the element catalogue, then call wpbakery-get-element-schema with a specific tag to inspect that element\'s control schema before authoring. Use `tag_contains` ("heading", "btn", "grid", …) to filter by substring when you already know roughly what element you want.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'description' => 'Filter by category name, case-insensitive. WPBakery translates these into the site locale, so read the exact names from the `categories` field of an unfiltered call rather than assuming the English ones. Every name listed there matches at least one element.',
            ],
            'tag_contains' => [
                'type' => 'string',
                'description' => 'Filter to tags whose name contains this substring (case-insensitive). E.g. "heading" returns vc_custom_heading; "btn" returns vc_btn / vc_btn3; "grid" returns vc_basic_grid / vc_masonry_grid.',
            ],
            'include_containers' => [
                'type' => 'boolean',
                'description' => 'Include container elements (vc_row, vc_column, etc.). Default: true.',
                'default' => true,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['elements', 'total', 'categories'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_list_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Browse the WPBakery element catalogue. The response is intentionally compact (no control schemas). Use `tag_contains` ("heading", "btn", "grid", …) when you already know the element family — case-insensitive substring match. Use include_containers=false to hide layout scaffolding (vc_row, vc_column) when you only need content elements, or `category` to narrow further — category names come back translated in the site locale, so take them from the `categories` field of an unfiltered call instead of guessing the English ones. To get the controls of a specific element, call wpbakery-get-element-schema tag="<tag>".',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{category?: string, tag_contains?: string, include_containers?: bool} $input
 * @return array{
 *     elements: list<array<string, mixed>>,
 *     total: int,
 *     categories: list<string>,
 * }
 */
function wpbakery_list_elements(array $input): array
{
    $filters = [
        'category' => $input['category'] ?? null,
        'tag_contains' => is_string($input['tag_contains'] ?? null) ? strtolower(trim($input['tag_contains'])) : '',
        'include_containers' => ($input['include_containers'] ?? true) !== false,
    ];

    $elements = [];
    foreach (\WPBMap::getAllShortCodes() as $tag => $settings) {
        $entry = wpb_build_element_overview($tag, $settings, $filters);
        if ($entry !== null) {
            $elements[] = $entry;
        }
    }

    return [
        'elements' => $elements,
        'total' => count($elements),
        'categories' => wpb_element_categories(),
    ];
}

/**
 * Case-insensitive membership test for an element's category list.
 *
 * @param list<string> $cats
 */
function wpb_category_matches(array $cats, string $wanted): bool
{
    $wanted = strtolower($wanted);
    foreach ($cats as $cat) {
        if (strtolower($cat) === $wanted) {
            return true;
        }
    }

    return false;
}

/**
 * Decides whether a single element survives the active filters and, if so,
 * shapes the compact overview entry. Returns null when the element is filtered
 * out — keeps wpbakery_list_elements small enough to stay under the
 * cyclomatic-complexity bar.
 *
 * @param array<string, mixed> $settings
 * @param array{category: ?string, tag_contains: string, include_containers: bool} $filters
 * @return array<string, mixed>|null
 */
function wpb_build_element_overview(string $tag, array $settings, array $filters): ?array
{
    $is_container = ($settings['is_container'] ?? false) !== false;
    if (!$filters['include_containers'] && $is_container) {
        return null;
    }

    if ($filters['tag_contains'] !== '' && !str_contains(strtolower($tag), $filters['tag_contains'])) {
        return null;
    }

    /** @var list<string>|string $raw_cats */
    $raw_cats = $settings['category'] ?? [];
    $cats = is_array($raw_cats) ? array_map('strval', $raw_cats) : [$raw_cats];

    // WPBakery hands back the category already translated into the site
    // locale ("Struttura", not "Structure"), so an exact match against an
    // English name silently returns nothing on a translated site. Match
    // case-insensitively; the `categories` field of the response lists the
    // names this install actually uses.
    if ($filters['category'] !== null && !wpb_category_matches($cats, $filters['category'])) {
        return null;
    }

    /** @var mixed $name_raw */
    $name_raw = $settings['name'] ?? $tag;
    return [
        'tag' => $tag,
        'label' => is_string($name_raw) && $name_raw !== '' ? $name_raw : $tag,
        'is_container' => $is_container,
        'categories' => array_values(array_filter($cats, static fn(string $c): bool => $c !== '')),
    ];
}
