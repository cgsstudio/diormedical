<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-get-translation-cache-state', [
    'label' => __('Get Weglot Translation Cache State', domain: 'novamira-pro'),
    'description' => __(
        'Reports the state of the Weglot local CDN cache transients (`weglot_cache_cdn` for the merged options snapshot, `weglot_slugs_cache` for translated URL slugs) Useful for debugging stale-local vs cloud-truth issues: tells the agent whether a cache entry exists, when it was last set (best-effort — WP transients do not expose creation time, so we report whether the row is present in wp_options and the timeout if available), and the default TTL Weglot configures.',
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
            'cache_cdn' => [
                'type' => 'object',
                'properties' => [
                    'present' => ['type' => 'boolean'],
                    'expires_at' => ['type' => ['integer', 'null']],
                    'seconds_remaining' => ['type' => ['integer', 'null']],
                ],
                'required' => ['present', 'expires_at', 'seconds_remaining'],
                'additionalProperties' => false,
            ],
            'slugs_cache' => [
                'type' => 'object',
                'properties' => [
                    'present' => ['type' => 'boolean'],
                    'expires_at' => ['type' => ['integer', 'null']],
                    'seconds_remaining' => ['type' => ['integer', 'null']],
                ],
                'required' => ['present', 'expires_at', 'seconds_remaining'],
                'additionalProperties' => false,
            ],
            'cdn_cache_default_ttl_seconds' => ['type' => 'integer'],
            'ttl_known' => [
                'type' => 'boolean',
                'description' => 'False when an external object cache (Redis/Memcached) is in use — wp_transient TTLs are not exposed through wp_options on those backends, so expires_at / seconds_remaining will read as null even for live cache entries.',
            ],
        ],
        'required' => ['cache_cdn', 'slugs_cache', 'cdn_cache_default_ttl_seconds', 'ttl_known'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_get_translation_cache_state',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only diagnostic. seconds_remaining is null when the cache is absent or the WP option timeout row was not stored (WP omits the timeout for permanent or zero-TTL transients). Compare with the cdn_cache_default_ttl_seconds (300 by default, overridable via the Weglot `weglot_get_options_from_cdn_cache_duration` filter) to estimate freshness.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{cache_cdn: array{present: bool, expires_at: int|null, seconds_remaining: int|null}, slugs_cache: array{present: bool, expires_at: int|null, seconds_remaining: int|null}, cdn_cache_default_ttl_seconds: int, ttl_known: bool}
 */
function weglot_get_translation_cache_state(): array
{
    $ttl_known = !(function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache());

    return [
        'cache_cdn' => wg_inspect_transient(name: 'weglot_cache_cdn'),
        'slugs_cache' => wg_inspect_transient(name: 'weglot_slugs_cache'),
        'cdn_cache_default_ttl_seconds' => 300,
        'ttl_known' => $ttl_known,
    ];
}

/**
 * Inspect a WP transient by reading the underlying `_transient_*` /
 * `_transient_timeout_*` options directly. WP exposes no public API to ask
 * "when does this transient expire", so we read the timeout option.
 *
 * @return array{present: bool, expires_at: int|null, seconds_remaining: int|null}
 */
function wg_inspect_transient(string $name): array
{
    $present = get_transient($name) !== false;
    /** @var mixed $timeout_raw */
    $timeout_raw = get_option('_transient_timeout_' . $name, default_value: 0);
    $expires_at = wg_coerce_int_or_null($timeout_raw) ?? 0;
    if (!$present || $expires_at <= 0) {
        return ['present' => $present, 'expires_at' => null, 'seconds_remaining' => null];
    }

    return [
        'present' => true,
        'expires_at' => $expires_at,
        'seconds_remaining' => max(0, $expires_at - (int) wg_now()),
    ];
}

/**
 * Wraps time() so unit-style smoke tests can mock the clock when needed.
 * Default behavior: standard wall clock.
 */
function wg_now(): int
{
    return time();
}
