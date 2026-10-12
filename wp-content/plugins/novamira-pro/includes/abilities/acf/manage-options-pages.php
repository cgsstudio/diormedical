<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-create-options-page', [
    'label' => __('Create ACF Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACF-managed options page from a payload in ACF native format. Top-level shape mirrors acf_add_options_page() arguments. Required minima: menu_slug (alias slug) and page_title (alias title or menu_title). Idempotent by key. Field values stored on the page are then accessed via acf-read-values / acf-write-values with target.type=options and target.id=<post_id> — note: <post_id> defaults to "options" (the shared default ACF options store) unless you pass an explicit post_id on the options-page payload. The menu_slug and post_id are different concepts — passing menu_slug as target.id will read/write to the wrong location.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'description' => 'ACF options-page native payload. Top-level shape: { key?, menu_slug | slug, page_title | title, menu_title?, parent_slug?, capability?, position?, icon_url?, redirect?, post_id?, autoload?, update_button?, updated_message?, description?, advanced_configuration?, active? }.',
        'properties' => [
            'key' => ['type' => 'string'],
            'menu_slug' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'page_title' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'parent_slug' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'position' => ['type' => ['string', 'integer', 'null']],
            'icon_url' => ['type' => 'string'],
            'redirect' => ['type' => 'boolean'],
            'post_id' => ['type' => 'string'],
            'autoload' => ['type' => 'boolean'],
            'update_button' => ['type' => 'string'],
            'updated_message' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'advanced_configuration' => ['type' => 'boolean'],
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
            'menu_title' => ['type' => 'string'],
            'parent_slug' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => [
            'key',
            'slug',
            'title',
            'active',
            'source',
            'menu_title',
            'parent_slug',
            'capability',
            'warnings',
            'payload',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_create_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to register a new ACF-managed options page. Pass either menu_slug or slug (they are aliases) and either page_title, title, or menu_title (also aliases). parent_slug is empty for top-level menu items; pass an existing admin menu slug (e.g. "options-general.php", "themes.php", or another options page menu_slug) to nest as a sub-page. capability defaults to ACF\'s edit_posts. post_id (default "options") controls where field values are stored in wp_options — leaving the default means values are shared with other pages that also use "options".',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-edit-options-page', [
    'label' => __('Edit ACF Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACF-managed options page in place using ACF native format. Requires key (or menu_slug, resolved by name). Renaming menu_slug only changes the admin URL; field values stored under the page\'s post_id continue to resolve as long as post_id is preserved.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'menu_slug' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'page_title' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'parent_slug' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'position' => ['type' => ['string', 'integer', 'null']],
            'icon_url' => ['type' => 'string'],
            'redirect' => ['type' => 'boolean'],
            'post_id' => ['type' => 'string'],
            'autoload' => ['type' => 'boolean'],
            'update_button' => ['type' => 'string'],
            'updated_message' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'advanced_configuration' => ['type' => 'boolean'],
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
            'menu_title' => ['type' => 'string'],
            'parent_slug' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => [
            'key',
            'slug',
            'title',
            'active',
            'source',
            'menu_title',
            'parent_slug',
            'capability',
            'warnings',
            'payload',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_edit_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to modify an existing ACF-managed options page. Changing post_id detaches the page from previously stored values (they remain in wp_options under the old post_id). Changing menu_slug only changes the admin URL; field values follow post_id, not menu_slug.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-delete-options-page', [
    'label' => __('Delete ACF Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an ACF-managed options page. The deletion does not touch wp_options; field values previously stored under the page\'s post_id remain in the database but are no longer reachable through the ACF UI.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'ACF key (ui_options_page_<menu_slug>) or bare menu_slug.',
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
        ],
        'required' => ['deleted', 'key'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_delete_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to permanently delete an ACF-managed options page. Idempotent: deleting a non-existent key returns deleted=false with reason=not_found. Stored field values in wp_options are NOT removed and remain reachable via direct get_option() calls until cleaned up manually.',
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
function acf_create_options_page(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard(kind: 'options-page');
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_create_input($args, kind: 'options-page');
    if ($required instanceof WP_Error) {
        return $required;
    }

    $slug = acf_pt_extract_slug($args, kind: 'options-page');
    if ($slug === null) {
        return new WP_Error('invalid_input', 'menu_slug (alias: slug) is required on create');
    }

    $constants = acf_internal_post_type_constants(kind: 'options-page');
    /** @var mixed $supplied_key */
    $supplied_key = $args['key'] ?? null;
    $candidate_key = is_string($supplied_key) && $supplied_key !== ''
        ? $supplied_key
        : $constants['key_prefix'] . $slug;
    $existing = acf_get_internal_post_type($candidate_key, post_type: $constants['post_type']);
    if (is_array($existing)) {
        $output = acf_internal_post_type_to_output_full($existing, kind: 'options-page');
        $output['warnings'] = [];
        return $output;
    }

    /** @var list<array<string, mixed>> $warnings */
    $warnings = [];
    $slug_check = acf_pt_validate_slug_pipeline($slug, kind: 'options-page', own_key: null, warnings: $warnings);
    if ($slug_check instanceof WP_Error) {
        return $slug_check;
    }

    $payload = acf_pt_normalize_payload($args, $slug, kind: 'options-page', existing_key: null);

    $saved = acf_update_internal_post_type($payload, post_type_name: 'acf-ui-options-page');

    $output = acf_internal_post_type_to_output_full($saved, kind: 'options-page');
    $output['warnings'] = $warnings;
    return $output;
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acf_edit_options_page(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard(kind: 'options-page');
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_edit_input($args);
    if ($required instanceof WP_Error) {
        return $required;
    }

    /** @var string $raw */
    $raw = $args['key'];

    $lookup = acf_pt_resolve_existing_record($raw, kind: 'options-page');
    if ($lookup instanceof WP_Error) {
        return $lookup;
    }
    $resolved = $lookup['resolved'];
    $existing = $lookup['existing'];

    /** @var mixed $existing_slug_raw */
    $existing_slug_raw = $existing['menu_slug'] ?? null;
    $existing_slug = is_string($existing_slug_raw) ? $existing_slug_raw : '';

    $new_slug = acf_pt_extract_slug($args, kind: 'options-page') ?? $existing_slug;

    /** @var list<array<string, mixed>> $warnings */
    $warnings = [];
    $slug_check = acf_pt_validate_slug_pipeline(
        $new_slug,
        kind: 'options-page',
        own_key: $resolved,
        warnings: $warnings,
    );
    if ($slug_check instanceof WP_Error) {
        return $slug_check;
    }

    $payload = acf_pt_normalize_payload($args, $new_slug, kind: 'options-page', existing_key: $resolved);
    $payload = acf_pt_merge_with_existing($payload, $existing);

    $saved = acf_update_internal_post_type($payload, post_type_name: 'acf-ui-options-page');

    $output = acf_internal_post_type_to_output_full($saved, kind: 'options-page');
    $output['warnings'] = $warnings;
    return $output;
}

/**
 * @param array{key?: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acf_delete_options_page(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard(kind: 'options-page');
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $required = acf_pt_validate_edit_input($args);
    if ($required instanceof WP_Error) {
        return $required;
    }

    $raw = $args['key'] ?? '';

    $lookup = acf_pt_resolve_existing_record($raw, kind: 'options-page');
    if ($lookup instanceof WP_Error) {
        if ($lookup->get_error_code() === 'not_found') {
            return ['deleted' => false, 'key' => $raw, 'reason' => 'not_found'];
        }
        return $lookup;
    }
    $resolved = $lookup['resolved'];
    $existing = $lookup['existing'];

    /** @var mixed $slug_raw */
    $slug_raw = $existing['menu_slug'] ?? '';
    $slug = is_string($slug_raw) ? $slug_raw : '';

    $deleted = acf_delete_internal_post_type($resolved, post_type_name: 'acf-ui-options-page');
    if (!$deleted) {
        return new WP_Error('acf_delete_failed', "acf_delete_internal_post_type failed for key {$resolved}", [
            'key' => $resolved,
        ]);
    }

    return ['deleted' => true, 'key' => $resolved, 'slug' => $slug];
}
