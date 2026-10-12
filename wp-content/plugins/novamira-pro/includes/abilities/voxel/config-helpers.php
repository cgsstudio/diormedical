<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Builder/config helpers for the Voxel post-type + taxonomy config abilities (S4).
 *
 * The voxel:post_types and voxel:taxonomies options are each a single JSON blob
 * holding EVERY post type / taxonomy keyed by slug, read-modified-written whole.
 * Every mutation here runs inside vx_with_lock() — an atomic wp_cache_add fast
 * path with a MySQL GET_LOCK fallback (multisite-keyed), mirroring the
 * breakdance option lock. Two honest limits on what that buys:
 *
 * - The lock serializes THESE abilities' writers only. Voxel itself takes no
 *   lock, so a concurrent write from the Voxel admin can still clobber ours
 *   (and vice versa) — that window cannot be closed from this side.
 * - It serializes at all only when a lock backend exists: vx_acquire_lock()
 *   proceeds UNLOCKED when neither an atomic external cache nor GET_LOCK is
 *   available (e.g. the SQLite dev drop-in), so there the guarantee degrades
 *   to best-effort ordering.
 *
 * For the lock to help, the read inside it must also be cache-proof, and no
 * cached path is: \Voxel\get() statically caches each group per request, and
 * even its $force_get flag only evicts the individual option key — WordPress
 * serves autoloaded options from the request-cached alloptions array first,
 * which that eviction never touches (and \Voxel\set() passes autoload=null,
 * so autoloaded is a perfectly normal state for these blobs). A locked writer
 * reading any of those caches would serialize on the lock and still overwrite
 * the previous writer with its own request-start snapshot. The readers below
 * therefore select the option row straight from the database, decoding it
 * exactly like \Voxel\get() does; writes keep going through \Voxel\set(),
 * which refreshes every cache after writing.
 *
 * These abilities operate on the option config DIRECTLY (not \Voxel\Post_Type,
 * which needs the WP post type registered): a newly created type is not
 * registered until the next request, so config-level access keeps every ability
 * usable immediately. They deliberately do NOT touch search filters or the search
 * index tables — changing filters requires Voxel to drop+recreate the index and
 * re-index every post, which stays in the Voxel admin.
 */

// ---------------------------------------------------------------------------
// Locking (mirrors breakdance bd_with_lock)
// ---------------------------------------------------------------------------

/**
 * Run $fn while holding the named advisory lock. Returns $fn's value, or a
 * WP_Error if the lock could not be acquired.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function vx_with_lock(string $slot, callable $fn): mixed
{
    $token = vx_acquire_lock($slot);
    if ($token instanceof WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        vx_release_lock($token);
    }
}

/**
 * Whether the active object cache implements wp_cache_add atomically (returns
 * false when the key exists) rather than as a wp_cache_set alias. Cached once.
 */
function vx_cache_add_is_atomic(): bool
{
    /** @var bool|null $verified */
    static $verified = null;
    if ($verified !== null) {
        return $verified;
    }
    $key = 'vx_atomic_probe_' . bin2hex(random_bytes(4));
    $group = 'voxel_config_lock';
    $first = wp_cache_add($key, data: 1, group: $group, expire: 5);
    $second = wp_cache_add($key, data: 2, group: $group, expire: 5);
    wp_cache_delete($key, $group);
    return $verified = $first === true && $second === false;
}

/**
 * Acquire the lock. Returns an opaque token string, or a WP_Error on contention.
 *
 * @return string|WP_Error
 */
function vx_acquire_lock(string $slot): string|WP_Error
{
    $blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $key = 'vx_lock_' . $blog_id . '_' . $slot;
    $token = bin2hex(random_bytes(8));

    if (wp_using_ext_object_cache() && vx_cache_add_is_atomic()) {
        $group = 'voxel_config_lock';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (wp_cache_add($key, $token, $group, expire: 5)) {
                return 'cache:' . $key . '|' . $token;
            }
            usleep(50_000);
        }
        return vx_lock_busy();
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $sql = $wpdb->prepare('SELECT GET_LOCK(%s, %d) AS got', $key, 5);
    /** @var mixed $got */
    $got = is_string($sql) ? $wpdb->get_var($sql) : null;
    if ((string) $got === '1') {
        return 'sql:' . $key;
    }
    // Only a literal '0' is real contention on MySQL/MariaDB; any other value
    // means the backend does not implement GET_LOCK (e.g. the SQLite dev drop-in)
    // so we proceed unlocked, matching the etch/kadence option-lock fallback.
    if ((string) $got === '0') {
        return vx_lock_busy();
    }
    return 'none:' . $key;
}

