<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use NF_Abstracts_Model;
use NF_Database_Models_Submission;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Ninja Forms specialization.
 *
 * The integration drives Ninja Forms through its PHP model layer
 * (`Ninja_Forms()->form()`, the `nf3_*` tables, the `nf_sub` CPT) — it does
 * NOT depend on the native `ninjaforms/*` abilities. Helpers here cover the
 * cross-ability concerns: error builders, id/string coercion, entity
 * resolution (form / field / action / submission), the form-cache bust, the
 * truncation contract, and Ninja's string-boolean coercion.
 */

// ---------------------------------------------------------------------------
// Error builders
// ---------------------------------------------------------------------------

/**
 * Builds the canonical Novamira error payload used by every ability.
 */
function ninja_forms_error(string $code, string $message): WP_Error
{
    return new WP_Error($code, $message);
}

/**
 * Standard "invalid input" 400. Issued when a caller-supplied value fails
 * validation before any model work happens.
 */
function ninja_forms_invalid(string $message): WP_Error
{
    return new WP_Error('ninja_forms_invalid_input', $message, ['status' => 400]);
}

/**
 * Standard "entity not found" 404. `$entity` is the noun (form / field /
 * action / submission); `$id` is echoed for the agent.
 */
function ninja_forms_not_found(string $entity, mixed $id): WP_Error
{
    return new WP_Error(
        sprintf('ninja_forms_%s_not_found', $entity),
        sprintf("Ninja Forms %s '%s' was not found.", $entity, is_scalar($id) ? (string) $id : 'unknown'),
        ['status' => 404],
    );
}

/**
 * Standard "Ninja Forms is not active / below minimum" 400.
 */
function ninja_forms_not_active(): WP_Error
{
    return new WP_Error(
        'ninja_forms_not_active',
        sprintf('Ninja Forms %s or newer must be active on this site.', \NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION),
        ['status' => 400],
    );
}

// ---------------------------------------------------------------------------
// Capability gate
// ---------------------------------------------------------------------------

/**
 * Per-ability capability guard. Ninja Forms gates form / field / action /
 * submission management on `manage_options` throughout (always wrapped in an
 * overridable filter); we mirror that. Returns null when the current user
 * passes, else a 403 WP_Error. Mutation abilities call this AFTER the
 * runtime check so an inactive install surfaces `ninja_forms_not_active`
 * first.
 */
function ninja_forms_capability_or_error(): ?WP_Error
{
    if (current_user_can('manage_options')) {
        return null;
    }
    return new WP_Error('ninja_forms_forbidden', "You don't have permission to manage Ninja Forms on this site.", [
        'status' => 403,
    ]);
}

// ---------------------------------------------------------------------------
// Input coercion
// ---------------------------------------------------------------------------

/**
 * Coerce a mixed value to a positive integer (>= 1). Returns 0 when the
 * input is not numeric or out of range. The float boundary guard prevents a
 * value beyond PHP_INT_MAX from casting lossily.
 */
function ninja_forms_pos_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 1.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Coerce a mixed value to a non-negative integer (>= 0). Same boundary guard
 * as `ninja_forms_pos_int` but accepts 0 as a valid lower bound.
 */
function ninja_forms_non_neg_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 0.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Coerce a mixed value to a UTF-8 string. Returns '' when the input is not a
 * scalar. Used to defensively read Ninja Forms model settings that may
 * surface as null on stale rows.
 */
function ninja_forms_str(mixed $raw): string
{
    if (is_string($raw)) {
        return $raw;
    }
    if (is_int($raw) || is_float($raw)) {
        return (string) $raw;
    }
    return '';
}

// ---------------------------------------------------------------------------
// Truncation contract + byte caps
// ---------------------------------------------------------------------------

/**
 * Default per-field byte cap for the large text properties (submission
 * field values, `mail_config.body`, `notifications[].body`). 8 KiB matches
 * the CF7 + WPForms readers so consumers can rely on a uniform truncation
 * contract regardless of the source plugin.
 */
function ninja_forms_default_max_field_bytes(): int
{
    return 8192;
}

/**
 * The hard ceiling for `max_field_bytes` regardless of caller intent — keeps
 * a single ability call from blowing past the MCP envelope.
 */
function ninja_forms_absolute_max_field_bytes(): int
{
    return 65_536;
}

