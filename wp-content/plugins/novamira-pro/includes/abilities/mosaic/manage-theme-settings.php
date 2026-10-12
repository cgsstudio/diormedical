<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// get-theme-settings
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-get-theme-settings', [
    'label' => __('Get Mosaic Theme Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the global settings stored under a Mosaic theme\'s `data.settings` sub-key. Mosaic stores them as flat, dotted keys: `settings.layout.contentSize` and `settings.layout.wideSize` (WordPress content / wide widths) and `styles.spacing.blockGap` (WordPress block gap) — CSS lengths Mosaic passes to WordPress\'s theme.json, so they style WordPress blocks in post content — plus `theme.json` (a JSON string merged into WordPress\'s theme.json before those three keys, which win) and the `connectedApiThemeID` / `wizard…Category` IDs Mosaic\'s library and wizard manage themselves; on a freshly wizard-seeded theme the value is `null` (Mosaic falls back to built-in defaults). Returns `{theme_id, settings}` where `settings` is either `null` or the decoded object. Use mosaic-edit-theme-settings to merge or replace.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID — get one from mosaic-list-themes.'],
        ],
        'required' => ['theme_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string'],
            'settings' => ['type' => ['object', 'null']],
        ],
        'required' => ['theme_id', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_get_theme_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns null when the theme uses Mosaic defaults (wizard-fresh themes). Edit via mosaic-edit-theme-settings.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-theme-settings
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-theme-settings', [
    'label' => __('Edit Mosaic Theme Settings', domain: 'novamira-pro'),
    'description' => __(
        'Updates the global theme settings stored under a Mosaic theme\'s `data.settings` sub-key. Two modes: `merge` (default) — a shallow array-merge against the existing settings, so keys you do NOT send are PRESERVED; `replace` — the supplied `settings` object becomes the new value verbatim, OVERWRITING everything (use only when sending the complete settings object). Nested keys are not deep-merged. Pass `settings = null` with `mode = replace` to clear settings back to Mosaic defaults. Optimistic concurrency: if the theme row was modified by another writer (Mosaic editor save on a parallel connection) between this call\'s load and the UPDATE, returns `mo_concurrent_modification` — re-fetch via mosaic-get-theme-settings and retry. Keys Mosaic supports (flat and dotted — send `{"settings.layout.contentSize": "760px"}`, not a nested object): `settings.layout.contentSize`, `settings.layout.wideSize`, `styles.spacing.blockGap` (CSS lengths), `theme.json` (a JSON string merged into WordPress\'s theme.json; the three length keys are applied after it and win) — all of them feed WordPress\'s theme.json, so they style WordPress blocks in post content, not Mosaic elements — plus the `connectedApiThemeID` / `wizard…Category` IDs Mosaic\'s library and wizard write themselves (leave them as they are). Keys Mosaic does not define (e.g. `containerWidth`) are stored but have no effect.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID.'],
            'settings' => [
                'type' => ['object', 'null'],
                'description' => 'The new settings object, or null to clear.',
            ],
            'mode' => [
                'type' => 'string',
                'enum' => ['replace', 'merge'],
                'default' => 'merge',
                'description' => '`merge` (default) is a shallow array-merge that preserves untouched settings; `replace` overwrites the whole settings object verbatim.',
            ],
        ],
        'required' => ['theme_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string'],
            'settings' => ['type' => ['object', 'null']],
            'revision' => ['type' => 'string'],
        ],
        'required' => ['theme_id', 'settings', 'revision'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_theme_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default `mode = merge` preserves settings you do not send (safe for partial updates). Pass `mode = replace` only for a full reset of the whole settings object (it overwrites verbatim and wipes untouched keys, including the bookkeeping IDs Mosaic\'s wizard stores there).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_get_theme_settings(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    if ($theme_id === '') {
        return mo_error('mo_invalid_input', __('theme_id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load('themes', $theme_id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: theme UUID. */
            __('Mosaic theme %s not found.', domain: 'novamira-pro'),
            $theme_id,
        ));
    }
    return [
        'theme_id' => $theme_id,
        'settings' => mo_extract_theme_settings($row),
    ];
}

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_theme_settings(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    if ($theme_id === '') {
        return mo_error('mo_invalid_input', __('theme_id is required.', domain: 'novamira-pro'));
    }
    if (!array_key_exists('settings', $input)) {
        return mo_error('mo_invalid_input', __('settings is required (pass null to clear).', domain: 'novamira-pro'));
    }
    /** @var mixed $supplied */
    $supplied = $input['settings'];
    if ($supplied !== null && !is_array($supplied)) {
        return mo_error('mo_invalid_input', __('settings must be an object or null.', domain: 'novamira-pro'));
    }
    // Default to merge (the `edit` verb's partial-update contract): only an
    // explicit `replace` overwrites the whole settings object.
    $mode = is_string($input['mode'] ?? null) && $input['mode'] === 'replace' ? 'replace' : 'merge';
    if ($supplied !== null) {
        $depth_err = mo_validate_blob_depth($supplied, field: 'settings');
        if ($depth_err !== null) {
            return $depth_err;
        }
        $bytes_err = mo_validate_blob_bytes($supplied, field: 'settings');
        if ($bytes_err !== null) {
            return $bytes_err;
        }
    }
    $pre = mo_load('themes', $theme_id);
    if ($pre === null || ($pre['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: theme UUID. */
            __('Mosaic theme %s not found.', domain: 'novamira-pro'),
            $theme_id,
        ));
    }
    return mo_with_lock("theme-write:{$theme_id}", static fn(): array|WP_Error => mo_apply_theme_settings_locked(
        $theme_id,
        $supplied,
        $mode,
    ));
}

