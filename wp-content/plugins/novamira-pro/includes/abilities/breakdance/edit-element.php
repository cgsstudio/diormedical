<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-edit-element', [
    'label' => __('Edit Breakdance Element', domain: 'novamira-pro'),
    'description' => __(
        'Patches the `properties` or registered `type` of an existing Breakdance element. Editing an existing raw `EssentialElements\\CodeBlock`, or changing another element into one, is gated and rejected unless the user explicitly confirms it and the re-call sets `allow_code_elements:true`. Merge semantics apply on the **top level** of `properties`: keys you pass replace the corresponding key on the stored element, keys you omit are kept as-is. To wipe a key, pass `null`.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'element_id' => ['type' => 'integer'],
            'properties' => [
                'type' => 'object',
                'description' => 'Properties patch. Shallow merge: top-level keys replace existing values; nested values are NOT deep-merged (pass the full sub-object you want to write).',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Optional replacement element type, resolved against the live registry. Changing to `EssentialElements\\CodeBlock` requires the allow_code_elements confirmation handshake.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Set true ONLY after the user has explicitly confirmed they want to write a raw Breakdance Code Block; never set it on your own initiative.',
            ],
        ],
        'required' => ['post_id', 'element_id'],
        'anyOf' => [
            ['required' => ['properties']],
            ['required' => ['type']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_edit_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow merge only. For nested objects (e.g. `design.layout`) call get-element first, modify the sub-object, then pass the whole sub-object back. To clear a key pass it as null. Optional `type` replaces the element type after live-registry resolution. Editing an existing EssentialElements\\CodeBlock or changing a node into one is rejected with breakdance_code_element_needs_confirmation. Ask the user; only after explicit confirmation may you re-call with allow_code_elements:true, and never set that flag on your own initiative. Successful opt-ins include an audit warning. For dynamic data, put the `[breakdance_dynamic field="<slug>"]` shortcode directly in the target property VALUE (e.g. `content.content.text`).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_edit_element(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    $element_id = bd_coerce_int($input['element_id'] ?? 0);
    $validation = bd_edit_element_validate($input, $post_id, $element_id);
    if ($validation instanceof WP_Error) {
        return $validation;
    }
    $input = $validation;
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_tree_lock($post_id, static fn(): array|WP_Error => bd_edit_element_locked(
        $input,
        $post_id,
        $element_id,
    ));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_edit_element_validate(array $input, int $post_id, int $element_id): array|WP_Error
{
    if ($post_id <= 0 || $element_id <= 0) {
        return bd_error('bd_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if (!array_key_exists('properties', $input) && !array_key_exists('type', $input)) {
        return bd_error('bd_invalid_input', __(
            'At least one of `properties` or `type` is required.',
            domain: 'novamira-pro',
        ));
    }
    if (array_key_exists('properties', $input) && !is_array($input['properties'])) {
        return bd_error('bd_invalid_input', __('`properties` must be an object/array.', domain: 'novamira-pro'));
    }
    if (
        isset($input['properties'])
        && is_array($input['properties'])
        && $input['properties'] !== []
        && array_is_list($input['properties'])
    ) {
        return bd_error('bd_invalid_input', __(
            '`properties` must be a JSON object (key/value map), not a list.',
            domain: 'novamira-pro',
        ));
    }
    if (array_key_exists('type', $input)) {
        if (!is_string($input['type']) || trim($input['type']) === '') {
            return bd_error('bd_invalid_input', __('`type` must be a non-empty string.', domain: 'novamira-pro'));
        }
        $resolved = bd_resolve_element_type(trim($input['type']));
        if ($resolved instanceof WP_Error) {
            return $resolved;
        }
        $input['type'] = $resolved;
    }
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    return $input;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_edit_element_locked(array $input, int $post_id, int $element_id): array|WP_Error
{
    $tree = bd_read_tree($post_id);
    $hit = bd_find_node($tree, $element_id);
    if ($hit === null) {
        return bd_error('bd_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }
    if ($element_id === 1) {
        return bd_error('bd_invalid_input', __(
            'The root element cannot be edited via this ability.',
            domain: 'novamira-pro',
        ));
    }

    $parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($parent_id === null) {
        return bd_error('bd_invalid_input', __('Cannot edit an element without a parent.', domain: 'novamira-pro'));
    }

    $siblings = bd_siblings_of($tree, $parent_id);
    $code_gate = bd_edit_element_code_gate($hit['node'], $input);
    if ($code_gate !== null) {
        return $code_gate;
    }

    /** @var array<string, mixed> $patch */
    $patch = isset($input['properties']) && is_array($input['properties']) ? $input['properties'] : [];
    $replacement_type = is_string($input['type'] ?? null) ? $input['type'] : null;
    [$updated, $new_siblings] = bd_patch_element_in_siblings($siblings, $element_id, $patch, $replacement_type);
    if ($updated === null) {
        return bd_error('bd_not_found', __('Element vanished mid-update.', domain: 'novamira-pro'));
    }

    bd_set_children($tree, $parent_id, $new_siblings);
    bd_rebuild_lookup($tree);
    $css_warning = null;
    $write = bd_write_tree($post_id, $tree, $css_warning);
    if ($write instanceof WP_Error) {
        return $write;
    }

    $result = ['success' => true, 'element' => bd_element_to_array($updated, $parent_id)];
    $warnings = array_values(array_filter([
        bd_code_element_audit_warning([bd_edit_element_target_only($updated)]),
        $css_warning,
    ]));
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

/**
 * Gate the element's effective type: a replacement type wins, otherwise the
 * stored type remains in effect for this property edit.
 *
 * @param array<string, mixed> $node
 * @param array<string, mixed> $input
 */
function bd_edit_element_code_gate(array $node, array $input): ?WP_Error
{
    /** @var array<string, mixed> $existing_data */
    $existing_data = isset($node['data']) && is_array($node['data'])
        ? $node['data']
        : ['type' => '', 'properties' => []];
    $effective_type = '';
    if (is_string($existing_data['type'] ?? null)) {
        $effective_type = $existing_data['type'];
    }
    if (is_string($input['type'] ?? null)) {
        $effective_type = $input['type'];
    }
    $node['data'] = [...$existing_data, 'type' => $effective_type];
    return bd_code_element_gate([bd_edit_element_target_only($node)], $input);
}

/**
 * Remove descendants before target-scoped code-gate and audit checks. The node
 * returned by bd_find_node carries its complete subtree, but editing a parent
 * must not inherit confirmation requirements from child Code Blocks.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function bd_edit_element_target_only(array $node): array
{
    $node['children'] = [];
    return $node;
}

/**
 * Apply the shallow-merge property patch to the element matching
 * `$element_id` in `$siblings`. Returns the updated sibling list and
 * the patched node (or null when the element is missing).
 *
 * @param list<array<string, mixed>> $siblings
 * @param array<string, mixed> $patch
 * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>}
 */
function bd_patch_element_in_siblings(
    array $siblings,
    int $element_id,
    array $patch,
    ?string $replacement_type = null,
): array {
    foreach ($siblings as $i => $sib) {
        if ((int) $sib['id'] !== $element_id) {
            continue;
        }
        /** @var array<string, mixed> $data */
        $data = isset($sib['data']) && is_array($sib['data']) ? $sib['data'] : ['type' => '', 'properties' => []];
        /** @var array<string, mixed> $existing_props */
        $existing_props = isset($data['properties']) && is_array($data['properties']) ? $data['properties'] : [];
        $merged_props = array_merge($existing_props, $patch);
        /** @var mixed $value */
        foreach ($patch as $key => $value) {
            if ($value !== null) {
                continue;
            }

            unset($merged_props[$key]);
        }
        $data['properties'] = $merged_props;
        if ($replacement_type !== null) {
            $data['type'] = $replacement_type;
        }
        $siblings[$i]['data'] = $data;
        return [$siblings[$i], $siblings];
    }
    return [null, $siblings];
}
