<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-clear-translation-cache', [
    'label' => __('Clear Weglot Translation Cache', domain: 'novamira-pro'),
    'description' => __(
        'Deletes the Weglot local CDN cache transients: `weglot_cache_cdn` (the merged options snapshot, default 300s TTL) and `weglot_slugs_cache` (translated URL slug cache). Also busts the per-request user-info cache used by check-setup and get-status. The next page load (or ability invocation that touches the cloud) re-fetches fresh data. Use this when local data appears stale after upstream cloud edits or after a settings push.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'cleared_transients' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of wp_transient keys actually deleted; an entry absent here was already not set.',
            ],
            'user_info_cache_busted' => [
                'type' => 'boolean',
                'description' => 'Whether the in-process user-info cache (used by check-setup / get-status) was reset.',
            ],
        ],
        'required' => ['cleared_transients', 'user_info_cache_busted'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_clear_translation_cache',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cheap and side-effect-free beyond cache invalidation. Returns the list of transient keys actually deleted; an entry not in `cleared` was already absent.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{cleared_transients: list<string>, user_info_cache_busted: bool}
 */
function weglot_clear_translation_cache(): array
{
    $cleared = [];
    foreach (['weglot_cache_cdn', 'weglot_slugs_cache'] as $key) {
        if (!delete_transient($key)) {
            continue;
        }
        $cleared[] = $key;
    }
    wg_user_info_cache_bust();
    return ['cleared_transients' => $cleared, 'user_info_cache_busted' => true];
}
