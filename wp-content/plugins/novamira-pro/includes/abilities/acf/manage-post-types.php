<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-create-post-type', [
    'label' => __('Create ACF Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACF-managed post type from a payload in ACF native format. Top-level shape mirrors register_post_type() arguments plus ACF-specific keys (advanced_configuration, active). Required minima: post_type (alias slug) and title (or labels.singular_name). Idempotent by key.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'description' => 'ACF post-type native payload. Top-level shape: { key?, post_type | slug, title, labels?, supports?, taxonomies?, public?, hierarchical?, has_archive?, rewrite?, show_in_rest?, menu_position?, menu_icon?, advanced_configuration?, active?, ... any other register_post_type() argument }.',
        'properties' => [
            'key' => ['type' => 'string'],
            'post_type' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'supports' => ['type' => 'array', 'items' => ['type' => 'string']],
            'taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'has_archive' => ['type' => ['boolean', 'string']],
            'show_in_rest' => ['type' => 'boolean'],
            'menu_position' => ['type' => 'integer'],
            'menu_icon' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'source' => ['type' => 'string'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => ['key', 'slug', 'title', 'active', 'source', 'public', 'hierarchical', 'warnings', 'payload'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_create_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to register a new ACF-managed post type. Pass either post_type or slug (they are aliases). Reserved WP slugs (post, page, attachment, revision, etc.) are rejected. Slug collisions with other ACF-managed post types are rejected; collisions with externally-registered post types are reported as warnings and the create proceeds.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-edit-post-type', [
    'label' => __('Edit ACF Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACF-managed post type in place using ACF native format. Requires key (or slug, resolved by name). Slug change while content exists attaches a slug_change_orphans_content warning to the response — the edit proceeds, content migration is the agent\'s responsibility.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'post_type' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'supports' => ['type' => 'array', 'items' => ['type' => 'string']],
            'taxonomies' => ['type' => 'array', 'items' => ['type' => 'string']],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'has_archive' => ['type' => ['boolean', 'string']],
            'show_in_rest' => ['type' => 'boolean'],
            'menu_position' => ['type' => 'integer'],
            'menu_icon' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
        ],
        'required' => ['key'],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'source' => ['type' => 'string'],
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => ['key', 'slug', 'title', 'active', 'source', 'public', 'hierarchical', 'warnings', 'payload'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_edit_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to modify an existing ACF-managed post type. Renaming the slug does NOT migrate existing posts — they keep the old post_type and become invisible. The response surfaces this risk via warnings[].',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-delete-post-type', [
    'label' => __('Delete ACF Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an ACF-managed post type. Refuses to delete when posts of this type exist unless force=true is passed. The deletion does not touch wp_posts; orphaned posts remain in the database with a post_type that is no longer registered.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'ACF key (post_type_<slug>) or bare slug.',
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, deletes even if posts of this type exist. Default false.',
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'key' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
            'orphaned_posts_count' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'key'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_delete_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to permanently delete an ACF-managed post type. Pass force=true only when you understand existing posts will be orphaned (their post_type value remains in the DB but no longer registered). Idempotent: deleting a non-existent key returns deleted=false with reason=not_found.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acf_create_post_type(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_create_input($args, kind: 'post-type');
    if ($required instanceof WP_Error) {
        return $required;
    }

    $slug = acf_pt_extract_slug($args, kind: 'post-type');
    if ($slug === null) {
        return new WP_Error('invalid_input', 'post_type (alias: slug) is required on create');
    }

    // Idempotency check: if a record already exists at the resolved key, return
    // it unchanged so callers can re-run the same payload without error.
    $constants = acf_internal_post_type_constants(kind: 'post-type');
    /** @var mixed $supplied_key */
    $supplied_key = $args['key'] ?? null;
    $candidate_key = is_string($supplied_key) && $supplied_key !== ''
        ? $supplied_key
        : $constants['key_prefix'] . $slug;
    $existing = acf_get_internal_post_type($candidate_key, post_type: $constants['post_type']);
    if (is_array($existing)) {
        $output = acf_internal_post_type_to_output_full($existing, kind: 'post-type');
        $output['warnings'] = [];
        return $output;
    }

    /** @var list<array<string, mixed>> $warnings */
    $warnings = [];
    $slug_check = acf_pt_validate_slug_pipeline($slug, kind: 'post-type', own_key: null, warnings: $warnings);
    if ($slug_check instanceof WP_Error) {
        return $slug_check;
    }

    $payload = acf_pt_normalize_payload($args, $slug, kind: 'post-type', existing_key: null);

    $saved = acf_update_internal_post_type($payload, post_type_name: 'acf-post-type');

    $output = acf_internal_post_type_to_output_full($saved, kind: 'post-type');
    $output['warnings'] = $warnings;
    return $output;
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acf_edit_post_type(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_edit_input($args);
    if ($required instanceof WP_Error) {
        return $required;
    }

    /** @var string $raw */
    $raw = $args['key'];

    $lookup = acf_pt_resolve_existing_record($raw, kind: 'post-type');
    if ($lookup instanceof WP_Error) {
        return $lookup;
    }
    $resolved = $lookup['resolved'];
    $existing = $lookup['existing'];

    /** @var mixed $existing_slug_raw */
    $existing_slug_raw = $existing['post_type'] ?? null;
    $existing_slug = is_string($existing_slug_raw) ? $existing_slug_raw : '';

    $new_slug = acf_pt_extract_slug($args, kind: 'post-type') ?? $existing_slug;

    /** @var list<array<string, mixed>> $warnings */
    $warnings = [];
    $slug_check = acf_pt_validate_slug_pipeline($new_slug, kind: 'post-type', own_key: $resolved, warnings: $warnings);
    if ($slug_check instanceof WP_Error) {
        return $slug_check;
    }

    $slug_change = acf_internal_post_type_slug_change_warning($existing_slug, $new_slug, kind: 'post-type');
    if ($slug_change !== null) {
        $warnings[] = $slug_change;
    }

    $payload = acf_pt_normalize_payload($args, $new_slug, kind: 'post-type', existing_key: $resolved);
    $payload = acf_pt_merge_with_existing($payload, $existing);

    $saved = acf_update_internal_post_type($payload, post_type_name: 'acf-post-type');

    $output = acf_internal_post_type_to_output_full($saved, kind: 'post-type');
    $output['warnings'] = $warnings;
    return $output;
}

/**
 * @param array{key?: string, force?: bool} $args
 * @return array<string, mixed>|WP_Error
 */
function acf_delete_post_type(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_edit_input($args);
    if ($required instanceof WP_Error) {
        return $required;
    }

    $raw = $args['key'] ?? '';
    $force = filter_var($args['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $lookup = acf_pt_resolve_existing_record($raw, kind: 'post-type');
    if ($lookup instanceof WP_Error) {
        if ($lookup->get_error_code() === 'not_found') {
            return ['deleted' => false, 'key' => $raw, 'reason' => 'not_found'];
        }
        return $lookup;
    }
    $resolved = $lookup['resolved'];
    $existing = $lookup['existing'];

    /** @var mixed $slug_raw */
    $slug_raw = $existing['post_type'] ?? '';
    $slug = is_string($slug_raw) ? $slug_raw : '';

    $count = acf_internal_post_type_count_content($slug, kind: 'post-type');
    if ($count > 0 && !$force) {
        return new WP_Error(
            'delete_blocked_by_content',
            "{$count} posts use post type '{$slug}'; pass force=true to delete anyway",
            ['affected_count' => $count, 'slug' => $slug, 'hint' => 'pass force: true to delete anyway'],
        );
    }

    $deleted = acf_delete_internal_post_type($resolved, post_type_name: 'acf-post-type');
    if (!$deleted) {
        return new WP_Error('acf_delete_failed', "acf_delete_internal_post_type failed for key {$resolved}", [
            'key' => $resolved,
        ]);
    }

    $out = ['deleted' => true, 'key' => $resolved, 'slug' => $slug];
    if ($count > 0) {
        $out['orphaned_posts_count'] = $count;
    }
    return $out;
}
