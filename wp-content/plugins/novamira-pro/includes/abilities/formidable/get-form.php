<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmAppHelper;
use FrmField;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Per-field byte cap for large text values in `field_options` / `default_value`
 * / `options` arrays. Anything beyond this is truncated with a sentinel suffix
 * and the caller is told the byte length via `truncated_at`. Mirrors the
 * CF7 / WPForms get-form pattern that ships large mail-blob fields under the
 * same cap. 8 KiB is comfortable for typical labels / messages while
 * preventing the response from ballooning on a form with a 1 MiB rich-text
 * default.
 */
const FRM_GET_FORM_DEFAULT_MAX_FIELD_BYTES = 8192;

const FRM_GET_FORM_MIN_MAX_FIELD_BYTES = 256;

const FRM_GET_FORM_MAX_MAX_FIELD_BYTES = 65_536;

const FRM_GET_FORM_TRUNCATION_SUFFIX = '…[truncated]';

wp_register_ability('novamira/formidable-get-form', [
    'label' => __('Get Formidable Form', domain: 'novamira-pro'),
    'description' => __(
        "Reads the full payload for a single Formidable form by id: the base row (id, form_key, name, description, status, parent_form_id, is_template, created_at), the decoded options blob (the serialized `wp_frm_forms.options` column — submit_value, success_msg, success_action, before/after/submit HTML, email_to, redirect, save_progress, etc.), and the compact field list (id, field_key, type, name, description, required, field_order, default_value, options, field_options). Per-field byte cap defaults to 8192; bump via `max_field_bytes` (256–65536) for long rich-text defaults. Use `include` to skip subsystems you don't need (`['settings']` returns just the options blob; `['fields']` returns just fields). Action / style binding payloads ship from list-form-actions and apply-style — never from get-form.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Numeric form id (from list-forms). Slug-based access (form_key) is not supported here; resolve via list-forms first.',
            ],
            'include' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['settings', 'fields'],
                ],
                'description' => "Subsystems to include in the response. Defaults to `['settings','fields']` — both. Pass `['settings']` to skip the field list, or `['fields']` to skip the options blob.",
                'default' => ['settings', 'fields'],
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => FRM_GET_FORM_MIN_MAX_FIELD_BYTES,
                'maximum' => FRM_GET_FORM_MAX_MAX_FIELD_BYTES,
                'description' => 'Per-string byte cap inside the options blob and per-field maps. Default 8192. Strings longer than this are truncated with a sentinel suffix and reported via a `truncated_at` companion key.',
                'default' => FRM_GET_FORM_DEFAULT_MAX_FIELD_BYTES,
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'form_key' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'parent_form_id' => ['type' => 'integer'],
                    'is_template' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string'],
                ],
                'required' => [
                    'id',
                    'form_key',
                    'name',
                    'description',
                    'status',
                    'parent_form_id',
                    'is_template',
                    'created_at',
                ],
                'additionalProperties' => false,
            ],
            'settings' => [
                // Decoded options blob OR null when the caller excluded 'settings'.
                // The shape inside is freeform per Formidable's options layout —
                // we don't lock it down with JSON Schema or future Formidable
                // updates would break the surface.
                'type' => ['object', 'null'],
            ],
            'fields' => [
                // List of compact field rows OR null when excluded.
                'type' => ['array', 'null'],
            ],
            'truncations' => [
                // List of dotted paths into the payload where a string value was
                // capped. Empty when nothing was truncated.
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'truncated_at' => ['type' => 'integer'],
                        'original_bytes' => ['type' => 'integer'],
                    ],
                    'required' => ['path', 'truncated_at', 'original_bytes'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['form', 'settings', 'fields', 'truncations'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Returns the full form payload: base row + decoded settings (the deserialized `options` blob, e.g. submit_value / success_msg / email_to / before_html) + compact fields[] (id, field_key, type, name, description, required, field_order, default_value, options, field_options). Slug-based access is not supported — pass the numeric id from list-forms. Trashed forms return formidable_form_unavailable; use list-forms{status='trash'} first to enumerate. Long strings inside settings/fields are capped at max_field_bytes (default 8192) — every truncation is listed in the `truncations[]` array with its path so the agent can retry with a higher cap (e.g. max_field_bytes=32768 — the schema accepts up to 65536) when it needs the full value of a specific path. Passing `include: []` (empty array) is treated as the default `['settings','fields']` so an accidental empty input does not strip the whole payload. NEVER call this in a tight loop over hundreds of forms — list-forms gives you the row summary cheaply.",
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, include?: list<string>, max_field_bytes?: int} $input
 * @return array{
 *     form: array{
 *         id: int,
 *         form_key: string,
 *         name: string,
 *         description: string,
 *         status: string,
 *         parent_form_id: int,
 *         is_template: bool,
 *         created_at: string,
 *     },
 *     settings: array<array-key, mixed>|null,
 *     fields: list<array<string, mixed>>|null,
 *     truncations: list<array{path: string, truncated_at: int, original_bytes: int}>,
 * }|WP_Error
 */
function formidable_get_form(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    // An empty `include: []` is normalised to the default — accidental empty
    // input shouldn't silently strip the payload. Callers that legitimately
    // want only one subsystem MUST name it explicitly (e.g. ['fields']).
    $include = $input['include'] ?? ['settings', 'fields'];
    if ($include === []) {
        $include = ['settings', 'fields'];
    }
    $include_settings = in_array(needle: 'settings', haystack: $include, strict: true);
    $include_fields = in_array(needle: 'fields', haystack: $include, strict: true);

    $max_bytes = frm_clamp_max_bytes($input['max_field_bytes'] ?? FRM_GET_FORM_DEFAULT_MAX_FIELD_BYTES);

    /** @var list<array{path: string, truncated_at: int, original_bytes: int}> $truncations */
    $truncations = [];

    $settings = $include_settings ? frm_decode_form_settings($form, $max_bytes, $truncations) : null;
    $fields = $include_fields ? frm_get_form_fields((int) $form->id, $max_bytes, $truncations) : null;

    return [
        'form' => frm_form_to_compact_row($form),
        'settings' => $settings,
        'fields' => $fields,
        'truncations' => $truncations,
    ];
}

/**
 * Clamp the caller-supplied max_field_bytes to the documented [min, max]
 * range. Centralised so the bounds stay aligned with the input_schema
 * declared minimum/maximum.
 */
function frm_clamp_max_bytes(int $requested): int
{
    if ($requested < FRM_GET_FORM_MIN_MAX_FIELD_BYTES) {
        return FRM_GET_FORM_MIN_MAX_FIELD_BYTES;
    }
    if ($requested > FRM_GET_FORM_MAX_MAX_FIELD_BYTES) {
        return FRM_GET_FORM_MAX_MAX_FIELD_BYTES;
    }
    return $requested;
}

/**
 * Decode the form's `options` blob (serialized OR JSON), recursively
 * truncate long strings, and return the resulting array. Returns an empty
 * array when the blob is missing or undecodable — never null — so the
 * caller never has to nullable-branch.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 * @return array<array-key, mixed>
 */
function frm_decode_form_settings(\stdClass $form, int $max_bytes, array &$truncations): array
{
    // FrmForm rows expose `options` as a serialized string OR a pre-decoded
    // array. FrmAppHelper::unserialize_or_decode is BY-REFERENCE and returns
    // void; it mutates the input variable. We handle the pre-decoded path
    // first, then fall through to the in-place decode for the string path.
    // @mago-expect analyzer:mixed-assignment
    $raw = $form->options ?? null;
    if (is_array($raw)) {
        return frm_truncate_recursive($raw, $max_bytes, path: 'settings', truncations: $truncations);
    }
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $raw */
    FrmAppHelper::unserialize_or_decode($raw);
    if (!is_array($raw)) {
        return [];
    }
    return frm_truncate_recursive($raw, $max_bytes, path: 'settings', truncations: $truncations);
}

/**
 * Fetch every field row attached to `$form_id` and reduce each to the
 * compact get-form shape.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 * @return list<array<string, mixed>>
 */
function frm_get_form_fields(int $form_id, int $max_bytes, array &$truncations): array
{
    /** @var list<\stdClass> $field_rows */
    $field_rows = FrmField::get_all_for_form($form_id);
    $compact = [];
    foreach ($field_rows as $index => $field_row) {
        $compact[] = frm_field_to_compact_row($field_row, $max_bytes, "fields[{$index}]", $truncations);
    }
    return $compact;
}

/**
 * Reduce a Formidable field row to the compact shape get-form emits.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 * @return array<string, mixed>
 */
function frm_field_to_compact_row(\stdClass $field, int $max_bytes, string $path, array &$truncations): array
{
    /** @var array{id?: int|string, field_key?: string, type?: string, name?: string, description?: string, default_value?: mixed, options?: mixed, field_options?: mixed, field_order?: int|string, required?: int|string, form_id?: int|string} $row */
    $row = (array) $field;

    // Both columns are serialized payloads of arbitrary shape — the decoder
    // returns mixed by design (most rows yield arrays, but legacy data may
    // hold scalars or null).
    // @mago-expect analyzer:mixed-assignment
    $options = frm_maybe_decode_then_truncate($row['options'] ?? null, $max_bytes, "{$path}.options", $truncations);
    // @mago-expect analyzer:mixed-assignment
    $field_options = frm_maybe_decode_then_truncate(
        $row['field_options'] ?? null,
        $max_bytes,
        "{$path}.field_options",
        $truncations,
    );

    return [
        'id' => (int) ($row['id'] ?? 0),
        'field_key' => $row['field_key'] ?? '',
        'type' => $row['type'] ?? '',
        'name' => frm_truncate_string($row['name'] ?? '', $max_bytes, "{$path}.name", $truncations),
        'description' => frm_truncate_string(
            $row['description'] ?? '',
            $max_bytes,
            "{$path}.description",
            $truncations,
        ),
        'required' => (int) ($row['required'] ?? 0) === 1,
        'field_order' => (int) ($row['field_order'] ?? 0),
        'form_id' => (int) ($row['form_id'] ?? 0),
        'default_value' => frm_maybe_truncate_scalar(
            $row['default_value'] ?? null,
            $max_bytes,
            "{$path}.default_value",
            $truncations,
        ),
        'options' => $options,
        'field_options' => $field_options,
    ];
}

/**
 * If the value is a non-empty string, run it through Formidable's smart
 * deserialize and recursively truncate the resulting array. Otherwise
 * return the value unchanged. Centralises the read-side of the serialized
 * `options` / `field_options` columns used by FrmField + FrmForm.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 */
function frm_maybe_decode_then_truncate(mixed $raw, int $max_bytes, string $path, array &$truncations): mixed
{
    if (is_array($raw)) {
        return frm_truncate_recursive($raw, $max_bytes, $path, $truncations);
    }
    if (!is_string($raw) || $raw === '') {
        return $raw;
    }
    // FrmAppHelper::unserialize_or_decode is BY-REFERENCE — mutates $raw,
    // returns void. After it runs, $raw is either an array (success path) or
    // an unchanged string (no-op path); pass arrays through the truncator,
    // return strings verbatim.
    /** @var mixed $raw */
    FrmAppHelper::unserialize_or_decode($raw);
    if (!is_array($raw)) {
        return $raw;
    }
    return frm_truncate_recursive($raw, $max_bytes, $path, $truncations);
}

/**
 * Truncate the value only when it is a scalar (which is the only case where
 * a length cap is meaningful). Non-scalars pass through.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 */
function frm_maybe_truncate_scalar(mixed $raw, int $max_bytes, string $path, array &$truncations): mixed
{
    if (!is_scalar($raw)) {
        return $raw;
    }
    return frm_truncate_string((string) $raw, $max_bytes, $path, $truncations);
}

/**
 * Walk an arbitrarily-deep array, truncating every string value that exceeds
 * `$max_bytes`. Each truncation is recorded into `$truncations` with its dotted
 * path so the caller can re-fetch specific paths with a higher cap.
 *
 * @param array<array-key, mixed> $value
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 * @return array<array-key, mixed>
 */
function frm_truncate_recursive(array $value, int $max_bytes, string $path, array &$truncations): array
{
    // Array values are mixed by design — the recursion handles strings and
    // nested arrays explicitly and lets scalars / nulls pass through unchanged.
    // @mago-expect analyzer:mixed-assignment
    foreach ($value as $key => $sub) {
        $sub_path = "{$path}.{$key}";
        if (is_string($sub)) {
            $value[$key] = frm_truncate_string($sub, $max_bytes, $sub_path, $truncations);
            continue;
        }
        if (is_array($sub)) {
            $value[$key] = frm_truncate_recursive($sub, $max_bytes, $sub_path, $truncations);
        }
    }
    return $value;
}

/**
 * Truncate a single string to `$max_bytes`. Records the truncation if applied.
 *
 * @param list<array{path: string, truncated_at: int, original_bytes: int}> $truncations
 */
function frm_truncate_string(string $value, int $max_bytes, string $path, array &$truncations): string
{
    $bytes = strlen($value);
    if ($bytes <= $max_bytes) {
        return $value;
    }
    $suffix = FRM_GET_FORM_TRUNCATION_SUFFIX;
    $head_bytes = max(0, $max_bytes - strlen($suffix));
    // Use mb_strcut so the cut never lands inside a multi-byte UTF-8 sequence
    // — a byte-based substr() can leave a half character that breaks the
    // downstream json_encode of the response. Falls back to substr when the
    // mbstring extension is somehow missing.
    $head = function_exists('mb_strcut')
        ? mb_strcut(string: $value, start: 0, length: $head_bytes, encoding: 'UTF-8')
        : substr(string: $value, offset: 0, length: $head_bytes);
    $truncated = $head . $suffix;
    $truncations[] = [
        'path' => $path,
        'truncated_at' => $head_bytes,
        'original_bytes' => $bytes,
    ];
    return $truncated;
}
