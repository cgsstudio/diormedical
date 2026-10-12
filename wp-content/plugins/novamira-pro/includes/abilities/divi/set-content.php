<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Replace a post's entire Divi 5 module tree.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-set-content', [
    'label' => __('Set Divi Content', domain: 'novamira-pro'),
    'description' => __(
        'Replaces a post\'s entire Divi block tree from a nested list of modules. Each module is {name, attrs?, children?} where name is a divi/* module (the "divi/" prefix is optional), attrs is the module attributes object, and children is a nested list. Nesting follows Divi\'s own rule, which reads the parent\'s ATTRIBUTES as well as its name: the root takes divi/section; a regular section takes divi/row > divi/column > content and fullwidth modules, and an ordinary divi/column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") takes content and fullwidth modules directly, with no row; a column marked module.advanced.specialtyColumns takes divi/row-inner > divi/column-inner; divi/column-inner takes content and fullwidth modules plus divi/group; divi/group takes content and fullwidth modules, another divi/group or a divi/row. A divi/post-filter-item stays inside a divi/post-filter: as its direct child or, where the site\'s Divi accepts it, deeper inside a module nested in the filter that takes content modules (e.g. post-filter > text > post-filter-item); a subtree holding such items cannot be moved out of its filter unless the destination is inside another post filter. A placement Divi rejects is refused, except a shape earlier content may already carry, which is saved with a warning. A SPECIALTY section (module.advanced.type = "specialty") is not supported here: Divi lays it out with direct divi/column children whose rendered widths depend on effective sizing these abilities cannot resolve. Placing a divi/column DIRECTLY in one is refused by set-content, which validates the whole tree you supply, and by add-module, move-module and non-Global apply-library-item, which validate the inserted subtree against its immediate destination. Those checks see the immediate parent only, so no other path guarantees the specialty ancestor is examined: applying a GLOBAL library item skips that validation entirely, and edits to the column itself, deletes, moving it out, the loop / dynamic-content / preset / interaction / display-condition writers and global variable edits may all succeed with no specialty-related warning. What does still fire: an insert is graded against its own immediate parent, so an illegal child there is refused and a tolerated one warns, and an attrs write that changes a section type or a column\'s inner-row flag is still reported. A divi/row in a specialty section saves with a warning. Rule of thumb: a page containing a specialty section is not safe to edit through these abilities — use the Visual Builder. Module names and nesting are validated. Raw-HTML code modules (divi/code, divi/fullwidth-code) are rejected unless you pass the explicit opt-in "allow_code_modules": true — style pages via module.decoration.* attrs, global presets, and global colors/variables instead (see divi-get-style-schema); a tree made up only of code modules is always rejected. Content HTML carrying inline style="…" attributes is likewise rejected unless you pass the explicit opt-in "allow_inline_styles": true — style via the module\'s native attrs instead. Accepts the post id as "post" or "post_id". This overwrites all existing content; use the *-module abilities for targeted edits. This is the supported write path for divi/* modules; the generic Gutenberg Block Editor Queue and novamira/gutenberg-* write abilities cannot finalize them.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'content' => [
                'type' => 'array',
                'description' =>
                    'Nested list of module nodes {name, attrs?, children?} forming the new tree. '
                        . 'Each node\'s attrs use the nested responsive shape (<attrName>.<breakpoint>.value as '
                        . 'nested objects); a flat dotted key is also accepted and auto-nested, since Divi 5 does '
                        . 'not render flat keys.',
                'items' => ['type' => 'object'],
            ],
            'allow_inline_styles' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Explicit opt-in to save content HTML carrying inline style="…" attributes. Only set true when the user has EXPLICITLY confirmed they want inline styles — never on your own initiative.',
            ],
            'allow_code_modules' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Explicit opt-in to write raw divi/code / divi/fullwidth-code modules. Only set true when the user has EXPLICITLY confirmed they want a raw code module — never on your own initiative.',
            ],
        ],
        'required' => ['content'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'node_count' => ['type' => 'integer'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Overwrites the whole page. Respect nesting, which depends on the parent\'s attrs as well as its name: root > divi/section > divi/row > divi/column > content and fullwidth modules, and an ordinary column also takes divi/group or a nested divi/row; a fullwidth section (module.advanced.type = "fullwidth") holds content and fullwidth modules directly with no row; a column carrying module.advanced.specialtyColumns holds divi/row-inner > divi/column-inner. A specialty section (type = "specialty") is not supported: set-content, add-module, move-module and non-Global apply-library-item refuse a divi/column placed directly in one, but they check the immediate parent only, so no other path guarantees the specialty ancestor is seen and many may succeed without a specialty-related warning. Rule of thumb: a page containing a specialty section is not safe to edit here — use the Visual Builder. Set text/content via the module attrs (e.g. content.innerContent.desktop.value), never by wrapping raw HTML in a code module. Read novamira/divi-get-module-schema for a module\'s attr paths first.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_set_content(array $input)
{
    $post = divi_post_or_error($input['post'] ?? $input['post_id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $raw_content */
    $raw_content = $input['content'] ?? null;
    if (!is_array($raw_content)) {
        return divi_error('invalid_input', message: '"content" must be an array of module nodes.');
    }

    $tree = divi_normalize_input_forest($raw_content);
    if ($tree instanceof WP_Error) {
        return $tree;
    }

    $gate = divi_code_module_gate($tree, $input);
    if ($gate !== null) {
        return $gate;
    }

    $inline_styles = divi_tree_count_inline_style_attrs($tree);
    $inline_gate = divi_inline_style_gate($inline_styles, $input);
    if ($inline_gate !== null) {
        return $inline_gate;
    }

    $validation = divi_validate_tree($tree);
    if ($validation['errors'] !== []) {
        return divi_error(
            'invalid_tree',
            message: 'The module tree is invalid: ' . implode(' ', $validation['errors']),
            status: 422,
            data: ['errors' => $validation['errors']],
        );
    }

    $count = divi_save_tree($post, $tree);
    if ($count instanceof WP_Error) {
        return $count;
    }

    $warnings = $validation['warnings'];
    if (divi_tree_has_free_form_css($tree)) {
        $warnings[] = divi_free_form_css_warning();
    }
    if (divi_raw_nodes_have_dotted_attrs($raw_content)) {
        $warnings[] = divi_dotted_keys_warning();
    }
    if ($inline_styles > 0) {
        $warnings[] = divi_inline_style_warning($inline_styles);
    }

    return [
        'post_id' => $post->ID,
        'node_count' => $count,
        'warnings' => $warnings,
    ];
}
