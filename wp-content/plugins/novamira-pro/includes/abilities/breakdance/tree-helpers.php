<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Helpers for the Breakdance element tree (`_breakdance_data` postmeta).
 *
 * Tree shape (from Breakdance psalm-types):
 *   Tree = {
 *     root: { id: int, data: { type: 'root', properties: array }, children: TreeNode[] },
 *     _nextNodeId: int,
 *     exportedLookupTable: array<int, TreeNode>,
 *     status?: 'exported',
 *   }
 *   TreeNode = { id: int, data: {type: string, properties: array}, children: TreeNode[], _parentId?: int }
 *
 * The envelope stored in postmeta is `{tree_json_string: string}` where
 * `tree_json_string` is a JSON-encoded `Tree`. We always operate on the
 * decoded tree and re-encode on write.
 */

/**
 * Cast a value that the caller knows is a tree node to the canonical
 * `array<string, mixed>` shape, with a safe fallback. Centralises the
 * narrow-from-mixed dance so call sites stay readable.
 *
 * @return array<string, mixed>
 */
function bd_assert_node(mixed $value): array
{
    if (!is_array($value)) {
        return ['id' => 0, 'data' => ['type' => '', 'properties' => []], 'children' => []];
    }
    /** @var array<string, mixed> $value */
    return $value;
}

/**
 * Return the children list of a node, narrowed to `list<array<string,
 * mixed>>`. Filters out non-array entries so type assumptions hold
 * downstream.
 *
 * @param array<string, mixed> $node
 * @return list<array<string, mixed>>
 */
function bd_node_children(array $node): array
{
    if (!isset($node['children']) || !is_array($node['children'])) {
        return [];
    }
    $children = [];
    /** @var mixed $child */
    foreach ($node['children'] as $child) {
        if (!is_array($child)) {
            continue;
        }
        /** @var array<string, mixed> $child */
        $children[] = $child;
    }
    return $children;
}

/**
 * Default `Tree` for posts that have never been opened in the builder.
 *
 * @return array<string, mixed>
 */
function bd_empty_tree(): array
{
    return [
        'root' => [
            'id' => 1,
            'data' => ['type' => 'root', 'properties' => []],
            'children' => [],
        ],
        '_nextNodeId' => 100,
        'exportedLookupTable' => [],
        'status' => 'exported',
    ];
}

/**
 * Read the element tree for a post. Returns the canonical `Tree` shape,
 * even when the postmeta is missing (caller gets an empty tree).
 *
 * @return array<string, mixed>
 */
function bd_read_tree(int $post_id): array
{
    /** @var mixed $raw */
    $raw = get_post_meta(post_id: $post_id, key: '_breakdance_data', single: true);
    if (!is_string($raw) || $raw === '') {
        return bd_empty_tree();
    }
    /** @var mixed $envelope */
    $envelope = json_decode(json: $raw, associative: true);
    if (!is_array($envelope) || !isset($envelope['tree_json_string'])) {
        return bd_empty_tree();
    }
    /** @var mixed $tree_raw */
    $tree_raw = $envelope['tree_json_string'];
    if (!is_string($tree_raw)) {
        return bd_empty_tree();
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $tree_raw, associative: true);
    if (!is_array($decoded) || !isset($decoded['root'])) {
        return bd_empty_tree();
    }
    // @mago-expect analysis:less-specific-return-statement
    return $decoded;
}

/**
 * Persist the tree back to `_breakdance_data`. Mirrors what Breakdance
 * does on save: outer envelope is `{tree_json_string: <json>}`. We do
 * NOT re-encode the inner string, Breakdance expects the
 * double-encoded shape (string-of-JSON).
 *
 * Returns true on success, WP_Error when `wp_json_encode` fails (the
 * usual cause is exceeding PHP's hard JSON depth limit of 512, left
 * unchecked the cast `(string) false` silently wiped the postmeta to
 * an empty `tree_json_string` and corrupted the canvas) OR when the
 * post has been trashed between the ability entry and the actual write.
 * The trash recheck closes the race window the per-post lock doesn't
 * cover (the lock protects against concurrent writes on the same
 * postmeta, not against a parallel `wp_trash_post`).
 *
 * `$css_warning` receives a non-null message when the post-write CSS
 * regeneration failed; the write itself has already been persisted at
 * that point, so callers must surface it in their `warnings` response
 * instead of failing.
 *
 * @param array<string, mixed> $tree
 */
