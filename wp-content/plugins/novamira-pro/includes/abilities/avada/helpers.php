<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;
use WP_Post;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Uniform error emitters. Every Avada ability returns errors through these
 * helpers so an agent can map a stable slug to a canned response. Never
 * leak internal `sprintf` details in the message — the message reaches the
 * user verbatim through `content[0].text`.
 */

/**
 * The Fusion Core companion plugin is not installed / active OR its
 * version floor is not satisfied. Every Fusion Slider ability
 * (list-sliders, get-slider, list-slides, get-slide, edit-slide,
 * edit-slider-settings) returns this when
 * `\Novamira\Pro\Abilities\Avada\fusion_core_available()` is false. Kept
 * distinct from `av_theme_not_active` so an agent can distinguish
 * "Avada theme absent" (nothing works) from "Avada theme present but
 * Fusion Core missing" (theme-side abilities still work; only Fusion
 * Slider is unavailable).
 */
function av_fusion_core_not_active(): WP_Error
{
    return new WP_Error(
        'avada_fusion_core_not_active',
        __(
            'The Fusion Core companion plugin is not active on this site, or the installed version is below the minimum this integration supports. Fusion Slider abilities are unavailable; theme-side Avada abilities still work.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * A Fusion Slider term (`slide-page` taxonomy) resolves to nothing, or
 * to a term of the wrong taxonomy. Emitted by every ability that takes
 * a `slider` slug or id.
 */
function av_slider_not_found(string|int $ref): WP_Error
{
    return new WP_Error(
        'avada_slider_not_found',
        /* translators: %s: slider slug or numeric id */
        sprintf(__('Avada slider `%s` not found.', domain: 'novamira-pro'), (string) $ref),
        ['status' => 404],
    );
}

/**
 * The Avada theme is not active OR the version floor is not satisfied.
 * Emitted by any read/write path when `avada_theme_available()` is false.
 */
function av_theme_not_active(): WP_Error
{
    return new WP_Error(
        'avada_theme_not_active',
        __(
            'The Avada theme is not active on this site, or the installed version is below the minimum this integration supports.',
            domain: 'novamira-pro',
        ),
        ['status' => 400],
    );
}

/**
 * Generic malformed-input error. Prefer a specific slug when one exists
 * (`avada_unknown_setting`, `avada_post_not_found`) — this is the catch-
 * all for shape errors the schema layer did not reject.
 */
function av_invalid_input(string $reason): WP_Error
{
    return new WP_Error(
        'avada_invalid_input',
        /* translators: %s: reason */
        sprintf(__('Invalid input: %s', domain: 'novamira-pro'), $reason),
        ['status' => 400],
    );
}

/**
 * A caller-supplied setting key is outside the whitelist a write ability
 * accepts. Used by `set-theme-options` when the caller passes a key that
 * is not on the allowlist (or is on the reserved list — `custom_css`,
 * `custom_js`, license fields).
 */
function av_unknown_setting(string $key): WP_Error
{
    return new WP_Error(
        'avada_unknown_setting',
        /* translators: %s: setting key */
        sprintf(__('Unknown or unsupported Avada setting: %s', domain: 'novamira-pro'), $key),
        ['status' => 400],
    );
}

/**
 * A write path failed — the underlying `update_option` returned `false`
 * for a reason distinct from a no-op (see the persist-meta pattern in the
 * Spectra Pro layer for the equivalent read-current + compare trick).
 */
function av_write_failed(string $key): WP_Error
{
    return new WP_Error(
        'avada_write_failed',
        /* translators: %s: setting key */
        sprintf(
            __(
                'Failed to persist Avada setting `%s` (a caching layer or upstream filter may have blocked the write).',
                domain: 'novamira-pro',
            ),
            $key,
        ),
        ['status' => 500],
    );
}

/**
 * A post id does not resolve to a post OR resolves to the wrong post type.
 * Used by portfolio / FAQ reads when the caller passes a stale id.
 */
function av_post_not_found(int $id): WP_Error
{
    return new WP_Error(
        'avada_post_not_found',
        /* translators: %d: post id */
        sprintf(__('Avada post #%d not found or not of an Avada post type.', domain: 'novamira-pro'), $id),
        ['status' => 404],
    );
}

/**
 * Resolve a post id to a `WP_Post` of an accepted post type, or return an
 * `avada_post_not_found` error. Rejects auto-drafts, trashed posts, and
 * revisions per the standard gate every read/write path shares.
 *
 * @param list<string> $accepted_post_types
 */
function av_post_or_error(int $id, array $accepted_post_types): WP_Post|WP_Error
{
    if ($id <= 0) {
        return av_post_not_found($id);
    }
    /** @var WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post) {
        return av_post_not_found($id);
    }
    if (!in_array($post->post_type, $accepted_post_types, strict: true)) {
        return av_post_not_found($id);
    }
    if (in_array($post->post_status, ['auto-draft', 'trash'], strict: true)) {
        return av_post_not_found($id);
    }
    return $post;
}

/**
 * The set of `post_status` values every `list-*` ability accepts. Kept as
 * one canonical list so the schema + the runtime guard + the counter helper
 * never drift. `any` maps to the visible-content statuses WP_Query would
 * normally include (publish + draft + private + pending + future), matching
 * `avada_wp_query_found_posts()` below.
 *
 * @return list<string>
 */
function av_accepted_statuses(): array
{
    return ['any', 'publish', 'draft', 'private', 'pending', 'future'];
}

/**
 * Persist a single scalar / array key inside the `fusion_options` blob.
 * Post-write, reads the row BACK from the DB (via the raw wpdb path, so
 * `pre_option_*` filters that would return the pre-write value do not
 * fool the check) and compares serialised forms — the ability layer must
 * NEVER report `changed: true` when a `pre_update_option_fusion_options`
 * short-circuit has left the DB untouched (adversarial audit A2 F1). The
 * theme-native writer (`Avada()->settings->set()`) is still preferred
 * because it keeps `Fusion_Settings::$saved_options` coherent — the
 * fallback direct `update_option` merge is used only when the theme
 * accessor is missing (smoke-test contexts without the theme booted).
 *
 * On any storage failure — filter veto, exception thrown by Fusion,
 * `update_option` returning false with a divergent read-back — returns
 * `av_write_failed`. Caller flushes cache after a successful null return.
 *
 * Serialise-based dedup at the top: if the incoming value already equals
 * the stored value, the writer skips both branches and returns `null`
 * (no-op is a legitimate success — callers should have already checked
 * `changed` before invoking, but this is defense-in-depth).
 */
function avada_write_fusion_setting(string $key, mixed $value): ?WP_Error
{
    /** @var mixed $before */
    $before = av_raw_fusion_option_key($key);
    if (maybe_serialize($before) === maybe_serialize($value)) {
        return null;
    }

    // Preferred path: theme-native writer keeps in-request Fusion_Settings
    // cache coherent. Wrapped in try/catch because on PHP 8+ any TypeError
    // in Fusion internals (unexpected value shape) propagates out of the
    // call and would kill the ability with an uncaught exception (audit A2
    // F3/F5). Fallback to the direct writer on any throw.
    if (function_exists('Avada') && class_exists('\\Avada')) {
        $avada = \Avada();
        if (property_exists($avada, 'settings') && method_exists($avada->settings, 'set')) {
            try {
                $avada->settings->set($key, $value);
            } catch (\Throwable $e) {
                // Fall through to the direct write. The exception itself
                // is swallowed because the read-back check below is the
                // canonical outcome test — if the DB is still stale, we
                // surface av_write_failed to the caller.
                unset($e);
            }
            // Read-back verify: if a `pre_update_option_*` filter vetoed
            // the write, or a caching plugin short-circuited it, the row
            // is still the pre-write value — surface as `av_write_failed`
            // instead of the caller thinking it succeeded.
            /** @var mixed $after */
            $after = av_raw_fusion_option_key($key);
            if (maybe_serialize($after) !== maybe_serialize($value)) {
                return av_write_failed($key);
            }
            return null;
        }
    }

    // Fallback: direct read-modify-write on the raw option row (used by
    // smoke tests when the theme isn't fully booted). Still guards with
    // a read-back verify for the same reason.
    /** @var mixed $current */
    $current = get_option(AVADA_OPTIONS_KEY, default_value: []);
    if (!is_array($current)) {
        $current = [];
    }
    $current[$key] = $value;
    update_option(AVADA_OPTIONS_KEY, $current);

    /** @var mixed $after */
    $after = av_raw_fusion_option_key($key);
    if (maybe_serialize($after) !== maybe_serialize($value)) {
        return av_write_failed($key);
    }
    return null;
}

/**
 * Read one key out of `fusion_options` via a direct wpdb query, bypassing
 * the `pre_option_*` / `option_*` filter chain. Used by
 * `avada_write_fusion_setting` for the read-back verify — a caching
 * filter returning the pre-write value would otherwise defeat the check.
 * Returns `null` when the row is missing OR the key is absent from the
 * decoded array.
 */
function av_raw_fusion_option_key(string $key): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    /** @var mixed $raw */
    // @mago-expect analysis:possibly-invalid-argument
    $raw = $wpdb->get_var((string) $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
        AVADA_OPTIONS_KEY,
    ));
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    /** @var mixed $decoded */
    $decoded = maybe_unserialize($raw);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded[$key] ?? null;
}

/**
 * Serialise the caller-supplied read-modify-write cycle for a single
 * subkey inside `fusion_options`. The lock is ROW-scoped — not per-
 * subkey — because both write paths (`Avada()->settings->set()` and the
 * fallback `update_option(AVADA_OPTIONS_KEY, ...)`) rewrite the whole
 * option row on commit. A per-subkey lock would still let a concurrent
 * writer on `typography_sets` clobber a mid-flight `color_palette` edit,
 * or vice-versa — first-round review MAJOR-2 (also flagged the original
 * transient CAS as non-atomic: `set_transient` falls back to
 * `update_option` on an existing key, so two writers that raced past the
 * initial `get_transient=false` check would both proceed).
 *
 * The callback receives the freshly-read raw value of `$scope` from
 * `fusion_options` (never the AWB-filtered version, so it can safely
 * write back without re-persisting filter output — audit A2 F3) and
 * MUST return either the mutated value to persist or a `WP_Error` to
 * abort. On abort, no write is performed.
 *
 * For batch writers that touch multiple subkeys inside one critical
 * section (e.g. `avada-set-theme-options`), use
 * {@see av_with_fusion_row_lock()} directly and do the reads/writes
 * inside the callback.
 *
 * @param callable(mixed): (mixed|WP_Error) $mutator
 */
function av_with_fusion_lock(string $scope, callable $mutator): ?WP_Error
{
    return av_with_fusion_row_lock(static function () use ($scope, $mutator): ?WP_Error {
        /** @var mixed $current */
        $current = av_raw_fusion_option_key($scope);
        /** @var mixed $mutated */
        $mutated = $mutator($current);
        if ($mutated instanceof WP_Error) {
            return $mutated;
        }
        return avada_write_fusion_setting($scope, $mutated);
    });
}

/**
 * Run `$fn` while holding a cross-process lock on the `fusion_options`
 * row so every write path Avada exposes (per-subkey set-global-color /
 * set-global-typography AND the batch set-theme-options writer that
 * touches many subkeys in one call) serialises against every other.
 *
 * The lock is a dedicated `wp_options` row acquired by INSERT: the
 * `option_name` UNIQUE index does the mutual exclusion at the DB layer
 * (exactly one concurrent inserter wins; the rest get a duplicate-key
 * failure). A row older than the TTL is stolen (the previous holder
 * likely died mid-request). If the lock stays contended past the wait
 * budget we proceed unlocked rather than fail the write — availability
 * over the small residual race, matching Divi's global-data lock trade.
 *
 * After the lock is taken, `alloptions` and `fusion_options` object-cache
 * entries are dropped so the callback's initial read reflects a prior
 * writer's committed row instead of this request's start-of-request
 * snapshot (else the lock would serialise writes on stale data).
 *
 * Multisite-safe: the lock option name is namespaced with
 * `get_current_blog_id()` so `switch_to_blog()` doesn't share the lock
 * across sites.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function av_with_fusion_row_lock(callable $fn): mixed
{
    $token = av_acquire_fusion_lock();
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: 'alloptions', group: 'options');
        wp_cache_delete(key: AVADA_OPTIONS_KEY, group: 'options');
    }
    try {
        return $fn();
    } finally {
        if ($token !== null) {
            av_release_fusion_lock($token);
        }
    }
}

/** The `wp_options` name backing the `fusion_options` row lock. */
function av_fusion_lock_option(): string
{
    return sprintf('_novamira_avada_lock_%d_fusion_options', get_current_blog_id());
}

/**
 * Spin until the lock row is ours (or the wait budget elapses). Returns
 * the ownership token we wrote (the exact row value) when we hold the
 * lock, or null to mean "proceed unlocked" — in which case the caller
 * must NOT release.
 *
 * The row is created with `$wpdb->insert` (not update_option) so the
 * `option_name` UNIQUE index does the mutual exclusion at the DB layer:
 * exactly one concurrent inserter wins, the rest get a duplicate-key
 * failure. The value is a unique `<uuid>|<timestamp>` token: the uuid
 * identifies THIS acquisition (so release and steal only remove the exact
 * row they own/observed, never a lock a different process has since taken
 * over), and the timestamp drives TTL steal.
 */
function av_acquire_fusion_lock(): ?string
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }
    $option = av_fusion_lock_option();
    $token = av_generate_lock_token();
    $deadline = microtime(as_float: true) + NOVAMIRA_AVADA_LOCK_WAIT;

    do {
        // A duplicate-key insert is the expected contended path — don't log it.
        $prior = $wpdb->suppress_errors(suppress: true);
        $inserted = $wpdb->insert($wpdb->options, [
            'option_name' => $option,
            'option_value' => $token,
            'autoload' => 'no',
        ]);
        $wpdb->suppress_errors(suppress: $prior);

        if ($inserted) {
            // $wpdb->insert bypasses the object cache; drop any stale/notoptions
            // entry so a same-process get_option (steal) sees the real row.
            av_flush_fusion_lock_cache();
            return $token;
        }
        av_steal_stale_fusion_lock();
        usleep(microseconds: 50_000);
    } while (microtime(as_float: true) < $deadline);

    return null;
}

