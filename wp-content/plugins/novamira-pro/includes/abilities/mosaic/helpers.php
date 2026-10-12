<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Mosaic abilities.
 *
 * Provides:
 *  - mo_error()           — uniform WP_Error factory
 *  - mo_table()           — prefix-aware table name helper
 *  - mo_require_runtime() — pre-flight gate every callback uses
 *  - mo_coerce_int()      — int coercion for numeric-string inputs
 *  - mo_clamp_limit()     — pagination limit clamp
 *  - mo_array_is_list()   — PHP 8.0 polyfill (mirrors other specializations)
 */

/**
 * Builds the WP_Error this surface returns. Keeps the `mo_` error code
 * prefix consistent across all 50+ Mosaic abilities and centralises the
 * translator-comment shape.
 */
function mo_error(string $code, string $message): WP_Error
{
    return new WP_Error($code, $message);
}

/**
 * Build the typed error every write callback returns when its UPDATE
 * matches zero rows under the optimistic-concurrency guard (`WHERE
 * status='publish' AND revision=$old_revision`). The zero-rows-matched
 * outcome means the row was modified, soft-deleted, or hard-deleted by
 * another writer between this ability's load and update — Mosaic's
 * editor REST save (which runs on a different MySQL connection and
 * bypasses MySQL's per-connection GET_LOCK) is the most common source.
 *
 * Round 8 H6 / H3 found that without this guard, writes silently mutated
 * tombstoned rows, lost merge-mode updates against external writers, or
 * committed dangling parentID references. The error code is documented
 * in SKILL.md so agents know they can re-fetch and retry.
 */
function mo_concurrent_modification_error(string $entity, string $entity_id): WP_Error
{
    return mo_error('mo_concurrent_modification', sprintf(
        /* translators: 1: entity kind (node/template/...), 2: entity UUID. */
        __(
            'Mosaic %1$s %2$s was modified or removed by another writer between this call\'s load and update (likely a Mosaic editor save on a different connection). Re-fetch via mosaic-get-element-tree or mosaic-get-template and retry.',
            domain: 'novamira-pro',
        ),
        $entity,
        $entity_id,
    ));
}

/**
 * Fully-qualified Mosaic table name. Mosaic stores everything in
 * `<wpdb-prefix>mosaic_<entity>` tables (not WP CPTs), so every read /
 * write goes through this helper instead of hardcoding the prefix.
 */
function mo_table(string $entity): string
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    return $wpdb->prefix . 'mosaic_' . $entity;
}

/**
 * Pre-flight every ability callback runs as the first line. Returns
 * `null` when the runtime is ready and the caller can proceed; returns
 * a typed `WP_Error` when Mosaic is missing, below the minimum version,
 * or the schema hasn't been bootstrapped yet. Keeps the per-ability
 * boilerplate to one line:
 *
 *     $err = mo_require_runtime();
 *     if ($err !== null) {
 *         return $err;
 *     }
 */
function mo_require_runtime(): ?WP_Error
{
    if (mo_runtime_available()) {
        return null;
    }
    $version = mo_version();
    if ($version === '') {
        return mo_error('mo_runtime_unavailable', __(
            'Mosaic plugin is not active. Activate Mosaic Pro before calling Mosaic abilities.',
            domain: 'novamira-pro',
        ));
    }
    if (!version_compare($version, NOVAMIRA_PRO_MOSAIC_MIN_VERSION, operator: '>=')) {
        return mo_error('mo_runtime_unavailable', sprintf(
            /* translators: 1: installed Mosaic version, 2: supported minimum. */
            __(
                'Mosaic %1$s is below the supported minimum %2$s. Update Mosaic before calling Mosaic abilities.',
                domain: 'novamira-pro',
            ),
            $version,
            NOVAMIRA_PRO_MOSAIC_MIN_VERSION,
        ));
    }
    if (!version_compare($version, NOVAMIRA_PRO_MOSAIC_MAX_VERSION, operator: '<')) {
        return mo_error('mo_runtime_unavailable', sprintf(
            /* translators: 1: installed Mosaic version, 2: supported maximum (exclusive). */
            __(
                'Mosaic %1$s is at or above the supported ceiling %2$s. Wait for a Novamira Pro release that audits the new version.',
                domain: 'novamira-pro',
            ),
            $version,
            NOVAMIRA_PRO_MOSAIC_MAX_VERSION,
        ));
    }
    // Mosaic only defines MOSAIC_SITE_RUNNING once this site's data matches
    // the running code (see mo_runtime_available()).
    if (!defined('MOSAIC_SITE_RUNNING')) {
        return mo_error('mo_runtime_unavailable', __(
            'Mosaic has not finished upgrading this site\'s data yet. Open the Mosaic admin on this site to complete its data upgrade, then retry.',
            domain: 'novamira-pro',
        ));
    }
    // Version is in range but tables missing or class not loaded.
    return mo_error('mo_runtime_unavailable', __(
        'Mosaic custom tables have not been provisioned yet. Visit the Mosaic admin page once to trigger the schema bootstrap.',
        domain: 'novamira-pro',
    ));
}

/**
 * Defensive int coercion. JSON Schema validation against `type: integer`
 * normally rejects numeric strings, but REST clients occasionally smuggle
 * them through as objects-of-mixed-shape (bracket-notation query strings,
 * legacy clients). Mirrors `bd_coerce_int` from the breakdance
 * specialization.
 *
 * @param mixed $value
 */
function mo_coerce_int(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && $value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }
    return 0;
}

/**
 * Clamp a pagination `limit` request to a sane range. Default 100, hard
 * cap 500 — values above the cap are returned at 500 (not rejected) so
 * an agent that overshoots the cap still gets a usable page. The schema
 * declares its own `maximum: 500` which makes the abilities-api validator
 * reject anything above the cap; this helper is the runtime defence for
 * direct PHP callers.
 */
function mo_clamp_limit(?int $limit): int
{
    if ($limit === null || $limit <= 0) {
        return 100;
    }
    return min($limit, 500);
}

/**
 * PHP 8.0 polyfill for `array_is_list()` (native in 8.1+). Mirrors the
 * canonical implementation other specializations use (bd_array_is_list /
 * kd_array_is_list). The minimum PHP supported by Novamira Pro is 8.0
 * (see plugin header `Requires PHP: 8.0`), so the polyfill is required.
 *
 * @param array<array-key, mixed> $value
 */
function mo_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * Single integration seam for downstream listeners (page-cache plugins,
 * audit-log plugins, compliance tooling): every write ability calls this
 * after a successful mutation so a third party can flush, log, or
 * react. Mosaic itself fires no equivalent hook on these tables, and
 * our writes bypass WP's CPT pipeline (no save_post / clean_post_cache
 * / wp_after_insert_post), so without this hook the writes are
 * invisible to Stream, WP Activity Log, WP Rocket, LiteSpeed, W3TC,
 * and any custom integration that wants to mirror the agent's actions.
 *
 * Payload shape (every field always present, nullable fields default to ''):
 *   ability        — full slug, e.g. 'novamira/mosaic-add-element'
 *   operation      — 'add' | 'edit' | 'delete' | 'move' | 'create' | 'set' | 'clear'
 *   entity         — 'node' | 'template' | 'template_assign'
 *   entity_id      — node UUID, template UUID, or composite key for assigns
 *   document_type  — for node operations: 'template' | 'master' | 'styleGuide' | 'component'
 *   document_id    — for node operations: the document UUID
 *   theme_id       — the owning theme UUID (always set when known)
 *   template_id    — for template_assign operations: the bound template UUID
 *   user_id        — current WP user id at the time of the write
 *
 * @param array<string, string|int> $context
 */
function mo_emit_changed(
    string $ability,
    string $operation,
    string $entity,
    string $entity_id,
    array $context = [],
): void {
    $defaults = [
        'ability' => $ability,
        'operation' => $operation,
        'entity' => $entity,
        'entity_id' => $entity_id,
        'document_type' => '',
        'document_id' => '',
        'theme_id' => '',
        'template_id' => '',
        'user_id' => get_current_user_id(),
    ];
    do_action('novamira_pro_mosaic_changed', array_merge($defaults, $context));
}

/**
 * Encode a data blob for an ARRAY-typed Mosaic column. Empty arrays
 * persist as the JSON array `[]`. Use for columns whose corresponding
 * revision-record property is typed `?array`:
 *   - wp_mosaic_templates.conditions
 *
 * Mosaic decodes these with `json_decode($raw)` (no assoc=true) and
 * assigns to the `?array` property. A `{}` literal lands as stdClass
 * and crashes the renderer with a TypeError on every front-end URL
 * the template resolves to AND the Mosaic admin Templates list.
 *
 * @param array<string, mixed> $data
 */
function mo_encode_array_blob(array $data): string
{
    return (string) wp_json_encode($data);
}

