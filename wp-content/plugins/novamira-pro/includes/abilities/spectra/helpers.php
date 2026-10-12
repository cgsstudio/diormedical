<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Spectra (UAGB) integration.
 *
 * Cross-cutting utilities every ability file leans on: uniform error
 * factories with stable codes tests assert on, the popup CPT + meta
 * accessors (which absorb the `clean_post_cache` gotcha in one place),
 * and the atomic read-modify-write helper for the `_uagb_blocks`
 * activation registry.
 */

// -----------------------------------------------------------------------------
// Error factories — stable codes; do not rename.
// -----------------------------------------------------------------------------

/**
 * Emitted by every non-check-setup ability when the Spectra plugin is
 * missing or below the min-supported floor. Callers surface it as the
 * "install / activate the plugin" signal — the check-setup ability is
 * the one place that returns a shaped state instead.
 */
function sp_not_active(): WP_Error
{
    return new WP_Error(
        'spectra_not_active',
        __(
            'Spectra plugin is not active at or above the minimum supported version. Call novamira/spectra-check-setup for details.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Emitted by every Pro-layer ability when Spectra Pro is missing or below
 * its own min-supported floor. Kept separate from `sp_not_active` so
 * tools can distinguish "no plugin" from "no Pro add-on".
 */
function sp_pro_required(): WP_Error
{
    return new WP_Error(
        'spectra_pro_required',
        __(
            'This ability requires the Spectra Pro add-on plugin. It is optional — free abilities work without it.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Uniform "input failed validation" error. `$detail` is the per-callsite
 * reason. Keep the code stable — smoke + audit assert on it.
 */
function sp_invalid_input(string $detail): WP_Error
{
    return new WP_Error('spectra_invalid_input', $detail, ['status' => 400]);
}

/**
 * Uniform "unknown / unsupported settings key" error. Every write path
 * that rejects a payload key outside its allowlist emits this so an agent
 * can distinguish "you spelled the key wrong" from generic input errors.
 */
function sp_unknown_setting(string $key): WP_Error
{
    return new WP_Error(
        'spectra_unknown_setting',
        /* translators: %s: settings key */
        sprintf(__('Unknown or unsupported Spectra setting: %s', domain: 'novamira-pro'), $key),
        ['status' => 400],
    );
}

/**
 * Emitted by `get-block-schema` and any Pro-layer schema reader when the
 * requested block name is not registered on this install (typo, block
 * disabled via `_uagb_blocks`, or Pro block requested on a free install).
 */
function sp_block_not_found(string $block_name): WP_Error
{
    return new WP_Error(
        'spectra_block_not_found',
        /* translators: %s: block name */
        sprintf(__('Spectra block not registered on this install: %s', domain: 'novamira-pro'), $block_name),
        ['status' => 404],
    );
}

/**
 * Emitted whenever a popup id argument does not resolve to a post of the
 * Spectra popup CPT — auto-drafts, trashed posts, revisions, and mismatched
 * post types all end here.
 */
function sp_popup_not_found(int $id): WP_Error
{
    return new WP_Error(
        'spectra_popup_not_found',
        /* translators: %d: popup post id */
        sprintf(__('Spectra popup #%d not found or not a popup post.', domain: 'novamira-pro'), $id),
        ['status' => 404],
    );
}

// -----------------------------------------------------------------------------
// Popup CPT accessors.
// -----------------------------------------------------------------------------

/**
 * Resolve a popup id to a `WP_Post` of the `spectra-popup` post type or
 * return a `spectra_popup_not_found` error. Rejects auto-drafts, trashed
 * posts, and revisions per the standard gate every read/write path shares.
 * The `manage_options` capability check inside the callback (popup CPT is
 * admin-only per UAGB registration) stays in the ability, not here.
 */
function sp_post_or_error(int $id): WP_Post|WP_Error
{
    if ($id <= 0) {
        return sp_popup_not_found($id);
    }
    /** @var WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post) {
        return sp_popup_not_found($id);
    }
    if ($post->post_type !== SPECTRA_POPUP_POST_TYPE) {
        return sp_popup_not_found($id);
    }
    if (in_array($post->post_status, ['auto-draft', 'trash'], strict: true)) {
        return sp_popup_not_found($id);
    }
    return $post;
}

/**
 * Read the standard popup meta bundle in one call — returns the three
 * canonical UAGB keys with typed defaults so ability output never leaks
 * an inconsistent shape when the meta rows are missing (fresh popup
 * before any save).
 *
 * @return array{type:string, enabled:bool, repetition:int}
 */
function sp_popup_meta_read(int $id): array
{
    /** @var mixed $type */
    $type = get_post_meta($id, SPECTRA_POPUP_META_TYPE, single: true);
    /** @var mixed $enabled */
    $enabled = get_post_meta($id, SPECTRA_POPUP_META_ENABLED, single: true);
    /** @var mixed $repetition */
    $repetition = get_post_meta($id, SPECTRA_POPUP_META_REPETITION, single: true);
    return [
        'type' => is_string($type) && $type !== '' ? $type : 'modal',
        'enabled' => sp_popup_enabled_flag($enabled),
        'repetition' => is_numeric($repetition) ? (int) $repetition : 0,
    ];
}

/**
 * Normalize the polymorphic `spectra-popup-enabled` meta value into a
 * strict boolean. UAGB stores it inconsistently across releases (`'1'`
 * from the admin UI, `1` from REST writes, `true` from the JS editor,
 * `'on'` from raw form submissions) so a single-branch `in_array`
 * strict-check would misclassify half the corpus. Kept as a helper so
 * both the reader (`sp_popup_meta_read`) and the query filter
 * (`spectra_list_popups`) share the same truthiness contract.
 */
function sp_popup_enabled_flag(mixed $raw): bool
{
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_int($raw)) {
        return $raw === 1;
    }
    if (is_string($raw)) {
        return in_array($raw, ['1', 'on', 'true'], strict: true);
    }
    return false;
}

/**
 * Persist any subset of the popup meta bundle. `update_post_meta` alone
 * does not fire `save_post`, so a stale post cache would leak the old
 * values to any consumer reading `get_post()` inside the same request —
 * the `clean_post_cache` call here absorbs that gotcha for every popup
 * write path.
 *
 * @param array{type?:string, enabled?:bool, repetition?:int} $updates
 */
function sp_popup_meta_write(int $id, array $updates): void
{
    if (array_key_exists('type', $updates)) {
        update_post_meta($id, SPECTRA_POPUP_META_TYPE, $updates['type']);
    }
    if (array_key_exists('enabled', $updates)) {
        update_post_meta($id, SPECTRA_POPUP_META_ENABLED, $updates['enabled'] ? '1' : '');
    }
    if (array_key_exists('repetition', $updates)) {
        update_post_meta($id, SPECTRA_POPUP_META_REPETITION, (int) $updates['repetition']);
    }
    clean_post_cache($id);
}

// -----------------------------------------------------------------------------
// Options-level read / write — the `_uagb_blocks` activation registry.
// -----------------------------------------------------------------------------

/**
 * Read a JSON-blob option that may be stored either as a native PHP array
 * (WordPress unserialised on read) or as a JSON string. Used by the
 * `_uagb_blocks` registry read paths where UAGB's own history has
 * flip-flopped between the two representations across releases.
 *
 * @return array<string, mixed>
 */
function sp_option_read_json(string $option): array
{
    /** @var mixed $raw */
    $raw = get_option(option: $option, default_value: []);
    if (is_array($raw)) {
        // @mago-expect analysis:less-specific-return-statement
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        /** @var mixed $decoded */
        $decoded = json_decode($raw, associative: true);
        if (is_array($decoded)) {
            // @mago-expect analysis:less-specific-return-statement
            return $decoded;
        }
    }
    return [];
}

/**
 * Atomic read-modify-write on a JSON-shaped option. Callers pass a
 * mutator closure that receives the current array and returns the new
 * one; the helper handles cache invalidation on the way in AND out so
 * a stale write to `_uagb_blocks` never leaves the deactivation
 * registry inconsistent with what the front-end enqueues.
 *
 * Not transaction-safe across concurrent HTTP requests — that's a
 * documented limit accepted for admin-only write paths per the plan
 * (option-level advisory locking is overkill for editor-triggered
 * settings toggles). If we ever expose these writes to an unauthenticated
 * REST route the constraint must be revisited.
 *
 * @param callable(array<string, mixed>): array<string, mixed> $mutator
 */
function sp_option_write_json_atomic(string $option, callable $mutator): void
{
    wp_cache_delete($option, group: 'options');
    $current = sp_option_read_json($option);
    $next = $mutator($current);
    update_option(option: $option, value: $next);
    wp_cache_delete($option, group: 'options');
}

/**
 * Invalidate the caches UAGB touches after any block-activation or
 * global-setting change. Called by every write path that mutates the
 * enqueue behaviour — omitting it leaves the front end serving old
 * page-CSS bundles for the current request.
 */
function sp_invalidate_spectra_cache(): void
{
    delete_transient('spectra_assets_files_indicator');
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('uagb');
    }
}

// -----------------------------------------------------------------------------
// WP-native input aliases — every create/edit ability that wraps a WP-core
// post writer accepts both the short name (title, content, …) and the WP
// native alias (post_title, post_content, …). Kept as a table so the two
// callers stay consistent — no drift between which alias is honored where.
// -----------------------------------------------------------------------------

/**
 * Short-name → WP-native-alias map. Every ability that
 * wraps a WP core post writer honors BOTH the short and the WP-native
 * form for these fields.
 *
 * @var array<string, string>
 */
const SPECTRA_WP_ALIAS_MAP = [
    'title' => 'post_title',
    'content' => 'post_content',
    'status' => 'post_status',
    'slug' => 'post_name',
];

/**
 * Resolve a WP-native alias for a given field. Returns the input value at
 * the short-name key or its WP-native key, whichever is present. When BOTH
 * are provided the short-name wins — callers surface that as a validation
 * error via `sp_alias_conflict()` if strict semantics are required.
 *
 * @param array<string, mixed> $input
 */
function sp_pick_alias(array $input, string $short): mixed
{
    if (array_key_exists($short, $input)) {
        return $input[$short];
    }
    $native = SPECTRA_WP_ALIAS_MAP[$short] ?? $short;
    return $input[$native] ?? null;
}

/**
 * Whether the caller passed BOTH the short and the WP-native form of the
 * same field. Write abilities call this before merging — passing both is
 * almost always a template bug in the caller, so returning an error is
 * safer than silently picking one.
 *
 * @param array<string, mixed> $input
 */
function sp_has_alias_conflict(array $input, string $short): bool
{
    $native = SPECTRA_WP_ALIAS_MAP[$short] ?? $short;
    if ($short === $native) {
        return false;
    }
    return array_key_exists($short, $input) && array_key_exists($native, $input);
}

/**
 * Uniform "same field provided under two aliases" error emitted by every
 * write path that catches an alias conflict.
 */
function sp_alias_conflict(string $short): WP_Error
{
    $native = SPECTRA_WP_ALIAS_MAP[$short] ?? $short;
    return new WP_Error(
        'spectra_alias_conflict',
        /* translators: 1: short-name field, 2: WP-native field */
        sprintf(
            __(
                'Both `%1$s` and `%2$s` were provided. Pass only one — they are aliases of the same field.',
                domain: 'novamira-pro',
            ),
            $short,
            $native,
        ),
        ['status' => 400],
    );
}