function bd_write_tree(int $post_id, array $tree, ?string &$css_warning = null): bool|\WP_Error
{
    $css_warning = null;
    $tree = bd_normalize_query_envelopes($tree);
    if (get_post_status($post_id) === 'trash') {
        return bd_error('bd_not_found', sprintf(
            // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
            __('Post %d is in the trash; refusing to write to a trashed canvas.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if (get_post($post_id) === null) {
        return bd_error('bd_not_found', sprintf(
            __('Post %d not found; refusing to write to a missing canvas.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    $inner = wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($inner === false) {
        return bd_error('bd_invalid_input', __(
            'Failed to encode the element tree, most likely due to depth beyond PHP\'s 512-level JSON cap.',
            domain: 'novamira-pro',
        ));
    }
    // Round 11 B4: defend against the encode/decode asymmetry. PHP's
    // json_encode tolerates exactly one more nesting level than
    // json_decode (the json-c implementation tracks depth differently
    // on the two sides). At the boundary — empirically depth ~254
    // — encode succeeds and a valid string lands in postmeta, but the
    // subsequent read can't decode it. bd_read_tree then returns
    // bd_empty_tree(), and the next add-element silently overwrites
    // the supposedly-saved canvas with a fresh single-node tree.
    // Verify the encode is round-trippable before committing the
    // write — refuse anything bd_read_tree would treat as empty.
    if (json_decode(json: $inner, associative: true) === null) {
        return bd_error('bd_invalid_input', __(
            'Encoded tree cannot be decoded back (depth at PHP\'s json_decode cap); refusing the write to avoid a silent canvas wipe on the next read.',
            domain: 'novamira-pro',
        ));
    }
    $outer = wp_json_encode(['tree_json_string' => $inner], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($outer === false) {
        return bd_error('bd_invalid_input', __('Failed to encode the element tree envelope.', domain: 'novamira-pro'));
    }
    update_post_meta(post_id: $post_id, meta_key: '_breakdance_data', meta_value: wp_slash($outer));
    // Wake page-cache plugins (WP Rocket, W3TC, WP Super Cache, LiteSpeed):
    // they listen on `clean_post_cache` to drop the rendered HTML.
    // `update_post_meta` alone fires `updated_post_meta` (used by audit-log
    // plugins) but NOT `save_post` / `clean_post_cache`, so without this
    // call the customer would keep seeing the pre-edit page until the
    // cache TTL or a cron sweep.
    clean_post_cache($post_id);
    $css_warning = bd_regenerate_post_css($post_id);
    return true;
}

/**
 * Regenerate Breakdance's rendered CSS/dependency cache after a tree write.
 *
 * Native builder saves call this entrypoint immediately after persisting the
 * `_breakdance_data` tree. Guard it so the integration remains safe if a
 * future Breakdance release renames the internal cache function.
 *
 * Regeneration runs Breakdance's own server-side render of the whole
 * tree, and some element renderers fatal on a legitimately-added but
 * not-yet-configured element (e.g. `EssentialElements\FormBuilder`
 * before a form is set up reaches `renderForm(null, ...)`). The tree
 * write has already been persisted by the time this runs, so a
 * regeneration failure must degrade to a returned warning — a hard
 * error after the write landed would misreport the mutation as failed
 * and invite a retry that duplicates it, and every later write to the
 * same post would keep crashing on the same node.
 *
 * The warning distinguishes the two failure phases of
 * `generateCacheForPost` so it never mislabels a persistence problem
 * as a render crash: element SSR runs entirely under
 * `Breakdance\Render\getRenderedNodes()` while the CSS file /
 * cache-metadata writes (`writeCssFileFromRules`,
 * `savePostCssCacheToWpDb`, dependency-cache meta) happen after that
 * frame has returned — identical structure in Breakdance 2.7.2, 2.8.1
 * and 3.0.0-beta.2. A Throwable is therefore a render crash iff
 * `getRenderedNodes` is on its stack.
 *
 * @return null|string A warning to surface to the agent, or null when
 *                     regeneration succeeded.
 */
function bd_regenerate_post_css(int $post_id): ?string
{
    if (!function_exists('\\Breakdance\\Render\\generateCacheForPost')) {
        return null;
    }
    try {
        \Breakdance\Render\generateCacheForPost($post_id);
    } catch (\Throwable $e) {
        if (bd_throwable_in_render_phase($e)) {
            return sprintf(
                'Breakdance could not render the saved tree to regenerate its CSS cache (%s). The write was saved, but the page may not render on the frontend until the element that failed (typically one just added and not yet configured, e.g. a Form Builder without a form) is configured via novamira/breakdance-edit-element; the cache regenerates on the next successful write.',
                $e->getMessage(),
            );
        }
        return sprintf(
            'Breakdance rendered the saved tree but failed to persist the regenerated CSS/dependency cache (%s). The write was saved; the frontend may serve stale styles until the cause (commonly filesystem permissions on the uploads directory, or a database write failure) is resolved and a later write or builder save regenerates the cache.',
            $e->getMessage(),
        );
    }
    return null;
}

/**
 * True when a Throwable thrown inside `generateCacheForPost` originated
 * in the element render/SSR phase, i.e. anywhere under
 * `Breakdance\Render\getRenderedNodes()` — including the function's own
 * body, which appears as the innermost trace frame. Failures in the
 * later CSS-file / cache / DB phase never have that frame on the stack.
 */
function bd_throwable_in_render_phase(\Throwable $e): bool
{
    /** @var array<string, mixed> $frame */
    foreach ($e->getTrace() as $frame) {
        if (($frame['class'] ?? '') === '' && ($frame['function'] ?? '') === 'Breakdance\\Render\\getRenderedNodes') {
            return true;
        }
    }
    return false;
}

/**
 * Acquire an exclusive lock around the read-modify-write window on a
 * given post id's `_breakdance_data` blob.
 *
 * Two execution paths:
 *
 *   1. **Persistent object cache present** (Redis / Memcached drop-in).
 *      Uses `wp_cache_add` which is atomic across PHP processes there.
 *      Fastest path (~3 µs per acquire).
 *
 *   2. **Stock WP** (no drop-in). `wp_cache_add` is per-PHP-process and
 *      provides zero cross-process exclusion, which left a measured
 *      ~52% lost-write rate under parallel REST traffic. Falls back to
 *      MySQL's session-scoped `GET_LOCK` (~0.06 ms per acquire), which
 *      is atomic at the DB engine. Each REST request runs in its own
 *      PHP-FPM worker with its own MySQL connection, so the lock is
 *      held for the duration of the request and auto-released when
 *      the connection drops.
 *
 * The lock TOKEN returned on success is opaque; pass it back to
 * `bd_release_tree_lock` so a late release on a different writer's
 * lock is a no-op (token mismatch).
 */
function bd_acquire_tree_lock(int $post_id): string|\WP_Error
{
    return bd_acquire_lock('tree_' . $post_id);
}

/**
 * Acquire an exclusive lock keyed by `$slot`. Generalised version of the
 * per-post tree lock used by every read-modify-write path in the
 * integration (element tree, template settings, variables, global classes,
 * global settings).
 *
 * The slot string is prefixed with the blog id so multisite sub-sites
 * do not collide on the server-scoped MySQL `GET_LOCK` namespace. Uses
 * `wp_cache_add` when a verified-atomic persistent object cache is
 * present, falls back to `GET_LOCK` otherwise.
 */
function bd_acquire_lock(string $slot): string|\WP_Error
{
    $blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $key = 'bd_lock_' . $blog_id . '_' . $slot;
    $token = bd_random_token();

    if (wp_using_ext_object_cache() && bd_cache_add_is_atomic()) {
        $group = 'breakdance_lock';
        $max_attempts = 10;
        $sleep_us = 50_000;
        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            if (wp_cache_add($key, $token, $group, expire: 5)) {
                return 'cache:' . $key . '|' . $token;
            }
            usleep(microseconds: $sleep_us);
        }
        return bd_error('bd_invalid_input', __(
            'Could not acquire lock; another write on the same resource is in progress. Retry the call.',
            domain: 'novamira-pro',
        ));
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $sql = (string) $wpdb->prepare('SELECT GET_LOCK(%s, %d) AS got', $key, 5);
    /** @var mixed $got */
    $got = $wpdb->get_var($sql);
    if ((string) $got === '1') {
        return 'sql:' . $key;
    }
    // On production MySQL/MariaDB GET_LOCK returns exactly '1' (acquired) or
    // '0' (timed out — the lock is genuinely held by another writer). Only '0'
    // is real contention. Any other value means the backend does not implement
    // GET_LOCK as MySQL does — NULL on error, or the literal '1=1' the SQLite
    // drop-in emits in dev/CI — so proceed unlocked, matching the etch/kadence
    // option-lock fallback (acceptable outside production MySQL/MariaDB).
    if ((string) $got === '0') {
        return bd_error('bd_invalid_input', __(
            'Could not acquire lock; another write on the same resource is in progress. Retry the call.',
            domain: 'novamira-pro',
        ));
    }
    return 'none:' . $key;
}

/**
 * Release a generalised lock acquired via `bd_acquire_lock`.
 */
function bd_release_lock(string $token): void
{
    if (str_starts_with($token, 'cache:')) {
        $payload = substr(string: $token, offset: strlen('cache:'));
        $sep = strpos(haystack: $payload, needle: '|');
        if ($sep === false) {
            return;
        }
        $key = substr(string: $payload, offset: 0, length: $sep);
        $cache_token = substr(string: $payload, offset: $sep + 1);
        $group = 'breakdance_lock';
        /** @var mixed $current */
        $current = wp_cache_get($key, $group);
        if (is_string($current) && hash_equals($current, $cache_token)) {
            wp_cache_delete($key, $group);
        }
        return;
    }
    if (str_starts_with($token, 'sql:')) {
        $name = substr(string: $token, offset: strlen('sql:'));
        global $wpdb;
        /** @var \wpdb $wpdb */
        $wpdb->query((string) $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }
}

/**
 * Run a callable under `$slot` lock, releasing on success OR failure.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|\WP_Error
 */
function bd_with_lock(string $slot, callable $fn): mixed
{
    $token = bd_acquire_lock($slot);
    if ($token instanceof \WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        bd_release_lock($token);
    }
}

/**
 * Verify the active object cache drop-in implements `wp_cache_add`
 * with real atomicity (returns false when the key already exists),
 * not as a `wp_cache_set` alias. A buggy drop-in would make the
 * cache fast-path silently lose writes - fall through to GET_LOCK
 * in that case. Cached per-request so the probe runs once.
 */
function bd_cache_add_is_atomic(): bool
{
    /** @var bool|null $verified */
    static $verified = null;
    if ($verified !== null) {
        return $verified;
    }
    $key = 'bd_atomic_probe_' . bin2hex(random_bytes(length: 4));
    $group = 'breakdance_tree';
    $first = wp_cache_add(key: $key, data: 1, group: $group, expire: 5);
    $second = wp_cache_add(key: $key, data: 2, group: $group, expire: 5);
    wp_cache_delete($key, $group);
    return $verified = $first === true && $second === false;
}

/**
 * Release a lock previously acquired via `bd_acquire_tree_lock`.
 * Kept as a thin alias over `bd_release_lock` (the new general primitive)
 * because the token already encodes the lock identity.
 */
function bd_release_tree_lock(int $post_id, string $token): void
{
    // $post_id is preserved for the public signature but unused now;
    // the new token format encodes everything required for release.
    unset($post_id);
    bd_release_lock($token);
}

/**
 * Cryptographically random lock token (64 bits of entropy as 16 hex
 * chars). Replaces the previous `uniqid()` call which was clock-based
 * and not collision-safe under burst load.
 */
function bd_random_token(): string
{
    return bin2hex(random_bytes(length: 8));
}

/**
 * Run a callable under a tree lock, releasing on success OR failure.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|\WP_Error
 */
function bd_with_tree_lock(int $post_id, callable $fn): mixed
{
    $token = bd_acquire_tree_lock($post_id);
    if ($token instanceof \WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        bd_release_tree_lock($post_id, $token);
    }
}

/**
 * Walk the tree depth-first, yielding `[$node, $parent_id]` pairs.
 * Visits `root` first, then every descendant.
 *
 * @param array<string, mixed> $tree
 * @return \Generator<int, array{0: array<string, mixed>, 1: ?int}>
 */
function bd_iter_tree(array $tree): \Generator
{
    $root = bd_assert_node($tree['root'] ?? null);
    yield [$root, null];
    yield from bd_iter_children(bd_node_children($root), (int) ($root['id'] ?? 0));
}

/**
 * @param list<array<string, mixed>> $children
 * @return \Generator<int, array{0: array<string, mixed>, 1: int}>
 */
function bd_iter_children(array $children, int $parent_id): \Generator
{
    foreach ($children as $child) {
        yield [$child, $parent_id];
        $grand = bd_node_children($child);
        if ($grand !== []) {
            yield from bd_iter_children($grand, (int) ($child['id'] ?? 0));
        }
    }
}

/**
 * Locate a node by id. Returns the node *and* the path of parent ids
 * leading to it (useful for diagnostics / moves).
 *
 * @param array<string, mixed> $tree
 * @return array{node: array<string, mixed>, path: list<int>}|null
 */
function bd_find_node(array $tree, int $id): ?array
{
    if (!isset($tree['root'])) {
        return null;
    }
    $root = bd_assert_node($tree['root']);
    if ((int) ($root['id'] ?? 0) === $id) {
        return ['node' => $root, 'path' => []];
    }
    return bd_find_in_children(bd_node_children($root), $id, [(int) ($root['id'] ?? 0)]);
}

/**
 * @param list<array<string, mixed>> $children
 * @param list<int> $path
 * @return array{node: array<string, mixed>, path: list<int>}|null
 */
function bd_find_in_children(array $children, int $id, array $path): ?array
{
    foreach ($children as $child) {
        $child_id = (int) ($child['id'] ?? 0);
        if ($child_id === $id) {
            return ['node' => $child, 'path' => $path];
        }
        $grand = bd_node_children($child);
        if ($grand === []) {
            continue;
        }
        $next_path = $path;
        $next_path[] = $child_id;
        $hit = bd_find_in_children($grand, $id, $next_path);
        if ($hit !== null) {
            return $hit;
        }
    }
    return null;
}

/**
 * Rewrite the children array of a parent node identified by id. Returns
 * true on success, false when the parent is not found.
 *
 * @param array<string, mixed> $tree (by reference)
 * @param list<array<string, mixed>> $new_children
 */
function bd_set_children(array &$tree, int $parent_id, array $new_children): bool
{
    if (!isset($tree['root']) || !is_array($tree['root'])) {
        return false;
    }
    /** @var array<string, mixed> $root */
    $root = $tree['root'];
    if ((int) ($root['id'] ?? 0) === $parent_id) {
        $root['children'] = $new_children;
        $tree['root'] = $root;
        return true;
    }
    $current_children = bd_node_children($root);
    [$ok, $updated] = bd_set_children_in($current_children, $parent_id, $new_children);
    if ($ok) {
        $root['children'] = $updated;
        $tree['root'] = $root;
    }
    return $ok;
}

/**
 * @param list<array<string, mixed>> $children
 * @param list<array<string, mixed>> $new_children
 * @return array{0: bool, 1: list<array<string, mixed>>}
 */
function bd_set_children_in(array $children, int $parent_id, array $new_children): array
{
    foreach ($children as $i => $child) {
        if ((int) ($child['id'] ?? 0) === $parent_id) {
            $child['children'] = $new_children;
            $children[$i] = $child;
            return [true, $children];
        }
        $grand = bd_node_children($child);
        if ($grand === []) {
            continue;
        }
        [$ok, $updated_grand] = bd_set_children_in($grand, $parent_id, $new_children);
        if ($ok) {
            $child['children'] = $updated_grand;
            $children[$i] = $child;
            return [true, $children];
        }
    }
    return [false, $children];
}

/**
 * Allocate a fresh node id from the tree, bumping `_nextNodeId`. The
 * Breakdance builder allocates 100, 101, 102… counters; we preserve
 * that monotonicity. Operates on a reference.
 *
 * @param array<string, mixed> $tree (by reference)
 */
function bd_allocate_id(array &$tree): int
{
    $next = isset($tree['_nextNodeId']) && is_int($tree['_nextNodeId']) ? $tree['_nextNodeId'] : 100;
    $tree['_nextNodeId'] = $next + 1;
    return $next;
}

/**
 * Rebuild the `exportedLookupTable` denormalised id→node map and the
 * `_parentId` annotations Breakdance embeds on each child. Call this
 * after every mutation that changes the tree shape.
 *
 * @param array<string, mixed> $tree (by reference)
 */
function bd_rebuild_lookup(array &$tree): void
{
    /** @var array<int, array<string, mixed>> $table */
    $table = [];
    if (!isset($tree['root']) || !is_array($tree['root'])) {
        $tree['exportedLookupTable'] = $table;
        return;
    }
    /** @var array<string, mixed> $root */
    $root = $tree['root'];
    $root_id = (int) ($root['id'] ?? 0);
    $children = bd_node_children($root);
    $annotated = bd_annotate_parents($children, $root_id);
    $root['children'] = $annotated;
    $tree['root'] = $root;
    bd_collect_lookup($root, $table);
    $tree['exportedLookupTable'] = $table;
    $tree['status'] = 'exported';
}

/**
 * Stamp `_parentId` on every child in the subtree and return the
 * re-annotated list. Pure — does not mutate inputs.
 *
 * @param list<array<string, mixed>> $children
 * @return list<array<string, mixed>>
 */
function bd_annotate_parents(array $children, int $parent_node_id): array
{
    foreach ($children as $i => $child) {
        $child['_parentId'] = $parent_node_id;
        $grand = bd_node_children($child);
        if ($grand !== []) {
            $child['children'] = bd_annotate_parents($grand, (int) ($child['id'] ?? 0));
        }
        $children[$i] = $child;
    }
    return $children;
}

/**
 * @param array<string, mixed> $node
 * @param array<int, array<string, mixed>> $table (by reference)
 */
function bd_collect_lookup(array $node, array &$table): void
{
    $table[(int) ($node['id'] ?? 0)] = $node;
    foreach (bd_node_children($node) as $child) {
        bd_collect_lookup($child, $table);
    }
}

/**
 * Return the `children` array of the parent identified by `$parent_id`,
 * or an empty list when the parent is missing. Saves a `bd_find_node`
 * + several isset checks at every mutation call site.
 *
 * @param array<string, mixed> $tree
 * @return list<array<string, mixed>>
 */
function bd_siblings_of(array $tree, int $parent_id): array
{
    $hit = bd_find_node($tree, $parent_id);
    if ($hit === null) {
        return [];
    }
    return bd_node_children($hit['node']);
}

/**
 * Insert a node into a children list at an optional position. Clamps a
 * negative position to 0 and a position past the end to "append". Pass
 * `null` to append.
 *
 * @param list<array<string, mixed>> $children
 * @param array<string, mixed> $node
 * @return list<array<string, mixed>>
 */
function bd_insert_at(array $children, array $node, ?int $position): array
{
    if ($position === null) {
        $children[] = $node;
        return $children;
    }
    $clamped = $position < 0 ? 0 : $position;
    if ($clamped > count($children)) {
        $clamped = count($children);
    }
    array_splice(array: $children, offset: $clamped, length: 0, replacement: [$node]);
    return $children;
}

/**
 * Compact summary used by get-element-tree at non-zero depth: returns
 * `{id, type, child_count}` and a `children` array when the depth
 * budget allows. `$depth = -1` means walk the entire subtree.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function bd_element_summary(array $node, int $depth = 1): array
{
    /** @var array<string, mixed> $data */
    $data = isset($node['data']) && is_array($node['data']) ? $node['data'] : [];
    $children = bd_node_children($node);
    $row = [
        'id' => (int) ($node['id'] ?? 0),
        'type' => isset($data['type']) && is_string($data['type']) ? $data['type'] : '',
        'child_count' => count($children),
    ];
    if ($depth === 0) {
        return $row;
    }
    $next_depth = $depth === -1 ? -1 : $depth - 1;
    $row['children'] = array_map(static fn(array $c): array => bd_element_summary($c, $next_depth), $children);
    return $row;
}

/**
 * Full element payload — same shape as a TreeNode but with the parent
 * id explicitly surfaced so the agent does not need to re-walk.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function bd_element_to_array(array $node, ?int $parent_id): array
{
    /** @var array<string, mixed> $data */
    $data = isset($node['data']) && is_array($node['data']) ? $node['data'] : [];
    return [
        'id' => (int) $node['id'],
        'parent_id' => $parent_id,
        'type' => isset($data['type']) && is_string($data['type']) ? $data['type'] : '',
        'properties' => isset($data['properties']) ? $data['properties'] : [],
        'children' => isset($node['children']) && is_array($node['children']) ? $node['children'] : [],
        'child_count' => isset($node['children']) && is_array($node['children']) ? count($node['children']) : 0,
    ];
}