/** Release a lock acquired via vx_acquire_lock. */
function vx_release_lock(string $token): void
{
    if (str_starts_with($token, 'cache:')) {
        $payload = substr($token, strlen('cache:'));
        $sep = strpos($payload, needle: '|');
        if ($sep === false) {
            return;
        }
        $key = substr($payload, offset: 0, length: $sep);
        $cache_token = substr($payload, $sep + 1);
        /** @var mixed $current */
        $current = wp_cache_get($key, group: 'voxel_config_lock');
        if (is_string($current) && hash_equals($current, $cache_token)) {
            wp_cache_delete($key, group: 'voxel_config_lock');
        }
        return;
    }
    if (str_starts_with($token, 'sql:')) {
        $name = substr($token, strlen('sql:'));
        global $wpdb;
        /** @var \wpdb $wpdb */
        $release = $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name);
        if (is_string($release)) {
            $wpdb->query($release);
        }
    }
}

function vx_lock_busy(): WP_Error
{
    return vx_error('voxel_locked', __(
        'Another configuration change is in progress. Retry the call in a moment.',
        domain: 'novamira-pro',
    ));
}

// ---------------------------------------------------------------------------
// Config accessors
// ---------------------------------------------------------------------------

/**
 * Read one voxel:<group> option blob straight from the options table,
 * bypassing every cache layer (Voxel's per-request static cache, the
 * per-option object cache, AND the alloptions array none of the eviction
 * paths touch — see the locking note in the file header). The value is
 * decoded exactly the way \Voxel\get() decodes it (app-utils.php): JSON with
 * ARRAY_A, a decode error reads as an empty config, and the result is cast
 * to array. A missing row reads as [] too, matching get_option's '' default
 * through the same decode.
 *
 * @return array<array-key, mixed>
 */
function vx_read_config_blob_from_db(string $group): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analyzer:possibly-invalid-argument -- table name from $wpdb->options, placeholder-safe
    $sql = $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
        'voxel:' . $group,
    );
    /** @var mixed $raw */
    $raw = is_string($sql) ? $wpdb->get_var($sql) : null;
    if (!is_string($raw)) {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    if (is_array($decoded)) {
        return $decoded;
    }
    // Mirror \Voxel\get()'s `(array)` cast: null decodes to an empty config,
    // a scalar to a one-element list.
    return $decoded === null ? [] : [$decoded];
}

/**
 * The whole voxel:post_types blob as an array (keyed by post type key), read
 * straight from the database so an in-lock read can never see a stale cached
 * snapshot. See the locking note in the file header.
 *
 * @return array<array-key, mixed>
 */
function vx_read_post_types_config(): array
{
    return vx_read_config_blob_from_db('post_types');
}

/** @param array<array-key, mixed> $config */
function vx_write_post_types_config(array $config): void
{
    \Voxel\set('post_types', $config);
}

/**
 * The whole voxel:taxonomies blob as an array (keyed by taxonomy key), read
 * straight from the database so an in-lock read can never see a stale cached
 * snapshot. See the locking note in the file header.
 *
 * @return array<array-key, mixed>
 */
function vx_read_taxonomies_config(): array
{
    return vx_read_config_blob_from_db('taxonomies');
}

/** @param array<array-key, mixed> $config */
function vx_write_taxonomies_config(array $config): void
{
    \Voxel\set('taxonomies', $config);
}

/**
 * Resolve one post type's config entry from the option, or a WP_Error. Works
 * regardless of WP registration (unlike vx_post_type_or_error).
 *
 * @param array<array-key, mixed> $all the full post_types config
 * @return array<array-key, mixed>|WP_Error
 */
function vx_post_type_config_or_error(array $all, string $key): array|WP_Error
{
    if ($key === '') {
        return vx_invalid(__('A non-empty post type key is required.', domain: 'novamira-pro'));
    }
    if (!isset($all[$key]) || !is_array($all[$key])) {
        return vx_not_found('Voxel post type', $key);
    }
    return $all[$key];
}

/**
 * @param array<array-key, mixed> $all the full taxonomies config
 * @return array<array-key, mixed>|WP_Error
 */
