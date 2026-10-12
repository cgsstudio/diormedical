<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Post;

/**
 * Ability: List Bricks templates.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-templates', [
    'label' => __('List Bricks Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists all Bricks templates with their type, status, and template settings (conditions, etc.). Optionally filter by template type.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Filter by template type, or omit for all.',
                'enum' => ['header', 'footer', 'content', 'section', 'popup', 'archive', 'search', 'error'],
            ],
            'status' => [
                'type' => 'string',
                'description' => 'Post status filter.',
                'default' => 'any',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'modified' => ['type' => 'string'],
                        'template_settings' => ['type' => 'object'],
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Template types include: header, footer, content, section, popup, archive, search, error. Template settings contain conditions that control where the template is applied.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_templates(array $input): array
{
    $args = [
        'post_type' => BRICKS_DB_TEMPLATE_SLUG,
        'posts_per_page' => -1,
        'post_status' => (string) ($input['status'] ?? 'any'),
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    /** @var string|null $type_filter */
    $type_filter = $input['type'] ?? null;

    if ($type_filter !== null) {
        $args['meta_query'] = [
            [
                'key' => BRICKS_DB_TEMPLATE_TYPE,
                'value' => $type_filter,
            ],
        ];
    }

    /** @var list<WP_Post> $posts */
    $posts = get_posts($args);
    $templates = [];

    foreach ($posts as $post) {
        /** @var string|false $raw_type */
        $raw_type = get_post_meta($post->ID, BRICKS_DB_TEMPLATE_TYPE, single: true);
        $type = is_string($raw_type) && $raw_type !== '' ? $raw_type : 'content';
        /** @var array<string, mixed>|false $raw_settings */
        $raw_settings = get_post_meta($post->ID, BRICKS_DB_TEMPLATE_SETTINGS, single: true);
        $settings = is_array($raw_settings) ? $raw_settings : [];

        $templates[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'type' => $type,
            'modified' => $post->post_modified_gmt,
            'template_settings' => (object) $settings,
        ];
    }

    return [
        'templates' => $templates,
        'total' => count($templates),
    ];
}