/**
 * Clear the row lock when it is older than the TTL (the previous holder
 * died without releasing) so the next acquire attempt can take it.
 * Compare-and-delete on the EXACT value observed: if another waiter has
 * meanwhile stolen it and inserted a fresh token, our delete no-ops
 * instead of removing their live lock.
 */
function av_steal_stale_fusion_lock(): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = av_fusion_lock_option();
    /** @var mixed $held */
    $held = get_option($option);
    if (!is_string($held)) {
        return;
    }
    if ((time() - av_fusion_lock_token_timestamp($held)) <= NOVAMIRA_AVADA_LOCK_TTL) {
        return;
    }
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $held]);
    av_flush_fusion_lock_cache();
}

/** Release a held lock, deleting only the exact row this acquisition wrote. */
function av_release_fusion_lock(string $token): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = av_fusion_lock_option();
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $token]);
    av_flush_fusion_lock_cache();
}

/** Drop the object-cache entry for the lock option after a raw $wpdb write. */
function av_flush_fusion_lock_cache(): void
{
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: av_fusion_lock_option(), group: 'options');
    }
}

/** The `<uuid>|<timestamp>` lock token's timestamp component (0 when malformed). */
function av_fusion_lock_token_timestamp(string $token): int
{
    $pos = strrpos($token, needle: '|');
    return $pos === false ? 0 : (int) substr($token, $pos + 1);
}

