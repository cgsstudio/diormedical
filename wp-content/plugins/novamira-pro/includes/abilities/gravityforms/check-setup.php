<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-check-setup', [
    'label' => __('Check Gravity Forms Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Gravity Forms environment: whether the plugin is active, its version, the field types this install can use, the WP capabilities that gate form/entry management, and the form count. Gravity Forms is a single-SKU paid plugin (no Lite/Pro split), so every form, entry, notification and confirmation surface is available whenever the plugin is active. Call this first before any other gravityforms ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
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
            'available_field_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'default_capabilities' => ['type' => 'array', 'items' => ['type' => 'string']],
            'form_count' => ['type' => 'integer'],
        ],
        'required' => [
            'active',
            'version',
            'available_field_types',
            'default_capabilities',
            'form_count',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, Gravity Forms is not active or is below the minimum version.) available_field_types is the authoritative list of types you may use in add-field / edit-field — picking a type not in this list will fail. Gravity Forms has no Lite/Pro split; every surface is available when the plugin is active.',
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
 *     available_field_types: list<string>,
 *     default_capabilities: list<string>,
 *     form_count: int,
 * }
 */
function gravityforms_check_setup(): array
{
    $detect = gravityforms_detect_setup();

    if (!$detect['active']) {
        return [
            'active' => false,
            'version' => null,
            'available_field_types' => [],
            'default_capabilities' => [],
            'form_count' => 0,
        ];
    }

    return [
        'active' => true,
        'version' => $detect['version'],
        'available_field_types' => gravityforms_available_field_types(),
        'default_capabilities' => gravityforms_default_capabilities(),
        'form_count' => gravityforms_count_forms(),
    ];
}

/**
 * Field-type slugs the install actually exposes. Reads from the live
 * GF_Fields registry so add-ons that register new field types (Gravity Forms
 * Quiz, Survey, Polls, etc.) are reflected without hard-coding.
 *
 * Sorted alphabetically for stable agent-facing output.
 *
 * @return list<string>
 */
function gravityforms_available_field_types(): array
{
    if (!gravityforms_min_runtime_available() || !class_exists('GF_Fields')) {
        return [];
    }
    $registry = \GF_Fields::get_all();
    $types = array_keys($registry);
    sort($types);
    return array_values($types);
}

/**
 * Canonical Gravity Forms capabilities. Used by check-setup so the agent
 * sees the cap surface up front and picks the right one when building
 * permission-aware UIs. List is hard-coded because GF does not expose a
 * machine-readable enumeration of its caps — the values mirror the
 * "GF Capabilities" admin matrix.
 *
 * @return list<string>
 */
function gravityforms_default_capabilities(): array
{
    return [
        'gravityforms_create_form',
        'gravityforms_edit_forms',
        'gravityforms_delete_forms',
        'gravityforms_view_entries',
        'gravityforms_edit_entries',
        'gravityforms_delete_entries',
        'gravityforms_view_settings',
        'gravityforms_edit_settings',
        'gravityforms_view_entry_notes',
        'gravityforms_edit_entry_notes',
        'gravityforms_export_entries',
        'gravityforms_preview_forms',
    ];
}
