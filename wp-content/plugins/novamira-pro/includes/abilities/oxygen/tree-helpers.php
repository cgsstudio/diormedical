<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Helpers for the Oxygen element tree (`_oxygen_data` postmeta).
 *
 * Tree shape (from Soflyy psalm-types — same as Breakdance because
 * Oxygen 6 is `BREAKDANCE_MODE=oxygen`):
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
 * `array<string, mixed>` shape, with a safe fallback.
 *
 * @return array<string, mixed>
 */
function ox_assert_node(mixed $value): array
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
function ox_node_children(array $node): array
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
function ox_empty_tree(): array
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
 * The mode-aware canvas meta key — `_oxygen_data` in Oxygen mode.
 */
function ox_data_meta_key(): string
{
    return ox_meta_prefix() . 'data';
}

/**
 * Read the element tree for a post via the native mode-aware Data API.
 *
 * Data-loss safety: distinguishes a genuinely ABSENT canvas from a
 * PRESENT-but-unreadable one.
 *  - Postmeta absent / empty → a fresh `ox_empty_tree()` (legitimate new
 *    canvas that mutations may populate).
 *  - Postmeta present but the envelope or the inner tree cannot be decoded
 *    → a `oxygen_corrupt_canvas` WP_Error, so mutations FAIL CLOSED instead
 *    of overwriting the unreadable original with an empty tree.
 *
 * @return array<string, mixed>|\WP_Error
 */