/**
 * Mint a fresh `<uuid>|<timestamp>` lock token. The uuid identifies THIS
 * acquisition uniquely so release and steal only ever remove the exact
 * row they own/observed. Uses `wp_generate_uuid4()` (WP core, RFC-4122
 * v4) — same primitive Novamira Pro uses elsewhere for stable ids.
 */
function av_generate_lock_token(): string
{
    return (string) wp_generate_uuid4() . '|' . time();
}

/**
 * Count posts of a given CPT + status that match the same filter chain a
 * `get_posts` call would honour. Uses `WP_Query`'s `found_posts` rather
 * than `wp_count_posts` so multilingual plugins (WPML / Polylang), custom
 * `pre_get_posts` filters, and any `post_status` remap all shape the total
 * identically to the paged result — otherwise `truncated` lies and an
 * agent paginates indefinitely (adversarial audit A1 finding).
 *
 * Defense-in-depth: the caller-supplied `$status` is validated against
 * `av_accepted_statuses()` and unknown values are coerced to `any` so a
 * schema-bypassing invocation can never leak `trash` posts.
 */
function av_query_total(string $post_type, string $status): int
{
    if (!in_array($status, av_accepted_statuses(), strict: true)) {
        $status = 'any';
    }
    $query = new \WP_Query([
        'post_type' => $post_type,
        'post_status' => $status,
        'posts_per_page' => 1,
        'no_found_rows' => false,
        'fields' => 'ids',
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'suppress_filters' => false,
        'ignore_sticky_posts' => true,
    ]);
    return (int) $query->found_posts;
}

