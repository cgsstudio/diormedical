<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Insert a module into a post's Divi 5 tree.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-add-module', [
    'label' => __('Add Divi Module', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a module into a post\'s Divi block tree under a parent (by address) at an optional position. Omit "parent" to insert a divi/section at the top level (inside Divi\'s root divi/placeholder envelope when the page carries one — the envelope consumes no address, so the returned address is the new section\'s top-level index in document order, the same path divi-get-content reports). The module name (divi/* — prefix optional), optional attrs, and optional nested children are validated against Divi\'s structural grammar; the insertion is rejected if the module cannot nest under the parent. Nesting follows Divi\'s own rule, which reads the parent\'s ATTRIBUTES as well as its name: the root takes divi/section; a regular section takes divi/row > divi/column > content and fullwidth modules, and an ordinary divi/column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") takes content and fullwidth modules directly, with no row; a column marked module.advanced.specialtyColumns takes divi/row-inner > divi/column-inner; divi/column-inner takes content and fullwidth modules plus divi/group; divi/group takes content and fullwidth modules, another divi/group or a divi/row. A divi/post-filter-item stays inside a divi/post-filter: as its direct child or, where the site\'s Divi accepts it, deeper inside a module nested in the filter that takes content modules (e.g. post-filter > text > post-filter-item); a subtree holding such items cannot be moved out of its filter unless the destination is inside another post filter. A placement Divi rejects is refused, except a shape earlier content may already carry, which is saved with a warning. A SPECIALTY section (module.advanced.type = "specialty") is not supported here: Divi lays it out with direct divi/column children whose rendered widths depend on effective sizing these abilities cannot resolve. Placing a divi/column DIRECTLY in one is refused by set-content, which validates the whole tree you supply, and by add-module, move-module and non-Global apply-library-item, which validate the inserted subtree against its immediate destination. Those checks see the immediate parent only, so no other path guarantees the specialty ancestor is examined: applying a GLOBAL library item skips that validation entirely, and edits to the column itself, deletes, moving it out, the loop / dynamic-content / preset / interaction / display-condition writers and global variable edits may all succeed with no specialty-related warning. What does still fire: an insert is graded against its own immediate parent, so an illegal child there is refused and a tolerated one warns, and an attrs write that changes a section type or a column\'s inner-row flag is still reported. A divi/row in a specialty section saves with a warning. Rule of thumb: a page containing a specialty section is not safe to edit through these abilities — use the Visual Builder. Raw-HTML code modules (divi/code, divi/fullwidth-code) anywhere in the payload are rejected unless you pass the explicit opt-in "allow_code_modules": true — style pages via module.decoration.* attrs, global presets, and global colors/variables instead (see divi-get-style-schema). Returns the new module\'s address. Content HTML carrying inline style="…" attributes is rejected unless you pass the explicit opt-in "allow_inline_styles": true — style via the module\'s native attrs instead. Accepts the post id as "post" or "post_id". This is the supported targeted write path for divi/* modules; the generic Gutenberg Block Editor Queue and novamira/gutenberg-* write abilities cannot finalize them.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'module' => ['type' => 'string', 'description' => 'Module name to insert, e.g. "divi/text" or "text".'],
            'parent' => [
                'type' => 'string',
                'description' => 'Address of the parent module (e.g. "0/0"). Omit or pass "" for the top level (the divi/placeholder envelope consumes no address and is never a parent). Use the addresses divi-get-content returns.',
            ],
            'position' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Index among the parent\'s children. Defaults to appending at the end.',
            ],
            'attrs' => [
                'type' => 'object',
                'description' =>
                    'Initial attributes for the module. Use the nested responsive shape '
                        . '(<attrName>.<breakpoint>.value as nested objects); a flat dotted key is also accepted '
                        . 'and auto-nested, since Divi 5 does not render flat keys.',
            ],
            'children' => [
                'type' => 'array',
                'description' => 'Optional nested child modules {name, attrs?, children?}.',
                'items' => ['type' => 'object'],
            ],
            'allow_code_modules' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Explicit opt-in to insert raw divi/code / divi/fullwidth-code modules. Only set true when the user has EXPLICITLY confirmed they want a raw code module — never on your own initiative.',
            ],
            'allow_inline_styles' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Explicit opt-in to save content HTML carrying inline style="…" attributes. Only set true when the user has EXPLICITLY confirmed they want inline styles — never on your own initiative.',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'node_count' => ['type' => 'integer'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_add_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Insert one module. Respect nesting, which depends on the parent\'s attrs as well as its name: root > divi/section > divi/row > divi/column > content and fullwidth modules, and an ordinary column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") holds content and fullwidth modules directly with no row; a column carrying module.advanced.specialtyColumns holds divi/row-inner > divi/column-inner. A specialty section (type = "specialty") is not supported: set-content, add-module, move-module and non-Global apply-library-item refuse a divi/column placed directly in one, but they check the immediate parent only, so no other path guarantees the specialty ancestor is seen and many may succeed without a specialty-related warning. Rule of thumb: a page containing a specialty section is not safe to edit here — use the Visual Builder. To start a page, add a divi/section at the top level, then a divi/row inside it, then divi/column(s), then content modules. Prefer native modules over divi/code.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_add_module(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    // divi_normalize_input_node reads name/attrs/children and validates them.
    $input['name'] = divi_string_arg($input, key: 'module');
    $node = divi_normalize_input_node($input);
    if ($node instanceof WP_Error) {
        return $node;
    }

    if (divi_module_metadata((string) $node['name']) === null) {
        return divi_error(
            'unknown_module',
            message: sprintf(
                'Unknown module "%s". Use novamira/divi-list-modules to discover valid names.',
                (string) $node['name'],
            ),
            status: 404,
        );
    }

    // Addresses in the gate error are relative to this payload (the inserted
    // module is node 0).
    $gate = divi_code_module_gate([$node], $input);
    if ($gate !== null) {
        return $gate;
    }

    $tree = divi_parse_tree($post->post_content);
    $parent = divi_string_arg($input, key: 'parent');

    $placement = divi_check_placement($tree, $parent, (string) $node['name']);
    if ($placement instanceof WP_Error) {
        return $placement;
    }

    /** @var mixed $raw_attrs */
    $raw_attrs = $input['attrs'] ?? null;
    $inline_styles = divi_input_inline_style_count($input);
    $gate = divi_inline_style_gate($inline_styles, $input);
    if ($gate !== null) {
        return $gate;
    }

    $result = divi_finalize_insert($post, $tree, $parent, divi_int_arg_or_null($input, key: 'position'), $node);
    if ($result instanceof WP_Error) {
        return $result;
    }

    $warnings = $result['warnings'];
    if (is_array($raw_attrs) && divi_attrs_have_dotted_keys($raw_attrs)) {
        $warnings[] = divi_dotted_keys_warning();
    }
    if ($inline_styles > 0) {
        $warnings[] = divi_inline_style_warning($inline_styles);
    }

    return [
        'post_id' => $post->ID,
        'address' => $result['address'],
        'node_count' => $result['node_count'],
        'warnings' => $warnings,
    ];
}
