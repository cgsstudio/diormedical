<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-write-values', [
    'label' => __('Write ACF Values', domain: 'novamira-pro'),
    'description' => __(
        'Writes a batch of ACF values to a target. Atomic field types accept the value directly: { "subtitle": "Hello" }. Complex field types (repeater, flexible_content, gallery, clone, group) require an explicit consent wrapper: { "features": { "value": [...], "mode": "replace" } }, because writing a complex value replaces the entire previous content. Pre-flight validation via acf_validate_value() enforces required and format constraints; any failure rejects the entire batch — no partial writes. Writes use update_field(), so acf/update_value hooks fire; acf/save_post does not fire because there is no admin save-post submission. Field keys (recommended) and names are both accepted.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['post', 'user', 'term', 'comment', 'options']],
                    'id' => ['type' => ['integer', 'string']],
                ],
                'required' => ['type'],
                'additionalProperties' => false,
            ],
            'values' => [
                'type' => 'object',
                'description' => 'Map of field name or key to value. Complex types require {value, mode:"replace"}.',
            ],
        ],
        'required' => ['target', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'results' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'field' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['ok', 'error']],
                        'error' => ['type' => 'string'],
                        'previous_length' => ['type' => 'integer'],
                        'new_length' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_write_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass a values map keyed by field name or key. For repeater/flexible_content/gallery/clone/group fields, wrap the value as {value, mode:"replace"} — without this, the batch is rejected. Validation runs before any write; if any field fails (required missing, format invalid), nothing is written. To modify a single row of a repeater, first read with acf-read-values, mutate the row, then write back the entire array with mode:"replace".',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{target: array{type: string, id?: int|string}, values: array<string, mixed>} $args
 * @return array{results: list<array<string, mixed>>}|WP_Error
 */
function acf_write_values(array $args): array|WP_Error
{
    if (!class_exists('ACF')) {
        return new WP_Error('acf_not_active', 'ACF is not active');
    }

    $target = $args['target'];
    $normalized = acf_normalize_target($target);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }

    $existence = acf_target_exists($target, $normalized);
    if ($existence instanceof WP_Error) {
        return $existence;
    }

    $values = $args['values'];
    if ($values === []) {
        return ['results' => []];
    }

    $resolved = acf_resolve_write_payload($values, $normalized);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    $validation = acf_validate_resolved($resolved);
    if ($validation instanceof WP_Error) {
        return $validation;
    }

    return ['results' => acf_apply_writes($resolved, $normalized)];
}

/**
 * Resolve each entry in the values map to a field config + payload, enforcing
 * the complex-type consent wrapper. Returns a list of resolved entries on
 * success, or a WP_Error if any field is unknown / missing the wrapper.
 *
 * @param array<string, mixed> $values
 * @return list<array{selector: string, field: array<string, mixed>, value: mixed, is_complex: bool}>|WP_Error
 */
function acf_resolve_write_payload(array $values, string $target_id): array|WP_Error
{
    $resolved = [];
    $missing_consent = [];

    /** @var mixed $entry */
    foreach ($values as $selector => $entry) {
        if ($selector === '') {
            return new WP_Error('invalid_payload', 'values keys must be non-empty field names or keys', [
                'path' => 'values',
                'message' => 'values must be keyed by a non-empty field name or field key',
            ]);
        }
        $field = acf_resolve_field_for_selector($selector, $target_id);
        if (!is_array($field)) {
            return new WP_Error('invalid_payload', "field not found: {$selector}", [
                'path' => "values.{$selector}",
                'message' => 'unknown field name or key',
            ]);
        }
        /** @var mixed $raw_type */
        $raw_type = $field['type'] ?? null;
        $type = is_string($raw_type) ? $raw_type : '';
        $is_complex = acf_field_is_complex($type);

        if (!$is_complex) {
            $resolved[] = [
                'selector' => $selector,
                'field' => $field,
                'value' => $entry,
                'is_complex' => false,
            ];
            continue;
        }

        if (!is_array($entry) || !array_key_exists('value', $entry) || ($entry['mode'] ?? null) !== 'replace') {
            $missing_consent[] = ['name' => $selector, 'type' => $type];
            continue;
        }

        $resolved[] = [
            'selector' => $selector,
            'field' => $field,
            'value' => $entry['value'],
            'is_complex' => true,
        ];
    }

    if ($missing_consent !== []) {
        return new WP_Error(
            'complex_field_missing_replace_mode',
            'complex-type fields require {value, mode:"replace"} wrapper',
            ['fields' => $missing_consent],
        );
    }

    return $resolved;
}

/**
 * Resolve a selector (field key or name) into a field config.
 *
 * Names can be ambiguous when multiple field groups define the same field
 * name; the correct one to use is whichever applies to the current target via
 * location rules. `get_field_object($selector, $target)` consults that
 * mapping. `acf_get_field($selector)` is target-blind and may return any
 * field that happens to share the name. So we try the target-aware lookup
 * first and fall back to the global lookup only when there is no target
 * binding yet (e.g. fresh post with no prior writes for that field).
 *
 * For field-key selectors (`field_<hex>`) both lookups are unambiguous.
 *
 * @return array<string, mixed>|null
 */
function acf_resolve_field_for_selector(string $selector, string $target_id): ?array
{
    $field = get_field_object($selector, $target_id, format_value: false, load_value: false);
    if (is_array($field)) {
        return $field;
    }

    $field = acf_get_field($selector);
    return is_array($field) ? $field : null;
}

/**
 * Prefix used as the `input` argument to acf_validate_value so we can later
 * map registered errors back to selectors.
 */
