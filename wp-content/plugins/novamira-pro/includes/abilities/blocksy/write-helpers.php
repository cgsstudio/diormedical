<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/** Maximum JSON-encoded byte size accepted for a single written option value. */
const BLY_MAX_WRITE_BYTES = 262_144;

/** Seconds to wait for the theme_mods advisory lock before failing the write. */
const BLY_MODS_LOCK_TIMEOUT = 5;

/**
 * Write infrastructure for the Blocksy theme integration (Sprint 2).
 *
 * Every customizer value lives in ONE shared wp_options row
 * (`theme_mods_<stylesheet>`), and Blocksy layers two caches on top of it,
 * so a correct server-side write is a fixed sequence (verified against
 * Blocksy 2.1.52 / Companion Pro 2.1.52 source):
 *
 *  1. Merge onto the CURRENT mods and persist into the row PINNED at the
 *     ability-callback entry (core set_theme_mod() recomputes
 *     `get_stylesheet()` at write time, so a hook switching themes
 *     mid-write would land the merged Blocksy mods in ANOTHER theme's
 *     row; the pinned identity is threaded through every read, the
 *     persist, the verification, the rollback and the cache-refresh
 *     decision, and a detected switch aborts with
 *     `bly_stylesheet_changed`). Every sibling mod is kept, the native
 *     `pre_set_theme_mod_<name>` filter fires BEFORE the final ownership
 *     checks, and the persist itself is a raw hook-free SQL write
 *     (bly_persist_mods_row_raw) VERIFIED by re-reading the row's raw
 *     bytes: only a persisted value identical to the intended one counts
 *     as success. Verification failure (e.g. a `pre_set_theme_mod_*`
 *     filter rewriting the value, or a database failure) rolls the row
 *     back to its exact prior bytes — and the rollback is itself
 *     verified. Exit invariant on every path: database row, Blocksy memo,
 *     and generated CSS are mutually consistent (`bly_write_failed` =
 *     prior state re-established; `bly_rollback_failed` = it could not
 *     be, caches refreshed to match the actual row).
 *  2. `blocksy_manager()->db->wipe_cache()` — `blocksy_get_theme_mod()`
 *     (inc/helpers/db.php:3) proxies `Blocksy\Database::get_theme_mod()`
 *     (inc/classes/database.php:8), which memoizes `get_theme_mods()` and
 *     only refreshes by itself in admin/customize-preview/AJAX requests;
 *     in the MCP/REST/CLI context this integration runs in, the memo is
 *     stale until `wipe_cache()` (inc/classes/database.php:31) resets it.
 *  3. `do_action('blocksy:dynamic-css:refresh-caches')` — the exact action
 *     the theme fires after a customizer save (inc/dynamic-css.php:19).
 *     Its listeners regenerate both dynamic-CSS caches from the fresh mods:
 *     the theme re-sets the `blocksy_dynamic_styles_descriptor` transient
 *     (inc/dynamic-css.php:22 → maybe_set_global_styles_descriptor(),
 *     inc/dynamic-css.php:360), and the companion rewrites the dynamic-CSS
 *     file cache `uploads/blocksy/css/global.css` in place
 *     (framework/features/dynamic-css.php:93 → generate_css_files(),
 *     framework/features/dynamic-css.php:176) whenever the site uses
 *     file-based CSS (`dynamic_css_file` mod = 'file', the default).
 *     Step 2 MUST precede step 3: the regenerators read the mods through
 *     the memoized `blocksy_get_theme_mod()`. Steps 2–3 run only after
 *     step 1's verification succeeds — a failed persist never touches the
 *     caches.
 *
 * Concurrency: the row-level merge makes lost updates possible when two
 * requests read the row before either writes. The whole sequence therefore
 * runs under a MySQL `GET_LOCK` advisory lock (scoped by blog id, database
 * name, AND table prefix so two WP installs sharing one MySQL server never
 * falsely contend) and FAILS CLOSED: when the lock cannot be acquired
 * (timeout, or a backend without advisory locks), the write is refused
 * with `bly_lock_unavailable` instead of running unprotected. Advisory
 * locks are CONNECTION-scoped, and wpdb silently reconnects after a
 * dropped connection — which releases the lock while the code believes it
 * is still held — so the connection id is captured ATOMICALLY with the
 * grant (one `SELECT GET_LOCK(), CONNECTION_ID()` query) and re-verified
 * (together with `IS_USED_LOCK()` ownership) AFTER the last user code has
 * run, immediately before the raw SQL persist; a lost lock aborts with
 * `bly_lock_lost` instead of writing unlocked, and the release path is a
 * no-op unless the connection is still the acquiring one. The mods are
 * re-read from the database INSIDE the
 * lock after busting the per-request option caches — a request that
 * waited on the lock would otherwise merge onto the alloptions snapshot
 * primed at its own bootstrap, i.e. onto pre-write state. Validation
 * failures return WP_Error before anything is persisted, so a failed call
 * never writes anything.
 *
 * Validation is two-gated: first the schema-derived typed checks below
 * (switch vocabulary, choice sets, numeric/bool/string leaf kinds —
 * applied to every device leaf of responsive maps and to the overlapping
 * keys of structured values), then Blocksy's OWN per-control sanitizer
 * `blocksy_validate_for()` (inc/customizer/validator.php:91, the exact
 * `sanitize_callback` the customizer installs via
 * `blocksy_include_sanitizer()`, validator.php:256). That sanitizer
 * signals rejection by returning the option's default; the wrapper below
 * converts that contract into a WP_Error instead of silently persisting
 * the default. Gate 2 fails CLOSED — if the sanitizer cannot run, the
 * write is refused with `bly_sanitizer_unavailable`, never bypassed.
 *
 * Both gates run at request start; choice vocabularies resolved from LIVE
 * site content (nav menus, pages, terms, content blocks — classified
 * explicitly by bly_choice_source()) get a third check INSIDE the lock,
 * on the FINAL value AFTER the pre-persist filter window and right before
 * the persist tail: the declaring schema is re-loaded fresh and the value
 * re-verified against it (bly_recheck_dynamic_choice; content blocks
 * additionally through a direct, cache-defeating row check —
 * bly_content_block_live_check), so an entity deleted or emptied between
 * validation and persist — by any process, or by a filter's side effect
 * — yields `bly_choice_stale` and no write. Static enums are exempt by
 * construction.
 *
 * Storage: almost every option is a theme_mod and takes the path above.
 * The few declarations carrying `setting.type = option`
 * (bly_option_storage — WooCommerce settings Blocksy re-exposes, plus
 * wp_page_for_privacy_policy) live in their OWN wp_options row, exactly
 * where the customizer saves them and their consumers read them; they
 * take bly_locked_option_write, the same discipline row-for-row (own
 * lock, raw prior bytes + autoload, byte-exact verification and rollback)
 * around a hook-for-hook replica of what a customizer save runs —
 * `update_option($id, $value, true)`: sanitize_option, pre_update
 * filters, core's same-value check on the FINAL value, the add/update
 * branch, the pre-SQL `add_option`/`update_option` action, the explicit
 * autoload ('on') set on insert AND update, the raw persist, and the
 * post-write `add_option_<id>`+`added_option` / `update_option_<id>`+
 * `updated_option` actions — with the final-value recheck placed AFTER
 * every replicated hook (see the writer's docblock for the exact order
 * and the documented trade-offs), never the theme_mods row.
 *
 * Autoload on the theme_mods row follows core's NULL-autoload path
 * (set_theme_mod → update_option with autoload null): a fresh row gets
 * `wp_determine_option_autoload_value()`, an existing `auto*` row is
 * re-evaluated against the new payload (bly_mods_row_autoload_plan),
 * any other flag is left alone.
 */

// ---------------------------------------------------------------------------
// Locking + fresh reads
// ---------------------------------------------------------------------------

/**
 * Cooperative cross-request lock around a critical section that touches one
 * shared wp_options row, via MySQL's `GET_LOCK` advisory lock. The row key
 * only scopes the lock NAME — this is the single lock primitive for every
 * serialized options-row write on this surface: the `theme_mods_*` writes
 * (via the bly_with_mods_lock() wrapper) and the extensions state row
 * (`blocksy_active_extensions`), whose Companion-side mutation is an
 * unlocked read-modify-write of the whole id list. Fails closed: when the
 * lock is not acquired — `GET_LOCK` returns 0 on timeout, NULL on error or
 * on backends without advisory locks (SQLite drop-in) — the critical
 * section is NOT run and a `bly_lock_unavailable` WP_Error is returned,
 * because running it unlocked is exactly the lost-update race this lock
 * exists to prevent.
 *
 * The lock name is scoped by blog id, database name, and table prefix:
 * advisory lock names are SERVER-global, so two independent WordPress
 * installs on one MySQL server would otherwise contend on the same name.
 *
 * `$fn` receives a `$lock_held` callable that re-verifies the lock is
 * STILL held on the SAME connection — advisory locks die with their
 * connection, and wpdb silently reconnects after a dropped connection, so
 * "acquired earlier" does not imply "held now". Callers MUST check it
 * immediately before any persist.
 *
 * @template T
 * @param callable(callable(): bool): T $fn
 * @return T|WP_Error
 */
function bly_with_option_row_lock(string $option_name, callable $fn): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    // MySQL caps GET_LOCK names at 64 chars. `nvp_bly_` (8) + blog_id (≤10)
    // + `_` (1) + md5 (32) = 51, safely under the cap.
    $scope = (defined('DB_NAME') ? (string) constant('DB_NAME') : '') . '|' . $wpdb->prefix . '|' . $option_name;
    $lock_name = sprintf('nvp_bly_%d_%s', get_current_blog_id(), md5($scope));

    // GET_LOCK and CONNECTION_ID must come from ONE query: two round trips
    // would let a reconnect in between record a thread id that never held
    // the lock (and later "release" from a non-acquiring connection).
    /** @var object|null $grant */
    // @mago-expect analysis:possibly-invalid-argument
    $grant = $wpdb->get_row($wpdb->prepare(
        'SELECT GET_LOCK(%s, %d) AS acquired, CONNECTION_ID() AS thread',
        $lock_name,
        BLY_MODS_LOCK_TIMEOUT,
    ));
    /** @var mixed $acquired_raw */
    $acquired_raw = is_object($grant) && property_exists($grant, 'acquired') ? $grant->acquired : null;
    if ($acquired_raw !== '1' && $acquired_raw !== 1) {
        return bly_error('bly_lock_unavailable', sprintf(
            'Could not acquire the write lock for the "%s" options row within %d seconds (%s). Nothing was written — retry shortly; if this persists, another process is holding the lock or the database backend does not support advisory locks.',
            $option_name,
            BLY_MODS_LOCK_TIMEOUT,
            $acquired_raw === null ? 'GET_LOCK returned NULL' : 'GET_LOCK timed out',
        ));
    }

    /** @var mixed $thread_raw */
    $thread_raw = is_object($grant) && property_exists($grant, 'thread') ? $grant->thread : null;
    $thread_id = is_scalar($thread_raw) ? (string) $thread_raw : '';
    if ($thread_id === '') {
        // Without the owning connection id, ownership can never be
        // re-verified — fail closed instead of writing on faith.
        // @mago-expect analysis:possibly-invalid-argument
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        return bly_error('bly_lock_unavailable', sprintf(
            'The write lock for the "%s" options row was acquired but the owning connection id could not be captured, so ownership cannot be re-verified before persisting. Nothing was written — retry.',
            $option_name,
        ));
    }

    try {
        return $fn(static fn(): bool => bly_lock_still_held($lock_name, $thread_id));
    } finally {
        // Release-path guard: advisory locks die with their connection, so
        // after a wpdb reconnect there is nothing of ours to release — and
        // the name may meanwhile be held by another request (RELEASE_LOCK
        // could never free theirs, but the release must not even pretend).
        // Only the still-acquiring connection releases.
        /** @var mixed $release_conn */
        $release_conn = $wpdb->get_var('SELECT CONNECTION_ID()');
        if (is_scalar($release_conn) && (string) $release_conn === $thread_id) {
            // @mago-expect analysis:possibly-invalid-argument
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}

/**
 * The theme_mods-flavored entry point the S2/S3 write path calls — a thin
 * delegator kept so the audited call sites read as what they are (a lock
 * around the `theme_mods_<stylesheet>` row). The primitive itself is
 * bly_with_option_row_lock() above.
 *
 * @template T
 * @param callable(callable(): bool): T $fn
 * @return T|WP_Error
 */
function bly_with_mods_lock(string $mods_key, callable $fn): mixed
{
    return bly_with_option_row_lock($mods_key, $fn);
}

/**
 * Whether the advisory lock acquired on `$thread_id` is still held by THIS
 * request's database connection. Two probes, both required: the current
 * `CONNECTION_ID()` must equal the id captured at acquire time (a wpdb
 * auto-reconnect yields a new id — and released the lock), and
 * `IS_USED_LOCK()` must report that same id as the lock's owner. Note the
 * first probe itself triggers wpdb's reconnect path when the connection
 * was dropped, which is exactly what makes the mismatch observable.
 */
function bly_lock_still_held(string $lock_name, string $thread_id): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    if ($thread_id === '') {
        return false;
    }
    /** @var mixed $current */
    $current = $wpdb->get_var('SELECT CONNECTION_ID()');
    if (!is_scalar($current) || (string) $current !== $thread_id) {
        return false;
    }
    /** @var mixed $owner */
    // @mago-expect analysis:possibly-invalid-argument
    $owner = $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock_name));
    return is_scalar($owner) && (string) $owner === $thread_id;
}

/**
 * Drop every per-request option-cache layer that could serve a stale
 * options row (per-option, alloptions, notoptions) — used for the
 * theme_mods row and for the extensions state row alike. Without a
 * persistent object cache these caches are primed at request bootstrap, so
 * a request that just waited on the row lock would otherwise read the row
 * as it was BEFORE the lock holder's write committed. Takes the PINNED row
 * key — never recomputed inside a critical section (see
 * bly_locked_mod_write).
 */
function bly_bust_mods_cache(string $mods_key): void
{
    wp_cache_delete($mods_key, group: 'options');
    wp_cache_delete('alloptions', group: 'options');
    wp_cache_delete('notoptions', group: 'options');
}

// ---------------------------------------------------------------------------
// Blocksy cache refresh (post-write)
// ---------------------------------------------------------------------------

/**
 * Reset Blocksy's per-request theme-mod memoizations so the next read hits
 * the database — without regenerating any dynamic CSS. Two layers exist
 * (verified 2.1.52), and BOTH must go:
 *
 *  1. `Blocksy\Database::wipe_cache()` (inc/classes/database.php:31) — the
 *     memo behind every `blocksy_get_theme_mod()`.
 *  2. The builder-logic objects' OWN `section_value` memo
 *     (Blocksy_Header_Builder::get_section_value(), header-logic.php:249,
 *     and the footer equivalent) — primed on first access and bypassed
 *     only in customize-preview requests, with no public reset. The CSS
 *     regeneration this integration triggers after a builder write reads
 *     THROUGH these objects, so a primed memo would regenerate CSS from
 *     pre-write builder state. The manager exposes the objects as public
 *     properties (inc/manager.php:11), so the reset is a fresh instance —
 *     exactly the state a new request would see.
 *
 * Used on the write success path (before the refresh action) and on the
 * rollback path, where the memos must reflect the restored row.
 */
function bly_wipe_blocksy_memo(): void
{
    if (!function_exists('blocksy_manager')) {
        return;
    }
    $manager = blocksy_manager();
    $db = $manager->db;
    if ($db instanceof \Blocksy\Database) {
        $db->wipe_cache();
    }

    try {
        if ($manager->header_builder !== null && class_exists('Blocksy_Header_Builder')) {
            $manager->header_builder = new \Blocksy_Header_Builder();
        }
        if ($manager->footer_builder !== null && class_exists('Blocksy_Footer_Builder')) {
            $manager->footer_builder = new \Blocksy_Footer_Builder();
        }
    } catch (\Throwable $e) {
        // A failed builder-object reset must not abort the wider cache
        // repair — the Database memo above is already wiped.
        error_log('novamira-pro blocksy: resetting the builder-logic memos threw: ' . $e->getMessage());
    }
}

/**
 * Reset Blocksy's theme-mod memoization and regenerate its dynamic-CSS
 * caches, in that order — see the sequence contract in the file docblock.
 * Idempotent and safe to call repeatedly.
 *
 * `$pinned_stylesheet` guards the regeneration on write paths: the
 * refresh action rebuilds the ACTIVE theme's caches from the ACTIVE
 * theme's mods, so when a mid-write theme switch left the pinned theme
 * dormant, firing it would regenerate the WRONG theme's CSS. In that
 * case (and when a refresh listener throws) the pinned theme's cached
 * CSS artifacts are INVALIDATED instead — deleted, never left stale
 * without a dirty marker — so Blocksy regenerates them on its next
 * activation, save, or front-end render. The memo is wiped on every
 * path (it is a plain per-request memoization, not theme-keyed). Null
 * (reads/tests/manual repair) always fires.
 *
 * @return true|false|WP_Error true = caches regenerated; false = pinned
 *     theme inactive, regeneration skipped and artifacts invalidated;
 *     WP_Error `bly_refresh_failed` = a refresh listener threw (memo
 *     wiped + artifacts invalidated; the DB write this call followed IS
 *     persisted — the error message says so).
 */
function bly_refresh_blocksy_caches(?string $pinned_stylesheet = null): bool|WP_Error
{
    bly_wipe_blocksy_memo();

    if ($pinned_stylesheet !== null && get_stylesheet() !== $pinned_stylesheet) {
        bly_invalidate_blocksy_css_artifacts();
        return false;
    }

    try {
        /**
         * Fires when the dynamic CSS caches need to be regenerated.
         *
         * Documented in blocksy/inc/dynamic-css.php. Regenerates the
         * `blocksy_dynamic_styles_descriptor` transient (theme) and the
         * `uploads/blocksy/css/global.css` file cache (companion).
         */
        do_action('blocksy:dynamic-css:refresh-caches');
    } catch (\Throwable $e) {
        error_log('novamira-pro blocksy: a dynamic-css refresh listener threw: ' . $e->getMessage());
        // A listener may have re-primed the memo (or half-written a cache)
        // before throwing — wipe again and invalidate so nothing stale
        // survives the failed regeneration.
        bly_wipe_blocksy_memo();
        bly_invalidate_blocksy_css_artifacts();
        return bly_error(
            'bly_refresh_failed',
            message: 'The value WAS persisted and verified, but regenerating Blocksy\'s dynamic-CSS caches failed (a refresh listener threw — details in the server error log). The stale cached CSS was invalidated and regenerates on the next front-end render; read the option back to confirm the stored value.',
        );
    }
    return true;
}

/**
 * Best-effort invalidation of Blocksy's generated CSS artifacts (the
 * dynamic-styles descriptor transient and the companion's global.css file
 * cache): used whenever regeneration cannot run or failed, so the next
 * activation/save/render rebuilds from the actual stored mods instead of
 * serving stale CSS. Cache repair must never be skipped by an exceptional
 * exit, so failures here are logged, never thrown.
 */
