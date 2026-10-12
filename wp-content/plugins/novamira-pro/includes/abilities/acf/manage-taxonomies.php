<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-create-taxonomy', [
    'label' => __('Create ACF Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACF-managed taxonomy from a payload in ACF native format. Top-level shape mirrors register_taxonomy() arguments plus ACF-specific keys (advanced_configuration, active). Required minima: taxonomy (alias slug) and title (or labels.singular_name). Idempotent by key.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'description' => 'ACF taxonomy native payload. Top-level shape: { key?, taxonomy | slug, title, labels?, object_type[], hierarchical?, public?, show_in_rest?, show_admin_column?, rewrite?, active?, ... any other register_taxonomy() argument }.',
        'properties' => [
            'key' => ['type' => 'string'],
            'taxonomy' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
            'hierarchical' => ['type' => 'boolean'],
            'public' => ['type' => 'boolean'],
            'show_in_rest' => ['type' => 'boolean'],
            'show_admin_column' => ['type' => 'boolean'],
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
            'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => ['key', 'slug', 'title', 'active', 'source', 'object_type', 'warnings', 'payload'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_create_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to register a new ACF-managed taxonomy. Pass either taxonomy or slug (they are aliases). object_type[] is the list of post type slugs this taxonomy attaches to (e.g. ["post"], or a custom post type slug). Reserved WP slugs (category, post_tag, etc.) are rejected. Slug collisions with other ACF-managed taxonomies are rejected; collisions with externally-registered taxonomies are reported as warnings.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-edit-taxonomy', [
    'label' => __('Edit ACF Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACF-managed taxonomy in place using ACF native format. Requires key (or slug, resolved by name). Slug change while terms exist attaches a slug_change_orphans_content warning to the response — the edit proceeds.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'taxonomy' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
            'hierarchical' => ['type' => 'boolean'],
            'public' => ['type' => 'boolean'],
            'show_in_rest' => ['type' => 'boolean'],
            'show_admin_column' => ['type' => 'boolean'],
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
            'object_type' => ['type' => 'array', 'items' => ['type' => 'string']],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => ['key', 'slug', 'title', 'active', 'source', 'object_type', 'warnings', 'payload'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_edit_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to modify an existing ACF-managed taxonomy. Renaming the slug does NOT migrate existing terms — they keep the old taxonomy and become unreachable. The response surfaces this risk via warnings[].',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-delete-taxonomy', [
    'label' => __('Delete ACF Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an ACF-managed taxonomy. Refuses to delete when terms exist unless force=true. The deletion does not touch wp_term_taxonomy; orphaned terms remain in the database with a taxonomy that is no longer registered.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'ACF key (taxonomy_<slug>) or bare slug.',
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'When true, deletes even if terms exist. Default false.',
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
            'orphaned_terms_count' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'key'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_delete_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to permanently delete an ACF-managed taxonomy. Pass force=true only when you understand existing terms will be orphaned. Idempotent: deleting a non-existent key returns deleted=false with reason=not_found.',
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
function acf_create_taxonomy(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_create_input($args, kind: 'taxonomy');
    if ($required instanceof WP_Error) {
        return $required;
    }

    $slug = acf_pt_extract_slug($args, kind: 'taxonomy');
    if ($slug === null) {
        return new WP_Error('invalid_input', 'taxonomy (alias: slug) is required on create');
    }

    // Idempotency check: if a record already exists at the resolved key, return
    // it unchanged so callers can re-run the same payload without error.
    $constants = acf_internal_post_type_constants(kind: 'taxonomy');
    /** @var mixed $supplied_key */
    $supplied_key = $args['key'] ?? null;
    $candidate_key = is_string($supplied_key) && $supplied_key !== ''
        ? $supplied_key
        : $constants['key_prefix'] . $slug;
    $existing = acf_get_internal_post_type($candidate_key, post_type: $constants['post_type']);
    if (is_array($existing)) {
        $output = acf_internal_post_type_to_output_full($existing, kind: 'taxonomy');
        $output['warnings'] = [];
        return $output;
    }

    /** @var list<array<string, mixed>> $warnings */
    $warnings = [];
    $slug_check = acf_pt_validate_slug_pipeline($slug, kind: 'taxonomy', own_key: null, warnings: $warnings);
    if ($slug_check instanceof WP_Error) {
        return $slug_check;
    }

    $payload = acf_pt_normalize_payload($args, $slug, kind: 'taxonomy', existing_key: null);

    $saved = acf_update_internal_post_type($payload, post_type_name: 'acf-taxonomy');

    $output = acf_internal_post_type_to_output_full($saved, kind: 'taxonomy');
    $output['warnings'] = $warnings;
    return $output;
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acf_edit_taxonomy(array $args): array|WP_Error
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

    $lookup = acf_pt_resolve_existing_record($raw, kind: 'taxonomy');
    if ($lookup instanceof WP_Error) {
        return $lookup;
    }
    $resolved = $lookup['resolved'];
    $existing = $lookup['existing'];

    /** @var mixed $existing_slug_raw */
    $existing_slug_raw = $existing['taxonomy'] ?? null;
    $existing_slug = is_string($existing_slug_raw) ? $existing_slug_raw : '';

    $new_slug = acf_pt_extract_slug($args, kind: 'taxonomy') ?? $existing_slug;

    /** @var list<array<string, mixed>> $warnings */
    $warnings = [];
    $slug_check = acf_pt_validate_slug_pipeline($new_slug, kind: 'taxonomy', own_key: $resolved, warnings: $warnings);
    if ($slug_check instanceof WP_Error) {
        return $slug_check;
    }

    $slug_change = acf_internal_post_type_slug_change_warning($existing_slug, $new_slug, kind: 'taxonomy');
    if ($slug_change !== null) {
        $warnings[] = $slug_change;
    }

    $payload = acf_pt_normalize_payload($args, $new_slug, kind: 'taxonomy', existing_key: $resolved);
    $payload = acf_pt_merge_with_existing($payload, $existing);

    $saved = acf_update_internal_post_type($payload, post_type_name: 'acf-taxonomy');

    $output = acf_internal_post_type_to_output_full($saved, kind: 'taxonomy');
    $output['warnings'] = $warnings;
    return $output;
}

/**
 * @param array{key?: string, force?: bool} $args
 * @return array<string, mixed>|WP_Error
 */
function acf_delete_taxonomy(array $args): array|WP_Error
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

    $lookup = acf_pt_resolve_existing_record($raw, kind: 'taxonomy');
    if ($lookup instanceof WP_Error) {
        if ($lookup->get_error_code() === 'not_found') {
            return ['deleted' => false, 'key' => $raw, 'reason' => 'not_found'];
        }
        return $lookup;
    }
    $resolved = $lookup['resolved'];
    $existing = $lookup['existing'];

    /** @var mixed $slug_raw */
    $slug_raw = $existing['taxonomy'] ?? '';
    $slug = is_string($slug_raw) ? $slug_raw : '';

    $count = acf_internal_post_type_count_content($slug, kind: 'taxonomy');
    if ($count > 0 && !$force) {
        return new WP_Error(
            'delete_blocked_by_content',
            "{$count} terms use taxonomy '{$slug}'; pass force=true to delete anyway",
            ['affected_count' => $count, 'slug' => $slug, 'hint' => 'pass force: true to delete anyway'],
        );
    }

    $deleted = acf_delete_internal_post_type($resolved, post_type_name: 'acf-taxonomy');
    if (!$deleted) {
        return new WP_Error('acf_delete_failed', "acf_delete_internal_post_type failed for key {$resolved}", [
            'key' => $resolved,
        ]);
    }

    $out = ['deleted' => true, 'key' => $resolved, 'slug' => $slug];
    if ($count > 0) {
        $out['orphaned_terms_count'] = $count;
    }
    return $out;
}
