<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-check-setup', [
    'label' => __('Check WPForms Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the WPForms environment: whether WPForms is active, its version, whether it is the Pro edition, which Pro-gated surfaces are available (entries CRUD and the premium field types need Pro), the list of field types this install can use, and the WP capabilities that gate form/entry management. Call this first before any other WPForms ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        // No inputs. Empty ARRAY (not object) keeps additionalProperties:false
        // safe — see the AIOSEO check-setup comment for the WP-core gotcha.
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'pro' => ['type' => 'boolean'],
            'supports_entries' => ['type' => 'boolean'],
            'supports_payments' => ['type' => 'boolean'],
            'supports_file_uploads' => ['type' => 'boolean'],
            'supports_signature' => ['type' => 'boolean'],
            'available_field_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'default_capabilities' => ['type' => 'array', 'items' => ['type' => 'string']],
            'form_count' => ['type' => 'integer'],
        ],
        'required' => [
            'active',
            'version',
            'pro',
            'supports_entries',
            'supports_payments',
            'supports_file_uploads',
            'supports_signature',
            'available_field_types',
            'default_capabilities',
            'form_count',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, WPForms is not active or is below the minimum version.) pro indicates the Pro edition; supports_entries, supports_payments, supports_file_uploads, supports_signature are Pro-only. available_field_types is the authoritative list of types you may use in add-field / edit-field — picking a type not in this list will fail.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     pro: bool,
 *     supports_entries: bool,
 *     supports_payments: bool,
 *     supports_file_uploads: bool,
 *     supports_signature: bool,
 *     available_field_types: list<string>,
 *     default_capabilities: list<string>,
 *     form_count: int,
 * }
 */
function wpforms_check_setup(): array
{
    $detect = wpforms_detect_setup();

    if (!$detect['active']) {
        return [
            'active' => false,
            'version' => null,
            'pro' => false,
            'supports_entries' => false,
            'supports_payments' => false,
            'supports_file_uploads' => false,
            'supports_signature' => false,
            'available_field_types' => [],
            'default_capabilities' => [],
            'form_count' => 0,
        ];
    }

    return [
        'active' => true,
        'version' => $detect['version'],
        'pro' => $detect['pro'],
        // Entries, payments, file uploads and signature are all Pro-only surfaces.
        // Reported individually so future fine-grained Pro module gating (e.g.
        // payment add-on disabled) can return them independently.
        'supports_entries' => $detect['pro'],
        'supports_payments' => $detect['pro'],
        'supports_file_uploads' => $detect['pro'],
        'supports_signature' => $detect['pro'],
        'available_field_types' => wpforms_available_field_types(),
        'default_capabilities' => [
            'create_forms',
            'view_forms',
            'edit_forms',
            'edit_others_forms',
            'delete_forms',
            'delete_others_forms',
            'view_entries',
            'view_others_entries',
            'edit_entries',
            'edit_others_entries',
            'delete_entries',
            'delete_others_entries',
        ],
        'form_count' => wpforms_count_forms(),
    ];
}

/**
 * Count of registered WPForms forms (all statuses except trash/auto-draft).
 * Reported by check-setup so an agent immediately knows whether the site has
 * forms to discover before calling list-forms.
 */
function wpforms_count_forms(): int
{
    $counts = wp_count_posts('wpforms');
    $total = 0;
    foreach (['publish', 'draft', 'pending', 'private'] as $status) {
        /** @var mixed $value */
        $value = $counts->{$status} ?? null;
        if (!is_numeric($value)) {
            continue;
        }
        $total += (int) $value;
    }
    return $total;
}
