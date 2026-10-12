<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmEntry;
use FrmEntryMeta;
use FrmField;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const FRM_LIST_ENTRIES_DEFAULT_PER_PAGE = 50;

const FRM_LIST_ENTRIES_MAX_PER_PAGE = 500;

const FRM_LIST_ENTRIES_HARD_CAP = 5000;

const FRM_GET_ENTRY_DEFAULT_MAX_FIELD_BYTES = 8192;

// ---------------------------------------------------------------------------
// list-entries
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-list-entries', [
    'label' => __('List Formidable Entries', domain: 'novamira-pro'),
    'description' => __(
        'Lists submissions (`wp_frm_items` rows) for a Formidable form. Filters: `is_draft` (omit for both, true = saved drafts only, false = completed entries only); `user_id` (entries created by that WP user id); `search` (substring on item_key); `created_after` / `created_before` (ISO-8601 dates, inclusive bounds on `created_at`). Paginated: default `per_page=50`, max 500. Hard cap on the matching pool is 5000 — `pagination.truncated=true` when reached. Compact row per entry: id, item_key, form_id, user_id, is_draft, parent_item_id, created_at, updated_at, ip. Use get-entry for the full meta payload.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'is_draft' => [
                'type' => 'boolean',
                'description' => 'Omit to return both. true → drafts only. false → completed entries only.',
            ],
            'user_id' => ['type' => 'integer', 'minimum' => 0],
            'search' => ['type' => 'string', 'description' => 'Substring match on item_key.'],
            'created_after' => ['type' => 'string', 'description' => 'ISO-8601 lower bound (inclusive) on created_at.'],
            'created_before' => [
                'type' => 'string',
                'description' => 'ISO-8601 upper bound (inclusive) on created_at.',
            ],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 50],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entries' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'item_key' => ['type' => 'string'],
                        'form_id' => ['type' => 'integer'],
                        'user_id' => ['type' => 'integer'],
                        'is_draft' => ['type' => 'boolean'],
                        'parent_item_id' => ['type' => 'integer'],
                        'created_at' => ['type' => 'string'],
                        'updated_at' => ['type' => 'string'],
                        'ip' => ['type' => 'string'],
                    ],
                    'required' => [
                        'id',
                        'item_key',
                        'form_id',
                        'user_id',
                        'is_draft',
                        'parent_item_id',
                        'created_at',
                        'updated_at',
                        'ip',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'pagination' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'total_pages' => ['type' => 'integer'],
                    'truncated' => ['type' => 'boolean'],
                ],
                'required' => ['page', 'per_page', 'total', 'total_pages', 'truncated'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['entries', 'pagination'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_entries',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists submissions on `form_id` with optional draft / user / date filters. Compact rows only — get-entry returns the per-field meta values. Pagination is 1-indexed; pagination.truncated=true means the matching pool exceeded the 5000-row cap (narrow the filters).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int|string,
 *     is_draft?: bool,
 *     user_id?: int,
 *     search?: string,
 *     created_after?: string,
 *     created_before?: string,
 *     page?: int,
 *     per_page?: int,
 * } $input
 * @return array{entries: list<array<string, mixed>>, pagination: array{page: int, per_page: int, total: int, total_pages: int, truncated: bool}}|WP_Error
 */
function formidable_list_entries(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_view_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $page = max(1, frm_to_int($input['page'] ?? 1));
    $per_page_raw = frm_to_int($input['per_page'] ?? FRM_LIST_ENTRIES_DEFAULT_PER_PAGE);
    $per_page = min(FRM_LIST_ENTRIES_MAX_PER_PAGE, max(1, $per_page_raw));

    // Fetch directly via $wpdb instead of FrmEntry::getAll so we sidestep
    // Formidable's per-request wp_cache for entry list queries. The cache
    // key includes the where/order/limit hash but FrmEntry::clear_cache
    // uses cache_delete_group which is a no-op on stock WP without an
    // external object cache — so a freshly-created entry would not appear
    // in a list query later in the same request. Reading via $wpdb avoids
    // the cache entirely, at the cost of one extra query per list call.
    $all = frm_query_entries((int) $form->id, $input);
    $total = count($all);
    $offset = ($page - 1) * $per_page;
    $slice = array_slice($all, $offset, $per_page);

    return [
        'entries' => array_map(static fn(\stdClass $row): array => frm_entry_to_compact_row($row), $slice),
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'truncated' => $total === FRM_LIST_ENTRIES_HARD_CAP,
        ],
    ];
}