/**
 * Resolve a slider reference (slug string OR numeric term id) to a
 * `WP_Term` of taxonomy `slide-page`, or return `avada_slider_not_found`.
 * Callers should always route through this so the resolver enforces the
 * taxonomy invariant once — a numeric ref of a term in a DIFFERENT
 * taxonomy would silently succeed with `get_term` alone.
 */
function av_slider_term_or_error(string|int $ref): WP_Term|WP_Error
{
    if (is_int($ref)) {
        /** @var WP_Term|WP_Error|null $term */
        $term = get_term($ref, AVADA_TAX_SLIDER);
        return $term instanceof WP_Term ? $term : av_slider_not_found($ref);
    }
    if ($ref === '') {
        return av_slider_not_found($ref);
    }
    // For a digit-only string, WP allows numeric term slugs (`'2024'`,
    // `'123'`). Prefer the slug lookup FIRST so a legitimately-slugged
    // slider is reachable via its slug even when a term with the same
    // numeric id happens to exist in the taxonomy (audit A3 F-H). Fall
    // through to id lookup only if no slug match — matches the
    // "slug-is-identity" principle of the ability layer.
    /** @var WP_Term|false $by_slug */
    $by_slug = get_term_by(field: 'slug', value: $ref, taxonomy: AVADA_TAX_SLIDER);
    if ($by_slug instanceof WP_Term) {
        return $by_slug;
    }
    if (ctype_digit($ref)) {
        /** @var WP_Term|WP_Error|null $term */
        $term = get_term((int) $ref, AVADA_TAX_SLIDER);
        if ($term instanceof WP_Term) {
            return $term;
        }
    }
    return av_slider_not_found($ref);
}

