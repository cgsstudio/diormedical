<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// acpt-create-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-create-post-type', [
    'label' => __('Create ACPT Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACPT-managed custom post type. Required: name (WP slug, ≤20 chars, lowercase alphanumeric/dash/underscore), singular, plural. Optional: icon (dashicons slug), supports[] (title|editor|thumbnail|excerpt|revisions|etc.), labels{} (WP label overrides), settings{} (public, show_in_rest, hierarchical, has_archive, menu_position, etc.). Reserved WP slugs (post, page, attachment, revision, nav_menu_item, wp_block, wp_template, wp_template_part, wp_navigation) are rejected.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'WP post type slug. Lowercase alphanumeric, dash, underscore. Max 20 chars.',
            ],
            'post_name' => [
                'type' => 'string',
                'description' => 'ACPT-native alias for name (matches register_acpt_post_type signature).',
            ],
            'singular' => [
                'type' => 'string',
                'description' => 'Singular label (e.g. "Book").',
            ],
            'singular_label' => [
                'type' => 'string',
                'description' => 'ACPT-native alias for singular.',
            ],
            'plural' => [
                'type' => 'string',
                'description' => 'Plural label (e.g. "Books").',
            ],
            'plural_label' => [
                'type' => 'string',
                'description' => 'ACPT-native alias for plural.',
            ],
            'icon' => [
                'type' => 'string',
                'description' => 'Dashicons class or URL. Default: dashicons-admin-post.',
            ],
            'supports' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'WP post type features. E.g. ["title","editor","thumbnail"].',
            ],
            'labels' => [
                'type' => 'object',
                'description' => 'WP label overrides. Keys mirror register_post_type labels array.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'WP register_post_type settings: public, show_in_rest, hierarchical, has_archive, menu_position, etc.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
        ],
        'required' => ['success', 'name'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_create_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an ACPT CPT. The name must be unique and not reserved. On collision with an existing ACPT CPT the call returns an error — it does not overwrite. After creation, attach meta groups with novamira/acpt-create-field-group.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-edit-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-edit-post-type', [
    'label' => __('Edit ACPT Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACPT-managed custom post type (PATCH semantics for top-level fields; REPLACE for supports[] and labels{}). Identify by name. Omitted top-level fields keep their current value. Passing supports[] or labels{} replaces the entire array.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'WP post type slug to edit.',
            ],
            'singular' => ['type' => 'string'],
            'plural' => ['type' => 'string'],
            'icon' => ['type' => 'string'],
            'supports' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Replaces the entire supports array when provided.',
            ],
            'labels' => [
                'type' => 'object',
                'description' => 'Replaces the entire labels map when provided.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Merged into existing settings when provided.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
        ],
        'required' => ['success', 'name'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_edit_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH-style edit of an ACPT CPT. Renaming the slug (name field) is not supported — create a new CPT and migrate content instead. Only the fields you pass are changed; others stay as-is. supports[] and labels{} are replaced entirely when provided.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-delete-post-type
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-delete-post-type', [
    'label' => __('Delete ACPT Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes an ACPT-managed custom post type from ACPT\'s registry. By default leaves existing posts in wp_posts (they become orphaned with an unregistered post_type). Pass delete_posts=true to also delete all posts of this type from wp_posts. Idempotent: deleting a non-existent name returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'WP post type slug to delete.',
            ],
            'delete_posts' => [
                'type' => 'boolean',
                'description' => 'When true, also deletes all wp_posts rows of this type. Default false.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'name'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_delete_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes the ACPT CPT record. Existing posts are NOT deleted by default — they become orphaned. Pass delete_posts=true only if you want to permanently remove all content of this type. This operation cannot be undone.',
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
 * Validates that a new CPT name is acceptable (not empty, not reserved, not existing).
 */
function acpt_validate_new_cpt_name(string $name): ?WP_Error
{
    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    if (in_array($name, acpt_reserved_post_type_slugs(), strict: true)) {
        return new WP_Error('acpt_reserved_slug', sprintf(
            /* translators: %s: post type slug. */
            __('"%s" is a reserved WordPress post type slug and cannot be used.', domain: 'novamira-pro'),
            $name,
        ));
    }

    if (\ACPT\Core\Repository\CustomPostTypeRepository::exists($name)) {
        return new WP_Error('acpt_already_exists', sprintf(
            /* translators: %s: post type name. */
            __('ACPT post type "%s" already exists.', domain: 'novamira-pro'),
            $name,
        ));
    }

    return null;
}

/**
 * Hydrates and saves a new CPT model. Called inside acpt_handle_api_result().
 *
 * @param array{name: string, singular: string, plural: string, icon: string, supports: list<string>, labels: array<string, mixed>, settings: array<string, mixed>} $data
 */
function acpt_save_new_cpt(array $data): void
{
    $uuid = acpt_uuid_v4();
    $model = \ACPT\Core\Models\CustomPostType\CustomPostTypeModel::hydrateFromArray([
        'id' => $uuid,
        'name' => $data['name'],
        'singular' => $data['singular'],
        'plural' => $data['plural'],
        'icon' => $data['icon'],
        'native' => false,
        'supports' => $data['supports'],
        'labels' => $data['labels'],
        'settings' => $data['settings'],
    ]);
    \ACPT\Core\Repository\CustomPostTypeRepository::save($model);
}

/**
 * Resolves PATCH-style edit fields for a CPT by merging args over model defaults.
 *
 * @param array{name: string, singular?: string, plural?: string, icon?: string, supports?: list<string>, labels?: array<string, mixed>, settings?: array<string, mixed>} $args
 * @return array{singular: string, plural: string, icon: string, supports: list<string>, labels: array<string, mixed>, settings: array<string, mixed>}
 */
function acpt_resolve_cpt_edit_fields(array $args, \ACPT\Core\Models\CustomPostType\CustomPostTypeModel $model): array
{
    $singular = isset($args['singular']) ? trim($args['singular']) : $model->getSingular();
    $plural = isset($args['plural']) ? trim($args['plural']) : $model->getPlural();
    $icon = isset($args['icon']) ? $args['icon'] : $model->getIcon();
    $supports = isset($args['supports']) ? $args['supports'] : $model->getSupports();
    $labels = isset($args['labels']) ? $args['labels'] : $model->getLabels();
    $merged_settings = isset($args['settings'])
        ? array_merge($model->getSettings(), $args['settings'])
        : $model->getSettings();

    return [
        'singular' => $singular,
        'plural' => $plural,
        'icon' => $icon,
        'supports' => $supports,
        'labels' => $labels,
        'settings' => $merged_settings,
    ];
}

/**
 * Pull name/singular/plural from $args, accepting both short and ACPT-native
 * aliases (post_name, singular_label, plural_label). Returns WP_Error when
 * any of the three is missing or not a string.
 *
 * @param array<string, mixed> $args
 * @return array{name: string, singular: string, plural: string}|WP_Error
 */
function acpt_extract_cpt_identity(array $args): array|WP_Error
{
    /** @var mixed $name_raw */
    $name_raw = $args['name'] ?? $args['post_name'] ?? null;
    /** @var mixed $singular_raw */
    $singular_raw = $args['singular'] ?? $args['singular_label'] ?? null;
    /** @var mixed $plural_raw */
    $plural_raw = $args['plural'] ?? $args['plural_label'] ?? null;

    if (!is_string($name_raw) || !is_string($singular_raw) || !is_string($plural_raw)) {
        return new WP_Error('acpt_invalid_input', __(
            'name (or post_name), singular (or singular_label), and plural (or plural_label) are required.',
            domain: 'novamira-pro',
        ));
    }
    return [
        'name' => trim($name_raw),
        'singular' => trim($singular_raw),
        'plural' => trim($plural_raw),
    ];
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_create_post_type(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $ident = acpt_extract_cpt_identity($args);
    if ($ident instanceof WP_Error) {
        return $ident;
    }
    $name = $ident['name'];
    $singular = $ident['singular'];
    $plural = $ident['plural'];

    $error = acpt_validate_new_cpt_name($name);
    if ($error !== null) {
        return $error;
    }

    if ($singular === '' || $plural === '') {
        return new WP_Error('acpt_invalid_input', __('singular and plural are required.', domain: 'novamira-pro'));
    }

    /** @var mixed $supports_raw */
    $supports_raw = $args['supports'] ?? ['title', 'editor'];
    $supports = is_array($supports_raw)
        ? array_values(array_filter($supports_raw, callback: 'is_string'))
        : ['title', 'editor'];

    /** @var mixed $labels_raw */
    $labels_raw = $args['labels'] ?? [];
    /** @var array<string, mixed> $labels */
    $labels = is_array($labels_raw) ? $labels_raw : [];

    /** @var mixed $settings_raw */
    $settings_raw = $args['settings'] ?? [];
    // ACPT generates per-CPT capabilities (edit_<cpt_name>s, etc.) unless
    // `capability_type` is something other than the magic 'post' string.
    // See CustomPostTypeModel::capabilityType: passing 'post' is silently
    // overridden with the CPT name, so admins 403 on the edit screen.
    // Default to 'page' so existing roles inherit page caps (edit_pages,
    // publish_pages — admins/editors hold these). Agent can override.
    /** @var array<string, mixed> $settings */
    $settings = array_merge(
        ['public' => true, 'capability_type' => 'page'],
        is_array($settings_raw) ? $settings_raw : [],
    );

    /** @var mixed $icon_raw */
    $icon_raw = $args['icon'] ?? 'dashicons-admin-post';
    $icon = is_string($icon_raw) ? $icon_raw : 'dashicons-admin-post';

    $cpt_data = [
        'name' => $name,
        'singular' => $singular,
        'plural' => $plural,
        'icon' => $icon,
        'supports' => $supports,
        'labels' => $labels,
        'settings' => $settings,
    ];

    $result = acpt_handle_api_result(static fn() => acpt_save_new_cpt($cpt_data));

    if (!$result['success']) {
        return new WP_Error(
            'acpt_create_post_type_failed',
            $result['error'] ?? __('Failed to create CPT.', domain: 'novamira-pro'),
        );
    }

    $id = \ACPT\Core\Repository\CustomPostTypeRepository::getId($name);

    return [
        'success' => true,
        'id' => (string) $id,
        'name' => $name,
        'singular' => $singular,
        'plural' => $plural,
    ];
}

/**
 * @param array{name: string, singular?: string, plural?: string, icon?: string, supports?: list<string>, labels?: array<string, mixed>, settings?: array<string, mixed>} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_edit_post_type(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = trim($args['name']);
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
    $fields = acpt_resolve_cpt_edit_fields($args, $model);

    $result = acpt_handle_api_result(static function () use ($model, $fields): void {
        $updated = \ACPT\Core\Models\CustomPostType\CustomPostTypeModel::hydrateFromArray([
            'id' => $model->getId(),
            'name' => $model->getName(),
            'singular' => $fields['singular'],
            'plural' => $fields['plural'],
            'icon' => $fields['icon'],
            'native' => $model->isNative(),
            'supports' => $fields['supports'],
            'labels' => $fields['labels'],
            'settings' => $fields['settings'],
        ]);
        \ACPT\Core\Repository\CustomPostTypeRepository::save($updated);
    });

    if (!$result['success']) {
        return new WP_Error(
            'acpt_edit_post_type_failed',
            $result['error'] ?? __('Failed to edit CPT.', domain: 'novamira-pro'),
        );
    }

    return ['success' => true, 'name' => $name];
}

/**
 * @param array{name: string, delete_posts?: bool} $args
 * @return array{deleted: bool, name: string, reason?: string}|WP_Error
 */
function acpt_delete_post_type(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = trim($args['name']);
    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    if (!\ACPT\Core\Repository\CustomPostTypeRepository::exists($name)) {
        return ['deleted' => false, 'name' => $name, 'reason' => 'not_found'];
    }

    $delete_posts = filter_var($args['delete_posts'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $result = acpt_handle_api_result(
        static fn() => \ACPT\Core\Repository\CustomPostTypeRepository::delete($name, $delete_posts),
    );

    if (!$result['success']) {
        return new WP_Error(
            'acpt_delete_post_type_failed',
            $result['error'] ?? __('Failed to delete CPT.', domain: 'novamira-pro'),
        );
    }

    return ['deleted' => true, 'name' => $name];
}

/**
 * Thin shim around ACPT's Uuid::v4() for use inside try blocks where we
 * cannot call static methods in closures without triggering analysis errors.
 */
function acpt_uuid_v4(): string
{
    try {
        return \ACPT\Core\Helper\Uuid::v4();
    } catch (Throwable) {
        return (string) wp_generate_uuid4();
    }
}
