<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Create the search-index table for post types this plugin created.
 *
 * Voxel builds a per-post-type `voxel_index_<key>` table, and its admin recreates
 * one on every post-type save (post-type-controller.php:331). Nothing in the
 * ability surface did, so a post type created through `create-post-type` had no
 * table at all: every index write failed with a swallowed SQL error and the type
 * stayed invisible to Voxel's front-end search, while the abilities still
 * reported success.
 *
 * The table cannot be created in the same request that writes the config. Voxel
 * registers post types on `init` priority 0 and `\Voxel\Post_Type::get()` returns
 * null for anything WordPress has not registered yet, so the Index_Table object
 * simply does not exist until the following request. `create-post-type` therefore
 * leaves a marker and this handler picks it up.
 *
 * Three constraints, each one a trap that was verified rather than assumed:
 *
 * 1. One option per key, never a shared list. Removing a key from a shared option
 *    is a read-modify-write, and a lost update there would drop a marker whose
 *    table was never created — nobody would ever retry, which is precisely the
 *    bug being fixed. `vx_with_lock()` is no defence: it degrades to running
 *    completely unlocked when the backend implements neither an atomic
 *    `wp_cache_add` nor `GET_LOCK` (config-helpers.php, `vx_acquire_lock`).
 *
 * 2. Never `Index_Table::exists()` as the exit condition. It also checks for the
 *    `post_status` and `priority` columns, which Voxel added in later versions,
 *    so on a table built by an older Voxel it returns false forever. Since
 *    `create()` is `CREATE TABLE IF NOT EXISTS` it would never repair it, and
 *    `recreate()` drops live data. The loop would never converge on a perfectly
 *    healthy site, so a plain existence check is the honest exit condition.
 *
 *    The cost of that choice, stated plainly: a table with an outdated schema
 *    looks healthy to this handler and to `voxel-check-setup`, whose
 *    `index_tables_missing` compares presence against presence. Indexing keeps
 *    failing on the missing column and nothing reports it. Surfacing that needs a
 *    separate diagnostic built on `exists()` — its one honest use, as a report
 *    rather than a loop condition — which is not implemented here.
 *
 * 3. Never `recreate()`. It is `drop()` + `create()` and would wipe a populated
 *    index.
 */

/**
 * Option name holding the pending marker for one post type key.
 */
function vx_index_pending_option(string $key): string
{
    return 'novamira_voxel_index_pending:' . sanitize_key($key);
}

/**
 * Option name for the "rewrite rules are stale" flag.
 */
function vx_rewrite_pending_option(): string
{
    return 'novamira_voxel_rewrite_pending';
}

/**
 * Note that the rewrite rules no longer match the configuration.
 *
 * Creating, editing or deleting a post type or taxonomy changes which permalinks
 * should exist, but WordPress builds its rules from what is *registered*, and
 * registration reflects the config as it was when `init` ran. Flushing in the
 * same request therefore regenerates the old rules: a new taxonomy's term
 * archives 404 (verified), and a deleted one leaves its rule behind.
 *
 * A single flag rather than one marker per key, because a flush is global — one
 * pass fixes every pending change. Unlike the index markers, a lost update here
 * is not permanent: it costs one missed flush, and the next structural change
 * sets the flag again.
 */
function vx_mark_rewrite_pending(): void
{
    update_option(vx_rewrite_pending_option(), value: 1, autoload: true);
}

/**
 * Rebuild the rewrite rules once, if anything asked for it.
 *
 * The soft flush is enough: it regenerates the rules WordPress holds without
 * rewriting .htaccess, which is what a permalink for a new post type or taxonomy
 * actually needs.
 */
function vx_flush_pending_rewrite_rules(): void
{
    if (!get_option(vx_rewrite_pending_option())) {
        return;
    }
    delete_option(vx_rewrite_pending_option());
    flush_rewrite_rules(false);
}

/**
 * How many times a key is retried before it is parked as failed.
 */
function vx_index_heal_max_attempts(): int
{
    return 3;
}

/**
 * Seconds to wait between attempts, so a permanent failure costs one try per
 * quarter hour instead of one per request.
 */
function vx_index_heal_retry_delay(): int
{
    return 900;
}

/**
 * Mark a post type as needing its index table built on a later request.
 */
function vx_mark_index_pending(string $key): void
{
    $safe = sanitize_key($key);
    if ($safe === '') {
        return;
    }
    update_option(
        vx_index_pending_option($safe),
        ['key' => $safe, 'attempts' => 0, 'last_attempt' => 0, 'status' => 'pending'],
        autoload: true,
    );
}

/**
 * Every pending marker, read from the already-loaded autoload cache so a request
 * with nothing to do costs no query at all.
 *
 * @return list<string> post type keys
 */
function vx_pending_index_keys(): array
{
    $prefix = 'novamira_voxel_index_pending:';
    /** @var mixed $all */
    $all = wp_load_alloptions();
    if (!is_array($all)) {
        return [];
    }
    $keys = [];
    /** @var mixed $name */
    foreach (array_keys($all) as $name) {
        if (!is_string($name) || !str_starts_with($name, $prefix)) {
            continue;
        }
        $keys[] = substr($name, strlen($prefix));
    }
    return $keys;
}