function vx_taxonomy_config_or_error(array $all, string $key): array|WP_Error
{
    if ($key === '') {
        return vx_invalid(__('A non-empty taxonomy key is required.', domain: 'novamira-pro'));
    }
    if (!isset($all[$key]) || !is_array($all[$key])) {
        return vx_not_found('Voxel taxonomy', $key);
    }
    return $all[$key];
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

/** Whether a field type is registered with Voxel. */
function vx_field_type_exists(string $type): bool
{
    /** @var mixed $types */
    $types = \Voxel\config('post_types.field_types');
    return is_array($types) && isset($types[$type]);
}

/**
 * WordPress + Voxel reserved post type keys that must not be used for a new type.
 *
 * @return list<string>
 */
function vx_reserved_post_type_keys(): array
{
    return [
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'action',
        'author',
        'order',
        'theme',
        'fields',
        'types',
    ];
}

/**
 * Field keys this surface must never create.
 *
 * `product` is the one that matters: Voxel decides a post type has a price index
 * table by looking for a field whose key *and* type are both `product`
 * (Index_Table::has_price_index_table). This surface cannot create a product
 * field — add-field rejects the type — so taking the key with some other field
 * type would permanently block the real one from ever being added in the Voxel
 * admin, with no way back through these abilities.
 *
 * @return list<string>
 */
function vx_reserved_field_keys(): array
{
    return ['product'];
}

/**
 * WordPress reserved term/taxonomy keys.
 *
 * @return list<string>
 */
function vx_reserved_taxonomy_keys(): array
{
    return [
        'action',
        'attachment',
        'attachment_id',
        'author',
        'author_name',
        'calendar',
        'cat',
        'category',
        'category__and',
        'category__in',
        'category__not_in',
        'category_name',
        'comments_per_page',
        'comments_popup',
        'cpage',
        'custom',
        'customize_messenger_channel',
        'customized',
        'date',
        'day',
        'debug',
        'embed',
        'error',
        'exact',
        'feed',
        'fields',
        'hour',
        'link_category',
        'm',
        'minute',
        'monthnum',
        'more',
        'name',
        'nav_menu',
        'nonce',
        'nopaging',
        'offset',
        'order',
        'orderby',
        'p',
        'page',
        'page_id',
        'paged',
        'pagename',
        'pb',
        'perm',
        'post',
        'post__in',
        'post__not_in',
        'post_format',
        'post_mime_type',
        'post_status',
        'post_tag',
        'post_type',
        'posts',
        'posts_per_archive_page',
        'posts_per_page',
        'preview',
        'robots',
        's',
        'search',
        'second',
        'sentence',
        'showposts',
        'static',
        'status',
        'subpost',
        'subpost_id',
        'tag',
        'tag__and',
        'tag__in',
        'tag__not_in',
        'tag_id',
        'tag_slug__and',
        'tag_slug__in',
        'taxonomy',
        'tb',
        'term',
        'terms',
        'theme',
        'title',
        'type',
        'types',
        'w',
        'withcomments',
        'withoutcomments',
        'year',
    ];
}

/**
 * Validate a proposed new post type / taxonomy key against sanitize_key, length,
 * the reserved list, and existing registration. $existing is the current config
 * blob (for the "already defined" check). Returns null when OK.
 *
 * @param array<array-key, mixed> $existing
 * @param list<string> $reserved
 */
function vx_validate_new_key(string $key, int $max_len, array $reserved, array $existing): ?WP_Error
{
    if ($key === '' || sanitize_key($key) !== $key) {
        return vx_invalid(__(
            'Key must be lowercase letters, numbers, hyphens and underscores only.',
            domain: 'novamira-pro',
        ));
    }
    // Reject an all-digit key: PHP coerces a numeric string to an integer array
    // key, and Voxel discards any non-string config key on its next save
    // (post-type-repository.php), so the type/taxonomy would vanish silently.
    if (ctype_digit($key)) {
        return vx_invalid(__('Key must contain at least one letter.', domain: 'novamira-pro'));
    }
    if (strlen($key) > $max_len) {
        return vx_invalid(sprintf(
            /* translators: %d maximum length. */
            __('Key must be at most %d characters.', domain: 'novamira-pro'),
            $max_len,
        ));
    }
    if (in_array($key, $reserved, strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s the key. */
            __('"%s" is a reserved key and cannot be used.', domain: 'novamira-pro'),
            $key,
        ));
    }
    if (isset($existing[$key])) {
        return vx_invalid(sprintf(
            /* translators: %s the key. */
            __('"%s" already exists.', domain: 'novamira-pro'),
            $key,
        ));
    }
    return null;
}

/**
 * Build a stored field config from input: {type, key, label} plus optional
 * description, required, and a freeform props object (type-specific settings).
 * Voxel fills any missing base props with defaults when it parses the field.
 *
 * @param array<array-key, mixed> $input
 * @return array<array-key, mixed>
 */