function bly_invalidate_blocksy_css_artifacts(): void
{
    try {
        delete_transient('blocksy_dynamic_styles_descriptor');
        $upload = wp_upload_dir();
        /** @var mixed $basedir */
        $basedir = $upload['basedir'];
        if (is_string($basedir) && $basedir !== '') {
            $file = $basedir . '/blocksy/css/global.css';
            if (file_exists($file)) {
                wp_delete_file($file);
            }
        }
    } catch (\Throwable $e) {
        error_log('novamira-pro blocksy: invalidating dynamic-css artifacts threw: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// The one write path
// ---------------------------------------------------------------------------

/**
 * Atomically write one theme_mod and run the full Blocksy refresh sequence.
 *
 * `$compute` receives the FRESH stored mods (re-read raw from the database
 * inside the lock, legacy-`mods_<name>`-aware, absence normalized to [])
 * and returns the complete new value for `$id` — or a WP_Error to abort
 * with nothing written. Computing inside the lock is what makes
 * merge-style values (the palette) safe: the merge base can not be
 * clobbered by a concurrent writer.
 *
 * ORDER MATTERS in the critical section: all suppressible user code — the
 * compute callback, the native `pre_set_theme_mod_<id>` filter, the
 * payload serialization (a __serialize() surface), the autoload
 * determination (a core filter, bly_mods_row_autoload_plan) and, LAST,
 * the caller's `$recheck` of the FINAL post-filter value (dynamic choice
 * vocabularies re-resolved fresh — option files are user code) — runs
 * FIRST to produce the exact pre-computed SQL payload; only then are the
 * pinned identity (HOOK-FREE, against the raw `stylesheet` row captured
 * at entry — bly_stylesheet_anchor; never get_stylesheet(), whose
 * `stylesheet` filter is user code that could invalidate the vocabulary
 * behind the recheck's back) and the lock ownership re-verified, and the
 * persist itself is a raw SQL write of those precomputed bytes
 * (bly_persist_mods_row_raw). The recheck sits AFTER the filter window
 * AND after every other hook of the section on purpose: a filter can
 * invalidate the vocabulary as a side effect (delete the menu item, trash
 * the page) while returning the value unchanged, and a recheck that ran
 * before any callback would have blessed a value that is stale by the
 * time the SQL runs. One window remains open by construction: wpdb's
 * global `query` filter (and its auto-reconnect path) executes arbitrary
 * code INSIDE the write call and cannot be suppressed — its containment
 * is the post-write ownership re-check, which rolls a possibly-unlocked
 * write back (verified) and returns `bly_lock_lost`. A filter that
 * reconnects wpdb (killing the connection-scoped advisory lock) or calls
 * switch_theme() before the persist aborts with nothing written.
 *
 * Persistence is verified, not assumed: the row's raw state is re-read
 * (bly_raw_option_row_state — immune to `option_<name>` filters, which
 * could both forge the verification and reopen the user-code window) and
 * the stored value must be identical to the intended one AND the autoload
 * flag must be the one the autoload plan chose. Verification failure
 * hands off to bly_handle_failed_persist(), which restores the captured
 * prior bytes, then the prior autoload flag (raw, last), VERIFIES the
 * complete restored state exactly, and guarantees the exit invariant
 * either way: database row, Blocksy memo, and generated CSS are mutually
 * consistent on every path — `bly_write_failed` when the prior state was
 * re-established, `bly_rollback_failed` when it could not be.
 *
 * The storage identity {stylesheet, mods key} is PINNED — resolved once at
 * the very start of the ability callback (callers pass it in BEFORE any
 * third-party code, sanitizers included, gets a chance to switch themes)
 * and threaded through every read, the persist, the verification, the
 * rollback, and the cache-refresh decision. A detected switch aborts with
 * `bly_stylesheet_changed` (409), and the OTHER theme's row is never
 * touched on any path.
 *
 * @param callable(array<array-key, mixed>): mixed $compute
 * @param null|string $stylesheet Pinned stylesheet from the callback
 *     entry; null pins here (direct/test callers).
 * @param null|string $mods_key Pinned mods row name; null derives it from
 *     the pinned stylesheet.
 * @param null|callable(mixed): (WP_Error|null) $recheck Optional last
 *     validation of the FINAL value (after the pre_set filter, before
 *     the persist) — the in-lock dynamic-choice re-check
 *     (bly_recheck_dynamic_choice). A WP_Error aborts with nothing
 *     written. Runs BEFORE the serialize/ownership/persist tail, which
 *     stays contiguous and hook-free.
 * @return mixed The value that was written, or WP_Error.
 */
function bly_locked_mod_write(
    string $id,
    callable $compute,
    ?string $stylesheet = null,
    ?string $mods_key = null,
    ?callable $recheck = null,
): mixed {
    $stylesheet ??= get_stylesheet();
    $mods_key ??= 'theme_mods_' . $stylesheet;

    // The hook-free anchor of the pinned identity (the raw `stylesheet`
    // row) is captured BEFORE the filtered check below confirms the pin,
    // so a lying filter that switches themes inside that very call is
    // caught by the tail's raw comparison. See bly_stylesheet_anchor().
    $pin = bly_pin_identity($stylesheet);
    // A theme switch between the callback-entry pin and this point (e.g.
    // from inside Blocksy's sanitizer while gate 2 ran) — abort before
    // even taking the lock.
    if (get_stylesheet() !== $stylesheet) {
        return bly_stylesheet_changed_error($id, $stylesheet);
    }

    return bly_with_mods_lock($mods_key, static function (callable $lock_held) use (
        $id,
        $compute,
        $mods_key,
        $stylesheet,
        $pin,
        $recheck,
    ): mixed {
        bly_bust_mods_cache($mods_key);
        // The exact prior row — bytes (or absence) AND autoload flag,
        // captured together — the byte-exact rollback target. Raw SQL, not
        // get_option(): option_<name> filters run third-party code and
        // could forge what "prior" looks like.
        $prior = bly_raw_option_row_state($mods_key);
        $prior_bytes = $prior['bytes'];
        /** @var mixed $prior_unserialized */
        $prior_unserialized = is_string($prior_bytes) ? maybe_unserialize($prior_bytes) : false;
        $mods = is_array($prior_unserialized) ? $prior_unserialized : bly_pinned_legacy_mods($stylesheet);

        /** @var mixed $value */
        $value = $compute($mods);
        if ($value instanceof WP_Error) {
            return $value;
        }
        if (get_stylesheet() !== $stylesheet) {
            return bly_stylesheet_changed_error($id, $stylesheet);
        }

        // The native pre_set filter — the last THIRD-PARTY code on the
        // write path.
        /** @var mixed $filtered */
        $filtered = bly_apply_pre_set_filter($mods, $id, $value);

        $bound_error = bly_assert_persistable($id, $filtered);
        if ($bound_error !== null) {
            return $bound_error;
        }

        // Precompute the EXACT SQL payload before the final checks:
        // serialize() can invoke __serialize() on rogue objects a filter
        // smuggled in, and the autoload determination applies a core
        // filter (`wp_default_autoload_value`) — both are user code and
        // must not run between the ownership check and the SQL.
        ['payload' => $payload, 'autoload' => $autoload_plan] = bly_mods_row_payload(
            $mods,
            $id,
            $filtered,
            $mods_key,
            $prior,
        );

        // The dynamic-choice re-check of the FINAL value — LAST, after the
        // filter window (a filter may have deleted the menu item / trashed
        // the page while returning the value untouched) AND after the
        // serialize/autoload user code above, right before the
        // ownership/persist tail: no callback of the pipeline runs behind
        // it — the last USER-CODE-CAPABLE step. It reloads option files,
        // i.e. runs user code, so it must NOT sit between the ownership
        // check and the SQL. Everything from here to the SQL is hook-free:
        // the pinned identity is re-verified against its raw anchor (never
        // through get_stylesheet(), whose `stylesheet` filter is user code
        // that could invalidate the just-verified vocabulary), then the
        // lock ownership.
        $stale = bly_run_recheck($recheck, $filtered, $id, $pin);
        if ($stale !== null) {
            return $stale;
        }

        if (!$lock_held()) {
            return bly_error('bly_lock_lost', sprintf(
                'The theme_mods write lock for option "%s" was lost before persisting (the database connection was re-established, which releases advisory locks). Nothing was written — retry.',
                $id,
            ));
        }

        bly_persist_mods_row_raw($mods_key, $payload, $autoload_plan['autoload'], $autoload_plan['update']);

        // Containment for the one window that cannot be closed: wpdb's
        // global 'query' filter (and its auto-reconnect path) runs
        // arbitrary code INSIDE the write call itself and is inherently
        // un-suppressible. If the connection changed across the write,
        // the SQL may have executed on a fresh, non-lock-owning
        // connection — roll the row back (verified) and refuse.
        if (!$lock_held()) {
            return bly_handle_failed_persist(
                $id,
                $mods_key,
                $prior,
                bly_error('bly_lock_lost', sprintf(
                    'The database connection changed WHILE persisting option "%s" (a query filter or auto-reconnect) — the write may have run without the advisory lock, so it was rolled back. Nothing effective was written; retry.',
                    $id,
                )),
                $stylesheet,
            );
        }

        return bly_verify_persisted($id, $mods_key, $stylesheet, $prior, [
            'value' => $value,
            'autoload' => $autoload_plan['autoload'],
        ]);
    });
}

/**
 * The hook-free anchor of the pinned storage identity: the raw bytes of
 * the `stylesheet` options row (false when absent), read straight from
 * the database. get_stylesheet() is `apply_filters('stylesheet',
 * get_option('stylesheet'))` (wp-includes/theme.php) — user code on every
 * call — so it must never be consulted between the final vocabulary
 * recheck and the SQL. Both writers capture this anchor at entry, right
 * before their filtered pin check confirms `get_stylesheet() ===
 * $stylesheet` (so anchor and pin describe the same moment, whatever a
 * `stylesheet`/`pre_option_stylesheet` filter maps the active theme to),
 * and re-compare it raw in the tail (bly_stylesheet_anchor_moved): a
 * switch_theme() by any hook after the capture changes the row and aborts
 * the write with nothing written. A FILTER-only switch (a hook adding or
 * removing a `stylesheet` filter mid-write) is invisible to a raw read by
 * construction; before the recheck it is still caught by the filtered
 * checks of the prepare phase, and after it the write lands in the pinned
 * row regardless (never another theme's), with the post-write cache
 * refresh guarded by bly_refresh_blocksy_caches($pinned) as before.
 */
function bly_stylesheet_anchor(): string|false
{
    return bly_raw_option_row('stylesheet');
}

/**
 * The pinned storage identity as the write path threads it: the pinned
 * stylesheet (from the ability-callback entry) plus its hook-free raw
 * anchor captured NOW (bly_stylesheet_anchor). Callers capture this
 * immediately before their filtered pin check confirms the pin.
 *
 * @return array{stylesheet: string, anchor: string|false}
 */
function bly_pin_identity(string $stylesheet): array
{
    return ['stylesheet' => $stylesheet, 'anchor' => bly_stylesheet_anchor()];
}

/**
 * Whether the raw `stylesheet` row no longer matches the anchor captured
 * at writer entry — the hook-free replacement for a `get_stylesheet() !==
 * $stylesheet` re-check on the tail of the write path.
 */
function bly_stylesheet_anchor_moved(string|false $anchor): bool
{
    return bly_stylesheet_anchor() !== $anchor;
}

/**
 * The exact mods-row SQL payload (the merged mods, serialized) and its
 * autoload plan — the two pieces of user code (serialize()/__serialize()
 * and core's autoload filter) the mods write precomputes BEFORE the
 * ownership check. Split out of bly_locked_mod_write() for readability
 * only.
 *
 * @param array<array-key, mixed> $mods
 * @param array{bytes: string|false, autoload: string|null} $prior The
 *     captured prior row state (bytes false = absent).
 * @return array{payload: string, autoload: array{autoload: string, update: string|null}}
 */
function bly_mods_row_payload(array $mods, string $id, mixed $filtered, string $mods_key, array $prior): array
{
    $row = $mods;
    /** @var mixed */
    $row[$id] = $filtered;
    $payload = serialize($row);
    return ['payload' => $payload, 'autoload' => bly_mods_row_autoload_plan($mods_key, $row, $payload, $prior)];
}

/**
 * The autoload value core itself would pick for a fresh options row (the
 * theme_mods row, or the extensions state row) on its NULL-autoload path:
 * `wp_determine_option_autoload_value()` (WP 6.6+ — applies the
 * `wp_default_autoload_value` filter and yields 'auto' for a
 * regular-sized option, exactly what update_option(autoload: null) →
 * add_option() stores), falling back to the legacy 'yes' on older cores.
 * Applies a core filter, i.e. user code — callers run it BEFORE the
 * ownership check.
 *
 * @param mixed $row The value about to be stored (the mods array, or the
 *     option-storage value).
 */
function bly_mods_row_autoload(string $mods_key, mixed $row, string $payload): string
{
    if (!function_exists('wp_determine_option_autoload_value')) {
        return 'yes';
    }
    $requested_autoload = null;
    /** @var mixed $autoload */
    $autoload = wp_determine_option_autoload_value($mods_key, $row, $payload, $requested_autoload);
    return is_string($autoload) && $autoload !== '' ? $autoload : 'yes';
}

/**
 * The autoload decision core's NULL-autoload update_option() makes for the
 * mods row (set_theme_mod() → update_option($key, $mods) with autoload
 * null, wp-includes/option.php:947-958 on the installed core): a fresh
 * row gets bly_mods_row_autoload(); an EXISTING row keeps its flag unless
 * it was set automatically (`auto`, `auto-on`, `auto-off`) — those are
 * re-evaluated against the NEW payload and rewritten only when the
 * evaluation differs (e.g. a mods payload crossing the
 * `wp_max_autoloaded_option_size` threshold). Works from the stored flag
 * CAPTURED with the prior bytes (one raw read, the rollback target) and
 * applies the same core filter as the fresh-row case, so it is user code
 * and runs BEFORE the ownership check.
 *
 * @param array<array-key, mixed> $row The mods array about to be stored.
 * @param array{bytes: string|false, autoload: string|null} $prior The
 *     captured prior row state (bytes false = absent).
 * @return array{autoload: string, update: string|null} `autoload` is the
 *     flag the row MUST hold after the persist on every branch (the
 *     verification target): the fresh determination for a row-absent
 *     INSERT, the untouched stored flag, or the re-evaluated one; `update`
 *     is the flag an existing row must be rewritten to (null = the stored
 *     flag stays).
 */
function bly_mods_row_autoload_plan(string $mods_key, array $row, string $payload, array $prior): array
{
    if ($prior['bytes'] === false) {
        return ['autoload' => bly_mods_row_autoload($mods_key, $row, $payload), 'update' => null];
    }
    $stored = $prior['autoload'];
    if (!in_array($stored, ['auto-on', 'auto-off', 'auto'], strict: true)) {
        return ['autoload' => $stored ?? 'yes', 'update' => null];
    }
    $reevaluated = bly_mods_row_autoload($mods_key, $row, $payload);
    return ['autoload' => $reevaluated, 'update' => $reevaluated !== $stored ? $reevaluated : null];
}

/**
 * Post-persist verification and outcome: re-read the row's raw state
 * (immune to `option_<name>` filters — the same reason the persist path
 * captures raw bytes) and demand the COMPLETE state be the intended one:
 * the stored value identical to the written one AND the autoload flag
 * equal to what the autoload plan said the row must hold (a persist that
 * landed the bytes but not the flag is not a success). Every failure —
 * including a defensively re-checked mid-write theme switch, unreachable
 * while no user code runs after the pre-persist checks — hands off to
 * the verified-rollback path. Success runs the pinned Blocksy cache
 * refresh and returns the written value.
 *
 * @param array{bytes: string|false, autoload: string|null} $prior The
 *     captured prior row state — the rollback target.
 * @param array{value: mixed, autoload: string} $intended The value the
 *     row must hold for `$id` and the flag the row must carry now
 *     (bly_mods_row_autoload_plan()['autoload']).
 * @return mixed
 */
function bly_verify_persisted(string $id, string $mods_key, string $stylesheet, array $prior, array $intended): mixed
{
    /** @var mixed $value */
    $value = $intended['value'];
    $state = bly_raw_option_row_state($mods_key);
    /** @var mixed $persisted_row */
    $persisted_row = is_string($state['bytes']) ? maybe_unserialize($state['bytes']) : false;
    $persisted =
        is_array($persisted_row)
        && array_key_exists($id, $persisted_row)
        && $persisted_row[$id] === $value
        && $state['autoload'] === $intended['autoload'];

    if (get_stylesheet() !== $stylesheet) {
        return bly_handle_failed_persist(
            $id,
            $mods_key,
            $prior,
            bly_stylesheet_changed_error($id, $stylesheet),
            $stylesheet,
        );
    }
    if (!$persisted) {
        return bly_handle_failed_persist($id, $mods_key, $prior, verified_error: null, stylesheet: $stylesheet);
    }

    $refresh = bly_refresh_blocksy_caches($stylesheet);
    if ($refresh instanceof WP_Error) {
        // The write itself succeeded and is verified — the distinct
        // bly_refresh_failed error says so; nothing stale survives
        // (memo wiped, artifacts invalidated).
        return $refresh;
    }
    return $value;
}

/**
 * The pinned mods row's raw serialized bytes straight from the database,
 * or false when the row is absent. The capture/verify primitive of the
 * persist path: get_option() is NOT usable here — its `option_<name>`
 * filters run third-party code, which both reopens the lock-ownership
 * window and lets a filter forge what verification reads.
 */
function bly_raw_mods_row(string $mods_key): string|false
{
    return bly_raw_option_row($mods_key);
}

/**
 * The raw serialized bytes of ANY wp_options row straight from the
 * database, or false when the row is absent — the primitive behind
 * bly_raw_mods_row(), shared with the option-storage write path
 * (bly_locked_option_write) and its read side (bly_stored_option_state).
 */
function bly_raw_option_row(string $option_name): string|false
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name);
    // get_row, not get_var: wpdb::get_var() folds a stored EMPTY STRING
    // into null (its `'' !== $value` guard), which would read a row that
    // holds '' — a legitimate option value ("no page set") — as absent.
    /** @var mixed $row */
    $row = is_string($sql) ? $wpdb->get_row($sql, 'ARRAY_A') : null;
    /** @var mixed $raw */
    $raw = is_array($row) ? $row['option_value'] ?? null : null;
    return is_string($raw) ? $raw : false;
}

/**
 * Legacy-row merge base for a PINNED theme whose modern `theme_mods_*` row
 * is absent: mirrors core get_theme_mods()'s `mods_<Theme Name>` fallback,
 * resolved for the pinned stylesheet instead of whatever theme happens to
 * be active. Persisting the merged result into the pinned row preserves
 * the same one-shot migration core's read-then-write performs.
 *
 * @return array<array-key, mixed>
 */
function bly_pinned_legacy_mods(string $stylesheet): array
{
    /** @var mixed $theme_name */
    $theme_name = get_stylesheet() === $stylesheet ? get_option('current_theme') : false;
    if (!is_string($theme_name) || $theme_name === '') {
        /** @var mixed $theme_name */
        $theme_name = wp_get_theme($stylesheet)->get('Name');
    }
    if (!is_string($theme_name) || $theme_name === '') {
        return [];
    }
    /** @var mixed $legacy */
    $legacy = get_option('mods_' . $theme_name, default_value: false);
    return is_array($legacy) ? $legacy : [];
}

/**
 * The native `pre_set_theme_mod_<id>` filter, exactly as core
 * set_theme_mod() fires it — split out so it runs BEFORE the ownership
 * and stylesheet re-checks: it is the last user code on the write path.
 *
 * @param array<array-key, mixed> $mods
 * @return mixed
 */
function bly_apply_pre_set_filter(array $mods, string $id, mixed $value): mixed
{
    /** @var mixed $old */
    $old = array_key_exists($id, $mods) ? $mods[$id] : false;

    /**
     * Filters the theme modification, matching core set_theme_mod().
     *
     * @param mixed $value The new value.
     * @param mixed $old   The current value.
     */
    return apply_filters('pre_set_theme_mod_' . $id, $value, $old);
}

/**
 * Run a writer's optional in-lock `$recheck` on the FINAL (post-filter)
 * value — the LAST user-code-capable step of the write path — and then
 * re-verify the pinned storage identity HOOK-FREE against its raw anchor
 * (bly_stylesheet_anchor_moved; the recheck reloads option files, user
 * code that could switch themes). No filterable lookup runs after the
 * vocabulary validation: get_stylesheet() applies the `stylesheet`
 * filter, and user code there could invalidate the just-verified entity
 * (trash the page) behind the recheck's back. Null when there is no
 * recheck or the value still holds. Shared by both write paths
 * (bly_locked_mod_write, bly_locked_option_write).
 *
 * @param null|callable(mixed): (WP_Error|null) $recheck
 * @param array{stylesheet: string, anchor: string|false} $pin The pinned
 *     identity with its raw anchor captured at writer entry
 *     (bly_pin_identity).
 */
function bly_run_recheck(?callable $recheck, mixed $final, string $id, array $pin): ?WP_Error
{
    if ($recheck !== null) {
        $stale = $recheck($final);
        if ($stale instanceof WP_Error) {
            return $stale;
        }
    }
    if (bly_stylesheet_anchor_moved($pin['anchor'])) {
        return bly_stylesheet_changed_error($id, $pin['stylesheet'], bly_stylesheet_anchor_now());
    }
    return null;
}

/**
 * The raw `stylesheet` row rendered for an error message on the hook-free
 * tail (where get_stylesheet() must not run): the stored theme slug, or a
 * marker when the row is absent.
 */
function bly_stylesheet_anchor_now(): string
{
    $raw = bly_stylesheet_anchor();
    return $raw === false ? '(no stylesheet row)' : $raw;
}

/**
 * The in-lock re-check closure for one GLOBAL option row — dynamic
 * choice vocabularies (nav menus, pages, terms, content blocks) are
 * re-resolved from a fresh, uncached option-tree load and re-verified
 * against the FINAL value (bly_recheck_dynamic_choice); static rows
 * return immediately. Both edit-option storages take this closure.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return callable(mixed): (WP_Error|null)
 */
function bly_option_recheck(array $row): callable
{
    return static fn(mixed $final): ?WP_Error => bly_recheck_dynamic_choice(
        $row,
        $final,
        static fn(): array|WP_Error => bly_fresh_option_index(),
    );
}

/**
 * Size bounds on the FINAL value about to be persisted — after the
 * compute merge and the pre_set filter, for EVERY writer (edit-option,
 * edit-color-palette, and anything future). Input-side validation cannot
 * cover this: a filter can expand the value, and a merge writer folds
 * STORED state (a corrupted 100k-entry palette must not be re-persisted
 * just because the client's input was tiny).
 */
function bly_assert_persistable(string $id, mixed $value): ?WP_Error
{
    $encoded = wp_json_encode($value);
    if (is_string($encoded) && strlen($encoded) > BLY_MAX_WRITE_BYTES) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": the final value to persist is %d bytes JSON-encoded, above the %d-byte cap (the stored value being merged, or a filter, inflated it). Nothing was written.',
            $id,
            strlen($encoded),
            BLY_MAX_WRITE_BYTES,
        ));
    }
    $oversized = bly_find_oversized_array($value);
    if ($oversized !== null) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": the final value to persist contains an array/object with %d elements, above the %d-element cap — the stored value being merged, or a filter, is corrupted. Nothing was written; inspect the stored option with blocksy-get-option.',
            $id,
            $oversized,
            BLY_MAX_LIST_ITEMS,
        ));
    }
    return null;
}