/**
 * Build the missing index tables. Runs on `wp_loaded`: post types are registered
 * by then, and unlike `init` there is no ordering to get right.
 */
function vx_heal_pending_index_tables(): void
{
    if (!vx_min_satisfied()) {
        return;
    }
    vx_flush_pending_rewrite_rules();
    foreach (vx_pending_index_keys() as $key) {
        vx_heal_index_table($key);
    }
}

/**
 * Build one post type's index table, or record why it could not be built.
 */
function vx_heal_index_table(string $key): void
{
    $safe = sanitize_key($key);
    $option = vx_index_pending_option($safe);
    if ($safe === '' || !vx_index_heal_needed($safe)) {
        delete_option($option);
        return;
    }
    $state = vx_index_heal_state($option);
    if (!vx_index_heal_due($state)) {
        return;
    }
    if (vx_create_index_table($safe)) {
        delete_option($option);
        return;
    }
    $attempts = $state['attempts'] + 1;
    update_option(
        $option,
        [
            'key' => $safe,
            'attempts' => $attempts,
            'last_attempt' => time(),
            'status' => $attempts >= vx_index_heal_max_attempts() ? 'failed' : 'pending',
        ],
        autoload: true,
    );
}

/**
 * Whether this key still needs a table: it is configured, and the table is not
 * there yet.
 *
 * The check is a plain lookup, deliberately not Index_Table::exists() — see the
 * note at the top of this file. And the config, not registration, decides what
 * should exist: post-types-controller.php:62 skips a key another plugin already
 * registered, so get_post_type_object() can hand back a foreign post type.
 */
function vx_index_heal_needed(string $safe): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if (vx_table_exists($wpdb->prefix . 'voxel_index_' . $safe)) {
        return false;
    }
    return array_key_exists($safe, vx_read_post_types_config());
}

/**
 * The stored retry state, normalized.
 *
 * @return array{attempts: int, last_attempt: int, status: string}
 */
function vx_index_heal_state(string $option): array
{
    /** @var mixed $stored */
    $stored = get_option($option);
    if (!is_array($stored)) {
        return ['attempts' => 0, 'last_attempt' => 0, 'status' => 'pending'];
    }
    return [
        'attempts' => is_int($stored['attempts'] ?? null) ? $stored['attempts'] : 0,
        'last_attempt' => is_int($stored['last_attempt'] ?? null) ? $stored['last_attempt'] : 0,
        'status' => is_string($stored['status'] ?? null) ? $stored['status'] : 'pending',
    ];
}

/**
 * Whether another attempt is allowed now.
 *
 * A parked key keeps its marker as evidence but stops costing anything; the gap
 * stays visible through voxel-check-setup's `index_tables_missing`. Without this
 * ceiling a table that cannot be created — no CREATE grant, a full disk — would
 * be retried on every single request forever.
 *
 * @param array{attempts: int, last_attempt: int, status: string} $state
 */
function vx_index_heal_due(array $state): bool
{
    if ($state['status'] === 'failed') {
        return false;
    }
    if ($state['attempts'] === 0) {
        return true;
    }
    return (time() - $state['last_attempt']) >= vx_index_heal_retry_delay();
}

/**
 * Ask Voxel to build the table, and confirm it landed.
 *
 * `Index_Table::create()` returns nothing and swallows database errors, so the
 * only trustworthy answer is to look for the table afterwards.
 */
function vx_create_index_table(string $key): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if (!class_exists('Voxel\\Post_Type')) {
        return false;
    }
    $post_type = \Voxel\Post_Type::get($key);
    if (!is_object($post_type) || !method_exists($post_type, 'get_index_table')) {
        return false;
    }
    try {
        $post_type->get_index_table()->create();
    } catch (\Throwable) {
        return false;
    }
    return vx_table_exists($wpdb->prefix . 'voxel_index_' . $key);
}

// This file is reached two ways, and they arrive at opposite ends of the request.
//
// Through the ability loader, which runs on `wp_abilities_api_init` — an action
// that is lazy, firing only when something instantiates WP_Abilities_Registry,
// well after `wp_loaded`. Registering a `wp_loaded` callback there would be
// registering for an action that has already fired, and the healing would never
// run at all. Verified at runtime: the marker survived with attempts still at 0.
//
// And through the eager bootstrap in includes/manifest.php, which includes this
// file *during* `wp_loaded` so that ordinary front-end and admin requests get the
// maintenance too — the ability loader alone would restrict it to MCP traffic.
//
// Both entry points land inside or after `wp_loaded`, so in practice the inline
// branch is the one that runs. The `add_action` is kept for the case where this
// file is ever included earlier. When nothing is pending the whole thing is one
// pass over the already-loaded autoload array, with no query at all.
function vx_schedule_index_healing(): void
{
    if (!did_action('wp_loaded')) {
        add_action('wp_loaded', callback: 'Novamira\\Pro\\Abilities\\Voxel\\vx_heal_pending_index_tables');
        return;
    }
    vx_heal_pending_index_tables();
}

vx_schedule_index_healing();
