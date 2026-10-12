<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Get Bricks element data for a post or template.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-get-content', [
    'label' => __('Get Bricks Content', domain: 'novamira-pro'),
    'description' => __(
        'Retrieves the Bricks element tree for a given post or template, for one or all areas (header, content, footer), plus element_count. Pass summary: true to get one compact row per element (id, name, parent, label, children_count, has_loop, cid — no settings): the right read to verify a write, to pick ids for bricks-insert-content / bricks-remove-content / bricks-patch-elements, or to check a large page without paying for its settings. Without summary each element is returned in full with its settings.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID.',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to retrieve: "header", "content", or "footer". Omit to get all areas.',
                'enum' => ['header', 'content', 'footer'],
            ],
            'summary' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'True = one compact row per element (id, name, parent, label, children_count, has_loop, cid) without settings. Use it to verify a write or to navigate a page cheaply.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_title' => ['type' => 'string'],
            'editor_mode' => ['type' => 'string', 'description' => '"bricks" or "wordpress".'],
            'element_count' => [
                'type' => 'integer',
                'description' => 'Total number of elements across the returned areas, read from the stored meta.',
            ],
            'element_counts' => [
                'type' => 'object',
                'description' => 'Element count per returned area.',
            ],
            'summary' => [
                'type' => 'boolean',
                'description' => 'True when areas hold compact rows instead of full elements.',
            ],
            'areas' => [
                'type' => 'object',
                'description' => 'Element data keyed by area name. Each value is an array of element objects (full, or compact rows with summary: true).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the raw Bricks element tree as a flat array. Each element has: id (string), name (element type), parent (parent id or 0), children (array of child element IDs, present on parent elements), and settings (control values). With summary: true each element is reduced to id, name, parent, label, children_count, has_loop, cid. element_count / element_counts are read from the stored meta — use them to confirm a write landed. Use novamira/bricks-list-elements to discover available element types.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_content(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    /** @var string|null $area */
    $area = $input['area'] ?? null;
    $summary = ($input['summary'] ?? false) === true;

    $meta_keys = bricks_area_meta_keys();
    $keys_to_fetch = $area !== null ? [$area => $meta_keys[$area]] : $meta_keys;

    $areas = [];
    $counts = [];
    $total = 0;
    foreach ($keys_to_fetch as $area_name => $meta_key) {
        /** @var array<array-key, mixed>|string|false $data */
        $data = get_post_meta($post_id, $meta_key, single: true);
        /** @var list<array<string, mixed>> $elements */
        $elements = is_array($data)
            ? array_values(array_filter($data, static fn(mixed $item): bool => is_array($item)))
            : [];
        $areas[$area_name] = $summary ? bricks_summarize_elements($elements) : $elements;
        $counts[$area_name] = count($elements);
        $total += count($elements);
    }

    /** @var string $editor_mode */
    $editor_mode = get_post_meta($post_id, BRICKS_DB_EDITOR_MODE, single: true);

    return [
        'post_id' => $post_id,
        'post_title' => $post->post_title,
        'editor_mode' => $editor_mode !== '' ? $editor_mode : 'wordpress',
        'element_count' => $total,
        'element_counts' => $counts,
        'summary' => $summary,
        'areas' => $areas,
    ];
}
