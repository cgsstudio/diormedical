<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-list-post-types', [
    'label' => __('List ACPT Post Types', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight list of all custom post types registered through ACPT. Each entry contains id, name (the WP post type slug), singular, plural, icon, native flag, and post_count. Use novamira/acpt-get-post-type to fetch the full schema for a single CPT.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_types' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'singular' => ['type' => 'string'],
                        'plural' => ['type' => 'string'],
                        'icon' => ['type' => 'string'],
                        'native' => ['type' => 'boolean'],
                        'post_count' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => true,
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['post_types', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_list_post_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns all ACPT-defined CPTs. Use the name field (the WP post type slug) as the identifier in subsequent calls. To read the full settings/labels/meta groups for one CPT call novamira/acpt-get-post-type with that name.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{post_types: list<array<string, mixed>>, total: int}|WP_Error
 */
function acpt_list_post_types(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $models = acpt_handle_api_result(static fn() => \ACPT\Core\Repository\CustomPostTypeRepository::get());

    if (!$models['success']) {
        return new WP_Error(
            'acpt_list_post_types_failed',
            $models['error'] ?? __('Failed to list CPTs.', domain: 'novamira-pro'),
        );
    }

    $post_types = [];
    $rows = \ACPT\Core\Repository\CustomPostTypeRepository::get();
    foreach ($rows as $model) {
        $post_types[] = [
            'id' => $model->getId(),
            'name' => $model->getName(),
            'singular' => $model->getSingular(),
            'plural' => $model->getPlural(),
            'icon' => $model->getIcon(),
            'native' => $model->isNative(),
            'post_count' => $model->getPostCount(),
        ];
    }

    return ['post_types' => $post_types, 'total' => count($post_types)];
}