/**
 * Encode a data blob for an OBJECT-typed Mosaic column. Empty arrays
 * are forced to the JSON object literal `{}` (a bare `[]` would crash
 * `json_decode`-then-assign to a stdClass-typed property). Non-empty
 * associative arrays serialise to `{...}` as PHP would anyway; we only
 * intervene on the empty case.
 *
 * Use for columns whose corresponding revision-record property is
 * typed `object` / `?object` / `?stdClass`:
 *   - wp_mosaic_nodes.data          (object — strictly non-null)
 *   - wp_mosaic_themes.data         (?object)
 *   - wp_mosaic_universal_classes.data (?stdClass)
 *   - wp_mosaic_settings.data       (?object)
 *
 * @param array<string, mixed> $data
 */
function mo_encode_object_blob(array $data): string
{
    if ($data === []) {
        return '{}';
    }
    return (string) wp_json_encode($data);
}

/**
 * @deprecated since Sprint 3 audit round 2. Kept as a backwards-compat
 * alias for `mo_encode_array_blob` while the codebase migrates. New
 * callers MUST pick the explicit array/object variant based on the
 * column's revision-record property type.
 *
 * @param array<string, mixed> $data
 */
function mo_encode_data(array $data): string
{
    return mo_encode_array_blob($data);
}

/**
 * Decode the `data` longtext column most Mosaic entity tables carry.
 * Returns [] when the column is empty or not a string.
 *
 * When the column holds non-empty content that json_decode can't parse —
 * malformed JSON, or a depth past {@see MO_BLOB_MAX_DEPTH} planted via
 * direct SQL bypassing {@see mo_validate_blob_depth()} on the write side —
 * returns a sentinel `[_decode_failed => true, _bytes => N]` marker so
 * the agent sees an explicit signal instead of an empty array. Round 5
 * (E8) found that the previous silent-`[]` behavior matched the "blob
 * was empty" case byte-for-byte, hiding data corruption on the read path.
 *
 * @param mixed $raw
 * @return array<array-key, mixed>
 */
function mo_decode_data(mixed $raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $raw, associative: true, depth: MO_BLOB_MAX_DEPTH);
    if (!is_array($decoded)) {
        return ['_decode_failed' => true, '_bytes' => strlen($raw)];
    }
    return $decoded;
}

/**
 * Maximum nesting depth we accept (and round-trip) for any agent-supplied
 * data blob — node `data`, template `conditions`, etc. Set well below
 * PHP's `json_encode` / `json_decode` ceiling (512) so writes can never
 * silently truncate, and matched between encode/decode so {@see
 * mo_decode_data()} can read anything {@see mo_validate_blob_depth()}
 * accepted on the way in.
 *
 * Real-world Mosaic blobs are typically <10 levels deep; 256 is generous
 * for any legitimate nested style/breakpoint blob while still cutting off
 * adversarially-deep payloads before Mosaic's own renderer trips on them.
 */
const MO_BLOB_MAX_DEPTH = 256;

/**
 * Walk an associative array and return its maximum nesting depth (1 for
 * a flat array, 2 for one-level-of-nesting, etc.). Stops walking past
 * {@see MO_BLOB_MAX_DEPTH} so adversarial input cannot OOM the audit
 * traversal itself.
 *
 * @param array<array-key, mixed> $value
 */
function mo_array_depth(array $value, int $current = 1): int
{
    if ($current >= MO_BLOB_MAX_DEPTH) {
        return $current;
    }
    $max = $current;
    // @mago-expect analysis:mixed-assignment
    foreach ($value as $v) {
        if (!is_array($v)) {
            continue;
        }
        $d = mo_array_depth($v, $current + 1);
        if ($d > $max) {
            $max = $d;
        }
    }
    return $max;
}

/**
 * Reject blobs deeper than {@see MO_BLOB_MAX_DEPTH}. Audit round 4 (D5)
 * proved that without this guard, writes at depth ≥512 succeed (wp_json_encode
 * silently returns false → empty string persisted) and reads then return
 * `{}` — silent data loss with no agent-visible signal. Called before any
 * `mo_encode_*_blob()` to short-circuit the corruption window.
 *
 * @param array<array-key, mixed> $data
 */
function mo_validate_blob_depth(array $data, string $field): ?WP_Error
{
    $depth = mo_array_depth($data);
    if ($depth >= MO_BLOB_MAX_DEPTH) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: 1: field name, 2: depth limit. */
            __(
                '`%1$s` blob is too deeply nested for safe storage (Mosaic can only round-trip up to %2$d levels). Flatten the structure before retrying.',
                domain: 'novamira-pro',
            ),
            $field,
            MO_BLOB_MAX_DEPTH - 1,
        ));
    }
    return null;
}

/**
 * Per-blob byte ceiling. Round 5 (E8) found that adversarial payloads with
 * many 1 MB strings can pass {@see mo_validate_blob_depth()} (depth low,
 * shape benign) but OOM PHP at `wpdb::prepare()` time when wpdb allocates
 * the escape buffer. The depth check alone is not enough — bound the
 * encoded byte length too.
 *
 * 1 MiB is generous for any legitimate Mosaic blob (real-world `data` /
 * `conditions` are typically <10 KB) while still keeping wpdb's escape
 * pass well under the default 128 MB PHP memory_limit.
 */
const MO_BLOB_MAX_BYTES = 1_048_576;

/**
 * Reject encoded blobs larger than {@see MO_BLOB_MAX_BYTES}. The depth
 * check covers nesting; this covers WIDTH. Call after depth validation
 * passes — the JSON encode is the expensive step and we want to fail
 * fast on the cheaper check first.
 *
 * @param array<array-key, mixed> $data
 */
function mo_validate_blob_bytes(array $data, string $field): ?WP_Error
{
    $encoded = wp_json_encode($data);
    if ($encoded === false) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: field name. */
            __('`%s` blob could not be JSON-encoded (likely a non-UTF-8 string).', domain: 'novamira-pro'),
            $field,
        ));
    }
    $bytes = strlen($encoded);
    if ($bytes > MO_BLOB_MAX_BYTES) {
        return mo_error('mo_payload_too_large', sprintf(
            /* translators: 1: field name, 2: actual bytes, 3: cap bytes. */
            __(
                '`%1$s` blob is %2$d bytes — over the %3$d-byte safe storage limit. Shrink the payload before retrying.',
                domain: 'novamira-pro',
            ),
            $field,
            $bytes,
            MO_BLOB_MAX_BYTES,
        ));
    }
    return null;
}

/**
 * Reject string inputs whose char count exceeds the column they will be
 * persisted into. Mosaic's wizard facade silently truncates names past
 * varchar(191) instead of erroring (round 5 E4) — without this guard
 * the agent gets a successful response holding a truncated string and
 * has no way to know the supplied value was rejected.
 *
 * Counts CHARACTERS (Unicode codepoints), matching MySQL's collation
 * which uses chars (`CHARACTER_MAXIMUM_LENGTH`) rather than bytes for
 * utf8mb4 varchar columns.
 *
 * @param array<string, array{value:string, max:int}> $fields
 */
function mo_validate_lengths(array $fields): ?WP_Error
{
    foreach ($fields as $name => $spec) {
        $chars = mb_strlen($spec['value']);
        if ($chars > $spec['max']) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: 1: field name, 2: actual chars, 3: cap. */
                __('`%1$s` is %2$d characters — over the %3$d-character column limit.', domain: 'novamira-pro'),
                $name,
                $chars,
                $spec['max'],
            ));
        }
    }
    return null;
}

/**
 * Apply the agent-facing `style` / `attributes` shortcuts onto an
 * element-data blob. Mosaic's internal `data.attributes` is an array of
 * `{name, value, uuid}` records (it deserialises with `json_decode($raw)` and
 * type-checks as `?array`, so a string-keyed map decodes to stdClass and
 * fatals at render time). Agents shouldn't have to know that — they pass
 * a CSS string or a `{prop: value}` map for `style`, and a `{name: value}`
 * map for other HTML attributes, and this helper merges them into the
 * native shape.
 *
 * Behaviour:
 *  - `$style` accepts a CSS string OR a `{prop: value}` map (auto-converted
 *    to a CSS string with semicolons between rules). An empty string clears
 *    the inline style; `null` leaves any existing `attributes[name=style]`
 *    record untouched.
 *  - `$attributes` accepts a `{name: value}` map. Each entry overlays its
 *    same-named record (or appends a new one).
 *  - Existing Mosaic-native `attributes` in `$data` (array of records) are
 *    preserved. The shortcut entries override entries with the same `name`.
 *
 * @param array<array-key, mixed> $data
 * @param mixed                   $style
 * @param mixed                   $attributes
 * @return array<string, mixed>|WP_Error
 */
