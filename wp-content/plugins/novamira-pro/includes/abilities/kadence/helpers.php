<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Kadence;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Kadence theme integration.
 *
 * Provides:
 *  - kd_error()                — uniform WP_Error factory for this surface
 *  - kd_storage_meta()         — option name + storage type Kadence uses
 *  - kd_cpt_entities()         — slug -> active map for the theme CPTs
 *  - kd_block_editor_entities() — the same map with human-readable labels
 */

/**
 * Builds the WP_Error this surface returns. Keeps the `kadence_*` code
 * prefix consistent and centralises the translator-comment shape.
 *
 * @param string $code  Error code, must start with `kadence_`.
 */
function kd_error(string $code, string $message): WP_Error
{
    return new WP_Error($code, $message);
}

/**
 * Reports the storage strategy Kadence is using for theme settings:
 * either `theme_mod` (default since 1.x) or `option` (advanced users
 * who flipped the storage type via `kadence_theme_option_type` filter).
 * The companion filter `kadence_theme_option_name` swaps the bag name
 * under `option` storage. Centralised so abilities don't reimplement
 * the lookup.
 *
 * @return array{type: string, option_name: string}
 */
function kd_storage_meta(): array
{
    $tags = kadence_theme_tags();
    if ($tags === null) {
        return ['type' => '', 'option_name' => ''];
    }
    return [
        'type' => $tags->get_option_type(),
        'option_name' => $tags->get_option_name(),
    ];
}

/**
 * Returns the slug -> registered map for the Kadence-introduced CPTs the
 * integration cares about (Header Builder, Navigation Builder, Advanced
 * Forms). Used by check-setup to advertise which subsystem entities are
 * available on the current install.
 *
 * @return array<string, bool>
 */
function kd_cpt_entities(): array
{
    return [
        'kadence_header' => post_type_exists('kadence_header'),
        'kadence_navigation' => post_type_exists('kadence_navigation'),
        'kadence_form' => post_type_exists('kadence_form'),
    ];
}

/**
 * PHP 8.0 polyfill for `array_is_list()` (native in 8.1+). Mirrors the
 * canonical implementation other specializations use
 * (bricks_array_is_list / el_array_is_list / mb_array_is_list) — checks
 * that the array's keys are exactly the contiguous 0..count-1 sequence
 * an empty array is considered a list. The minimum PHP supported by
 * Novamira Pro is 8.0 (see plugin header `Requires PHP: 8.0`), so the
 * polyfill is required for parity.
 *
 * @param array<array-key, mixed> $value
 */
function kd_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * Defensive cast for an ability `input` field that the JSON Schema
 * declares as array-typed but that PHP's REST validator may still let
 * a scalar slip through. Returns the value as an array when possible,
 * or null when the input is neither array nor castable. Encapsulated
 * here so mago sees a `mixed`-typed input and accepts the runtime
 * branch without flagging it as redundant.
 *
 * @param mixed $value
 * @return array<array-key, mixed>|null
 */
function kd_coerce_array(mixed $value): ?array
{
    return is_array($value) ? $value : null;
}

/**
 * Cooperative cross-request lock around a critical section that touches
 * a single shared wp_options row (the kadence_settings bag or the
 * theme_mods_<stylesheet> bag). Round 2 audit B4 found that without
 * a lock, 8 parallel edit-settings REST calls produce ~87% silent data
 * loss because each request reads the bag before the previous one's
 * write commits.
 *
 * Uses MySQL's `GET_LOCK` advisory lock when available; on backends that
 * don't support it (SQLite drop-in, some managed hosts) the call falls
 * through without locking and the critical section runs unprotected —
 * still strictly better than no batching at all. Lock name is scoped by
 * blog id so a multisite install does not have cross-site contention on
 * the same option key.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function kd_with_options_lock(string $option_name, callable $fn): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    // MySQL caps GET_LOCK at 64 chars. `nvp_kd_` (7) + blog_id (up to 10)
    // + `_` (1) + md5 (32) = 50, safely under the cap.
    $lock_name = sprintf('nvp_kd_%d_%s', get_current_blog_id(), md5($option_name));

    /** @var mixed $acquired_raw */
    // @mago-expect analysis:possibly-invalid-argument
    $acquired_raw = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    $acquired = $acquired_raw === '1' || $acquired_raw === 1;

    try {
        return $fn();
    } finally {
        if ($acquired) {
            // @mago-expect analysis:possibly-invalid-argument
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}
