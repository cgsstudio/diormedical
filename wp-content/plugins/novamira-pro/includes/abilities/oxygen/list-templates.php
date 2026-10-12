<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-list-templates', [
    'label' => __('List Oxygen Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists Oxygen "template-like" posts across the editable template CPTs Oxygen 6 registers (`oxygen_template`, `oxygen_header`, `oxygen_footer`, `oxygen_block`). Each row is a compact summary: id, title, post_type + friendly label, status, modified date, the template settings `type` slug (e.g. `single`, `archive`), the priority value, and the number of rule groups currently attached. Use `post_type` to scope (e.g. only headers) and `limit` to keep the response small.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'enum' => [
                    'oxygen_template',
                    'oxygen_header',
                    'oxygen_footer',
                    'oxygen_block',
                ],
                'description' => 'Filter to a single CPT. Omit to return all four editable template CPTs.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'any'],
                'description' => 'Post status filter. Defaults to `publish`. Use `any` to include drafts (trashed templates are NEVER returned, they are managed via delete-template only).',
                'default' => 'publish',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of rows to return. Default 100. Server clamps to 500.',
                'default' => 100,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['templates', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always run this before get-template. Use `post_type` to keep the response focused (e.g. only headers when designing a site-header).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_type?: string, status?: string, limit?: int} $input
 * @return array<string, mixed>
 */
function oxygen_list_templates(array $input): array
{
    $post_types = isset($input['post_type']) ? [$input['post_type']] : ox_template_post_types();
    $status = isset($input['status']) ? $input['status'] : 'publish';
    /** @var mixed $raw_limit */
    $raw_limit = $input['limit'] ?? 100;
    $limit = min(500, max(1, ox_coerce_int($raw_limit)));

    $query_status = $status === 'any' ? ['publish', 'draft'] : [$status];
    /** @var mixed $raw_posts */
    $raw_posts = get_posts([
        'post_type' => $post_types,
        'post_status' => $query_status,
        'numberposts' => $limit,
        'orderby' => 'modified',
        'order' => 'DESC',
    ]);
    $posts = is_array($raw_posts) ? $raw_posts : [];

    $rows = [];
    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        $rows[] = ox_template_summary($post);
    }
    return ['templates' => $rows, 'total' => count($rows)];
}