/**
 * Truncates a string at a byte cap and tags the result. UTF-8-safe via
 * `mb_strcut`; returns `{value, truncated, original_bytes}` so cross-plugin
 * canonical consumers can rely on the same envelope shape regardless of
 * source.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function ninja_forms_truncate_field(string $value, int $max_bytes): array
{
    $original = strlen($value);
    if ($max_bytes <= 0 || $original <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'original_bytes' => $original];
    }
    $cut = mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8');
    return [
        'value' => $cut . "\n…[truncated]",
        'truncated' => true,
        'original_bytes' => $original,
    ];
}

/**
 * Resolves the effective `max_field_bytes` for an ability from its input.
 * Centralises the truncation-budget contract so every reader (canonical,
 * get-submission, …) clamps identically:
 *  - key absent  → the 8 KiB default.
 *  - explicit 0  → 0 (truncation disabled — full-fidelity read).
 *  - otherwise   → clamped to [1, 64 KiB] so a caller-supplied giant value
 *                  can never blow the MCP envelope.
 *
 * @param array<string, mixed> $input
 */
function ninja_forms_resolve_max_field_bytes(array $input, string $key = 'max_field_bytes'): int
{
    if (!isset($input[$key])) {
        return ninja_forms_default_max_field_bytes();
    }
    $value = ninja_forms_non_neg_int($input[$key]);
    if ($value === 0) {
        return 0;
    }
    return min(ninja_forms_absolute_max_field_bytes(), max(1, $value));
}

/**
 * Boolean coercion that handles Ninja Forms' string-encoded booleans
 * (`"1"` / `""` / `"0"`). Ninja stores field flags as strings — naive PHP
 * casts produce wrong results for `"0"`.
 */
function ninja_forms_truthy(mixed $value): bool
{
    if (in_array($value, [null, false, 0, 0.0, '', '0', 'false', 'False', 'FALSE'], strict: true)) {
        return false;
    }
    return true;
}

/**
 * Whether two setting values are equal for the purpose of the edit "changed"
 * diff. Ninja stores every setting in a string meta column, so an int/bool we
 * write (`required => 1`, `active => 0`) reads back as the string `"1"`/`"0"`.
 * A strict `===` would then flag an unchanged numeric/boolean setting as
 * changed on every re-run (breaking idempotency and firing a needless save).
 * Compare scalars by their string form; fall back to strict `===` for arrays /
 * objects where structural identity is what matters.
 */
function ninja_forms_setting_equal(mixed $a, mixed $b): bool
{
    if (is_scalar($a) && is_scalar($b)) {
        return (string) $a === (string) $b;
    }
    return $a === $b;
}

// ---------------------------------------------------------------------------
// Storage / counting
// ---------------------------------------------------------------------------

/**
 * Counts every Ninja Forms form on the install via the NF3 storage table.
 * Returns 0 when the table is missing (older 3.x installs / fresh
 * activations before the upgrader has run).
 */
function ninja_forms_count_forms(): int
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if (!ninja_forms_storage_ready()) {
        return 0;
    }
    $table = $wpdb->prefix . 'nf3_forms';
    // The table name is built from `$wpdb->prefix` + a literal — safe to
    // interpolate; %i would require WP 6.2+ which is above the plugin floor.
    /** @var mixed $count */
    $count = $wpdb->get_var('SELECT COUNT(*) FROM ' . $table);
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Whether a row with `$id` exists in one of the `nf3_*` tables, optionally
 * scoped to a `parent_id` (the owning form). Used by the entity-resolution
 * helpers so a 404 surfaces before any model load. `$table_suffix` is a
 * trusted literal (`nf3_forms` / `nf3_fields` / `nf3_actions`).
 */
function ninja_forms_row_exists(string $table_suffix, int $id, ?int $parent_id = null): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if ($id <= 0) {
        return false;
    }
    // `$id` / `$parent_id` are typed int (injection-safe to interpolate) and
    // `$table` is `$wpdb->prefix` + a trusted literal suffix — no caller
    // string reaches the query, so we skip `prepare()` (which rejects the
    // non-literal table name) and mirror the unprepared pattern used by
    // `ninja_forms_count_forms()`.
    $table = $wpdb->prefix . $table_suffix;
    $sql = 'SELECT id FROM ' . $table . ' WHERE id = ' . $id;
    if ($parent_id !== null) {
        $sql .= ' AND parent_id = ' . $parent_id;
    }
    /** @var mixed $found */
    $found = $wpdb->get_var($sql);
    return $found !== null;
}

// ---------------------------------------------------------------------------
// Entity resolution (model layer)
// ---------------------------------------------------------------------------