/**
 * Read the raw `_fusion` postmeta bag for a slide as an assoc array.
 * Missing / non-array values coerce to `[]` so callers don't have to
 * defensively type-check on every field access.
 *
 * @return array<string, mixed>
 */
function av_get_slide_meta(int $post_id): array
{
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, AVADA_FUSION_META_KEY, single: true);
    if (!is_array($raw)) {
        return [];
    }
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Persist the `_fusion` postmeta bag for a slide, with a read-back verify
 * pass — a `pre_update_post_meta_*` filter (LiteSpeed, some Fusion Core
 * write mirrors) could veto the write while `update_post_meta` still
 * returns true, so we compare the serialised form of the row after
 * commit and surface `avada_write_failed` when the DB diverges. The
 * caller is responsible for the cache flush; this helper stays low-level.
 *
 * Serialised-form dedup at the top: if the incoming bag already equals
 * the stored bag, the writer skips both the update and the verify pass
 * and returns `null` (no-op is a legitimate success).
 *
 * @param array<string, mixed> $data
 */
function av_set_slide_meta(int $post_id, array $data): ?WP_Error
{
    $before = av_get_slide_meta($post_id);
    if (maybe_serialize($before) === maybe_serialize($data)) {
        return null;
    }
    update_post_meta($post_id, AVADA_FUSION_META_KEY, $data);
    // Read-back verify via a raw wpdb query — bypasses the
    // `get_post_metadata` filter chain that caching / security plugins
    // register (audit A3 F-F). A filter returning the requested value
    // as a short-circuit would otherwise fool the verify while the DB
    // is still stale.
    $after_raw = av_raw_fusion_post_meta($post_id);
    if (maybe_serialize($after_raw) !== maybe_serialize($data)) {
        return av_write_failed(AVADA_FUSION_META_KEY);
    }
    return null;
}

