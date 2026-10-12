<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Abilities: manage Etch loops (the etch_loops option — the data sources an
 * etch/loop node repeats over). A loop is { id, name, key, global, config },
 * where config is { type (e.g. "wp-query"), args } and global:true marks an
 * Etch built-in preset. CRUD is per-entity on the shared option, guarded by the
 * etch_option_lock so concurrent writers don't clobber each other; built-in
 * preset fields (e.g. _preset_hash) are preserved on edit.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The loops option as a normalized map { id => loop }.
 *
 * @return array<array-key, mixed>
 */
function etch_loops_option(): array
{
    return etch_option_array('etch_loops');
}

/**
 * Resolve a loop id within the given map, or a WP_Error.
 *
 * @param array<array-key, mixed> $loops
 * @return array<array-key, mixed>|WP_Error
 */
function etch_loop_or_error(array $loops, string $id): array|WP_Error
{
    if (!isset($loops[$id])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: loop ID. */
            __('Loop "%s" was not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    /** @var mixed $loop */
    $loop = $loops[$id];

    return is_array($loop) ? $loop : [];
}

/**
 * A non-empty loop_id string from input, or a WP_Error.
 *
 * @return string|WP_Error
 */
function etch_loop_id_or_error(mixed $id): string|WP_Error
{
    if (!is_string($id) || $id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "loop_id" is required.', domain: 'novamira-pro'));
    }

    return $id;
}

/**
 * Validate a loop `config` object (the query definition) — an array within the
 * depth/size caps.
 *
 * @return array<array-key, mixed>|WP_Error
 */
function etch_loop_config_or_error(mixed $config): array|WP_Error
{
    if (!is_array($config)) {
        return etch_error('etch_invalid_input', __('"config" must be an object.', domain: 'novamira-pro'));
    }

    $encoded = wp_json_encode($config, flags: 0, depth: ETCH_MAX_ATTRS_DEPTH);
    if (!is_string($encoded) || strlen($encoded) > ETCH_MAX_ATTRS_BYTES) {
        return etch_error('etch_config_too_large', __(
            '"config" is too large or too deeply nested to store safely.',
            domain: 'novamira-pro',
        ));
    }

    return $config;
}

/**
 * Generate a loop id not already present in the map.
 *
 * @param array<array-key, mixed> $existing
 */
function etch_generate_loop_id(array $existing): string
{
    do {
        $id = substr(uniqid(), offset: -7);
    } while ($id === '' || isset($existing[$id]));

    return $id;
}

/**
 * Shape one loop entry for output.
 *
 * @param array<array-key, mixed> $loop
 * @return array{id: string, name: string, key: string, global: bool, config: array<array-key, mixed>}
 */
function etch_loop_view(string $id, array $loop): array
{
    /** @var mixed $name */
    $name = $loop['name'] ?? '';
    /** @var mixed $key */
    $key = $loop['key'] ?? '';
    /** @var mixed $config */
    $config = $loop['config'] ?? [];

    return [
        'id' => $id,
        'name' => is_string($name) ? $name : '',
        'key' => is_string($key) ? $key : '',
        'global' => ($loop['global'] ?? null) === true,
        'config' => is_array($config) ? $config : [],
    ];
}

// ---------------------------------------------------------------------------
// list-loops
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-loops', [
    'label' => __('List Etch Loops', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Etch loops (data sources an etch/loop node repeats over, stored in the etch_loops option). Returns a compact entry per loop { id, name, key, global, config_type } — global:true marks an Etch built-in preset. Fetch the full config with novamira/etch-get-loop.',
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
            'total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'loops' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'key' => ['type' => 'string'],
                        'global' => ['type' => 'boolean'],
                        'config_type' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_loops',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact — no full config. If truncated is true there are more than 500 loops (total reports the real count). Reference a loop from a page via an etch/loop node whose attrs.loopId is the loop id. Use novamira/etch-get-loop for the full config (type + args).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, total: int, truncated: bool, loops: list<array{id: string, name: string, key: string, global: bool, config_type: string}>}
 */
function etch_list_loops(): array
{
    $loops = etch_loops_option();

    $cap = 500;
    $total = 0;
    $out = [];
    foreach (array_keys($loops) as $id) {
        /** @var mixed $loop */
        $loop = $loops[$id];
        if (!is_array($loop)) {
            continue;
        }

        $total++;
        if (count($out) >= $cap) {
            continue;
        }

        $view = etch_loop_view((string) $id, $loop);
        /** @var mixed $config_type */
        $config_type = $view['config']['type'] ?? '';
        $out[] = [
            'id' => $view['id'],
            'name' => $view['name'],
            'key' => $view['key'],
            'global' => $view['global'],
            'config_type' => is_string($config_type) ? $config_type : '',
        ];
    }

    return [
        'count' => count($out),
        'total' => $total,
        'truncated' => $total > count($out),
        'loops' => $out,
    ];
}

// ---------------------------------------------------------------------------
// get-loop
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-loop', [
    'label' => __('Get Etch Loop', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Etch loop in full: id, name, key, global, and config (the query definition { type, args }).',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'loop_id' => ['type' => 'string', 'description' => 'The loop id (the etch_loops map key).'],
        ],
        'required' => ['loop_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'global' => ['type' => 'boolean'],
            'config' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a loop before editing it. config.type is e.g. "wp-query"; config.args holds the query args (post_type, posts_per_page, …, with Etch $variables).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{id: string, name: string, key: string, global: bool, config: array<array-key, mixed>}|WP_Error
 */
function etch_get_loop(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_loop_id_or_error($input['loop_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $loops = etch_loops_option();
    $loop = etch_loop_or_error($loops, $id);
    if ($loop instanceof WP_Error) {
        return $loop;
    }

    return etch_loop_view($id, $loop);
}

// ---------------------------------------------------------------------------
// create-loop
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-loop', [
    'label' => __('Create Etch Loop', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Etch loop. Requires name and config (the query definition, e.g. { "type": "wp-query", "args": { "post_type": "post", "posts_per_page": 10 } }); key defaults to a slug of the name. Returns the new id, name, and key. Reference it from a page via an etch/loop node with attrs.loopId = the returned id.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'config' => ['type' => 'object', 'description' => 'The query definition { type, args }.'],
            'key' => ['type' => 'string', 'description' => 'A slug; defaults to a slug of the name.'],
        ],
        'required' => ['name', 'config'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Model config on an existing loop (novamira/etch-get-loop) — config.type "wp-query" with config.args being WP_Query args. The new loop is user-level (global:false).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: string, name: string, key: string}|WP_Error
 */
function etch_create_loop(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $name */
    $name = $input['name'] ?? '';
    if (!is_string($name) || trim($name) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "name" is required.', domain: 'novamira-pro'));
    }

    $config = etch_loop_config_or_error($input['config'] ?? null);
    if ($config instanceof WP_Error) {
        return $config;
    }

    /** @var mixed $key */
    $key = $input['key'] ?? '';
    $key = is_string($key) && $key !== '' ? sanitize_title($key) : sanitize_title($name);

    $clean_name = sanitize_text_field($name);

    $id = '';
    $lock = etch_option_lock_acquire('etch_loops');
    try {
        $loops = etch_loops_option();
        $id = etch_generate_loop_id($loops);
        $loops[$id] = [
            'name' => $clean_name,
            'key' => $key,
            'global' => false,
            'config' => $config,
        ];
        update_option('etch_loops', $loops);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'id' => $id,
        'name' => $clean_name,
        'key' => $key,
    ];
}

// ---------------------------------------------------------------------------
// edit-loop
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-loop', [
    'label' => __('Edit Etch Loop', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Etch loop in place (partial update). Provide loop_id and any of name, key, config. Built-in preset fields (e.g. _preset_hash) are preserved. Returns id, name, and key.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'loop_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'config' => ['type' => 'object'],
        ],
        'required' => ['loop_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: pass only what changes. Editing a global:true built-in preset rewrites it (its preset fields are preserved).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Validate the provided (optional) loop fields, returning only those given.
 *
 * @param array<array-key, mixed> $input
 * @return array{name?: string, key?: string, config?: array<array-key, mixed>}|WP_Error
 */
function etch_loop_fields_or_error(array $input): array|WP_Error
{
    $fields = [];

    if (isset($input['name'])) {
        /** @var mixed $name */
        $name = $input['name'];
        if (!is_string($name) || trim($name) === '') {
            return etch_error('etch_invalid_input', __('"name" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $fields['name'] = sanitize_text_field($name);
    }

    if (isset($input['key'])) {
        /** @var mixed $key */
        $key = $input['key'];
        if (!is_string($key) || $key === '') {
            return etch_error('etch_invalid_input', __('"key" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $fields['key'] = sanitize_title($key);
    }

    if (isset($input['config'])) {
        $config = etch_loop_config_or_error($input['config']);
        if ($config instanceof WP_Error) {
            return $config;
        }
        $fields['config'] = $config;
    }

    return $fields;
}

/**
 * @return array{id: string, name: string, key: string}|WP_Error
 */
function etch_edit_loop(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_loop_id_or_error($input['loop_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $fields = etch_loop_fields_or_error($input);
    if ($fields instanceof WP_Error) {
        return $fields;
    }

    $result = ['id' => $id, 'name' => '', 'key' => ''];
    $lock = etch_option_lock_acquire('etch_loops');
    try {
        $loops = etch_loops_option();
        $loop = etch_loop_or_error($loops, $id);
        if ($loop instanceof WP_Error) {
            return $loop;
        }

        // Merge into the existing raw entry so preset fields (_preset_hash, …) survive.
        if (isset($fields['name'])) {
            $loop['name'] = $fields['name'];
        }
        if (isset($fields['key'])) {
            $loop['key'] = $fields['key'];
        }
        if (isset($fields['config'])) {
            $loop['config'] = $fields['config'];
        }
        $loops[$id] = $loop;
        update_option('etch_loops', $loops);

        $view = etch_loop_view($id, $loop);
        $result = ['id' => $id, 'name' => $view['name'], 'key' => $view['key']];
    } finally {
        etch_option_lock_release($lock);
    }

    return $result;
}

// ---------------------------------------------------------------------------
// delete-loop
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-loop', [
    'label' => __('Delete Etch Loop', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Etch loop by id. Pages with an etch/loop node referencing it will have nothing to repeat over. NOTE: deleting a built-in preset (global:true) is transient — Etch re-seeds its presets on the next request, so a preset delete does not stick. Returns id and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'loop_id' => ['type' => 'string'],
        ],
        'required' => ['loop_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE. etch/loop nodes referencing this loop id will repeat over nothing. Deleting a global:true built-in preset does NOT persist — Etch re-seeds presets on the next request.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: string, deleted: bool}|WP_Error
 */
function etch_delete_loop(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_loop_id_or_error($input['loop_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $lock = etch_option_lock_acquire('etch_loops');
    try {
        $loops = etch_loops_option();
        $loop = etch_loop_or_error($loops, $id);
        if ($loop instanceof WP_Error) {
            return $loop;
        }

        unset($loops[$id]);
        update_option('etch_loops', $loops);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'id' => $id,
        'deleted' => true,
    ];
}