/**
 * Raw, hook-free persist of one options row (the pinned mods row, or an
 * option-storage row). By the time this runs, every piece of user code —
 * compute callback, pre_set / pre_update filters, sanitize_option(), the
 * serialize()/__serialize() of the payload, the autoload determination
 * and any pre-SQL core action — has already executed and the lock
 * ownership has been re-verified: this function receives the finished
 * payload bytes and does nothing but prepare + query. update_option()
 * would reopen that window (`pre_update_option_*` and option-cache hooks
 * run third-party code between the ownership check and the SQL); wpdb's
 * own global 'query' filter remains — it is inherently un-suppressible —
 * and is contained by the caller's post-write connection re-check.
 *
 * `$autoload` is stored on the row-absent INSERT. Whether an EXISTING row's
 * autoload is touched follows core's two branches, decided by the caller:
 * a non-null `$update_autoload` rewrites it to that flag (core's
 * explicit-autoload path — update_option($k, $v, true), what a customizer
 * save of an option-storage row does; and core's null path when it
 * re-evaluated an `auto*` row to a different value); null leaves the
 * stored flag alone (core's null path otherwise). Every option-cache
 * layer is busted right after so subsequent get_option() reads see the
 * new bytes. Returns the rows affected as MySQL reports them for this
 * statement — 1 inserted, 2 updated, 0 when the existing row already
 * held exactly these bytes (and flag) — the signal core's own
 * `if (!$result) return false;` keys on; 0 also when the statement did
 * not run.
 */
function bly_persist_mods_row_raw(
    string $mods_key,
    string $payload,
    string $autoload,
    ?string $update_autoload = null,
): int {
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)"
        . ' ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)'
        . ($update_autoload !== null ? ', autoload = %s' : ''),
        ...$update_autoload !== null
            ? [$mods_key, $payload, $autoload, $update_autoload]
            : [$mods_key, $payload, $autoload],
    );
    /** @var mixed $result */
    $result = is_string($sql) ? $wpdb->query($sql) : 0;
    bly_bust_mods_cache($mods_key);
    return is_int($result) ? $result : 0;
}

/**
 * WP_Error for a write aborted because the active stylesheet changed
 * mid-write (a hook called switch_theme()). HTTP 409. `$now` names the
 * current theme for the message; null resolves it through
 * get_stylesheet() — callers on the hook-free tail pass the raw row
 * (bly_stylesheet_anchor_now) instead, so no filter runs there.
 */
function bly_stylesheet_changed_error(string $id, string $pinned_stylesheet, ?string $now = null): WP_Error
{
    return bly_error('bly_stylesheet_changed', sprintf(
        'The active theme changed while writing option "%s" (write started under stylesheet "%s", now "%s"). The write was aborted and the pinned row left unchanged — re-run against the current theme if the change is intended.',
        $id,
        $pinned_stylesheet,
        $now ?? get_stylesheet(),
    ));
}

/**
 * Recovery path for a write whose verification failed: roll the row back
 * to its exact captured prior STATE — bytes (existence-aware — `false`
 * deletes the row again) AND autoload flag — and VERIFY the restore
 * exactly, both halves, via a raw re-read. Order matters: the bytes are
 * restored FIRST (through the options API, hooks and all — best-effort,
 * see bly_restore_mods_row), and only THEN the flag, RAW and hook-free
 * (bly_restore_option_row_autoload): core's update_option() on the
 * NULL-autoload path re-evaluates an `auto*` flag it finds on the row
 * (wp-includes/option.php, the `$raw_autoload` branch), so a flag put
 * back before the value restore could be rewritten by it (`auto-on` →
 * `auto`) — the restore must be the last statement to touch the flag.
 * The verification then compares the COMPLETE {bytes, autoload} state to
 * the captured prior one; bytes alone would call a rollback verified
 * while the flag drifted.
 *
 * Exit invariant, both branches: the PINNED database row, Blocksy memo,
 * and generated CSS leave this function mutually consistent.
 *  - Restore verified: memo wiped (next read hits the restored row), no
 *    dynamic-CSS refresh — the generated CSS already reflects the pre-call
 *    state the row holds again. Returns `bly_write_failed` (nothing
 *    changed).
 *  - Restore NOT verified (a filter or database failure blocked it): the
 *    row now holds something that is neither the requested nor the prior
 *    value — that must not be papered over. The memo is wiped AND the
 *    dynamic-CSS refresh fires so every cache matches what the database
 *    actually holds — but ONLY while the pinned theme is still the active
 *    one: the refresh action regenerates the ACTIVE theme's caches, and a
 *    rollback-time theme switch must not trigger a regeneration of the
 *    wrong theme (the pinned theme's CSS regenerates on its next
 *    activation/save). The distinct `bly_rollback_failed` tells the
 *    caller the stored option needs attention.
 *
 * `$verified_error` overrides the error returned on a VERIFIED restore —
 * used when the rollback was triggered by something other than a value
 * mismatch (a mid-write theme switch); null keeps the default
 * `bly_write_failed`.
 *
 * @param array{bytes: string|false, autoload: string|null} $prior The
 *     captured prior row: raw serialized bytes (false = captured absence)
 *     and autoload flag (null when absent).
 */
function bly_handle_failed_persist(
    string $id,
    string $mods_key,
    array $prior,
    ?WP_Error $verified_error,
    string $stylesheet,
): WP_Error {
    bly_restore_mods_row($mods_key, $prior['bytes']);
    bly_restore_option_row_autoload($mods_key, $prior);
    bly_bust_mods_cache($mods_key);

    $restored = bly_raw_option_row_state($mods_key);
    if ($restored['bytes'] === $prior['bytes'] && $restored['autoload'] === $prior['autoload']) {
        bly_wipe_blocksy_memo();
        return $verified_error ?? bly_error('bly_write_failed', sprintf(
            'Writing option "%s" did not persist: the re-read %s row did not hold the intended value (or autoload flag), so the row was rolled back to its prior state (verified, value AND autoload). A pre_set_theme_mod_%s filter rewriting the value, or a database failure, can cause this.',
            $id,
            $mods_key,
            $id,
        ));
    }

    $refresh = bly_refresh_blocksy_caches($stylesheet);
    return bly_error('bly_rollback_failed', sprintf(
        'Writing option "%s" did not persist AND the rollback could not re-establish the prior state: the %s row now holds a value that may differ from both the requested and the previous one. %s The option needs attention — read it back with blocksy-get-option and decide.',
        $id,
        $mods_key,
        $refresh === true
            ? 'Blocksy\'s theme-mod memo and dynamic-CSS caches were refreshed to match the actual stored row.'
            : 'The theme-mod memo was wiped, but the dynamic-CSS caches could NOT be regenerated (the pinned theme is no longer active, or a refresh listener failed) — the cached CSS artifacts were invalidated instead and regenerate on the theme\'s next activation, save, or front-end render.',
    ));
}

/**
 * Existence-aware restore attempt of the raw theme_mods row on the write
 * rollback path: `false` (captured absence) deletes the row again,
 * anything else is written back from the captured bytes. Runs through the
 * options API (hooks and all) — the restore is best-effort and the caller
 * verifies the outcome byte-exactly with a raw re-read, so a lying hook
 * surfaces as `bly_rollback_failed` instead of being trusted. A THROWING
 * hook must not abort the recovery either: the exception is contained
 * (logged), and the caller's verification + cache repair run regardless.
 *
 * @param string|false $prior_bytes
 */
function bly_restore_mods_row(string $mods_key, string|false $prior_bytes): void
{
    try {
        if ($prior_bytes === false) {
            delete_option($mods_key);
            return;
        }
        update_option($mods_key, maybe_unserialize($prior_bytes));
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: an option hook threw during the %s rollback restore: %s',
            $mods_key,
            $e->getMessage(),
        ));

        // Fall through — verification will report bly_rollback_failed.
    }
}

// ---------------------------------------------------------------------------
// The option-storage write path (setting.type = option rows)
// ---------------------------------------------------------------------------

/**
 * The exact bytes core stores for an option value: update_option() runs
 * `maybe_serialize()` and hands the result to a `%s`-formatted query, so
 * arrays/objects persist serialized and every scalar persists as its
 * string form ("600", "1" for true, "" for false/null). This is what a
 * raw re-read must be compared against, and why the option path reports
 * scalars back in stored (string) form.
 */
function bly_option_row_bytes(mixed $value): string
{
    /** @var mixed $serialized */
    $serialized = maybe_serialize($value);
    if (is_string($serialized)) {
        return $serialized;
    }
    if (is_bool($serialized)) {
        return $serialized ? '1' : '';
    }
    return is_scalar($serialized) ? (string) $serialized : '';
}

/**
 * Atomically write one option-storage id (`setting.type = option`, see
 * bly_option_storage) into its OWN wp_options row, honoring the storage
 * the declaration and the customizer honor — with the SAME discipline as
 * the theme_mods path (bly_locked_mod_write), row-for-row, and core's
 * OWN write pipeline replicated hook-for-hook.
 *
 * What a Blocksy customizer save does for such a row (verified on the
 * installed core, WP 7.0.1): `WP_Customize_Setting::set_root_value()`
 * (class-wp-customize-setting.php:657-664) calls
 * `update_option($id, $value, true)` — autoload EXPLICITLY true — and
 * update_option() (wp-includes/option.php:884-1029) then runs, in this
 * order:
 *
 *   1. sanitize_option($id, $value)          — incl. `sanitize_option_<id>`
 *   2. $old_value = get_option($id)
 *   3. apply_filters("pre_update_option_<id>", $value, $old_value, $id)
 *   4. apply_filters('pre_update_option', $value, $id, $old_value)
 *   5. same-value check on the FINAL filtered value:
 *      `$value === $old_value || maybe_serialize($value) === maybe_serialize($old_value)`
 *      → return false, nothing written, no further hook
 *   6. apply_filters("default_option_<id>", false, $id, false) === $old_value
 *      → the ADD branch: add_option($id, $value, '', true):
 *        6a. sanitize_option($id, $value)    — a SECOND pass, on the filtered value
 *        6a'. add_option()'s OWN existence pass (option.php add_option()):
 *            unless the `notoptions` cache already marks the id absent
 *            (core's step-2 get_option() primes it for an absent row, so
 *            for an ABSENT row this pass is skipped), it applies
 *            `default_option_<id>` AGAIN and compares the result with
 *            get_option($id): a mismatch → add_option() returns false →
 *            update_option() returns false: NOTHING written, no further hook
 *        6b. maybe_serialize
 *        6c. wp_determine_option_autoload_value(…, true) → 'on'
 *        6d. do_action('add_option', $id, $value)
 *        6e. INSERT … ON DUPLICATE KEY UPDATE option_value, autoload
 *        6f. do_action("add_option_<id>", $id, $value); do_action('added_option', $id, $value)
 *      otherwise the UPDATE branch:
 *        6a. maybe_serialize
 *        6b. do_action('update_option', $id, $old_value, $value)
 *        6c. autoload = wp_determine_option_autoload_value(…, true) → 'on'
 *            (explicit autoload: the row's autoload is SET on update, no
 *            reevaluation, no `wp_default_autoload_value` filter)
 *        6d. UPDATE option_value (+ autoload) — `if (!$result) return false;`:
 *            an UPDATE that changed nothing (0 rows affected — identical
 *            bytes AND flag, e.g. int 508 over a stored "508", which
 *            step 5 does not equate) ends here, no post-write action
 *        6e. do_action("update_option_<id>", $old_value, $value, $id);
 *            do_action('updated_option', $id, $old_value, $value)
 *      (the add branch's INSERT … ON DUPLICATE KEY UPDATE has the same
 *      `if (!$result) return false;` guard before 6f)
 *
 * This writer replicates that sequence exactly, split into the phases the
 * lock discipline demands. Everything up to and including the pre-SQL
 * action (1–6d) is USER CODE and runs in bly_option_write_prepare(),
 * BEFORE the ownership check; the caller's dynamic-choice `$recheck` of
 * the FINAL value runs LAST in that phase — after every replicated hook,
 * after the payload serialization (a smuggled object's __serialize()) and
 * after the autoload determination — so no callback can invalidate the
 * selected entity behind the recheck's back, and NOTHING user-code-capable
 * runs after it: the pinned identity is re-verified hook-free against its
 * raw anchor (bly_stylesheet_anchor — never get_stylesheet(), whose
 * `stylesheet` filter is user code). Then the hook-free tail
 * (bly_option_write_persist: raw anchor + lock ownership, the raw
 * INSERT … ON DUPLICATE KEY UPDATE of value AND autoload — 6e — the
 * post-write ownership re-check, exact verification of the COMPLETE row
 * state: the INTENDED bytes AND the autoload flag) and, only after a
 * VERIFIED persist, the post-write actions (6f / 6e-second —
 * bly_option_write_notify) and the Blocksy cache refresh (its dynamic CSS
 * reads e.g. woocommerce_catalog_columns through get_option()).
 *
 * Deviations from core, all deliberate and all documented in the phase
 * functions: (a) `$old_value` is the RAW row value (or, for an absent
 * row, the `default_option_<id>` filter result exactly as get_option()
 * would return) — the `pre_option_<id>` / `option_<id>` READ filters are
 * not consulted, because the prior state is the byte-exact rollback
 * target and must not be forgeable (this also covers add_option()'s own
 * existence pass, 6a': its default-vs-get_option() comparison is made
 * against that same raw prior value, and its `notoptions` shortcut is
 * taken exactly when core's own step-2 read would have primed it — an
 * absent row); (b) the pre-SQL action
 * (`update_option` / `add_option`) can fire for a write the final recheck
 * (or the size bounds) THEN refuses — a failure mode core does not have,
 * accepted because the recheck must be the last user code before the SQL
 * (fidelity on success paths, recheck-last on refusal paths); (c) a
 * `pre_update_option*` filter (or a non-idempotent second sanitize pass on
 * the add branch) that REWRITES the value is not honored: the persist is
 * verified against the INTENDED bytes — the value after the first
 * sanitize_option() pass — and a mismatch is a verified rollback,
 * `bly_write_failed`, exactly the `pre_set_theme_mod_*` contract of the
 * mods path. `sanitize_option_<id>` rewrites (a validation gate, the
 * option-row analogue of Blocksy's own sanitizer — gate 2) ARE honored:
 * their result is what gets persisted and reported.
 *
 * @param mixed $value The value to write — or, when `$compute` is given,
 *     the pre-lock CANDIDATE it completes.
 * @param mixed $default The schema default — reported as previous_value
 *     when no row existed.
 * @param array{recheck?: null|callable(mixed): (WP_Error|null), compute?: null|callable(mixed): mixed} $in_lock
 *     The in-lock callbacks: `recheck` — the re-check of the FINAL value
 *     (bly_recheck_dynamic_choice); absent/null when static. `compute` —
 *     optional completion of `$value` over the FRESH raw row: receives the
 *     row's current unserialized value (the schema `$default` when the row
 *     is absent — the same convention as the mods path's `$previous_value`),
 *     returns the complete value to write or a WP_Error to abort with
 *     nothing written. It runs FIRST in the critical section, before the
 *     replicated core pipeline (sanitize → filters → same-value → branch →
 *     pre-SQL action → recheck → ownership → persist), exactly where
 *     bly_locked_mod_write runs its compute: a ct-typography candidate
 *     completed from a pre-lock read would let the locked write overwrite
 *     a concurrent writer's fresh keys with the stale expansion
 *     (edit-option's eo_complete_in_lock).
 * @return array{value: mixed, previous_value: mixed, previously_stored: bool}|WP_Error
 *     `value` is the persisted value re-read from the row (stored form:
 *     scalars as strings — what get_option() and blocksy-get-option
 *     return).
 */
function bly_locked_option_write(
    string $id,
    mixed $value,
    mixed $default,
    string $stylesheet,
    array $in_lock = [],
): array|WP_Error {
    // Hook-free anchor first, filtered pin check second — see
    // bly_stylesheet_anchor().
    $pin = bly_pin_identity($stylesheet);
    if (get_stylesheet() !== $stylesheet) {
        return bly_stylesheet_changed_error($id, $stylesheet);
    }
    $recheck = $in_lock['recheck'] ?? null;
    $compute = $in_lock['compute'] ?? null;

    return bly_with_option_row_lock($id, static function (callable $lock_held) use (
        $id,
        $value,
        $default,
        $stylesheet,
        $pin,
        $recheck,
        $compute,
    ): array|WP_Error {
        bly_bust_mods_cache($id);
        $prior = bly_raw_option_row_state($id);
        $previously_stored = $prior['bytes'] !== false;

        if ($compute !== null) {
            // In-lock completion over the fresh raw row (user code, like
            // everything in the prepare phase — BEFORE the ownership check).
            /** @var mixed $value */
            $value = $compute($previously_stored ? maybe_unserialize($prior['bytes']) : $default);
            if ($value instanceof WP_Error) {
                return $value;
            }
        }

        $prepared = bly_option_write_prepare($id, $value, $prior['bytes'], $pin, $recheck);
        if ($prepared instanceof WP_Error) {
            return $prepared;
        }
        if ($prepared['same_value']) {
            // Core's step 5: update_option() returns false here WITHOUT
            // writing and without firing anything further — a no-op, not
            // a failure. Nothing to persist, nothing to verify.
            return [
                'value' => $prepared['old_value'],
                'previous_value' => $previously_stored ? $prepared['old_value'] : $default,
                'previously_stored' => $previously_stored,
            ];
        }

        $persisted = bly_option_write_persist($id, $prepared, $prior, $pin, $lock_held);
        if ($persisted instanceof WP_Error) {
            return $persisted;
        }

        // Core's `if (!$result) return false;` (6e): when the SQL changed
        // nothing — the row already held exactly these bytes and this flag
        // (e.g. int 508 requested over a stored "508": not same-value by
        // expression 5, identical on disk) — core returns false WITHOUT
        // the post-write actions. Same here: verified, nothing to notify.
        if ($persisted['changed']) {
            bly_option_write_notify($id, $prepared);
        }
        $refresh = bly_refresh_blocksy_caches($stylesheet);
        if ($refresh instanceof WP_Error) {
            return $refresh;
        }
        return [
            'value' => maybe_unserialize($persisted['bytes']),
            'previous_value' => $previously_stored ? $prepared['old_value'] : $default,
            'previously_stored' => $previously_stored,
        ];
    });
}

/**
 * Raw state of one wp_options row: its serialized bytes (false when
 * absent) and its stored autoload flag (null when absent) — captured
 * together, inside the lock, as the byte-exact rollback target of the
 * option-storage write (value bytes AND autoload).
 *
 * @return array{bytes: string|false, autoload: string|null}
 */
function bly_raw_option_row_state(string $option_name): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $option_name);
    /** @var mixed $row */
    $row = is_string($sql) ? $wpdb->get_row($sql, 'ARRAY_A') : null;
    if (!is_array($row) || !is_string($row['option_value'] ?? null)) {
        return ['bytes' => false, 'autoload' => null];
    }
    /** @var mixed $autoload */
    $autoload = $row['autoload'] ?? null;
    return ['bytes' => $row['option_value'], 'autoload' => is_string($autoload) ? $autoload : null];
}

