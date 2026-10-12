<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ----- create -----

wp_register_ability('novamira/jetengine-create-meta-box', [
    'label' => __('Create JetEngine Meta Box', domain: 'novamira-pro'),
    'description' => __(
        'Create a new JetEngine meta-box record. Accepts the same shape returned by get-meta-box (id, name, args, meta_fields). Idempotent: passing the same id with an identical payload returns the existing record without side effects. A conflicting id returns meta_box_id_exists.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Meta box id (slug-like, e.g. "meta-1" or "ui-mb"). Use only lowercase letters, digits, and hyphens: JetEngine\'s admin URL routing rejects underscores, so an id like "my_box" persists correctly but can only be edited via this ability, not through the JetEngine admin UI.',
            ],
            'name' => ['type' => 'string', 'description' => 'Display name; stored as args.name.'],
            'args' => [
                'type' => 'object',
                'description' => 'Meta-box configuration (object_type, allowed_post_type, position, context, etc.). The name key inside args is overridden by the top-level name field.',
            ],
            'meta_fields' => ['type' => 'array', 'description' => 'Field definitions for this meta box.'],
        ],
        'required' => ['id', 'name', 'args', 'meta_fields'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'args' => ['type' => 'object'],
            'meta_fields' => ['type' => 'array'],
            'created' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'name', 'args', 'meta_fields', 'created'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_create_meta_box',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ----- edit -----

wp_register_ability('novamira/jetengine-edit-meta-box', [
    'label' => __('Edit JetEngine Meta Box', domain: 'novamira-pro'),
    'description' => __(
        'Update an existing JetEngine meta-box record. Top-level keys present in the payload are merged onto the persisted record; arrays (meta_fields, args.allowed_post_type, args.visibility_conditions) replace atomically when provided; the args object merges per-key (provide args.name to rename). PHP-registered records refuse with meta_box_not_editable.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Meta box id to edit.'],
            'name' => ['type' => 'string', 'description' => 'New display name; updates args.name.'],
            'args' => [
                'type' => 'object',
                'description' => 'Partial args object — keys provided replace the stored keys; omitted keys are left unchanged.',
            ],
            'meta_fields' => [
                'type' => 'array',
                'description' => 'When provided, replaces the full meta_fields array atomically.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'args' => ['type' => 'object'],
            'meta_fields' => ['type' => 'array'],
        ],
        'required' => ['id', 'name', 'args', 'meta_fields'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_edit_meta_box',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ----- delete -----

wp_register_ability('novamira/jetengine-delete-meta-box', [
    'label' => __('Delete JetEngine Meta Box', domain: 'novamira-pro'),
    'description' => __(
        'Permanently remove a JetEngine meta-box record by id. PHP-registered records refuse with meta_box_not_editable; edit the registering source file via novamira/edit-file instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Meta box id to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'id'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_delete_meta_box',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ----- callbacks -----

/**
 * Build the raw DB payload from the normalised API fields.
 *
 * JetEngine stores the display name inside args.name, not as a top-level key.
 * The API exposes it as a convenience top-level field; this helper maps it back.
 *
 * @param string $id
 * @param string $name
 * @param array<string, mixed> $args
 * @param list<array<string, mixed>> $meta_fields
 * @return array{id: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>}
 */
function mb_build_db_payload(string $id, string $name, array $args, array $meta_fields): array
{
    $args['name'] = $name;

    return [
        'id' => $id,
        'args' => $args,
        'meta_fields' => array_values($meta_fields),
    ];
}

/**
 * @param array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>} $input
 * @return array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>, created: bool}|WP_Error
 */
function jetengine_create_meta_box(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $input['id'];
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $existing_source = jetengine_meta_box_source($id);

    if ($existing_source === 'php') {
        return new WP_Error('meta_box_not_editable', __(
            'Meta box is registered via PHP and cannot be created or modified at runtime.',
            domain: 'novamira-pro',
        ));
    }

    if ($existing_source === 'db') {
        return mb_handle_create_idempotency($id, $input);
    }

    $data = jet_engine()->meta_boxes->data ?? null;
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine meta-boxes data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $args = $input['args'];
    $meta_fields = $input['meta_fields'];
    $name = $input['name'];
    $payload = mb_build_db_payload($id, $name, $args, $meta_fields);

    $data->before_item_update($payload);
    $written = $data->update_item_in_db($payload);
    if ($written === '') {
        return new WP_Error('jetengine_write_failed', __('Failed to persist the meta box.', domain: 'novamira-pro'));
    }

    jetengine_invalidate_raw_cache($data);

    return [
        'id' => $id,
        'name' => $name,
        'args' => $payload['args'],
        'meta_fields' => $payload['meta_fields'],
        'created' => true,
    ];
}

/**
 * Handle the idempotency check when a DB record with the same id already exists.
 *
 * @param array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>} $input
 * @return array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>, created: bool}|WP_Error
 */
function mb_handle_create_idempotency(string $id, array $input): array|WP_Error
{
    $existing = jetengine_get_meta_box(['id' => $id]);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    $args = $input['args'];
    $meta_fields = $input['meta_fields'];
    $name = $input['name'];

    $candidate_args = $args;
    $candidate_args['name'] = $name;

    $candidate = [
        'args' => $candidate_args,
        'meta_fields' => array_values($meta_fields),
    ];

    $existing_payload = [
        'args' => $existing['args'],
        'meta_fields' => $existing['meta_fields'],
    ];

    if ($candidate === $existing_payload) {
        return [
            'id' => $id,
            'name' => $existing['name'],
            'args' => $existing['args'],
            'meta_fields' => $existing['meta_fields'],
            'created' => false,
        ];
    }

    return new WP_Error(
        'meta_box_id_exists',
        __('A meta box with this id already exists. Use jetengine-edit-meta-box to modify it.', domain: 'novamira-pro'),
        ['id' => $id],
    );
}

/**
 * @param array{id: string, name?: string, args?: array<string, mixed>, meta_fields?: list<array<string, mixed>>} $input
 * @return array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>}|WP_Error
 */
function jetengine_edit_meta_box(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $input['id'];
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $source = jetengine_meta_box_source($id);
    if ($source === null) {
        return new WP_Error('meta_box_not_found', __('Meta box not found.', domain: 'novamira-pro'));
    }

    if ($source === 'php') {
        return new WP_Error('meta_box_not_editable', __(
            'Meta box is registered via PHP. Edit the source file via novamira/edit-file.',
            domain: 'novamira-pro',
        ));
    }

    $existing = jetengine_get_meta_box(['id' => $id]);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    $data = jet_engine()->meta_boxes->data ?? null;
    if ($data === null) {
        return new WP_Error('jetengine_unavailable', __(
            'JetEngine meta-boxes data layer is unavailable.',
            domain: 'novamira-pro',
        ));
    }

    $merged_args = mb_merge_edit_args($input, $existing);
    $merged_fields = array_key_exists('meta_fields', $input)
        ? array_values($input['meta_fields'])
        : $existing['meta_fields'];

    $payload = [
        'id' => $id,
        'args' => $merged_args,
        'meta_fields' => $merged_fields,
    ];

    $data->before_item_update($payload);
    $written = $data->update_item_in_db($payload);
    if ($written === '') {
        return new WP_Error('jetengine_write_failed', __('Failed to persist the meta box.', domain: 'novamira-pro'));
    }

    jetengine_invalidate_raw_cache($data);

    return [
        'id' => $id,
        'name' => (string) ($merged_args['name'] ?? $id),
        'args' => $merged_args,
        'meta_fields' => $merged_fields,
    ];
}

/**
 * Merge the args for an edit operation:
 * - If top-level `name` is provided, set args.name.
 * - If `args` is provided, merge (replace-on-key) into existing args.
 *
 * @param array{id: string, name?: string, args?: array<string, mixed>, meta_fields?: list<array<string, mixed>>} $input
 * @param array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>, editable: bool, source: 'db'|'php'} $existing
 * @return array<string, mixed>
 */
function mb_merge_edit_args(array $input, array $existing): array
{
    $merged_args = $existing['args'];

    if (array_key_exists('args', $input)) {
        $input_args = $input['args'];
        $merged_args = array_replace($merged_args, $input_args);
    }

    if (array_key_exists('name', $input)) {
        $merged_args['name'] = $input['name'];
    }

    return $merged_args;
}

/**
 * @param array{id: string} $input
 * @return array{deleted: bool, id: string}|WP_Error
 */
function jetengine_delete_meta_box(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $input['id'];
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $source = jetengine_meta_box_source($id);
    if ($source === null) {
        return new WP_Error('meta_box_not_found', __('Meta box not found.', domain: 'novamira-pro'));
    }

    if ($source === 'php') {
        return new WP_Error('meta_box_not_editable', __(
            'Meta box is registered via PHP. Edit the source file via novamira/edit-file.',
            domain: 'novamira-pro',
        ));
    }

    /** @var mixed $raw_option */
    $raw_option = get_option('jet_engine_meta_boxes', []);
    /** @var array<string, mixed> $option */
    $option = is_array($raw_option) ? $raw_option : [];

    unset($option[$id]);

    update_option('jet_engine_meta_boxes', $option);

    jetengine_invalidate_raw_cache(jet_engine()->meta_boxes->data ?? null);

    return ['deleted' => true, 'id' => $id];
}
