<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-set-editor-settings', [
    'label' => __('Set Spectra Editor Settings', domain: 'novamira-pro'),
    'description' => __(
        'Write one or more Spectra editor / container settings. Whitelist-only — accepts the same 17 keys that novamira/spectra-get-settings exposes. Toggles (`uag_enable_*`, `uag_load_gfonts_locally`, `uag_preload_local_fonts`, `uag_load_fse_font_globally`) accept the strings "enabled" / "disabled" (UAGB storage shape); container-default keys and the visibility/dynamic-content mode keys accept free-form strings. Unknown or non-whitelisted keys return spectra_unknown_setting. Cache is flushed after the write so the front end serves fresh CSS.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Map of setting key → new value. See novamira/spectra-get-settings for the allowlist.',
                'additionalProperties' => ['type' => 'string'],
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'written' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys whose values were persisted this call.',
            ],
            'unchanged' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys whose new value matched the stored value (no write emitted).',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Post-write snapshot of every touched key.',
            ],
        ],
        'required' => ['written', 'unchanged', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_set_editor_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Whitelist-only settings writer. Toggles accept "enabled"/"disabled"; unknown keys return spectra_unknown_setting.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Every key writable via `set-editor-settings`. Union of the reader's two
 * whitelists (editor toggles + container defaults) plus the Google Fonts
 * mode keys. Keeping the writer's allowlist derived from the reader's
 * constants avoids drift between get and set semantics.
 *
 * @return list<string>
 */
function spectra_writable_setting_keys(): array
{
    return array_merge(
        SPECTRA_EDITOR_TOGGLE_KEYS,
        SPECTRA_CONTAINER_DEFAULT_KEYS,
        ['uag_load_gfonts_locally', 'uag_preload_local_fonts', 'uag_load_fse_font_globally'],
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_set_editor_settings(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    /** @var mixed $settings */
    $settings = $input['settings'] ?? null;
    if (!is_array($settings) || $settings === []) {
        return sp_invalid_input('settings must be a non-empty object');
    }

    $writable = spectra_writable_setting_keys();
    $written = [];
    $unchanged = [];
    $snapshot = [];

    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        if (!is_string($key) || !in_array($key, $writable, strict: true)) {
            return sp_unknown_setting(is_string($key) ? $key : '');
        }
        if (!is_string($value)) {
            return sp_invalid_input(sprintf('Value for `%s` must be a string.', $key));
        }
        $result = spectra_write_editor_setting($key, $value);
        if ($result === 'written') {
            $written[] = $key;
        }
        if ($result === 'unchanged') {
            $unchanged[] = $key;
        }
        $snapshot[$key] = spectra_option_to_string(spectra_admin_settings_option($key, default_value: ''));
    }

    sp_invalidate_spectra_cache();

    return [
        'written' => $written,
        'unchanged' => $unchanged,
        'settings' => (object) $snapshot,
    ];
}

/**
 * Persist a single Spectra setting. Reads the current value first so
 * idempotent no-ops are reported honestly to the caller. Returns either
 * `'written'` or `'unchanged'` so the ability response distinguishes the
 * two shapes.
 */
function spectra_write_editor_setting(string $key, string $value): string
{
    $current = spectra_option_to_string(spectra_admin_settings_option($key, default_value: ''));
    if ($current === $value) {
        return 'unchanged';
    }
    // Mirror the reader — settings go through UAGB_Admin_Helper when the
    // helper class is available, otherwise fall back to raw update_option
    // (same fallback semantics as `spectra_admin_settings_option`).
    /** @var callable-string|array{class-string, string} $callable */
    $callable = ['\\UAGB_Admin_Helper', 'update_admin_settings_option'];
    if (is_callable($callable)) {
        $callable($key, $value);
        return 'written';
    }
    update_option(option: $key, value: $value);
    return 'written';
}
