<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-edit-node', [
    'label' => __('Edit Beaver Builder Node', domain: 'novamira-pro'),
    'description' => __(
        'Edits one Beaver Builder node settings object. By default settings are merged at the top level; pass replace=true to replace the whole settings object. Node type, id, and parent are immutable here; use move-node for parent/position. If the edit would make the target an HTML (raw-code) module, it is rejected unless you pass `allow_html_module: true`, which is only for a real code snippet the user explicitly asked to embed — never for layout, styling, CSS, or content.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer'],
            'post_id' => ['type' => 'integer'],
            'node_id' => ['type' => 'string'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft', 'both'],
                'description' => 'Defaults to both.',
            ],
            'settings' => ['type' => 'object'],
            'module' => ['type' => 'string', 'description' => 'Convenience alias for settings.type on module nodes.'],
            'replace' => [
                'type' => 'boolean',
                'description' => 'Replace all settings instead of merging. Defaults false.',
            ],
            'allow_html_module' => [
                'type' => 'boolean',
                'description' => 'Opt-in required if the edit turns the node into a raw-code HTML module. Defaults false. Set true ONLY for a code snippet the user explicitly asked to embed, never for layout, styling, CSS, or content.',
            ],
        ],
        'required' => ['node_id', 'settings'],
        'anyOf' => [
            ['required' => ['post']],
            ['required' => ['post_id']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_edit_node',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use get-layout include_settings=true first when editing unfamiliar settings. Merge is shallow: keys you pass replace existing top-level settings, omitted keys are preserved. For module nodes, keep settings.type aligned with the module slug. The HTML (raw-code) module is enforced-gated: an edit that would make the target an HTML module is rejected unless you pass `allow_html_module: true`, which is only for a real code snippet the user explicitly asked to embed — never for layout, CSS, styling, or content.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_edit_node(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['post'] ?? $input['post_id'] ?? null;
    $post = bb_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $post_error = bb_write_post_error($post);
    if ($post_error instanceof WP_Error) {
        return $post_error;
    }

    $node_id = bb_string_arg($input['node_id'] ?? '');
    if ($node_id === '') {
        return bb_error(code: 'beaver_builder_invalid_node_id', message: 'Pass a non-empty node_id.', data: [
            'status' => 400,
        ]);
    }

    $patch = bb_settings_from_input($input['settings'] ?? []);
    $patch = bb_apply_module_alias_to_patch(patch: $patch, module: bb_string_arg($input['module'] ?? ''));
    if ($patch instanceof WP_Error) {
        return $patch;
    }
    $mode = bb_bool_arg($input['replace'] ?? false) ? 'replace' : 'merge';
    $statuses = bb_target_statuses($input);

    if (!bb_bool_arg($input['allow_html_module'] ?? false)) {
        $gate = bb_edit_html_gate(
            post_id: $post->ID,
            node_id: $node_id,
            patch: $patch,
            mode: $mode,
            statuses: $statuses,
        );
        if ($gate instanceof WP_Error) {
            return $gate;
        }
    }

    $locked = bb_with_layout_lock(post_id: $post->ID, fn: fn(): array|WP_Error => beaver_builder_edit_node_locked(
        post_id: $post->ID,
        node_id: $node_id,
        patch: $patch,
        mode: $mode,
        statuses: $statuses,
    ));
    if ($locked instanceof WP_Error) {
        return $locked;
    }

    $response = $locked;
    $response['edited_node_id'] = $node_id;
    return $response;
}

/**
 * Resolve the optional `module` convenience alias onto the patch's
 * `settings.type`, or return a WP_Error when the slug is unknown/disabled.
 */
function bb_apply_module_alias_to_patch(\stdClass $patch, string $module): \stdClass|WP_Error
{
    if ($module === '') {
        return $patch;
    }

    $resolved_module = bb_resolve_module_slug(slug: $module, scope: 'enabled');
    if ($resolved_module === '') {
        return bb_error(
            code: 'beaver_builder_unknown_module',
            message: sprintf('Module `%s` is unknown or disabled.', $module),
            data: ['status' => 400],
        );
    }
    $patch->type = $resolved_module;
    return $patch;
}

/**
 * Reject an edit that would make the target node a Beaver Builder HTML
 * (raw-code) module while `allow_html_module` is not set. The node ends up an
 * HTML module when the patch sets `settings.type` to the html slug, or when the
 * patch leaves the type untouched (shallow merge) and the existing node is
 * already an HTML module — both close the bypass without persisting anything.
 *
 * @param list<string> $statuses
 */
function bb_edit_html_gate(int $post_id, string $node_id, \stdClass $patch, string $mode, array $statuses): ?WP_Error
{
    $patch_type = bb_property_string(object: $patch, property: 'type');
    if ($patch_type === BB_HTML_MODULE_SLUG) {
        return bb_html_gate_error([$node_id]);
    }

    // The patch sets some other module type, or replaces the whole settings
    // object: either way the result cannot inherit the node's existing html
    // type, so only an empty-type shallow merge can still resolve to html.
    if ($patch_type !== '' || $mode !== 'merge') {
        return null;
    }

    foreach ($statuses as $status) {
        $data = bb_layout_data_for_write(post_id: $post_id, status: $status);
        $node = $data[$node_id] ?? null;
        if ($node instanceof \stdClass && bb_is_html_module_node($node)) {
            return bb_html_gate_error([$node_id]);
        }
    }
    return null;
}

/**
 * @param list<string> $statuses
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_edit_node_locked(
    int $post_id,
    string $node_id,
    \stdClass $patch,
    string $mode,
    array $statuses,
): array|WP_Error {
    $prepared = [];
    $response_data = [];
    foreach ($statuses as $status) {
        $old_data = bb_layout_data_for_write(post_id: $post_id, status: $status);
        $data = bb_clone_layout_data($old_data);
        if (!isset($data[$node_id])) {
            return bb_error(
                code: 'beaver_builder_node_not_found',
                message: sprintf('Node `%s` does not exist in the `%s` layout.', $node_id, $status),
                data: ['status' => 404],
            );
        }

        $current = bb_node_settings($data[$node_id]) ?? new \stdClass();
        $new_settings = $mode === 'replace'
            ? $patch
            : (object) array_merge(get_object_vars($current), get_object_vars($patch));
        $data[$node_id]->settings = $new_settings;

        $preserved_modules = bb_existing_module_ids($old_data);
        $old_type = bb_property_string(object: $current, property: 'type');
        $new_type = bb_property_string(object: $new_settings, property: 'type');
        if ($new_type !== '' && $new_type !== $old_type) {
            $resolved_module = bb_resolve_module_slug(slug: $new_type, scope: 'enabled');
            if ($resolved_module === '') {
                return bb_error(
                    code: 'beaver_builder_unknown_module',
                    message: sprintf('Module `%s` is unknown or disabled.', $new_type),
                    data: ['status' => 400],
                );
            }
            $new_settings->type = $resolved_module;
            unset($preserved_modules[$node_id]);
        }

        $validation = bb_validate_layout_data(data: $data, preserved_modules: $preserved_modules);
        if ($validation instanceof WP_Error) {
            return $validation;
        }
        $lifecycle = bb_call_module_lifecycle_hooks(post_id: $post_id, new_data: $data, old_data: $old_data);
        if ($lifecycle instanceof WP_Error) {
            return $lifecycle;
        }
        bb_reindex_layout_positions($data);
        $prepared[$status] = $data;
        $response_data = $data;
    }

    foreach ($prepared as $status => $data) {
        bb_save_layout_data(post_id: $post_id, data: $data, statuses: [$status]);
    }

    return bb_write_response(post_id: $post_id, data: $response_data, statuses: $statuses);
}