/**
 * Option-storage write, phase 1 — ALL the user code, in core's exact
 * order (see bly_locked_option_write for the numbered sequence read from
 * the installed wp-includes/option.php):
 *
 *   1. sanitize_option()                       — `sanitize_option_<id>` honored
 *   2. $old_value                              — raw row value, or the
 *                                                `default_option_<id>` result
 *                                                for an absent row
 *   3–4. pre_update_option_<id>, pre_update_option
 *   5. same-value check on the FINAL filtered value → `same_value`
 *   6. branch: `default_option_<id>` === $old_value → ADD, else UPDATE
 *   6a. (ADD only) the second sanitize_option() pass add_option() runs
 *   6a'. (ADD only) add_option()'s own existence pass — for a row that
 *        EXISTED (core's `notoptions` shortcut is taken exactly when its
 *        step-2 get_option() primed the cache, i.e. for an absent row):
 *        `default_option_<id>` applied AGAIN and compared against the
 *        captured raw prior value (deviation a — core compares against
 *        get_option()); a mismatch is core's `add_option() → false`:
 *        nothing written, no further hook, reported honestly as
 *        `bly_write_failed` (core's update_option() returns false there,
 *        a silent non-write; the requested value is NOT stored, so this
 *        writer does not dress it up as a no-op success)
 *   —  size bounds on the final value (ours; refuses BEFORE any action)
 *   —  payload serialization (user code: __serialize on objects)
 *   —  autoload determination (the customizer's explicit-true path)
 *   6b/6d. the pre-SQL action: `add_option` / `update_option`
 *   —  the pinned stylesheet re-verified (filtered — still before the
 *      recheck, so still allowed to run user code)
 *   —  the caller's `$recheck` of the FINAL value — LAST, after every
 *      hook above, so nothing can invalidate the entity behind it — and
 *      then ONLY the hook-free raw-anchor comparison of the pinned
 *      identity (bly_run_recheck): no filterable lookup after the
 *      vocabulary validation
 *
 * Trade-off (documented, deliberate): the pre-SQL core action fires
 * before the size bounds are known to hold and before the recheck; a
 * write the recheck then refuses (`bly_choice_stale`) has fired
 * `update_option` / `add_option` without a following SQL or post-write
 * action — core has no such path. Ordering the recheck earlier would let
 * a listener of that very action invalidate the entity after the recheck,
 * which is the TOCTOU this pipeline exists to close; recheck-last wins on
 * refusal paths, hook fidelity holds on every success path.
 *
 * @param string|false $prior_bytes The raw prior row bytes (false =
 *     absent), captured inside the lock.
 * @param array{stylesheet: string, anchor: string|false} $pin The pinned
 *     identity with its raw anchor (bly_pin_identity).
 * @param null|callable(mixed): (WP_Error|null) $recheck
 * @return array{
 *     value: mixed,
 *     old_value: mixed,
 *     same_value: bool,
 *     is_add: bool,
 *     intended: string,
 *     payload: string,
 *     autoload: string,
 * }|WP_Error `value` is the FINAL value to persist (post every hook);
 *     `intended` the bytes the row must hold afterwards (the value after
 *     the FIRST sanitize pass — see bly_locked_option_write, deviation c);
 *     `payload` the exact bytes handed to the SQL; `autoload` the flag to
 *     store (insert AND update, the explicit path). Only `old_value` and
 *     `same_value` are meaningful when `same_value` is true.
 */
function bly_option_write_prepare(
    string $id,
    mixed $value,
    string|false $prior_bytes,
    array $pin,
    ?callable $recheck,
): array|WP_Error {
    $stylesheet = $pin['stylesheet'];
    // 1. sanitize_option() — core's first pass (formatting.php:4852-5127:
    // the per-option switch, then the `sanitize_option_<id>` filter with
    // (value, option, original_value)). Its result is what the caller
    // INTENDS to store: `sanitize_option_<id>` is a validation gate, the
    // option-row analogue of Blocksy's own sanitizer, and is honored.
    /** @var mixed $sanitized */
    $sanitized = sanitize_option($id, $value);
    $intended = bly_option_row_bytes($sanitized);

    // 2. $old_value — core reads get_option($id). Raw row discipline (see
    // bly_locked_option_write, deviation a); an absent row yields what
    // get_option() would return: the `default_option_<id>` filter over
    // false, exact signature (option.php get_option()).
    $no_default = false;
    $not_passed = false;
    /** @var mixed $old_value */
    $old_value = $prior_bytes !== false
        ? maybe_unserialize($prior_bytes)
        : apply_filters("default_option_{$id}", $no_default, $id, $not_passed);

    // 3–4. The pre-update filters, core's exact signatures and order.
    /** @var mixed $final */
    $final = apply_filters("pre_update_option_{$id}", $sanitized, $old_value, $id);
    /** @var mixed $final */
    $final = apply_filters('pre_update_option', $final, $id, $old_value);
    if (get_stylesheet() !== $stylesheet) {
        return bly_stylesheet_changed_error($id, $stylesheet);
    }

    // 5. Same-value check on the FINAL filtered value — core's exact
    // expression (option.php:899-922, ticket #38903): unserialized strict
    // equality first, then the (maybe) serialized forms.
    if ($final === $old_value || maybe_serialize($final) === maybe_serialize($old_value)) {
        return bly_option_prepared_same_value($final, $old_value, $intended);
    }

    // 6. ADD vs UPDATE — core's branch condition, exact.
    /** @var mixed $default_value */
    $default_value = apply_filters("default_option_{$id}", $no_default, $id, $not_passed);
    $is_add = $default_value === $old_value;
    if ($is_add) {
        // 6a. add_option() sanitizes AGAIN — the second pass, on the
        // filtered value (option.php add_option()).
        /** @var mixed $final */
        $final = sanitize_option($id, $final);

        // 6a'. add_option()'s own existence pass.
        $refused = bly_add_option_existence_pass($id, $old_value, $prior_bytes);
        if ($refused !== null) {
            return $refused;
        }
    }

    $bound_error = bly_assert_persistable($id, $final);
    if ($bound_error !== null) {
        return $bound_error;
    }

    // The exact SQL payload (maybe_serialize — user code for objects) and
    // the autoload flag, both BEFORE the last user hook and the recheck.
    $payload = bly_option_row_bytes($final);
    $autoload = bly_customizer_option_autoload($id, $final, $payload);

    // 6b (add) / 6d (update): the pre-SQL action, exact signatures.
    $is_add ? do_action('add_option', $id, $final) : do_action('update_option', $id, $old_value, $final);
    if (get_stylesheet() !== $stylesheet) {
        return bly_stylesheet_changed_error($id, $stylesheet);
    }

    // LAST: the dynamic-choice recheck of the FINAL value, after every
    // hook of the replicated pipeline — followed only by the HOOK-FREE
    // raw-anchor comparison of the pinned identity (inside
    // bly_run_recheck): nothing user-code-capable runs behind the recheck.
    $stale = bly_run_recheck($recheck, $final, $id, $pin);
    if ($stale !== null) {
        return $stale;
    }

    return [
        'value' => $final,
        'old_value' => $old_value,
        'same_value' => false,
        'is_add' => $is_add,
        'intended' => $intended,
        'payload' => $payload,
        'autoload' => $autoload,
    ];
}

/**
 * The prepare-phase result for core's step-5 same-value no-op: nothing to
 * persist, `old_value`/`same_value` are the meaningful fields.
 *
 * @return array{value: mixed, old_value: mixed, same_value: bool, is_add: bool, intended: string, payload: string, autoload: string}
 */
function bly_option_prepared_same_value(mixed $final, mixed $old_value, string $intended): array
{
    return [
        'value' => $final,
        'old_value' => $old_value,
        'same_value' => true,
        'is_add' => false,
        'intended' => $intended,
        'payload' => '',
        'autoload' => '',
    ];
}

/**
 * Core add_option()'s OWN existence pass (wp-includes/option.php
 * add_option(), right after its sanitize pass), replicated for the ADD
 * branch of the option-storage write. Core skips it when the `notoptions`
 * cache marks the id absent — which its own step-2 get_option() did
 * exactly when the row was absent — so for a captured-ABSENT row nothing
 * runs here (and `default_option_<id>` fires exactly twice per write, as
 * in core). Otherwise — the row EXISTS and its value equals the
 * registered default, which is what put the write on the add branch —
 * core applies `default_option_<id>` once more and aborts unless the
 * result still equals what get_option() returns: compared here against
 * the captured raw prior value (bly_locked_option_write, deviation a). A
 * second-call mismatch is core's `add_option() → false` → `update_option()
 * → false`: nothing written, no further hook. Same here — reported as an
 * honest `bly_write_failed` rather than a no-op success, since the
 * requested value is NOT what the row holds afterwards.
 *
 * @param mixed $old_value The prior value (raw row, unserialized).
 * @param string|false $prior_bytes The raw prior bytes (false = absent).
 */
function bly_add_option_existence_pass(string $id, mixed $old_value, string|false $prior_bytes): ?WP_Error
{
    if ($prior_bytes === false) {
        return null;
    }
    $no_default = false;
    $not_passed = false;
    /** @var mixed $second_default */
    $second_default = apply_filters("default_option_{$id}", $no_default, $id, $not_passed);
    if ($second_default === $old_value) {
        return null;
    }
    return bly_error('bly_write_failed', sprintf(
        'Writing option "%s" was refused by core\'s add_option() existence check: the stored value equals the registered default, and a default_option_%s filter answered its second evaluation with a different value than the stored one — core\'s update_option() returns false here without writing, and nothing was written here either. Inspect the default_option_%s filters on this site and retry.',
        $id,
        $id,
        $id,
    ));
}

/**
 * The autoload flag a Blocksy customizer save stores for an option-storage
 * row: `WP_Customize_Setting::set_root_value()` calls
 * `update_option($id, $value, true)` — autoload EXPLICITLY true — and both
 * of core's branches then store
 * `wp_determine_option_autoload_value($id, $value, $serialized, true)`,
 * i.e. 'on' on WP 6.6+ (an explicit boolean short-circuits before the
 * `wp_default_autoload_value` filter, so no user code runs here), on the
 * INSERT and on the UPDATE alike — the row's autoload is SET, never
 * reevaluated. Pre-6.6 cores store the literal 'yes'.
 */
function bly_customizer_option_autoload(string $id, mixed $value, string $payload): string
{
    if (!function_exists('wp_determine_option_autoload_value')) {
        return 'yes';
    }
    $explicit = true;
    /** @var mixed $autoload */
    $autoload = wp_determine_option_autoload_value($id, $value, $payload, $explicit);
    return is_string($autoload) && $autoload !== '' ? $autoload : 'on';
}

/**
 * Option-storage write, phase 2 (checks + raw SQL, NO user code before the
 * SQL): the pinned identity (raw anchor, hook-free) and lock ownership
 * re-verified, the raw hook-free upsert of value AND autoload (core's 6e
 * — the explicit autoload path sets the flag on an existing row too), the
 * post-write ownership re-check, and exact raw verification of the
 * COMPLETE row state — the INTENDED bytes AND the intended autoload flag
 * (bytes alone would call a write verified while the flag did not land)
 * — every failure hands off to the verified rollback of bytes AND flag
 * (bly_handle_failed_persist, generic over the row name: bytes restored
 * first, flag last and raw, both re-verified). Returns the persisted
 * bytes on success, plus whether the SQL actually CHANGED the row (rows
 * affected > 0) — core's `$result`.
 *
 * @param array{value: mixed, old_value: mixed, same_value: bool, is_add: bool, intended: string, payload: string, autoload: string} $prepared
 * @param array{bytes: string|false, autoload: string|null} $prior The
 *     captured prior row (bytes false = absent), the rollback target.
 * @param array{stylesheet: string, anchor: string|false} $pin The pinned
 *     identity with its raw anchor (bly_pin_identity).
 * @param callable $lock_held The lock-ownership probe (returns bool).
 * @return array{bytes: string, changed: bool}|WP_Error
 */
function bly_option_write_persist(
    string $id,
    array $prepared,
    array $prior,
    array $pin,
    callable $lock_held,
): array|WP_Error {
    $stylesheet = $pin['stylesheet'];
    if (bly_stylesheet_anchor_moved($pin['anchor'])) {
        return bly_stylesheet_changed_error($id, $stylesheet, bly_stylesheet_anchor_now());
    }
    if ($lock_held() !== true) {
        return bly_error('bly_lock_lost', sprintf(
            'The write lock for the "%s" options row was lost before persisting (the database connection was re-established, which releases advisory locks). Nothing was written — retry.',
            $id,
        ));
    }

    $rows_affected = bly_persist_mods_row_raw(
        $id,
        $prepared['payload'],
        $prepared['autoload'],
        update_autoload: $prepared['autoload'],
    );

    if ($lock_held() !== true) {
        return bly_handle_failed_persist(
            $id,
            $id,
            $prior,
            bly_error('bly_lock_lost', sprintf(
                'The database connection changed WHILE persisting the "%s" options row (a query filter or auto-reconnect) — the write may have run without the advisory lock, so it was rolled back. Nothing effective was written; retry.',
                $id,
            )),
            $stylesheet,
        );
    }

    $persisted = bly_raw_option_row_state($id);
    if ($persisted['bytes'] !== $prepared['intended'] || $persisted['autoload'] !== $prepared['autoload']) {
        return bly_handle_failed_persist(
            $id,
            $id,
            $prior,
            bly_error('bly_write_failed', sprintf(
                'Writing option "%s" did not persist: the re-read wp_options row did not hold the intended value (or autoload flag), so the row was rolled back to its prior state (verified, value AND autoload). A pre_update_option_%s filter rewriting the value, or a database failure, can cause this.',
                $id,
                $id,
            )),
            $stylesheet,
        );
    }
    return ['bytes' => $prepared['intended'], 'changed' => $rows_affected > 0];
}

/**
 * Rollback companion of BOTH persist paths (the option-storage row and
 * the theme_mods row alike): put an existing row's autoload flag back to
 * the captured prior one — raw, hook-free UPDATE (the options API cannot
 * restore an existing row's flag without churn), and AFTER the byte
 * restore of bly_handle_failed_persist(), never before: the value restore
 * runs update_option() on core's NULL-autoload path, which re-evaluates
 * any `auto*` flag it finds on the row and would rewrite a flag restored
 * too early (`auto-on` → `auto`); a flag restored last is what the
 * verification re-reads. Both halves of "exact prior state" (bytes AND
 * autoload) are then re-established and verified together. No-op when the
 * row did not exist before (the byte restore deleted it again).
 *
 * @param array{bytes: string|false, autoload: string|null} $prior
 */
function bly_restore_option_row_autoload(string $option_name, array $prior): void
{
    if ($prior['autoload'] === null || $prior['bytes'] === false) {
        return;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $wpdb->update($wpdb->options, ['autoload' => $prior['autoload']], ['option_name' => $option_name]);
    bly_bust_mods_cache($option_name);
}

/**
 * Option-storage write, phase 3: the post-write actions core fires after
 * its OWN successful SQL, exact signatures — `update_option_<id>` +
 * `updated_option` on the update branch, `add_option_<id>` +
 * `added_option` on the add branch — so downstream listeners
 * (WooCommerce's `updated_option` handlers) see the change the way they
 * would after a customizer save. Runs only after the persist is VERIFIED
 * — user code after the SQL, never between the ownership check and it.
 * The generic actions fire exactly once per successful write, like core.
 *
 * @param array{value: mixed, old_value: mixed, same_value: bool, is_add: bool, intended: string, payload: string, autoload: string} $prepared
 */
function bly_option_write_notify(string $id, array $prepared): void
{
    if ($prepared['is_add']) {
        do_action("add_option_{$id}", $id, $prepared['value']);
        do_action('added_option', $id, $prepared['value']);
        return;
    }
    do_action("update_option_{$id}", $prepared['old_value'], $prepared['value'], $id);
    do_action('updated_option', $id, $prepared['old_value'], $prepared['value']);
}

// ---------------------------------------------------------------------------
// Value validation / coercion — gate 1: schema-derived typed checks
// ---------------------------------------------------------------------------

/**
 * Validate (and minimally coerce) a candidate value against one option's
 * schema definition, then pass the accepted value through Blocksy's own
 * per-control sanitizer (gate 2, see bly_apply_blocksy_sanitizer). Every
 * leaf is checked: scalar options by their typed leaf spec, responsive
 * maps per device leaf, structured values per overlapping key against the
 * default's structure. Returns the value to write (possibly coerced) or a
 * WP_Error — on error nothing must be persisted by callers.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_validate_option_value(array $row, mixed $value): mixed
{
    $caps = bly_check_value_caps($row, $value);
    if ($caps instanceof WP_Error) {
        return $caps;
    }

    /** @var mixed $validated */
    $validated = match ($row['type']) {
        'ct-switch' => bly_validate_switch_value($row, $value),
        // Layer lists are validated against their per-layer declarations
        // (bly_validate_layers_value) — the native sanitizer has no branch
        // for them and their nested settings reach markup and CSS.
        'ct-layers' => bly_validate_layers_value($row, $value),
        default => bly_validate_against_schema($row, $value),
    };
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    return bly_apply_blocksy_sanitizer($row, $validated);
}

/**
 * Complete a PARTIAL `ct-typography` candidate into a FULL typography
 * object. Blocksy's typography CSS output (inc/css/typography.php:30-190)
 * reads every key of the stored object unguarded — family, variation,
 * size, line-height, letter-spacing, text-transform, text-decoration —
 * and blocksy_output_css_vars() reads every DEVICE of a responsive
 * size / line-height / letter-spacing unguarded too
 * (inc/css/fundamentals.php:143-180: a stored `{desktop}`-only map is
 * kept as is by blocksy_expand_responsive_value and `$value['tablet']`
 * warns on every CSS regeneration). The customizer UI always stores the
 * complete object, so the write paths must too:
 *
 *  1. start from the COMPLETE schema default of the option (the
 *     installed option file's `value` — the key set is never hardcoded);
 *  2. overlay the current stored value when it is a valid keyed object
 *     (a legacy non-array / list-shaped stored value contributes
 *     nothing — the default stands in for it);
 *  3. overlay the candidate (keys passed win);
 *  4. complete every responsive `size` / `line-height` / `letter-spacing`
 *     device map: a device the candidate omits — `desktop` included: a
 *     `{tablet}`-only or `{mobile}`-only candidate is legitimate input —
 *     keeps what renders today: the current value's device leaf, a
 *     current scalar expanded to all devices exactly like
 *     blocksy_expand_responsive_value does, a current map that is itself
 *     partial completed tablet ← desktop, mobile ← tablet (the theme's
 *     own expansion order), and — when the current value offers no
 *     usable desktop leaf (legacy/corrupt stored map) — the schema
 *     default's leaf for that key; with no usable desktop anywhere the
 *     write is refused (WP_Error), never persisted incomplete;
 *  5. the result carries every key of the schema default AND every
 *     responsive device map among those keys carries desktop, tablet
 *     and mobile (post-condition, WP_Error otherwise — a schema default
 *     that is not a keyed object cannot complete anything, so such
 *     candidates are returned as they are and gate 1 decides). Blocksy's
 *     descriptor generation logs `value[desktop] must be a scalar` and
 *     fundamentals.php warns on every regen for a map without desktop.
 *
 * `$current` is the FRESH stored value read INSIDE the write lock on
 * both write paths (edit-option's compute closure, the per-post
 * epo_finalize_batch) — completing from a pre-lock read would let the
 * locked merge overwrite a concurrent writer's fresh keys with stale
 * ones. Non-object candidates, list-shaped candidates and
 * non-typography rows pass through untouched (their own gates decide).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_complete_typography_value(array $row, mixed $value, mixed $current): mixed
{
    if ($row['type'] !== 'ct-typography' || !is_array($value) || bly_array_is_list($value)) {
        return $value;
    }
    /** @var mixed $default */
    $default = $row['option']['value'] ?? null;
    if (!is_array($default) || $default === [] || bly_array_is_list($default)) {
        return $value;
    }
    $base = bly_typography_base($default, $current);
    $merged = bly_complete_typography_responsive_keys($row, array_merge($base, $value), $base, $default);
    if ($merged instanceof WP_Error) {
        return $merged;
    }
    $missing = array_diff(array_map('strval', array_keys($default)), array_map('strval', array_keys($merged)));
    if ($missing !== []) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": the typography object could not be completed — missing key(s): %s.',
            $row['id'],
            implode(', ', $missing),
        ));
    }
    return $merged;
}

/**
 * The completion base: the complete schema default, overlaid with the
 * current stored value when that is a valid keyed object (a legacy
 * non-array or list-shaped stored value contributes nothing).
 *
 * @param array<array-key, mixed> $default
 * @return array<array-key, mixed>
 */
function bly_typography_base(array $default, mixed $current): array
{
    if (is_array($current) && $current !== [] && !bly_array_is_list($current)) {
        return array_merge($default, $current);
    }
    return $default;
}

/**
 * Complete the responsive typography keys (size / line-height /
 * letter-spacing) of a merged object — every device map among them is
 * filled from the base's leaf for that key, then from the schema
 * default's leaf (bly_complete_typography_device_map), and must come out
 * with all three devices: a map whose `desktop` could not be sourced
 * anywhere is refused — a desktop-less typography map persisted as is
 * warns on every CSS regeneration (inc/css/fundamentals.php:143-180)
 * and Blocksy's descriptor generation logs `value[desktop] must be a
 * scalar`.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $merged
 * @param array<array-key, mixed> $base
 * @param array<array-key, mixed> $default
 * @return array<array-key, mixed>|WP_Error
 */
function bly_complete_typography_responsive_keys(array $row, array $merged, array $base, array $default): array|WP_Error
{
    foreach (['size', 'line-height', 'letter-spacing'] as $key) {
        if (!isset($merged[$key]) || !is_array($merged[$key]) || !bly_is_device_map($merged[$key])) {
            continue;
        }
        $completed = bly_complete_typography_device_map($merged[$key], $base[$key] ?? null, $default[$key] ?? null);
        if (!array_key_exists('desktop', $completed)) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": `%s` is a responsive map without a `desktop` value and neither the stored value nor the schema default offers one to complete it from; pass {desktop, tablet?, mobile?} (the theme reads every device of a typography map unguarded).',
                $row['id'],
                $key,
            ));
        }
        $merged[$key] = $completed;
    }
    return $merged;
}