/**
 * Read the raw `_fusion` term_meta bag for a slider (`slide-page` term)
 * as an assoc array. Missing / non-array values coerce to `[]`.
 *
 * @return array<string, mixed>
 */
function av_get_slider_settings(int $term_id): array
{
    /** @var mixed $raw */
    $raw = get_term_meta($term_id, AVADA_FUSION_META_KEY, single: true);
    if (!is_array($raw)) {
        return [];
    }
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Persist the `_fusion` term_meta bag for a slider, with a read-back
 * verify pass. Semantics mirror {@see av_set_slide_meta()} — the caller
 * flushes cache; this helper stays low-level and returns `null` on both
 * no-op and successful commit, `avada_write_failed` on divergent DB
 * state after the update.
 *
 * @param array<string, mixed> $data
 */
function av_set_slider_settings(int $term_id, array $data): ?WP_Error
{
    $before = av_get_slider_settings($term_id);
    if (maybe_serialize($before) === maybe_serialize($data)) {
        return null;
    }
    update_term_meta($term_id, AVADA_FUSION_META_KEY, $data);
    // Read-back verify via a raw wpdb query — same reasoning as
    // av_set_slide_meta() above (audit A3 F-F).
    $after_raw = av_raw_fusion_term_meta($term_id);
    if (maybe_serialize($after_raw) !== maybe_serialize($data)) {
        return av_write_failed(AVADA_FUSION_META_KEY);
    }
    return null;
}

/**
 * Filter-immune read of the `_fusion` postmeta bag for a slide. Queries
 * `wp_postmeta` directly and unserialises the raw column value — the
 * `get_post_metadata` filter chain (LiteSpeed, security plugins) can
 * return a filtered value as a short-circuit that would defeat a
 * post-write verify. Used by `av_set_slide_meta` for the read-back;
 * ability read paths still go through `av_get_slide_meta` because they
 * want the filtered view an agent would see.
 *
 * @return array<string, mixed>
 */
function av_raw_fusion_post_meta(int $post_id): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    /** @var mixed $raw */
    // @mago-expect analysis:possibly-invalid-argument
    $raw = $wpdb->get_var((string) $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
        $post_id,
        AVADA_FUSION_META_KEY,
    ));
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = maybe_unserialize($raw);
    if (!is_array($decoded)) {
        return [];
    }
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Filter-immune read of the `_fusion` term_meta bag for a slider. Same
 * shape and rationale as {@see av_raw_fusion_post_meta()}.
 *
 * @return array<string, mixed>
 */
function av_raw_fusion_term_meta(int $term_id): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    /** @var mixed $raw */
    // @mago-expect analysis:possibly-invalid-argument
    $raw = $wpdb->get_var((string) $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s LIMIT 1",
        $term_id,
        AVADA_FUSION_META_KEY,
    ));
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = maybe_unserialize($raw);
    if (!is_array($decoded)) {
        return [];
    }
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * The `wp_options` name backing a per-term slider-settings lock row. One
 * lock row per term, multisite-scoped by blog id — same pattern as
 * `av_fusion_lock_option()` for `fusion_options` writes.
 */
function av_slider_term_lock_option(int $term_id): string
{
    return sprintf('_novamira_avada_lock_%d_slider_term_%d', get_current_blog_id(), $term_id);
}

/**
 * The `wp_options` name backing a per-post slide lock row (audit A3
 * F-A). Slide `_fusion` postmeta writes are read-modify-write on the
 * WHOLE bag — two concurrent callers editing different subkeys of the
 * same slide would clobber each other without this. Multisite-scoped
 * via blog id, mirrors the slider-term lock naming scheme.
 */