function vx_build_field_config(string $type, string $key, string $label, array $input): array
{
    $config = ['type' => $type, 'key' => $key, 'label' => $label];
    if (isset($input['description']) && is_string($input['description'])) {
        $config['description'] = $input['description'];
    }
    if (array_key_exists('required', $input)) {
        $config['required'] = $input['required'] === true;
    }
    if (isset($input['props']) && is_array($input['props'])) {
        vx_apply_field_props($config, $input['props']);
    }
    return $config;
}

/**
 * The structured field keys that come only from their own dedicated, sanitized
 * inputs and must NEVER be taken raw from the freeform `props` object. `label`
 * in particular reaches an unescaped wp-admin sink (expiry-metabox), so an
 * unsanitized props.label is a stored-XSS vector; `type`/`key` are immutable and
 * `required`/`description` have their own typed params.
 *
 * @return list<string>
 */
function vx_reserved_field_prop_keys(): array
{
    return ['type', 'key', 'label', 'required', 'description'];
}

/**
 * Merge a freeform props object into a field config, skipping the structured
 * keys (see vx_reserved_field_prop_keys). Mutates $config in place.
 *
 * @param array<array-key, mixed> $config
 * @param array<array-key, mixed> $props
 */
function vx_apply_field_props(array &$config, array $props): void
{
    $reserved = vx_reserved_field_prop_keys();
    /** @var mixed $value */
    foreach ($props as $prop => $value) {
        if (!is_string($prop) || in_array($prop, $reserved, strict: true)) {
            continue;
        }
        $config[$prop] = $value;
    }
}

/**
 * Whether an array is associative (has at least one string key). An empty array
 * and a pure 0..n list are both treated as non-associative. Avoids the 8.1-only
 * array_is_list so the helper is safe on the plugin's PHP floor.
 *
 * @param array<array-key, mixed> $value
 */
function vx_is_assoc_array(array $value): bool
{
    foreach (array_keys($value) as $k) {
        if (is_string($k)) {
            return true;
        }
    }
    return false;
}

/**
 * Recursively merge $patch into $base for a partial settings edit. Associative
 * sub-arrays are merged so toggling one nested flag preserves the caller's
 * untouched siblings (a shallow replace silently drops them, and Voxel then
 * falls back to defaults — e.g. submission moderation quietly reverts). List
 * arrays and scalars replace wholesale, since a list cannot be partially patched.
 *
 * @param array<array-key, mixed> $base
 * @param array<array-key, mixed> $patch
 * @return array<array-key, mixed>
 */
function vx_deep_merge_settings(array $base, array $patch): array
{
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        /** @var mixed $existing */
        $existing = $base[$key] ?? null;
        if (is_array($value) && vx_is_assoc_array($value) && is_array($existing) && vx_is_assoc_array($existing)) {
            $base[$key] = vx_deep_merge_settings($existing, $value);
            continue;
        }
        $base[$key] = $value;
    }
    return $base;
}

/**
 * Reject an edit that would blank a required label (create rejects empty labels,
 * so edit must too). Only checks a key when it is present as a string in $input.
 *
 * @param array<array-key, mixed> $input
 * @param list<string> $keys
 */
function vx_reject_blank_labels(array $input, array $keys): ?WP_Error
{
    foreach ($keys as $key) {
        if (isset($input[$key]) && is_string($input[$key]) && trim($input[$key]) === '') {
            return vx_invalid(__('A label cannot be set to an empty value.', domain: 'novamira-pro'));
        }
    }
    return null;
}

/**
 * Compact row for a field config (for list-style output).
 *
 * @param array<array-key, mixed> $field
 * @return array{key: string, type: string, label: string, required: bool}
 */
function vx_field_config_row(array $field): array
{
    return [
        'key' => isset($field['key']) && is_string($field['key']) ? $field['key'] : '',
        'type' => isset($field['type']) && is_string($field['type']) ? $field['type'] : '',
        'label' => isset($field['label']) && is_string($field['label']) ? $field['label'] : '',
        'required' => ($field['required'] ?? false) === true,
    ];
}

/**
 * Project a post type's field config array to compact rows.
 *
 * @param mixed $fields
 * @return list<array{key: string, type: string, label: string, required: bool}>
 */
function vx_field_config_rows(mixed $fields): array
{
    if (!is_array($fields)) {
        return [];
    }
    $rows = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $rows[] = vx_field_config_row($field);
    }
    return $rows;
}

/**
 * The field-config list of a post type config entry as a list of arrays.
 *
 * @param array<array-key, mixed> $config
 * @return list<array<array-key, mixed>>
 */
