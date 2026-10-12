<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Oxygen Global Settings is the top-level configuration tree the
 * builder exposes under "Global Styles" — typography, colors, button
 * styles, container defaults, WooCommerce defaults, etc. The whole
 * tree is stored as a single JSON blob in the
 * `global_settings_json_string` global option.
 */

// get-global-settings -------------------------------------------------------

wp_register_ability('novamira/oxygen-get-global-settings', [
    'label' => __('Get Oxygen Global Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the persisted Global Settings tree plus `available_sections` discovered from Oxygen\'s live control registry. Base Oxygen 6 disables this surface, so an empty available_sections list is authoritative; optional extensions may re-enable sections.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'description' => 'Optional, return only this top-level section (e.g. `typography`).',
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
            'available_sections' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_global_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use available_sections as the writable schema. Empty means base Oxygen has disabled Global Settings; do not invent sections.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// edit-global-settings ------------------------------------------------------

wp_register_ability('novamira/oxygen-edit-global-settings', [
    'label' => __('Edit Oxygen Global Settings', domain: 'novamira-pro'),
    'description' => __(
        'Patches only top-level Global Settings sections exposed by Oxygen\'s live control registry. Unknown sections are rejected before write; base Oxygen normally exposes none unless an extension enables them. Merge is shallow: pass the whole discovered section object, or null to remove it.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'description' => 'Top-level patch: keys here replace corresponding sections in the stored settings.',
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
            'verification' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_edit_global_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call get-global-settings first and use only available_sections. The ability rejects unknown sections and returns the observed persisted settings.',
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
function oxygen_get_global_settings(array $input): array
{
    $settings = ox_read_global_settings();
    $sections = array_keys($settings);
    $available = ox_global_settings_allowed_sections();
    if (isset($input['section']) && is_string($input['section']) && $input['section'] !== '') {
        $key = $input['section'];
        return [
            'success' => true,
            'settings' => isset($settings[$key]) && is_array($settings[$key]) ? $settings[$key] : [],
            'sections' => $sections,
            'available_sections' => $available,
        ];
    }
    return [
        'success' => true,
        'settings' => $settings,
        'sections' => $sections,
        'available_sections' => $available,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_edit_global_settings(array $input): array|WP_Error
{
    if (!isset($input['settings']) || !is_array($input['settings'])) {
        return ox_error('oxygen_invalid_input', __('`settings` must be an object.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed> $patch */
    $patch = [];
    $allowed = ox_global_settings_allowed_sections();
    if ($allowed === []) {
        return ox_error('oxygen_invalid_input', __(
            'This Oxygen runtime exposes no Global Settings sections. Base Oxygen 6 disables this surface; install/activate an extension that registers sections before writing.',
            domain: 'novamira-pro',
        ));
    }
    /** @var mixed $value */
    foreach ($input['settings'] as $key => $value) {
        if (!is_string($key)) {
            return ox_error('oxygen_invalid_input', __(
                'Global Settings section names must be strings.',
                domain: 'novamira-pro',
            ));
        }
        if (!in_array($key, $allowed, strict: true)) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('Unknown Global Settings section `%1$s`. Live sections: %2$s.', domain: 'novamira-pro'),
                $key,
                implode(', ', $allowed),
            ));
        }
        if ($value !== null && !is_array($value)) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('Global Settings section `%s` must be an object or null.', domain: 'novamira-pro'),
                $key,
            ));
        }

        $patch[$key] = $value;
    }

    // `global_settings_json_string` is a read-modify-write option shared
    // by every setting key. Without a lock parallel writes lost ~33% of
    // patches under Breakdance testing. Serialise on a dedicated slot
    // key — separate from variables / global_classes so unrelated
    // surfaces don't block each other.
    return ox_with_lock('global_settings', static function () use ($patch): array|WP_Error {
        $existing = ox_read_global_settings();
        $merged = array_merge($existing, $patch);
        /** @var mixed $value */
        foreach ($patch as $key => $value) {
            if ($value !== null) {
                continue;
            }

            unset($merged[$key]);
        }
        $written = ox_write_global_settings($merged);
        if ($written instanceof WP_Error) {
            return $written;
        }

        $observed = ox_read_global_settings();
        return [
            'success' => true,
            'settings' => $observed,
            'verification' => [
                'persisted' => $observed === $merged,
                'applied_sections' => array_keys($patch),
                'dropped_sections' => [],
            ],
        ];
    });
}