function av_slide_post_lock_option(int $post_id): string
{
    return sprintf('_novamira_avada_lock_%d_slide_post_%d', get_current_blog_id(), $post_id);
}

/**
 * Run `$fn` while holding a cross-process lock on the `_fusion`
 * postmeta row of a slide (`slide` post). Serialises the read-modify-
 * write cycle in `edit-slide` so two concurrent callers editing
 * different keys of the same slide don't clobber each other — the
 * exact same pattern the slider-term lock uses for term_meta writes
 * (audit A3 F-A).
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function av_with_slide_post_lock(int $post_id, callable $fn): mixed
{
    $token = av_acquire_slide_post_lock($post_id);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $post_id, group: 'post_meta');
    }
    try {
        return $fn();
    } finally {
        if ($token !== null) {
            av_release_slide_post_lock($post_id, $token);
        }
    }
}

/**
 * Spin until the slide-post lock row is ours (or the wait budget
 * elapses). Mirrors {@see av_acquire_slider_term_lock()} — same
 * availability-over-race trade-off applies.
 */
function av_acquire_slide_post_lock(int $post_id): ?string
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }
    $option = av_slide_post_lock_option($post_id);
    $token = av_generate_lock_token();
    $deadline = microtime(as_float: true) + NOVAMIRA_AVADA_LOCK_WAIT;

    do {
        $prior = $wpdb->suppress_errors(suppress: true);
        $inserted = $wpdb->insert($wpdb->options, [
            'option_name' => $option,
            'option_value' => $token,
            'autoload' => 'no',
        ]);
        $wpdb->suppress_errors(suppress: $prior);

        if ($inserted) {
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete(key: $option, group: 'options');
            }
            return $token;
        }
        av_steal_stale_slide_post_lock($post_id);
        usleep(microseconds: 50_000);
    } while (microtime(as_float: true) < $deadline);

    return null;
}

/**
 * Clear the slide-post lock row when it is older than the TTL (previous
 * holder died without releasing). Compare-and-delete on the exact
 * observed value so a fresh acquisition by another waiter isn't
 * clobbered.
 */
function av_steal_stale_slide_post_lock(int $post_id): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = av_slide_post_lock_option($post_id);
    /** @var mixed $held */
    $held = get_option($option);
    if (!is_string($held)) {
        return;
    }
    if ((time() - av_fusion_lock_token_timestamp($held)) <= NOVAMIRA_AVADA_LOCK_TTL) {
        return;
    }
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $held]);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $option, group: 'options');
    }
}

/** Release a held slide-post lock, deleting only the exact row this acquisition wrote. */
function av_release_slide_post_lock(int $post_id, string $token): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = av_slide_post_lock_option($post_id);
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $token]);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $option, group: 'options');
    }
}

/**
 * Run `$fn` while holding a cross-process lock on the `_fusion` term_meta
 * row of a slider (`slide-page` term). Serialises the read-modify-write
 * cycle in `edit-slider-settings` so two concurrent callers editing
 * different subkeys of the same term don't clobber each other (both
 * writers would `get → merge → set` the ENTIRE bag, last commit wins).
 *
 * Uses the same INSERT-based mutual exclusion pattern as
 * {@see av_with_fusion_row_lock()} (option_name UNIQUE index does the
 * mutex; TTL-based steal for crash recovery; compare-and-delete on
 * release; multisite-safe via blog_id in the lock name).
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function av_with_slide_page_term_lock(int $term_id, callable $fn): mixed
{
    $token = av_acquire_slider_term_lock($term_id);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $term_id, group: 'term_meta');
    }
    try {
        return $fn();
    } finally {
        if ($token !== null) {
            av_release_slider_term_lock($term_id, $token);
        }
    }
}

/**
 * Spin until the slider-term lock row is ours (or the wait budget
 * elapses). Returns the ownership token when we hold the lock, or null
 * to mean "proceed unlocked" (matches the availability-over-race
 * trade-off in the `fusion_options` row lock).
 */
