<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect Gravity Forms presence + version. Cached per request.
 *
 * @return array{active: bool, version: string|null}
 */
function gravityforms_detect_setup(): array
{
    /** @var array{active: bool, version: string|null}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = gravityforms_min_runtime_available();

    return $cache = [
        'active' => $active,
        'version' => $active ? \GFForms::$version : null,
    ];
}

/**
 * Standard "Gravity Forms is not active" error.
 */
function gravityforms_not_active(): WP_Error
{
    return new WP_Error(
        'gravityforms_not_active',
        'Gravity Forms is not active or does not meet the minimum supported version.',
        ['status' => 400],
    );
}

/**
 * Standard "invalid input" error wrapper with a consistent prefix.
 */
function gravityforms_invalid_input(string $message): WP_Error
{
    return new WP_Error('gravityforms_invalid_input', $message, ['status' => 400]);
}

/**
 * Check a Gravity Forms-native capability and return a 403 WP_Error when the
 * current user lacks it. Delegates to GFAPI::current_user_can_any() rather
 * than bare current_user_can() so the `gform_user_can_any` filter chain fires
 * — admins reliably pass via WordPress's manage_options superset and team /
 * multi-author setups get the documented per-form / per-entry override hook.
 *
 * Accepts both short (`view_entries`) and prefixed (`gravityforms_view_entries`)
 * cap names. The GF helper normalises internally.
 *
 * @param string|list<string> $capability
 */
function gravityforms_capability_or_error(string|array $capability): ?WP_Error
{
    $caps = is_array($capability) ? $capability : [$capability];
    if (\GFAPI::current_user_can_any($caps)) {
        return null;
    }
    $display = implode(', ', $caps);
    return new WP_Error(
        'gravityforms_capability_missing',
        sprintf("Current user lacks the required '%s' capability.", $display),
        ['status' => 403],
    );
}

/**
 * Count of registered Gravity Forms forms (active + inactive, excluding
 * trashed). Reported by check-setup so an agent immediately knows whether
 * the site has forms to discover before calling list-forms.
 *
 * Uses GFAPI::get_forms with both active flags so trashed forms are excluded
 * but inactive (draft-style) forms are included — matches the agent's mental
 * model of "forms that exist".
 */
function gravityforms_count_forms(): int
{
    if (!gravityforms_min_runtime_available()) {
        return 0;
    }
    $active_forms = \GFAPI::get_forms(active: true, trash: false);
    $inactive_forms = \GFAPI::get_forms(active: false, trash: false);
    return count($active_forms) + count($inactive_forms);
}

/**
 * Standard "form not found" error wrapper.
 */
function gravityforms_form_not_found(int $form_id): WP_Error
{
    return new WP_Error('gravityforms_form_not_found', "Gravity Forms form not found: {$form_id}", ['status' => 404]);
}

/**
 * Standard "entry not found" error wrapper.
 */
function gravityforms_entry_not_found(int $entry_id): WP_Error
{
    return new WP_Error('gravityforms_entry_not_found', "Gravity Forms entry not found: {$entry_id}", [
        'status' => 404,
    ]);
}

/**
 * Standard "operation failed" error wrapper (used when a GFAPI call returns
 * false / 0 / WP_Error for reasons we cannot diagnose).
 */
function gravityforms_operation_failed(string $message): WP_Error
{
    return new WP_Error('gravityforms_operation_failed', $message, ['status' => 500]);
}

/**
 * Fetch an active (non-trashed) Gravity Forms form by id. Rejects trashed
 * forms (use gravityforms_form_or_error_with_trash for delete / restore
 * paths that legitimately need access to the trashed state).
 *
 * @return array<string, mixed>|WP_Error
 */
function gravityforms_form_or_error(int $form_id): array|WP_Error
{
    if ($form_id <= 0) {
        return gravityforms_invalid_input('form_id must be a positive integer.');
    }
    if (!gravityforms_min_runtime_available()) {
        return gravityforms_not_active();
    }
    $form = \GFAPI::get_form($form_id);
    if ($form === false) {
        return gravityforms_form_not_found($form_id);
    }
    if ((int) ($form['is_trash'] ?? 0) === 1) {
        return new WP_Error(
            'gravityforms_form_trashed',
            "Form {$form_id} is in trash. Restore it first or use force=true on delete-form.",
            ['status' => 410],
        );
    }
    return $form;
}

/**
 * Like gravityforms_form_or_error but does NOT reject a trashed form. Used by
 * delete-form / duplicate-form-from-trash style paths where the caller has to
 * see the trashed state to make a decision.
 *
 * @return array<string, mixed>|WP_Error
 */
function gravityforms_form_or_error_with_trash(int $form_id): array|WP_Error
{
    if ($form_id <= 0) {
        return gravityforms_invalid_input('form_id must be a positive integer.');
    }
    if (!gravityforms_min_runtime_available()) {
        return gravityforms_not_active();
    }
    $form = \GFAPI::get_form($form_id);
    if ($form === false) {
        return gravityforms_form_not_found($form_id);
    }
    return $form;
}

/**
 * Compact row shape returned by list-forms, create-form, edit-form,
 * duplicate-form. Mirrors the WPForms row shape so an agent that already
 * speaks WPForms recognises the contract.
 *
 * status: 'active' when is_active=1 and not trashed, 'inactive' when
 * is_active=0 and not trashed, 'trash' when is_trash=1.
 *
 * @param array<string, mixed> $form
 * @return array{id: int, title: string, status: string, date_created: string, date_updated: string, entry_count: int}
 */
function gravityforms_form_row(array $form, ?int $entry_count = null): array
{
    $is_trash = (int) ($form['is_trash'] ?? 0) === 1;
    $is_active = (int) ($form['is_active'] ?? 1) === 1;
    $status = match (true) {
        $is_trash => 'trash',
        $is_active => 'active',
        default => 'inactive',
    };

    $form_id = (int) ($form['id'] ?? 0);
    if ($entry_count === null && $form_id > 0) {
        $entry_count = \GFAPI::count_entries($form_id);
    }

    return [
        'id' => $form_id,
        'title' => (string) ($form['title'] ?? ''),
        'status' => $status,
        'date_created' => (string) ($form['date_created'] ?? ''),
        'date_updated' => (string) ($form['date_updated'] ?? $form['date_created'] ?? ''),
        'entry_count' => (int) ($entry_count ?? 0),
    ];
}

/**
 * Batch-count entries for a list of form ids via one GROUP BY query, avoiding
 * the N+1 pattern of calling GFAPI::count_entries() in a loop. Returns a map
 * keyed by form_id; missing keys imply zero entries for that form (sparse).
 *
 * @param list<int> $form_ids
 * @return array<int, int>
 */
function gravityforms_form_entry_counts_batch(array $form_ids): array
{
    if ($form_ids === []) {
        return [];
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = \GFFormsModel::get_entry_table_name();
    if ($table === '') {
        return [];
    }
    // intval+positive-filtered up front so direct interpolation into the IN
    // list is safe; skipping $wpdb->prepare() sidesteps its literal-string
    // requirement and avoids per-cardinality SQL templating for the IN clause.
    $ids = array_values(array_filter(
        array_map(static fn($id): int => (int) $id, $form_ids),
        static fn(int $id): bool => $id > 0,
    ));
    if ($ids === []) {
        return [];
    }
    $ids_csv = implode(',', $ids);
    /** @var list<object{form_id: string|int, total: string|int}>|null $rows */
    $rows = $wpdb->get_results(
        "SELECT form_id, COUNT(*) AS total FROM {$table} WHERE form_id IN ({$ids_csv}) AND status = 'active' GROUP BY form_id",
    );
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row->form_id] = (int) $row->total;
    }
    return $out;
}

