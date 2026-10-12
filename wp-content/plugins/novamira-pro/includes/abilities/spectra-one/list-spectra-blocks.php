<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SpectraOne;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate the Spectra (UAGB) plugin block library — the
 * `spectra-one` companion plugin's block set. Gated on
 * `spectra_plugin_available()` (theme's own companion detection); when
 * the plugin is not active, returns `spectra_one_requires_plugin` so
 * the agent can propose installing the plugin before deep-diving.
 *
 * Reads the WP_Block_Type_Registry directly rather than a Spectra-side
 * accessor — the plugin registers its blocks under the `uagb/*` slug
 * prefix, and the block registry is the source of truth for what is
 * actually available on the request.
 */

wp_register_ability('novamira/spectra-one-list-spectra-blocks', [
    'label' => __('List Spectra (UAGB) Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Returns the catalogue of Spectra (UAGB) blocks registered by the companion plugin. Compact rows: name (fully-qualified `uagb/*` slug), title, category. Optional `category` narrows the response (e.g. `uagb-category`). Progressive disclosure — per-block attribute schemas are NOT echoed; use the base Novamira gutenberg / block-schema abilities for those. Requires the Spectra (UAGB) plugin: when the plugin is missing / under-min, returns `spectra_one_requires_plugin`.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra-one',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'maxLength' => 60,
                'description' => 'Optional block-category slug filter (case-sensitive). Empty (default) returns every registered UAGB block.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 200,
                'default' => 100,
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'blocks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 120],
                        'title' => ['type' => 'string', 'maxLength' => 200],
                        'category' => ['type' => 'string', 'maxLength' => 60],
                    ],
                    'required' => ['name', 'title', 'category'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => ['type' => 'integer', 'minimum' => 0],
            'returned' => ['type' => 'integer', 'minimum' => 0],
            'plugin_version' => [
                'type' => 'string',
                'maxLength' => 40,
                'description' => 'The active Spectra (UAGB) plugin version. Always non-empty when this ability succeeds — the ability is registered only when the plugin is available.',
            ],
        ],
        'required' => ['blocks', 'total', 'returned', 'plugin_version'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\SpectraOne\\spectra_one_list_spectra_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Companion-plugin discovery. Per-block attribute schemas live on the base Novamira gutenberg / block-schema abilities — this ability only surfaces the catalogue.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{category?: string, limit?: int, offset?: int} $input
 * @return array{
 *     blocks: list<array{name: string, title: string, category: string}>,
 *     total: int,
 *     returned: int,
 *     plugin_version: string,
 * }|WP_Error
 */
function spectra_one_list_spectra_blocks(array $input): array|WP_Error
{
    if (!spectra_one_theme_available()) {
        return so_not_active();
    }
    if (!\Novamira\Pro\Abilities\SpectraOne\spectra_plugin_available()) {
        return so_requires_plugin();
    }

    $limit = so_clamp_int($input['limit'] ?? 100, min: 1, max: 200);
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $category_filter = trim($input['category'] ?? '');

    /** @var mixed $registry */
    $registry = class_exists('WP_Block_Type_Registry') ? \WP_Block_Type_Registry::get_instance() : null;
    if (!$registry instanceof \WP_Block_Type_Registry) {
        return new WP_Error(
            'spectra_one_block_registry_unavailable',
            __(
                'The WordPress block-type registry is not available on this request. This should never happen on a normally-booted WP; retry after full plugin load.',
                domain: 'novamira-pro',
            ),
            ['status' => 500],
        );
    }

    /** @var array<string, \WP_Block_Type> $all */
    $all = $registry->get_all_registered();
    $rows = [];
    foreach ($all as $name => $type) {
        if (!str_starts_with($name, 'uagb/')) {
            continue;
        }
        $category = so_block_type_string($type, property: 'category');
        if ($category_filter !== '' && $category !== $category_filter) {
            continue;
        }
        $rows[] = [
            'name' => $name,
            'title' => so_block_type_string($type, property: 'title'),
            'category' => $category,
        ];
    }

    usort($rows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    $total = count($rows);
    $paged = array_slice($rows, $offset, $limit);

    return [
        'blocks' => array_values($paged),
        'total' => $total,
        'returned' => count($paged),
        'plugin_version' => spectra_plugin_version(),
    ];
}