function av_acquire_slider_term_lock(int $term_id): ?string
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }
    $option = av_slider_term_lock_option($term_id);
    $token = av_generate_lock_token();
    $deadline = microtime(as_float: true) + NOVAMIRA_AVADA_LOCK_WAIT;

    do {
        $prior = $wpdb->suppress_errors(suppress: true);
        $inserted = $wpdb->insert($wpdb->options, [
            'option_name' => $option,
            'option_value' => $token,
            'autoload' => 'no',
        ]);
        $wpdb->suppress_errors(suppress: $prior);

        if ($inserted) {
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete(key: $option, group: 'options');
            }
            return $token;
        }
        av_steal_stale_slider_term_lock($term_id);
        usleep(microseconds: 50_000);
    } while (microtime(as_float: true) < $deadline);

    return null;
}

/**
 * Clear the slider-term lock row when it is older than the TTL (the
 * previous holder died without releasing). Compare-and-delete on the
 * exact observed value so a fresh acquisition by another waiter isn't
 * clobbered.
 */
function av_steal_stale_slider_term_lock(int $term_id): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = av_slider_term_lock_option($term_id);
    /** @var mixed $held */
    $held = get_option($option);
    if (!is_string($held)) {
        return;
    }
    if ((time() - av_fusion_lock_token_timestamp($held)) <= NOVAMIRA_AVADA_LOCK_TTL) {
        return;
    }
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $held]);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $option, group: 'options');
    }
}

/**
 * Pre-validate + sanitize a caller-supplied `values` bag against a
 * writable-keys registry (returned by `avada_writable_slide_keys()` or
 * `avada_writable_slider_setting_keys()`). Returns the fully sanitized
 * bag on success, or a `WP_Error` (unknown_setting / invalid_input) on
 * the FIRST failure — batch is all-or-nothing per the audit-A2 pattern
 * used across the theme-side writers. Extracted from both `edit-slide`
 * and `edit-slider-settings` so their execute callbacks stay under the
 * cyclomatic-complexity threshold and no key-validation logic drifts
 * between the two abilities.
 *
 * @param array<array-key, mixed> $values
 * @param array<string, string> $registry
 * @return array<string, mixed>|WP_Error
 */
function avada_prevalidate_bag(array $values, array $registry): array|WP_Error
{
    /** @var array<string, mixed> $clean */
    $clean = [];
    /** @var mixed $raw_value */
    foreach ($values as $key => $raw_value) {
        if (!is_string($key)) {
            return av_invalid_input('values keys must be strings');
        }
        if (!isset($registry[$key])) {
            return av_unknown_setting($key);
        }
        /** @var mixed $sanitized */
        $sanitized = avada_sanitize_slide_value($registry[$key], $raw_value);
        if ($sanitized === null) {
            return av_invalid_input(sprintf('value for `%s` is not a valid %s', $key, $registry[$key]));
        }
        $clean[$key] = $sanitized;
    }
    return $clean;
}

/**
 * Compute the partial-merge diff between `$existing` and `$incoming`.
 * Returns `[merged, written, unchanged]` — `merged` is the full bag
 * after the merge (safe to persist), `written` lists keys whose value
 * changed, `unchanged` lists keys whose value was already the incoming
 * value (idempotent no-ops).
 *
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $incoming
 * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>}
 */
function avada_partial_merge_bag(array $existing, array $incoming): array
{
    $merged = $existing;
    $written = [];
    $unchanged = [];
    /** @var mixed $value */
    foreach ($incoming as $key => $value) {
        /** @var mixed $prev */
        $prev = $merged[$key] ?? null;
        if (maybe_serialize($prev) === maybe_serialize($value)) {
            $unchanged[] = $key;
            continue;
        }
        $merged[$key] = $value;
        $written[] = $key;
    }
    return [$merged, $written, $unchanged];
}

/** Release a held slider-term lock, deleting only the exact row this acquisition wrote. */
function av_release_slider_term_lock(int $term_id, string $token): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = av_slider_term_lock_option($term_id);
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $token]);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $option, group: 'options');
    }
}