function ox_read_tree(int $post_id): array|\WP_Error
{
    /** @var mixed $raw_meta */
    $raw_meta = get_post_meta(post_id: $post_id, key: ox_data_meta_key(), single: true);
    // Genuinely absent (never opened in the builder) → new empty canvas.
    if ($raw_meta === '' || $raw_meta === false || $raw_meta === null) {
        return ox_empty_tree();
    }
    // Present: it MUST decode. Anything else is a corrupt canvas we refuse
    // to silently clobber.
    if (!function_exists('Breakdance\\Data\\get_meta')) {
        return ox_error('oxygen_corrupt_canvas', sprintf(
            __('Cannot read the Oxygen canvas of post %d: the Data API is unavailable.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    /** @var mixed $inner */
    $inner = \Breakdance\Data\get_meta($post_id, ox_data_meta_key(), array_key: 'tree_json_string');
    // Oxygen's own new-post path persists `tree_json_string => ""` for a
    // legitimately blank document (see the plugin's insert-post endpoint), so
    // an empty string is a valid blank canvas, NOT corruption. Treat it as an
    // empty tree; keep failing closed only when the envelope field is missing
    // / not a string.
    if ($inner === '') {
        return ox_empty_tree();
    }
    if (!is_string($inner)) {
        return ox_error('oxygen_corrupt_canvas', sprintf(
            __(
                'The Oxygen canvas of post %d is present but its data envelope is unreadable; refusing to overwrite it. Inspect the post meta before retrying.',
                domain: 'novamira-pro',
            ),
            $post_id,
        ));
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $inner, associative: true);
    if (!is_array($decoded) || !isset($decoded['root'])) {
        return ox_error('oxygen_corrupt_canvas', sprintf(
            __(
                'The Oxygen canvas of post %d is present but its element tree is malformed; refusing to overwrite it.',
                domain: 'novamira-pro',
            ),
            $post_id,
        ));
    }
    // @mago-expect analysis:less-specific-return-statement
    return $decoded;
}

/**
 * Persist the tree back through the native mode-aware Data API. The
 * envelope is `{tree_json_string: <inner-json>}`; we JSON-encode the tree
 * to the inner string ourselves, then hand the ARRAY envelope to
 * `set_meta`, which json-encodes + slashes it exactly as the Oxygen editor
 * does. (Writing to `update_post_meta` directly, or pre-encoding the
 * envelope, would produce a shape the builder cannot read.)
 *
 * Returns true on success, WP_Error when `wp_json_encode` fails (usual
 * cause: depth beyond PHP's 512-level JSON cap) or when the post has been
 * trashed between the ability entry and the write.
 *
 * @param array<string, mixed> $tree
 */
function ox_write_tree(int $post_id, array $tree): bool|\WP_Error
{
    if (get_post_status($post_id) === 'trash') {
        return ox_error('oxygen_not_found', sprintf(
            __('Post %d is in the trash; refusing to write to a trashed canvas.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if (get_post($post_id) === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Post %d not found; refusing to write to a missing canvas.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if (!function_exists('Breakdance\\Data\\set_meta')) {
        return ox_error('oxygen_invalid_input', __(
            'The Oxygen Data API is unavailable; cannot write the canvas.',
            domain: 'novamira-pro',
        ));
    }
    $inner = wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($inner === false) {
        return ox_error('oxygen_invalid_input', __(
            'Failed to encode the element tree, most likely due to depth beyond PHP\'s 512-level JSON cap.',
            domain: 'novamira-pro',
        ));
    }
    // Round-trip guard: PHP's json_encode tolerates one more nesting level
    // than json_decode. At the boundary (~254 deep) encode succeeds but the
    // subsequent read cannot decode, and the next mutation would wipe the
    // canvas. Refuse anything ox_read_tree could not decode back.
    if (json_decode(json: $inner, associative: true) === null) {
        return ox_error('oxygen_invalid_input', __(
            'Encoded tree cannot be decoded back (depth at PHP\'s json_decode cap); refusing the write to avoid a silent canvas wipe on the next read.',
            domain: 'novamira-pro',
        ));
    }
    /** @var mixed $before */
    $before = \Breakdance\Data\get_meta($post_id, ox_data_meta_key());
    \Breakdance\Data\set_meta($post_id, ox_data_meta_key(), ['tree_json_string' => $inner]);
    // Reproduce Oxygen's native document-save side-effects FIRST, THEN verify.
    // `ox_after_document_save` runs `wp_update_post`, which fires `save_post` /
    // `post_updated` listeners that can still overwrite or revert `_oxygen_data`.
    // Verifying before that would pass while a late hook silently reverts the
    // canvas. So the observed-state read-back must come AFTER the full lifecycle.
    ox_after_document_save($post_id);
    // Observed-state: read the persisted inner string back and require an exact
    // match. Besides a late save_post revert, `update_post_meta` (inside
    // set_meta) can be short-circuited by an `update_post_metadata` filter that
    // leaves the old value in place; without this check every caller would echo
    // its in-memory node as `applied` while the canvas never changed. Because
    // the match is exact, success means the persisted tree is byte-for-byte the
    // intended one — so callers may safely return their in-memory node.
    /** @var mixed $stored */
    $stored = \Breakdance\Data\get_meta($post_id, ox_data_meta_key(), array_key: 'tree_json_string');
    if ($stored !== $inner) {
        $rollback = ox_restore_tree_snapshot($post_id, $before);
        $rollback_message = $rollback['restored']
            ? 'The previous canvas was restored and re-verified.'
            : sprintf(
                'Rollback was not fully applied; retained canvas fingerprint: %s.',
                $rollback['observed_fingerprint'],
            );
        return ox_error(
            'oxygen_write_not_applied',
            sprintf(
                __('The canvas of post %1$d did not persist as requested. %2$s', domain: 'novamira-pro'),
                $post_id,
                $rollback_message,
            ),
            [
                'requested_fingerprint' => ox_state_fingerprint($inner),
                'rollback' => $rollback,
            ],
        );
    }
    return true;
}

/**
 * Restore the raw `_oxygen_data` envelope after an observed-state mismatch and
 * verify the compensating write after Oxygen's full save/cache lifecycle.
 *
 * @return array{restored: bool, observed_fingerprint: string}
 */
function ox_restore_tree_snapshot(int $post_id, mixed $snapshot): array
{
    if ($snapshot === false) {
        delete_post_meta($post_id, ox_data_meta_key());
        ox_after_document_save($post_id);
        // save_post listeners run during the lifecycle and may re-create the
        // failed canvas. Apply the compensating mutation last, then observe it.
        delete_post_meta($post_id, ox_data_meta_key());
        /** @var mixed $observed */
        $observed = \Breakdance\Data\get_meta($post_id, ox_data_meta_key());
        return [
            'restored' => $observed === false,
            'observed_fingerprint' => ox_state_fingerprint($observed),
        ];
    }
    \Breakdance\Data\set_meta($post_id, ox_data_meta_key(), $snapshot);
    ox_after_document_save($post_id);
    \Breakdance\Data\set_meta($post_id, ox_data_meta_key(), $snapshot);
    /** @var mixed $observed */
    $observed = \Breakdance\Data\get_meta($post_id, ox_data_meta_key());
    return [
        'restored' => $observed === $snapshot,
        'observed_fingerprint' => ox_state_fingerprint($observed),
    ];
}

/**
 * Reproduce the side-effects Oxygen's own `save_document()` runs after it
 * persists a canvas, so an agent write behaves like an editor save: bump the
 * post modified date (native revision), regenerate the per-post CSS and
 * dependency caches, and fire the after-save action (whose listener clears
 * page caches). `set_meta` alone does none of these, so the front end would
 * keep rendering stale CSS/scripts. We reproduce the effects via the engine's
 * public functions rather than calling the monolithic `save_document` AJAX
 * handler directly.
 */
function ox_after_document_save(int $post_id): void
{
    // Bump the modified date + trigger the native revision, as save_document does.
    wp_update_post(['ID' => $post_id]);
    if (function_exists('Breakdance\\Render\\generateCacheForPost')) {
        \Breakdance\Render\generateCacheForPost($post_id);
    }
    if (function_exists('bdox_run_action')) {
        // The registered listener calls clean_post_cache($post_id) itself.
        \bdox_run_action('breakdance_after_save_document', $post_id);
    }
    // Idempotent belt-and-braces: page-cache plugins (WP Rocket, W3TC, LiteSpeed)
    // listen on clean_post_cache to drop the rendered HTML.
    clean_post_cache($post_id);
}

/**
 * Acquire an exclusive lock around the read-modify-write window on a
 * given post id's `_oxygen_data` blob.
 *
 * Two execution paths — same strategy as the Breakdance integration
 * because the RMW hazard is identical (Oxygen 6 shares the postmeta
 * shape):
 *
 *   1. **Persistent object cache present** (Redis / Memcached drop-in).
 *      Uses `wp_cache_add` which is atomic across PHP processes there.
 *      Fastest path (~3 µs per acquire).
 *   2. **Stock WP** (no drop-in). `wp_cache_add` is per-PHP-process
 *      only, so it falls back to MySQL's session-scoped `GET_LOCK`
 *      (~0.06 ms per acquire), atomic at the DB engine.
 *
 * The lock TOKEN returned on success is opaque; pass it back to
 * `ox_release_tree_lock` so a late release on a different writer's
 * lock is a no-op (token mismatch).
 */
function ox_acquire_tree_lock(int $post_id): string|\WP_Error
{
    return ox_acquire_lock('tree_' . $post_id);
}

/**
 * Acquire an exclusive lock keyed by `$slot`. Generalised version of
 * the per-post tree lock used by every read-modify-write path in the
 * integration (element tree, template settings, variables, global
 * classes, global settings).
 *
 * The slot string is prefixed with the blog id so multisite sub-sites
 * do not collide on the server-scoped MySQL `GET_LOCK` namespace. Uses
 * `wp_cache_add` when a verified-atomic persistent object cache is
 * present, falls back to `GET_LOCK` otherwise.
 */
function ox_acquire_lock(string $slot): string|\WP_Error
{
    $blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $key = 'ox_lock_' . $blog_id . '_' . $slot;
    $token = ox_random_token();

    if (wp_using_ext_object_cache() && ox_cache_add_is_atomic()) {
        $group = 'oxygen_lock';
        $max_attempts = 10;
        $sleep_us = 50_000;
        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            if (wp_cache_add($key, $token, $group, expire: 5)) {
                return 'cache:' . $key . '|' . $token;
            }
            usleep(microseconds: $sleep_us);
        }
        return ox_error('oxygen_invalid_input', __(
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
    if ((string) $got === '0') {
        return ox_error('oxygen_invalid_input', __(
            'Could not acquire lock; another write on the same resource is in progress. Retry the call.',
            domain: 'novamira-pro',
        ));
    }
    // Non-MySQL backend (NULL on error, or the literal '1=1' the SQLite
    // drop-in emits in dev/CI) — proceed unlocked, matching the etch /
    // kadence option-lock fallback (acceptable outside production
    // MySQL/MariaDB).
    return 'none:' . $key;
}

/**
 * Release a generalised lock acquired via `ox_acquire_lock`.
 */
function ox_release_lock(string $token): void
{
    if (str_starts_with($token, 'cache:')) {
        $payload = substr(string: $token, offset: strlen('cache:'));
        $sep = strpos(haystack: $payload, needle: '|');
        if ($sep === false) {
            return;
        }
        $key = substr(string: $payload, offset: 0, length: $sep);
        $cache_token = substr(string: $payload, offset: $sep + 1);
        $group = 'oxygen_lock';
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
function ox_with_lock(string $slot, callable $fn): mixed
{
    $token = ox_acquire_lock($slot);
    if ($token instanceof \WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        ox_release_lock($token);
    }
}

/**
 * Verify the active object cache drop-in implements `wp_cache_add` with
 * real atomicity (returns false when the key already exists), not as a
 * `wp_cache_set` alias. A buggy drop-in would make the cache fast-path
 * silently lose writes — fall through to GET_LOCK in that case. Cached
 * per-request so the probe runs once.
 */
function ox_cache_add_is_atomic(): bool
{
    /** @var bool|null $verified */
    static $verified = null;
    if ($verified !== null) {
        return $verified;
    }
    $key = 'ox_atomic_probe_' . bin2hex(random_bytes(length: 4));
    $group = 'oxygen_tree';
    $first = wp_cache_add(key: $key, data: 1, group: $group, expire: 5);
    $second = wp_cache_add(key: $key, data: 2, group: $group, expire: 5);
    wp_cache_delete($key, $group);
    return $verified = $first === true && $second === false;
}

/**
 * Release a lock previously acquired via `ox_acquire_tree_lock`. Kept
 * as a thin alias over `ox_release_lock` (the new general primitive)
 * because the token already encodes the lock identity.
 */
function ox_release_tree_lock(int $post_id, string $token): void
{
    // $post_id preserved for the public signature but unused now; the
    // token encodes everything required for release.
    unset($post_id);
    ox_release_lock($token);
}

/**
 * Cryptographically random lock token (64 bits of entropy as 16 hex
 * chars). Replaces the `uniqid()` pattern which was clock-based and
 * not collision-safe under burst load.
 */
function ox_random_token(): string
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
function ox_with_tree_lock(int $post_id, callable $fn): mixed
{
    $token = ox_acquire_tree_lock($post_id);
    if ($token instanceof \WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        ox_release_tree_lock($post_id, $token);
    }
}

/**
 * Walk the tree depth-first, yielding `[$node, $parent_id]` pairs.
 * Visits `root` first, then every descendant.
 *
 * @param array<string, mixed> $tree
 * @return \Generator<int, array{0: array<string, mixed>, 1: ?int}>
 */
function ox_iter_tree(array $tree): \Generator
{
    $root = ox_assert_node($tree['root'] ?? null);
    yield [$root, null];
    yield from ox_iter_children(ox_node_children($root), (int) ($root['id'] ?? 0));
}

/**
 * @param list<array<string, mixed>> $children
 * @return \Generator<int, array{0: array<string, mixed>, 1: int}>
 */
function ox_iter_children(array $children, int $parent_id): \Generator
{
    foreach ($children as $child) {
        yield [$child, $parent_id];
        $grand = ox_node_children($child);
        if ($grand !== []) {
            yield from ox_iter_children($grand, (int) ($child['id'] ?? 0));
        }
    }
}

/**
 * Map every element slug → its engine `nestingRule`, from the builder
 * metadata (`get_elements_for_builder`). Built once per request. The rule is
 * the AUTHORITATIVE "can this element contain children" signal the builder
 * itself uses: `{type: 'container'|'container-link'|'final'|…}`, plus, for
 * loop elements, `inlineEditableBlockPath`. NOTE: `defaultChildren()` is NOT
 * this signal — it returns `false` for containers too (it only means "no
 * default children"), so it cannot distinguish a Container from a leaf.
 *
 * @return array<string, array<string, mixed>>
 */
function ox_element_nesting_rules(): array
{
    /** @var array<string, array<string, mixed>>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $rules = [];
    if (function_exists('Breakdance\\Elements\\get_elements_for_builder')) {
        foreach (\Breakdance\Elements\get_elements_for_builder() as $element) {
            /** @var mixed $slug */
            $slug = $element['slug'] ?? null;
            /** @var mixed $rule */
            $rule = $element['nestingRule'] ?? null;
            if (!is_string($slug) || !is_array($rule)) {
                continue;
            }
            $normalized = [];
            /** @var mixed $value */
            foreach ($rule as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $normalized[$key] = $value;
            }
            $rules[$slug] = $normalized;
        }
    }
    return $cache = $rules;
}

/**
 * Whether an element type may contain tree children. The engine marks leaf
 * AND loop elements with `nestingRule.type === 'final'`: they render no child
 * slot (a loop renders its `content.repeated_block.global_block` block per
 * query row instead), so tree children under them are silently ignored by
 * Oxygen. The root always accepts children; an unknown type is permitted
 * (its slug is validated elsewhere) so we never wrongly block a valid write.
 */
function ox_element_accepts_children(string $type): bool
{
    if ($type === '' || $type === 'root') {
        return true;
    }
    $rule = ox_element_nesting_rules()[$type] ?? null;
    if ($rule === null) {
        return true;
    }
    $rule_type = isset($rule['type']) && is_string($rule['type']) ? $rule['type'] : '';
    // The engine marks EVERY leaf variant with a `final*` nesting type — verified
    // live on Oxygen 6: `final` (Text, Image, PhpCode, the loops, …),
    // `final-link` (TextLink), `final-that-is-allowed-inside-root` (Component,
    // TemplateContentArea). All render no child slot, so children under them
    // persist but never render. `container` / `container-link` accept children;
    // an unknown type is permitted so a valid write is never wrongly blocked.
    return !str_starts_with($rule_type, 'final');
}

/**
 * Whether an element is a query LOOP whose repeated item is a Global Block
 * referenced at `content.repeated_block.global_block` (PostsLoop /
 * DynamicDataLoop / TermLoopBuilder), rather than tree children.
 */
function ox_element_is_loop(string $type): bool
{
    $rule = ox_element_nesting_rules()[$type] ?? null;
    return $rule !== null && ($rule['inlineEditableBlockPath'] ?? null) === 'content.repeated_block.global_block';
}

/**
 * Validate that an element `properties` object only uses Oxygen's real
 * top-level control tabs. Every Oxygen element exposes controls SOLELY through
 * `contentControls` / `designControls` / `settingsControls`, and the renderer
 * reads persisted values ONLY under `content`, `design`, `settings`. A
 * top-level key outside that set (e.g. passing `text` instead of the real
 * `content.content.text`) is silently ignored by the renderer, so the write
 * would report success while the value never takes effect. We reject it at the
 * write boundary with the correct-path hint. Returns an error string, or null
 * when the roots are clean.
 *
 * @param array<array-key, mixed> $properties
 */
function ox_invalid_property_roots(array $properties): ?string
{
    $allowed = ['content' => true, 'design' => true, 'settings' => true, 'meta' => true];
    $bad = [];
    foreach (array_keys($properties) as $key) {
        if (isset($allowed[$key])) {
            continue;
        }
        $bad[] = (string) $key;
    }
    if ($bad === []) {
        return null;
    }
    return sprintf(
        /* translators: %s: comma-separated list of invalid top-level property keys */
        __(
            'Unknown top-level property key(s): %s. Oxygen element properties nest only under `content`, `design`, `settings`, or native binding metadata under `meta`. Use oxygen-get-element-schema to find the exact path.',
            domain: 'novamira-pro',
        ),
        implode(', ', $bad),
    );
}

/**
 * Back-compat alias for per-element custom CSS. Earlier revisions of this
 * integration documented the key as `settings.advanced.custom_css`, but the
 * engine's universal CSS template consumes ONLY `settings.advanced.css`
 * (`plugin/elements/universal-controls/advanced.php:160` appends
 * `{{ settings.advanced.css }}`, and the control registered for the
 * `advanced` section is `responsiveControl('css', …)` from
 * `plugin/elements/preset-sections/custom_css.php:13-16`) — so a
 * `custom_css` write persisted successfully and silently never rendered.
 * Normalise the legacy alias to the real key at the write boundary; an
 * explicit `css` value wins when both are present (the alias is then
 * dropped rather than clobbering the canonical key).
 *
 * @param array<array-key, mixed> $properties
 * @return array<array-key, mixed>
 */
function ox_normalize_advanced_css(array $properties): array
{
    if (!isset($properties['settings']) || !is_array($properties['settings'])) {
        return $properties;
    }
    $settings = $properties['settings'];
    if (!isset($settings['advanced']) || !is_array($settings['advanced'])) {
        return $properties;
    }
    $advanced = $settings['advanced'];
    if (!array_key_exists('custom_css', $advanced)) {
        return $properties;
    }
    if (!array_key_exists('css', $advanced)) {
        /** @var mixed $legacy */
        $legacy = $advanced['custom_css'];
        $advanced['css'] = $legacy;
    }
    unset($advanced['custom_css']);
    $settings['advanced'] = $advanced;
    $properties['settings'] = $settings;
    return $properties;
}

/**
 * Whether a class/selector name carries characters unsafe for an emitted CSS
 * class token. Native Oxygen element bindings store selector ids, while this
 * guard validates names accepted by create-global-class.
 */
function ox_class_name_has_unsafe_chars(string $class): bool
{
    return preg_match('/["\'<>`\s\x00-\x1f]/', $class) === 1;
}

/**
 * Validate native Oxygen selector ids at `meta.classes`. Every id must resolve
 * against `oxy_selectors_json_string`; otherwise the renderer silently ignores
 * it. Also reject the legacy Breakdance `settings.advanced.classes` path with a
 * precise migration hint instead of persisting a successful no-op.
 *
 * @param array<array-key, mixed> $properties
 */
function ox_invalid_class_list(array $properties): ?string
{
    $settings = isset($properties['settings']) && is_array($properties['settings']) ? $properties['settings'] : [];
    $advanced = isset($settings['advanced']) && is_array($settings['advanced']) ? $settings['advanced'] : [];
    if (array_key_exists('classes', $advanced)) {
        return __(
            '`settings.advanced.classes` is a Breakdance binding path that Oxygen mode removes. Use oxygen-apply-global-class; native Oxygen stores stable selector ids at `meta.classes`.',
            domain: 'novamira-pro',
        );
    }
    foreach (ox_element_class_list($properties) as $selector_id) {
        $hit = ox_find_global_class_by_id(ox_read_global_classes(), $selector_id);
        if ($hit === null) {
            return sprintf(
                __(
                    'Selector id `%s` is not registered in Oxygen. Discover ids with oxygen-list-global-classes.',
                    domain: 'novamira-pro',
                ),
                $selector_id,
            );
        }
        if (($hit[1]['type'] ?? null) !== 'class') {
            return sprintf(
                __(
                    'Selector id `%s` is a custom CSS selector, not an attachable class. Only type `class` ids belong in `meta.classes`.',
                    domain: 'novamira-pro',
                ),
                $selector_id,
            );
        }
    }
    return null;
}

/**
 * Full property validation for a tree write: roots, native selector ids, then
 * every submitted branch against the target element's LIVE control-tree leaves.
 * A control leaf is treated as atomic, so compound values and responsive maps
 * remain valid; only invented paths are rejected. `patch` mode additionally
 * permits a top-level null because edit-element documents it as an unset.
 *
 * @param array<array-key, mixed> $properties
 */
function ox_invalid_properties(array $properties, ?string $type = null, string $mode = 'replace'): ?string
{
    $shallow = ox_invalid_property_roots($properties) ?? ox_invalid_class_list($properties);
    if ($shallow !== null || $type === null) {
        return $shallow;
    }
    $schema = ox_schema_index($type, tab_filter: null);
    $paths = array_keys($schema['leaves']);
    /** @var mixed $value */
    foreach ($properties as $key => $value) {
        if (!is_string($key)) {
            return __('Element property keys must be strings.', domain: 'novamira-pro');
        }
        if ($mode === 'patch' && $value === null) {
            continue;
        }
        $error = ox_invalid_element_property_branch($value, $key, $paths);
        if ($error !== null) {
            return $error;
        }
    }
    return null;
}

/**
 * Validate a submitted branch until it reaches an atomic live control leaf.
 *
 * @param list<string> $paths
 */
function ox_invalid_element_property_branch(mixed $value, string $path, array $paths): ?string
{
    if (in_array($path, $paths, strict: true)) {
        return null;
    }
    $prefix = $path . '.';
    $has_children = false;
    foreach ($paths as $allowed) {
        if (!str_starts_with($allowed, $prefix)) {
            continue;
        }
        $has_children = true;
        break;
    }
    if (!$has_children) {
        return sprintf(
            __(
                'Unknown property path `%s` for this Oxygen element. Use oxygen-get-element-schema for its live controls.',
                domain: 'novamira-pro',
            ),
            $path,
        );
    }
    if (!is_array($value) || array_is_list($value) && $value !== []) {
        return sprintf(__('Property group `%s` must be an object.', domain: 'novamira-pro'), $path);
    }
    /** @var mixed $child */
    foreach ($value as $key => $child) {
        if (!is_string($key)) {
            return sprintf(__('Property group `%s` contains a non-string key.', domain: 'novamira-pro'), $path);
        }
        $error = ox_invalid_element_property_branch($child, $prefix . $key, $paths);
        if ($error !== null) {
            return $error;
        }
    }
    return null;
}

/** Whether a single dotted path is an exact leaf for this element type. */
function ox_element_property_path_is_valid(string $type, string $path): bool
{
    $schema = ox_schema_index($type, tab_filter: null);
    return isset($schema['leaves'][$path]);
}

/**
 * Set a value deep inside a `properties` object at a dotted path (e.g.
 * `content.content.text`), creating intermediate objects as needed and
 * preserving every sibling key. Pure — returns a new array. Used by the
 * apply-* abilities to bind a value at one leaf without the caller having
 * to reconstruct the whole tab sub-object (which shallow-merge edit-element
 * would otherwise require).
 *
 * @param array<array-key, mixed> $properties
 * @param list<string>            $segments non-empty path segments
 * @return array<array-key, mixed>
 */
function ox_set_property_at_path(array $properties, array $segments, mixed $value): array
{
    $key = $segments[0];
    if (count($segments) === 1) {
        $properties[$key] = $value;
        return $properties;
    }
    $child = isset($properties[$key]) && is_array($properties[$key]) ? $properties[$key] : [];
    $properties[$key] = ox_set_property_at_path($child, array_slice($segments, offset: 1), $value);
    return $properties;
}

/**
 * Detect whether writing `$value` at `$segments` would have to descend THROUGH
 * an existing non-array (scalar/string) value — which ox_set_property_at_path
 * would silently overwrite, destroying that value. Returns the dotted prefix of
 * the offending scalar, or null when the path is safe to write. The apply-*
 * abilities call this first so a slightly-too-deep path (e.g. binding at
 * `content.content.text.foo` when `content.content.text` is already a string)
 * fails loudly instead of clobbering existing content and reporting success.
 * Only intermediate segments are checked; the final segment is the leaf being
 * (re)written, which may legitimately replace a scalar.
 *
 * @param array<array-key, mixed> $properties
 * @param list<string>            $segments
 */
function ox_property_path_conflict(array $properties, array $segments): ?string
{
    $current = $properties;
    $prefix = [];
    $last = count($segments) - 1;
    for ($i = 0; $i < $last; $i++) {
        $key = $segments[$i];
        $prefix[] = $key;
        if (!array_key_exists($key, $current)) {
            return null;
        }
        /** @var mixed $value */
        $value = $current[$key];
        if (!is_array($value)) {
            return implode('.', $prefix);
        }
        $current = $value;
    }
    return null;
}

/**
 * Replace the entire `properties` object of the element matching
 * `$element_id` in `$siblings`. Returns `[updatedNode|null, newSiblings]`.
 * Distinct from ox_patch_element_in_siblings (shallow merge): the apply-*
 * abilities have already computed the full properties they want to store.
 *
 * @param list<array<string, mixed>> $siblings
 * @param array<array-key, mixed>    $properties
 * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>}
 */
function ox_replace_element_properties(array $siblings, int $element_id, array $properties): array
{
    foreach ($siblings as $i => $sib) {
        if ((int) ($sib['id'] ?? 0) !== $element_id) {
            continue;
        }
        /** @var array<string, mixed> $data */
        $data = isset($sib['data']) && is_array($sib['data']) ? $sib['data'] : ['type' => '', 'properties' => []];
        $data['properties'] = $properties;
        $siblings[$i]['data'] = $data;
        return [$siblings[$i], $siblings];
    }
    return [null, $siblings];
}

/**
 * Persist a rebuilt `properties` object onto the element located by `$hit`
 * (from ox_find_node) and write the tree with the native save lifecycle +
 * observed-state guard. Shared tail of the apply-* abilities, which compute
 * the full properties then hand off here. `$tree` is a local working copy —
 * the write happens inside, so mutating it by value is safe. Returns true on
 * success or a WP_Error.
 *
 * @param array<string, mixed>                                $tree
 * @param array{node: array<string, mixed>, path: list<int>} $hit
 * @param array<array-key, mixed>                             $properties
 */
function ox_persist_element_properties(
    array $tree,
    array $hit,
    int $post_id,
    int $element_id,
    array $properties,
): bool|\WP_Error {
    $parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($parent_id === null) {
        return ox_error('oxygen_invalid_input', __('Cannot bind an element without a parent.', domain: 'novamira-pro'));
    }
    $siblings = ox_siblings_of($tree, $parent_id);
    [$updated, $new_siblings] = ox_replace_element_properties($siblings, $element_id, $properties);
    if ($updated === null) {
        return ox_error('oxygen_not_found', __('Element vanished mid-update.', domain: 'novamira-pro'));
    }
    ox_set_children($tree, $parent_id, $new_siblings);
    ox_rebuild_lookup($tree);
    return ox_write_tree($post_id, $tree);
}

/**
 * Read the native selector-id list an element carries at `meta.classes`.
 *
 * @param array<array-key, mixed> $properties
 * @return list<string>
 */
function ox_element_class_list(array $properties): array
{
    $meta = isset($properties['meta']) && is_array($properties['meta']) ? $properties['meta'] : [];
    $classes = isset($meta['classes']) && is_array($meta['classes']) ? $meta['classes'] : [];
    $out = [];
    /** @var mixed $entry */
    foreach ($classes as $entry) {
        if (!is_string($entry)) {
            continue;
        }
        $out[] = $entry;
    }
    return $out;
}

/**
 * Locate a node by id. Returns the node *and* the path of parent ids
 * leading to it (useful for diagnostics / moves).
 *
 * @param array<string, mixed> $tree
 * @return array{node: array<string, mixed>, path: list<int>}|null
 */
function ox_find_node(array $tree, int $id): ?array
{
    if (!isset($tree['root'])) {
        return null;
    }
    $root = ox_assert_node($tree['root']);
    if ((int) ($root['id'] ?? 0) === $id) {
        return ['node' => $root, 'path' => []];
    }
    return ox_find_in_children(ox_node_children($root), $id, [(int) ($root['id'] ?? 0)]);
}

/**
 * @param list<array<string, mixed>> $children
 * @param list<int> $path
 * @return array{node: array<string, mixed>, path: list<int>}|null
 */
function ox_find_in_children(array $children, int $id, array $path): ?array
{
    foreach ($children as $child) {
        $child_id = (int) ($child['id'] ?? 0);
        if ($child_id === $id) {
            return ['node' => $child, 'path' => $path];
        }
        $grand = ox_node_children($child);
        if ($grand === []) {
            continue;
        }
        $next_path = $path;
        $next_path[] = $child_id;
        $hit = ox_find_in_children($grand, $id, $next_path);
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
function ox_set_children(array &$tree, int $parent_id, array $new_children): bool
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
    $current_children = ox_node_children($root);
    [$ok, $updated] = ox_set_children_in($current_children, $parent_id, $new_children);
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
function ox_set_children_in(array $children, int $parent_id, array $new_children): array
{
    foreach ($children as $i => $child) {
        if ((int) ($child['id'] ?? 0) === $parent_id) {
            $child['children'] = $new_children;
            $children[$i] = $child;
            return [true, $children];
        }
        $grand = ox_node_children($child);
        if ($grand === []) {
            continue;
        }
        [$ok, $updated_grand] = ox_set_children_in($grand, $parent_id, $new_children);
        if ($ok) {
            $child['children'] = $updated_grand;
            $children[$i] = $child;
            return [true, $children];
        }
    }
    return [false, $children];
}

/**
 * Allocate a fresh node id from the tree, bumping `_nextNodeId`. Oxygen
 * (the Soflyy builder) allocates 100, 101, 102… counters; we preserve
 * that monotonicity. Operates on a reference.
 *
 * @param array<string, mixed> $tree (by reference)
 */
function ox_allocate_id(array &$tree): int
{
    $next = isset($tree['_nextNodeId']) && is_int($tree['_nextNodeId']) ? $tree['_nextNodeId'] : 100;
    $tree['_nextNodeId'] = $next + 1;
    return $next;
}

/**
 * Rebuild the `exportedLookupTable` denormalised id→node map and the
 * `_parentId` annotations Oxygen embeds on each child. Call this after
 * every mutation that changes the tree shape.
 *
 * @param array<string, mixed> $tree (by reference)
 */
function ox_rebuild_lookup(array &$tree): void
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
    $children = ox_node_children($root);
    $annotated = ox_annotate_parents($children, $root_id);
    $root['children'] = $annotated;
    $tree['root'] = $root;
    ox_collect_lookup($root, $table);
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
function ox_annotate_parents(array $children, int $parent_node_id): array
{
    foreach ($children as $i => $child) {
        $child['_parentId'] = $parent_node_id;
        $grand = ox_node_children($child);
        if ($grand !== []) {
            $child['children'] = ox_annotate_parents($grand, (int) ($child['id'] ?? 0));
        }
        $children[$i] = $child;
    }
    return $children;
}

/**
 * @param array<string, mixed> $node
 * @param array<int, array<string, mixed>> $table (by reference)
 */
function ox_collect_lookup(array $node, array &$table): void
{
    $table[(int) ($node['id'] ?? 0)] = $node;
    foreach (ox_node_children($node) as $child) {
        ox_collect_lookup($child, $table);
    }
}

/**
 * Return the `children` array of the parent identified by `$parent_id`,
 * or an empty list when the parent is missing. Saves a `ox_find_node`
 * + several isset checks at every mutation call site.
 *
 * @param array<string, mixed> $tree
 * @return list<array<string, mixed>>
 */
function ox_siblings_of(array $tree, int $parent_id): array
{
    $hit = ox_find_node($tree, $parent_id);
    if ($hit === null) {
        return [];
    }
    return ox_node_children($hit['node']);
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
function ox_insert_at(array $children, array $node, ?int $position): array
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
function ox_element_summary(array $node, int $depth = 1): array
{
    /** @var array<string, mixed> $data */
    $data = isset($node['data']) && is_array($node['data']) ? $node['data'] : [];
    $children = ox_node_children($node);
    $row = [
        'id' => (int) ($node['id'] ?? 0),
        'type' => isset($data['type']) && is_string($data['type']) ? $data['type'] : '',
        'child_count' => count($children),
    ];
    if ($depth === 0) {
        return $row;
    }
    $next_depth = $depth === -1 ? -1 : $depth - 1;
    $row['children'] = array_map(static fn(array $c): array => ox_element_summary($c, $next_depth), $children);
    return $row;
}

/**
 * Count the nodes a summary at `$depth` would actually PROJECT (root plus
 * descendants down to the depth limit), NOT the whole canvas. get-element-tree
 * caps on this so a shallow query of a huge page is not refused for the page's
 * total size. `$depth = -1` walks the whole subtree.
 *
 * @param array<string, mixed> $node
 */
function ox_count_projected_nodes(array $node, int $depth): int
{
    $count = 1;
    if ($depth === 0) {
        return $count;
    }
    $next = $depth === -1 ? -1 : $depth - 1;
    foreach (ox_node_children($node) as $child) {
        $count += ox_count_projected_nodes($child, $next);
    }
    return $count;
}

/**
 * Like ox_element_summary but bounded by a shared node budget: it emits the
 * requested-depth projection until `$budget` nodes have been produced, then
 * stops. Every emitted row still carries its `id` and `child_count`, so the
 * caller can re-issue get-element-tree on a child id to continue past the cut
 * — large pages stay navigable instead of collapsing to a bare root. Only
 * called when the projection already exceeds the budget, so the result is
 * always truncated.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function ox_element_summary_capped(array $node, int $depth, int &$budget): array
{
    /** @var array<string, mixed> $data */
    $data = isset($node['data']) && is_array($node['data']) ? $node['data'] : [];
    $children = ox_node_children($node);
    $row = [
        'id' => (int) ($node['id'] ?? 0),
        'type' => isset($data['type']) && is_string($data['type']) ? $data['type'] : '',
        'child_count' => count($children),
    ];
    $budget--;
    if ($depth === 0 || $children === []) {
        return $row;
    }
    $next = $depth === -1 ? -1 : $depth - 1;
    $emitted = [];
    foreach ($children as $child) {
        if ($budget <= 0) {
            break;
        }
        $emitted[] = ox_element_summary_capped($child, $next, $budget);
    }
    $row['children'] = $emitted;
    return $row;
}

/**
 * Element payload for get-element / add / edit / move responses. Returns the
 * element's OWN `properties` in full, but its `children` as immediate-child
 * SUMMARIES (`{id, type, child_count}`), not the raw recursive subtree.
 * Progressive disclosure: a root with 500 children carrying real properties
 * would otherwise return a ~190 KB blob and re-transmit every descendant on
 * every edit/move. The agent recurses by calling get-element on a child id.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function ox_element_to_array(array $node, ?int $parent_id): array
{
    /** @var array<string, mixed> $data */
    $data = isset($node['data']) && is_array($node['data']) ? $node['data'] : [];
    $children = ox_node_children($node);
    return [
        'id' => (int) $node['id'],
        'parent_id' => $parent_id,
        'type' => isset($data['type']) && is_string($data['type']) ? $data['type'] : '',
        'properties' => isset($data['properties']) ? $data['properties'] : [],
        'children' => array_map(static fn(array $child): array => ox_element_summary($child, depth: 0), $children),
        'child_count' => count($children),
    ];
}

/**
 * Total node count in a tree, walked recursively. Used by
 * get-element-tree to decide whether to emit a truncated summary
 * (`max_nodes` cap) or the full tree.
 *
 * @param array<string, mixed> $tree
 */
function ox_count_nodes(array $tree): int
{
    if (!isset($tree['root']) || !is_array($tree['root'])) {
        return 0;
    }
    $count = 1;
    /** @var array<string, mixed> $root */
    $root = $tree['root'];
    foreach (ox_node_children($root) as $child) {
        $count += ox_count_nodes_in_subtree($child);
    }
    return $count;
}

/**
 * @param array<string, mixed> $node
 */
function ox_count_nodes_in_subtree(array $node): int
{
    $count = 1;
    foreach (ox_node_children($node) as $child) {
        $count += ox_count_nodes_in_subtree($child);
    }
    return $count;
}
