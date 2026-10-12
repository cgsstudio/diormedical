<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-list-taxonomies', [
    'label' => __('List ACPT Taxonomies', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight list of all custom taxonomies registered through ACPT. Each entry contains id, slug (WP taxonomy slug), singular, plural, native flag, and the list of CPT names it is associated with. Use novamira/acpt-get-taxonomy to fetch the full schema for one taxonomy.',
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
            'taxonomies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'singular' => ['type' => 'string'],
                        'plural' => ['type' => 'string'],
                        'native' => ['type' => 'boolean'],
                        'post_types' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'additionalProperties' => true,
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['taxonomies', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_list_taxonomies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns all ACPT-defined taxonomies. Use the slug field as the identifier in subsequent calls. post_types[] lists which CPTs this taxonomy is attached to. For full labels/settings call novamira/acpt-get-taxonomy.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{taxonomies: list<array<string, mixed>>, total: int}|WP_Error
 */
function acpt_list_taxonomies(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $rows = \ACPT\Core\Repository\TaxonomyRepository::get();
    $taxonomies = [];
    foreach ($rows as $model) {
        $cpt_names = [];
        foreach ($model->getCustomPostTypes() as $cpt) {
            $cpt_names[] = $cpt->getName();
        }
        $taxonomies[] = [
            'id' => $model->getId(),
            'slug' => $model->getSlug(),
            'singular' => $model->getSingular(),
            'plural' => $model->getPlural(),
            'native' => $model->isNative(),
            'post_types' => $cpt_names,
        ];
    }

    return ['taxonomies' => $taxonomies, 'total' => count($taxonomies)];
}