/**
 * @param  array<array-key, mixed>|null $supplied
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_theme_settings_locked(string $theme_id, ?array $supplied, string $mode): array|WP_Error
{
    $row = mo_load('themes', $theme_id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return mo_concurrent_modification_error('theme', $theme_id);
    }
    $existing_settings = mo_extract_theme_settings($row);
    $new_settings = mo_merge_theme_settings($existing_settings, $supplied, $mode);
    $existing_data = mo_decode_data($row['data'] ?? null);
    /** @var array<string, mixed> $next_data */
    $next_data = [];
    /** @var mixed $v */
    foreach ($existing_data as $k => $v) {
        if (!is_string($k)) {
            continue;
        }
        $next_data[$k] = $v;
    }
    $next_data['settings'] = $new_settings;
    $old_revision = mo_str($row, key: 'revision');
    $new_revision = mo_uuid();
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('themes'),
        [
            'data' => mo_encode_object_blob($next_data),
            'modified_gmt' => mo_now_gmt(),
            'revision' => $new_revision,
        ],
        ['ID' => $theme_id, 'status' => 'publish', 'revision' => $old_revision],
        ['%s', '%s', '%s'],
        ['%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not update the Mosaic theme settings.', domain: 'novamira-pro'));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('theme', $theme_id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-theme-settings',
        operation: 'edit',
        entity: 'theme',
        entity_id: $theme_id,
    );
    return [
        'theme_id' => $theme_id,
        'settings' => $new_settings,
        'revision' => $new_revision,
    ];
}

/**
 * Pull the `data.settings` sub-object out of a theme row, normalizing
 * the wizard-fresh `null` sentinel and any decode-failure marker into
 * `null` so callers never have to distinguish "missing" from "explicit
 * null".
 *
 * @param  array<string, mixed>           $row
 * @return array<string, mixed>|null
 */
function mo_extract_theme_settings(array $row): ?array
{
    $data = mo_decode_data($row['data'] ?? null);
    if (isset($data['_decode_failed'])) {
        return null;
    }
    if (!array_key_exists('settings', $data)) {
        return null;
    }
    /** @var mixed $settings */
    $settings = $data['settings'];
    if (!is_array($settings)) {
        return null;
    }
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $v */
    foreach ($settings as $k => $v) {
        if (!is_string($k)) {
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Resolve the new settings value based on the agent-supplied input and
 * mode. `replace` mode returns the supplied value verbatim (including
 * null); `merge` mode shallow-merges supplied into existing.
 *
 * @param  array<string, mixed>|null      $existing
 * @param  array<array-key, mixed>|null   $supplied
 * @return array<string, mixed>|null
 */
function mo_merge_theme_settings(?array $existing, ?array $supplied, string $mode): ?array
{
    if ($mode === 'merge') {
        if ($supplied === null) {
            return $existing;
        }
        $merged = $existing ?? [];
        /** @var mixed $v */
        foreach ($supplied as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $merged[$k] = $v;
        }
        return $merged;
    }
    if ($supplied === null) {
        return null;
    }
    /** @var array<string, mixed> $replaced */
    $replaced = [];
    /** @var mixed $v */
    foreach ($supplied as $k => $v) {
        if (!is_string($k)) {
            continue;
        }
        $replaced[$k] = $v;
    }
    return $replaced;
}
