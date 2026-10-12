<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Kadence;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Kadence theme presence + capability snapshot.
 */

wp_register_ability('novamira/kadence-check-setup', [
    'label' => __('Check Kadence Setup', domain: 'novamira-pro'),
    'description' => __(
        'Diagnostic snapshot of the Kadence theme install: theme active flag + version + minimum supported, the storage type and option name Kadence is using (theme_mod vs option), the activation flag for the Kadence-introduced CPTs the integration touches (kadence_header, kadence_navigation, kadence_form), and the active palette name. Call once at the start of any session that will read or write Kadence theme settings, build a header/footer, or assemble an Advanced Form. Lean by design — does NOT echo the full settings or palette here; call kadence-get-settings / kadence-list-customizer-settings for actual content.',
        domain: 'novamira-pro',
    ),
    'category' => 'kadence',
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
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
            ],
            'storage' => [
                'type' => 'object',
                'description' => 'How Kadence persists the theme settings on this install. `type` is `theme_mod` by default; advanced users can switch to `option` via the `kadence_theme_option_type` filter (companion `kadence_theme_option_name` swaps the bag name). `option_name` is the WP option key under which the bag is stored (empty string when `theme_mod` because mods use a per-stylesheet WP-managed key).',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['theme_mod', 'option', '']],
                    'option_name' => ['type' => 'string'],
                ],
                'required' => ['type', 'option_name'],
            ],
            'entities' => [
                'type' => 'object',
                'description' => 'Activation flags for the Kadence-introduced CPTs the integration cares about. `kadence_header` for the Header Builder, `kadence_navigation` for menu-as-blocks, `kadence_form` for Advanced Forms. A flag is `true` when the post type is registered (the corresponding feature is available).',
                'properties' => [
                    'kadence_header' => ['type' => 'boolean'],
                    'kadence_navigation' => ['type' => 'boolean'],
                    'kadence_form' => ['type' => 'boolean'],
                ],
                'required' => ['kadence_header', 'kadence_navigation', 'kadence_form'],
            ],
            'palette' => [
                'type' => 'object',
                'description' => 'The active color palette identifier and its full color list. Slots not customised fall back to the default palette of the same name. When the theme is not active this block is null.',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Active palette identifier (the `active` key inside the `kadence_settings_palette` option JSON — typically the literal string `"palette"` for the default selection, or the swatch id for an alternate palette).',
                    ],
                    'colors' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Hex / rgb strings for every slot in the active palette. Kadence ships 9 user-editable slots plus extra accent / utility slots; the exact count varies by theme version. Slot indices 0..2 are typically brand / accent; 3..5 typography; 6..8 background tones — but the convention is theme-specific and not enforced.',
                    ],
                ],
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => ['theme', 'storage', 'entities', 'current_user_can_manage'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Kadence\\kadence_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once at the start of any Kadence-touching session. If `theme.active=false` or `theme.min_satisfied=false`, stop and surface the gap to the user — none of the other kadence-* abilities will return useful data. Use `entities.<name>=true` to gate workflows that need the corresponding CPT (`kadence_header` for the Header Builder, `kadence_form` for Advanced Forms) — those flows go through the standard `novamira/create-post` + `novamira/gutenberg-*` pipeline, not through a dedicated kadence-* ability. `storage.type` is informational — write paths (kadence-edit-settings) handle both theme_mod and option transparently.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     theme: array{active: bool, version: string, min_supported: string, min_satisfied: bool},
 *     storage: array{type: string, option_name: string},
 *     entities: array<string, bool>,
 *     palette: array{name: string, colors: list<string>}|null,
 *     current_user_can_manage: bool,
 * }
 */
function kadence_check_setup(): array
{
    $version = kadence_theme_version();
    $tags = kadence_theme_tags();

    $palette = null;
    if ($tags !== null) {
        $palette = kd_normalise_palette($tags->get_palette());
    }
    if ($palette === null) {
        $palette = ['name' => '', 'colors' => []];
    }

    // `active` checks BOTH that Kadence's PHP runtime is loaded AND that
    // it is the currently active WP stylesheet — once the theme files are
    // included in a request the constants stay defined until shutdown,
    // so a mid-request `switch_theme()` would otherwise leave `active`
    // truthy on the new theme.
    $stylesheet = get_stylesheet();
    $template = get_template();
    $is_active_theme = $stylesheet === 'kadence' || $template === 'kadence';

    return [
        'theme' => [
            'active' => $version !== '' && class_exists('Kadence\\Theme') && $is_active_theme,
            'version' => $version,
            'min_supported' => NOVAMIRA_PRO_KADENCE_THEME_MIN_VERSION,
            'min_satisfied' => kadence_theme_available() && $is_active_theme,
        ],
        'storage' => kd_storage_meta(),
        'entities' => kd_cpt_entities(),
        'palette' => $palette,
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}

/**
 * Shapes the raw palette returned by `kadence()->get_palette()` into a
 * stable `{name, colors[]}` record. Kadence's storage format is a JSON
 * string `{"palette": [{color, slug, name}, ...], "second-palette":
 * [...], ...}`; we surface a portable list so the agent does not have
 * to traverse the internal structure.
 *
 * Returns null when the input is unparseable.
 *
 * @return array{name: string, colors: list<string>}|null
 */
function kd_normalise_palette(string $palette_json): ?array
{
    if ($palette_json === '') {
        return null;
    }
    /** @var mixed $decoded */
    $decoded = json_decode($palette_json, associative: true);
    if (!is_array($decoded)) {
        return null;
    }

    /** @var mixed $colors_raw */
    $colors_raw = $decoded['palette'] ?? [];
    if (!is_array($colors_raw)) {
        return null;
    }

    $colors = [];
    /** @var mixed $entry */
    foreach ($colors_raw as $entry) {
        if (is_string($entry)) {
            $colors[] = $entry;
            continue;
        }
        if (is_array($entry) && isset($entry['color']) && is_string($entry['color'])) {
            $colors[] = $entry['color'];
        }
    }

    /** @var mixed $name_raw */
    $name_raw = $decoded['active'] ?? $decoded['name'] ?? '';
    $name = is_string($name_raw) ? $name_raw : '';

    return ['name' => $name, 'colors' => $colors];
}