/**
 * Resolve a form id to its loaded model, or a WP_Error. Rejects non-positive
 * ids and ids that don't exist in `nf3_forms`. The returned model exposes
 * `get_settings()` / `update_settings()` / `save()` / `delete()`.
 */
function ninja_forms_form_or_error(mixed $raw): NF_Abstracts_Model|WP_Error
{
    $id = ninja_forms_pos_int($raw);
    if ($id <= 0) {
        return ninja_forms_invalid('form_id must be a positive integer.');
    }
    if (!ninja_forms_row_exists('nf3_forms', $id)) {
        return ninja_forms_not_found('form', $id);
    }
    return \Ninja_Forms()->form($id)->get();
}

/**
 * Resolve a field id (scoped to a form) to its loaded model, or a WP_Error.
 * The field must belong to `$form_id` (parent_id match) — a field id from a
 * different form returns 404 rather than silently editing the wrong form.
 */
function ninja_forms_field_or_error(int $form_id, mixed $raw): NF_Abstracts_Model|WP_Error
{
    $id = ninja_forms_pos_int($raw);
    if ($id <= 0) {
        return ninja_forms_invalid('field_id must be a positive integer.');
    }
    if (!ninja_forms_row_exists('nf3_fields', $id, $form_id)) {
        return ninja_forms_not_found('field', $id);
    }
    return \Ninja_Forms()->form($form_id)->get_field($id);
}

/**
 * Resolve an action id (scoped to a form) to its loaded model, or a
 * WP_Error. Same parent_id scoping as fields.
 */
function ninja_forms_action_or_error(int $form_id, mixed $raw): NF_Abstracts_Model|WP_Error
{
    $id = ninja_forms_pos_int($raw);
    if ($id <= 0) {
        return ninja_forms_invalid('action_id must be a positive integer.');
    }
    if (!ninja_forms_row_exists('nf3_actions', $id, $form_id)) {
        return ninja_forms_not_found('action', $id);
    }
    return \Ninja_Forms()->form($form_id)->get_action($id);
}

/**
 * Resolve a submission id to its model, or a WP_Error. Submissions are the
 * `nf_sub` CPT, so existence is a `get_post()` check with a post-type guard
 * (rejects trashed / wrong-type ids). `$expected_form_id`, when > 0, asserts
 * the submission belongs to that form via the `_form_id` postmeta.
 */
function ninja_forms_submission_or_error(mixed $raw, int $expected_form_id = 0): NF_Database_Models_Submission|WP_Error
{
    $id = ninja_forms_pos_int($raw);
    if ($id <= 0) {
        return ninja_forms_invalid('submission_id must be a positive integer.');
    }
    // @mago-expect analysis:mixed-assignment
    $post = get_post($id);
    if (!$post instanceof \WP_Post || $post->post_type !== 'nf_sub') {
        return ninja_forms_not_found('submission', $id);
    }
    if ($expected_form_id > 0) {
        $form_id = ninja_forms_pos_int(get_post_meta($id, key: '_form_id', single: true));
        if ($form_id !== $expected_form_id) {
            return ninja_forms_not_found('submission', $id);
        }
    }
    return new NF_Database_Models_Submission($id);
}

// ---------------------------------------------------------------------------
// Cache
// ---------------------------------------------------------------------------

/**
 * Invalidate the Ninja Forms form cache after a structural write (form /
 * field / action create / edit). Ninja caches a form in the `nf3_upgrades`
 * table (primary) with a `nf_form_<id>` option fallback — NOT a transient.
 * When `WPN_Helper::use_cache()` is true, `get_settings()` / `get_fields()`
 * read that cache; a write that bypasses it reads stale on the next call.
 *
 * Delete-only by design: `NF_Abstracts_Model::get_settings()` lazy-loads
 * directly from the `nf3_*` tables when the cache is cold (Model.php:287),
 * so the next read rebuilds fresh from the DB. We deliberately do NOT call
 * `build_nf_cache()` — eager rebuild snapshots the DB at rebuild time
 * (last-rebuild-wins, decoupled from last-write-wins) and would resurrect a
 * phantom cache row if the form was concurrently deleted. Mirrors NF's own
 * `Form::delete()` which deletes the cache and lets the next get lazy-load.
 *
 * Callers MUST commit their `model->save()` BEFORE calling this (bust after
 * write, never before). Safe to call repeatedly; a no-op on an invalid id.
 */
function ninja_forms_bust_cache(int $form_id): void
{
    if ($form_id <= 0 || !class_exists('WPN_Helper')) {
        return;
    }
    \WPN_Helper::delete_nf_cache($form_id);
}

