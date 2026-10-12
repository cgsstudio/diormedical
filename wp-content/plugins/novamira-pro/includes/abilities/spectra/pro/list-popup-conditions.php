<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra\Pro;

use Novamira\Pro\Abilities\Spectra as Free;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-pro-list-popup-conditions', [
    'label' => __('List Spectra Pro Popup Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Read the Spectra Pro display-condition bundle for one popup: `trigger` (open trigger — click, scroll, exit-intent, time-delay), `trigger_delay` (seconds), `display_inclusions` (where the popup MAY appear — page targets), and `display_exclusions` (where it MUST NOT appear). All four keys are the meta rows the Spectra Pro popup extension (`Spectra_Pro_Popup_Builder::register_pro_meta()`) writes on the free `spectra-popup` CPT. Reading is safe on any popup — missing rows return empty defaults, mirroring the reader shape novamira/spectra-pro-set-popup-conditions writes.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'trigger' => ['type' => 'string'],
            'trigger_delay' => ['type' => 'integer'],
            // Spectra Pro stores display inclusions/exclusions as an
            // associative bundle ({rule, specific, specificText, ...}), not
            // a list — the shape is defined by BSF and passed through
            // verbatim. `type: object` matches; `additionalProperties: true`
            // avoids fighting a shape whose keys the extension owns.
            'display_inclusions' => ['type' => 'object', 'additionalProperties' => true],
            'display_exclusions' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['id', 'trigger', 'trigger_delay', 'display_inclusions', 'display_exclusions'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\Pro\\spectra_pro_list_popup_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the 4-key Pro display-condition bundle for a popup. Empty rows come back as sensible defaults.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_pro_list_popup_conditions(array $input): array|WP_Error
{
    if (!Free\spectra_pro_active()) {
        return Free\sp_pro_required();
    }

    $id = Free\spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = Free\sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    return [
        'id' => $post->ID,
        'trigger' => spectra_pro_read_string_meta($post->ID, SPECTRA_PRO_POPUP_META_TRIGGER),
        'trigger_delay' => spectra_pro_read_int_meta($post->ID, SPECTRA_PRO_POPUP_META_TRIGGER_DELAY),
        'display_inclusions' => spectra_pro_read_condition_meta($post->ID, SPECTRA_PRO_POPUP_META_DISPLAY_INCLUSIONS),
        'display_exclusions' => spectra_pro_read_condition_meta($post->ID, SPECTRA_PRO_POPUP_META_DISPLAY_EXCLUSIONS),
    ];
}

/**
 * Read a string post_meta, coercing anything non-string to the empty
 * string so the ability's output shape is stable regardless of the raw
 * meta value.
 */
function spectra_pro_read_string_meta(int $id, string $key): string
{
    /** @var mixed $value */
    $value = get_post_meta($id, $key, single: true);
    return is_string($value) ? $value : '';
}

/**
 * Read an integer post_meta.
 */
function spectra_pro_read_int_meta(int $id, string $key): int
{
    /** @var mixed $value */
    $value = get_post_meta($id, $key, single: true);
    return is_numeric($value) ? (int) $value : 0;
}

/**
 * Read a condition-bundle post_meta as a stdClass object, coercing
 * anything non-array to `{}` and casting associative arrays through
 * `(object)` so `json_encode` emits `{...}` (matching `type: object`
 * in the schema).
 */
function spectra_pro_read_condition_meta(int $id, string $key): \stdClass
{
    /** @var mixed $value */
    $value = get_post_meta($id, $key, single: true);
    if (!is_array($value)) {
        return new \stdClass();
    }
    return (object) $value;
}