/**
 * The current-INDEPENDENT pre-lock check of a ct-typography candidate:
 * size caps and shape — a non-empty keyed object ({family, variation,
 * size, …} or any subset). Completion from the stored value and the
 * typed gates run inside the write lock (see bly_complete_typography_value).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return array<array-key, mixed>|WP_Error
 */
function bly_typography_candidate_shape(array $row, mixed $value): array|WP_Error
{
    $caps = bly_check_value_caps($row, $value);
    if ($caps instanceof WP_Error) {
        return $caps;
    }
    if (!is_array($value) || $value === [] || bly_array_is_list($value)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s" is a ct-typography control and stores a keyed object {family, variation, size, line-height, letter-spacing, text-transform, text-decoration} — pass an object (a partial one is completed from the current value), not a %s.',
            $row['id'],
            is_array($value) ? 'list' : 'scalar',
        ));
    }
    return $value;
}

/**
 * Complete one responsive typography device map from the reference that
 * renders today: a reference scalar expands to every device, a reference
 * map supplies its own device leaves (itself completed tablet ← desktop,
 * mobile ← tablet); a reference without a usable desktop (legacy/corrupt
 * stored map) is replaced by the schema default's leaf for the key.
 * Every device the candidate omits — desktop included — is taken from
 * that fallback; with no usable fallback the candidate expands like the
 * theme expands a scalar (tablet ← desktop, mobile ← tablet), and a
 * candidate without desktop comes back WITHOUT desktop (keys preserved)
 * for the caller to refuse.
 *
 * @param array<array-key, mixed> $map
 * @return array<string, mixed>
 */
function bly_complete_typography_device_map(array $map, mixed $reference, mixed $schema_default = null): array
{
    $fallback = bly_typography_device_fallback($reference, $schema_default);
    if (!array_key_exists('desktop', $map) && !array_key_exists('desktop', $fallback)) {
        return array_combine(array_map('strval', array_keys($map)), array_values($map));
    }
    /** @var mixed $desktop */
    $desktop = array_key_exists('desktop', $map) ? $map['desktop'] : $fallback['desktop'];
    /** @var mixed $tablet */
    $tablet = array_key_exists('tablet', $map) ? $map['tablet'] : $fallback['tablet'] ?? $desktop;
    /** @var mixed $mobile */
    $mobile = array_key_exists('mobile', $map) ? $map['mobile'] : $fallback['mobile'] ?? $tablet;
    return ['desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile];
}

/**
 * What a typography key renders as per device today: a scalar reference
 * on every device (the theme's scalar expansion), a device-map reference
 * completed tablet ← desktop, mobile ← tablet. A reference the theme
 * cannot render — null, a non-map array, or a device map WITHOUT
 * desktop (a legacy/corrupt stored value) — falls back to the schema
 * default's leaf, expanded the same way; a desktop-less reference map
 * keeps its own tablet/mobile leaves over that default. Nothing when
 * neither offers a desktop.
 *
 * @return array<string, mixed>
 */
function bly_typography_device_fallback(mixed $reference, mixed $schema_default = null): array
{
    if (is_array($reference) && bly_is_device_map($reference)) {
        if (array_key_exists('desktop', $reference)) {
            return bly_complete_device_map($reference);
        }
        $base = bly_typography_device_fallback($schema_default);
        return $base === [] ? [] : bly_complete_device_map(array_merge($base, $reference));
    }
    if ($reference === null || is_array($reference)) {
        return $schema_default === null ? [] : bly_typography_device_fallback($schema_default);
    }
    return ['desktop' => $reference, 'tablet' => $reference, 'mobile' => $reference];
}

/**
 * Complete a partial top-level {desktop, tablet, mobile} map the way the
 * theme expands a scalar — tablet falls back to desktop, mobile to
 * tablet (blocksy_expand_responsive_value, inc/css/fundamentals.php:52).
 *
 * @param array<array-key, mixed> $map with `desktop` present
 * @return array<string, mixed>
 */
function bly_complete_device_map(array $map): array
{
    /** @var mixed $desktop */
    $desktop = $map['desktop'];
    /** @var mixed $tablet */
    $tablet = array_key_exists('tablet', $map) ? $map['tablet'] : $desktop;
    /** @var mixed $mobile */
    $mobile = array_key_exists('mobile', $map) ? $map['mobile'] : $tablet;
    return ['desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile];
}

/**
 * The size gates every written value clears before any typed check:
 * JSON-encodable, at most BLY_MAX_WRITE_BYTES encoded, and no array/object
 * node anywhere inside it above BLY_MAX_LIST_ITEMS elements. Shared by the
 * theme_mods/option writes (bly_validate_option_value) and the per-post
 * options writes (page-options-helpers.php), which run their own gate 2.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_check_value_caps(array $row, mixed $value): ?WP_Error
{
    $encoded = wp_json_encode($value);
    if (!is_string($encoded)) {
        return bly_error('bly_invalid_input', message: '`value` is not JSON-encodable.');
    }
    if (strlen($encoded) > BLY_MAX_WRITE_BYTES) {
        return bly_error('bly_invalid_input', sprintf(
            '`value` is %d bytes JSON-encoded, above the %d-byte write cap.',
            strlen($encoded),
            BLY_MAX_WRITE_BYTES,
        ));
    }
    $oversized = bly_find_oversized_array($value);
    if ($oversized !== null) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": the value contains an array/object with %d elements, above the %d-element cap. '
            . 'No Blocksy customizer value legitimately holds that many entries — PHP-serializing huge '
            . 'arrays into the autoloaded theme_mods row would balloon every request. Nothing was written.',
            $row['id'],
            $oversized,
            BLY_MAX_LIST_ITEMS,
        ));
    }
    return null;
}

/**
 * Element count of the first array node (at any depth) exceeding
 * BLY_MAX_LIST_ITEMS, or null when every node is within bounds. Objects
 * are traversed through their public properties too — a filter can wrap
 * an oversized array in a stdClass, and the serialized row would balloon
 * all the same. The JSON-byte cap alone is not enough: 200 KB of JSON
 * holding a 100k-element list PHP-serializes into a megabyte-plus
 * autoloaded theme_mods row, so element counts are bounded independently.
 * Real Blocksy values (palettes, typography maps, builder sections) sit
 * far below the cap.
 */
function bly_find_oversized_array(mixed $value): ?int
{
    if (is_object($value)) {
        $value = get_object_vars($value);
    }
    if (!is_array($value)) {
        return null;
    }
    if (count($value) > BLY_MAX_LIST_ITEMS) {
        return count($value);
    }
    /** @var mixed $item */
    foreach ($value as $item) {
        $nested = bly_find_oversized_array($item);
        if ($nested !== null) {
            return $nested;
        }
    }
    return null;
}

/**
 * ct-switch values are the strings 'yes' / 'no' throughout the theme
 * (`blocksy_get_theme_mod($id, 'no') === 'yes'`) — EXCEPT switches
 * declared with `'behavior' => 'bool'`, which store literal booleans and
 * whose native sanitizer only accepts [true, false] (validator.php:107).
 * Both vocabularies are accepted on input and coerced to the one the
 * option declares.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return bool|string|WP_Error
 */
function bly_validate_switch_value(array $row, mixed $value): bool|string|WP_Error
{
    $bool_behavior = ($row['option']['behavior'] ?? null) === 'bool';

    if (is_bool($value)) {
        if ($bool_behavior) {
            return $value;
        }
        return $value ? 'yes' : 'no';
    }
    if ($value === 'yes' || $value === 'no') {
        return $bool_behavior ? $value === 'yes' : $value;
    }

    return bly_error('bly_invalid_input', sprintf(
        'Option "%s" is a ct-switch and only accepts "yes", "no", or a boolean.',
        $row['id'],
    ));
}

/**
 * Schema-derived structural validation: dispatches scalar candidates to the
 * typed leaf check, responsive maps to per-device leaf checks, and
 * structured candidates to the structure comparison against the default.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_validate_against_schema(array $row, mixed $value): mixed
{
    /** @var mixed $default */
    $default = $row['option']['value'] ?? null;
    $responsive = bly_option_is_responsive($row['option']);

    if (!is_array($value)) {
        if (is_array($default)) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s" stores a structured value (object/array, like its default), not a scalar. Read the current shape with blocksy-get-option first.',
                $row['id'],
            ));
        }
        return bly_validate_leaf($row, $value, $default, context: 'value');
    }

    // Array candidate. A responsive per-device map is validated leaf by
    // leaf; the reference default for each leaf is the default's desktop
    // leaf when the default is itself a device map.
    if (bly_is_device_map($value)) {
        if (!$responsive && !(is_array($default) && bly_is_device_map($default))) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s" is not responsive — it stores a single scalar value (like its default), not a {desktop, tablet, mobile} map.',
                $row['id'],
            ));
        }
        return bly_validate_device_map($row, $value, $default);
    }

    if (!is_array($default)) {
        return bly_error('bly_invalid_input', sprintf(
            $responsive
                ? 'Option "%s" stores a scalar or a responsive map with keys desktop/tablet/mobile (desktop required); got keys: %s.'
                : 'Option "%s" stores a scalar value (like its default), not an object/array; got keys: %s.',
            $row['id'],
            implode(', ', array_map('strval', array_keys($value))),
        ));
    }

    if (bly_is_device_map($default)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s" stores a responsive map with keys desktop/tablet/mobile (desktop required); got keys: %s.',
            $row['id'],
            implode(', ', array_map('strval', array_keys($value))),
        ));
    }

    return bly_validate_structured($row, $value, $default);
}

/**
 * Whether the option schema marks this option responsive: Blocksy uses
 * `'responsive' => true` or a per-device config array — both count,
 * everything else does not.
 *
 * @param array<array-key, mixed> $option
 */
function bly_option_is_responsive(array $option): bool
{
    /** @var mixed $responsive_raw */
    $responsive_raw = $option['responsive'] ?? null;
    return $responsive_raw === true || is_array($responsive_raw) && $responsive_raw !== [];
}

/**
 * Whether an array looks like a responsive per-device value: non-empty,
 * keys drawn only from desktop/tablet/mobile.
 *
 * @param array<array-key, mixed> $value
 */
function bly_is_device_map(array $value): bool
{
    if ($value === []) {
        return false;
    }
    foreach (array_keys($value) as $key) {
        if (!in_array($key, ['desktop', 'tablet', 'mobile'], strict: true)) {
            return false;
        }
    }
    return true;
}

/**
 * Validate a responsive {desktop, tablet, mobile} candidate: desktop is
 * required, and EVERY present device leaf must pass the option's typed
 * leaf check (a numeric option rejects ['desktop' => 'wide'], not just
 * non-device keys). Leaves are coerced individually (numeric strings on
 * numeric options), and the map is returned rebuilt from the coerced
 * leaves.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $value
 * @return array<string, mixed>|WP_Error
 */
function bly_validate_device_map(array $row, array $value, mixed $default): array|WP_Error
{
    if (!array_key_exists('desktop', $value)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": a responsive map must include the `desktop` key; got keys: %s.',
            $row['id'],
            implode(', ', array_map('strval', array_keys($value))),
        ));
    }

    /** @var mixed $leaf_default */
    $leaf_default = is_array($default) && bly_is_device_map($default) ? $default['desktop'] ?? null : $default;

    $out = [];
    /** @var mixed $leaf */
    foreach ($value as $device => $leaf) {
        /** @var mixed $validated */
        $validated = bly_validate_leaf($row, $leaf, $leaf_default, context: sprintf('`%s` leaf', (string) $device));
        if ($validated instanceof WP_Error) {
            return $validated;
        }
        /** @var mixed */
        $out[(string) $device] = $validated;
    }
    return $out;
}

/**
 * Typed check for one scalar leaf, driven by what the schema DECLARES —
 * never by the runtime type of the default value: a declared finite
 * choice set wins; then numerically-constrained options (ct-slider /
 * ct-number control types, declared `units`, `defaultUnit`) put EVERY
 * leaf — numeric or string, whatever the default's kind — through the
 * range/unit gate; then int/float defaults demand numeric leaves
 * (bounded when the option declares bounds), unit-shaped string defaults
 * ('16px') demand grammar-clean number+unit strings, bool defaults demand
 * booleans, and string defaults accept string/number scalars (never
 * booleans). With no usable reference any scalar passes; Blocksy's own
 * sanitizer still runs after this gate.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_validate_leaf(array $row, mixed $leaf, mixed $reference_default, string $context): mixed
{
    if (is_array($leaf)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": %s must be a scalar, not an object/array.',
            $row['id'],
            $context,
        ));
    }

    $choice_values = bly_choice_vocabulary($row);
    if ($choice_values !== null && !is_bool($leaf) && $leaf !== null) {
        return bly_validate_choice_leaf($row['id'], $leaf, $choice_values, $context);
    }

    if (bly_option_is_numeric_constrained($row['option'])) {
        return bly_validate_constrained_leaf($row, $leaf, $context);
    }

    if (is_int($reference_default) || is_float($reference_default)) {
        return bly_validate_numeric_leaf($row, $leaf, $reference_default, $context);
    }

    if (
        is_string($leaf)
        && !is_numeric($leaf)
        && is_string($reference_default)
        && bly_parse_unit_token($reference_default) !== null
    ) {
        return bly_validate_reference_unit_leaf($row['id'], $leaf, $reference_default, $context);
    }

    return bly_validate_kindred_leaf($row['id'], $leaf, $reference_default, $context);
}

/**
 * Whether the RESOLVED schema declaration marks this option numerically
 * constrained — slider/number control types and unit vocabularies. The
 * runtime type of the default value plays no part: a slider whose default
 * is the string '60px' (or null) bounds its candidates all the same.
 *
 * @param array<array-key, mixed> $option
 */
function bly_option_is_numeric_constrained(array $option): bool
{
    /** @var mixed $type */
    $type = $option['type'] ?? null;
    return $type === 'ct-number' || bly_option_is_unit_bearing($option);
}

/**
 * The range/unit gate for every scalar leaf of a numerically-constrained
 * option: non-numeric strings must satisfy the declared value+unit
 * grammar; everything else must coerce to a finite number and sit inside
 * the range the declaration binds bare numbers to
 * (bly_bare_number_range).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_validate_constrained_leaf(array $row, mixed $leaf, string $context): mixed
{
    if (is_string($leaf) && !is_numeric($leaf)) {
        if (bly_option_is_unit_bearing($row['option'])) {
            return bly_validate_unit_leaf($row['id'], $leaf, $row['option'], $context);
        }
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": %s must be numeric; got %s.',
            $row['id'],
            $context,
            (string) wp_json_encode($leaf),
        ));
    }

    $number = bly_coerce_number($leaf);
    if ($number === null) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": %s must be a finite number; got %s.',
            $row['id'],
            $context,
            is_scalar($leaf) ? (string) wp_json_encode($leaf) : gettype($leaf),
        ));
    }

    $range_error = bly_check_declared_range(
        $row['id'],
        (float) $number,
        bly_bare_number_range($row['option']),
        $context,
        unit: '',
    );
    return $range_error ?? $number;
}

/**
 * The range binding a BARE numeric leaf on a constrained option: the
 * top-level `min`/`max` when declared, else the `defaultUnit` entry's
 * bounds, else the FIRST declared unit's bounds — the unit Blocksy itself
 * applies when rendering a unitless number.
 *
 * @param array<array-key, mixed> $option
 * @return array{min: null|float, max: null|float}
 */
function bly_bare_number_range(array $option): array
{
    $top = bly_option_range($option, unit_entry: null);
    if ($top['min'] !== null || $top['max'] !== null) {
        return $top;
    }
    $units = bly_declared_units($option);
    /** @var mixed $default_unit */
    $default_unit = $option['defaultUnit'] ?? null;
    $entry = is_string($default_unit) ? $units[strtolower($default_unit)] ?? null : null;
    $entry ??= $units === [] ? null : reset($units);
    return is_array($entry) ? bly_option_range($option, $entry) : $top;
}

/**
 * A scalar leaf on a choice-bearing option must be one of the declared
 * choice values, matched stringly (agents may send ints for string keys
 * and vice versa) but returned in its DECLARED type: Blocksy's own
 * per-control validator strict-compares (a menu term id must persist as
 * the int the declaration carries, not the string the agent sent).
 * `$valid` is the shape-normalized vocabulary from
 * bly_choice_typed_values — for dynamic sources (the menu picker) it is
 * resolved live at call time, so it already lists the current nav_menu
 * term ids. A menu id that EXISTS but is absent from the vocabulary is a
 * menu without items (blocksy_get_menus_items filters with hide_empty,
 * and Blocksy's own validator would silently swap it for the default) —
 * that case gets a precise, actionable refusal instead of the generic
 * vocabulary error.
 *
 * @param list<int|string> $valid
 * @return mixed
 */
function bly_validate_choice_leaf(string $id, mixed $leaf, array $valid, string $context): mixed
{
    if (is_scalar($leaf)) {
        foreach ($valid as $declared) {
            if ((string) $declared === (string) $leaf) {
                return $declared;
            }
        }
    }
    $strings = array_map('strval', $valid);
    if (in_array('blocksy_location', $strings, strict: true) && is_scalar($leaf) && ctype_digit((string) $leaf)) {
        $menu_id = (int) (string) $leaf;
        if (bly_nav_menu_exists($menu_id)) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": navigation menu %d exists but has no items, and Blocksy\'s menu picker only accepts '
                . 'menus containing at least one item. Add an item to the menu first, then retry. '
                . 'Currently valid: %s.',
                $id,
                $menu_id,
                implode(', ', $strings),
            ));
        }
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": navigation menu %d does not exist (deleted, or never created). Currently valid: %s.',
            $id,
            $menu_id,
            implode(', ', $strings),
        ));
    }
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": %s only accepts: %s%s.',
        $id,
        $context,
        implode(', ', array_slice($strings, offset: 0, length: BLY_MAX_CHOICES)),
        count($strings) > BLY_MAX_CHOICES
            ? sprintf(', … (+%d more — see blocksy-get-option)', count($strings) - BLY_MAX_CHOICES)
            : '',
    ));
}

/**
 * Whether nav_menu term `$menu_id` exists RIGHT NOW — a real term query
 * (WP_Term_Query keys its cache by the `terms` last_changed stamp, so
 * after bly_bust_entity_caches() this hits the database), not the
 * per-request term object cache wp_get_nav_menu_object() reads through.
 * Shared by gate 1 and the in-lock re-check so both name the same fact.
 */
function bly_nav_menu_exists(int $menu_id): bool
{
    /** @var mixed $found */
    $found = get_terms([
        'taxonomy' => 'nav_menu',
        'include' => [$menu_id],
        'hide_empty' => false,
        'fields' => 'ids',
    ]);
    return is_array($found) && $found !== [];
}

/**
 * Invalidate the per-request query caches behind a dynamic vocabulary's
 * generator, so the fresh re-resolution actually re-queries the database
 * instead of replaying the request-start result: WP_Term_Query and
 * WP_Query key their caches by the `terms` / `posts` last_changed stamps
 * (non-persistent object cache = one stamp per request, blind to what
 * OTHER processes changed since). Bumping the stamp is exactly what core
 * does after its own term/post writes; it only redirects cache keys.
 * Known limit, handled elsewhere: blocksy_companion_get_content_blocks()
 * memoizes its id list in a function-local static and reads each block's
 * type through the (request-local) meta cache — no stamp bump reaches
 * that, so a ct_content_block deleted or unpublished by ANOTHER process
 * mid-window would stay listed for the rest of this request. The
 * content-block re-check therefore does not stop at the reloaded schema:
 * see bly_content_block_live_check().
 */
function bly_bust_entity_caches(string $source): void
{
    if ($source === BLY_CHOICE_SOURCE_NAV_MENU || $source === BLY_CHOICE_SOURCE_TERM) {
        wp_cache_set_terms_last_changed();
        return;
    }
    wp_cache_set_posts_last_changed();
}

/**
 * In-lock TOCTOU re-check for DYNAMIC choice vocabularies — the second
 * half of the fresh-re-read discipline bly_locked_mod_write applies to
 * the mods row itself. Gate 1 validated `$value` against the vocabulary
 * as it stood at request start; a nav menu can be deleted or emptied (a
 * page trashed, a content block removed) between that check and the
 * persist, and Blocksy's own picker/validator would then silently swap
 * the stale id for the default. So, INSIDE the lock, immediately before
 * the persist: STATIC vocabularies (bly_choice_source() === null) return
 * at once — no reload, no overhead; DYNAMIC ones re-resolve the declaring
 * schema fresh through `$fresh_index` (bly_fresh_option_index for global
 * options, a re-run bly_builder_item_option_index for builder items —
 * the SAME loaders and the SAME bly_choice_vocabulary() extraction every
 * surface uses, never a second implementation) and re-run the SAME gate-1
 * validation against the fresh row. Any divergence — declaration gone,
 * vocabulary now empty, value no longer accepted, or accepted in a
 * different typed form — fails closed with `bly_choice_stale` (409) and
 * the caller writes nothing. For the content-block vocabulary the reload
 * is followed by a direct, cache-defeating row check
 * (bly_content_block_live_check) — Companion's resolver memoizes across
 * the request, see there. Runs as the write path's `$recheck` step: AFTER
 * the pre_set filter window on the FINAL value (a filter can invalidate
 * the vocabulary as a side effect while returning the value unchanged),
 * and BEFORE the ownership/stylesheet re-checks — reloading option files
 * IS user code (option files and `blocksy:options:retrieve` filters run),
 * which must precede the final checks by the write path's contract.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 *     The request-start row `$value` was validated against.
 * @param mixed $value The gate-1/gate-2 clean value about to be persisted.
 * @param callable(): (array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|WP_Error) $fresh_index
 *     Reloads the declaring schema fresh (uncached).
 * @return WP_Error|null Null when the value is still valid against the
 *     fresh vocabulary (or the vocabulary is static).
 */