/**
 * Sets the `nf3_forms.key` slug column for a form to a readable, collision-free
 * value derived from `$title` + the form id. Ninja does NOT auto-generate this
 * column on a model insert OR on `Form::duplicate()`, and the form model routes
 * a `key` setting to meta rather than the column — so write the column directly.
 * The slug is the form's stable handle (get_by_key, exports, the canonical
 * reader's `slug`). No-op on a non-positive id.
 */
function ninja_forms_set_form_key(int $form_id, string $title): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if ($form_id <= 0) {
        return;
    }
    $base = sanitize_title($title);
    if ($base === '') {
        $base = 'form';
    }
    $wpdb->update($wpdb->prefix . 'nf3_forms', ['key' => $base . '-' . $form_id], ['id' => $form_id]);
}

/**
 * Reads the physical `nf3_forms` columns that the form model's
 * `get_settings()` does NOT surface. Ninja's `NF_Database_Models_Form`
 * declares only a subset of columns in its `$_columns` property
 * (`title, created_at, …`) — `key` and `updated_at` are real columns but
 * absent from `$_columns` AND not stored as meta, so `get_settings()` never
 * returns them. Read them straight from the table instead. `$form_id` is a
 * typed int (injection-safe interpolation); the table is `$wpdb->prefix` +
 * a literal.
 *
 * @return array{title: string, key: string, created_at: ?string, updated_at: ?string}
 */
function ninja_forms_form_columns(int $form_id): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $empty = ['title' => '', 'key' => '', 'created_at' => null, 'updated_at' => null];
    if ($form_id <= 0) {
        return $empty;
    }
    $table = $wpdb->prefix . 'nf3_forms';
    /** @var mixed $row */
    $row = $wpdb->get_row(
        'SELECT title, `key`, created_at, updated_at FROM ' . $table . ' WHERE id = ' . $form_id,
        'ARRAY_A',
    );
    if (!is_array($row)) {
        return $empty;
    }
    return [
        'title' => ninja_forms_str($row['title'] ?? ''),
        'key' => ninja_forms_str($row['key'] ?? ''),
        'created_at' => isset($row['created_at']) && is_string($row['created_at']) ? $row['created_at'] : null,
        'updated_at' => isset($row['updated_at']) && is_string($row['updated_at']) ? $row['updated_at'] : null,
    ];
}

/**
 * Builds a `form_id => count` map of child rows (fields or actions) for the
 * given form ids in a single GROUP BY query — avoids the N+1 of counting
 * per form. `$table_suffix` is a trusted literal; `$ids` are cast to int for
 * a safe IN list. Returns [] when no ids are given.
 *
 * @param list<int> $ids
 *
 * @return array<int, int>
 */
function ninja_forms_child_count_map(string $table_suffix, array $ids): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_map(static fn(int $id): int => $id, $ids));
    $table = $wpdb->prefix . $table_suffix;
    /** @var list<array<string, mixed>> $rows */
    $rows = $wpdb->get_results(
        'SELECT parent_id, COUNT(*) AS c FROM ' . $table . ' WHERE parent_id IN (' . $in . ') GROUP BY parent_id',
        'ARRAY_A',
    );
    $map = [];
    foreach ($rows as $row) {
        $map[(int) ($row['parent_id'] ?? 0)] = (int) ($row['c'] ?? 0);
    }
    return $map;
}

/**
 * Builds a `form_id => submission_count` map for the given form ids in one
 * GROUP BY query over the `nf_sub` CPT (joined on the `_form_id` postmeta).
 * Counts only published submissions (excludes trashed). `$ids` are cast to
 * int for a safe IN list.
 *
 * @param list<int> $ids
 *
 * @return array<int, int>
 */
function ninja_forms_submission_count_map(array $ids): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_map(static fn(int $id): int => $id, $ids));
    /** @var list<array<string, mixed>> $rows */
    $rows = $wpdb->get_results(
        'SELECT pm.meta_value AS form_id, COUNT(*) AS c'
        . ' FROM '
        . $wpdb->postmeta
        . ' pm'
        . ' INNER JOIN '
        . $wpdb->posts
        . ' p ON p.ID = pm.post_id'
        . " WHERE pm.meta_key = '_form_id' AND p.post_type = 'nf_sub' AND p.post_status = 'publish'"
        . ' AND pm.meta_value IN ('
        . $in
        . ')'
        . ' GROUP BY pm.meta_value',
        'ARRAY_A',
    );
    $map = [];
    foreach ($rows as $row) {
        $map[(int) ($row['form_id'] ?? 0)] = (int) ($row['c'] ?? 0);
    }
    return $map;
}

