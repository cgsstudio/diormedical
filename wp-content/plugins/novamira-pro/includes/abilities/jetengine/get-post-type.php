<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-get-post-type', [
    'label' => __('Get JetEngine Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Return the full JetEngine Custom Post Type definition by slug, in JetEngine\'s own grouped shape: general_settings (name, slug, custom_storage…), labels (singular_name, menu_name…), advanced_settings (public, supports, hierarchical, has_archive, show_in_rest, rewrite…), and meta_fields. Plus an editable flag and a source flag (db|php). This is the same shape accepted by create-post-type / edit-post-type, so a get → edit round-trip is safe.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'The post type slug (e.g. "book").'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'general_settings' => ['type' => 'object'],
            'labels' => ['type' => 'object'],
            'advanced_settings' => ['type' => 'object'],
            'meta_fields' => ['type' => 'array'],
            'editable' => ['type' => 'boolean'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
        ],
        'required' => ['slug', 'general_settings', 'labels', 'advanced_settings', 'meta_fields', 'editable', 'source'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string} $input
 * @return array{slug: string, general_settings: array<array-key, mixed>, labels: array<array-key, mixed>, advanced_settings: array<array-key, mixed>, meta_fields: list<mixed>, editable: bool, source: 'db'|'php'}|WP_Error
 */
function jetengine_get_post_type(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $row = jetengine_cpt_find_row_by_slug($slug);
    if ($row === null) {
        return new WP_Error('post_type_not_found', __('Post type not found.', domain: 'novamira-pro'));
    }

    $data = jetengine_cpt_data();
    if ($data === null) {
        return new WP_Error('post_type_not_found', __('Post type not found.', domain: 'novamira-pro'));
    }

    $id = (int) ($row['id'] ?? 0);

    $item = $data->get_item_for_edit($id);
    if (!is_array($item)) {
        return new WP_Error('post_type_not_found', __('Post type not found.', domain: 'novamira-pro'));
    }

    $meta_fields = jetengine_cct_coerce_array($item['meta_fields'] ?? null);

    return [
        'slug' => $slug,
        'general_settings' => jetengine_cct_coerce_array($item['general_settings'] ?? null),
        'labels' => jetengine_cct_coerce_array($item['labels'] ?? null),
        'advanced_settings' => jetengine_cct_coerce_array($item['advanced_settings'] ?? null),
        'meta_fields' => array_values($meta_fields),
        'editable' => true,
        'source' => 'db',
    ];
}
