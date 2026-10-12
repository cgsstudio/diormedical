<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Edit a module's attributes in place (partial merge).
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-edit-module', [
    'label' => __('Edit Divi Module', domain: 'novamira-pro'),
    'description' => __(
        'Updates a single module\'s attributes in place by address (e.g. "0/0/0"). The provided "attrs" are deep-merged into the module\'s existing attributes: nested objects are merged, while lists and scalars replace. Use novamira/divi-get-module-schema to find a module\'s attr paths (most values use the responsive shape <attrName>.<breakpoint>.value). Pass the nested object form ({"module":{"decoration":{"spacing":{"tablet":{"value": …}}}}}); a flat dotted key like "module.decoration.spacing.tablet.value" is also accepted and auto-nested (Divi 5 does not render flat keys), with a warning. The module type is not changed. Content HTML carrying inline style="…" attributes is rejected unless you pass the explicit opt-in "allow_inline_styles": true — style via the module\'s native attrs instead. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Address of the module to edit, e.g. "0/0/0".'],
            'attrs' => ['type' => 'object', 'description' => 'Partial attributes to deep-merge into the module.'],
            'allow_inline_styles' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Explicit opt-in to save content HTML carrying inline style="…" attributes. Only set true when the user has EXPLICITLY confirmed they want inline styles — never on your own initiative.',
            ],
        ],
        'required' => ['address', 'attrs'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_edit_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update of one module\'s attrs. Read the current tree with novamira/divi-get-content to find the address, and novamira/divi-get-module-schema for the attr paths. Merge semantics: pass only the keys you want to change.',
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
function divi_edit_module(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required (e.g. "0/0/0").');
    }

    /** @var mixed $patch */
    $patch = $input['attrs'] ?? null;
    if (!is_array($patch)) {
        return divi_error('invalid_input', message: '"attrs" must be an object.');
    }
    if ($patch !== [] && divi_is_list($patch)) {
        return divi_error('invalid_input', message: '"attrs" must be an object, not a list.');
    }

    $inline_styles = divi_count_inline_style_attrs($patch);
    $gate = divi_inline_style_gate($inline_styles, $input);
    if ($gate !== null) {
        return $gate;
    }

    // Shared helper resolves the node, rejects non-Divi blocks, deep-merges, saves.
    $result = divi_merge_module_attrs($post, $address, $patch);
    if (!$result instanceof WP_Error) {
        // The helper reports any structure the patch re-shaped; keep those.
        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        if (divi_attrs_have_dotted_keys($patch)) {
            $warnings[] = divi_dotted_keys_warning();
        }
        if (divi_attrs_have_free_form_css($patch)) {
            $warnings[] = divi_free_form_css_warning();
        }
        if ($inline_styles > 0) {
            $warnings[] = divi_inline_style_warning($inline_styles);
        }
        if ($warnings !== []) {
            $result['warnings'] = $warnings;
        }
    }
    return $result;
}
