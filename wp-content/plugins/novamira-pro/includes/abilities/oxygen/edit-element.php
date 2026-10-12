<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-edit-element', [
    'label' => __('Edit Oxygen Element', domain: 'novamira-pro'),
    'description' => __(
        'Patches the `properties` of an existing Oxygen element. Every submitted path is validated against that element type\'s live control schema before write. Merge semantics on the **top level** of `properties`: keys you pass replace the corresponding key on the stored element, keys you omit are kept as-is. To wipe a top-level key, pass `null`. Element `type` is immutable here (use add-element + delete-element if you need to swap an element\'s type). EXECUTABLE settings payloads are gated: a `custom-php` display condition, a wp_query envelope in PHP mode, a `term_query` envelope on TermLoopBuilder, or a `phpreturn` / `process_value` dynamic-data payload (including one hidden in a shortcode\'s `params` JSON, which the engine merges OVER the plain attributes) is rejected unless you pass `allow_code_elements: true` after the user explicitly confirms — the engine eval()s each of them on every render.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'element_id' => ['type' => 'integer'],
            'properties' => [
                'type' => 'object',
                'description' => 'Properties patch. Shallow merge: top-level keys replace existing values; nested values are NOT deep-merged (pass the full sub-object you want to write).',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for EXECUTABLE settings payloads: a `custom-php` display condition, a query envelope in PHP mode, or a `phpreturn` / `process_value` dynamic-data payload. The engine eval()s all of these on every render, so they are gated exactly like a raw code element. Set true ONLY after the user has explicitly confirmed they want executable code — never set it on your own.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'properties'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_edit_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow merge only. For nested objects (e.g. `design.layout`) call get-element first, modify the sub-object, then pass the whole sub-object back. To clear a key pass it as null. For responsive maps (`{desktop, tablet, mobile}`) send the whole map — a partial map silently drops the omitted breakpoints. For dynamic data, put the `[breakdance_dynamic field="<slug>"]` shortcode directly in the target property VALUE — a sibling `*_dynamic_meta` key is ignored by the renderer.',
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
function oxygen_edit_element(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    $element_id = ox_coerce_int($input['element_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if (!isset($input['properties']) || !is_array($input['properties'])) {
        return ox_error('oxygen_invalid_input', __('`properties` must be an object/array.', domain: 'novamira-pro'));
    }
    if ($input['properties'] !== [] && array_is_list($input['properties'])) {
        return ox_error('oxygen_invalid_input', __(
            '`properties` must be a JSON object (key/value map), not a list.',
            domain: 'novamira-pro',
        ));
    }
    $input['properties'] = ox_normalize_advanced_css($input['properties']);
    // The element `type` is immutable here, so edit-element cannot introduce a
    // code ELEMENT — but it CAN introduce executable SETTINGS (a `custom-php`
    // display condition, a PHP-mode query envelope, a phpreturn /
    // process_value dynamic payload), which the engine eval()s just the same.
    // Type immutability therefore does not protect this boundary: gate it.
    $payloads = ox_collect_executable_payloads($input['properties']);
    if ($payloads !== [] && !ox_coerce_bool($input['allow_code_elements'] ?? false)) {
        return ox_code_payload_block($payloads);
    }
    $prop_error = ox_invalid_properties($input['properties']);
    if ($prop_error !== null) {
        return ox_error('oxygen_invalid_input', $prop_error);
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_edit_element_locked(
        $input,
        $post_id,
        $element_id,
    ));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_edit_element_locked(array $input, int $post_id, int $element_id): array|WP_Error
{
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    $hit = ox_find_node($tree, $element_id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }
    if ($element_id === 1) {
        return ox_error('oxygen_invalid_input', __(
            'The root element cannot be edited via this ability.',
            domain: 'novamira-pro',
        ));
    }

    /** @var array<string, mixed> $data */
    $data = isset($hit['node']['data']) && is_array($hit['node']['data']) ? $hit['node']['data'] : [];
    $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : '';
    /** @var array<string, mixed> $properties */
    $properties = $input['properties'];
    $prop_error = ox_invalid_properties($properties, $type, mode: 'patch');
    if ($prop_error !== null) {
        return ox_error('oxygen_invalid_input', $prop_error);
    }

    $parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($parent_id === null) {
        return ox_error('oxygen_invalid_input', __('Cannot edit an element without a parent.', domain: 'novamira-pro'));
    }

    $siblings = ox_siblings_of($tree, $parent_id);
    /** @var array<string, mixed> $patch */
    $patch = $input['properties'];
    [$updated, $new_siblings] = ox_patch_element_in_siblings($siblings, $element_id, $patch);
    if ($updated === null) {
        return ox_error('oxygen_not_found', __('Element vanished mid-update.', domain: 'novamira-pro'));
    }

    ox_set_children($tree, $parent_id, $new_siblings);
    ox_rebuild_lookup($tree);
    $write = ox_write_tree($post_id, $tree);
    if ($write instanceof WP_Error) {
        return $write;
    }

    return ['success' => true, 'element' => ox_element_to_array($updated, $parent_id)];
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
function ox_patch_element_in_siblings(array $siblings, int $element_id, array $patch): array
{
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
        $siblings[$i]['data'] = $data;
        return [$siblings[$i], $siblings];
    }
    return [null, $siblings];
}