/**
 * The compact form row every write ability returns: id + the nf3_forms
 * columns + the live child / submission counts. Reads straight from the DB
 * (columns + GROUP BY counts), NOT from a memoized model — Ninja's factory
 * memoizes models within a request, so a write-then-read off the model can
 * be stale. Building the response from a fresh column read sidesteps that.
 *
 * @return array{id:int,title:string,key:string,fields_count:int,actions_count:int,submissions_count:int,created_at:?string,updated_at:?string}
 */
function ninja_forms_form_compact(int $form_id): array
{
    $cols = ninja_forms_form_columns($form_id);
    $field_counts = ninja_forms_child_count_map('nf3_fields', [$form_id]);
    $action_counts = ninja_forms_child_count_map('nf3_actions', [$form_id]);
    $sub_counts = ninja_forms_submission_count_map([$form_id]);
    return [
        'id' => $form_id,
        'title' => $cols['title'],
        'key' => $cols['key'],
        'fields_count' => $field_counts[$form_id] ?? 0,
        'actions_count' => $action_counts[$form_id] ?? 0,
        'submissions_count' => $sub_counts[$form_id] ?? 0,
        'created_at' => $cols['created_at'],
        'updated_at' => $cols['updated_at'],
    ];
}

// ---------------------------------------------------------------------------
// Settings sanitation (secret redaction + byte caps)
// ---------------------------------------------------------------------------

/**
 * Whether a setting key name looks like it holds a transport secret.
 * Case-insensitive substring match against the high-risk names Ninja Forms
 * action / integration add-ons use (webhook URLs with auth, API keys, SMTP
 * credentials, bearer tokens). Deliberately broad — over-redaction is the
 * safe failure mode for a surface that streams into an LLM context.
 */
function ninja_forms_name_is_sensitive(string $name): bool
{
    $needles = ['url', 'key', 'secret', 'token', 'header', 'password', 'auth', 'credential', 'webhook'];
    $lower = strtolower($name);
    foreach ($needles as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }
    return false;
}

/**
 * Redacts secret-bearing values from a settings map, RECURSIVELY. A key whose
 * name is sensitive (per `ninja_forms_name_is_sensitive`) has its value
 * replaced with `[REDACTED]` — whether that value is a non-empty string OR a
 * nested structure (an auth block / header map / credential object under a
 * sensitive parent key). Keys whose name is NOT sensitive but whose value is
 * an array are WALKED, so a secret nested under a benign parent (e.g.
 * `connection.api_key`, `transport.headers.authorization`) is still caught —
 * the previous top-level-only pass leaked those. The key is always preserved
 * so the caller SEES a secret was present without learning its value. Returns
 * the redacted map plus a `has_secrets` flag.
 *
 * Apply this to ACTION and form-level settings (which carry add-on secrets),
 * NOT to field settings (whose `key` is the field slug, not a secret).
 *
 * @param array<array-key, mixed> $settings
 *
 * @return array{settings: array<string, mixed>, has_secrets: bool}
 */
function ninja_forms_redact_settings(array $settings): array
{
    $has_secrets = false;
    $out = ninja_forms_redact_map($settings, $has_secrets);
    return ['settings' => $out, 'has_secrets' => $has_secrets];
}

/**
 * The recursive worker for `ninja_forms_redact_settings`. Walks one settings
 * level, redacting sensitive leaves and recursing into non-sensitive arrays.
 * `$has_secrets` is set by-reference so a secret found at any depth bubbles up.
 *
 * @param array<array-key, mixed> $settings
 *
 * @return array<string, mixed>
 */
// @mago-expect lint:no-boolean-flag-parameter -- by-ref accumulator, not a flag
function ninja_forms_redact_map(array $settings, bool &$has_secrets): array
{
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($settings as $key => $value) {
        $name = is_string($key) ? $key : (string) $key;
        $sensitive = ninja_forms_name_is_sensitive($name);
        // A sensitive key: redact its value wholesale — string secret OR a
        // nested credential structure. Empty values carry nothing to leak.
        if ($sensitive && (is_string($value) ? $value !== '' : $value !== null && $value !== [])) {
            $out[$name] = '[REDACTED]';
            $has_secrets = true;
            continue;
        }
        // A benign key holding a structure: recurse so secrets nested under a
        // non-sensitive parent (connection.api_key, …) are still caught.
        if (is_array($value)) {
            $out[$name] = ninja_forms_redact_map($value, $has_secrets);
            continue;
        }
        $out[$name] = $value;
    }
    return $out;
}