/**
 * Split a Gravity Forms form array into the four top-level sections an agent
 * consumes separately (fields, notifications, confirmations, settings). The
 * "settings" bucket collects every other top-level key — button, save,
 * pagination, schedule*, limitEntries*, requireLogin*, postCreation*, etc.
 * Bookkeeping keys (id, title, description, version, markupVersion,
 * is_active, is_trash, date_created, date_updated, nextFieldId) are stripped
 * so settings stays focused on agent-relevant configuration.
 *
 * @param array<string, mixed> $form
 * @return array{
 *     fields: list<mixed>,
 *     notifications: array<array-key, mixed>,
 *     confirmations: array<array-key, mixed>,
 *     settings: array<string, mixed>,
 * }
 */
function gravityforms_split_form_definition(array $form): array
{
    $bookkeeping = [
        'id',
        'title',
        'description',
        'version',
        'markupVersion',
        'is_active',
        'is_trash',
        'date_created',
        'date_updated',
        'nextFieldId',
        'view_count',
        'lead_count',
    ];
    $structural = ['fields', 'notifications', 'confirmations'];

    $fields = isset($form['fields']) && is_array($form['fields']) ? array_values($form['fields']) : [];
    $notifications = isset($form['notifications']) && is_array($form['notifications']) ? $form['notifications'] : [];
    $confirmations = isset($form['confirmations']) && is_array($form['confirmations']) ? $form['confirmations'] : [];

    $settings = $form;
    foreach (array_merge($bookkeeping, $structural) as $key) {
        unset($settings[$key]);
    }

    return [
        'fields' => $fields,
        'notifications' => $notifications,
        'confirmations' => $confirmations,
        'settings' => $settings,
    ];
}

