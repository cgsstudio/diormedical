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
 * Ability: clear per-post Astra display overrides (revert to global Customizer).
 */

wp_register_ability('novamira/astra-clear-post-settings', [
    'label' => __('Clear Astra Post Settings', domain: 'novamira-pro'),
    'description' => __(
        'Removes per-post Astra display overrides, reverting the specified keys (or all 20 keys when `keys` is omitted) to their global Customizer values. Only the post-meta bindings are deleted — the post content and global Astra settings are untouched. Idempotent: a second call after a successful clear puts every key in `no_op`.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to clear overrides for.',
                'minimum' => 1,
            ],
            'keys' => [
                'type' => 'array',
                'description' => 'Subset of meta keys to clear. When omitted, ALL 20 per-post meta keys are cleared and the post fully reverts to global Astra settings. An empty array returns an error — omit the field to clear all.',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'cleared' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys whose post meta was actually deleted.',
            ],
            'unknown_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys in `keys` that are not in the allowlist — skipped.',
            ],
            'no_op' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys that had no post meta to delete (already unset). Not an error.',
            ],
        ],
        'required' => ['post_id', 'cleared', 'unknown_keys', 'no_op'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_clear_post_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Clears per-post Astra display overrides, reverting keys to the global Customizer values. Omit keys to clear all 20 meta fields at once. Idempotent: a second call puts all keys in no_op.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_clear_post_settings(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $post_id = astra_coerce_int($input['post_id'] ?? 0);
    $post_or_err = astra_post_or_error($post_id);
    if ($post_or_err instanceof WP_Error) {
        return $post_or_err;
    }

    $allowlist = astra_post_meta_allowlist();
    $allowlist_idx = array_flip($allowlist);

    $unknown = [];
    $target_keys = $allowlist;

    if (array_key_exists('keys', $input)) {
        $target_keys = [];
        $keys_raw = astra_coerce_array($input['keys']) ?? [];
        if ($keys_raw === []) {
            return astra_invalid_input_error('keys must contain at least one key, or omit the field to clear all.');
        }
        /** @var mixed $k */
        foreach ($keys_raw as $k) {
            if (!is_string($k)) {
                continue;
            }
            if (!array_key_exists($k, $allowlist_idx)) {
                $unknown[] = $k;
                continue;
            }
            $target_keys[] = $k;
        }
    }

    $cleared = [];
    $no_op = [];

    foreach ($target_keys as $key) {
        $deleted = delete_post_meta($post_id, $key);
        if ($deleted) {
            $cleared[] = $key;
            continue;
        }
        $no_op[] = $key;
    }

    if ($cleared !== []) {
        clean_post_cache($post_id);
    }

    return [
        'post_id' => $post_id,
        'cleared' => $cleared,
        'unknown_keys' => $unknown,
        'no_op' => $no_op,
    ];
}