/**
 * Narrows a mixed value to an `array<string, mixed>` — keeps only string
 * keys so the result satisfies the model layer's `update_settings()` shape,
 * and strips the model-managed keys (`id`, `parent_id`, `created_at`,
 * `updated_at`) so a caller's `settings` cannot clobber the row identity /
 * timestamps the integration sets itself. Shared by create-form (field /
 * action settings) and edit-form (the form-settings patch).
 *
 * @return array<string, mixed>
 */
function ninja_forms_string_keyed(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $managed = ['id', 'parent_id', 'created_at', 'updated_at'];
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        // Normalise before the deny-list test so cased / whitespace variants
        // (`ID`, ` id `, `Created_At`) can't slip a managed key past the
        // filter as junk meta. The original key is preserved on legit keys.
        if (in_array(strtolower(trim($key)), $managed, strict: true)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Recursively caps every string leaf in a settings value at `$max_bytes`
 * (UTF-8-safe), so a large HTML default / help text / email body / list of
 * options can't blow the agent context. `$max_bytes <= 0` disables capping
 * (full-fidelity). Non-string leaves pass through unchanged.
 */
function ninja_forms_cap_settings_strings(mixed $value, int $max_bytes): mixed
{
    if ($max_bytes <= 0) {
        return $value;
    }
    if (is_string($value)) {
        return ninja_forms_truncate_field($value, $max_bytes)['value'];
    }
    if (is_array($value)) {
        $out = [];
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $k => $v) {
            $out[$k] = ninja_forms_cap_settings_strings($v, $max_bytes);
        }
        return $out;
    }
    return $value;
}

// ---------------------------------------------------------------------------
// Field-type registry
// ---------------------------------------------------------------------------

/**
 * The Ninja Forms field-type registry, keyed by type slug. Each value is an
 * `NF_Abstracts_Field` instance exposing `get_name()` / `get_nicename()` /
 * `get_section()`. Returns [] when Ninja Forms is not loaded. Always read at
 * runtime — add-ons register extra types via the `ninja_forms_register_fields`
 * filter, so the set is install-specific.
 *
 * @return array<string, \NF_Abstracts_Field>
 */
function ninja_forms_field_registry(): array
{
    if (!function_exists('Ninja_Forms')) {
        return [];
    }
    /** @var mixed $fields */
    $fields = \Ninja_Forms()->fields;
    if (!is_array($fields)) {
        return [];
    }
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($fields as $slug => $field) {
        if (!is_string($slug) || $slug === '' || !$field instanceof \NF_Abstracts_Field) {
            continue;
        }
        $out[$slug] = $field;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Field persistence (shared by create-form and add-field / edit-field)
// ---------------------------------------------------------------------------

/**
 * Returns a field key not already present in `$used_keys`, appending a numeric
 * suffix when the base key collides. Shared by create-form (intra-request
 * uniqueness while building a form) and add-field / edit-field (uniqueness
 * against the form's already-persisted field keys).
 *
 * @param array<string, bool> $used_keys
 */
function ninja_forms_unique_key(string $base, array $used_keys): string
{
    if (!isset($used_keys[$base])) {
        return $base;
    }
    $i = 2;
    while (isset($used_keys[$base . '_' . $i])) {
        $i++;
    }
    return $base . '_' . $i;
}

/**
 * Persists one field on a form via the model layer and returns its new id +
 * final (possibly uniquified) key. Builds the settings from the caller's extra
 * `settings` (string keys only, managed keys stripped) plus the top-level
 * type/label/key/required/order. The field factory (`->field()->get()`)
 * instantiates a fresh model each call, so unlike the form factory it is safe
 * to reuse within a request.
 *
 * @param array<array-key, mixed> $field
 * @param array<string, bool>     $used_keys
 * @param list<string>            $warnings
 *
 * @return array{id: int, key: string}
 */
function ninja_forms_persist_field(int $form_id, array $field, int $order, array $used_keys, array &$warnings): array
{
    $type = ninja_forms_str($field['type'] ?? 'textbox');
    $label = ninja_forms_str($field['label'] ?? '');
    $key = ninja_forms_str($field['key'] ?? '');
    if ($key === '') {
        $key = $label !== '' ? sanitize_key($label) : $type;
        if ($key === '') {
            $key = 'field';
        }
    }
    $key = ninja_forms_unique_key($key, $used_keys);
    $given = ninja_forms_str($field['key'] ?? '');
    if ($given !== '' && $given !== $key) {
        $warnings[] = sprintf("Field key '%s' was changed to '%s' to stay unique.", $given, $key);
    }

    $settings = ninja_forms_string_keyed($field['settings'] ?? null);
    $settings['type'] = $type;
    $settings['label'] = $label;
    $settings['key'] = $key;
    $settings['order'] = $order;
    $settings['required'] = ninja_forms_truthy($field['required'] ?? null) ? 1 : 0;

    $model = \Ninja_Forms()->form($form_id)->field()->get();
    $model->update_settings($settings);
    $model->save();

    return ['id' => (int) $model->get_id(), 'key' => $key];
}

/**
 * The set of field keys already persisted on a form, as a `key => true` map
 * ready for `ninja_forms_unique_key`. `$exclude_field_id` (> 0) drops one
 * field from the set — used by edit-field so renaming a field to its own key
 * is a no-op, not a self-collision.
 *
 * @return array<string, bool>
 */
function ninja_forms_existing_field_keys(int $form_id, int $exclude_field_id = 0): array
{
    $keys = [];
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        if ($exclude_field_id > 0 && (int) $field->get_id() === $exclude_field_id) {
            continue;
        }
        $key = ninja_forms_str($field->get_setting('key'));
        if ($key !== '') {
            $keys[$key] = true;
        }
    }
    return $keys;
}

/**
 * Whether the form already has at least one `submit` field. Used by add-field
 * to warn (not block) on a second submit, and is the basis for the
 * delete-the-only-submit guard. Reads the persisted fields.
 */
function ninja_forms_form_has_submit(int $form_id, int $exclude_field_id = 0): bool
{
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        if ($exclude_field_id > 0 && (int) $field->get_id() === $exclude_field_id) {
            continue;
        }
        if (ninja_forms_str($field->get_setting('type')) === 'submit') {
            return true;
        }
    }
    return false;
}

/**
 * The order value for a field appended at the end of a form — one past the
 * current maximum field order (1-based, matching create-form). Reads the
 * persisted fields' `order` settings.
 */
function ninja_forms_next_field_order(int $form_id): int
{
    $max = 0;
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        $order = ninja_forms_non_neg_int($field->get_setting('order'));
        if ($order > $max) {
            $max = $order;
        }
    }
    return $max + 1;
}

