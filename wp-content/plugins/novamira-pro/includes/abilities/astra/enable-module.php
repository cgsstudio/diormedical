<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: activate an Astra Pro Addon module.
 */

wp_register_ability('novamira/astra-enable-module', [
    'label' => __('Enable Astra Module', domain: 'novamira-pro'),
    'description' => __(
        'Activates an Astra Pro Addon module via Astra_Ext_Extension::activate_extension(). Requires the Astra Pro Addon to be installed. The slug must be one of the 20 known module slugs (call astra-list-modules to enumerate them). CPT-bearing modules (advanced-hooks, advanced-headers) set restart_required=true — flush rewrite rules on the next request to register their custom post types properly. Idempotent: activating an already-active module is a no-op.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Module slug to activate (e.g. advanced-hooks, typography, woocommerce). Call astra-list-modules to see all valid slugs.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'was_active' => ['type' => 'boolean', 'description' => 'Module state before this call.'],
            'is_active' => ['type' => 'boolean', 'description' => 'Module state after this call.'],
            'restart_required' => [
                'type' => 'boolean',
                'description' => 'True for CPT-bearing modules that need rewrite-rule flush on the next request.',
            ],
        ],
        'required' => ['slug', 'was_active', 'is_active', 'restart_required'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_enable_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Activates an Astra Pro module. Requires Astra Pro Addon (check addon.active from astra-check-setup). When restart_required=true, inform the user that WordPress rewrite rules will be flushed on the next front-end request, which registers the new CPT properly. Activation is immediate and persistent.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_enable_module(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    if (!astra_addon_available()) {
        return astra_addon_required_error($input['slug']);
    }

    $slug = astra_strip_nul_bytes(trim($input['slug']));
    if ($slug === '') {
        return astra_invalid_input_error('slug must be a non-empty string.');
    }

    if (!in_array($slug, astra_module_slugs(), strict: true)) {
        return astra_invalid_input_error(sprintf(
            'Unknown module slug "%s". Call astra-list-modules for the valid list.',
            $slug,
        ));
    }

    $was_active = false;
    $is_active = false;

    astra_with_extensions_lock(static function () use ($slug, &$was_active, &$is_active): void {
        $was_active_inner = astra_module_active($slug);
        if (!$was_active_inner && class_exists('Astra_Ext_Extension')) {
            \Astra_Ext_Extension::activate_extension($slug, true);
        }
        $was_active = $was_active_inner;
        $is_active = astra_module_active($slug);
    });

    // CPT-bearing modules need a rewrite-rule flush to register their post types.
    $restart_required = in_array($slug, ['advanced-hooks', 'advanced-headers'], strict: true);

    return [
        'slug' => $slug,
        'was_active' => $was_active,
        'is_active' => $is_active,
        'restart_required' => $restart_required,
    ];
}