function bly_recheck_dynamic_choice(array $row, mixed $value, callable $fresh_index): ?WP_Error
{
    $source = bly_choice_source($row);
    if ($source === null) {
        return null;
    }

    bly_bust_entity_caches($source);
    $fresh = $fresh_index();
    if ($fresh instanceof WP_Error) {
        return $fresh;
    }
    if (!isset($fresh[$row['id']])) {
        return bly_choice_stale_error(
            $row['id'],
            $source,
            detail: 'its option declaration is no longer present in the freshly loaded schema',
        );
    }
    $fresh_row = $fresh[$row['id']];
    if (bly_choice_vocabulary($fresh_row) === null) {
        return bly_choice_stale_error(
            $row['id'],
            $source,
            detail: 'the choice set is now EMPTY (every entity behind it is gone), so the value cannot be verified',
        );
    }

    /** @var mixed $revalidated */
    $revalidated = bly_validate_against_schema($fresh_row, $value);
    if ($revalidated instanceof WP_Error) {
        return bly_choice_stale_error($row['id'], $source, $revalidated->get_error_message());
    }
    if ($revalidated !== $value) {
        return bly_choice_stale_error(
            $row['id'],
            $source,
            detail: 'the value is still listed but its declared type changed, so the typed form validated earlier would no longer match',
        );
    }
    if ($source === BLY_CHOICE_SOURCE_CONTENT_BLOCK) {
        return bly_content_block_live_check($row['id'], $value);
    }
    return null;
}

/**
 * Cross-process freshness for the content-block vocabulary — the one
 * dynamic source whose declaring generator can NOT be made fresh by a
 * reload. Discovery, gate 1 and the schema reload above all go through
 * Companion's own blocksy_companion_get_content_blocks()
 * (framework/premium/helpers/content-blocks.php:98): its id list is a
 * function-local static primed on first call, and each id's
 * template_type/title come from the request-local post-meta/post caches
 * — a block force-deleted, trashed or unpublished by ANOTHER process
 * after that first call stays listed for the rest of this request, and
 * no last_changed stamp bump reaches a function static. Sharing that
 * resolver everywhere else is right (one vocabulary, request-local
 * caching is fine for surfacing and the request-start gate); the
 * IN-LOCK re-check is the one place that must see the database as it is
 * NOW, so it deliberately diverges here: one direct $wpdb row lookup
 * (no object cache, no query cache, no filters) that re-establishes the
 * three facts the vocabulary encodes — the post exists, its post_type is
 * ct_content_block, it is published — plus the template_type meta the
 * hook picker filters on. Any miss → `bly_choice_stale`, nothing
 * written. Non-numeric values (the picker's '' placeholder) carry no row
 * to check and pass through — the schema re-validation above already
 * judged them.
 */
function bly_content_block_live_check(string $id, mixed $value): ?WP_Error
{
    if (!is_scalar($value) || !ctype_digit((string) $value)) {
        return null;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare(
        "SELECT p.post_type, p.post_status, pm.meta_value AS template_type FROM {$wpdb->posts} p"
        . " LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'template_type'"
        . ' WHERE p.ID = %d LIMIT 1',
        (int) (string) $value,
    );
    /** @var object|null $live */
    $live = is_string($sql) ? $wpdb->get_row($sql) : null;
    if (!is_object($live)) {
        return bly_choice_stale_error(
            $id,
            BLY_CHOICE_SOURCE_CONTENT_BLOCK,
            detail: sprintf('content block %d no longer exists in the database', (int) (string) $value),
        );
    }
    /** @var mixed $post_type */
    $post_type = property_exists($live, 'post_type') ? $live->post_type : null;
    /** @var mixed $post_status */
    $post_status = property_exists($live, 'post_status') ? $live->post_status : null;
    /** @var mixed $template_type */
    $template_type = property_exists($live, 'template_type') ? $live->template_type : null;
    if ($post_type !== BLY_CB_POST_TYPE) {
        return bly_choice_stale_error(
            $id,
            BLY_CHOICE_SOURCE_CONTENT_BLOCK,
            detail: sprintf(
                'post %d is not a ct_content_block (post_type "%s")',
                (int) (string) $value,
                is_scalar($post_type) ? (string) $post_type : '?',
            ),
        );
    }
    if ($post_status !== 'publish') {
        return bly_choice_stale_error(
            $id,
            BLY_CHOICE_SOURCE_CONTENT_BLOCK,
            detail: sprintf(
                'content block %d is no longer published (status "%s")',
                (int) (string) $value,
                is_scalar($post_status) ? (string) $post_status : '?',
            ),
        );
    }
    if ($template_type !== 'hook') {
        return bly_choice_stale_error(
            $id,
            BLY_CHOICE_SOURCE_CONTENT_BLOCK,
            detail: sprintf(
                'content block %d is no longer a hook block (template_type "%s")',
                (int) (string) $value,
                is_scalar($template_type) ? (string) $template_type : '?',
            ),
        );
    }
    return null;
}

/**
 * WP_Error for a write refused because a dynamic choice vocabulary
 * changed between gate-1 validation and the in-lock persist. HTTP 409.
 */
function bly_choice_stale_error(string $id, string $source, string $detail): WP_Error
{
    $labels = [
        BLY_CHOICE_SOURCE_NAV_MENU => 'the live navigation-menu vocabulary',
        BLY_CHOICE_SOURCE_CONTENT_BLOCK => 'the live content-block vocabulary',
        BLY_CHOICE_SOURCE_TERM => 'the live term vocabulary',
        BLY_CHOICE_SOURCE_PAGE => 'the live page vocabulary',
    ];
    // The gate-1 detail already names the option; keep one prefix.
    $detail = (string) preg_replace('/^Option "[^"]*": /', replacement: '', subject: $detail);
    return bly_error('bly_choice_stale', sprintf(
        'Option "%s": the value passed validation, but %s changed before the write could be persisted — %s Nothing was written; re-read the current choices (blocksy-get-option / blocksy-get-header / blocksy-get-footer) and retry.',
        $id,
        $labels[$source] ?? 'its live choice vocabulary',
        rtrim($detail, characters: '.') . '.',
    ));
}

/**
 * A leaf whose reference default is an int/float must be a finite number;
 * numeric strings are coerced to the matching PHP number type. The coerced
 * number is still checked against any top-level `min`/`max` the option
 * declares — the numerically-constrained control types (ct-slider,
 * ct-number, unit vocabularies) never reach this function (they dispatch
 * to bly_validate_constrained_leaf), so this is the defensive net for
 * other declarations that happen to carry bounds. Out-of-range values are
 * REJECTED with the range in the error, never clamped or persisted.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_validate_numeric_leaf(array $row, mixed $leaf, int|float $reference_default, string $context): mixed
{
    $number = bly_coerce_number($leaf);
    if ($number === null) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": %s must be numeric (default is %s); got %s.',
            $row['id'],
            $context,
            (string) wp_json_encode($reference_default),
            is_scalar($leaf) ? (string) wp_json_encode($leaf) : gettype($leaf),
        ));
    }

    $range_error = bly_check_declared_range(
        $row['id'],
        (float) $number,
        bly_option_range($row['option'], unit_entry: null),
        $context,
        unit: '',
    );
    return $range_error ?? $number;
}

/**
 * A finite numeric scalar (int/float, or a numeric string coerced to the
 * matching PHP number type) — null for everything else, non-finite values
 * included. Numeric strings parse through FLOAT first: an (int) cast on
 * "1e309" silently yields a garbage 0 that would then pass any
 * non-negative range check, so scientific notation, overflow and
 * INF/NAN forms all surface (and reject) here. Plain integer notation
 * within the int range keeps its int type.
 */
function bly_coerce_number(mixed $leaf): int|float|null
{
    if (is_int($leaf)) {
        return $leaf;
    }
    if (is_float($leaf)) {
        return is_finite($leaf) ? $leaf : null;
    }
    if (!is_string($leaf) || !is_numeric($leaf)) {
        return null;
    }
    $float = (float) $leaf;
    if (!is_finite($float)) {
        return null;
    }
    if (preg_match('/^\s*-?\d+\s*$/', $leaf) === 1 && $float >= (float) PHP_INT_MIN && $float <= (float) PHP_INT_MAX) {
        return (int) $leaf;
    }
    return $float;
}

/**
 * A schema `min`/`max` bound as a float, or null when the schema declares
 * none (absent or non-numeric — an absent bound constrains nothing).
 */
function bly_declared_bound(mixed $bound): ?float
{
    if (!is_numeric($bound)) {
        return null;
    }
    return (float) $bound;
}

/**
 * The numeric range constraining a leaf: the matching per-unit `units`
 * entry wins, falling back to the option's top-level `min`/`max`.
 *
 * @param array<array-key, mixed> $option
 * @param array<array-key, mixed>|null $unit_entry
 * @return array{min: null|float, max: null|float}
 */
function bly_option_range(array $option, ?array $unit_entry): array
{
    return [
        'min' => bly_declared_bound($unit_entry['min'] ?? null) ?? bly_declared_bound($option['min'] ?? null),
        'max' => bly_declared_bound($unit_entry['max'] ?? null) ?? bly_declared_bound($option['max'] ?? null),
    ];
}

/** Range-bound label for error messages: integral floats print as ints. */
function bly_format_bound(float $bound): string
{
    return $bound === floor($bound) ? (string) (int) $bound : (string) $bound;
}

/**
 * Reject a number outside the option's declared range — null bounds
 * constrain nothing. `$unit` labels the range in the error on unit-bearing
 * leaves ('' on plain numbers).
 *
 * @param array{min: null|float, max: null|float} $range
 */
function bly_check_declared_range(string $id, float $number, array $range, string $context, string $unit): ?WP_Error
{
    $too_small = $range['min'] !== null && $number < $range['min'];
    $too_large = $range['max'] !== null && $number > $range['max'];
    if (!$too_small && !$too_large) {
        return null;
    }
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": %s is out of range — the schema allows %s to %s%s. Nothing was written.',
        $id,
        $context,
        $range['min'] !== null ? bly_format_bound($range['min']) : '-∞',
        $range['max'] !== null ? bly_format_bound($range['max']) : '∞',
        $unit === '' ? '' : ' (' . $unit . ')',
    ));
}

/**
 * Whether string leaves on this option carry CSS length units: every
 * ct-slider, plus any option declaring a `units` vocabulary or a
 * `defaultUnit`. Such leaves are interpolated into generated CSS and must
 * satisfy the strict value+unit grammar (bly_validate_unit_leaf) instead
 * of the permissive string-kind check.
 *
 * @param array<array-key, mixed> $option
 */
function bly_option_is_unit_bearing(array $option): bool
{
    if (($option['type'] ?? null) === 'ct-slider') {
        return true;
    }
    if (isset($option['units']) && is_array($option['units']) && $option['units'] !== []) {
        return true;
    }
    return isset($option['defaultUnit']) && is_string($option['defaultUnit']);
}

/**
 * Strict value+unit grammar for a non-numeric string leaf on a
 * unit-bearing option: the ENTIRE string must be one number immediately
 * followed by one unit from the option's declared vocabulary (its `units`
 * entries plus `defaultUnit`), and the number must sit inside the range
 * that unit declares (per-unit `min`/`max`, falling back to the option's
 * top-level bounds). Slider strings flow verbatim into the dynamic-CSS
 * input, and Blocksy's native slider check is only an intval() probe —
 * "9999remgarbage; }body{display:none}" passes it — so anything outside
 * this grammar is rejected outright; no CSS-injectable content may ever
 * persist.
 *
 * @param array<array-key, mixed> $option
 * @return string|WP_Error
 */
function bly_validate_unit_leaf(string $id, string $leaf, array $option, string $context): string|WP_Error
{
    $units = bly_declared_units($option);

    $token = bly_parse_unit_token($leaf);
    if ($token === null || !isset($units[$token['unit']])) {
        return bly_unit_grammar_error($id, $leaf, $units, $context);
    }

    $range = bly_option_range($option, unit_entry: $units[$token['unit']]);
    $range_error = bly_check_declared_range(
        $id,
        bly_declared_bound($token['number']) ?? 0.0,
        $range,
        $context,
        $token['unit'],
    );
    return $range_error ?? $token['number'] . $token['unit'];
}

/**
 * WP_Error for a string leaf that failed the value+unit grammar, listing
 * the option's declared unit vocabulary (or the plain-number contract when
 * it declares none).
 *
 * @param array<string, array<array-key, mixed>> $units
 */
function bly_unit_grammar_error(string $id, string $leaf, array $units, string $context): WP_Error
{
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": %s must be a number%s (e.g. "20px") or a plain number; got %s.',
        $id,
        $context,
        $units === []
            ? ' — but this option declares no units, send a plain number instead'
            : ' immediately followed by one of: ' . implode(', ', array_keys($units)),
        (string) wp_json_encode($leaf),
    ));
}

/**
 * The unit vocabulary an option declares, as {unit => entry}: every
 * `units` entry with a string `unit` key (Blocksy resolves
 * blocksy_units_config() before the schema reaches us, so entries carry
 * their own min/max), plus a bare entry for `defaultUnit` when it is not
 * already covered (its bounds then come from the option's top level).
 *
 * @param array<array-key, mixed> $option
 * @return array<string, array<array-key, mixed>>
 */
function bly_declared_units(array $option): array
{
    $out = [];
    /** @var mixed $units_raw */
    $units_raw = $option['units'] ?? null;
    /** @var mixed $entry */
    foreach (is_array($units_raw) ? $units_raw : [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        /** @var mixed $unit_name */
        $unit_name = $entry['unit'] ?? null;
        if (!is_string($unit_name) || $unit_name === '') {
            continue;
        }
        $out[strtolower($unit_name)] = $entry;
    }
    /** @var mixed $default_unit */
    $default_unit = $option['defaultUnit'] ?? null;
    if (is_string($default_unit) && $default_unit !== '' && !isset($out[strtolower($default_unit)])) {
        $out[strtolower($default_unit)] = ['unit' => $default_unit];
    }
    return $out;
}

/**
 * Parse one clean number+unit token ("16px", "-0.5em", "100%") into its
 * parts (unit lowercased), or null when the string is anything else —
 * including a token followed by MORE content, which is exactly the shape
 * a declaration-escaping injection takes.
 *
 * @return array{number: string, unit: string}|null
 */
function bly_parse_unit_token(string $value): ?array
{
    $m = null;
    if (preg_match('/^\s*(?<number>-?(?:\d+(?:\.\d+)?|\.\d+))(?<unit>[a-z%]+)\s*$/i', $value, $m) !== 1) {
        return null;
    }
    return ['number' => $m['number'], 'unit' => strtolower($m['unit'])];
}

/** Syntactically-known CSS units, for grammar checks with no declared vocabulary. */
function bly_known_css_unit(string $unit): bool
{
    return in_array(
        $unit,
        [
            'px',
            'em',
            'rem',
            '%',
            'vw',
            'vh',
            'vmin',
            'vmax',
            'pt',
            'pc',
            'ch',
            'ex',
            'cm',
            'mm',
            'in',
            'fr',
            's',
            'ms',
            'deg',
            'rad',
            'grad',
            'turn',
        ],
        strict: true,
    );
}

/**
 * Grammar check for a non-numeric string leaf whose REFERENCE default is
 * itself a number+unit token ('16px'): the candidate must be exactly one
 * number plus one syntactically-known CSS unit (the reference's own unit
 * always counts). Bounds are not determinable at this depth — the nested
 * control's schema is not resolvable from the parent definition — but the
 * grammar alone blocks every declaration-escaping string.
 *
 * @return string|WP_Error
 */
function bly_validate_reference_unit_leaf(string $id, string $leaf, string $reference, string $context): string|WP_Error
{
    $reference_token = bly_parse_unit_token($reference);
    $token = bly_parse_unit_token($leaf);
    if (
        $token !== null
        && (
            bly_known_css_unit($token['unit'])
            || $reference_token !== null
            && $token['unit'] === $reference_token['unit']
        )
    ) {
        return $token['number'] . $token['unit'];
    }
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": %s must be a number followed by a CSS unit, like its default %s; got %s.',
        $id,
        $context,
        (string) wp_json_encode($reference),
        (string) wp_json_encode($leaf),
    ));
}

/**
 * Whether a reference-less/nested string leaf could escape a CSS
 * declaration: any `{`, `}` or `;` anywhere in the string is rejected —
 * no attempt to parse CSS. `calc(1px); }body{display:none}` and
 * `var(--x); }body{display:none}` fail on the raw characters alone, and
 * a numeric-prefix precondition (the previous heuristic) is exactly the
 * kind of shape check such payloads route around. Verified against every
 * string leaf in every array-shaped default of the real Blocksy 2.1.52
 * schema: zero legitimate occurrences of these characters, so there is
 * nothing to whitelist. Residual, documented tradeoff: hand-written
 * builder HTML using entity notation (`&amp;` and friends carry `;`)
 * would be rejected at gate 1 in these unreferenced regions — write such
 * text with literal characters instead; the theme stores them verbatim.
 */
function bly_string_leaf_injects_css(string $leaf): bool
{
    return preg_match('/[{};]/', $leaf) === 1;
}

/**
 * Kind check against a bool/string reference default: bool defaults demand
 * booleans, string defaults accept string/number scalars (never booleans).
 * A null or absent reference constrains nothing.
 *
 * @return mixed
 */
function bly_validate_kindred_leaf(string $id, mixed $leaf, mixed $reference_default, string $context): mixed
{
    if (is_bool($reference_default) && !is_bool($leaf)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": %s must be a boolean (like its default).',
            $id,
            $context,
        ));
    }

    if (is_string($reference_default) && !is_string($leaf) && !is_int($leaf) && !is_float($leaf)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": %s must be a string (like its default); got %s.',
            $id,
            $context,
            gettype($leaf),
        ));
    }

    return $leaf;
}

/**
 * Structured (non-responsive) candidate against a structured default:
 * list-shaped defaults demand list-shaped values, map-shaped defaults
 * demand a map sharing at least one key with the default's structure, and
 * every overlapping key must be kind-compatible with the default's leaf
 * (recursively for nested arrays). Keys beyond the default's structure are
 * allowed — stored Blocksy values legitimately grow bookkeeping keys — and
 * missing keys are allowed, matching partial structures the theme
 * tolerates.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $value
 * @param array<array-key, mixed> $default
 * @return array<array-key, mixed>|WP_Error
 */
function bly_validate_structured(array $row, array $value, array $default, string $path = ''): array|WP_Error
{
    if ($value === []) {
        return $value;
    }
    if ($default === []) {
        // No usable reference — the injection backstop still walks every
        // leaf; an empty default is not an exemption.
        $error = bly_walk_reference_less($row, $value, $path);
        return $error ?? $value;
    }
    $at = $path === '' ? '' : sprintf(' at `%s`', $path);

    $default_is_list = bly_array_is_list($default);
    if ($default_is_list !== bly_array_is_list($value)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s"%s: expected a %s (like its default), got a %s.',
            $row['id'],
            $at,
            $default_is_list ? 'list' : 'keyed object',
            $default_is_list ? 'keyed object' : 'list',
        ));
    }
    if ($default_is_list) {
        // List items of non-layer controls (sortable rows) carry no
        // item-level schema in the definition — ct-layers lists never get
        // here (bly_validate_layers_value owns them) — but every nested
        // string leaf still gets the CSS-injection backstop.
        $error = bly_walk_reference_less($row, $value, $path);
        return $error ?? $value;
    }

    $overlap = array_intersect_key($value, $default);
    if ($overlap === []) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s"%s: the value shares no keys with the option\'s structure. Expected keys like: %s. Read the current shape with blocksy-get-option first.',
            $row['id'],
            $at,
            implode(', ', array_slice(array_map('strval', array_keys($default)), offset: 0, length: 12)),
        ));
    }

    $error = bly_validate_structured_keys($row, $overlap, $default, $path);
    // Keys beyond the default's structure are tolerated (stored values
    // grow bookkeeping keys) but not exempt from the injection backstop.
    $error ??= bly_walk_reference_less($row, $value, $path);
    return $error ?? $value;
}

/**
 * Reference-less recursive sweep for regions the definition cannot type
 * (dynamic list items, keys beyond the default's structure): arrays are
 * walked all the way down and every string leaf must clear
 * bly_string_leaf_injects_css() — gate 1 refuses declaration-escaping
 * strings at ANY depth, not only on schema-mapped leaves.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $value
 */