/**
 * Compact field row returned by the field write abilities: identity +
 * the common attributes, read FRESH from the model. No settings blob — call
 * get-form (include=["fields"]) for the full per-field settings map.
 *
 * @return array{id:int,type:string,key:string,label:string,required:bool,order:int,form_id:int}
 */
function ninja_forms_field_compact(int $form_id, int $field_id): array
{
    $field = \Ninja_Forms()->form($form_id)->get_field($field_id);
    $settings = $field->get_settings();
    return [
        'id' => $field_id,
        'type' => ninja_forms_str($settings['type'] ?? ''),
        'key' => ninja_forms_str($settings['key'] ?? ''),
        'label' => ninja_forms_str($settings['label'] ?? ''),
        'required' => ninja_forms_truthy($settings['required'] ?? null),
        'order' => ninja_forms_non_neg_int($settings['order'] ?? 0),
        'form_id' => $form_id,
    ];
}

/**
 * Resolve a field on a form from either a numeric `field_id` or a `field_key`
 * slug, to its loaded model or a WP_Error. `field_id` takes precedence when
 * both are given. A key that matches no field on the form returns the same
 * `ninja_forms_field_not_found` 404 as a bad id, so callers get a uniform
 * not-found contract. Returns `ninja_forms_invalid_input` when neither ref is
 * supplied. Reads only `field_id` / `field_key` from `$input`; other keys are
 * ignored, so the whole ability input can be passed straight through.
 *
 * @param array<string, mixed> $input
 */
function ninja_forms_resolve_field(int $form_id, array $input): NF_Abstracts_Model|WP_Error
{
    if (isset($input['field_id']) && ninja_forms_pos_int($input['field_id']) > 0) {
        return ninja_forms_field_or_error($form_id, $input['field_id']);
    }
    $key = ninja_forms_str($input['field_key'] ?? '');
    if ($key === '') {
        return ninja_forms_invalid('Either `field_id` or `field_key` is required.');
    }
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        if (ninja_forms_str($field->get_setting('key')) === $key) {
            return $field;
        }
    }
    return ninja_forms_not_found('field', $key);
}

// ---------------------------------------------------------------------------
// Action / notification persistence (shared by create-form and notifications)
// ---------------------------------------------------------------------------