/**
 * Typed 409 returned when a CAS-guarded form edit detects a concurrent write.
 * Mirrors wpforms_concurrent_modification_error in shape so REST clients can
 * uniformly handle the conflict.
 */
function gravityforms_concurrent_modification_error(int $form_id): WP_Error
{
    return new WP_Error(
        'gravityforms_concurrent_modification',
        sprintf(
            'Gravity Forms form %d was modified by another writer between read and persist — re-fetch via gravityforms-get-form and retry.',
            $form_id,
        ),
        ['status' => 409],
    );
}

/**
 * Fetch a Gravity Forms entry by id. Returns the entry array as GFAPI emits
 * it (string-keyed entry meta + numeric input-id keys for field values), or
 * a WP_Error.
 *
 * @return array<array-key, mixed>|WP_Error
 */
function gravityforms_entry_or_error(int $entry_id): array|WP_Error
{
    if ($entry_id <= 0) {
        return gravityforms_invalid_input('entry_id must be a positive integer.');
    }
    if (!gravityforms_min_runtime_available()) {
        return gravityforms_not_active();
    }
    /** @var array<array-key, mixed>|false|WP_Error $entry */
    $entry = \GFAPI::get_entry($entry_id);
    if ($entry instanceof WP_Error) {
        // GFAPI emits a generic 'not_found' code for missing entries; normalize
        // to our typed code so the caller can switch on a single value.
        if ($entry->get_error_code() === 'not_found') {
            return gravityforms_entry_not_found($entry_id);
        }
        return $entry;
    }
    if ($entry === false) {
        return gravityforms_entry_not_found($entry_id);
    }
    return $entry;
}

/**
 * Compact row format used by list-entries.
 *
 * @param array<array-key, mixed> $entry
 * @return array<string, mixed>
 */
function gravityforms_entry_row(array $entry): array
{
    return [
        'id' => (int) ($entry['id'] ?? 0),
        'form_id' => (int) ($entry['form_id'] ?? 0),
        'status' => (string) ($entry['status'] ?? 'active'),
        'date_created' => (string) ($entry['date_created'] ?? ''),
        'date_updated' => (string) ($entry['date_updated'] ?? $entry['date_created'] ?? ''),
        'is_read' => (int) ($entry['is_read'] ?? 0) === 1,
        'is_starred' => (int) ($entry['is_starred'] ?? 0) === 1,
        'ip' => (string) ($entry['ip'] ?? ''),
    ];
}

/**
 * Full entry detail (meta + per-field values) used by get-entry. GF stores
 * the field values inline on the entry array under numeric / dotted input
 * ids; this helper splits the meta columns from the field values into the
 * shape get-entry advertises.
 *
 * @param array<array-key, mixed> $entry
 * @return array<string, mixed>
 */
function gravityforms_entry_detail(array $entry): array
{
    $meta_keys = [
        'id',
        'form_id',
        'status',
        'date_created',
        'date_updated',
        'is_read',
        'is_starred',
        'ip',
        'user_agent',
        'source_url',
        'created_by',
        'currency',
        'payment_status',
        'payment_amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'transaction_type',
        'is_fulfilled',
        'post_id',
        'source_id',
        'user_uuid',
    ];
    $fields = [];
    /** @var mixed $value */
    foreach ($entry as $key => $value) {
        if (in_array((string) $key, $meta_keys, strict: true)) {
            continue;
        }
        $fields[(string) $key] = $value;
    }

    /** @var mixed $created_by_raw */
    $created_by_raw = $entry['created_by'] ?? null;
    $created_by = is_numeric($created_by_raw) ? (int) $created_by_raw : null;

    return [
        'id' => (int) ($entry['id'] ?? 0),
        'form_id' => (int) ($entry['form_id'] ?? 0),
        'status' => (string) ($entry['status'] ?? 'active'),
        'date_created' => (string) ($entry['date_created'] ?? ''),
        'date_updated' => (string) ($entry['date_updated'] ?? $entry['date_created'] ?? ''),
        'is_read' => (int) ($entry['is_read'] ?? 0) === 1,
        'is_starred' => (int) ($entry['is_starred'] ?? 0) === 1,
        'ip' => (string) ($entry['ip'] ?? ''),
        'user_agent' => (string) ($entry['user_agent'] ?? ''),
        'source_url' => (string) ($entry['source_url'] ?? ''),
        'created_by' => $created_by,
        'fields' => $fields,
    ];
}