function vx_config_field_list(array $config): array
{
    /** @var mixed $fields */
    $fields = $config['fields'] ?? [];
    if (!is_array($fields)) {
        return [];
    }
    $out = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $out[] = $field;
    }
    return $out;
}

/**
 * Index of the field with the given key, or null.
 *
 * @param list<array<array-key, mixed>> $fields
 */
function vx_find_field_index(array $fields, string $key): ?int
{
    foreach ($fields as $i => $field) {
        if (($field['key'] ?? null) === $key) {
            return $i;
        }
    }
    return null;
}

/**
 * Shared output schema for the field-mutation abilities.
 *
 * @return array<array-key, mixed>
 */
function vx_field_op_output_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string'],
            'fields_count' => ['type' => 'integer'],
            'fields' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'required' => ['type' => 'boolean'],
                    ],
                    'required' => ['key', 'type', 'label', 'required'],
                    'additionalProperties' => false,
                ],
            ],
            'field' => ['type' => ['object', 'null'], 'additionalProperties' => true],
        ],
        'required' => ['post_type', 'fields_count', 'fields', 'field'],
        'additionalProperties' => false,
    ];
}

/**
 * Build the shared field-op result envelope. When $field_key is given and found,
 * the full config of that field is included; otherwise `field` is null.
 *
 * The field list is re-read from the stored config rather than taken from the
 * caller's in-memory array. \Voxel\set() discards the update_option() result, so
 * an envelope built from the array we meant to save would describe a state that
 * may never have been persisted. The setter refreshes its own cache on write, so
 * this reads the value that is actually stored. Call it only after the write.
 *
 * @return array<string, mixed>
 */
function vx_field_op_result(string $post_type, ?string $field_key): array
{
    $all = vx_read_post_types_config();
    /** @var mixed $config */
    $config = $all[$post_type] ?? null;
    $fields = is_array($config) ? vx_config_field_list($config) : [];
    $index = $field_key !== null ? vx_find_field_index($fields, $field_key) : null;
    $field = $index !== null ? $fields[$index] : null;
    return [
        'post_type' => $post_type,
        'fields_count' => count($fields),
        'fields' => vx_field_config_rows($fields),
        'field' => $field,
    ];
}

/**
 * Delete the per-post-type options Voxel writes but never cleans up, and report
 * which ones were actually there.
 *
 * Two exist. `voxel:post-type-<key>:revisions` (post-type-revisions.php) holds a
 * JSON snapshot of the whole config per revision and is removed by nobody, in
 * either the theme or this plugin. `post_type_index:<key>` (post-type-controller
 * .php:333) is normally cleared when the async index run finishes, so it only
 * survives when that run never started or never completed.
 *
 * @return list<string> option names that existed and are now gone
 */
function vx_delete_post_type_options(string $key): array
{
    $safe = sanitize_key($key);
    if ($safe === '') {
        return [];
    }
    $deleted = [];
    foreach (['voxel:post-type-' . $safe . ':revisions', 'post_type_index:' . $safe] as $option) {
        if (!delete_option($option)) {
            continue;
        }
        $deleted[] = $option;
    }
    return $deleted;
}

/**
 * Whether a table physically exists. Takes the full, already-prefixed name.
 */
function vx_table_exists(string $full_name): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $full_name);
    /** @var mixed $found */
    $found = is_string($sql) ? $wpdb->get_var($sql) : null;
    return $found === $full_name;
}

/**
 * Drop the orphaned search-index tables for a post type (Voxel leaks these on
 * delete). Table names are built from a sanitized key — injection-safe.
 *
 * Returns whether a table was actually removed, not whether the statements ran:
 * `DROP TABLE IF EXISTS` succeeds against a table that was never there, so the
 * query result alone would report a cleanup that never happened. Existence is
 * therefore checked before and after each drop.
 */
function vx_drop_index_tables(string $key): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $safe = sanitize_key($key);
    if ($safe === '') {
        return false;
    }
    $dropped = false;
    foreach (['voxel_price_index_', 'voxel_index_'] as $prefix) {
        $name = $wpdb->prefix . $prefix . $safe;
        if (!vx_table_exists($name)) {
            continue;
        }
        $wpdb->query("DROP TABLE IF EXISTS `{$name}`");
        if (!vx_table_exists($name)) {
            $dropped = true;
        }
    }
    return $dropped;
}

// Search-index maintenance. Loaded from here, at the end, because it runs as soon
// as it is included and needs the helpers above (vx_table_exists,
// vx_read_post_types_config) to already be defined — runtime.php, the other
// natural home, is required before them.
require_once __DIR__ . '/index-maintenance.php';