const ACF_VALIDATION_INPUT_PREFIX = 'novamira_acf:';

/**
 * Run ACF's pre-flight validation for every resolved entry. Returns null when
 * all values are valid, or a WP_Error listing every failure.
 *
 * `acf_validate_value()` only returns true/false; the actual error message is
 * pushed onto a global error store keyed by the `input` argument. We use the
 * field selector as the input attribute so we can read the message back per
 * field after the loop.
 *
 * @param list<array{selector: string, field: array<string, mixed>, value: mixed, is_complex: bool}> $resolved
 */
function acf_validate_resolved(array $resolved): ?WP_Error
{
    $messages = acf_run_validation_loop($resolved);
    if ($messages === []) {
        return null;
    }
    return new WP_Error('validation_failed', 'one or more fields failed validation', [
        'failures' => acf_validation_failures_from_messages($messages),
    ]);
}

/**
 * @param list<array{selector: string, field: array<string, mixed>, value: mixed, is_complex: bool}> $resolved
 * @return array<string, string>  Map of input → message.
 */
function acf_run_validation_loop(array $resolved): array
{
    if (function_exists('acf_reset_validation_errors')) {
        acf_reset_validation_errors();
    }

    $messages = [];
    foreach ($resolved as $r) {
        $input = ACF_VALIDATION_INPUT_PREFIX . $r['selector'];
        $valid = acf_validate_value($r['value'], $r['field'], input: $input);
        if ($valid === true) {
            continue;
        }
        $messages[$input] = 'invalid value';
    }

    return acf_merge_validation_store_messages($messages);
}

/**
 * Read messages from ACF's global validation store and merge into the local
 * map. The stored message overrides the placeholder set in the loop above.
 *
 * @param array<string, string> $messages
 * @return array<string, string>
 */
function acf_merge_validation_store_messages(array $messages): array
{
    if (!function_exists('acf_get_validation_errors')) {
        return $messages;
    }

    /** @var mixed $errors */
    $errors = acf_get_validation_errors();
    if (!is_array($errors)) {
        return $messages;
    }
    /** @var mixed $err */
    foreach ($errors as $err) {
        if (!is_array($err)) {
            continue;
        }
        /** @var mixed $input */
        $input = $err['input'] ?? null;
        /** @var mixed $msg */
        $msg = $err['message'] ?? null;
        if (is_string($input) && is_string($msg) && $msg !== '') {
            $messages[$input] = $msg;
        }
    }
    return $messages;
}

/**
 * @param array<string, string> $messages
 * @return list<array{field: string, code: string, message: string}>
 */
function acf_validation_failures_from_messages(array $messages): array
{
    $failures = [];
    foreach ($messages as $input => $message) {
        $selector = str_starts_with($input, ACF_VALIDATION_INPUT_PREFIX)
            ? substr($input, strlen(ACF_VALIDATION_INPUT_PREFIX))
            : $input;
        $failures[] = ['field' => $selector, 'code' => 'acf_validation', 'message' => $message];
    }
    return $failures;
}

/**
 * Persist each resolved entry via update_field(). Captures previous/new
 * lengths for complex fields so callers can confirm the replace happened.
 *
 * @param list<array{selector: string, field: array<string, mixed>, value: mixed, is_complex: bool}> $resolved
 * @return list<array<string, mixed>>
 */
function acf_apply_writes(array $resolved, string $normalized): array
{
    $results = [];
    foreach ($resolved as $r) {
        /** @var mixed $previous */
        $previous = get_field($r['selector'], $normalized, format_value: false);
        $previous_length = $r['is_complex'] && is_array($previous) ? count($previous) : 0;
        $new_length = is_array($r['value']) ? count($r['value']) : 0;

        // WordPress' update_post_meta wp_unslash()es string values, so a literal
        // backslash from the agent gets stripped unless the input is pre-slashed.
        $ok = update_field($r['selector'], acf_slash_for_storage($r['value']), $normalized);

        // update_field returns false on actual failure AND on a no-op (the new
        // value already equals the stored value). For atomic types we can
        // serialize-compare. For complex types ACF stores rows keyed by field
        // key while the input uses field name, so a direct compare always
        // fails — fall back to a best-effort "row count unchanged" heuristic.
        if (!$ok) {
            $ok = $r['is_complex']
                ? $previous_length === $new_length
                : acf_atomic_value_unchanged($r['selector'], $normalized, $r['value']);
        }

        $entry = [
            'field' => $r['selector'],
            'status' => $ok ? 'ok' : 'error',
        ];
        if (!$ok) {
            $entry['error'] = 'update_field returned false and the stored value did not match the requested value';
        }
        if ($r['is_complex']) {
            $entry['previous_length'] = $previous_length;
            $entry['new_length'] = $new_length;
        }
        $results[] = $entry;
    }

    return $results;
}

/**
 * Re-read the stored value of an atomic field and compare against the
 * requested value via serialize. Used to decide whether a `false` from
 * update_field meant a real failure or a no-op identical-value write.
 */
function acf_atomic_value_unchanged(string $selector, string $normalized, mixed $requested): bool
{
    /** @var mixed $current */
    $current = get_field($selector, $normalized, format_value: false);
    return serialize($current) === serialize($requested);
}

/**
 * Pre-slash strings (and recursively, strings inside arrays) before passing to
 * update_field, so WP's internal wp_unslash on the meta path leaves the agent's
 * value intact. Non-string scalars pass through untouched.
 */
function acf_slash_for_storage(mixed $value): mixed
{
    if (is_string($value)) {
        return wp_slash($value);
    }
    if (is_array($value)) {
        return wp_slash($value);
    }
    return $value;
}
