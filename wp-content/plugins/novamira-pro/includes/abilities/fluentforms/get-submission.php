<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-get-submission', [
    'label' => __('Get a Fluent Forms Submission', domain: 'novamira-pro'),
    'description' => __(
        'Fetch one Fluent Forms submission by id. Always returns the compact row PLUS the decoded `response` mapped to friendly triples [{field_name, label, value, truncated?, full_size?}]. When Pro is loaded AND the submission has payment data, the `payment` block surfaces (status, method, type, currency, total, paid) — read-only. Optional: `fields` (whitelist — only return these field names in response[]); `max_field_bytes` (per-field byte cap, default 4 KiB, capped at 64 KiB). Returns fluentforms_submission_not_found (404) for missing ids.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'submission_id' => ['type' => 'integer', 'minimum' => 1],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional whitelist of field_name values to include in response[]. Default: all.',
                'default' => [],
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 65_536,
                'description' => 'Per-field truncation cap in bytes. Default 4096.',
                'default' => 4096,
            ],
        ],
        'required' => ['submission_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'status' => ['type' => 'string'],
            'user_id' => ['type' => ['integer', 'null']],
            'payment_status' => ['type' => ['string', 'null']],
            'payment_method' => ['type' => ['string', 'null']],
            'currency' => ['type' => ['string', 'null']],
            'payment_total' => ['type' => ['integer', 'null']],
            'source_url' => ['type' => 'string'],
            'ip' => ['type' => 'string'],
            'browser' => ['type' => 'string'],
            'device' => ['type' => 'string'],
            'country' => ['type' => 'string'],
            'is_favourite' => ['type' => 'boolean'],
            'created_at' => ['type' => ['string', 'null']],
            'updated_at' => ['type' => ['string', 'null']],
            'response' => ['type' => 'array', 'items' => ['type' => 'object']],
            'payment' => ['type' => ['object', 'null']],
        ],
        'required' => [
            'id',
            'form_id',
            'status',
            'created_at',
            'updated_at',
            'source_url',
            'ip',
            'browser',
            'device',
            'country',
            'is_favourite',
            'response',
            'payment',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_submission',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use `fields` to fetch only the keys you care about — every response field counts against the agent context. Default max_field_bytes is 4 KiB per field; bump up only when handling textarea / repeater values that exceed that. Capability: fluentform_entries_viewer.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array<string, mixed>|WP_Error
 */
function fluentforms_get_submission(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $sub = ff_submission_or_error($input['submission_id'] ?? null);
    if ($sub instanceof WP_Error) {
        return $sub;
    }
    $cap = ff_capability_or_error('fluentform_entries_viewer', (int) $sub->form_id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $only = isset($input['fields']) && is_array($input['fields'])
        ? ff_normalize_field_names(array_values($input['fields']))
        : null;
    $max =
        isset($input['max_field_bytes']) && is_int($input['max_field_bytes']) && $input['max_field_bytes'] >= 1
            ? $input['max_field_bytes']
            : ff_default_max_field_bytes();

    return ff_submission_full($sub, $only, $max);
}

// ff_normalize_field_names lives in helpers.php since audit round 2 — it is
// shared with export-submissions, so the helpers file is its canonical home.