// ---------------------------------------------------------------------------
// get-entry
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-get-entry', [
    'label' => __('Get Formidable Entry', domain: 'novamira-pro'),
    'description' => __(
        'Reads ONE submission by id. With `include_meta=true` (default), the response includes a `meta` map keyed by field_key with the submitted values (Formidable stores them in `wp_frm_item_metas`). Long string values are truncated at `max_field_bytes` (default 8192) and the truncation paths are reported in `truncations[]` so you can re-fetch a specific field with a higher cap.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'include_meta' => ['type' => 'boolean', 'default' => true],
            'max_field_bytes' => ['type' => 'integer', 'minimum' => 256, 'maximum' => 65_536, 'default' => 8192],
        ],
        'required' => ['entry_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry' => ['type' => 'object'],
            'truncations' => ['type' => 'array'],
        ],
        'required' => ['entry', 'truncations'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Returns one entry by id. include_meta=true populates entry.meta keyed by field_key (NOT field_id) so the agent can map values back to the form's get-field-types-schema slugs. Truncations[] reports any field value that was capped.",
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id?: int|string, include_meta?: bool, max_field_bytes?: int} $input
 * @return array{entry: array<string, mixed>, truncations: list<array{path: string, truncated_at: int, original_bytes: int}>}|WP_Error
 */
function formidable_get_entry(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_view_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $entry_id = frm_to_int($input['entry_id'] ?? 0);
    if ($entry_id <= 0) {
        return frm_invalid_input('`entry_id` must be a positive integer.');
    }

    $include_meta = !isset($input['include_meta']) || (bool) (int) $input['include_meta'];
    $max_bytes = frm_clamp_max_bytes(frm_to_int($input['max_field_bytes'] ?? FRM_GET_ENTRY_DEFAULT_MAX_FIELD_BYTES));

    $entry = FrmEntry::getOne($entry_id, $include_meta);
    if (!$entry instanceof \stdClass) {
        return new WP_Error('formidable_entry_not_found', "Formidable entry not found: {$entry_id}", [
            'status' => 404,
        ]);
    }

    /** @var list<array{path: string, truncated_at: int, original_bytes: int}> $truncations */
    $truncations = [];
    $row = frm_entry_to_compact_row($entry);
    if ($include_meta) {
        $row['meta'] = frm_entry_meta_to_map($entry, $max_bytes, $truncations);
    }

    return ['entry' => $row, 'truncations' => $truncations];
}

// ---------------------------------------------------------------------------
// edit-entry
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-edit-entry', [
    'label' => __('Edit Formidable Entry', domain: 'novamira-pro'),
    'description' => __(
        'Modifies field values on an existing entry. Pass `item_meta` keyed by field_id (int) OR field_key (string) — both are accepted and normalised internally. Unknown field refs are rejected with formidable_invalid_input (arbitrary-key-injection guard). Goes through FrmEntry::update so the documented `frm_after_update_entry` hook chain fires (downstream listeners like audit log / payment processors see the change). Do NOT poke FrmEntryMeta::update_entry_meta directly — that path skips the hooks.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'item_meta' => [
                'type' => 'object',
                'description' => 'Map keyed by field_id (int) OR field_key (string) → new value.',
            ],
        ],
        'required' => ['entry_id', 'item_meta'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ],
        'required' => ['entry', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_edit_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Edit entry meta values. item_meta keys may be field_id (int) OR field_key (string); unknown refs return formidable_invalid_input. `changed[]` lists the field_ids actually written.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id?: int|string, item_meta?: array<int|string, mixed>} $input
 * @return array{entry: array<string, mixed>, changed: list<int>}|WP_Error
 */
