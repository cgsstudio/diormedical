<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// acpt-create-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-create-taxonomy', [
    'label' => __('Create ACPT Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACPT-managed custom taxonomy. Required: slug (WP taxonomy slug, ≤32 chars), singular, plural. Optional: post_types[] (CPT slugs to associate), labels{} (WP label overrides), settings{} (public, hierarchical, show_in_rest, etc.). Reserved WP taxonomy slugs are rejected by the ACPT model.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'WP taxonomy slug. Max 32 chars.',
            ],
            'singular' => [
                'type' => 'string',
                'description' => 'Singular label (e.g. "Genre").',
            ],
            'plural' => [
                'type' => 'string',
                'description' => 'Plural label (e.g. "Genres").',
            ],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'CPT slugs to associate with this taxonomy.',
            ],
            'labels' => [
                'type' => 'object',
                'description' => 'WP label overrides.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'register_taxonomy() settings: public, hierarchical, show_in_rest, etc.',
            ],
        ],
        'required' => ['slug', 'singular', 'plural'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
        ],
        'required' => ['success', 'slug'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_create_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an ACPT taxonomy. The slug must be unique and must not collide with reserved WP taxonomy terms. Pass post_types[] to associate with CPTs immediately.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-edit-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-edit-taxonomy', [
    'label' => __('Edit ACPT Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACPT-managed taxonomy (PATCH for top-level fields; REPLACE for labels{}). Identify by slug. Omitted fields keep their current value.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'WP taxonomy slug to edit.',
            ],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'labels' => [
                'type' => 'object',
                'description' => 'Replaces the entire labels map when provided.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Merged into existing settings when provided.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'slug' => ['type' => 'string'],
        ],
        'required' => ['success', 'slug'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_edit_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH-style edit of an ACPT taxonomy. Renaming the slug is not supported. labels{} is replaced entirely when provided.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-delete-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-delete-taxonomy', [
    'label' => __('Delete ACPT Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes an ACPT-managed taxonomy from ACPT\'s registry. Idempotent: deleting a non-existent slug returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'WP taxonomy slug to delete.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'slug' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'slug'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_delete_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes the ACPT taxonomy record. Existing WP terms for this taxonomy remain in the DB — use wp_delete_term() separately if needed.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * @param array{slug: string, singular: string, plural: string, post_types?: list<string>, labels?: array<string, mixed>, settings?: array<string, mixed>} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_create_taxonomy(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = trim($args['slug']);
    if ($slug === '') {
        return new WP_Error('acpt_invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    if (\ACPT\Core\Repository\TaxonomyRepository::exists($slug)) {
        return new WP_Error('acpt_already_exists', sprintf(
            /* translators: %s: taxonomy slug. */
            __('ACPT taxonomy "%s" already exists.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $singular = trim($args['singular']);
    $plural = trim($args['plural']);
    if ($singular === '' || $plural === '') {
        return new WP_Error('acpt_invalid_input', __('singular and plural are required.', domain: 'novamira-pro'));
    }

    $labels = $args['labels'] ?? [];
    $settings = $args['settings'] ?? ['public' => true, 'hierarchical' => false];

    $result = acpt_handle_api_result(static function () use ($slug, $singular, $plural, $labels, $settings): void {
        $uuid = acpt_uuid_v4();
        $model = \ACPT\Core\Models\Taxonomy\TaxonomyModel::hydrateFromArray([
            'id' => $uuid,
            'slug' => $slug,
            'singular' => $singular,
            'plural' => $plural,
            'native' => false,
            'labels' => $labels,
            'settings' => $settings,
        ]);
        \ACPT\Core\Repository\TaxonomyRepository::save($model);
    });

    if (!$result['success']) {
        return new WP_Error(
            'acpt_create_taxonomy_failed',
            $result['error'] ?? __('Failed to create taxonomy.', domain: 'novamira-pro'),
        );
    }

    // Associate with CPTs if requested.
    $post_types = $args['post_types'] ?? [];
    acpt_associate_taxonomy_to_post_types($slug, $post_types);

    $id = \ACPT\Core\Repository\TaxonomyRepository::getId($slug);

    return [
        'success' => true,
        'id' => (string) $id,
        'slug' => $slug,
        'singular' => $singular,
        'plural' => $plural,
    ];
}

/**
 * @param array{slug: string, singular?: string, plural?: string, labels?: array<string, mixed>, settings?: array<string, mixed>} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_edit_taxonomy(array $args): array|WP_Error
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
    $singular = isset($args['singular']) ? trim($args['singular']) : $model->getSingular();
    $plural = isset($args['plural']) ? trim($args['plural']) : $model->getPlural();
    $labels = isset($args['labels']) ? $args['labels'] : $model->getLabels();
    $merged_settings = isset($args['settings'])
        ? array_merge($model->getSettings(), $args['settings'])
        : $model->getSettings();

    $result = acpt_handle_api_result(static function () use (
        $model,
        $singular,
        $plural,
        $labels,
        $merged_settings,
    ): void {
        $updated = \ACPT\Core\Models\Taxonomy\TaxonomyModel::hydrateFromArray([
            'id' => $model->getId(),
            'slug' => $model->getSlug(),
            'singular' => $singular,
            'plural' => $plural,
            'native' => $model->isNative(),
            'labels' => $labels,
            'settings' => $merged_settings,
        ]);
        \ACPT\Core\Repository\TaxonomyRepository::save($updated);
    });

    if (!$result['success']) {
        return new WP_Error(
            'acpt_edit_taxonomy_failed',
            $result['error'] ?? __('Failed to edit taxonomy.', domain: 'novamira-pro'),
        );
    }

    return ['success' => true, 'slug' => $slug];
}

/**
 * @param array{slug: string} $args
 * @return array{deleted: bool, slug: string, reason?: string}|WP_Error
 */
function acpt_delete_taxonomy(array $args): array|WP_Error
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
        return ['deleted' => false, 'slug' => $slug, 'reason' => 'not_found'];
    }

    $result = acpt_handle_api_result(static fn() => \ACPT\Core\Repository\TaxonomyRepository::delete($slug));

    if (!$result['success']) {
        return new WP_Error(
            'acpt_delete_taxonomy_failed',
            $result['error'] ?? __('Failed to delete taxonomy.', domain: 'novamira-pro'),
        );
    }

    return ['deleted' => true, 'slug' => $slug];
}

/**
 * Associate a taxonomy with a list of CPT slugs.
 *
 * @param list<string> $post_types
 */
function acpt_associate_taxonomy_to_post_types(string $taxonomy_slug, array $post_types): void
{
    if ($post_types === []) {
        return;
    }
    $tax_id = \ACPT\Core\Repository\TaxonomyRepository::getId($taxonomy_slug);
    if ($tax_id === null) {
        return;
    }
    foreach ($post_types as $cpt_slug) {
        $cpt_id = \ACPT\Core\Repository\CustomPostTypeRepository::getId($cpt_slug);
        if ($cpt_id !== null) {
            acpt_handle_api_result(
                static fn() => \ACPT\Core\Repository\TaxonomyRepository::assocToPostType($cpt_id, $tax_id),
            );
        }
    }
}