function bly_walk_reference_less(array $row, array $value, string $path): ?WP_Error
{
    /** @var mixed $item */
    foreach ($value as $key => $item) {
        $key_path = $path === '' ? (string) $key : $path . '.' . (string) $key;
        if (is_array($item)) {
            $nested = bly_walk_reference_less($row, $item, $key_path);
            if ($nested !== null) {
                return $nested;
            }
            continue;
        }
        if (is_string($item) && bly_string_leaf_injects_css($item)) {
            return bly_nested_injection_error($row['id'], $key_path);
        }
    }
    return null;
}

/**
 * Per-key pass behind bly_validate_structured: every overlapping key must
 * be kind-compatible with the default's leaf, recursing into nested keyed
 * structures. Nested DEVICE maps are validated leaf by leaf against the
 * default's matching device leaf — ranges are not determinable at this
 * depth, but the unit grammar derived from the reference ('16px' pins a
 * candidate to number+unit) blocks every declaration-escaping string.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $overlap
 * @param array<array-key, mixed> $default
 */
function bly_validate_structured_keys(array $row, array $overlap, array $default, string $path): ?WP_Error
{
    /** @var mixed $candidate */
    foreach ($overlap as $key => $candidate) {
        /** @var mixed $reference */
        $reference = $default[$key];
        $key_path = $path === '' ? (string) $key : $path . '.' . (string) $key;

        if (is_array($reference) && is_array($candidate)) {
            if (bly_is_device_map($reference)) {
                $device_error = bly_validate_nested_device_map($row, $candidate, $reference, $key_path);
                if ($device_error !== null) {
                    return $device_error;
                }
                continue;
            }
            $nested = bly_validate_structured($row, $candidate, $reference, $key_path);
            if ($nested instanceof WP_Error) {
                return $nested;
            }
            continue;
        }

        $scalar_error = bly_check_nested_scalar($row, $candidate, $reference, $key_path);
        if ($scalar_error !== null) {
            return $scalar_error;
        }
    }
    return null;
}

/**
 * A nested {desktop, tablet, mobile} candidate against the default's
 * device map: the candidate must itself be device-shaped, and every
 * present device leaf is checked against the default's matching device
 * leaf (desktop as fallback) — same reference-derived grammar as scalar
 * nested keys.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $candidate
 * @param array<array-key, mixed> $reference
 */
function bly_validate_nested_device_map(array $row, array $candidate, array $reference, string $path): ?WP_Error
{
    if (!bly_is_device_map($candidate)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": key `%s` is a responsive map with keys desktop/tablet/mobile (like its default); got keys: %s.',
            $row['id'],
            $path,
            implode(', ', array_map('strval', array_keys($candidate))),
        ));
    }
    /** @var mixed $leaf */
    foreach ($candidate as $device => $leaf) {
        /** @var mixed $reference_leaf */
        $reference_leaf = $reference[$device] ?? $reference['desktop'] ?? null;
        $leaf_error = bly_check_nested_scalar($row, $leaf, $reference_leaf, $path . '.' . (string) $device);
        if ($leaf_error !== null) {
            return $leaf_error;
        }
    }
    return null;
}

/**
 * One nested scalar leaf against its reference leaf: numeric references
 * demand finite numbers, unit-shaped string references ('16px') demand a
 * grammar-clean number+unit candidate (bare numbers tolerated — Blocksy
 * stores both), plain references fall back to the kind check — and every
 * string candidate additionally clears the CSS-injection backstop.
 *
 * Array candidates against a SCALAR reference are a shape mismatch and
 * are rejected — never walked through — with one sanctioned exception:
 * a {desktop, tablet, mobile} device map is the responsive expansion
 * Blocksy itself stores for scalar-defaulted sub-values (a typography
 * size defaulting to '16px' is stored per-device once customized), so a
 * device-map candidate is validated leaf by leaf against the scalar
 * reference instead. Only a null/absent reference (no schema knowledge)
 * falls back to the reference-less injection walk.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_check_nested_scalar(array $row, mixed $candidate, mixed $reference, string $path): ?WP_Error
{
    if (is_array($candidate)) {
        return bly_check_nested_array_candidate($row, $candidate, $reference, $path);
    }

    if (is_string($candidate)) {
        if (!is_numeric($candidate) && is_string($reference) && bly_parse_unit_token($reference) !== null) {
            $unit_checked = bly_validate_reference_unit_leaf(
                $row['id'],
                $candidate,
                $reference,
                sprintf('key `%s`', $path),
            );
            return $unit_checked instanceof WP_Error ? $unit_checked : null;
        }
        if (bly_string_leaf_injects_css($candidate)) {
            return bly_nested_injection_error($row['id'], $path);
        }
    }

    if (!bly_kind_compatible($candidate, $reference)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": key `%s` must be %s (like its default); got %s.',
            $row['id'],
            $path,
            bly_kind_label($reference),
            gettype($candidate),
        ));
    }
    return null;
}

/**
 * Array candidate where the reference leaf is NOT an array — see the
 * shape rules on bly_check_nested_scalar().
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $candidate
 */
function bly_check_nested_array_candidate(array $row, array $candidate, mixed $reference, string $path): ?WP_Error
{
    if ($reference === null) {
        return bly_walk_reference_less($row, $candidate, $path);
    }
    if (bly_is_device_map($candidate)) {
        /** @var mixed $leaf */
        foreach ($candidate as $device => $leaf) {
            // The sanctioned responsive expansion holds SCALAR device
            // values only — an array/object here would recurse back into
            // this exception (size.desktop.mobile) and re-open the walk.
            if (is_array($leaf) || is_object($leaf)) {
                return bly_error('bly_invalid_input', sprintf(
                    'Option "%s": key `%s.%s` must be a scalar (%s, like its default) — device values of a responsive map cannot nest further maps.',
                    $row['id'],
                    $path,
                    (string) $device,
                    bly_kind_label($reference),
                ));
            }
            $leaf_error = bly_check_nested_scalar($row, $leaf, $reference, $path . '.' . (string) $device);
            if ($leaf_error !== null) {
                return $leaf_error;
            }
        }
        return null;
    }
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": key `%s` must be %s (like its default) or a responsive {desktop, tablet, mobile} map of such values; got an object/array.',
        $row['id'],
        $path,
        bly_kind_label($reference),
    ));
}

/** WP_Error for a nested string leaf refused by the injection backstop. */
function bly_nested_injection_error(string $id, string $path): WP_Error
{
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": key `%s` contains `{`, `}` or `;` — no value in this region legitimately carries CSS-structural characters, so a string that could escape its CSS declaration is refused.',
        $id,
        $path,
    ));
}

/**
 * Kind compatibility between a candidate leaf and the default's leaf:
 * arrays match arrays, booleans match booleans, numbers match FINITE
 * numbers or finitely-coercible numeric strings (bly_coerce_number — the
 * same finite-only gate as top level, so "1e309" fails at any depth),
 * strings match string/number scalars, and a null reference constrains
 * nothing.
 */
function bly_kind_compatible(mixed $candidate, mixed $reference): bool
{
    if ($reference === null) {
        return true;
    }
    if (is_array($reference)) {
        return is_array($candidate);
    }
    if (is_bool($reference)) {
        return is_bool($candidate);
    }
    if (is_int($reference) || is_float($reference)) {
        return bly_coerce_number($candidate) !== null;
    }
    return is_string($candidate) || is_int($candidate) || is_float($candidate);
}

/** Human label for the expected kind in bly_validate_structured errors. */
function bly_kind_label(mixed $reference): string
{
    if (is_array($reference)) {
        return 'an object/array';
    }
    if (is_bool($reference)) {
        return 'a boolean';
    }
    if (is_int($reference) || is_float($reference)) {
        return 'a number';
    }
    return 'a string';
}

/**
 * `array_is_list` exists only on PHP 8.1+; the plugin floor is 8.0
 * (`Requires PHP: 8.0`), so the check is spelled out.
 *
 * @param array<array-key, mixed> $value
 */
function bly_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

// ---------------------------------------------------------------------------
// Value validation — ct-layers (schema-driven, recursive) + ct-visibility
// ---------------------------------------------------------------------------

/** Maximum nesting of ct-layers inside ct-layers settings (meta_elements inside custom_meta inside hero_elements = 2). */
const BLY_MAX_LAYER_DEPTH = 3;

/** Layer-row keys every ct-layers control owns regardless of its declaration. */
const BLY_LAYER_OWN_KEYS = ['id', 'enabled', '__id'];

/**
 * Per-layer settings Blocksy parses into HTML attribute NAME=value pairs
 * and emits with the NAME unescaped: `custom_attribute` on the hero
 * `custom_title` layer (inc/options/general/page-title.php:457 →
 * inc/components/hero/elements.php:216-239 explode(' ') / explode('=')
 * → blocksy_html_tag → blocksy_attr_to_html, inc/helpers/html.php:42-53:
 * `$attr_name . '="' . esc_attr($attr_val) . '"'`), and the Companion's
 * `custom_attributes` (content-block wrapper, framework/premium/features/
 * content-blocks.php:702-717 via blocksy_companion_parse_attributes_string)
 * should a ct-layers declaration ever carry it. Validated by
 * bly_validate_custom_attribute_value — the generic text gate is NOT
 * enough there (it refuses `{};` only; `onmouseover=alert(1)` clears it).
 */
const BLY_LAYER_ATTRIBUTE_SETTINGS = ['custom_attribute', 'custom_attributes'];

/**
 * Per-layer settings whose value lands in an `href`: the account
 * dropdown's `custom_link.link` / `account_user_info_custom_link`
 * (Companion framework/features/header/items/account/views/login.php:
 * 315-345, 427-450, 782-790 — blocksy_html_tag, i.e. esc_attr ONLY, no
 * esc_url: `javascript:` survives), the contacts `link` (contacts-box.php:
 * 138 esc_url) and the socials `custom_url` (social-box.php:1303 esc_url).
 * Validated by bly_validate_layer_url_value on top of the typed gate.
 */
const BLY_LAYER_URL_SETTINGS = ['link', 'custom_url', 'account_user_info_custom_link'];

/** Attribute names a custom-attribute setting may carry (exact names). */
const BLY_CUSTOM_ATTRIBUTE_SAFE_NAMES = ['id', 'class', 'title', 'role', 'lang', 'dir'];

/** Attribute-name prefixes a custom-attribute setting may carry (`data-*`, `aria-*`). */
const BLY_CUSTOM_ATTRIBUTE_SAFE_PREFIXES = ['data-', 'aria-'];

/** The placeholder Blocksy substitutes in custom-attribute VALUES (elements.php:234). */
const BLY_CUSTOM_ATTRIBUTE_TITLE_PLACEHOLDER = '{title}';

/**
 * `ct-layers` (hero elements, archive card elements, share networks,
 * product layouts, post-meta elements …): a LIST of layer rows
 * `{id, enabled, __id?, …per-layer settings}`. Blocksy's own customizer
 * sanitizer has NO branch for this control type (inc/customizer/
 * validator.php:91 — blocksy_validate_for returns the input as is) and the
 * per-post sanitizer only strips `<`/`>`; the nested settings are
 * rendered straight into markup and CSS by the theme (`heading_tag` →
 * blocksy_html_tag() with no allowlist, inc/components/hero/elements.php:242;
 * `__id` → a `[data-field*="…"]` CSS selector, inc/dynamic-styles/
 * page-title/page-title.php:569), so the declaration is enforced HERE,
 * recursively, on both the customizer and the per-post write paths:
 *
 *  - the value is a list of objects; every row carries a string `id`
 *    drawn from the layers the declaration knows — its `settings` keys
 *    (the per-layer option groups) plus the ids of its default rows —
 *    and a boolean `enabled` (the theme reads `$layer['enabled']`
 *    unguarded, inc/dynamic-styles/page-title/page-title.php:535);
 *    a layer id may repeat only when its settings entry is `clone`-able
 *    (post_meta, divider, content-block — the UI's "+" button), up to the
 *    editor's own cap — (int clone) + 1 rows in total, `clone: true`
 *    = 2 rows (bly_check_layer_repeat);
 *  - `__id` (the UI's clone handle) is an optional `[A-Za-z0-9_-]{1,64}`
 *    token — it lands in a CSS attribute selector;
 *  - every other key must be either a setting the layer DECLARES
 *    (`settings[<id>]['options']`, flattened with the same walker as the
 *    option tree) — validated with the typed gate-1 checks of its control
 *    type (finite choices, ranges/units, device maps, structure vs its
 *    default, ct-switch vocabulary, ct-visibility maps, nested ct-layers
 *    recursively) and then the shape-safe per-control sanitizer
 *    (gate 2) — or a key the layer's DEFAULT row carries (the option
 *    files seed e.g. `title`/`page_meta_elements` on rows whose settings
 *    do not re-declare them), validated by kind against that default
 *    leaf; anything else is an unknown setting and is refused;
 *  - every string leaf at any depth clears the CSS-injection backstop
 *    (bly_string_leaf_injects_css);
 *  - settings the theme feeds into attribute NAMES (BLY_LAYER_ATTRIBUTE_
 *    SETTINGS) are parsed the way the theme parses them and allow only a
 *    documented safe name set (bly_validate_custom_attribute_value);
 *    settings that land in an `href` (BLY_LAYER_URL_SETTINGS) refuse
 *    non-allowlisted URL schemes (bly_validate_layer_url_value).
 *
 * Returns the cleaned list (typed coercions applied, key order as
 * passed) or a WP_Error naming the layer and key — nothing is persisted
 * by callers on error.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return list<array<string, mixed>>|WP_Error
 */
function bly_validate_layers_value(array $row, mixed $value, int $depth = 0): array|WP_Error
{
    if (!is_array($value) || !bly_array_is_list($value)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s" is a ct-layers control and stores a LIST of layer objects ({id, enabled, …}); read the current value first and send the whole list back.',
            $row['id'],
        ));
    }
    /** @var mixed $settings_raw */
    $settings_raw = $row['option']['settings'] ?? null;
    $settings = is_array($settings_raw) ? $settings_raw : [];
    $defaults = bly_layer_defaults_by_id($row['option']['value'] ?? null);
    $known = bly_known_layer_ids($settings, $defaults);

    $out = [];
    $seen = [];
    /** @var mixed $layer */
    foreach ($value as $i => $layer) {
        $i = (int) $i;
        $layer_id = bly_check_layer_identity($row, $i, $layer, $known);
        if ($layer_id instanceof WP_Error) {
            return $layer_id;
        }
        /** @var array<array-key, mixed> $layer */
        /** @var mixed $setting_raw */
        $setting_raw = $settings[$layer_id] ?? null;
        $setting = is_array($setting_raw) ? $setting_raw : [];
        $seen[$layer_id] = ($seen[$layer_id] ?? 0) + 1;
        $repeat = bly_check_layer_repeat($row, $i, $layer_id, $seen[$layer_id], $setting);
        if ($repeat !== null) {
            return $repeat;
        }

        $clean = bly_validate_layer_row(
            $row,
            $i,
            $layer,
            ['setting' => $setting, 'default_row' => $defaults[$layer_id] ?? []],
            $depth,
        );
        if ($clean instanceof WP_Error) {
            return $clean;
        }
        $out[] = $clean;
    }
    return $out;
}

/**
 * A layer row must be an object with a non-empty string `id` among the
 * layers the control knows (when it knows any). Returns the id.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param list<string> $known
 */
function bly_check_layer_identity(array $row, int $index, mixed $layer, array $known): string|WP_Error
{
    if (!is_array($layer) || !is_string($layer['id'] ?? null) || $layer['id'] === '') {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": layer #%d must be an object with a non-empty string `id`.',
            $row['id'],
            $index,
        ));
    }
    $layer_id = $layer['id'];
    if ($known !== [] && !in_array($layer_id, $known, strict: true)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": layer #%d has unknown id "%s"; this control knows the layers: %s.',
            $row['id'],
            $index,
            bly_ext_clean_text($layer_id, BLY_MAX_EXTRA_KEY_BYTES),
            implode(', ', $known),
        ));
    }
    return $layer_id;
}

/**
 * A layer id may repeat only when its settings entry declares `clone`,
 * and only up to the cap the customizer UI itself enforces: the "Clone
 * Item" button is rendered while the rows of that id number fewer than
 * `(parseInt(clone) || 1) + 1` (static/js/options/options/ct-layers/
 * LayerControls.js:94-102 — the only route that adds a SECOND row of an
 * id; the "+" select offers un-added ids only, ct-layers.js:43-46). So
 * `clone: true` allows 2 rows in total (parseInt(true) is NaN → 1),
 * `clone: 5` allows 6 — bly_layer_clone_cap. Rows beyond the cap would
 * never come out of the editor and are refused; rows the editor can
 * author (the 6th of a `clone: 5` id) are accepted.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $setting
 */
function bly_check_layer_repeat(array $row, int $index, string $layer_id, int $occurrence, array $setting): ?WP_Error
{
    if ($occurrence < 2) {
        return null;
    }
    /** @var mixed $clone */
    $clone = $setting['clone'] ?? null;
    if ($clone === null || $clone === false || $clone === 0 || $clone === '' || $clone === '0') {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": layer "%s" appears more than once (layer #%d) but is not a clone-able layer — only layers declared `clone` (the UI\'s "Clone Item" button) may repeat.',
            $row['id'],
            $layer_id,
            $index,
        ));
    }
    $cap = bly_layer_clone_cap($clone);
    if ($occurrence <= $cap) {
        return null;
    }
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": layer "%s" appears %d times (layer #%d) but the editor allows at most %d rows of it (clone: %s).',
        $row['id'],
        $layer_id,
        $occurrence,
        $index,
        $cap,
        is_bool($clone) ? 'true' : (string) (is_scalar($clone) ? $clone : '?'),
    ));
}

/**
 * The editor's total-row cap for a clone-able layer id — the JS
 * expression `(parseInt(clone) || 1) + 1` (LayerControls.js:97) in PHP:
 * an int (or numeric string) clone counts itself, anything parseInt
 * cannot read (true) counts as 1; plus the original row.
 */
function bly_layer_clone_cap(mixed $clone): int
{
    $parsed = match (true) {
        is_int($clone) => $clone,
        is_float($clone) => (int) $clone,
        is_string($clone) => (int) $clone, // (int) "5px" === 5, (int) "abc" === 0 — parseInt's leading-digits read
        default => 0,
    };
    return ($parsed > 0 ? $parsed : 1) + 1;
}

/**
 * The layer ids a ct-layers control knows: its `settings` keys plus the
 * ids of its default rows (in that order, deduplicated).
 *
 * @param array<array-key, mixed> $settings
 * @param array<string, array<array-key, mixed>> $defaults
 * @return list<string>
 */
function bly_known_layer_ids(array $settings, array $defaults): array
{
    $known = [];
    foreach (array_keys($settings) as $key) {
        $known[] = (string) $key;
    }
    foreach (array_keys($defaults) as $key) {
        if (in_array($key, $known, strict: true)) {
            continue;
        }
        $known[] = $key;
    }
    return $known;
}

/**
 * The declaration's default rows indexed by layer id (first row wins on
 * clone-able repeats) — the reference for keys a layer's settings do
 * not re-declare.
 *
 * @return array<string, array<array-key, mixed>>
 */
function bly_layer_defaults_by_id(mixed $default): array
{
    $out = [];
    if (!is_array($default)) {
        return $out;
    }
    /** @var mixed $layer */
    foreach ($default as $layer) {
        if (!is_array($layer) || !is_string($layer['id'] ?? null) || $layer['id'] === '' || isset($out[$layer['id']])) {
            continue;
        }
        $out[$layer['id']] = $layer;
    }
    return $out;
}

/**
 * One layer row against its declaration — see bly_validate_layers_value.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $layer
 * @param array{setting: array<array-key, mixed>, default_row: array<array-key, mixed>} $declaration the `settings[<id>]` entry ({label, options?, clone?, …}) and the declaration's default row for this id ([] when none)
 * @return array<string, mixed>|WP_Error
 */
function bly_validate_layer_row(array $row, int $index, array $layer, array $declaration, int $depth): array|WP_Error
{
    ['setting' => $setting, 'default_row' => $default_row] = $declaration;
    /** @var string $layer_id */
    $layer_id = $layer['id'];
    if (!array_key_exists('enabled', $layer) || !is_bool($layer['enabled'])) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": layer "%s" (#%d) — `enabled` is required and must be a boolean (the theme reads it unguarded).',
            $row['id'],
            $layer_id,
            $index,
        ));
    }
    $declared = [];
    if (isset($setting['options']) && is_array($setting['options'])) {
        bly_walk_options($declared, $setting['options'], []);
    }
    $context = [
        'row' => $row,
        'path' => sprintf('%s[%d:%s]', $row['id'], $index, $layer_id),
        'declared' => $declared,
        'default_row' => $default_row,
        'depth' => $depth,
    ];

    $out = [];
    /** @var mixed $leaf */
    foreach ($layer as $key => $leaf) {
        /** @var mixed $clean */
        $clean = bly_validate_layer_key($context, (string) $key, $leaf);
        if ($clean instanceof WP_Error) {
            return $clean;
        }
        /** @var mixed */
        $out[(string) $key] = $clean;
    }
    return $out;
}

