<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Astra theme + addon presence and capability snapshot.
 */

wp_register_ability('novamira/astra-check-setup', [
    'label' => __('Check Astra Setup', domain: 'novamira-pro'),
    'description' => __(
        'Diagnostic snapshot of the Astra install: theme active flag + version + min supported, Astra Pro Addon presence + version + 20-module status map, active color palette, storage keys, CPT entity flags (Advanced Hooks, Advanced Headers), third-party plugin integrations (WooCommerce, EDD, LearnDash, LifterLMS), and current user capability. Call once at the start of any Astra session. Branch on theme.active, addon.active, and modules.<slug> to gate subsequent ability calls.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
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
                    'is_stylesheet' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'is_stylesheet', 'version', 'min_supported', 'min_satisfied'],
            ],
            'addon' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'ext_min_ver_ok' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied', 'ext_min_ver_ok'],
            ],
            'modules' => [
                'type' => 'object',
                'description' => 'Map of Astra Pro Addon module slug to active boolean. Gate calls into addon-scoped abilities on the corresponding module flag: `advanced-hooks` for astra-{list,get,create,edit,delete}-hook; `advanced-headers` for astra-{list,get,create,edit,delete}-page-header; `woocommerce` for astra-{get,edit}-woocommerce-settings.',
            ],
            'storage' => [
                'type' => 'object',
                'properties' => [
                    'option_key' => ['type' => 'string'],
                    'palette_option_key' => ['type' => 'string'],
                    'settings_size_bytes' => ['type' => 'integer'],
                ],
                'required' => ['option_key', 'palette_option_key', 'settings_size_bytes'],
            ],
            'palette' => [
                'type' => ['object', 'null'],
                'properties' => [
                    'active_id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'colors' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'entities' => [
                'type' => 'object',
                'properties' => [
                    'astra-advanced-hook' => ['type' => 'boolean'],
                    'astra_adv_header' => ['type' => 'boolean'],
                ],
                'required' => ['astra-advanced-hook', 'astra_adv_header'],
            ],
            'integrations' => [
                'type' => 'object',
                'properties' => [
                    'woocommerce' => ['type' => 'boolean'],
                    'edd' => ['type' => 'boolean'],
                    'learndash' => ['type' => 'boolean'],
                    'lifterlms' => ['type' => 'boolean'],
                ],
                'required' => ['woocommerce', 'edd', 'learndash', 'lifterlms'],
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => ['theme', 'addon', 'modules', 'storage', 'entities', 'integrations', 'current_user_can_manage'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once at the start of any Astra-touching session. If theme.active=false or theme.min_satisfied=false, stop and surface the gap — no other astra-* abilities will work. Use addon.active to gate Astra Pro Addon abilities (Advanced Hooks, Advanced Headers, WooCommerce styling). Use modules.<slug>=true to gate module-specific abilities (e.g. modules.advanced-hooks for astra-list-hooks). Response is compact by design; call astra-get-settings / astra-get-defaults for actual content.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     theme: array{active: bool, is_stylesheet: bool, version: string, min_supported: string, min_satisfied: bool},
 *     addon: array{active: bool, version: string, min_supported: string, min_satisfied: bool, ext_min_ver_ok: bool},
 *     modules: array<string, bool>,
 *     storage: array{option_key: string, palette_option_key: string, settings_size_bytes: int},
 *     palette: array{active_id: string, name: string, colors: list<string>}|null,
 *     entities: array{'astra-advanced-hook': bool, 'astra_adv_header': bool},
 *     integrations: array{woocommerce: bool, edd: bool, learndash: bool, lifterlms: bool},
 *     current_user_can_manage: bool,
 * }
 */
function astra_check_setup(): array
{
    $theme_version = astra_theme_version();
    $addon_version = astra_addon_version();
    $stylesheet = get_stylesheet();
    $template = get_template();
    $is_active = $theme_version !== '' && ($stylesheet === 'astra' || $template === 'astra');

    // Settings storage size — allows agents to surface byte-cap warnings before
    // pulling the full bag.
    /** @var mixed $raw_settings */
    $raw_settings = get_option('astra-settings', []);
    $settings_size = strlen(serialize(is_array($raw_settings) ? $raw_settings : []));

    // Addon ext_min_ver_ok: the addon declares a minimum theme version it requires;
    // we check whether the installed addon meets the threshold it ships in its own constant.
    $ext_min_ver_ok = true;
    if ($addon_version !== '' && defined('ASTRA_EXT_MIN_VER')) {
        $ext_min_ver_ok = version_compare($addon_version, (string) constant('ASTRA_EXT_MIN_VER'), operator: '>=');
    }

    return [
        'theme' => [
            'active' => $is_active,
            'is_stylesheet' => $stylesheet === 'astra',
            'version' => $theme_version,
            'min_supported' => NOVAMIRA_PRO_ASTRA_THEME_MIN_VERSION,
            'min_satisfied' => \Novamira\Pro\Abilities\Astra\astra_theme_available() && $is_active,
        ],
        'addon' => [
            'active' => $addon_version !== '' && class_exists('Astra_Ext_Extension'),
            'version' => $addon_version,
            'min_supported' => NOVAMIRA_PRO_ASTRA_ADDON_MIN_VERSION,
            'min_satisfied' => \Novamira\Pro\Abilities\Astra\astra_addon_available(),
            'ext_min_ver_ok' => $ext_min_ver_ok,
        ],
        'modules' => astra_active_module_map(),
        'storage' => [
            'option_key' => 'astra-settings',
            'palette_option_key' => 'astra-color-palettes',
            'settings_size_bytes' => $settings_size,
        ],
        'palette' => $is_active ? astra_cs_read_active_palette() : null,
        'entities' => [
            'astra-advanced-hook' => post_type_exists('astra-advanced-hook'),
            'astra_adv_header' => post_type_exists('astra_adv_header'),
        ],
        'integrations' => [
            'woocommerce' => class_exists('WooCommerce'),
            'edd' => defined('EDD_VERSION'),
            'learndash' => defined('LEARNDASH_VERSION'),
            'lifterlms' => defined('LLMS_VERSION'),
        ],
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}

/**
 * Extract a list of colour strings from a single palette entry array.
 *
 * @param array<mixed> $entry
 * @return list<string>
 */
function astra_cs_extract_entry_colors(array $entry): array
{
    /** @var mixed $colors_raw */
    $colors_raw = $entry['colors'] ?? [];
    if (!is_array($colors_raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $c */
    foreach ($colors_raw as $c) {
        if (!is_string($c)) {
            continue;
        }
        $out[] = $c;
    }
    return $out;
}

/**
 * Read fallback palette colours from the theme helper when the stored entry
 * cannot be parsed.
 *
 * @return list<string>
 */
function astra_cs_fallback_palette_colors(): array
{
    if (!function_exists('astra_get_palette_colors')) {
        return [];
    }
    $fallback = astra_get_palette_colors();
    // @mago-expect analysis:redundant-type-comparison
    // @mago-expect analysis:impossible-condition
    if (!is_array($fallback)) {
        return [];
    }
    $out = [];
    /** @var mixed $c */
    foreach ($fallback as $c) {
        if (!is_string($c)) {
            continue;
        }
        $out[] = $c;
    }
    return $out;
}

/**
 * Read the active palette entry from `astra-color-palettes`. Only called when
 * the theme is active (the caller gates on $is_active). Reads defensively
 * because the option format may differ across Astra releases.
 *
 * @return array{active_id: string, name: string, colors: list<string>}
 */
function astra_cs_read_active_palette(): array
{
    /** @var mixed $raw */
    $raw = get_option('astra-color-palettes', []);
    if (!is_array($raw)) {
        return ['active_id' => '', 'name' => '', 'colors' => []];
    }

    // Determine the active palette id — Astra stores it under 'currentPalette'.
    $active_id = '';
    if (isset($raw['currentPalette']) && is_string($raw['currentPalette'])) {
        $active_id = $raw['currentPalette'];
    }

    $pal_name = $active_id;
    $pal_colors = [];

    if ($active_id !== '' && isset($raw[$active_id]) && is_array($raw[$active_id])) {
        $entry = $raw[$active_id];
        if (isset($entry['name']) && is_string($entry['name'])) {
            $pal_name = $entry['name'];
        }
        $pal_colors = astra_cs_extract_entry_colors($entry);
    }

    if ($pal_colors === []) {
        $pal_colors = astra_cs_fallback_palette_colors();
    }

    return ['active_id' => $active_id, 'name' => $pal_name, 'colors' => $pal_colors];
}
