<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: replace one content block's display conditions.
 *
 * REPLACE semantics, deliberately: the customizer's conditions modal
 * edits the entire condition tree at once and the Companion persists it
 * by setting the whole `conditions` key inside the options meta
 * (admin-ui.php's read-whole-atts → set key → update_post_meta pattern).
 * A merge would be ambiguous for an ordered tree (no stable row
 * identity), so the input here IS the complete new value — pass the
 * current descriptor (from blocksy-get-content-block) plus/minus the
 * change. The persisted shape is the UI's own descriptor
 * `{relation, conditions}` (ConditionsManager.js:54-59 wraps and saves
 * exactly that); nested Advanced-Mode relation groups are accepted and
 * validated recursively. All sibling option keys (position, design,
 * is_hook_enabled) are preserved untouched.
 */

wp_register_ability('novamira/blocksy-edit-content-block-conditions', [
    'label' => __('Edit Blocksy Content Block Conditions', domain: 'novamira-pro'),
    'description' => __(
        'REPLACES the display conditions of one Blocksy Content Block (Companion Pro) — the same whole-value semantics as the customizer\'s conditions modal, so pass the complete new condition set (start from blocksy-get-content-block\'s `conditions`). Input is either a JSON array of {type: include|exclude, rule, payload?} rows, or a full {relation, conditions} descriptor whose rows may include nested {relation: AND|OR, conditions} groups (Advanced Mode); both persist as the descriptor the customizer itself saves. Excludes are evaluated within their OWN group\'s relation and a nested group acts as an include in its parent group — root OR(include:everywhere, AND(exclude:everywhere)) still matches, because the direct include succeeds. EMPTY conditions make the block match nowhere (maintenance/nothing_found template types then default to everywhere). Rules, payloads and group shapes are strictly validated against the install\'s live catalog for the block\'s template type; the write is one atomic meta update with verified persistence — on failure nothing changes (no partial condition set is ever stored). All other block settings (position, design keys, enabled switch) are preserved.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Content-block post id.',
            ],
            'conditions' => [
                'type' => ['array', 'object'],
                'description' => 'The COMPLETE new conditions (replaces the stored value): a JSON array of rule rows, or a {relation, conditions} descriptor with nested relation groups. Empty array clears all conditions.',
            ],
        ],
        'required' => ['id', 'conditions'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'template_type' => ['type' => 'string'],
            'conditions' => [
                'type' => 'object',
                'description' => 'The normalized {relation, conditions} descriptor as persisted.',
            ],
            'changed' => [
                'type' => 'boolean',
                'description' => 'False when the stored conditions already equalled the given list (idempotent no-op — nothing was written).',
            ],
        ],
        'required' => ['id', 'template_type', 'conditions', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_edit_content_block_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'This REPLACES the whole condition set — to add one rule, pass the existing descriptor plus the new row. Condition changes take effect on the next front-end request; the block must also be published and enabled to render.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, template_type: string, conditions: array{relation: string, conditions: list<array<string, mixed>>}, changed: bool}|WP_Error
 */
function blocksy_edit_content_block_conditions(array $input): array|WP_Error
{
    $gate = bly_cb_gate();
    if ($gate !== null) {
        return $gate;
    }

    $post = bly_cb_get_post($input['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $template_type = bly_cb_template_type($post->ID);
    if ($template_type === '' || !in_array($template_type, bly_cb_template_types(), strict: true)) {
        return bly_error('bly_invalid_input', sprintf(
            'Content block %d has no valid template_type meta ("%s") — its condition grammar cannot be determined. Fix the block first (recreate it, or set the template_type meta).',
            $post->ID,
            bly_ext_clean_text($template_type, BLY_EXT_MAX_NAME_BYTES),
        ));
    }

    $conditions = bly_cb_validate_conditions($input['conditions'] ?? null, $template_type);
    if ($conditions instanceof WP_Error) {
        return $conditions;
    }

    $written = bly_cb_locked_atts_edit($post->ID, static function (array $prior) use ($conditions): array {
        $next = $prior;
        $next['conditions'] = $conditions;
        return $next;
    });
    if ($written instanceof WP_Error) {
        return $written;
    }

    return [
        'id' => $post->ID,
        'template_type' => $template_type,
        'conditions' => $conditions,
        'changed' => $written['changed'],
    ];
}
