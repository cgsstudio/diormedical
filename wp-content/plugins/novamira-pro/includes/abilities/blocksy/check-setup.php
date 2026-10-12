<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Blocksy theme + companion presence and capability snapshot.
 */

wp_register_ability('novamira/blocksy-check-setup', [
    'label' => __('Check Blocksy Setup', domain: 'novamira-pro'),
    'description' => __(
        'Diagnostic snapshot of the Blocksy install: theme active flag + version + minimum supported (2.1.47, the CompanionBridge floor), child-theme awareness, Blocksy Companion free/Pro presence + license state + plan, active companion extensions, theme_mods storage row (`theme_mods_<stylesheet>` — child themes have their own row), dynamic-CSS mode, pending database migrations, and current user capability. Call once at the start of any Blocksy session and branch on theme.active, theme.min_satisfied, and companion.* to gate subsequent ability calls.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'name' => ['type' => 'string'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'is_child_theme' => ['type' => 'boolean'],
                    'template' => ['type' => 'string'],
                    'stylesheet' => ['type' => 'string'],
                ],
                'required' => [
                    'active',
                    'name',
                    'version',
                    'min_supported',
                    'min_satisfied',
                    'is_child_theme',
                    'template',
                    'stylesheet',
                ],
            ],
            'companion' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'pro_code_present' => [
                        'type' => 'boolean',
                        'description' => 'Companion Pro code bundle is installed. Can be true while licensed=false (Pro zip present, license inactive).',
                    ],
                    'licensed' => ['type' => 'boolean'],
                    'plan' => ['type' => 'string'],
                ],
                'required' => [
                    'active',
                    'version',
                    'min_supported',
                    'min_satisfied',
                    'pro_code_present',
                    'licensed',
                    'plan',
                ],
            ],
            'extensions' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Slugs from the `blocksy_active_extensions` option (companion extensions the user activated).',
            ],
            'storage' => [
                'type' => 'object',
                'properties' => [
                    'mods_key' => [
                        'type' => 'string',
                        'description' => 'wp_options row holding every Blocksy customizer value for the ACTIVE theme. With a child theme this is theme_mods_<child-slug>; the parent row is dormant.',
                    ],
                    'mods_row_exists' => ['type' => 'boolean'],
                    'db_version' => ['type' => 'string'],
                    'pending_migrations' => ['type' => 'boolean'],
                    'dynamic_css_mode' => [
                        'type' => 'string',
                        'description' => 'Value of the dynamic_css_file theme_mod ("file" default: compiled CSS is written to wp-content/uploads/blocksy/css/global.css).',
                    ],
                ],
                'required' => ['mods_key', 'mods_row_exists', 'db_version', 'pending_migrations', 'dynamic_css_mode'],
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => ['theme', 'companion', 'extensions', 'storage', 'warnings', 'current_user_can_manage'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once at the start of any Blocksy-touching session. If theme.active=false or theme.min_satisfied=false, stop and surface the gap — no other blocksy-* abilities will work. companion.pro_code_present and companion.licensed can diverge; gate Pro-only surfaces (Content Blocks, dark mode, custom sidebars) on licensed=true. storage.mods_key tells you which theme_mods row is live (child themes have their own). Response is compact by design; use blocksy-list-options / blocksy-get-option for actual values.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Blocksy stores database versions in a per-stylesheet map, while older
 * installs may only have the legacy site-wide marker. The map entry for the
 * active stylesheet is authoritative when both forms exist because current
 * theme code updates that entry without rewriting the legacy marker.
 *
 * @return array{
 *     theme: array{active: bool, name: string, version: string, min_supported: string, min_satisfied: bool, is_child_theme: bool, template: string, stylesheet: string},
 *     companion: array{active: bool, version: string, min_supported: string, min_satisfied: bool, pro_code_present: bool, licensed: bool, plan: string},
 *     extensions: list<string>,
 *     storage: array{mods_key: string, mods_row_exists: bool, db_version: string, pending_migrations: bool, dynamic_css_mode: string},
 *     warnings: list<string>,
 *     current_user_can_manage: bool,
 * }
 */
function blocksy_check_setup(): array
{
    $theme_active = function_exists('blocksy_manager');
    $theme_version = bly_theme_version();
    $companion_active = bly_companion_active();
    $companion_version = bly_companion_version();

    /** @var mixed $db_versions */
    $db_versions = get_option('blocksy_db_versions', default_value: []);
    /** @var mixed $stylesheet */
    $stylesheet = get_option('stylesheet');
    $db_version = is_array($db_versions)
    && is_string($stylesheet)
    && isset($db_versions[$stylesheet])
    && is_string($db_versions[$stylesheet])
        ? $db_versions[$stylesheet]
        : (string) get_option('blocksy_db_version', default_value: '');
    $pending_migrations =
        $theme_active && $db_version !== '' && version_compare($db_version, $theme_version, operator: '<');

    $warnings = [];
    if ($theme_active && !$companion_active) {
        $warnings[] = 'Blocksy Companion is not active. Extensions, Content Blocks and the companion API surface are unavailable.';
    }
    if ($theme_active && $pending_migrations) {
        $warnings[] = sprintf(
            'The stored Blocksy database version (%s) is behind the theme version (%s) — database migrations are pending; theme option shapes may be in a transitional state.',
            $db_version,
            $theme_version,
        );
    }
    $min_companion = bly_theme_declared_min_companion();
    if (
        $theme_active
        && $companion_active
        && $min_companion !== ''
        && version_compare($companion_version, $min_companion, operator: '<')
    ) {
        $warnings[] = sprintf(
            'The theme declares "Blocksy Minimum Companion Version: %s" but the companion is %s — the companion silently refuses to boot features below its floor.',
            $min_companion,
            $companion_version,
        );
    }

    return [
        'theme' => [
            'active' => $theme_active,
            'name' => bly_theme_name(),
            'version' => $theme_version,
            'min_supported' => NOVAMIRA_PRO_BLOCKSY_THEME_MIN_VERSION,
            'min_satisfied' => blocksy_theme_available(),
            'is_child_theme' => is_child_theme(),
            'template' => get_template(),
            'stylesheet' => get_stylesheet(),
        ],
        'companion' => [
            'active' => $companion_active,
            'version' => $companion_version,
            'min_supported' => NOVAMIRA_PRO_BLOCKSY_COMPANION_MIN_VERSION,
            'min_satisfied' => blocksy_companion_available(),
            'pro_code_present' => bly_companion_pro_code_present(),
            'licensed' => bly_companion_licensed(),
            'plan' => bly_companion_plan(),
        ],
        'extensions' => bly_active_extension_slugs(),
        'storage' => [
            'mods_key' => bly_mods_key(),
            'mods_row_exists' => get_option(bly_mods_key()) !== false,
            'db_version' => $db_version,
            'pending_migrations' => $pending_migrations,
            'dynamic_css_mode' => (string) get_theme_mod('dynamic_css_file', default_value: 'file'),
        ],
        'warnings' => $warnings,
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}

/**
 * The companion floor the installed theme declares in its style.css header
 * (`Blocksy Minimum Companion Version`), or '' when unreadable. WP_Theme
 * does not expose custom headers, so read the file directly.
 */
function bly_theme_declared_min_companion(): string
{
    if (!function_exists('blocksy_manager')) {
        return '';
    }
    $style = get_template_directory() . '/style.css';
    if (!is_readable($style)) {
        return '';
    }
    $headers = get_file_data($style, ['min_companion' => 'Blocksy Minimum Companion Version']);
    return trim($headers['min_companion'] ?? '');
}

/**
 * Active companion extension slugs from the `blocksy_active_extensions`
 * option (flat list of lowercased slugs, e.g. ['trending']). Missing or
 * malformed rows read as "none active"; the list is capped at
 * BLY_MAX_EXTENSIONS so a corrupted row cannot balloon the response.
 *
 * @return list<string>
 */
function bly_active_extension_slugs(): array
{
    /** @var mixed $raw */
    $raw = get_option('blocksy_active_extensions', []);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $slug */
    foreach ($raw as $slug) {
        if (!is_string($slug) || $slug === '') {
            continue;
        }
        $out[] = $slug;
        if (count($out) >= BLY_MAX_EXTENSIONS) {
            break;
        }
    }
    return $out;
}
