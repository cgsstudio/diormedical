<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-apply-library-template', [
    'label' => __('Apply OceanWP Library Template', domain: 'novamira-pro'),
    'description' => __(
        'Mounts an Ocean Extra Library template (`oceanwp_library` CPT post) into one of the four customizer-driven slots OceanWP reads at render time: `header`, `topbar`, `footer_widgets`, `custom_404`. Writes the template id into the matching theme_mod (`ocean_header_template` / `ocean_topbar_template` / `ocean_footer_widgets_replace` / `ocean_custom_404_template`). The header slot is only effective when the header style is set to `custom` (see `oceanwp-set-header-config`). REQUIRES Ocean Extra.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slot' => [
                'type' => 'string',
                'enum' => ['header', 'topbar', 'footer_widgets', 'custom_404'],
                'description' => 'Which template-slot to mount the Library item into.',
            ],
            'template_id' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Post id of an `oceanwp_library` CPT entry (see `oceanwp-list-library-items`). Pass 0 to CLEAR the slot.',
            ],
        ],
        'required' => ['slot', 'template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slot' => ['type' => 'string'],
            'theme_mod' => ['type' => 'string', 'description' => 'Internal theme_mod name written.'],
            'persisted' => ['type' => 'boolean'],
            'previous_template_id' => ['type' => ['integer', 'null']],
            'template_id' => ['type' => 'integer'],
        ],
        'required' => ['slot', 'theme_mod', 'persisted', 'previous_template_id', 'template_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_apply_library_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Validates that `template_id` is an existing `oceanwp_library` post in publish state (unless 0 = clear). Returns `previous_template_id` so the agent can confirm the swap or roll back.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slot?: string, template_id?: int} $input
 * @return array{
 *   slot: string,
 *   theme_mod: string,
 *   persisted: bool,
 *   previous_template_id: int|null,
 *   template_id: int
 * }|WP_Error
 */
function oceanwp_apply_library_template(array $input): array|WP_Error
{
    $gate = ow_assert_ocean_extra_available();
    if ($gate !== null) {
        return $gate;
    }
    $slot = ow_coerce_string_or_null($input['slot'] ?? null);
    if ($slot === null) {
        return ow_invalid_input(__('Input `slot` is required.', domain: 'novamira-pro'));
    }
    $slot_map = ow_library_slot_map();
    if (!isset($slot_map[$slot])) {
        return ow_invalid_input(sprintf(
            /* translators: 1: the slot passed by the caller, 2: list of valid slot ids */
            __('Unknown slot "%1$s". Valid slots: %2$s.', domain: 'novamira-pro'),
            $slot,
            implode(', ', array_keys($slot_map)),
        ));
    }
    $template_id = ow_coerce_int_or_null($input['template_id'] ?? null);
    if ($template_id === null || $template_id < 0) {
        return ow_invalid_input(__(
            'Input `template_id` is required (non-negative integer; 0 to clear).',
            domain: 'novamira-pro',
        ));
    }
    if ($template_id > 0) {
        // @mago-expect analysis:mixed-assignment
        $post = get_post($template_id);
        if (!$post instanceof \WP_Post || $post->post_type !== 'oceanwp_library') {
            return ow_invalid_input(sprintf(
                /* translators: %d: template id */
                __('No `oceanwp_library` template found with id %d.', domain: 'novamira-pro'),
                $template_id,
            ));
        }
        // Enforce the publish-state guard advertised in the annotation. A
        // trash / auto-draft / pending / draft library item silently renders
        // empty at the front-end if mounted into a customer-facing slot.
        // Audit round 2 M#9 (3× cross-reviewer agreement).
        if ($post->post_status !== 'publish') {
            return ow_template_not_published($template_id, $post->post_status);
        }
    }

    // Wrap the read-modify-write in the shared per-blog lock so a concurrent
    // S2/S3/S4 theme_mod writer cannot interleave between our read and our
    // write. Without this, `previous_template_id` could refer to a value the
    // other writer changed mid-call and a rollback driven off it would
    // restore the wrong template id. Audit round 2 HIGH#1.
    $theme_mod = $slot_map[$slot];
    return ow_with_theme_mods_lock(
        static fn(): array => ow_apply_library_template_locked($slot, $theme_mod, $template_id),
    );
}

/**
 * Lock-guarded body of `oceanwp-apply-library-template`. Extracted so the
 * main callback stays readable AND under mago's cyclomatic-complexity
 * threshold while still running every state-touching call inside the
 * `ow_with_theme_mods_lock` closure. Audit round 2 HIGH#1.
 *
 * @return array{
 *   slot: string,
 *   theme_mod: string,
 *   persisted: bool,
 *   previous_template_id: int|null,
 *   template_id: int
 * }
 */
function ow_apply_library_template_locked(string $slot, string $theme_mod, int $template_id): array
{
    // @mago-expect analysis:mixed-assignment
    $previous_raw = get_theme_mod($theme_mod, default_value: 0);
    $previous = is_int($previous_raw) || is_string($previous_raw) && preg_match('/^\d+$/', $previous_raw) === 1
        ? (int) $previous_raw
        : null;

    $shape = [
        'slot' => $slot,
        'theme_mod' => $theme_mod,
        'previous_template_id' => $previous,
        'template_id' => $template_id,
    ];

    if ($previous === $template_id) {
        return $shape + ['persisted' => false];
    }
    if ($template_id === 0) {
        remove_theme_mod($theme_mod);
        return $shape + ['persisted' => true];
    }
    set_theme_mod($theme_mod, $template_id);
    return $shape + ['persisted' => true];
}
