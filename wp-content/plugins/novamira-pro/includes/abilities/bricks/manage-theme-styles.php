<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

/**
 * Abilities: Bricks Theme Styles — list / get / create / edit / delete.
 * Theme styles set element-type defaults site-wide (or per condition).
 * Stored as keyed object {id: {label, settings, ...}}.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-theme-styles', [
    'label' => __('List Bricks Theme Styles', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Bricks theme style defined on this site. Each entry includes id, label, conditions (extracted from settings.conditions for quick scanning) and `active` — true when the style has at least one condition, which is what Bricks requires to consider it (a style without conditions never applies). Fetch full settings via bricks-get-theme-style.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'include_settings' => [
                'type' => 'boolean',
                'description' => 'Include each style\'s full settings in the list. Default false returns id, label, and conditions only; for one style\'s full settings use bricks-get-theme-style.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_styles' => ['type' => 'array'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_theme_styles',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_theme_styles(array $input): array
{
    $include_settings = isset($input['include_settings']) && $input['include_settings'] === true;

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_THEME_STYLES, []);
    $store = is_array($raw) ? $raw : [];

    $items = [];
    /** @var mixed $id */
    /** @var mixed $entry */
    foreach ($store as $id => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $settings = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
        $conditions_group = is_array($settings['conditions'] ?? null) ? $settings['conditions'] : [];
        $conditions = is_array($conditions_group['conditions'] ?? null) ? $conditions_group['conditions'] : null;
        $item = [
            // From an array key: an all-digit id comes back as an int, and the
            // schema (and every ability that takes this id back) says string.
            'id' => (string) $id,
            'label' => $entry['label'] ?? '',
            'conditions' => $conditions,
            'active' => ts_is_active($settings),
        ];
        if ($include_settings) {
            $item['settings'] = $settings;
        }
        $items[] = $item;
    }

    return ['theme_styles' => $items];
}