/**
 * One key of a layer row: the control's own keys (`id`, `enabled`,
 * `__id`), a DECLARED setting (typed gates of its control type), a key
 * the default row seeds (kind check against that default), or unknown —
 * refused. Returns the cleaned leaf.
 *
 * @param array{row: array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}, path: string, declared: array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>, default_row: array<array-key, mixed>, depth: int} $context
 * @return mixed
 */
function bly_validate_layer_key(array $context, string $key, mixed $leaf): mixed
{
    $row = $context['row'];
    if ($key === 'id' || $key === 'enabled') {
        return $leaf;
    }
    if ($key === '__id') {
        if (!is_string($leaf) || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $leaf) !== 1) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": layer `%s` — `__id` must be a short [A-Za-z0-9_-] token (the UI\'s clone handle; it becomes a CSS attribute selector).',
                $row['id'],
                $context['path'],
            ));
        }
        return $leaf;
    }
    $declared = isset($context['declared'][$key]);
    if (!$declared && !array_key_exists($key, $context['default_row'])) {
        $allowed = array_values(array_unique(array_merge(
            array_keys($context['declared']),
            array_values(array_diff(array_map('strval', array_keys($context['default_row'])), BLY_LAYER_OWN_KEYS)),
        )));
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": layer `%s` — unknown setting `%s`; this layer accepts: id, enabled%s.',
            $row['id'],
            $context['path'],
            bly_ext_clean_text($key, BLY_MAX_EXTRA_KEY_BYTES),
            $allowed === [] ? '' : ', ' . implode(', ', $allowed),
        ));
    }
    $key_path = $context['path'] . '.' . $key;
    if (in_array($key, BLY_LAYER_ATTRIBUTE_SETTINGS, strict: true)) {
        // Attribute-NAME sink: the dedicated parser replaces the generic
        // text gate (it is strictly tighter, and admits the theme's own
        // `{title}` value placeholder the CSS backstop would refuse).
        return bly_validate_custom_attribute_value($row, $key_path, $leaf);
    }
    /** @var mixed $clean */
    $clean = $leaf;
    if ($declared) {
        $nested = $context['declared'][$key];
        $nested['id'] = $key_path;
        /** @var mixed $clean */
        $clean = bly_validate_nested_option_value($nested, $leaf, $context['depth']);
    }
    if (!$declared) {
        /** @var mixed $clean */
        $clean =
            bly_validate_layer_default_key($row, $context['path'], $key, $leaf, $context['default_row'][$key]) ?? $leaf;
    }
    if ($clean instanceof WP_Error) {
        return $clean;
    }
    return in_array($key, BLY_LAYER_URL_SETTINGS, strict: true)
        ? bly_validate_layer_url_value($row, $key_path, $clean)
        : $clean;
}

/**
 * A custom-attribute layer setting (BLY_LAYER_ATTRIBUTE_SETTINGS), parsed
 * exactly the way the theme parses it before emitting the attribute NAMES
 * unescaped (inc/components/hero/elements.php:222-239): the string is
 * split on single spaces, every token is split on `=` and kept only when
 * that yields exactly two parts; the left part is the attribute name as
 * emitted (`$attr_name . '="' …`, inc/helpers/html.php:49), the right
 * part the value (quotes stripped, `{title}` replaced, then esc_attr'd).
 * The value side is escaped by the theme; the NAME side is not, so the
 * name is what this gate pins down:
 *
 *  - every token must be `name=value` (a token the theme would silently
 *    drop — no `=`, or two — is refused as malformed so the agent learns
 *    what did not apply; runs of spaces are tolerated, the theme skips
 *    the empty tokens they produce);
 *  - the name must match `^[a-z][a-z0-9-]*$` (lowercase, no quotes, no
 *    control characters — `foo"onclick="…` style names are refused by
 *    the charset alone) AND be one of the documented safe set: the exact
 *    names BLY_CUSTOM_ATTRIBUTE_SAFE_NAMES (id, class, title, role, lang,
 *    dir) or the prefixes BLY_CUSTOM_ATTRIBUTE_SAFE_PREFIXES (data-*,
 *    aria-*); everything else — on* handlers, style, srcdoc, href/src/
 *    action/formaction and any other URL-bearing or unknown name — is
 *    refused with a WP_Error that names the offending attribute;
 *  - the value may not carry control characters, quotes, `<`/`>`,
 *    backticks or the CSS-injection characters (`{};`) — except the
 *    literal `{title}` placeholder the theme substitutes.
 *
 * Returns the string unchanged when it is acceptable ('' included).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_validate_custom_attribute_value(array $row, string $path, mixed $value): string|WP_Error
{
    if (!is_string($value)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": `%s` must be a string of space-separated `name=value` attribute pairs; got %s.',
            $row['id'],
            $path,
            gettype($value),
        ));
    }
    if ($value === '') {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return bly_custom_attribute_error($row, $path, detail: 'control characters are not allowed');
    }
    foreach (explode(' ', $value) as $token) {
        if ($token === '') {
            continue; // the theme skips the empty tokens of a run of spaces
        }
        $parts = explode('=', $token);
        if (count($parts) !== 2) {
            return bly_custom_attribute_error(
                $row,
                $path,
                sprintf('token "%s" is not a single `name=value` pair (the theme would silently drop it)', bly_ext_clean_text(
                    $token,
                    BLY_MAX_EXTRA_KEY_BYTES,
                )),
            );
        }
        [$name, $attr_value] = $parts;
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            return bly_custom_attribute_error(
                $row,
                $path,
                sprintf('attribute name "%s" is malformed — names are lowercase [a-z][a-z0-9-]* (no quotes, spaces or punctuation)', bly_ext_clean_text(
                    $name,
                    BLY_MAX_EXTRA_KEY_BYTES,
                )),
            );
        }
        if (!bly_custom_attribute_name_allowed($name)) {
            return bly_custom_attribute_error(
                $row,
                $path,
                sprintf(
                    'attribute "%s" is not allowed (event handlers, style, src/href-bearing and unknown names are refused)',
                    $name,
                ),
            );
        }
        $bare_value = str_replace(BLY_CUSTOM_ATTRIBUTE_TITLE_PLACEHOLDER, replace: '', subject: $attr_value);
        if (preg_match('/["\'<>`{};\\\\]/', $bare_value) === 1) {
            return bly_custom_attribute_error(
                $row,
                $path,
                sprintf(
                    'the value of "%s" may not contain quotes, angle brackets, backticks, backslashes or `{ } ;` (only the literal `{title}` placeholder)',
                    $name,
                ),
            );
        }
    }
    return $value;
}

/** Whether an attribute name is in the documented safe set (exact names + data-/aria- prefixes). */
function bly_custom_attribute_name_allowed(string $name): bool
{
    if (in_array($name, BLY_CUSTOM_ATTRIBUTE_SAFE_NAMES, strict: true)) {
        return true;
    }
    foreach (BLY_CUSTOM_ATTRIBUTE_SAFE_PREFIXES as $prefix) {
        if (strlen($name) > strlen($prefix) && str_starts_with($name, $prefix)) {
            return true;
        }
    }
    return false;
}

/**
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_custom_attribute_error(array $row, string $path, string $detail): WP_Error
{
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": `%s` — %s. The theme parses this setting into HTML attributes (space-separated name=value pairs) and writes the names straight into the page-title tag; accepted names: %s, %s.',
        $row['id'],
        $path,
        $detail,
        implode(', ', BLY_CUSTOM_ATTRIBUTE_SAFE_NAMES),
        implode(', ', array_map(static fn(string $p): string => $p . '*', BLY_CUSTOM_ATTRIBUTE_SAFE_PREFIXES)),
    ));
}

/**
 * A layer setting whose value lands in an `href` (BLY_LAYER_URL_SETTINGS):
 * on top of the typed gate, a string value may not carry control
 * characters and, when it names a scheme, that scheme must be one of
 * wp_allowed_protocols() (http, https, mailto, tel, sms, … — what
 * esc_url() keeps): `javascript:`, `data:`, `vbscript:` and friends are
 * refused — the account dropdown emits these links through
 * blocksy_html_tag (esc_attr only), so esc_url cannot be relied on there.
 * Scheme-less values (paths, anchors, shortcodes) pass; non-strings pass
 * untouched (their own gate decided).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_validate_layer_url_value(array $row, string $path, mixed $value): mixed
{
    if (!is_string($value) || $value === '') {
        return $value;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": `%s` is a link and may not contain control characters.',
            $row['id'],
            $path,
        ));
    }
    $matches = null;
    if (preg_match('/^\s*([a-zA-Z][a-zA-Z0-9+.\-]*):/', $value, $matches) !== 1) {
        return $value;
    }
    $scheme = strtolower($matches[1]);
    /** @var list<string> $allowed */
    $allowed = wp_allowed_protocols();
    if (in_array($scheme, $allowed, strict: true)) {
        return $value;
    }
    return bly_error('bly_invalid_input', sprintf(
        'Option "%s": `%s` uses the URL scheme "%s:", which is not allowed in a link (it is emitted into an href); allowed schemes: %s, or a scheme-less path/anchor.',
        $row['id'],
        $path,
        $scheme,
        implode(', ', $allowed),
    ));
}

/**
 * A layer key the declaration seeds only through its DEFAULT row
 * (`title`, `page_meta_elements`, …): kind-compatible with that default
 * leaf — structured defaults via the structure comparison, scalars via
 * the nested-scalar rule (unit grammar / kind / injection) — and every
 * string leaf clears the injection backstop.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_validate_layer_default_key(array $row, string $path, string $key, mixed $leaf, mixed $reference): ?WP_Error
{
    $key_path = $path . '.' . $key;
    if (is_array($reference)) {
        if (!is_array($leaf)) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": key `%s` must be an object/array (like its default); got %s.',
                $row['id'],
                $key_path,
                gettype($leaf),
            ));
        }
        if (bly_is_device_map($reference)) {
            return bly_validate_nested_device_map($row, $leaf, $reference, $key_path);
        }
        $structured = bly_validate_structured($row, $leaf, $reference, $key_path);
        return $structured instanceof WP_Error ? $structured : null;
    }
    return bly_check_nested_scalar($row, $leaf, $reference, $key_path);
}

/**
 * One DECLARED per-layer setting against its own option declaration:
 * the typed gate-1 check of its control type (the same dispatch the
 * top-level writes use — ct-switch vocabulary, ct-visibility maps,
 * nested ct-layers recursively, everything else through the
 * schema-derived structural/leaf validation with the setting's own
 * default, choices, ranges and units), then the blanket CSS-injection
 * rule on every string leaf, then the shape-safe per-control sanitizer
 * (gate 2, bly_control_sanitizer_applies — fail-closed).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row the nested setting's row, `id` already path-qualified
 * @return mixed
 */
function bly_validate_nested_option_value(array $row, mixed $value, int $depth): mixed
{
    if ($row['type'] === 'ct-layers') {
        if (($depth + 1) >= BLY_MAX_LAYER_DEPTH) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": ct-layers nested deeper than %d levels are not accepted.',
                $row['id'],
                BLY_MAX_LAYER_DEPTH,
            ));
        }
        return bly_validate_layers_value($row, $value, $depth + 1);
    }
    /** @var mixed $validated */
    $validated = match ($row['type']) {
        'ct-switch' => bly_validate_switch_value($row, $value),
        'ct-visibility' => bly_validate_visibility_value($row, $value),
        default => bly_validate_against_schema($row, $value),
    };
    if ($validated instanceof WP_Error) {
        return $validated;
    }
    if (is_string($validated) && bly_string_leaf_injects_css($validated)) {
        return bly_nested_injection_error($row['id'], path: 'value');
    }
    if (is_array($validated)) {
        $injection = bly_walk_reference_less($row, $validated, path: '');
        if ($injection !== null) {
            return $injection;
        }
    }
    if (bly_control_sanitizer_applies($row['type'], $validated)) {
        return bly_apply_blocksy_sanitizer($row, $validated);
    }
    return $validated;
}

/**
 * Whether gate 2 (blocksy_validate_for) is applied to a value of this
 * control type on the per-post write path and on NESTED (layer) settings
 * of both paths — only the control types whose validator branch is
 * shape-safe and meaningful: color pickers (blocksy_validate_color on
 * every state), image uploaders (attachment must be an image),
 * ct-number / ct-slider, and the choice controls (ct-select /
 * ct-image-picker / ct-radio) when the candidate is a scalar — a
 * responsive ct-radio map would be misjudged by the scalar in_array
 * branch; text/textarea are deliberately excluded (the theme's own
 * per-post and layer paths never wp_kses them — gate 1's injection rule
 * and, per-post, gate 3 cover them).
 */
function bly_control_sanitizer_applies(string $type, mixed $validated): bool
{
    if (in_array($type, ['ct-color-picker', 'ct-image-uploader', 'ct-number', 'ct-slider'], strict: true)) {
        return true;
    }
    return in_array($type, ['ct-select', 'ct-image-picker', 'ct-radio'], strict: true) && !is_array($validated);
}

/**
 * `ct-visibility`: a boolean, or a {desktop, tablet, mobile} => bool map
 * whose keys are drawn from the declared choices (desktop required). The
 * option-file default is a bare `false` on some declarations, so the
 * generic device-map rule would refuse the map the UI itself stores.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return bool|array<string, bool>|WP_Error
 */
function bly_validate_visibility_value(array $row, mixed $value): bool|array|WP_Error
{
    if (is_bool($value)) {
        return $value;
    }
    $choices = bly_choice_vocabulary($row) ?? ['desktop', 'tablet', 'mobile'];
    $allowed = array_map('strval', $choices);
    if (!is_array($value) || $value === [] || !array_key_exists('desktop', $value)) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s" is a ct-visibility control: pass a boolean or a {desktop, tablet, mobile} map of booleans (desktop required); allowed keys: %s.',
            $row['id'],
            implode(', ', $allowed),
        ));
    }
    $out = [];
    /** @var mixed $leaf */
    foreach ($value as $device => $leaf) {
        $device = (string) $device;
        if (!in_array($device, $allowed, strict: true) || !is_bool($leaf)) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": key `%s` must be one of %s with a boolean value.',
                $row['id'],
                bly_ext_clean_text($device, BLY_MAX_EXTRA_KEY_BYTES),
                implode(', ', $allowed),
            ));
        }
        $out[$device] = $leaf;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Value validation — gate 2: Blocksy's own per-control sanitizer
// ---------------------------------------------------------------------------

/**
 * Run a candidate value through `blocksy_validate_for()` — the exact
 * per-control sanitizer the customizer installs on every Blocksy setting
 * (inc/customizer/validator.php:91 via blocksy_include_sanitizer(),
 * validator.php:256). Outside a customizer request validator.php is not
 * loaded, so it is required on demand (pure function definitions, all
 * `function_exists`-guarded upstream).
 *
 * The sanitizer's rejection contract is "return the option's default":
 * that is converted into a WP_Error here, because silently persisting the
 * default in place of an invalid request would report success for a write
 * that did not happen. Outputs that differ from the input WITHOUT being
 * the default are Blocksy's own normalization (builder HTML sanitization,
 * wp_kses on text, ct-number clamping — though gate 1 rejects out-of-range
 * numbers before it can trigger) and are adopted. Note the ct-slider
 * branch normalizes NOTHING: it is only an intval() probe with no range
 * check, which is exactly why gate 1 enforces slider ranges and the
 * value+unit grammar itself.
 *
 * This gate FAILS CLOSED: gate 1 cannot type every region (list items and
 * beyond-default keys only get the CSS-injection backstop, nested leaves
 * carry no resolvable bounds), so a write that never went through the
 * theme's own sanitizer must not reach the persist. When
 * validator.php is unreadable, loading it does not define the function, or
 * the call throws, `bly_sanitizer_unavailable` is returned and nothing is
 * written.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_apply_blocksy_sanitizer(array $row, mixed $value): mixed
{
    if (!function_exists('blocksy_validate_for')) {
        $validator = get_template_directory() . '/inc/customizer/validator.php';
        if (is_readable($validator)) {
            try {
                require_once $validator;
            } catch (\Throwable $e) {
                // Third-party exception text stays server-side, matching
                // the other sanitizer/sync failure paths.
                error_log(sprintf(
                    'novamira-pro blocksy: loading inc/customizer/validator.php threw for option "%s": %s',
                    $row['id'],
                    $e->getMessage(),
                ));
                return bly_sanitizer_error(
                    $row['id'],
                    detail: 'loading inc/customizer/validator.php failed — details in the server error log',
                );
            }
        }
    }
    if (!function_exists('blocksy_validate_for')) {
        return bly_sanitizer_error(
            $row['id'],
            detail: 'inc/customizer/validator.php is missing, unreadable, or does not define blocksy_validate_for()',
        );
    }

    /** @var mixed $default */
    $default = $row['option']['value'] ?? null;
    try {
        /** @var mixed $sanitized */
        $sanitized = blocksy_validate_for($row['option'], $value);
    } catch (\Throwable $e) {
        // Third-party exception text stays server-side: REST error payloads
        // reach agents/clients, and raw messages can leak paths or internals.
        error_log(sprintf(
            'novamira-pro blocksy: blocksy_validate_for() threw for option "%s": %s',
            $row['id'],
            $e->getMessage(),
        ));
        return bly_sanitizer_error(
            $row['id'],
            detail: 'blocksy_validate_for() threw — details in the server error log',
        );
    }

    if ($sanitized !== $value && $sanitized === $default && $value !== $default) {
        return bly_error('bly_invalid_input', sprintf(
            'Option "%s": the value was rejected by Blocksy\'s own %s control sanitizer. Read blocksy-get-option for the expected shape and valid values.',
            $row['id'],
            $row['type'],
        ));
    }

    return $sanitized;
}

/**
 * WP_Error for a write refused because Blocksy's own control sanitizer
 * could not run — the write path never bypasses gate 2.
 */
function bly_sanitizer_error(string $id, string $detail): WP_Error
{
    return bly_error('bly_sanitizer_unavailable', sprintf(
        'Refusing to write option "%s": Blocksy\'s own control sanitizer could not run (%s). Nothing was written — verify the theme installation and retry.',
        $id,
        $detail,
    ));
}

// ---------------------------------------------------------------------------
// Palette color validation
// ---------------------------------------------------------------------------

/**
 * Whether a string is a syntactically valid palette color. Grammar-level
 * whitelist, not a character check: hex (#rgb/#rgba/#rrggbb/#rrggbbaa),
 * rgb()/rgba() and hsl()/hsla() with the correct argument count and
 * numeric/percentage components (comma and space syntax), a strict
 * `var(--token[, fallback])` form whose fallback must itself be a valid
 * color, and the transparent / currentColor keywords. Palette slots feed
 * straight into generated CSS, so anything outside the grammar is
 * rejected.
 */
function bly_valid_palette_color(string $color): bool
{
    $color = trim($color);
    if ($color === '' || strlen($color) > BLY_MAX_COLOR_VALUE_BYTES) {
        return false;
    }
    if (preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color) === 1) {
        return true;
    }
    $matches = null;
    if (preg_match('/^var\(\s*--[a-zA-Z0-9_-]+\s*(?:,\s*(?<fallback>\S.*?)\s*)?\)$/', $color, $matches) === 1) {
        $fallback = $matches['fallback'] ?? '';
        return $fallback === '' || bly_valid_palette_color($fallback);
    }
    if (bly_valid_functional_color($color)) {
        return true;
    }
    return in_array(strtolower($color), ['transparent', 'currentcolor'], strict: true);
}

/**
 * Grammar for functional color notations: rgb()/rgba() take exactly three
 * number-or-percentage components plus an optional alpha; hsl()/hsla()
 * take a hue (number with optional deg/grad/rad/turn unit) and two
 * percentages plus an optional alpha. Both classic comma syntax and CSS
 * Color 4 space syntax (alpha after `/`) are accepted. `rgb(,)`,
 * `rgba(1)`, `hsl(deg)` and friends do not parse.
 */
function bly_valid_functional_color(string $color): bool
{
    $num = '(?:\d+(?:\.\d+)?|\.\d+)';
    $pct = $num . '%';
    $np = "(?:{$num}|{$pct})"; // number or percentage
    $hue = "(?:{$num}(?:deg|grad|rad|turn)?)";

    $patterns = [
        "rgba?\\(\\s*{$np}\\s*,\\s*{$np}\\s*,\\s*{$np}\\s*(?:,\\s*{$np}\\s*)?\\)",
        "rgba?\\(\\s*{$np}\\s+{$np}\\s+{$np}\\s*(?:/\\s*{$np}\\s*)?\\)",
        "hsla?\\(\\s*{$hue}\\s*,\\s*{$pct}\\s*,\\s*{$pct}\\s*(?:,\\s*{$np}\\s*)?\\)",
        "hsla?\\(\\s*{$hue}\\s+{$pct}\\s+{$pct}\\s*(?:/\\s*{$np}\\s*)?\\)",
    ];
    foreach ($patterns as $pattern) {
        if (preg_match('#^' . $pattern . '$#i', $color) === 1) {
            return true;
        }
    }
    return false;
}
