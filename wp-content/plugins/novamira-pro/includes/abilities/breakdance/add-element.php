<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-add-element', [
    'label' => __('Add Breakdance Element', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a new element into the Breakdance element tree of a post. The raw `EssentialElements\\CodeBlock` element is gated and rejected unless the user explicitly confirms it and the re-call sets `allow_code_elements:true`; prefer native Breakdance elements. `parent_id` is the id of the element that will contain the new node (use `1` for the root). `type` is the Breakdance element type slug, e.g. `EssentialElements\\Section`, `EssentialElements\\Heading`, `EssentialElements\\Text`, `EssentialElements\\Image2` (the modern image — the bare `Image` is the deprecated "Image V1"). Slugs are case-sensitive PascalCase: `type` is resolved against Breakdance\'s live element registry, so a wrong-cased slug is normalised to the registered one and a genuinely unknown type is rejected with the nearest match (call `novamira/breakdance-list-element-types` to discover valid slugs). Optional `properties` is the element-type-specific blob (same shape as get-element returns). `position` is the zero-based index inside the parent\'s `children` array (omit to append at the end). The new id is allocated from the tree\'s monotonic `_nextNodeId` counter, matching Breakdance\'s own builder.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'parent_id' => [
                'type' => 'integer',
                'description' => 'Id of the element to insert under. Use 1 for the root.',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Breakdance element type slug (namespace separator is a single backslash, e.g. `EssentialElements\\Section`). `EssentialElements\\CodeBlock` requires the allow_code_elements confirmation handshake.',
            ],
            'properties' => [
                'type' => 'object',
                'description' => 'Optional element-type-specific properties blob. Defaults to `{}`.',
            ],
            'position' => [
                'type' => 'integer',
                'description' => 'Zero-based index in the parent\'s children array. Omit to append.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Set true ONLY after the user has explicitly confirmed they want a raw Breakdance Code Block; never set it on your own initiative.',
            ],
        ],
        'required' => ['post_id', 'parent_id', 'type'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_add_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Element type slugs are case-sensitive PascalCase class names from Breakdance and its add-ons (e.g. EssentialElements\\Section, EssentialElements\\Heading, EssentialElements\\Image2). The modern image element is EssentialElements\\Image2 (label "Image"); EssentialElements\\Image is the deprecated "Image V1". A wrong-cased slug is auto-normalised to the registered one; an unknown type is rejected with the nearest match — call novamira/breakdance-list-element-types to discover valid slugs. Pass `properties` matching the type. The raw EssentialElements\\CodeBlock is rejected with breakdance_code_element_needs_confirmation. Ask the user; only after explicit confirmation may you re-call with allow_code_elements:true, and never set that flag on your own initiative. Successful opt-ins include an audit warning.',
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
function breakdance_add_element(array $input): array|WP_Error
{
    $post_id = bd_coerce_int($input['post_id'] ?? 0);
    $parent_id = bd_coerce_int($input['parent_id'] ?? 0);
    $bail = bd_add_element_validate($input, $post_id, $parent_id);
    if ($bail !== null) {
        return $bail;
    }
    // Resolve `type` against Breakdance's live element registry: normalise a
    // wrong-cased slug to the canonical one, hard-reject a genuinely unknown
    // type. Keeps a typo from shipping a "missing element" card to the canvas.
    // Trim first so a stray-whitespace slug still resolves (and stores clean).
    $input['type'] = trim((string) $input['type']);
    $resolved = bd_resolve_element_type($input['type']);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    $input['type'] = $resolved;
    $gate_node = [
        'id' => 'new',
        'data' => ['type' => $resolved],
        'children' => [],
    ];
    $code_gate = bd_code_element_gate([$gate_node], $input);
    if ($code_gate !== null) {
        return $code_gate;
    }
    /** @var array<string, mixed>|WP_Error $result */
    return bd_with_tree_lock($post_id, static fn(): array|WP_Error => bd_add_element_locked(
        $input,
        $post_id,
        $parent_id,
    ));
}

/**
 * Element-tree mutation for add-element, run under the post's tree
 * lock so concurrent REST calls cannot stomp each other.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bd_add_element_locked(array $input, int $post_id, int $parent_id): array|WP_Error
{
    $tree = bd_read_tree($post_id);
    $parent_hit = bd_find_node($tree, $parent_id);
    if ($parent_hit === null) {
        return bd_error('bd_not_found', sprintf(
            __('Parent element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $parent_id,
            $post_id,
        ));
    }

    $new_id = bd_allocate_id($tree);
    /** @var array<string, mixed> $properties */
    $properties = isset($input['properties']) && is_array($input['properties']) ? $input['properties'] : [];
    $new_node = [
        'id' => $new_id,
        'data' => ['type' => $input['type'], 'properties' => $properties],
        'children' => [],
    ];

    /** @var list<array<string, mixed>> $siblings */
    $siblings = isset($parent_hit['node']['children']) && is_array($parent_hit['node']['children'])
        ? $parent_hit['node']['children']
        : [];
    $siblings = bd_insert_at(
        $siblings,
        $new_node,
        isset($input['position']) ? bd_coerce_int($input['position']) : null,
    );

    if (!bd_set_children($tree, $parent_id, $siblings)) {
        return bd_error('bd_invalid_input', __(
            'Could not insert element under the requested parent.',
            domain: 'novamira-pro',
        ));
    }

    bd_rebuild_lookup($tree);
    $css_warning = null;
    $write = bd_write_tree($post_id, $tree, $css_warning);
    if ($write instanceof WP_Error) {
        return $write;
    }

    $result = ['success' => true, 'element' => bd_element_to_array($new_node, $parent_id)];
    $warnings = array_values(array_filter([bd_code_element_audit_warning([$new_node]), $css_warning]));
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

/**
 * Validate the add-element inputs. Returns a WP_Error to bail out
 * with, or null when the inputs look usable. Extracted so the main
 * callback stays under the cyclomatic threshold.
 *
 * @param array<string, mixed> $input
 */
function bd_add_element_validate(array $input, int $post_id, int $parent_id): ?WP_Error
{
    if ($post_id <= 0 || $parent_id <= 0) {
        return bd_error('bd_invalid_input', __(
            'post_id and parent_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if (!isset($input['type']) || !is_string($input['type']) || trim($input['type']) === '') {
        return bd_error('bd_invalid_input', __(
            '`type` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    // The schema declares `properties: object` but PHP's `is_array`
    // accepts both JSON objects and JSON lists. Reject lists explicitly
    // so a `["a","b"]` payload does not silently corrupt the element.
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
    $post = bd_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    return null;
}