wp_register_ability('novamira/bricks-get-theme-style', [
    'label' => __('Get Bricks Theme Style', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Bricks theme style by its id. Returns the full entry including id, label, and complete settings (per-element-type defaults plus conditions). Note: conditions are stored in settings.conditions.conditions (the outer key is the Bricks control group label; the inner array is the repeater).',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the theme style to read.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_style' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_theme_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_theme_style(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_THEME_STYLES, []);
    $store = is_array($raw) ? $raw : [];

    if (!array_key_exists($id, $store) || !is_array($store[$id])) {
        return bricks_error('not_found', sprintf('Theme style "%s" not found.', $id));
    }

    return ['theme_style' => array_merge(['id' => $id], $store[$id])];
}

wp_register_ability('novamira/bricks-list-theme-style-schema', [
    'label' => __('List Bricks Theme Style Schema', domain: 'novamira-pro'),
    'description' => __(
        'Lists the theme-style groups this Bricks registers and the setting keys each group accepts, so a style can be written correctly on a site that has none to copy from. Theme-style settings only take effect when they are nested under a group Bricks knows (typography, general, container, button, …) with keys that group registers: anything else is stored and silently ignored, and the write still verifies. Call this before create-theme-style or edit-theme-style on an unfamiliar group; pass group to get one group\'s keys with their control types instead of the whole list.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => [
                'type' => 'string',
                'description' => 'One group key. Omit for every group with its key count.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'groups' => [
                'type' => 'array',
                'description' => 'Every registered group with how many keys it accepts. Returned when group is omitted.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'group' => ['type' => 'string'],
                        'setting_count' => ['type' => 'integer'],
                    ],
                    'required' => ['group', 'setting_count'],
                ],
            ],
            'group' => ['type' => 'string', 'description' => 'The requested group, when one was passed.'],
            'settings' => [
                'type' => 'array',
                'description' => 'The keys that group accepts, with the control type of each. Returned when group is passed.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'type', 'label'],
                ],
            ],
            'example' => ['type' => 'string', 'description' => 'The nesting a write must use.'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_theme_style_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
        'instructions' => 'Read this before writing a theme-style group you have not written before, especially on a site with no existing style to copy. A key this does not list is stored but produces no CSS.',
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_list_theme_style_schema(array $input): array|WP_Error
{
    $groups = ts_group_keys();
    if ($groups === []) {
        return bricks_error(
            'unavailable',
            message: 'The Bricks theme-style controls are not loaded, so the schema cannot be read. Check the theme with novamira/bricks-check-setup and retry.',
        );
    }

    /** @var mixed $requested */
    $requested = $input['group'] ?? null;
    if (!is_string($requested) || $requested === '') {
        $rows = [];
        foreach (array_keys($groups) as $group) {
            $rows[] = ['group' => $group, 'setting_count' => count(ts_group_setting_keys($group))];
        }

        return [
            'groups' => $rows,
            'example' => 'Settings nest one level: {"typography": {"typographyBody": {"font-size": "18px"}}}. Pass group to see the keys one group accepts.',
        ];
    }

    if (!isset($groups[$requested])) {
        // 400, not the map's 404: `group` filters a listing that exists and
        // answers without it. The addressed resource is the schema, and the
        // schema is there — it is the filter VALUE that is wrong.
        return bricks_error(
            'not_found',
            sprintf(
                'Theme-style group "%s" is not registered by this Bricks. Call novamira/bricks-list-theme-style-schema without a group for the ones that are.',
                $requested,
            ),
            status: 400,
        );
    }

    $settings = [];
    foreach (array_keys(ts_group_setting_keys($requested)) as $key) {
        $control = ts_group_control($requested, $key);
        $settings[] = [
            'key' => $key,
            'type' => (string) ($control['type'] ?? 'unknown'),
            'label' => (string) ($control['label'] ?? $key),
        ];
    }

    return [
        'group' => $requested,
        'settings' => $settings,
        'example' => sprintf(
            'Settings nest one level: {"%s": {"<key from settings above>": <value for its control type>}}.',
            $requested,
        ),
    ];
}

/**
 * One control definition inside a theme-style group.
 *
 * @return array<array-key, mixed>
 */
function ts_group_control(string $group, string $key): array
{
    if (!class_exists(\Bricks\Theme_Styles::class)) {
        return [];
    }
    if (!is_array(\Bricks\Theme_Styles::$controls[$group][$key] ?? null)) {
        return [];
    }

    return \Bricks\Theme_Styles::$controls[$group][$key];
}

wp_register_ability('novamira/bricks-create-theme-style', [
    'label' => __('Create Bricks Theme Style', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Bricks theme style. Provide a label (required), an optional settings object (per-element-type defaults), and optional conditions array (same shape as bricks-set-template-conditions). The id is auto-generated with a "ts-" prefix. When conditions is passed, it is embedded under settings.conditions. Conditions arriving nested inside settings (settings.conditions.conditions) are validated and normalized the same way; when both routes are supplied, the dedicated conditions input wins. The response includes an `active` flag — Bricks only activates a theme style that has at least one condition, so creating one without conditions returns active: false plus a warning. Settings are grouped by Bricks theme-style group: site-wide keys live in `general` (e.g. `general.siteBackground` = the site background, which Bricks compiles to `html {background: …}` PLUS `body {background: none}` — the body itself is left transparent, so custom CSS that targets `body` competes with that reset; see the bricks-element-gotchas skill), `typography` (`typography.typographyBody`, `typography.typographyHeadings`, `typography.typographyHeadingH1`…`H6` — each a typography object whose `font-family` is a BARE family name such as "Lato", never var() or a comma stack; put fallbacks in `fallback`), `colors` (`colors.colorPrimary`, …) and `css` (`css.stylesheet` = site-wide custom CSS, the only raw-CSS key a theme style has). Per-element groups (`section`, `container`, `block`, `heading`, `button`, `image`, …) hold that element\'s defaults; layout primitives use dimensional keys WITHOUT underscore — `width`, `widthMin`, `widthMax`, `padding`, `margin` — even though the equivalent element-level shared controls are `_padding`/`_margin`/`_width`; the flex/layout keys (`_display`, `_direction`, `_justifyContent`, `_alignItems`, `_columnGap`, `_rowGap`) keep the underscore in both contexts. Typed controls first; `css.stylesheet` only as last resort.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => [
                'type' => 'string',
                'description' => 'Display label for the theme style. Required.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Settings keyed by Bricks theme-style group: per-element groups (e.g. {"section": {...}, "container": {...}, "heading": {...}}) plus the site-wide groups `general` (`siteBackground` — compiled to `html {background: …}` plus `body {background: none}`, so the body is left transparent), `typography` (`typographyBody`, `typographyHeadings`, `typographyHeadingH1`…`H6` — typography objects whose `font-family` is a bare family name, never var() or a comma stack), `colors` (`colorPrimary`, …) and `css` (`stylesheet` = site-wide custom CSS). Layout primitives (section/container/block): use unprefixed dimensional keys `width`/`widthMin`/`widthMax`/`padding`/`margin` and prefixed flex keys `_display`/`_direction`/`_justifyContent`/`_alignItems`/`_columnGap`/`_rowGap`. For non-layout elements (heading, text-basic, button, image, …) the keys mirror the element\'s own control schema — discover via `bricks-list-elements element=<name>`. Prefer typed controls over raw CSS; reserve `css.stylesheet` for cases no typed control covers (complex selectors, pseudo-elements, container queries). Optional.',
            ],
            'conditions' => [
                'type' => 'array',
                'description' => 'Conditions array controlling where this style applies. When provided, embedded under settings.conditions. Optional, but a theme style without conditions is created in an inert state (active: false in the response).',
            ],
        ],
        'required' => ['label'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('theme styles'),
            'theme_style' => ['type' => 'object'],
            'active' => [
                'type' => 'boolean',
                'description' => 'True when the theme style has at least one condition and Bricks will consider it for activation.',
            ],
            'warning' => [
                'type' => 'string',
                'description' => 'Human-readable warning when active is false (e.g. missing conditions).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Warnings about settings Bricks will silently ignore (a control key at the top level instead of under its group, a font-family written as var() or as a comma stack, unresolved var(--x) references).',
            ],
            'css_file' => [
                'type' => ['string', 'null'],
                'description' => 'External CSS file mode: "regenerated" when this style\'s CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise; null when this Bricks build exposes no theme-style file-name generator, so the file step was skipped (a warning says so — regenerate from the builder).',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'var(--name) references in the settings that resolve to nothing on this site.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_create_theme_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

function bricks_create_theme_style(array $input): array|WP_Error
{
    /** @var mixed $raw_label */
    $raw_label = $input['label'] ?? '';
    if (!is_string($raw_label) || trim($raw_label) === '') {
        return bricks_error('invalid_input', message: 'label is required.');
    }

    $label = trim($raw_label);

    /** @var mixed $raw_settings */
    $raw_settings = $input['settings'] ?? null;
    $settings = is_array($raw_settings) ? $raw_settings : [];

    /** @var mixed $raw_conditions */
    $raw_conditions = $input['conditions'] ?? null;
    if (is_array($raw_conditions)) {
        // The dedicated conditions input wins over settings.conditions when
        // both are supplied.
        $settings['conditions'] = ['conditions' => array_values($raw_conditions)];
    }

    $normalized = ts_normalize_settings_conditions($settings);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }
    [$settings, $condition_warnings] = $normalized;

    /** @var mixed $raw_store */
    $raw_store = get_option(BRICKS_DB_THEME_STYLES, []);
    $store = is_array($raw_store) ? $raw_store : [];

    $id = ts_unique_id($store);

    $entry = [
        'label' => $label,
        'settings' => $settings,
    ];

    $store[$id] = $entry;
    $css_file = ts_save_store($store);

    return ts_verified(ts_format_response($id, $entry, $condition_warnings, $css_file), $store);
}

/**
 * Persists the theme-style store. In external CSS file mode Bricks' option
 * listener rewrites one file per style; the CSS directory is made sure to
 * exist first and the outcome is reported so a stale frontend never goes
 * unnoticed. Returns the css_file status keyed by style id.
 *
 * @param array<array-key, mixed> $store
 * @return array<string, string|null> css_file status per style id; null when the file name is unavailable
 */
function ts_save_store(array $store): array
{
    bricks_ensure_css_dir();
    $started = time();
    update_option(BRICKS_DB_THEME_STYLES, $store);

    $status = [];
    /** @var mixed $entry */
    foreach ($store as $style_id => $entry) {
        $file_name = bricks_theme_style_css_file_name((string) $style_id, ts_entry_fields($entry));
        $status[(string) $style_id] = $file_name === null ? null : bricks_global_css_file_status($file_name, $started);
    }

    return $status;
}

/**
 * Builds the standard response shape for create/edit ops, including the active
 * flag and a warning when the style is inert (no conditions).
 *
 * @param array<array-key, mixed> $entry
 * @param list<string>            $extra_warnings
 * @param array<string, string|null> $css_files css_file status per style id (external file mode; null = file name unavailable)
 * @return array{theme_style: array<array-key, mixed>, active: bool, warning?: string, warnings?: list<string>, css_file?: string|null, unresolved_variables?: list<string>}
 */
function ts_format_response(string $id, array $entry, array $extra_warnings = [], array $css_files = []): array
{
    $entry_settings = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
    $active = ts_is_active($entry_settings);
    $response = [
        'theme_style' => array_merge(['id' => $id], $entry),
        'active' => $active,
    ];
    if (!$active) {
        $response['warning'] = 'Theme style has no conditions — Bricks will not activate it. Pass conditions on create or edit (e.g. [{"main": "any"}] for site-wide).';
    }
    if (array_key_exists($id, $css_files)) {
        $response['css_file'] = $css_files[$id];
    }
    $warnings = array_merge(
        ts_unknown_group_warnings($entry_settings),
        bricks_font_family_warnings($entry_settings, sprintf('Theme style "%s"', $id)),
        $extra_warnings,
    );
    $unresolved = bricks_unresolved_variables($entry_settings);
    if ($unresolved !== []) {
        $response['unresolved_variables'] = $unresolved;
        $warnings[] = bricks_unresolved_variables_warning($unresolved);
    }
    if (isset($css_files[$id]) && str_starts_with($css_files[$id], 'skipped(')) {
        $warnings[] =
            'External CSS file mode is on but the theme-style CSS file was not written: '
            . substr($css_files[$id], offset: 8, length: -1)
            . '. The frontend keeps the previous styles until it is.';
    }
    if (array_key_exists($id, $css_files) && $css_files[$id] === null) {
        $warnings[] = bricks_theme_style_file_unavailable_warning();
    }
    if ($warnings !== []) {
        $response['warnings'] = $warnings;
    }
    return $response;
}

/**
 * Returns true iff the theme-style settings carry at least one condition under
 * settings.conditions.conditions (the double-nested path Bricks reads).
 *
 * @param array<array-key, mixed> $settings
 */
function ts_is_active(array $settings): bool
{
    /** @var mixed $conditions_group */
    $conditions_group = $settings['conditions'] ?? null;
    if (!is_array($conditions_group)) {
        return false;
    }
    /** @var mixed $conditions_list */
    $conditions_list = $conditions_group['conditions'] ?? null;
    return is_array($conditions_list) && count($conditions_list) > 0;
}

/**
 * Warns about top-level settings keys that are not registered Bricks
 * theme-style groups. `Assets::generate_inline_css_theme_style()` iterates the
 * settings by group key and silently skips any top-level key that is not a
 * registered group, so a control left flat (e.g. `widthMax` instead of nesting
 * it under `container`, or `siteBackground` instead of under `general`) is
 * saved but produces no CSS and shows empty in the editor.
 *
 * @param array<array-key, mixed> $settings
 * @return list<string>
 */
function ts_unknown_group_warnings(array $settings): array
{
    $groups = ts_group_keys();
    if ($groups === []) {
        return [];
    }
    $warnings = [];
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if (!isset($groups[$key])) {
            $warnings[] = sprintf(
                'Theme-style setting "%s" is not a registered Bricks group (groups are e.g. typography, general, container, heading, button, image, …). Bricks generates CSS only for keys nested under a group, so a control left at the top level is saved but silently ignored. Nest it under its group, e.g. {"container": {"%s": …}}. Call novamira/bricks-list-theme-style-schema for the groups this Bricks registers and the keys each one accepts.',
                $key,
                $key,
            );
            continue;
        }
        if (is_array($value)) {
            $warnings = array_merge($warnings, ts_unknown_setting_warnings($key, $value));
        }
    }
    return $warnings;
}

/**
 * Warns about keys inside a valid group that the group does not register.
 *
 * A stored key Bricks does not know produces no CSS and cannot be seen in the
 * editor, and the write still verifies — the value really is in the database.
 * Saying so is the only way an agent learns the difference between "saved"
 * and "in effect".
 *
 * @param array<array-key, mixed> $settings
 * @return list<string>
 */
function ts_unknown_setting_warnings(string $group, array $settings): array
{
    $known = ts_group_setting_keys($group);
    if ($known === []) {
        return [];
    }
    $unknown = [];
    foreach (array_keys($settings) as $key) {
        $key = (string) $key;
        if ($key === '' || isset($known[$key])) {
            continue;
        }
        $unknown[] = $key;
    }
    if ($unknown === []) {
        return [];
    }

    return [sprintf(
        'Theme-style group "%s" does not register %s, so %s stored but produces no CSS and stays invisible in the builder — a verified write is not the same as a setting in effect. Read the keys this group accepts with novamira/bricks-list-theme-style-schema (group="%s"), then write the real ones; remove the ignored keys by sending the group again with replace: true.',
        $group,
        implode(', ', array_map(static fn(string $k): string => '"' . $k . '"', $unknown)),
        count($unknown) === 1 ? 'it is' : 'they are',
        $group,
    )];
}

/**
 * The setting keys one theme-style group registers.
 *
 * @return array<string, true>
 */
function ts_group_setting_keys(string $group): array
{
    if (!class_exists(\Bricks\Theme_Styles::class)) {
        return [];
    }
    if (count(\Bricks\Theme_Styles::$controls) === 0) {
        \Bricks\Theme_Styles::set_controls();
    }
    if (!is_array(\Bricks\Theme_Styles::$controls[$group] ?? null)) {
        return [];
    }
    $keys = [];
    foreach (array_keys(\Bricks\Theme_Styles::$controls[$group]) as $key) {
        $keys[$key] = true;
    }

    return $keys;
}

/**
 * The set of valid top-level Bricks theme-style group keys
 * (`array_keys(Theme_Styles::$controls)`), cached per request. Empty when
 * Bricks is not loaded, which disables the group validation gracefully.
 *
 * @return array<string, true>
 */
function ts_group_keys(): array
{
    /** @var array<string, true>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    return $cache = ts_compute_group_keys();
}

/**
 * Uncached core of {@see ts_group_keys()}.
 *
 * @return array<string, true>
 */
function ts_compute_group_keys(): array
{
    if (!class_exists(\Bricks\Theme_Styles::class)) {
        return [];
    }
    if (count(\Bricks\Theme_Styles::$controls) === 0) {
        \Bricks\Theme_Styles::set_controls();
    }
    $keys = [];
    foreach (array_keys(\Bricks\Theme_Styles::$controls) as $key) {
        $keys[$key] = true;
    }
    return $keys;
}

/**
 * Generates a unique ts- prefixed id not already present in the store.
 *
 * @param array<array-key, mixed> $store
 */
function ts_unique_id(array $store): string
{
    do {
        $id = bricks_random_id(prefix: 'ts-');
    } while (array_key_exists($id, $store));

    return $id;
}

wp_register_ability('novamira/bricks-edit-theme-style', [
    'label' => __('Edit Bricks Theme Style', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Bricks theme style by id. Provide at least one of: label, settings, or conditions. Settings are deep-merged by default: associative objects merge by key, while indexed arrays/lists and scalars replace the prior value wholesale. Pass replace=true to wipe and replace the full settings object. When conditions is passed, it replaces settings.conditions directly. Conditions arriving nested inside settings (settings.conditions.conditions) are validated and normalized the same way; when both routes are supplied, the dedicated conditions input wins. Settings are grouped by Bricks theme-style group (`general.siteBackground` — compiled to `html {background: …}` plus `body {background: none}`, so the body is left transparent, `typography.typographyBody` / `typographyHeadings` with bare font-family names, `colors.*`, `css.stylesheet` for site-wide custom CSS, plus per-element groups); typed controls first, `css.stylesheet` as last resort. Layout primitives (`section`/`container`/`block`) use dimensional keys WITHOUT underscore — `width`, `widthMin`, `widthMax`, `padding`, `margin` — while flex/layout keys (`_display`, `_direction`, `_justifyContent`, `_alignItems`, `_columnGap`, `_rowGap`) keep the underscore.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the theme style to edit.'],
            'label' => ['type' => 'string', 'description' => 'New display label.'],
            'settings' => [
                'type' => 'object',
                'description' => 'New or merged per-element-type defaults. Layout primitives (section/container/block): unprefixed dimensional keys (`width`/`widthMin`/`widthMax`/`padding`/`margin`) and prefixed flex keys (`_display`/`_direction`/`_justifyContent`/`_alignItems`/`_columnGap`/`_rowGap`). For non-layout elements, keys mirror the element\'s own control schema (discover via `bricks-list-elements element=<name>`). Prefer typed controls over raw CSS.',
            ],
            'conditions' => [
                'type' => 'array',
                'description' => 'Replaces settings.conditions when provided.',
            ],
            'replace' => [
                'type' => 'boolean',
                'description' => 'When true, the settings object replaces the existing one entirely. Default false (merge).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('theme styles'),
            'theme_style' => ['type' => 'object'],
            'active' => [
                'type' => 'boolean',
                'description' => 'True when the theme style has at least one condition and Bricks will consider it for activation.',
            ],
            'warning' => [
                'type' => 'string',
                'description' => 'Human-readable warning when active is false (e.g. removed all conditions).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Warnings about settings Bricks will silently ignore (a control key at the top level instead of under its group, a font-family written as var() or as a comma stack, unresolved var(--x) references).',
            ],
            'css_file' => [
                'type' => ['string', 'null'],
                'description' => 'External CSS file mode: "regenerated" when this style\'s CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise; null when this Bricks build exposes no theme-style file-name generator, so the file step was skipped (a warning says so — regenerate from the builder).',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'var(--name) references in the settings that resolve to nothing on this site.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_edit_theme_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_edit_theme_style(array $input): array|WP_Error
{
    $ctx = ts_validate_edit_input($input);
    if ($ctx instanceof WP_Error) {
        return $ctx;
    }

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_THEME_STYLES, []);
    $store = is_array($raw) ? $raw : [];

    $result = $ctx['replace']
        ? ts_apply_replace($store, $ctx['id'], $ctx['new_label'], $ctx['new_settings'], $ctx['new_conditions'])
        : ts_apply_merge($store, $ctx['id'], $ctx['new_label'], $ctx['new_settings'], $ctx['new_conditions']);

    if ($result === null) {
        return bricks_error('not_found', sprintf('Theme style "%s" not found.', $ctx['id']));
    }

    // Conditions can arrive through the dedicated `conditions` input (which
    // ts_apply_* embed last, so it wins) OR nested inside `settings` as
    // settings.conditions — both routes must satisfy the array-shape
    // guarantees before anything is persisted. Only a request that actually
    // touches conditions is normalized, so editing e.g. just the label never
    // trips over legacy entries already in the store.
    $condition_warnings = [];
    $touches_conditions =
        is_array($ctx['new_conditions'])
        || is_array($ctx['new_settings']) && array_key_exists('conditions', $ctx['new_settings']);
    if ($touches_conditions) {
        $entry_settings = is_array($result['entry']['settings'] ?? null) ? $result['entry']['settings'] : [];
        $normalized = ts_normalize_settings_conditions($entry_settings);
        if ($normalized instanceof WP_Error) {
            return $normalized;
        }
        [$entry_settings, $condition_warnings] = $normalized;
        $result['entry']['settings'] = $entry_settings;
        $result['store'][$ctx['id']] = $result['entry'];
    }

    $css_files = ts_save_store($result['store']);

    return ts_verified(
        ts_format_response($ctx['id'], $result['entry'], $condition_warnings, $css_files),
        $result['store'],
    );
}

/**
 * Runs the conditions nested inside a theme-style settings object
 * (settings.conditions.conditions — the double-nested path Bricks reads)
 * through the shared condition normalizer, so scalar multi-value fields are
 * persisted as arrays regardless of which input route they arrived on.
 *
 * "Path absent" and "path malformed" are distinct: settings without a
 * `conditions` key pass through untouched, but a `conditions` key whose group
 * (or inner `conditions` value) is not the expected array shape is rejected —
 * persisting it verbatim would store exactly the malformed scalar/null
 * containers Bricks 2.4 discards at match time.
 *
 * @param  array<array-key, mixed> $settings
 * @return array{0: array<array-key, mixed>, 1: list<string>}|WP_Error [settings, warnings]
 */
function ts_normalize_settings_conditions(array $settings): array|WP_Error
{
    if (!array_key_exists('conditions', $settings)) {
        return [$settings, []];
    }

    /** @var mixed $group */
    $group = $settings['conditions'];
    if (!is_array($group) || !is_array($group['conditions'] ?? null)) {
        return bricks_error(
            'invalid_condition',
            message: 'settings.conditions must be an object of shape {"conditions": [...]} with a list of condition objects under the inner "conditions" key.',
        );
    }

    $normalized = bricks_normalize_template_conditions(array_values($group['conditions']));
    if (is_wp_error($normalized)) {
        return $normalized;
    }

    $group['conditions'] = $normalized['conditions'];
    $settings['conditions'] = $group;

    return [$settings, $normalized['warnings']];
}

/**
 * Validates the input for bricks_edit_theme_style.
 * Returns a context array on success or a WP_Error on failure.
 *
 * @param  array<mixed> $input
 * @return array{id: string, new_label: mixed, new_settings: mixed, new_conditions: mixed, replace: bool}|WP_Error
 */
function ts_validate_edit_input(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $new_label */
    $new_label = $input['label'] ?? null;
    /** @var mixed $new_settings */
    $new_settings = $input['settings'] ?? null;
    /** @var mixed $new_conditions */
    $new_conditions = $input['conditions'] ?? null;

    if ($new_label === null && $new_settings === null && $new_conditions === null) {
        return bricks_error('invalid_input', message: 'Pass at least one of label, settings, or conditions.');
    }

    return [
        'id' => $id,
        'new_label' => $new_label,
        'new_settings' => $new_settings,
        'new_conditions' => $new_conditions,
        'replace' => ($input['replace'] ?? null) === true,
    ];
}

/**
 * Patches a theme style entry using replace mode (new_settings wipes existing).
 * Returns null when the id is not found.
 *
 * @param  array<array-key, mixed> $store
 * @param  string                  $id
 * @param  mixed                   $new_label
 * @param  mixed                   $new_settings
 * @param  mixed                   $new_conditions
 * @return array{store: array<array-key, mixed>, entry: array<array-key, mixed>}|null
 */
function ts_apply_replace(
    array $store,
    string $id,
    mixed $new_label,
    mixed $new_settings,
    mixed $new_conditions,
): ?array {
    if (!array_key_exists($id, $store) || !is_array($store[$id])) {
        return null;
    }

    $entry = $store[$id];

    if (is_string($new_label) && $new_label !== '') {
        $entry['label'] = $new_label;
    }

    $resolved = ts_resolve_replace_settings($entry, $new_settings);

    if (is_array($new_conditions)) {
        $resolved['conditions'] = ['conditions' => $new_conditions];
    }

    $entry['settings'] = $resolved;
    $store[$id] = $entry;

    return ['store' => $store, 'entry' => $entry];
}

/**
 * Patches a theme style entry using list-safe deep-merge mode.
 * Returns null when the id is not found.
 *
 * @param  array<array-key, mixed> $store
 * @param  string                  $id
 * @param  mixed                   $new_label
 * @param  mixed                   $new_settings
 * @param  mixed                   $new_conditions
 * @return array{store: array<array-key, mixed>, entry: array<array-key, mixed>}|null
 */
function ts_apply_merge(array $store, string $id, mixed $new_label, mixed $new_settings, mixed $new_conditions): ?array
{
    if (!array_key_exists($id, $store) || !is_array($store[$id])) {
        return null;
    }

    $entry = $store[$id];

    if (is_string($new_label) && $new_label !== '') {
        $entry['label'] = $new_label;
    }

    $resolved = ts_resolve_merge_settings($entry, $new_settings);

    if (is_array($new_conditions)) {
        $resolved['conditions'] = ['conditions' => $new_conditions];
    }

    $entry['settings'] = $resolved;
    $store[$id] = $entry;

    return ['store' => $store, 'entry' => $entry];
}

/**
 * Returns the new_settings array verbatim (replace mode), falling back to the
 * existing settings when new_settings is not an array.
 *
 * @param  array<array-key, mixed> $entry
 * @param  mixed                   $new_settings
 * @return array<array-key, mixed>
 */
function ts_resolve_replace_settings(array $entry, mixed $new_settings): array
{
    if (is_array($new_settings)) {
        return $new_settings;
    }
    return is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
}

/**
 * Merges new_settings into the existing settings of the entry (merge mode).
 * Returns existing settings unchanged when new_settings is not an array.
 *
 * @param  array<array-key, mixed> $entry
 * @param  mixed                   $new_settings
 * @return array<array-key, mixed>
 */
function ts_resolve_merge_settings(array $entry, mixed $new_settings): array
{
    /** @var array<string, mixed> $existing */
    $existing = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
    if (!is_array($new_settings)) {
        return $existing;
    }
    /** @var array<string, mixed> $new_settings */
    return bricks_deep_merge($existing, $new_settings);
}

wp_register_ability('novamira/bricks-delete-theme-style', [
    'label' => __('Delete Bricks Theme Style', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Bricks theme style by id. Hard delete — any pages that relied on this theme style will revert to Bricks defaults.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the theme style to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('theme styles'),
            'deleted' => ['type' => 'boolean'],
            'css_file' => [
                'type' => ['string', 'null'],
                'description' => 'External CSS file mode: "regenerated" when this style\'s CSS file was removed, "inline" when Bricks serves CSS inline, "skipped(reason)" when the stale file is still there; null when this Bricks build exposes no theme-style file-name generator, so the file step was skipped (see warnings).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when the theme-style file could not be addressed (css_file null): regenerate the CSS files from the builder.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_delete_theme_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

function bricks_delete_theme_style(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_THEME_STYLES, []);
    $store = is_array($raw) ? $raw : [];

    if (!array_key_exists($id, $store)) {
        return bricks_error('not_found', sprintf('Theme style "%s" not found.', $id));
    }

    $entry = ts_entry_fields($store[$id]);
    unset($store[$id]);
    $file_name = bricks_theme_style_css_file_name($id, $entry);

    // Deleting the last theme style removes the option row, as the builder
    // does, instead of persisting an empty array.
    if ($store === []) {
        delete_option(BRICKS_DB_THEME_STYLES);
        // No option listener fires on delete: remove the stale theme-style
        // files ourselves through Bricks' generator (an empty store unlinks
        // every theme-style-*.min.css).
        if (
            bricks_css_loading_is_file()
            && class_exists(\Bricks\Assets_Theme_Styles::class)
            && method_exists(\Bricks\Assets_Theme_Styles::class, 'generate_css_file')
        ) {
            \Bricks\Assets_Theme_Styles::generate_css_file([]);
        }
        return ts_verified(array_merge(['deleted' => true], ts_removed_file_status($file_name)), intended: null);
    }

    // The listener rewrites the remaining styles and unlinks files of styles
    // that no longer exist.
    ts_save_store($store);

    return ts_verified(array_merge(['deleted' => true], ts_removed_file_status($file_name)), $store);
}

/**
 * Adds the read-back verification of the theme-styles option to a response.
 *
 * @param array<string, mixed>          $result
 * @param array<array-key, mixed>|null  $intended null when the row was deleted
 * @return array<string, mixed>
 */
function ts_verified(array $result, ?array $intended): array
{
    return bricks_with_option_write_verification($result, BRICKS_DB_THEME_STYLES, $intended);
}

/**
 * The stored theme-style entry as a string-keyed array (empty when the store
 * holds something that is not an array).
 *
 * @return array<string, mixed>
 */
function ts_entry_fields(mixed $entry): array
{
    if (!is_array($entry)) {
        return [];
    }
    $fields = [];
    /** @var mixed $value */
    foreach ($entry as $key => $value) {
        $fields[(string) $key] = $value;
    }

    return $fields;
}

/**
 * css_file (+ warnings) part of a delete response: the removed-file status
 * when Bricks named the file, `null` plus a warning when it could not.
 *
 * @return array{css_file: string|null, warnings?: list<string>}
 */
function ts_removed_file_status(?string $file_name): array
{
    if ($file_name === null) {
        return ['css_file' => null, 'warnings' => [bricks_theme_style_file_unavailable_warning()]];
    }

    return ['css_file' => bricks_global_css_file_removed_status($file_name)];
}