function mo_normalize_element_attributes(array $data, mixed $style, mixed $attributes): array|WP_Error
{
    $merged = mo_collect_attribute_records($data['attributes'] ?? null);
    if ($merged instanceof WP_Error) {
        return $merged;
    }
    $merged = mo_overlay_attributes_map($merged, $attributes);
    if ($merged instanceof WP_Error) {
        return $merged;
    }
    $merged = mo_apply_style_shortcut($merged, $style);
    if ($merged instanceof WP_Error) {
        return $merged;
    }
    /** @var array<string, mixed> $out */
    $out = $data;
    if ($merged === []) {
        unset($out['attributes']);
        return $out;
    }
    $out['attributes'] = mo_attributes_map_to_records($merged);
    return $out;
}

/**
 * @param  mixed $existing
 * @return array<string, array{value:string, uuid:?string}>|WP_Error
 */
function mo_collect_attribute_records(mixed $existing): array|WP_Error
{
    if ($existing === null) {
        return [];
    }
    if (!is_array($existing)) {
        return mo_error('mo_invalid_input', __(
            '`data.attributes` must be an array of `{name, value, uuid}` records (Mosaic-native shape). Use the top-level `attributes` map for the agent-friendly shape.',
            domain: 'novamira-pro',
        ));
    }
    /** @var array<string, array{value:string, uuid:?string}> $merged */
    $merged = [];
    /** @var mixed $record */
    foreach ($existing as $record) {
        if (!is_array($record)) {
            return mo_error('mo_invalid_input', __(
                'Each entry in `data.attributes` must be a `{name, value, uuid}` record.',
                domain: 'novamira-pro',
            ));
        }
        $name = is_string($record['name'] ?? null) ? $record['name'] : '';
        $value = is_string($record['value'] ?? null) ? $record['value'] : '';
        $uuid = is_string($record['uuid'] ?? null) ? trim($record['uuid']) : null;
        $uuid = $uuid !== '' ? $uuid : null;
        if ($name !== '') {
            $merged[$name] = ['value' => $value, 'uuid' => $uuid];
        }
    }
    return $merged;
}

/**
 * @param  array<string, array{value:string, uuid:?string}> $merged
 * @param  mixed                                               $attributes
 * @return array<string, array{value:string, uuid:?string}>|WP_Error
 */
function mo_overlay_attributes_map(array $merged, mixed $attributes): array|WP_Error
{
    if (!is_array($attributes)) {
        return $merged;
    }
    /** @var mixed $value */
    foreach ($attributes as $name => $value) {
        if (!is_string($name) || $name === '') {
            return mo_error('mo_invalid_input', __(
                '`attributes` keys must be non-empty strings.',
                domain: 'novamira-pro',
            ));
        }
        if (!is_string($value)) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: attribute name */
                __('`attributes.%s` must be a string.', domain: 'novamira-pro'),
                $name,
            ));
        }
        $merged[$name] = [
            'value' => $value,
            'uuid' => $merged[$name]['uuid'] ?? null,
        ];
    }
    return $merged;
}

/**
 * @param  array<string, array{value:string, uuid:?string}> $merged
 * @param  mixed                                               $style
 * @return array<string, array{value:string, uuid:?string}>|WP_Error
 */
function mo_apply_style_shortcut(array $merged, mixed $style): array|WP_Error
{
    if ($style === null) {
        return $merged;
    }
    if (is_string($style)) {
        if ($style === '') {
            unset($merged['style']);
            return $merged;
        }
        $merged['style'] = [
            'value' => $style,
            'uuid' => $merged['style']['uuid'] ?? null,
        ];
        return $merged;
    }
    if (is_array($style)) {
        $css = mo_property_map_to_css($style);
        if ($css instanceof WP_Error) {
            return $css;
        }
        if ($css === '') {
            unset($merged['style']);
            return $merged;
        }
        $merged['style'] = [
            'value' => $css,
            'uuid' => $merged['style']['uuid'] ?? null,
        ];
        return $merged;
    }
    return mo_error('mo_invalid_input', __(
        '`style` must be a CSS string, a `{property: value}` map, or null.',
        domain: 'novamira-pro',
    ));
}

/**
 * @param  array<string, array{value:string, uuid:?string}> $merged
 * @return list<array{name:string, value:string, uuid:string}>
 */
function mo_attributes_map_to_records(array $merged): array
{
    $records = [];
    foreach ($merged as $name => $attribute) {
        $uuid = $attribute['uuid'];
        // Repair records written before attribute UUIDs were emitted, as well
        // as malformed native input, at the single record-serialization exit.
        if (
            $uuid === null
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1
        ) {
            $uuid = mo_uuid();
        }
        $records[] = ['name' => $name, 'value' => $attribute['value'], 'uuid' => $uuid];
    }
    return $records;
}

/**
 * Resolve a `collection_skins` / `collection_modes` row scoped to a
 * collection, accepting either the row UUID or its `data.name` display
 * name (case-insensitive, trimmed) — the same UUID-or-name duality the
 * element class inputs accept. An empty `$requested` resolves to the first published
 * row (lowest `ordering`), matching Mosaic's own default-to-first-
 * published rule (`CollectionMResource::getCurrentSkin()` /
 * `CollectionVariableMResourceAbstract::getFirstModeID()`).
 *
 * @param  'collection_skins'|'collection_modes' $entity
 * @return string|WP_Error row UUID
 */
function mo_resolve_collection_child_id(
    string $entity,
    string $collection_id,
    string $requested,
    string $label,
): string|WP_Error {
    $rows = mo_select($entity, ['parentID' => $collection_id, 'parentType' => 'collection'], limit: 500, offset: 0);
    if ($rows === []) {
        return mo_error('mo_not_found', sprintf(
            /* translators: 1: "skin" or "mode", 2: collection UUID. */
            __('Collection %2$s has no published %1$s rows.', domain: 'novamira-pro'),
            $label,
            $collection_id,
        ));
    }
    if ($requested === '') {
        return mo_str($rows[0], key: 'ID');
    }
    $normalized = mo_norm_uuid($requested);
    foreach ($rows as $row) {
        if (mo_norm_uuid(mo_str($row, key: 'ID')) === $normalized) {
            return mo_str($row, key: 'ID');
        }
    }
    $needle = mb_strtolower($requested);
    foreach ($rows as $row) {
        $data = mo_decode_data($row['data'] ?? null);
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        if (mb_strtolower($name) === $needle) {
            return mo_str($row, key: 'ID');
        }
    }
    return mo_error('mo_not_found', sprintf(
        /* translators: 1: "Skin" or "Mode" (capitalized), 2: the requested id/name, 3: collection UUID. */
        __('%1$s "%2$s" not found under collection %3$s. Use its UUID or exact display name.', domain: 'novamira-pro'),
        ucfirst($label),
        $requested,
        $collection_id,
    ));
}

/**
 * Convert a `{css-property: value}` map into a single CSS declaration
 * string with `;` separators. Used by {@see mo_normalize_element_attributes()}
 * to accept the agent-friendly shape for the top-level `style` parameter.
 *
 * @param array<array-key, mixed> $map
 * @return string|WP_Error
 */
function mo_property_map_to_css(array $map): string|WP_Error
{
    $declarations = [];
    /** @var mixed $value */
    foreach ($map as $prop => $value) {
        if (!is_string($prop) || $prop === '') {
            return mo_error('mo_invalid_input', __(
                '`style` property names must be non-empty strings (e.g. "background", "padding").',
                domain: 'novamira-pro',
            ));
        }
        if (preg_match('/^[a-z][a-z0-9-]*$/', $prop) !== 1) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: offending property name */
                __('`style` property `%s` is not a valid CSS property name.', domain: 'novamira-pro'),
                $prop,
            ));
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: offending property name */
                __('`style.%s` must be a string or number.', domain: 'novamira-pro'),
                $prop,
            ));
        }
        $declarations[] = $prop . ': ' . (string) $value;
    }
    return implode('; ', $declarations);
}

/**
 * Lowercase a UUID-bearing input string so PHP `===` comparisons against
 * Mosaic's lowercase-canonical UUIDs (`wp_generate_uuid4()`) cannot
 * silently miss when an agent passes an UPPERCASE id. Mosaic's MySQL
 * collation (`utf8mb4_unicode_ci`) is case-insensitive so SELECTs would
 * still match — without normalization the cycle-guard at
 * {@see mo_is_descendant()} compares lowercase-from-DB with the
 * uppercase-from-input and returns false-negative, letting a move
 * create a self-cycle (audit round 4 D9).
 *
 * Empty strings pass through unchanged so existing required-field
 * checks (`if ($id === '')`) keep working.
 */
function mo_norm_uuid(mixed $raw): string
{
    if (!is_string($raw)) {
        return '';
    }
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return '';
    }
    return strtolower($trimmed);
}

/**
 * Whitelist of column names that callers can use as filter keys against
 * mo_select / mo_count. Filter keys flow into the SQL between backticks
 * and do NOT pass through wpdb->prepare(), so anything outside this list
 * is rejected before the query is built. Today every caller passes
 * hard-coded keys, but the whitelist is defence-in-depth in case a future
 * ability ever forwards user-controlled filter keys.
 *
 * @return list<string>
 */
