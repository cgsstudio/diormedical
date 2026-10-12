<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Abilities: manage the custom taxonomies Etch registers from the
 * etch_taxonomies option (map { slug => { args, object_types } }). object_types
 * is the list of post type slugs the taxonomy attaches to. CRUD is guarded by
 * the etch_option_lock. Reuses etch_cpt_slug_or_error / etch_cpt_args_or_error
 * from manage-post-types.php.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The etch_taxonomies option as a normalized map { slug => {args, object_types} }.
 *
 * @return array<array-key, mixed>
 */
function etch_taxonomies_option(): array
{
    return etch_option_array('etch_taxonomies');
}

/**
 * Resolve a taxonomy slug within the given map, or a WP_Error.
 *
 * @param array<array-key, mixed> $taxonomies
 * @return array<array-key, mixed>|WP_Error
 */
function etch_taxonomy_or_error(array $taxonomies, string $slug): array|WP_Error
{
    if (!isset($taxonomies[$slug])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: taxonomy slug. */
            __('Taxonomy "%s" was not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    /** @var mixed $entry */
    $entry = $taxonomies[$slug];

    return is_array($entry) ? $entry : [];
}

/**
 * Validate object_types — a list of non-empty post type slugs.
 *
 * @return list<string>|WP_Error
 */
function etch_object_types_or_error(mixed $object_types): array|WP_Error
{
    if (!is_array($object_types)) {
        return etch_error('etch_invalid_input', __(
            '"object_types" must be an array of post type slugs.',
            domain: 'novamira-pro',
        ));
    }

    $out = [];
    foreach (array_keys($object_types) as $key) {
        /** @var mixed $type */
        $type = $object_types[$key];
        if (!is_string($type) || $type === '') {
            return etch_error('etch_invalid_input', __(
                'Each "object_types" entry must be a non-empty post type slug.',
                domain: 'novamira-pro',
            ));
        }
        $out[] = $type;
    }

    return $out;
}

/**
 * Read the display label out of a stored taxonomy args array. Etch's taxonomy
 * mapper builds WP labels from args.labels.pluralName (camelCase), so that is
 * the source of truth; fall back to a top-level "label" for legacy entries.
 *
 * @param array<array-key, mixed> $args
 */
function etch_taxonomy_label(array $args): string
{
    /** @var mixed $labels */
    $labels = $args['labels'] ?? null;
    if (is_array($labels)) {
        /** @var mixed $plural */
        $plural = $labels['pluralName'] ?? null;
        if (is_string($plural) && $plural !== '') {
            return $plural;
        }
    }

    return etch_cpt_label($args);
}

/**
 * Write the agent-supplied display label into the camelCase shape Etch's
 * taxonomy mapper consumes: args.labels.pluralName (→ name + menu_name) and
 * args.labels.singularName (→ singular_name). A top-level "label" or snake_case
 * keys are silently dropped by TaxonomiesService::map_args_to_wp_args().
 *
 * @param array<array-key, mixed> $args
 * @return array<array-key, mixed>
 */
function etch_taxonomy_apply_label(array $args, string $label): array
{
    /** @var mixed $labels */
    $labels = $args['labels'] ?? null;
    $labels = is_array($labels) ? $labels : [];
    $labels['pluralName'] = $label;
    if (!isset($labels['singularName'])) {
        $labels['singularName'] = $label;
    }
    $args['labels'] = $labels;

    return $args;
}

/**
 * Shape one taxonomy entry for output.
 *
 * @param array<array-key, mixed> $entry
 * @return array{slug: string, args: array<array-key, mixed>, object_types: list<string>}
 */
function etch_taxonomy_view(string $slug, array $entry): array
{
    /** @var mixed $args */
    $args = $entry['args'] ?? [];
    /** @var mixed $object_types */
    $object_types = $entry['object_types'] ?? [];

    $types = [];
    if (is_array($object_types)) {
        foreach (array_keys($object_types) as $key) {
            /** @var mixed $type */
            $type = $object_types[$key];
            if (is_string($type)) {
                $types[] = $type;
            }
        }
    }

    return [
        'slug' => $slug,
        'args' => is_array($args) ? $args : [],
        'object_types' => $types,
    ];
}

// ---------------------------------------------------------------------------
// list-taxonomies
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-taxonomies', [
    'label' => __('List Etch Taxonomies', domain: 'novamira-pro'),
    'description' => __(
        'Lists the custom taxonomies Etch registers (the etch_taxonomies option). Returns a compact entry per taxonomy { slug, label, object_types }. Fetch the full args with novamira/etch-get-taxonomy.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'taxonomies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'object_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_taxonomies',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Etch-managed taxonomies only. object_types lists the post types each taxonomy is attached to. Use the slug with novamira/etch-get-taxonomy for the full args.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, taxonomies: list<array{slug: string, label: string, object_types: list<string>}>}
 */
function etch_list_taxonomies(): array
{
    $taxonomies = etch_taxonomies_option();

    $out = [];
    foreach (array_keys($taxonomies) as $slug) {
        // Skip non-string keys (a numeric-looking slug would arrive as an int).
        if (!is_string($slug)) {
            continue;
        }
        /** @var mixed $entry */
        $entry = $taxonomies[$slug];
        if (!is_array($entry)) {
            continue;
        }

        $view = etch_taxonomy_view($slug, $entry);
        $out[] = [
            'slug' => $view['slug'],
            'label' => etch_taxonomy_label($view['args']),
            'object_types' => $view['object_types'],
        ];
    }

    return [
        'count' => count($out),
        'taxonomies' => $out,
    ];
}

// ---------------------------------------------------------------------------
// get-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-taxonomy', [
    'label' => __('Get Etch Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Etch custom taxonomy in full: slug, args (the register_taxonomy configuration), and object_types (the post types it is attached to).',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'description' => 'The taxonomy slug.'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'args' => ['type' => 'object'],
            'object_types' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a taxonomy before editing it with novamira/etch-edit-taxonomy.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{slug: string, args: array<array-key, mixed>, object_types: list<string>}|WP_Error
 */
function etch_get_taxonomy(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $taxonomies = etch_taxonomies_option();
    $entry = etch_taxonomy_or_error($taxonomies, $slug);
    if ($entry instanceof WP_Error) {
        return $entry;
    }

    return etch_taxonomy_view($slug, $entry);
}

// ---------------------------------------------------------------------------
// create-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-taxonomy', [
    'label' => __('Create Etch Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Registers a new Etch custom taxonomy. Requires slug (1-20 lowercase letters/digits/_/-), label, and object_types (the post type slugs it attaches to). Etch maps taxonomy args through a camelCase allowlist, so any extra args must use camelCase keys (hierarchical, showUi, showInMenu, showInNavMenus, showInRest, showInQuickEdit, showInAdminColumn, description, capabilities) and labels via labels.pluralName / labels.singularName; public and show_in_rest default to true. Errors if the slug already exists. Returns slug and label.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'object_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Post type slugs to attach to.',
            ],
            'args' => ['type' => 'object', 'description' => 'Extra register_taxonomy args.'],
        ],
        'required' => ['slug', 'label', 'object_types'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'object_types should be slugs of existing post types (built-in like "post"/"page" or Etch CPTs). Etch reads camelCase taxonomy args only — use showInRest, showInAdminColumn, showInMenu, hierarchical, etc. (snake_case is dropped); public and show_in_rest default to true.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{slug: string, label: string}|WP_Error
 */
function etch_create_taxonomy(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    // Reject slugs that already exist as a WordPress taxonomy (a core built-in
    // like "category"/"post_tag", or another plugin's) — registering over them
    // silently corrupts the existing taxonomy.
    if (taxonomy_exists($slug)) {
        return etch_error('etch_exists', sprintf(
            /* translators: %s: taxonomy slug. */
            __('"%s" is already a registered taxonomy and cannot be re-created.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    /** @var mixed $label */
    $label = $input['label'] ?? '';
    if (!is_string($label) || trim($label) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "label" is required.', domain: 'novamira-pro'));
    }

    $object_types = etch_object_types_or_error($input['object_types'] ?? null);
    if ($object_types instanceof WP_Error) {
        return $object_types;
    }

    $args = etch_cpt_args_or_error($input['args'] ?? []);
    if ($args instanceof WP_Error) {
        return $args;
    }

    $clean_label = sanitize_text_field($label);
    // Etch maps taxonomy args through TaxonomiesService::map_args_to_wp_args(),
    // which only reads camelCase keys (showInRest, showInAdminColumn, …) and
    // labels.pluralName/singularName. public + show_in_rest already default to
    // true inside that mapper, so we only need to inject the label here.
    $args = etch_taxonomy_apply_label($args, $clean_label);

    $lock = etch_option_lock_acquire('etch_taxonomies');
    try {
        $taxonomies = etch_taxonomies_option();
        if (isset($taxonomies[$slug])) {
            return etch_error('etch_exists', sprintf(
                /* translators: %s: taxonomy slug. */
                __('Taxonomy "%s" already exists.', domain: 'novamira-pro'),
                $slug,
            ));
        }
        $taxonomies[$slug] = ['args' => $args, 'object_types' => $object_types];
        update_option('etch_taxonomies', $taxonomies);
    } finally {
        etch_option_lock_release($lock);
    }

    etch_set_flush_rewrite_transient();

    return ['slug' => $slug, 'label' => $clean_label];
}

// ---------------------------------------------------------------------------
// edit-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-taxonomy', [
    'label' => __('Edit Etch Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Etch custom taxonomy (partial update). Provide slug and any of label, args (merged into the stored config), or object_types (replaces the attached post types). Like create-taxonomy, `args` use Etch\'s camelCase allowlist (showInRest, showInAdminColumn, showInMenu, hierarchical, …) with labels via labels.pluralName / labels.singularName — snake_case keys are dropped by Etch\'s mapper. Returns slug and label.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'object_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'args' => ['type' => 'object'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial: args merge over existing; object_types (if passed) replaces the whole attached-types list; label updates the label. The slug cannot change.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{slug: string, label: string}|WP_Error
 */
function etch_edit_taxonomy(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $merge = [];
    if (isset($input['args'])) {
        $merge = etch_cpt_args_or_error($input['args']);
        if ($merge instanceof WP_Error) {
            return $merge;
        }
    }

    $new_label = null;
    if (isset($input['label'])) {
        /** @var mixed $label */
        $label = $input['label'];
        if (!is_string($label) || trim($label) === '') {
            return etch_error('etch_invalid_input', __('"label" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $new_label = sanitize_text_field($label);
    }

    $new_types = null;
    if (isset($input['object_types'])) {
        $new_types = etch_object_types_or_error($input['object_types']);
        if ($new_types instanceof WP_Error) {
            return $new_types;
        }
    }

    $result = ['slug' => $slug, 'label' => ''];
    $lock = etch_option_lock_acquire('etch_taxonomies');
    try {
        $taxonomies = etch_taxonomies_option();
        $entry = etch_taxonomy_or_error($taxonomies, $slug);
        if ($entry instanceof WP_Error) {
            return $entry;
        }

        $view = etch_taxonomy_view($slug, $entry);
        $args = array_merge($view['args'], $merge);
        if ($new_label !== null) {
            $args = etch_taxonomy_apply_label($args, $new_label);
        }
        $types = $new_types ?? $view['object_types'];

        $taxonomies[$slug] = ['args' => $args, 'object_types' => $types];
        update_option('etch_taxonomies', $taxonomies);

        $result = ['slug' => $slug, 'label' => etch_taxonomy_label($args)];
    } finally {
        etch_option_lock_release($lock);
    }

    etch_set_flush_rewrite_transient();

    return $result;
}

// ---------------------------------------------------------------------------
// delete-taxonomy
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-taxonomy', [
    'label' => __('Delete Etch Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Etch custom taxonomy registration by slug. Existing terms remain in the database but the taxonomy is no longer registered. Returns slug and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE for the registration only — existing terms are orphaned (not deleted).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{slug: string, deleted: bool}|WP_Error
 */
function etch_delete_taxonomy(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $slug = etch_cpt_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $lock = etch_option_lock_acquire('etch_taxonomies');
    try {
        $taxonomies = etch_taxonomies_option();
        $entry = etch_taxonomy_or_error($taxonomies, $slug);
        if ($entry instanceof WP_Error) {
            return $entry;
        }

        unset($taxonomies[$slug]);
        update_option('etch_taxonomies', $taxonomies);
    } finally {
        etch_option_lock_release($lock);
    }

    etch_set_flush_rewrite_transient();

    return ['slug' => $slug, 'deleted' => true];
}
