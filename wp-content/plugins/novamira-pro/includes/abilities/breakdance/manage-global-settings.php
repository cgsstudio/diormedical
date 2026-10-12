<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Breakdance Global Settings stores the builder's "Global Styles" categories
 * under a `settings` envelope: `{settings: {typography, colors, buttons, ...}}`.
 * The ability surfaces that inner category map while preserving the full
 * envelope when it writes the `global_settings_json_string` global option.
 */

// get-global-settings -------------------------------------------------------

wp_register_ability('novamira/breakdance-get-global-settings', [
    'label' => __('Get Breakdance Global Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Breakdance Global Styles categories stored inside the native `settings` envelope. Returned keys are categories like `typography`, `colors`, `buttons`, `containers`, `forms`, `woocommerce`, etc., each one a nested object with the CSS controls the builder UI shows. Pair with `edit-global-settings` to patch.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'description' => 'Optional, return only this section from the native `settings` envelope (e.g. `typography`).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'settings' => ['type' => 'object'],
            'sections' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_global_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'This ability unwraps the stored `{settings: {...}}` envelope and returns its inner categories. On fresh installs Breakdance may not pre-populate this option; an empty response with `sections: []` is normal.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// edit-global-settings ------------------------------------------------------

wp_register_ability('novamira/breakdance-edit-global-settings', [
    'label' => __('Edit Breakdance Global Settings', domain: 'novamira-pro'),
    'description' => __(
        'Patches categories inside Breakdance\'s native `settings` envelope with shallow merge semantics. Category keys you pass replace the stored value for that section; keys you omit are preserved; pass `null` to wipe a section. The replacement is *not* deep: if you patch `typography`, the entire `typography` sub-tree is overwritten with what you pass, not deep-merged. To partially patch a section, call `get-global-settings` with `section`, modify the returned object, then pass the whole sub-object back here.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Category patch: keys here replace corresponding sections inside the native `settings` envelope.',
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'settings' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_edit_global_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Shallow merge inside the native `settings` envelope only. For deep edits inside a section, call get-global-settings first, modify the section locally, then pass the full section back.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

// callbacks -----------------------------------------------------------------

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function breakdance_get_global_settings(array $input): array
{
    $settings = bd_global_settings_sections(bd_read_global_settings());
    $sections = array_keys($settings);
    if (isset($input['section']) && is_string($input['section']) && $input['section'] !== '') {
        $key = $input['section'];
        return [
            'success' => true,
            'settings' => isset($settings[$key]) && is_array($settings[$key]) ? $settings[$key] : [],
            'sections' => $sections,
        ];
    }
    return ['success' => true, 'settings' => $settings, 'sections' => $sections];
}

/**
 * Extract the native `settings` envelope, while tolerating the legacy flat
 * shape written by earlier versions of these abilities.
 *
 * @param array<string, mixed> $global_settings
 * @return array<string, mixed>
 */
function bd_global_settings_sections(array $global_settings): array
{
    /** @var mixed $stored_sections */
    $stored_sections = array_key_exists('settings', $global_settings) ? $global_settings['settings'] : $global_settings;
    if (!is_array($stored_sections)) {
        return [];
    }

    $sections = [];
    /** @var mixed $value */
    foreach ($stored_sections as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $sections[$key] = $value;
    }
    return $sections;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_edit_global_settings(array $input): array|WP_Error
{
    if (!isset($input['settings']) || !is_array($input['settings'])) {
        return bd_error('bd_invalid_input', __('`settings` must be an object.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed> $patch */
    $patch = [];
    /** @var mixed $value */
    foreach ($input['settings'] as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        $patch[$key] = $value;
    }

    // Round 11 B15 — `global_settings_json_string` is a read-modify-write
    // option shared by every setting key. Without a lock parallel writes
    // lost ~33% of patches. Serialise on a dedicated slot key — separate
    // from variables / global_classes so unrelated surfaces don't block
    // each other.
    return bd_with_lock('global_settings', static function () use ($patch): array|WP_Error {
        $envelope = bd_read_global_settings();
        $existing = bd_global_settings_sections($envelope);
        $merged = array_merge($existing, $patch);
        /** @var mixed $value */
        foreach ($patch as $key => $value) {
            if ($value !== null) {
                continue;
            }

            unset($merged[$key]);
        }
        // A missing envelope means the option is empty or carries the legacy
        // flat shape these abilities used to write. Rebuild the canonical
        // envelope in that case; otherwise preserve any sibling metadata.
        if (!isset($envelope['settings']) || !is_array($envelope['settings'])) {
            $envelope = [];
        }
        $envelope['settings'] = $merged;
        $write = bd_write_global_settings($envelope);
        if ($write instanceof WP_Error) {
            return $write;
        }

        return ['success' => true, 'settings' => $merged];
    });
}
