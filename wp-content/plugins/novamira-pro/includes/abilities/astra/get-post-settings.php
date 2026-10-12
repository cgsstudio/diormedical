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
 * Ability: read per-post Astra display overrides (raw meta + global fallback).
 */

wp_register_ability('novamira/astra-get-post-settings', [
    'label' => __('Get Astra Post Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the per-post Astra display overrides for a given post. `raw` contains the values stored directly in post meta (empty string when not set); `resolved` contains the effective values including global Customizer fallback via `astra_get_option_meta()`; `has_override` is true for each key that has an explicit post-level value. Use `astra-set-post-settings` to write overrides, `astra-clear-post-settings` to revert to global.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to read overrides for. Must be a published or draft post — trashed posts and revisions return a 404 error.',
                'minimum' => 1,
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'raw' => [
                'type' => 'object',
                'description' => 'Values stored directly in post meta. Empty string means the key is not set (inherits global Customizer).',
                'additionalProperties' => true,
            ],
            'resolved' => [
                'type' => 'object',
                'description' => 'Effective values including global Customizer fallback via astra_get_option_meta().',
                'additionalProperties' => true,
            ],
            'has_override' => [
                'type' => 'object',
                'description' => 'True for each key that has an explicit post-level override (raw value is not empty string).',
                'additionalProperties' => ['type' => 'boolean'],
            ],
        ],
        'required' => ['post_id', 'raw', 'resolved', 'has_override'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_post_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read the per-post Astra display overrides. has_override[key]=true means the post carries an explicit setting that overrides the global Customizer. has_override[key]=false means the key inherits from the global. Use astra-clear-post-settings to revert individual keys to global.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_get_post_settings(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    $post_id = astra_coerce_int($input['post_id'] ?? 0);
    $post_or_err = astra_post_or_error($post_id);
    if ($post_or_err instanceof WP_Error) {
        return $post_or_err;
    }

    $raw = [];
    $resolved = [];
    $has_override = [];

    foreach (astra_post_meta_allowlist() as $key) {
        // Astra calls register_post_meta() with a `default` for several keys,
        // so get_post_meta() returns the registered default even when no actual
        // DB row exists. Use metadata_exists() to distinguish a real stored
        // override from the registered-default fallback.
        $row_exists = metadata_exists('post', $post_id, $key);

        // raw: the actual stored value, or '' when no DB row exists.
        /** @var mixed $raw_val */
        $raw_val = $row_exists ? get_post_meta($post_id, $key, single: true) : '';
        // Normalize false (shouldn't happen with single:true + row_exists, but guard anyway).
        if ($raw_val === false) {
            $raw_val = '';
        }

        // Cap large string values (background JSON can exceed 2 KB when serialized
        // by older Astra; background arrays pass through uncapped).
        /** @var mixed $capped */
        $capped = is_string($raw_val) ? astra_cap_bytes($raw_val, limit: 8192) : $raw_val;
        $raw[$key] = $capped;

        // Resolved: effective value with global Customizer fallback.
        if (!function_exists('astra_get_option_meta')) {
            $resolved[$key] = $capped;
            $has_override[$key] = $row_exists;
            continue;
        }
        /** @var mixed $res_val */
        $res_val = astra_get_option_meta($key, default: '', only_meta: false, extension: '', post_id: $post_id);
        $resolved[$key] = is_string($res_val) ? astra_cap_bytes($res_val, limit: 8192) : $res_val;

        $has_override[$key] = $row_exists;
    }

    return [
        'post_id' => $post_id,
        'raw' => $raw,
        'resolved' => $resolved,
        'has_override' => $has_override,
    ];
}
