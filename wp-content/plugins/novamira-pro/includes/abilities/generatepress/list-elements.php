<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-list-elements', [
    'label' => __('List GeneratePress Elements', domain: 'novamira-pro'),
    'description' => __(
        'Lists `gp_elements` posts (Hook Elements + Block Elements + headers + layouts) as a compact summary per element — id, title, status, type, block_type, hook, priority, modified, and per-bucket conditions counts. The `content` body is intentionally omitted (can be long raw HTML / shortcode) — drill down via generatepress-get-element for a specific id. Filters compose: `type`, `block_type`, `status`, `hook`, `limit`.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['hook', 'header', 'layout', 'block'],
                'description' => 'Filter to a single element type.',
            ],
            'block_type' => [
                'type' => 'string',
                'enum' => [
                    'site-header',
                    'site-footer',
                    'right-sidebar',
                    'left-sidebar',
                    'content-template',
                    'loop-template',
                    'search-modal',
                    'page-hero',
                    'post-meta-template',
                    'post-navigation-template',
                    'archive-navigation-template',
                    'hook',
                ],
                'description' => 'Filter Block Elements by their `_generate_block_type` (only meaningful when type=block).',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'trash', 'any'],
                'description' => 'Post status filter. Defaults to `publish`. Use `any` to include drafts / trash.',
                'default' => 'publish',
            ],
            'hook' => [
                'type' => 'string',
                'description' => 'Filter Hook Elements by their `_generate_hook` value (action name).',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of elements to return. Default 100. Use a smaller value for very large catalogues; the list is sorted by post id descending (newest first).',
                'default' => 100,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['elements', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_list_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default response excludes the `content` body. Use `type` and `status` filters to keep the list focused. Call generatepress-get-element with a specific id when you need the body or the full conditions arrays.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string, block_type?: string, status?: string, hook?: string, limit?: int} $input
 * @return array{elements: list<array<string, mixed>>, total: int}
 */
function generatepress_list_elements(array $input): array
{
    $args = [
        'post_type' => 'gp_elements',
        'post_status' => $input['status'] ?? 'publish',
        'posts_per_page' => isset($input['limit']) ? max(1, $input['limit']) : 100,
        'orderby' => 'ID',
        'order' => 'DESC',
        'no_found_rows' => true,
    ];

    $meta_query = [];
    if (isset($input['type']) && $input['type'] !== '') {
        $meta_query[] = ['key' => '_generate_element_type', 'value' => $input['type']];
    }
    if (isset($input['block_type']) && $input['block_type'] !== '') {
        $meta_query[] = ['key' => '_generate_block_type', 'value' => $input['block_type']];
    }
    if (isset($input['hook']) && $input['hook'] !== '') {
        $meta_query[] = ['key' => '_generate_hook', 'value' => $input['hook']];
    }
    if ($meta_query !== []) {
        $args['meta_query'] = $meta_query;
    }

    /** @var list<\WP_Post> $posts */
    $posts = get_posts($args);
    $out = [];
    foreach ($posts as $post) {
        $out[] = gp_element_summary($post);
    }
    return ['elements' => $out, 'total' => count($out)];
}
