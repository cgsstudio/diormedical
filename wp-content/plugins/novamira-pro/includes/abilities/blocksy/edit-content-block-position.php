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
 * Ability: edit one hook content block's position settings.
 *
 * A separate ability (not folded into edit-conditions) although both
 * live in the same options meta row: conditions answer WHERE ON THE
 * SITE a block appears, position answers WHERE IN THE PAGE MARKUP a
 * hook block renders — distinct concerns with distinct grammars, and
 * one-ability-per-operation beats an action blob. Per the Novamira
 * `edit` verb semantics this one is a PARTIAL merge (scalar keys are
 * individually optional; `additional_locations`, an ordered list
 * without row identity, is replaced whole when provided) — unlike the
 * conditions ability, whose payload is one indivisible list.
 */

wp_register_ability('novamira/blocksy-edit-content-block-position', [
    'label' => __('Edit Blocksy Content Block Position', domain: 'novamira-pro'),
    'description' => __(
        'Edits the hook position of a Blocksy Content Block with template type "hook" (Companion Pro): where in the page markup it renders. Partial merge — only the provided keys change: location (a Blocksy HooksManager id like wp_body_open / blocksy:content:before, "custom_hook" with custom_location naming any WordPress action, or "" for none), priority (1–100), the count fields for the virtual locations (paragraphs_count for blocksy:single:content:paragraphs-number, headings_count, cards_count + repeat_for_every_card for blocksy:loop:card:cards-number), and additional_locations (extra rows of the same shape; replaced as a whole list when provided, empty array clears them). Only the supplied keys are written — absent keys are neither materialized nor re-typed, so customizer-authored rows keep their exact bytes. Validated against the live hooks catalog; one atomic verified meta write; conditions and all other block settings are preserved.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Content-block post id (template type must be "hook").',
            ],
            'position' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                    'priority' => ['type' => 'integer'],
                    'custom_location' => ['type' => 'string'],
                    'paragraphs_count' => ['type' => 'integer'],
                    'headings_count' => ['type' => 'integer'],
                    'cards_count' => ['type' => 'integer'],
                    'repeat_for_every_card' => ['type' => 'boolean'],
                    'additional_locations' => ['type' => 'array'],
                ],
                'additionalProperties' => false,
                'description' => 'The position keys to change (at least one).',
            ],
        ],
        'required' => ['id', 'position'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'position' => [
                'type' => 'object',
                'description' => 'The full effective position after the edit: {location, priority, custom_location, paragraphs_count, headings_count, cards_count, repeat_for_every_card, additional_locations}.',
            ],
            'changed' => [
                'type' => 'boolean',
                'description' => 'False when the stored position already matched (idempotent no-op — nothing was written).',
            ],
        ],
        'required' => ['id', 'position', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_edit_content_block_position',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Position changes take effect on the next front-end request. The block still needs publish status, enabled=true and a matching include condition to actually render at the hook.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, position: array<string, mixed>, changed: bool}|WP_Error
 */
function blocksy_edit_content_block_position(array $input): array|WP_Error
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
    if ($template_type !== 'hook') {
        return bly_error('bly_invalid_input', sprintf(
            'Content block %d has template type "%s" — hook positions apply only to "hook" blocks (other types render through their own template surface).',
            $post->ID,
            bly_ext_clean_text($template_type, BLY_EXT_MAX_NAME_BYTES),
        ));
    }

    // Validate against the CURRENT stored/effective position so
    // cross-field rules (custom_hook needs custom_location) see the
    // merged view — the stored read here is validation-only, never
    // persisted. The locked mutate below re-reads fresh under the lock
    // and re-applies the same supplied-key merge, so a concurrent edit
    // cannot be clobbered by this snapshot, and ONLY the supplied keys
    // are written: absent keys are not materialized and stored values
    // (including customizer-written numeric strings) keep their exact
    // bytes.
    $validated = bly_cb_validate_position(
        $input['position'] ?? null,
        effective_base: bly_cb_position_effective_base(bly_cb_read_atts($post->ID)),
    );
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    /** @var mixed $position_input */
    $position_input = $input['position'];
    $written = bly_cb_locked_atts_edit($post->ID, static function (array $prior) use ($position_input): array|WP_Error {
        $revalidated = bly_cb_validate_position(
            $position_input,
            effective_base: bly_cb_position_effective_base($prior),
        );
        if ($revalidated instanceof WP_Error) {
            return $revalidated;
        }
        $next = $prior;
        foreach ($revalidated['set'] as $key => $value) {
            $next[$key] = $value;
        }
        if ($revalidated['additional_locations'] !== null) {
            $next['additional_locations'] = $revalidated['additional_locations'];
        }
        return $next;
    });
    if ($written instanceof WP_Error) {
        return $written;
    }

    $final = bly_cb_stored_position($written['atts']);
    return [
        'id' => $post->ID,
        'position' => $final,
        'changed' => $written['changed'],
    ];
}
