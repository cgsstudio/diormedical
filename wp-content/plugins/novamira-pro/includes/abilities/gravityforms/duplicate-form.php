<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-duplicate-form', [
    'label' => __('Duplicate Gravity Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Duplicate an existing Gravity Forms form. Required: form_id. Optional: title (defaults to "<original title> - Copy 1" — GF auto-bumps the suffix on collision) and status (defaults to "inactive" so the duplicate does not silently start accepting submissions). The new form copies fields, settings, notifications, and confirmations from the source. Entries are NOT copied.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Source Gravity Forms form id.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Title for the duplicate. Defaults to the GF auto-suffixed title ("<original> - Copy 1").',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive'],
                'default' => 'inactive',
                'description' => 'Initial activation state. Default is inactive so the copy does not silently accept submissions.',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'source_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_updated' => ['type' => 'string'],
            'entry_count' => ['type' => 'integer'],
        ],
        'required' => ['id', 'source_id', 'title', 'status', 'date_created', 'date_updated', 'entry_count'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_duplicate_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Copies the full form definition (fields + settings + notifications + confirmations). Entries are NOT copied. New form starts as inactive unless status is overridden so the duplicate does not silently accept submissions.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{form_id: int, title?: string, status?: string} $input
 * @return array{id: int, source_id: int, title: string, status: string, date_created: string, date_updated: string, entry_count: int}|WP_Error
 */
function gravityforms_duplicate_form(array $input): array|WP_Error
{
    $source = gravityforms_form_or_error((int) $input['form_id']);
    if ($source instanceof WP_Error) {
        return $source;
    }
    $cap_error = gravityforms_capability_or_error('gravityforms_create_form');
    if ($cap_error !== null) {
        return $cap_error;
    }

    $source_id = (int) $source['id'];

    $new_id = \GFAPI::duplicate_form($source_id);
    if ($new_id instanceof WP_Error) {
        return $new_id;
    }
    if ($new_id <= 0) {
        return gravityforms_operation_failed('GFAPI::duplicate_form returned no form id.');
    }

    $copy = gravityforms_apply_duplicate_overrides($new_id, $input);
    if ($copy instanceof WP_Error) {
        return $copy;
    }

    $row = gravityforms_form_row($copy);
    return [
        'id' => $row['id'],
        'source_id' => $source_id,
        'title' => $row['title'],
        'status' => $row['status'],
        'date_created' => $row['date_created'],
        'date_updated' => $row['date_updated'],
        'entry_count' => $row['entry_count'],
    ];
}

/**
 * Re-fetch the duplicated form and apply optional title / status overrides
 * on top. Returns the (possibly re-read) form array or a WP_Error.
 *
 * @param array{form_id?: int, title?: string, status?: string} $input
 * @return array<string, mixed>|WP_Error
 */
function gravityforms_apply_duplicate_overrides(int $new_id, array $input): array|WP_Error
{
    $copy = \GFAPI::get_form($new_id);
    if ($copy === false) {
        return gravityforms_form_not_found($new_id);
    }

    $patched = $copy;
    $touched = false;
    if (isset($input['title'])) {
        $title = trim($input['title']);
        if ($title !== '') {
            $patched['title'] = $title;
            $touched = true;
        }
    }
    $is_active = ($input['status'] ?? 'inactive') === 'active' ? 1 : 0;
    if ((int) ($patched['is_active'] ?? 1) !== $is_active) {
        $patched['is_active'] = $is_active;
        $touched = true;
    }
    if (!$touched) {
        return $copy;
    }

    $result = \GFAPI::update_form($patched, $new_id);
    if ($result instanceof WP_Error) {
        return $result;
    }
    $refreshed = \GFAPI::get_form($new_id);
    return $refreshed === false ? $copy : $refreshed;
}