function mo_filter_columns(): array
{
    return [
        'themeID',
        'parentType',
        'parentID',
        'status',
        'type',
        'documentType',
        'documentID',
        'masterID',
    ];
}

/**
 * Sentinel value used inside a `status` filter to disable the default
 * `status = 'publish'` filter mo_select / mo_count append. Callers that
 * legitimately want to see deleted rows pass `['status' => MO_STATUS_ANY]`.
 */
const MO_STATUS_ANY = '*';

/**
 * Build the WHERE clause and prepared params for mo_select / mo_count.
 * Centralised so both helpers share the column-whitelist + status-default
 * + status-any-sentinel logic.
 *
 * @param array<string, scalar> $filters
 * @return array{0: list<string>, 1: list<string>}
 */
function mo_build_where(array $filters): array
{
    $allowed = mo_filter_columns();
    $where = [];
    $params = [];
    $status_explicit = false;
    foreach ($filters as $column => $value) {
        if (!in_array($column, $allowed, strict: true)) {
            continue;
        }
        if ($column === 'status') {
            $status_explicit = true;
            if ((string) $value === MO_STATUS_ANY) {
                continue;
            }
        }
        if ($value === '') {
            continue;
        }
        $where[] = "`{$column}` = %s";
        $params[] = (string) $value;
    }
    if (!$status_explicit) {
        $where[] = '`status` = %s';
        $params[] = 'publish';
    }
    return [$where, $params];
}

/**
 * SELECT rows from a Mosaic entity table with optional WHERE filters.
 * Centralised so every list-* ability shares the same prepared-statement
 * shape + the same `themeID` / `status` defaults. Filters keys mirror
 * the column names — `themeID`, `parentType`, `parentID`, `status`,
 * `type`, `documentType`, `documentID`. Unknown keys are silently
 * skipped (see mo_filter_columns whitelist).
 *
 * Status defaults to `publish` — Mosaic stores `publish` / `delete` for
 * soft-delete tracking (see MResourceStatus); listing deleted rows is
 * rarely what the agent wants and would surface stale data. Pass
 * `status: '*'` (or `MO_STATUS_ANY`) to disable the default filter and
 * see every row regardless of status. The empty string `''` is treated
 * as "skip this filter" (same as omitting the key).
 *
 * Returns a list of associative rows. The `data` column is left as the
 * raw JSON string — callers decode via `mo_decode_data` only for the
 * rows they care about (saves cycles on large lists).
 *
 * @param array<string, scalar> $filters
 * @return list<array<string, mixed>>
 */
function mo_select(string $entity, array $filters = [], int $limit = 100, int $offset = 0): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $table = mo_table($entity);
    [$where, $params] = mo_build_where($filters);
    $sql = "SELECT * FROM `{$table}`";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    // Most Mosaic entities sort by `ordering` (varchar holding a
    // fractional index — see Common/FractionalIndex.php). LIMIT/OFFSET
    // are appended as integers via sprintf to avoid prepare()'s
    // placeholder count mismatch.
    $sql .= sprintf(' ORDER BY BINARY `ordering` ASC LIMIT %d OFFSET %d', $limit, $offset);
    /** @var mixed $prepared */
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $params === [] ? $sql : $wpdb->prepare($sql, $params);
    if (!is_string($prepared)) {
        return [];
    }
    /** @var mixed $rows */
    $rows = $wpdb->get_results($prepared, 'ARRAY_A');
    if (!is_array($rows)) {
        return [];
    }
    /** @var list<array<string, mixed>> $rows */
    return $rows;
}

/**
 * Count rows under the same filters as `mo_select`. Used by list-*
 * abilities to surface `total` alongside the paginated `items` array
 * so the agent can decide whether to keep paging.
 *
 * @param array<string, scalar> $filters
 */
function mo_count(string $entity, array $filters = []): int
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $table = mo_table($entity);
    [$where, $params] = mo_build_where($filters);
    $sql = "SELECT COUNT(*) FROM `{$table}`";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    /** @var mixed $prepared */
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $params === [] ? $sql : $wpdb->prepare($sql, $params);
    if (!is_string($prepared)) {
        return 0;
    }
    /** @var mixed $count */
    $count = $wpdb->get_var($prepared);
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Coerce a row column value to string. Centralises the `(string) ($r['X'] ?? '')`
 * pattern every list mapper used to repeat across N keys.
 *
 * @param array<string, mixed> $row
 */
function mo_str(array $row, string $key): string
{
    return is_scalar($row[$key] ?? null) ? (string) $row[$key] : '';
}

/**
 * Row mappers — one per entity, each centralised so the per-ability
 * callback stays simple and mago's cyclomatic-complexity / halstead
 * checks pass.
 */

/**
 * @param array<string, mixed> $r
 * @return array<string, string>
 */
function mo_map_theme_row(array $r): array
{
    return [
        'id' => mo_str($r, key: 'ID'),
        'name' => mo_str($r, key: 'name'),
        'version' => mo_str($r, key: 'version'),
        'revision' => mo_str($r, key: 'revision'),
        'ordering' => mo_str($r, key: 'ordering'),
        'status' => mo_str($r, key: 'status'),
        'modified_gmt' => mo_str($r, key: 'modified_gmt'),
    ];
}

/**
 * @param array<string, mixed> $r
 * @return array<string, string>
 */
function mo_map_template_row(array $r): array
{
    return [
        'id' => mo_str($r, key: 'ID'),
        'name' => mo_str($r, key: 'name'),
        'theme_id' => mo_str($r, key: 'themeID'),
        'master_id' => mo_str($r, key: 'masterID'),
        'assign' => mo_str($r, key: 'assign'),
        'path' => mo_str($r, key: 'path'),
        'ordering' => mo_str($r, key: 'ordering'),
        'status' => mo_str($r, key: 'status'),
        'modified_gmt' => mo_str($r, key: 'modified_gmt'),
    ];
}

/**
 * @param array<string, mixed> $r
 * @return array<string, string>
 */
function mo_map_component_row(array $r): array
{
    return [
        'id' => mo_str($r, key: 'ID'),
        'theme_id' => mo_str($r, key: 'themeID'),
        'name' => mo_str($r, key: 'name'),
        'path' => mo_str($r, key: 'path'),
        'parent_id' => mo_str($r, key: 'parentID'),
        'parent_type' => mo_str($r, key: 'parentType'),
        'ordering' => mo_str($r, key: 'ordering'),
        'status' => mo_str($r, key: 'status'),
    ];
}

/**
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function mo_map_collection_row(array $r): array
{
    $data = mo_decode_data($r['data'] ?? null);
    return [
        'id' => mo_str($r, key: 'ID'),
        'theme_id' => mo_str($r, key: 'themeID'),
        'parent_id' => mo_str($r, key: 'parentID'),
        'parent_type' => mo_str($r, key: 'parentType'),
        'ordering' => mo_str($r, key: 'ordering'),
        'status' => mo_str($r, key: 'status'),
        'name' => is_string($data['name'] ?? null) ? $data['name'] : '',
        'data' => $data,
    ];
}

/**
 * Compact variable summary — full `data` blob is excluded to keep list
 * responses small on Figma-imported design systems with thousands of
 * variables; only `name`, the pinned `customProperty` and the emitted CSS
 * variable name (the agent-relevant fields) are surfaced.
 *
 * @param array<string, mixed> $r
 * @return array<string, string>
 */
function mo_map_variable_row(array $r): array
{
    $data = mo_decode_data($r['data'] ?? null);
    return [
        'id' => mo_str($r, key: 'ID'),
        'theme_id' => mo_str($r, key: 'themeID'),
        'collection_id' => mo_str($r, key: 'parentID'),
        'ordering' => mo_str($r, key: 'ordering'),
        'status' => mo_str($r, key: 'status'),
        'name' => is_string($data['name'] ?? null) ? $data['name'] : '',
        'custom_property' => is_string($data['customProperty'] ?? null) ? $data['customProperty'] : '',
        'css_variable' => mo_variable_css_name($r, $data),
    ];
}

/**
 * The CSS custom property a variable actually emits. A variable either pins
 * one (`data.customProperty`) or, when that is empty, Mosaic derives it from
 * the collection / group / variable labels
 * (CollectionVariableMResourceAbstract::getCSSVariableName() →
 * CollectionVariableNameHelper::emittedCustomProperty()). Mosaic projects the
 * emitted base name — with its `--` prefix, without per-type postfixes — into
 * the `emittedName` column on every manager save
 * (CollectionVariablesMResourceManager::deriveEmittedNameFor()), so that column
 * is read first; installs without it fall back to the pinned value.
 *
 * @param array<string, mixed>    $row
 * @param array<array-key, mixed> $data
 */
