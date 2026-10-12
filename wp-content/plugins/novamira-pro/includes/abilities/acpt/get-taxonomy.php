<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-get-taxonomy', [
    'label' => __('Get ACPT Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full schema for one ACPT-managed taxonomy identified by slug. Includes id, slug, singular, plural, native, labels, settings, and the CPT names this taxonomy is associated with.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The WP taxonomy slug (e.g. "genre", "category").',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'native' => ['type' => 'boolean'],
            'labels' => ['type' => 'object'],
            'settings' => ['type' => 'object'],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['id', 'slug', 'singular', 'plural'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_get_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetches one ACPT taxonomy by its WP slug. post_types[] shows which CPTs use this taxonomy. Edit via novamira/acpt-edit-taxonomy.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_get_taxonomy(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = trim($args['slug']);
    if ($slug === '') {
        return new WP_Error('acpt_invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    if (!\ACPT\Core\Repository\TaxonomyRepository::exists($slug)) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: taxonomy slug. */
            __('ACPT taxonomy "%s" not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $rows = \ACPT\Core\Repository\TaxonomyRepository::get(['taxonomy' => $slug]);
    if ($rows === []) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: taxonomy slug. */
            __('ACPT taxonomy "%s" not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $model = $rows[0];
    $cpt_names = [];
    foreach ($model->getCustomPostTypes() as $cpt) {
        $cpt_names[] = $cpt->getName();
    }

    return [
        'id' => $model->getId(),
        'slug' => $model->getSlug(),
        'singular' => $model->getSingular(),
        'plural' => $model->getPlural(),
        'native' => $model->isNative(),
        'labels' => $model->getLabels(),
        'settings' => $model->getSettings(),
        'post_types' => $cpt_names,
    ];
}
