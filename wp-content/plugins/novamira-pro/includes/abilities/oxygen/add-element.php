<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-add-element', [
    'label' => __('Add Oxygen Element', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a new element into the Oxygen element tree of a post. `parent_id` is the id of the element that will contain the new node (use `1` for the root). `type` is the element type slug in case-sensitive PascalCase, resolved against the LIVE element registry — always discover valid slugs with novamira/oxygen-list-element-types instead of guessing. Base Oxygen 6 ships the native `OxygenElements\\*` set: for example `OxygenElements\\Container`, `OxygenElements\\Text`, `OxygenElements\\RichText`, `OxygenElements\\Image`, `OxygenElements\\ContainerShortcode`, `OxygenElements\\Shortcode`, `OxygenElements\\WpWidget`, `OxygenElements\\DynamicDataLoop`, `OxygenElements\\oEmbed`. Builder elements such as `EssentialElements\\Section`, `EssentialElements\\Heading`, `EssentialElements\\Image2` exist only when the optional "Breakdance Elements for Oxygen" extension is active (check `elements.essential_extension_present` in check-setup). The RAW-CODE elements (`OxygenElements\\PhpCode`, `HtmlCode`, `CssCode`, `JavaScriptCode`, `EssentialElements\\CodeBlock`) are a last resort and are GATED: the call is rejected unless you pass `allow_code_elements: true` after the user explicitly confirms they want a raw code element. A wrong-cased slug is normalised to the registered one; an unknown type is rejected with the nearest match. Optional `properties` is the element-type-specific blob (same shape as get-element returns). `position` is the zero-based index inside the parent\'s `children` array (omit to append at the end). The new id is allocated from the tree\'s monotonic `_nextNodeId` counter.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
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
                'description' => 'Element type slug in PascalCase (namespace separator is a single backslash), e.g. `OxygenElements\\Container` or `OxygenElements\\HtmlCode`. Discover valid slugs via novamira/oxygen-list-element-types.',
            ],
            'properties' => [
                'type' => 'object',
                'description' => 'Optional element-type-specific properties blob. Every path is validated against the live schema from oxygen-get-element-schema. Defaults to `{}`.',
            ],
            'position' => [
                'type' => 'integer',
                'description' => 'Zero-based index in the parent\'s children array. Omit to append.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for the raw-code element types (PhpCode, HtmlCode, CssCode, JavaScriptCode, CodeBlock), which execute/inject operator-supplied code on every render and are a last resort. By default adding one is rejected with guidance to use native elements. Set true ONLY after the user has explicitly confirmed they want a raw code element — never set it on your own.',
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
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_add_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Element type slugs are case-sensitive PascalCase, resolved against the live registry — call novamira/oxygen-list-element-types to discover valid slugs. Call oxygen-get-element-schema for that type before sending properties; unknown deep paths are rejected. A wrong-cased slug auto-normalises; an unknown type is rejected with a "did you mean" hint.',
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
function oxygen_add_element(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    $parent_id = ox_coerce_int($input['parent_id'] ?? 0);
    $bail = ox_add_element_validate($input, $post_id, $parent_id);
    if ($bail !== null) {
        return $bail;
    }
    // Resolve `type` against the live element registry so a typo doesn't
    // ship a "missing element" card to the canvas. Trim first so a stray-
    // whitespace slug still resolves.
    $input['type'] = trim((string) $input['type']);
    $resolved = ox_resolve_element_type($input['type']);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    $input['type'] = $resolved;
    // Raw-code elements require the explicit allow_code_elements opt-in
    // (checked on the RESOLVED slug so a case-alias cannot bypass it).
    $allow_code = ox_coerce_bool($input['allow_code_elements'] ?? false);
    if (ox_is_gated_code_type($resolved) && !$allow_code) {
        return ox_code_payload_block([$resolved]);
    }
    if (isset($input['properties']) && is_array($input['properties'])) {
        $input['properties'] = ox_normalize_advanced_css($input['properties']);
        // Executable SETTINGS are gated exactly like an executable element
        // type: a plain Text node carrying a `custom-php` display condition,
        // a PHP-mode query envelope, or a phpreturn/process_value dynamic
        // payload reaches the same engine eval() a PhpCode element would.
        $payloads = ox_collect_executable_payloads($input['properties']);
        if ($payloads !== [] && !$allow_code) {
            return ox_code_payload_block($payloads);
        }
        $prop_error = ox_invalid_properties($input['properties'], $resolved);
        if ($prop_error !== null) {
            return ox_error('oxygen_invalid_input', $prop_error);
        }
    }
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_add_element_locked(
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
function ox_add_element_locked(array $input, int $post_id, int $parent_id): array|WP_Error
{
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    $parent_hit = ox_find_node($tree, $parent_id);
    if ($parent_hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Parent element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $parent_id,
            $post_id,
        ));
    }

    // Nesting rule: reject inserting under a leaf/loop parent (nestingRule
    // `final`). Oxygen renders no child slot for those — a loop renders its
    // repeated item through content.repeated_block.global_block, not children —
    // so the tree would be accepted but silently not render.
    /** @var array<string, mixed> $parent_data */
    $parent_data = isset($parent_hit['node']['data']) && is_array($parent_hit['node']['data'])
        ? $parent_hit['node']['data']
        : [];
    $parent_type = isset($parent_data['type']) && is_string($parent_data['type']) ? $parent_data['type'] : '';
    if (!ox_element_accepts_children($parent_type)) {
        $hint = ox_element_is_loop($parent_type)
            ? __(
                'It is a query loop: build the repeated item in an oxygen_block and reference it at content.repeated_block.global_block, not as a child.',
                domain: 'novamira-pro',
            )
            : __('It is a leaf element and renders no children.', domain: 'novamira-pro');
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: parent id, 2: parent element type, 3: hint */
            __('Element %1$d (`%2$s`) cannot contain children. %3$s', domain: 'novamira-pro'),
            $parent_id,
            $parent_type,
            $hint,
        ));
    }

    $new_id = ox_allocate_id($tree);
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
    $siblings = ox_insert_at(
        $siblings,
        $new_node,
        isset($input['position']) ? ox_coerce_int($input['position']) : null,
    );

    if (!ox_set_children($tree, $parent_id, $siblings)) {
        return ox_error('oxygen_invalid_input', __(
            'Could not insert element under the requested parent.',
            domain: 'novamira-pro',
        ));
    }

    ox_rebuild_lookup($tree);
    $write = ox_write_tree($post_id, $tree);
    if ($write instanceof WP_Error) {
        return $write;
    }

    return ['success' => true, 'element' => ox_element_to_array($new_node, $parent_id)];
}

/**
 * Validate the add-element inputs. Returns a WP_Error to bail out
 * with, or null when the inputs look usable. Extracted so the main
 * callback stays under the cyclomatic threshold.
 *
 * @param array<string, mixed> $input
 */
function ox_add_element_validate(array $input, int $post_id, int $parent_id): ?WP_Error
{
    if ($post_id <= 0 || $parent_id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'post_id and parent_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    if (!isset($input['type']) || !is_string($input['type']) || trim($input['type']) === '') {
        return ox_error('oxygen_invalid_input', __(
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
        return ox_error('oxygen_invalid_input', __(
            '`properties` must be a JSON object (key/value map), not a list.',
            domain: 'novamira-pro',
        ));
    }
    if (isset($input['properties']) && is_array($input['properties'])) {
        $prop_error = ox_invalid_properties($input['properties']);
        if ($prop_error !== null) {
            return ox_error('oxygen_invalid_input', $prop_error);
        }
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    return null;
}