function mo_variable_css_name(array $row, array $data): string
{
    $emitted = mo_str($row, key: 'emittedName');
    if ($emitted !== '') {
        return $emitted;
    }
    return is_string($data['customProperty'] ?? null) ? $data['customProperty'] : '';
}

/**
 * Full template record returned by mosaic-get-template (includes the
 * decoded `conditions` JSON; the element tree lives in a separate table).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_template(array $row): array
{
    return [
        'id' => mo_str($row, key: 'ID'),
        'name' => mo_str($row, key: 'name'),
        'theme_id' => mo_str($row, key: 'themeID'),
        'master_id' => mo_str($row, key: 'masterID'),
        'assign' => mo_str($row, key: 'assign'),
        'path' => mo_str($row, key: 'path'),
        'ordering' => mo_str($row, key: 'ordering'),
        'status' => mo_str($row, key: 'status'),
        'version' => mo_str($row, key: 'version'),
        'revision' => mo_str($row, key: 'revision'),
        'modified_gmt' => mo_str($row, key: 'modified_gmt'),
        'conditions' => mo_decode_data($row['conditions'] ?? null),
    ];
}

/**
 * Full theme record returned by mosaic-get-theme (includes the decoded
 * `data` blob — settings, metadata, internal flags).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_theme(array $row): array
{
    return [
        'id' => mo_str($row, key: 'ID'),
        'name' => mo_str($row, key: 'name'),
        'version' => mo_str($row, key: 'version'),
        'revision' => mo_str($row, key: 'revision'),
        'ordering' => mo_str($row, key: 'ordering'),
        'status' => mo_str($row, key: 'status'),
        'modified_gmt' => mo_str($row, key: 'modified_gmt'),
        'data' => mo_decode_data($row['data'] ?? null),
    ];
}

/**
 * Fetch a single Mosaic entity row by ID. Returns null when the row
 * doesn't exist. Soft-deleted rows (status = 'delete') ARE returned by
 * id; callers that want to hide them should check `$row['status']` and
 * map non-publish rows to mo_not_found themselves.
 *
 * @return array<string, mixed>|null
 */
function mo_load(string $entity, string $id): ?array
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $table = mo_table($entity);
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare("SELECT * FROM `{$table}` WHERE `ID` = %s LIMIT 1", $id);
    /** @var mixed $row */
    // @mago-expect analysis:possibly-invalid-argument
    $row = $wpdb->get_row($prepared, 'ARRAY_A');
    if (!is_array($row)) {
        return null;
    }
    /** @var array<string, mixed> $row */
    return $row;
}

/**
 * Generate a UUID v4 — Mosaic uses these for every `ID` column. Falls
 * back to wp_generate_uuid4() but wraps it so the codebase has one
 * canonical surface that we can swap if Mosaic ever moves to ULIDs.
 */
function mo_uuid(): string
{
    return (string) wp_generate_uuid4();
}

/**
 * Current GMT timestamp in MySQL DATETIME format. Mosaic stores
 * `modified_gmt` on every entity write, so every mo_create_* /
 * mo_edit_* path needs the same source.
 */
function mo_now_gmt(): string
{
    return current_time('mysql', gmt: true);
}

/**
 * Map a Mosaic documentType to the `parentType` value root nodes carry
 * (root = direct child of the document). Verified against the live
 * `wp_mosaic_nodes` table on a real install:
 *
 *   document_type | root parent_type
 *   template      | template
 *   master        | master
 *   styleGuide    | styleGuide
 *   component     | component
 *
 * Non-root nodes always carry `parentType = 'node'` regardless of
 * document type. Returns null for unknown document types.
 */
function mo_root_parent_type(string $document_type): ?string
{
    $supported = ['template', 'master', 'styleGuide', 'component'];
    return in_array($document_type, $supported, strict: true) ? $document_type : null;
}

/**
 * Generate a fractional-index ordering string that sorts between
 * `$before` and `$after`. Delegates to Mosaic's own implementation
 * (Mosaic\Common\FractionalIndex::generateKeyBetween) so our keys stay
 * compatible with Mosaic's own client — never reinvent the algorithm.
 *
 * Both bounds are optional: a null `$before` means "at the very start",
 * a null `$after` means "at the very end".
 *
 * Returns a WP_Error on Mosaic exceptions (typically: the caller passed
 * orderings that aren't actually in order, or trailing-zero keys).
 *
 * @return string|WP_Error
 */
function mo_ordering_between(?string $before, ?string $after): string|WP_Error
{
    if (!class_exists('Mosaic\\Common\\FractionalIndex')) {
        return mo_error('mo_runtime_unavailable', __(
            'Mosaic\\Common\\FractionalIndex class is missing. Mosaic Pro is not loaded.',
            domain: 'novamira-pro',
        ));
    }
    try {
        /** @var callable $cb */
        $cb = ['Mosaic\\Common\\FractionalIndex', 'generateKeyBetween'];
        return (string) call_user_func($cb, $before, $after);
    } catch (\Throwable $e) {
        return mo_error('mo_invalid_ordering', sprintf(
            /* translators: 1: previous sibling ordering, 2: next sibling ordering, 3: error message. */
            __('Could not place element between %1$s and %2$s: %3$s', domain: 'novamira-pro'),
            $before ?? '(start)',
            $after ?? '(end)',
            $e->getMessage(),
        ));
    }
}

/**
 * Compute the next ordering key for a new sibling under a given parent.
 * Reads the highest current ordering among siblings (publish status)
 * and returns the key that sorts strictly after it, so the new node
 * appears at the end of the sibling list.
 *
 * @return string|WP_Error
 */
function mo_ordering_at_end(
    string $document_type,
    string $document_id,
    string $parent_type,
    string $parent_id,
): string|WP_Error {
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT `ordering` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = %s AND `parentID` = %s AND `status` = 'publish' ORDER BY BINARY `ordering` DESC LIMIT 1",
        $document_type,
        $document_id,
        $parent_type,
        $parent_id,
    );
    /** @var mixed $last */
    // @mago-expect analysis:possibly-invalid-argument
    $last = $wpdb->get_var($prepared);
    $last_str = is_string($last) && $last !== '' ? $last : null;
    return mo_ordering_between($last_str, after: null);
}

/**
 * Look up an existing node ordering. Used by add/move to anchor the
 * new ordering relative to a sibling the caller already knows.
 *
 * @return string|null
 */
function mo_node_ordering(string $document_type, string $document_id, string $node_id): ?string
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    // Filter by status='publish' so soft-deleted nodes don't act as
    // valid parents / anchors — otherwise add-element under a deleted
    // parent silently creates an orphan that never appears in any read
    // path. Same protection for placement=before/after anchors.
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT `ordering` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `ID` = %s AND `status` = 'publish' LIMIT 1",
        $document_type,
        $document_id,
        $node_id,
    );
    /** @var mixed $ord */
    // @mago-expect analysis:possibly-invalid-argument
    $ord = $wpdb->get_var($prepared);
    return is_string($ord) && $ord !== '' ? $ord : null;
}

/**
 * Create a Mosaic template via Mosaic's own MResource framework so the
 * resulting row is wired into the design-system graph (master linkage +
 * root `template-internal` node). Without this wiring, Mosaic's
 * `AdminTemplateEditorInstance` and the front-end renderer both fail
 * with `Exception: CollectionMResource instance required` the moment
 * they try to open the template.
 *
 * Uses `FinishWizardEditorInstance` because that's the only public
 * editor instance that exposes ThemeExport variant managers — the
 * variants that carry `getNodeMResourceManager()` on the template
 * MResource (the admin variant doesn't). Mosaic's own wizard uses the
 * exact same class for the "Continue with Empty theme" → first-template
 * flow, so this is the matched code path, not a hack.
 *
 * Returns `['template_id' => $uuid]` on success. Caller is responsible
 * for any extra-column patching (e.g. `conditions` JSON) the facade
 * does not write on its own.
 *
 * @param array<string, mixed> $conditions
 * @return array{template_id: string}|WP_Error
 */
