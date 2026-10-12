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
 * Ability: deactivate an Astra Pro Addon module.
 */

wp_register_ability('novamira/astra-disable-module', [
    'label' => __('Disable Astra Module', domain: 'novamira-pro'),
    'description' => __(
        'Deactivates an Astra Pro Addon module by removing its slug from `_astra_ext_enabled_extensions`. For CPT-bearing modules (advanced-hooks, advanced-headers) with existing posts, the ability returns a 412 confirmation_required error and reports the post count in orphaned_posts — pass confirm=true to proceed anyway (posts are NOT deleted, only the module is disabled). Idempotent: disabling an already-disabled module is a no-op.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Module slug to deactivate.',
            ],
            'confirm' => [
                'type' => 'boolean',
                'description' => 'When true, bypass the orphaned-posts confirmation guard for CPT-bearing modules. Defaults to false.',
                'default' => false,
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'was_active' => ['type' => 'boolean'],
            'is_active' => ['type' => 'boolean'],
            'orphaned_posts' => [
                'type' => ['integer', 'null'],
                'description' => 'Number of published+draft CPT posts that will become orphaned. null for modules without a CPT.',
            ],
        ],
        'required' => ['slug', 'was_active', 'is_active', 'orphaned_posts'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_disable_module',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Deactivates an Astra Pro module. For advanced-hooks and advanced-headers, first check orphaned_posts — if > 0 you MUST surface the count to the user and ask for confirmation before passing confirm=true. Disabling does not delete CPT posts; they become inaccessible until the module is re-enabled.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string, confirm?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function astra_disable_module(array $input): array|WP_Error
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

    $confirm = astra_coerce_bool($input['confirm'] ?? false);

    // ast_cpt_for_module is a pure match expression — no DB access, safe outside lock.
    $cpt_slug = ast_cpt_for_module($slug);

    $was_active = false;
    $is_active = false;
    $orphaned_posts = null;
    /** @var WP_Error|null $early_error */
    $early_error = null;

    astra_with_extensions_lock(static function () use (
        $slug,
        $confirm,
        $cpt_slug,
        &$was_active,
        &$is_active,
        &$orphaned_posts,
        &$early_error,
    ): void {
        $was_active_inner = astra_module_active($slug);
        $orphaned_count = $cpt_slug !== null ? ast_count_cpt_posts($cpt_slug) : null;
        $orphaned_posts = $orphaned_count;

        if ($orphaned_count !== null && $orphaned_count > 0 && !$confirm) {
            $early_error = new WP_Error(
                'astra_confirmation_required',
                sprintf(
                    'Disabling "%s" will orphan %d post(s) of type "%s". Pass confirm=true to proceed.',
                    $slug,
                    $orphaned_count,
                    $cpt_slug ?? '',
                ),
                ['status' => 412],
            );
            return;
        }

        if ($was_active_inner) {
            ast_deactivate_module_extension($slug);
        }

        $was_active = $was_active_inner;
        $is_active = astra_module_active($slug);
    });

    if ($early_error !== null) {
        return $early_error;
    }

    return [
        'slug' => $slug,
        'was_active' => $was_active,
        'is_active' => $is_active,
        'orphaned_posts' => $orphaned_posts,
    ];
}

/**
 * Return the CPT slug for a CPT-bearing module, or null for others.
 */
function ast_cpt_for_module(string $slug): ?string
{
    return match ($slug) {
        'advanced-hooks' => 'astra-advanced-hook',
        'advanced-headers' => 'astra_adv_header',
        default => null,
    };
}

/**
 * Count published + draft posts of a given post type.
 */
function ast_count_cpt_posts(string $cpt_slug): int
{
    $counts = wp_count_posts($cpt_slug);
    $publish = (int) ($counts->publish ?? 0);
    $draft = (int) ($counts->draft ?? 0);
    return $publish + $draft;
}

/**
 * Remove a module slug from the `_astra_ext_enabled_extensions` option.
 */
function ast_deactivate_module_extension(string $slug): void
{
    if (!class_exists('Astra_Admin_Helper')) {
        return;
    }
    /** @var mixed $enabled_raw */
    $enabled_raw = \Astra_Admin_Helper::get_admin_settings_option('_astra_ext_enabled_extensions', false, []);
    $enabled = is_array($enabled_raw) ? $enabled_raw : [];
    unset($enabled[$slug]);
    \Astra_Admin_Helper::update_admin_settings_option('_astra_ext_enabled_extensions', $enabled, false);
}
