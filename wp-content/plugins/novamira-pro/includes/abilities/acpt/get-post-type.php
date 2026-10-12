<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-get-post-type', [
    'label' => __('Get ACPT Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full schema for one ACPT-managed custom post type identified by name (WP slug). Default response includes identity, supports[], labels, settings, and meta_group_count. Pass include_meta_groups=true to also receive the names and box counts of attached meta groups (without fields — call novamira/acpt-get-field-group-schema for field details).',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The WP post type slug (e.g. "book", "product").',
            ],
            'include_meta_groups' => [
                'type' => 'boolean',
                'description' => 'When true, includes a meta_groups[] array with name, label, and box_count for each attached meta group. Default false.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'icon' => ['type' => 'string'],
            'native' => ['type' => 'boolean'],
            'supports' => ['type' => 'array', 'items' => ['type' => 'string']],
            'labels' => ['type' => 'object'],
            'settings' => ['type' => 'object'],
            'post_count' => ['type' => 'integer'],
            'meta_group_count' => ['type' => 'integer'],
            'meta_groups' => ['type' => 'array'],
        ],
        'required' => ['id', 'name', 'singular', 'plural', 'meta_group_count'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetches one ACPT CPT by its WP post-type slug. meta_group_count tells you how many meta groups are attached without loading them. Pass include_meta_groups=true when you need to see which groups are attached (still no fields — call acpt-get-field-group-schema for that). Edit via novamira/acpt-edit-post-type.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{name: string, include_meta_groups?: bool} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_get_post_type(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = $args['name'];
    $include_meta_groups = $args['include_meta_groups'] ?? false;

    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    if (!\ACPT\Core\Repository\CustomPostTypeRepository::exists($name)) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: post type name. */
            __('ACPT post type "%s" not found.', domain: 'novamira-pro'),
            $name,
        ));
    }

    $rows = \ACPT\Core\Repository\CustomPostTypeRepository::get(['postType' => $name]);
    if ($rows === []) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: post type name. */
            __('ACPT post type "%s" not found.', domain: 'novamira-pro'),
            $name,
        ));
    }

    $model = $rows[0];

    // Fetch attached meta groups for count (and optionally summary).
    $meta_groups = \ACPT\Core\Repository\MetaRepository::get([
        'belongsTo' => 'customPostType',
        'find' => $name,
    ]);

    $out = [
        'id' => $model->getId(),
        'name' => $model->getName(),
        'singular' => $model->getSingular(),
        'plural' => $model->getPlural(),
        'icon' => $model->getIcon(),
        'native' => $model->isNative(),
        'supports' => $model->getSupports(),
        'labels' => $model->getLabels(),
        'settings' => $model->getSettings(),
        'post_count' => $model->getPostCount(),
        'meta_group_count' => count($meta_groups),
    ];

    if ($include_meta_groups) {
        $groups_summary = [];
        foreach ($meta_groups as $group) {
            $groups_summary[] = [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'label' => $group->getLabel(),
                'box_count' => count($group->getBoxes()),
            ];
        }
        $out['meta_groups'] = $groups_summary;
    }

    return $out;
}