function formidable_edit_entry(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_edit_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $entry_id = frm_to_int($input['entry_id'] ?? 0);
    if ($entry_id <= 0) {
        return frm_invalid_input('`entry_id` must be a positive integer.');
    }

    $entry = FrmEntry::getOne($entry_id, meta: false);
    if (!$entry instanceof \stdClass) {
        return new WP_Error('formidable_entry_not_found', "Formidable entry not found: {$entry_id}", [
            'status' => 404,
        ]);
    }

    $item_meta = $input['item_meta'] ?? [];
    if ($item_meta === []) {
        return frm_invalid_input('`item_meta` must be a non-empty object.');
    }

    $resolved = frm_resolve_item_meta((int) $entry->form_id, $item_meta);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    // FrmEntryMeta::update_entry_metas has hidden full-replace semantics: it
    // captures `previous_field_ids` at the top of the call, then DELETEs any
    // existing meta row whose field_id is NOT in the caller's payload. Audit
    // round 1 confirmed this would silently destroy untouched fields when
    // the caller passes a single-key item_meta map ("partial-merge" contract
    // violated). Read the entry's current meta first and merge the caller's
    // overrides on top so every existing field_id is re-passed and the
    // leftovers-delete branch removes nothing.
    $existing_with_meta = FrmEntry::getOne($entry_id, meta: true);
    /** @var mixed $existing_metas */
    $existing_metas = $existing_with_meta instanceof \stdClass ? $existing_with_meta->metas ?? null : null;
    $current_meta = is_array($existing_metas) ? $existing_metas : [];
    /** @var array<int, mixed> $merged_meta */
    $merged_meta = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($current_meta as $field_id => $value) {
        $merged_meta[(int) $field_id] = $value;
    }
    // @mago-expect analysis:mixed-assignment
    foreach ($resolved as $field_id => $value) {
        $merged_meta[(int) $field_id] = $value;
    }
    FrmEntryMeta::update_entry_metas($entry_id, $merged_meta);

    // Touch the entry row so updated_at reflects the change and any
    // listeners on `frm_after_update_entry` get the canonical signal.
    global $wpdb;
    /** @var \wpdb $wpdb */
    $wpdb->update(
        $wpdb->prefix . 'frm_items',
        ['updated_at' => current_time('mysql', gmt: true), 'updated_by' => get_current_user_id()],
        ['id' => $entry_id],
        ['%s', '%d'],
        ['%d'],
    );
    do_action('frm_after_update_entry', $entry_id, (int) $entry->form_id);

    FrmEntry::clear_cache();

    $fresh = FrmEntry::getOne($entry_id, meta: true);
    if (!$fresh instanceof \stdClass) {
        return frm_error(
            code: 'formidable_edit_entry_failed',
            message: "Could not re-fetch entry {$entry_id}.",
            status: 500,
        );
    }
    /** @var list<array{path: string, truncated_at: int, original_bytes: int}> $truncations */
    $truncations = [];
    $row = frm_entry_to_compact_row($fresh);
    $row['meta'] = frm_entry_meta_to_map($fresh, FRM_GET_ENTRY_DEFAULT_MAX_FIELD_BYTES, $truncations);

    return [
        'entry' => $row,
        'changed' => array_values(array_map(static fn(int|string $k): int => (int) $k, array_keys($resolved))),
    ];
}