/**
 * Persists one action on a form via the model layer and returns its new id.
 * Caller-supplied `settings` (string keys only, managed keys stripped) are the
 * base; type / label / active are set from the top-level descriptor. The
 * action factory (`->action()->get()`) instantiates a fresh model each call,
 * so (like fields) it is safe to reuse within a request.
 *
 * @param array<array-key, mixed> $action
 */
function ninja_forms_persist_action(int $form_id, array $action): int
{
    $settings = ninja_forms_string_keyed($action['settings'] ?? null);
    $settings['type'] = ninja_forms_str($action['type'] ?? 'email');
    $settings['label'] = ninja_forms_str($action['label'] ?? '');
    $settings['active'] = !isset($action['active']) || ninja_forms_truthy($action['active']) ? 1 : 0;

    $model = \Ninja_Forms()->form($form_id)->action()->get();
    $model->update_settings($settings);
    $model->save();

    return (int) $model->get_id();
}

/**
 * Resolve a notification (an action whose `type` is `email`) on a form to its
 * model, or a WP_Error. Enforces id + parent_id scoping and the email-type
 * gate, and — unlike wrapping `ninja_forms_action_or_error` — returns a
 * UNIFORM `ninja_forms_notification_not_found` (404) for BOTH a missing id and
 * a non-email action (redirect / success-message / save / webhook), so the
 * notification surface speaks a single not-found contract. A non-positive id
 * still returns `ninja_forms_invalid_input`.
 */
function ninja_forms_notification_or_error(int $form_id, mixed $raw): NF_Abstracts_Model|WP_Error
{
    $id = ninja_forms_pos_int($raw);
    if ($id <= 0) {
        return ninja_forms_invalid('notification_id must be a positive integer.');
    }
    if (!ninja_forms_row_exists('nf3_actions', $id, $form_id)) {
        return ninja_forms_not_found('notification', $id);
    }
    $action = \Ninja_Forms()->form($form_id)->get_action($id);
    if (ninja_forms_str($action->get_setting('type')) !== 'email') {
        return ninja_forms_not_found('notification', $id);
    }
    return $action;
}

/**
 * Compact notification row returned by the notification abilities: the email
 * action's identity + the scan-friendly recipient / subject, read FRESH from
 * the model. The full settings map (message body, headers) is intentionally
 * omitted — call get-notification for it (redacted + byte-capped).
 *
 * @return array{id:int,label:string,active:bool,to:string,subject:string,form_id:int}
 */
function ninja_forms_notification_compact(int $form_id, int $action_id): array
{
    $action = \Ninja_Forms()->form($form_id)->get_action($action_id);
    $settings = $action->get_settings();
    return [
        'id' => $action_id,
        'label' => ninja_forms_str($settings['label'] ?? ''),
        'active' => ninja_forms_truthy($settings['active'] ?? null),
        'to' => ninja_forms_str($settings['to'] ?? ''),
        'subject' => ninja_forms_str($settings['email_subject'] ?? ''),
        'form_id' => $form_id,
    ];
}

// ---------------------------------------------------------------------------
// Submissions (the `nf_sub` CPT)
// ---------------------------------------------------------------------------

/**
 * Maps the ability-level submission `status` filter to the WP post_status(es)
 * to query. Ninja submissions are `nf_sub` posts whose only status is the WP
 * post_status: `publish` (active) or `trash` (trashed). `any` returns both.
 *
 * @return string|list<string>
 */
function ninja_forms_submission_status_filter(string $status): string|array
{
    if ($status === 'trashed') {
        return 'trash';
    }
    if ($status === 'any') {
        return ['publish', 'trash'];
    }
    return 'publish';
}

/**
 * The friendly status label for a submission's WP post_status.
 */
function ninja_forms_submission_status_label(string $post_status): string
{
    if ($post_status === 'trash') {
        return 'trashed';
    }
    if ($post_status === 'publish') {
        return 'active';
    }
    return $post_status;
}

/**
 * Stringify a stored submission field value for the read response. Scalars
 * pass through; a list of scalars (checkbox / multiselect) is comma-joined;
 * anything deeper is JSON-encoded so the agent still sees the data without a
 * fatal. Returns '' for null / unstorable values.
 */
function ninja_forms_stringify_value(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }
    if (is_bool($value) || is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_array($value)) {
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                return (string) wp_json_encode($value);
            }
        }
        return implode(', ', array_map(static fn(mixed $v): string => (string) $v, $value));
    }
    return '';
}