function mo_facade_create_template(
    string $theme_id,
    string $name,
    string $assign,
    string $path,
    array $conditions,
): array|WP_Error {
    // Widened class-exists guard: the facade transitively calls every
    // class below. If a future Mosaic release renames any of them, we
    // want a typed mo_runtime_unavailable up front — not a cryptic
    // wrapped TypeError from deep inside the try/catch.
    $required_classes = [
        'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance\\FinishWizardEditorInstance',
        'Mosaic\\EditorInstance\\MResources\\Node\\NodeMResourceInserterHelper',
        'Mosaic\\Common\\FractionalIndex',
        'Mosaic\\Database\\MosaicDB',
    ];
    foreach ($required_classes as $cls) {
        if (!class_exists($cls)) {
            return mo_error('mo_runtime_unavailable', sprintf(
                /* translators: %s: Mosaic PHP class FQN. */
                __(
                    'Mosaic class %s is missing, so templates cannot be created on this install. Make sure Mosaic Pro is active and open its admin once.',
                    domain: 'novamira-pro',
                ),
                $cls,
            ));
        }
    }
    // C4 audit P4: getFirstMasterMResource does NOT filter status='delete'
    // in Mosaic 1.0.3 — its parent RevisionModel reads every row regardless
    // of status. Without this pre-flight, a theme whose only master was
    // soft-deleted would silently produce templates that reference a dead
    // master. Fail loud here instead.
    $master_count = mo_count_publish_masters($theme_id);
    if ($master_count === 0) {
        return mo_error('mo_no_active_master', sprintf(
            /* translators: %s: theme UUID. */
            __(
                'Theme %s has no published master MResource. Open the Mosaic admin once to let the wizard seed the default master, then retry.',
                domain: 'novamira-pro',
            ),
            $theme_id,
        ));
    }
    // Pre-flight orphan cleanup BEFORE the try/catch so a typed
    // mo_orphan_chain_too_deep (audit round 4 D2) surfaces cleanly
    // instead of being wrapped as a generic mo_facade_failed when the
    // residual chain crashes Mosaic's deep load().
    $cleanup = mo_facade_clean_orphans($theme_id);
    if ($cleanup !== null) {
        return $cleanup;
    }
    try {
        $template_id = mo_facade_invoke_template_create($theme_id, $name, $assign, $path);
        return ['template_id' => $template_id];
    } catch (\Throwable $e) {
        return mo_error('mo_facade_failed', sprintf(
            /* translators: %s: upstream Mosaic Throwable message. */
            __('Mosaic facade create-template failed: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }
}

/**
 * Drive the Mosaic MResource framework to create a renderable template
 * + its root `template-internal` node. Isolated so the static-analyzer
 * suppression covers exactly the dynamic-dispatch surface — Mago has
 * no stubs for Mosaic's classes, so every method call here resolves to
 * `mixed` and would flood the analyzer with false positives otherwise.
 *
 * Returns the new template UUID on success, throws on failure (caller
 * wraps in WP_Error).
 *
 * @throws \Throwable
 */
function mo_facade_invoke_template_create(string $theme_id, string $name, string $assign, string $path): string
{
    // Every Mosaic class / method call goes through mo_call() — an
    // opaque call_user_func wrapper — so Mago's static analyzer cannot
    // resolve them against its stubs (which don't include Mosaic Pro
    // source). Runtime semantics are unchanged: we still instantiate,
    // method-dispatch, and static-call the same FQNs.

    // (Orphan cleanup is run by mo_facade_create_template before this
    // is called so a deep-chain residue returns mo_orphan_chain_too_deep
    // instead of getting wrapped as mo_facade_failed.)

    // String concat keeps the FQN out of Mago's static class-resolution
    // path — without it the analyzer would try to bind to a class it has
    // no stubs for and emit non-existent-class errors.
    // @mago-expect lint:no-redundant-string-concat
    $editor_fqn = 'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance' . '\\FinishWizardEditorInstance';
    // @mago-expect lint:no-redundant-string-concat
    $inserter_fqn = 'Mosaic\\EditorInstance\\MResources\\Node' . '\\NodeMResourceInserterHelper';
    $editor = mo_new($editor_fqn, $theme_id);
    mo_call([$editor, 'load']);
    /** @var mixed $master_mgr */
    $master_mgr = mo_call([$editor, 'getMasterMResourceManager']);
    /** @var mixed $master */
    $master = mo_call([$master_mgr, 'getFirstMasterMResource']);
    if ($master === null) {
        throw new \RuntimeException("Theme {$theme_id} has no master MResource");
    }
    $template_id = mo_uuid();
    /** @var mixed $tpl_mgr */
    $tpl_mgr = mo_call([$editor, 'getTemplateMResourceManager']);
    /** @var mixed $record */
    $record = mo_call(
        [$tpl_mgr, 'createRevisionRecord'],
        (object) [
            'ID' => $template_id,
            'name' => $name,
            'masterID' => mo_call([$master, 'getID']),
            'assign' => $assign,
            'path' => $path,
            'ordering' => mo_call([$tpl_mgr, 'generateLastOrderingForTemplate']),
        ],
        // Mosaic's createRevisionRecord(_, $isNew) — positional-only.
        // @mago-expect lint:literal-named-argument
        true,
    );
    /** @var mixed $factory */
    $factory = mo_call([$tpl_mgr, 'getFactory']);
    /** @var mixed $tpl_mr */
    // Mosaic's MResourceFactoryAbstract::create($record, $isNew) — positional.
    // @mago-expect lint:literal-named-argument
    $tpl_mr = mo_call([$factory, 'create'], $record, false);
    mo_call([$tpl_mgr, 'addMResource'], $tpl_mr);
    mo_call([$tpl_mr, 'initSync']);

    // Seed the required root `template-internal` node — Mosaic's editor
    // refuses to open a template that lacks it.
    mo_call(
        [$inserter_fqn, 'createNodeMResource'],
        mo_call([$tpl_mr, 'getNodeMResourceManager']),
        (object) [
            'ID' => mo_uuid(),
            'parentType' => mo_call([$tpl_mr, 'getMResourceType']),
            'parentID' => mo_call([$tpl_mr, 'getID']),
            'ordering' => mo_call([$tpl_mr, 'generateLastOrderingForNode']),
            'version' => '',
            'type' => 'template-internal',
            'data' => new \stdClass(),
        ],
    );
    mo_call([$editor, 'pushToDB']);
    return $template_id;
}

/**
 * Run `$mutate` against a freshly loaded Mosaic editor instance for a theme and
 * persist it with Mosaic's own pushToDB(), so Mosaic's validators, name
 * dedupe and `emittedName` projection all apply. Throwables become
 * `mo_facade_failed`.
 *
 * @template T
 * @param callable(object): (T|WP_Error) $mutate
 * @return T|WP_Error
 */
function mo_facade_editor_write(string $theme_id, callable $mutate): mixed
{
    return mo_run_editor_write($theme_id, $mutate, static function (object $_editor): void {});
}

/**
 * {@see mo_facade_editor_write()} for deletes: after pushToDB() it also runs
 * the editor's post-commit detach pass (DocumentInstanceAbstract::
 * onAfterCommit() → MResourcesDetacherManager::onAfterCommit()), which is how
 * Mosaic's editor rewrites references to a deleted resource across the theme.
 * The mutation must hand each deleted resource to its detacher first.
 *
 * @template T
 * @param callable(object): (T|WP_Error) $mutate
 * @return T|WP_Error
 */
function mo_facade_editor_delete(string $theme_id, callable $mutate): mixed
{
    return mo_run_editor_write($theme_id, $mutate, static function (object $editor): void {
        if (method_exists($editor, 'onAfterCommit')) {
            mo_call([$editor, 'onAfterCommit']);
        }
    });
}

/**
 * @template T
 * @param callable(object): (T|WP_Error) $mutate
 * @param callable(object): void         $after_push
 * @return T|WP_Error
 */
function mo_run_editor_write(string $theme_id, callable $mutate, callable $after_push): mixed
{
    // @mago-expect lint:no-redundant-string-concat
    $editor_fqn = 'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance' . '\\FinishWizardEditorInstance';
    if (!class_exists($editor_fqn)) {
        return mo_error('mo_runtime_unavailable', sprintf(
            /* translators: %s: Mosaic PHP class FQN. */
            __('Mosaic class %s is missing. Open the Mosaic admin once, then retry.', domain: 'novamira-pro'),
            $editor_fqn,
        ));
    }
    try {
        $editor = mo_new($editor_fqn, $theme_id);
        mo_call([$editor, 'load']);
        $result = $mutate($editor);
        if ($result instanceof WP_Error) {
            return $result;
        }
        mo_call([$editor, 'pushToDB']);
        $after_push($editor);
        return $result;
    } catch (\Throwable $e) {
        return mo_error('mo_facade_failed', sprintf(
            /* translators: %s: upstream Mosaic Throwable message. */
            __('Mosaic rejected the write: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }
}

/**
 * Create a Mosaic collection (design-token bucket) via the editor
 * MResource framework. Collections are theme-scoped; the seeded
 * "Default collection" has `parentType=''` / `parentID=''`, so we
 * follow that pattern. Mirrors {@see mo_facade_create_template()}.
 *
 * @return string|WP_Error
 */
function mo_facade_create_collection(string $theme_id, string $name): string|WP_Error
{
    $required_classes = [
        'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance\\FinishWizardEditorInstance',
        'Mosaic\\EditorInstance\\MResources\\Collections\\CollectionsMResourceManager',
    ];
    foreach ($required_classes as $cls) {
        if (!class_exists($cls)) {
            return mo_error('mo_runtime_unavailable', sprintf(
                /* translators: %s: Mosaic PHP class FQN. */
                __(
                    'Mosaic class %s is missing, so collections cannot be created on this install.',
                    domain: 'novamira-pro',
                ),
                $cls,
            ));
        }
    }
    try {
        return mo_facade_invoke_collection_create($theme_id, $name);
    } catch (\Throwable $e) {
        return mo_error('mo_facade_failed', sprintf(
            /* translators: %s: upstream Mosaic Throwable message. */
            __('Mosaic facade create-collection failed: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }
}

/**
 * @throws \Throwable
 */
function mo_facade_invoke_collection_create(string $theme_id, string $name): string
{
    // @mago-expect lint:no-redundant-string-concat
    $editor_fqn = 'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance' . '\\FinishWizardEditorInstance';
    $editor = mo_new($editor_fqn, $theme_id);
    mo_call([$editor, 'load']);
    /** @var mixed $mgr */
    // Mosaic's getMResourceManager(string $type) — positional.
    // @mago-expect lint:literal-named-argument
    $mgr = mo_call([$editor, 'getMResourceManager'], 'collection');
    if ($mgr === null) {
        throw new \RuntimeException("Theme {$theme_id} does not expose a collection MResource manager");
    }
    $id = mo_uuid();
    mo_call([$mgr, 'restoreCollectionMResource'], (object) [
        'ID' => $id,
        'data' => (object) ['name' => $name],
    ]);
    mo_call([$editor, 'pushToDB']);
    return $id;
}

/**
 * Create a Mosaic collection variable (design token) under an existing
 * collection. Routes through `CollectionVariablesMResourceManager::
 * restoreCollectionVariableMResource`, which dispatches to the sub-
 * factory matching `data.type` (color → ColorCollectionVariableSubFactory,
 * etc.) and auto-populates `skinsData` with the default skin × mode
 * value Mosaic chose for that type.
 *
 * This facade only creates the row: the default per-type value is
 * whatever Mosaic seeds (e.g. `#FFF` for color). Editing the per-skin /
 * per-mode value walks the `skinsData[<skin-uuid>][<mode-uuid>]` graph —
 * use `mosaic-set-variable-value` for `color` / `length` / `n-length`
 * types (`font` / `background` / `box-shadow` / `text-shadow` still need Mosaic's editor).
 *
 * @return string|WP_Error
 */
function mo_facade_create_variable(
    string $theme_id,
    string $collection_id,
    string $name,
    string $custom_property,
    string $type,
): string|WP_Error {
    $required_classes = [
        'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance\\FinishWizardEditorInstance',
        'Mosaic\\EditorInstance\\MResources\\CollectionVariable\\CollectionVariablesMResourceManager',
    ];
    foreach ($required_classes as $cls) {
        if (!class_exists($cls)) {
            return mo_error('mo_runtime_unavailable', sprintf(
                /* translators: %s: Mosaic PHP class FQN. */
                __(
                    'Mosaic class %s is missing, so variables cannot be created on this install.',
                    domain: 'novamira-pro',
                ),
                $cls,
            ));
        }
    }
    try {
        return mo_facade_invoke_variable_create($theme_id, $collection_id, $name, $custom_property, $type);
    } catch (\Throwable $e) {
        return mo_error('mo_facade_failed', sprintf(
            /* translators: %s: upstream Mosaic Throwable message. */
            __('Mosaic facade create-variable failed: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }
}

/**
 * @throws \Throwable
 */
function mo_facade_invoke_variable_create(
    string $theme_id,
    string $collection_id,
    string $name,
    string $custom_property,
    string $type,
): string {
    // @mago-expect lint:no-redundant-string-concat
    $editor_fqn = 'Mosaic\\Admin\\Ajax\\Library\\Wizard\\EditorInstance' . '\\FinishWizardEditorInstance';
    $editor = mo_new($editor_fqn, $theme_id);
    mo_call([$editor, 'load']);
    /** @var mixed $mgr */
    // Mosaic's getMResourceManager(string $type) — positional.
    // @mago-expect lint:literal-named-argument
    $mgr = mo_call([$editor, 'getMResourceManager'], 'collectionVariable');
    if ($mgr === null) {
        throw new \RuntimeException("Theme {$theme_id} does not expose a collectionVariable MResource manager");
    }
    $id = mo_uuid();
    /** @var mixed $mr */
    $mr = mo_call([$mgr, 'restoreCollectionVariableMResource'], (object) [
        'ID' => $id,
        'parentType' => 'collection',
        'parentID' => $collection_id,
        'data' => (object) [
            'type' => $type,
            'name' => $name,
            'customProperty' => $custom_property,
            'modesData' => new \stdClass(),
            'skinsData' => new \stdClass(),
            'propertyGroups' => [],
        ],
    ]);
    // The facade swallows exceptions and returns null on parent-collection
    // miss or sub-factory rejection. Catch the silent failure here so the
    // ability layer surfaces a typed error instead of a phantom-success row.
    if ($mr === null) {
        throw new \RuntimeException(sprintf(
            "Mosaic refused to create variable under collection %s — collection missing, deleted, or variable type '%s' unsupported",
            $collection_id,
            $type,
        ));
    }
    mo_call([$editor, 'pushToDB']);
    return $id;
}

/**
 * Count `status='publish'` master rows for a theme. Used by the facade
 * to assert the theme has at least one live master before invoking
 * FinishWizardEditorInstance — Mosaic's `getFirstMasterMResource`
 * happily returns a soft-deleted master in 1.0.3 (its RevisionModel's
 * read query has no status filter), so this is the gate.
 */
function mo_count_publish_masters(string $theme_id): int
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('masters');
    // @mago-expect analysis:possibly-invalid-argument
    $sql = (string) $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$table}` WHERE `themeID` = %s AND `status` = 'publish'",
        $theme_id,
    );
    /** @var mixed $count */
    $count = $wpdb->get_var($sql);
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Pre-flight cleanup before a facade-driven write: hard-delete every
 * orphan node row that would crash Mosaic's editor `load()`.
 *
 * Two failure modes drive this:
 *  - A node with `parentType='node'` whose `parentID` is no longer in
 *    the table (rare, but happens when a soft-delete cascade was
 *    aborted partway).
 *  - A `status='publish'` child whose parent is `status='delete'`
 *    (the publish-only filter on the read path doesn't help here —
 *    the editor's tree-graph loader scans EVERY publish row and
 *    blows up when its parent isn't loaded).
 *
 * Mosaic's own admin would normally heal these on next open, but the
 * editor's heal-loop runs AFTER the failing init step. Cleaning them
 * out before we hand the editor instance to Mosaic keeps the facade
 * deterministic.
 *
 * Scoped by theme so we don't touch other sites' data.
 *
 * Returns null on success, or a typed WP_Error with code
 * `mo_orphan_chain_too_deep` when the cascade hit its sanity cap with
 * residual orphans still present (audit round 4 D2). Callers MUST
 * propagate the error — invoking the Mosaic facade past this point
 * would crash in deep stack with a cryptic message.
 */
function mo_facade_clean_orphans(string $theme_id): ?WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $nodes = mo_table('nodes');
    // Pattern 1a: dangling node-children (parent node row gone entirely).
    // @mago-expect analysis:possibly-invalid-argument
    $sql1a = (string) $wpdb->prepare(
        "DELETE FROM `{$nodes}` WHERE `themeID` = %s AND `parentType` = 'node' AND `parentID` NOT IN (SELECT * FROM (SELECT `ID` FROM `{$nodes}`) AS s)",
        $theme_id,
    );
    $wpdb->query($sql1a);
    // Pattern 1b: nodes rooted directly under a document whose row no
    // longer exists in the corresponding document table. This happens when
    // a template/master/component was hard-deleted but its nodes were not
    // (e.g. external SQL cleanup, partial-rollback bug). Mosaic's loader
    // walks every node in the theme during load() — these dangling roots
    // raise the same "ToParentConnector does not exists!" crash that the
    // node-level cleanup is meant to prevent.
    $doc_map = [
        'template' => mo_table('templates'),
        'master' => mo_table('masters'),
        'component' => mo_table('components'),
        'styleGuide' => mo_table('styleguides'),
    ];
    foreach ($doc_map as $doc_type => $doc_table) {
        // @mago-expect analysis:possibly-invalid-argument
        $sql_rooted = (string) $wpdb->prepare(
            "DELETE FROM `{$nodes}` WHERE `themeID` = %s AND `parentType` = %s AND `parentID` NOT IN (SELECT * FROM (SELECT `ID` FROM `{$doc_table}`) AS s)",
            $theme_id,
            $doc_type,
        );
        $wpdb->query($sql_rooted);
        // Also hard-delete nodes whose documentID points to a row gone
        // from the document table — these are still loaded by the editor's
        // theme-wide scan even if their parentType is `node`.
        // @mago-expect analysis:possibly-invalid-argument
        $sql_doc = (string) $wpdb->prepare(
            "DELETE FROM `{$nodes}` WHERE `themeID` = %s AND `documentType` = %s AND `documentID` NOT IN (SELECT * FROM (SELECT `ID` FROM `{$doc_table}`) AS s)",
            $theme_id,
            $doc_type,
        );
        $wpdb->query($sql_doc);
    }
    // Pattern 1c: stray direct-children of a template that aren't the
    // canonical `template-internal` canvas. Mosaic's editor expects the
    // ONLY direct child of a template document to be a `template-internal`
    // node — anything else gets `makeOrphaned()`'d during heal on next
    // editor open. We hard-delete here so the facade's load() doesn't
    // hit the stray before heal runs.
    // @mago-expect analysis:possibly-invalid-argument
    $sql_stray = (string) $wpdb->prepare(
        "DELETE FROM `{$nodes}` WHERE `themeID` = %s AND `parentType` = 'template' AND `type` <> 'template-internal'",
        $theme_id,
    );
    $wpdb->query($sql_stray);
    // Pattern 2: publish child under a deleted parent. The cascade is
    // chained — when we soft-delete a level, its publish children become
    // the next round's candidates. A 4-deep chain (A:delete -> B:publish
    // -> C:publish -> D:publish) needs three passes for everything past
    // B to flip. Loop until a pass finds nothing to flip, capped at a
    // sanity ceiling so a buggy parent-cycle would not spin forever.
    // Audit round 4 (D2) lifted the cap from 64 to 256 — real Mosaic
    // trees are 6–10 deep; 256 covers any plausible chain while still
    // bounding adversarial parent-cycle runaway.
    // @mago-expect analysis:possibly-invalid-argument
    $sql2 = (string) $wpdb->prepare(
        "UPDATE `{$nodes}` SET `status` = 'delete' WHERE `themeID` = %s AND `parentType` = 'node' AND `status` = 'publish' AND `parentID` IN (SELECT * FROM (SELECT `ID` FROM `{$nodes}` WHERE `status` = 'delete') AS s)",
        $theme_id,
    );
    for ($i = 0; $i < 256; $i++) {
        $affected = $wpdb->query($sql2);
        if (!is_int($affected) || $affected === 0) {
            // Pattern 2 settled — fall through to pattern 3 (design-system
            // orphans) before declaring the cleanup complete.
            mo_facade_clean_design_system_orphans($theme_id);
            return null;
        }
    }
    // Loop hit the cap. The cleanup did its best but residual
    // publish-under-delete nodes remain — if the caller is about to invoke
    // a Mosaic facade that walks all publish nodes, those would still
    // crash the load with "ToParentConnector does not exists!". Surface
    // a typed error so the caller can short-circuit cleanly instead of
    // letting Mosaic's deep stack throw a cryptic message that gets
    // wrapped into a generic mo_facade_failed.
    // @mago-expect analysis:possibly-invalid-argument
    $count_sql = (string) $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$nodes}` WHERE `themeID` = %s AND `parentType` = 'node' AND `status` = 'publish' AND `parentID` IN (SELECT * FROM (SELECT `ID` FROM `{$nodes}` WHERE `status` = 'delete') AS s)",
        $theme_id,
    );
    $remaining = (int) $wpdb->get_var($count_sql);
    if ($remaining > 0) {
        return mo_error('mo_orphan_chain_too_deep', sprintf(
            /* translators: 1: count of remaining orphans, 2: cap. */
            __(
                'Mosaic theme has %1$d publish-under-delete nodes after %2$d cascade passes — chain depth exceeds the cleanup cap. Resolve manually before retrying.',
                domain: 'novamira-pro',
            ),
            $remaining,
            256,
        ));
    }
    mo_facade_clean_design_system_orphans($theme_id);
    return null;
}

/**
 * Pattern 3 of the facade cleanup: hard-delete dangling design-system
 * child rows whose `parentID` no longer resolves. Sprint 4 CRUD on
 * collections / variables / classes can leave orphan
 * `collection_modes` (auto-seeded under each collection) and
 * `collection_skins` rows when the parent collection is hard-deleted
 * out of band; Mosaic's editor load syncs every MResource and throws
 * "CollectionMResource instance required" or
 * "CollectionSkinMResource::getCollectionMResource return value must
 * be of type..." on the first orphan. Same for template_assigns
 * pointing at hard-deleted templates.
 *
 * Extracted from {@see mo_facade_clean_orphans()} so the outer function
 * stays under mago's halstead-effort ceiling.
 */
function mo_facade_clean_design_system_orphans(string $theme_id): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $cascade_map = [
        ['collection_modes',     mo_table('collections')],
        ['collection_skins',     mo_table('collections')],
        ['collection_variables', mo_table('collections')],
        ['template_assigns',     mo_table('templates')],
    ];
    foreach ($cascade_map as [$child_entity, $parent_table]) {
        $child_table = mo_table($child_entity);
        // @mago-expect analysis:possibly-invalid-argument
        $sql = (string) $wpdb->prepare(
            "DELETE FROM `{$child_table}` WHERE `themeID` = %s AND `parentID` != '' AND `parentID` NOT IN (SELECT * FROM (SELECT `ID` FROM `{$parent_table}`) AS s)",
            $theme_id,
        );
        $wpdb->query($sql);
    }
}

/**
 * Opaque call_user_func wrapper that hides Mosaic's class hierarchy
 * from the static analyzer. Mago resolves the return as `mixed`, which
 * is exactly what we want here — we already class-existence-guard at
 * the entry point, and runtime errors get caught + wrapped in WP_Error.
 *
 * @param array<int, mixed> $callable [class-string|object, method-name]
 */
function mo_call(array $callable, mixed ...$args): mixed
{
    /** @var callable $cb */
    $cb = $callable;
    return call_user_func($cb, ...$args);
}

/**
 * Opaque `new` for a runtime-resolved class name. Mirrors mo_call() so
 * the same analyzer-blinding indirection applies to instantiation, not
 * just method dispatch.
 */
function mo_new(string $class, mixed ...$args): object
{
    // @mago-expect analysis:unknown-class-instantiation
    return new $class(...$args);
}

/**
 * MySQL GET_LOCK wrapper for read-modify-write paths on the shared
 * `wp_mosaic_nodes` / `wp_mosaic_templates` tables. Multisite-scoped
 * via `get_current_blog_id()` so two sites on the same DB do not
 * collide. Returns whatever the callback returns; returns a typed
 * WP_Error if the lock could not be acquired within `$timeout` seconds.
 *
 * @template T
 * @param callable(): (T|WP_Error) $cb
 * @return T|WP_Error
 */
function mo_with_lock(string $key, callable $cb, int $timeout = 5): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $blog = (int) get_current_blog_id();
    // MySQL GET_LOCK names are capped at 64 chars since 5.7.5 — UUIDs
    // alone already push us past the limit when combined with the
    // human prefix, so we hash the (blog, key) pair to a fixed 40-char
    // sha1 and reattach a short namespace tag for log readability.
    $lock = 'novamira-mo:' . sha1("blog={$blog}|key={$key}");
    // @mago-expect analysis:possibly-invalid-argument
    $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, $timeout));
    if ((string) $got !== '1') {
        return mo_error('mo_lock_conflict', sprintf(
            /* translators: 1: lock scope key, 2: timeout in seconds. */
            __('Could not acquire Mosaic write lock for %s within %d seconds.', domain: 'novamira-pro'),
            $key,
            $timeout,
        ));
    }
    try {
        // @mago-expect lint:inline-variable-return
        $result = $cb();
        return $result;
    } finally {
        // @mago-expect analysis:possibly-invalid-argument
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

/**
 * Present a single node row as the compact shape returned by
 * mosaic-get-element-tree / mosaic-add-element / mosaic-edit-element.
 * The `data` blob is decoded — agents reading the tree expect the
 * element settings inline.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_node(array $row): array
{
    return [
        'id' => mo_str($row, key: 'ID'),
        'type' => mo_str($row, key: 'type'),
        'theme_id' => mo_str($row, key: 'themeID'),
        'document_type' => mo_str($row, key: 'documentType'),
        'document_id' => mo_str($row, key: 'documentID'),
        'parent_type' => mo_str($row, key: 'parentType'),
        'parent_id' => mo_str($row, key: 'parentID'),
        'ordering' => mo_str($row, key: 'ordering'),
        'status' => mo_str($row, key: 'status'),
        'data' => mo_decode_data($row['data'] ?? null),
    ];
}

/**
 * Compact template-assign mapper. Each row binds a content-type
 * identifier (e.g. `singularPost/post`) to a template within a theme.
 *
 * @param array<string, mixed> $row
 * @return array<string, string>
 */
function mo_map_template_assign_row(array $row): array
{
    return [
        'theme_id' => mo_str($row, key: 'themeID'),
        'type' => mo_str($row, key: 'type'),
        'type_identifier' => mo_str($row, key: 'typeIdentifier'),
        'template_id' => mo_str($row, key: 'parentID'),
        'parent_type' => mo_str($row, key: 'parentType'),
    ];
}