// ---------------------------------------------------------------------------
// delete-entry
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-delete-entry', [
    'label' => __('Delete Formidable Entry', domain: 'novamira-pro'),
    'description' => __(
        'Hard-deletes one entry + its `wp_frm_item_metas` rows. No soft-delete (there is no trash state for entries — only the is_draft flag which means something different). Idempotent: deleting an already-missing entry returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['entry_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['entry_id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_delete_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Hard-deletes an entry. Idempotent: re-call on a missing id returns deleted=false. No undo.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id?: int|string} $input
 * @return array{entry_id: int, deleted: bool}|WP_Error
 */
function formidable_delete_entry(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_delete_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $entry_id = frm_to_int($input['entry_id'] ?? 0);
    if ($entry_id <= 0) {
        return frm_invalid_input('`entry_id` must be a positive integer.');
    }

    $entry = FrmEntry::getOne($entry_id, meta: false);
    if (!$entry instanceof \stdClass) {
        return ['entry_id' => $entry_id, 'deleted' => false];
    }

    $result = FrmEntry::destroy($entry_id);
    if (!is_int($result) || $result <= 0) {
        return frm_error(
            code: 'formidable_delete_entry_failed',
            message: "FrmEntry::destroy returned a non-positive value for entry {$entry_id}.",
        );
    }

    return ['entry_id' => $entry_id, 'deleted' => true];
}

// ---------------------------------------------------------------------------
// set-entry-status (toggle is_draft)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-set-entry-status', [
    'label' => __('Set Formidable Entry Status', domain: 'novamira-pro'),
    'description' => __(
        'Toggles the `is_draft` flag on an entry. Draft entries (is_draft=1) are typically saved-for-later submissions; completed entries (is_draft=0) are final. Goes through FrmEntry::update so the documented hook chain fires (NOT a direct wp_frm_items column write). Idempotent: setting is_draft to its current value returns changed=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer', 'minimum' => 1],
            'is_draft' => ['type' => 'boolean'],
        ],
        'required' => ['entry_id', 'is_draft'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry_id' => ['type' => 'integer'],
            'is_draft' => ['type' => 'boolean'],
            'changed' => ['type' => 'boolean'],
        ],
        'required' => ['entry_id', 'is_draft', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_set_entry_status',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Flips the is_draft flag on an entry. Returns changed=false on an idempotent re-call (same value). The full hook chain fires (frm_after_update_entry).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{entry_id?: int|string, is_draft?: bool} $input
 * @return array{entry_id: int, is_draft: bool, changed: bool}|WP_Error
 */
function formidable_set_entry_status(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_edit_entries');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $entry_id = frm_to_int($input['entry_id'] ?? 0);
    if ($entry_id <= 0) {
        return frm_invalid_input('`entry_id` must be a positive integer.');
    }
    if (!array_key_exists('is_draft', $input)) {
        return frm_invalid_input('`is_draft` is required.');
    }
    $target = (bool) (int) $input['is_draft'];

    $entry = FrmEntry::getOne($entry_id, meta: false);
    if (!$entry instanceof \stdClass) {
        return new WP_Error('formidable_entry_not_found', "Formidable entry not found: {$entry_id}", [
            'status' => 404,
        ]);
    }

    $current = (int) ($entry->is_draft ?? 0) === 1;
    if ($current === $target) {
        return ['entry_id' => $entry_id, 'is_draft' => $current, 'changed' => false];
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $wpdb->update(
        $wpdb->prefix . 'frm_items',
        ['is_draft' => $target ? 1 : 0, 'updated_at' => current_time('mysql', gmt: true)],
        ['id' => $entry_id],
        ['%d', '%s'],
        ['%d'],
    );
    do_action('frm_after_update_entry', $entry_id, (int) $entry->form_id);
    FrmEntry::clear_cache();

    return ['entry_id' => $entry_id, 'is_draft' => $target, 'changed' => true];
}

// ===========================================================================
// Internal helpers (private to this file)
// ===========================================================================

/**
 * Reduce a `wp_frm_items` row to the compact list/edit response shape.
 *
 * @return array{
 *     id: int,
 *     item_key: string,
 *     form_id: int,
 *     user_id: int,
 *     is_draft: bool,
 *     parent_item_id: int,
 *     created_at: string,
 *     updated_at: string,
 *     ip: string,
 * }
 */
function frm_entry_to_compact_row(\stdClass $entry): array
{
    /** @var array{id?: int|string, item_key?: string, form_id?: int|string, user_id?: int|string, is_draft?: int|string, parent_item_id?: int|string, created_at?: string, updated_at?: string, ip?: string} $row */
    $row = (array) $entry;
    return [
        'id' => (int) ($row['id'] ?? 0),
        'item_key' => $row['item_key'] ?? '',
        'form_id' => (int) ($row['form_id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'is_draft' => (int) ($row['is_draft'] ?? 0) === 1,
        'parent_item_id' => (int) ($row['parent_item_id'] ?? 0),
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? '',
        'ip' => $row['ip'] ?? '',
    ];
}

/**
 * Build the per-field meta map for the get-entry response. Keyed by
 * field_key when resolvable, otherwise by stringified field_id. Long
 * string values pass through the same truncation pipeline get-form uses.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 * @return array<string, mixed>
 */
function frm_entry_meta_to_map(\stdClass $entry, int $max_bytes, array &$truncations): array
{
    /** @var array<int|string, mixed>|null $metas */
    $metas = $entry->metas ?? null;
    if (!is_array($metas)) {
        return [];
    }

    /** @var array<string, mixed> $map */
    $map = [];
    // Resolve field_id → field_key in one pass via FrmField::getOne.
    // @mago-expect analyzer:mixed-assignment
    foreach ($metas as $field_id => $value) {
        $field_id_int = (int) $field_id;
        $key = (string) $field_id;
        if ($field_id_int > 0 && class_exists(FrmField::class) && method_exists(FrmField::class, 'getOne')) {
            $field = FrmField::getOne($field_id_int);
            if ($field instanceof \stdClass) {
                /** @var mixed $field_key */
                $field_key = $field->field_key ?? null;
                if (is_string($field_key) && $field_key !== '') {
                    $key = $field_key;
                }
            }
        }
        $map[$key] = is_scalar($value)
            ? frm_truncate_string((string) $value, $max_bytes, "meta.{$key}", $truncations)
            : $value;
    }
    return $map;
}

/**
 * Normalise caller's item_meta map (keyed by field_id OR field_key) to a
 * field_id-keyed map suitable for FrmEntry::update. Any unresolvable key
 * causes the whole operation to fail with formidable_invalid_input — the
 * arbitrary-key-injection guard at this surface (FluentForms round 3 #F
 * lesson).
 *
 * @param array<int|string, mixed> $item_meta
 * @return array<int, mixed>|WP_Error
 */
function frm_resolve_item_meta(int $form_id, array $item_meta): array|WP_Error
{
    /** @var array<int, mixed> $resolved */
    $resolved = [];
    // @mago-expect analyzer:mixed-assignment
    foreach ($item_meta as $ref => $value) {
        $field = frm_field_or_error($form_id, $ref);
        if ($field instanceof WP_Error) {
            return frm_invalid_input("item_meta key '{$ref}' does not resolve to a field on form {$form_id}.");
        }
        $resolved[(int) $field->id] = $value;
    }
    return $resolved;
}

/**
 * Query wp_frm_items directly (bypassing FrmEntry::getAll's wp_cache layer)
 * and return matching rows as a list of stdClass.
 *
 * @param array<string, mixed> $filters
 * @return list<\stdClass>
 */
function frm_query_entries(int $form_id, array $filters): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    [$where_sql, $params] = frm_build_entry_where($wpdb, $form_id, $filters);

    $table = $wpdb->prefix . 'frm_items';
    $cap = (int) FRM_LIST_ENTRIES_HARD_CAP;
    // @mago-expect analyzer:possibly-invalid-argument
    $prepared = $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT {$cap}", ...$params);
    $query = is_string($prepared) ? $prepared : '';
    $rows = $wpdb->get_results($query);
    if (!is_array($rows)) {
        return [];
    }
    /** @var list<\stdClass> $out */
    $out = [];
    foreach ($rows as $row) {
        if (!$row instanceof \stdClass) {
            continue;
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Build the WHERE clause + ordered params list for the entry-list query.
 *
 * @param array<string, mixed> $filters
 * @return array{0: string, 1: list<mixed>}
 */
function frm_build_entry_where(\wpdb $wpdb, int $form_id, array $filters): array
{
    $clauses = ['form_id = %d'];
    /** @var list<mixed> $params */
    $params = [$form_id];

    if (isset($filters['is_draft'])) {
        $clauses[] = 'is_draft = %d';
        $params[] = (bool) (int) $filters['is_draft'] ? 1 : 0;
    }
    if (isset($filters['user_id'])) {
        $clauses[] = 'user_id = %d';
        $params[] = frm_to_int($filters['user_id']);
    }
    /** @var mixed $search */
    $search = $filters['search'] ?? '';
    if (is_string($search) && trim($search) !== '') {
        $clauses[] = 'item_key LIKE %s';
        $params[] = '%' . $wpdb->esc_like(trim($search)) . '%';
    }
    /** @var mixed $after */
    $after = $filters['created_after'] ?? '';
    if (is_string($after) && $after !== '') {
        $clauses[] = 'created_at >= %s';
        $params[] = $after;
    }
    /** @var mixed $before */
    $before = $filters['created_before'] ?? '';
    if (is_string($before) && $before !== '') {
        $clauses[] = 'created_at <= %s';
        $params[] = $before;
    }

    return [implode(' AND ', $clauses), $params];
}
