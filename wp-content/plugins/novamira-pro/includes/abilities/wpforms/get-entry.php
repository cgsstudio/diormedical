<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const NVP_WPFORMS_GET_ENTRY_BYTES_DEFAULT = 4096;

const NVP_WPFORMS_GET_ENTRY_BYTES_MAX = 65_536;

wp_register_ability('novamira/wpforms-get-entry', [
    'label' => __('Get WPForms Entry', domain: 'novamira-pro'),
    'description' => __(
        'Return a single submission (entry) with its decoded field values. Required: entry_id. Optional: max_field_bytes (default 4096) caps the per-field value size so a textarea/richtext/file-upload value never blows the agent\'s context — capped fields surface a value_truncated flag plus the original byte length. include_meta=true also returns the entry.meta blob (UA, referer, payment data on payment forms). Pro only.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 256,
                'maximum' => NVP_WPFORMS_GET_ENTRY_BYTES_MAX,
                'default' => NVP_WPFORMS_GET_ENTRY_BYTES_DEFAULT,
                'description' => 'Per-field byte cap; default 4 KiB.',
            ],
            'include_meta' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include the entry.meta blob (user agent, referer, payment data, custom meta).',
            ],
        ],
        'required' => ['entry_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry' => ['type' => 'object'],
            'fields' => ['type' => 'object'],
            'meta' => ['type' => ['object', 'null']],
            'truncated_fields' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ],
        'required' => ['entry', 'fields', 'meta', 'truncated_fields'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_get_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Fields are returned as the JSON map WPForms stores (keyed by field id) with each entry containing name / value / type / id. truncated_fields lists the field ids whose value was capped — the field entry carries value_truncated=true and value_original_bytes=N so the agent knows the size of the omitted tail.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id: int, max_field_bytes?: int, include_meta?: bool} $input
 * @return array{entry: array<string, mixed>, fields: array<array-key, mixed>, meta: array<array-key, mixed>|null, truncated_fields: list<int>}|WP_Error
 */
function wpforms_get_entry(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    if (!wpforms_pro_active()) {
        return wpforms_pro_required('entries');
    }

    $entry = wpforms_entry_or_error($input['entry_id']);
    if ($entry instanceof WP_Error) {
        return $entry;
    }
    $cap_error = wpforms_capability_or_error('wpforms_view_entries', (int) ($entry->entry_id ?? 0));
    if ($cap_error !== null) {
        return $cap_error;
    }

    $max_bytes = isset($input['max_field_bytes'])
        ? max(256, min(NVP_WPFORMS_GET_ENTRY_BYTES_MAX, (int) $input['max_field_bytes']))
        : NVP_WPFORMS_GET_ENTRY_BYTES_DEFAULT;
    $include_meta = ($input['include_meta'] ?? false) === true;

    $payload = wpforms_entry_full($entry, $max_bytes);
    if (!$include_meta) {
        // Drop the meta block at the callsite so wpforms_entry_full stays
        // boolean-flag-free (per mago's no-boolean-flag-parameter rule).
        $payload['meta'] = null;
    }
    return $payload;
}
