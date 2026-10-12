<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra\Pro;

use Novamira\Pro\Abilities\Spectra as Free;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-pro-set-popup-conditions', [
    'label' => __('Set Spectra Pro Popup Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Write the Spectra Pro display-condition bundle for one popup. Every field is optional (at least one MUST accompany `id`). `display_inclusions` and `display_exclusions` are REPLACE-ALL writes (the whole bundle is overwritten). `trigger` accepts the Spectra Pro trigger vocabulary (typically `load`, `click`, `time-delay`, `exit-intent`, `scroll`); the ability stores the string verbatim and lets Spectra Pro validate at render time. `written[]` in the response lists keys whose stored value actually changed on this call — a key already at target does NOT appear (idempotent no-op). Post cache is flushed after the write. All-or-nothing type validation: every submitted field is type-checked before the first write, so a bad type never leaves a partial write behind. Not transaction-safe against a mid-sequence storage failure (e.g. a caching-plugin filter short-circuiting one row after another has committed) OR against concurrent callers overlapping keys — last-write-wins per meta row. On such a mid-sequence failure the returned `WP_Error` carries the list of already-persisted rows under `data.partial_writes` and the post cache is flushed for them.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'trigger' => ['type' => 'string'],
            'trigger_delay' => ['type' => 'integer', 'minimum' => 0],
            // Condition bundles are BSF-defined associative objects
            // ({rule, specific, specificText, ...}), not lists.
            'display_inclusions' => ['type' => 'object', 'additionalProperties' => true],
            'display_exclusions' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['id'],
        // At least one condition field must accompany `id`.
        'minProperties' => 2,
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'written' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Meta keys whose stored value actually changed on this call. A key you submitted whose value already matched target is NOT listed here (idempotent no-op).',
            ],
            'trigger' => ['type' => 'string'],
            'trigger_delay' => ['type' => 'integer'],
            'display_inclusions' => ['type' => 'object', 'additionalProperties' => true],
            'display_exclusions' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['id', 'written', 'trigger', 'trigger_delay', 'display_inclusions', 'display_exclusions'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\Pro\\spectra_pro_set_popup_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge-update the 4-key Pro display-condition bundle. display_inclusions/exclusions are REPLACE-ALL.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_pro_set_popup_conditions(array $input): array|WP_Error
{
    if (!Free\spectra_pro_active()) {
        return Free\sp_pro_required();
    }

    $id = Free\spectra_coerce_int($input['id'] ?? 0, minimum: 0);
    $post = Free\sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    // Guard: id alone would be a no-op. `minProperties: 2` catches this at
    // the schema layer; the runtime guard is defense-in-depth.
    if (count($input) < 2) {
        return Free\sp_invalid_input(
            'At least one of trigger, trigger_delay, display_inclusions, display_exclusions must accompany id.',
        );
    }

    // Write order: the two condition-bundle metas first (largest payload,
    // most likely to trip a filter or fail on out-of-memory), then the
    // scalar trigger / trigger_delay. This minimises the "trigger persisted
    // but rules didn't" partial-write footprint if a fatal interrupts the
    // sequence — WP has no transaction wrapper around postmeta at this
    // layer, so we can't guarantee atomicity across the four rows.
    $written_or_error = spectra_pro_apply_condition_writes($post->ID, $input);
    if ($written_or_error instanceof WP_Error) {
        return $written_or_error;
    }
    clean_post_cache($post->ID);
    $written = $written_or_error;

    return [
        'id' => $post->ID,
        'written' => $written,
        'trigger' => spectra_pro_read_string_meta($post->ID, SPECTRA_PRO_POPUP_META_TRIGGER),
        'trigger_delay' => spectra_pro_read_int_meta($post->ID, SPECTRA_PRO_POPUP_META_TRIGGER_DELAY),
        'display_inclusions' => spectra_pro_read_condition_meta($post->ID, SPECTRA_PRO_POPUP_META_DISPLAY_INCLUSIONS),
        'display_exclusions' => spectra_pro_read_condition_meta($post->ID, SPECTRA_PRO_POPUP_META_DISPLAY_EXCLUSIONS),
    ];
}

/**
 * Apply the four possible meta writes in the storage-safe order (bundles
 * first, scalars last). Returns the list of meta keys whose stored value
 * actually changed, or a WP_Error on the first write failure.
 *
 * Split off from the ability callback so the top-level function stays
 * under the complexity budget and so the audit-round-1 partial-write
 * ordering is grep-visible in one place.
 *
 * @param array<string, mixed> $input
 * @return list<string>|WP_Error
 */
function spectra_pro_apply_condition_writes(int $post_id, array $input): array|WP_Error
{
    // Pre-validation pass: type-check + coerce every field BEFORE the first
    // write. Guarantees that a type mismatch on field N cannot leak a
    // partial write from fields 1..N-1 to the DB. Necessary because
    // `minProperties: 2` at the schema layer does not run when the callback
    // is invoked outside the abilities-API framework (direct PHP invocation,
    // test harness, custom REST wrapper). Adversarial-lens fix.
    $validation_error = spectra_pro_validate_condition_inputs($input);
    if ($validation_error instanceof WP_Error) {
        return $validation_error;
    }

    $written = [];

    if (array_key_exists('display_inclusions', $input)) {
        $r = spectra_pro_write_bundle(
            $post_id,
            SPECTRA_PRO_POPUP_META_DISPLAY_INCLUSIONS,
            $input['display_inclusions'],
        );
        if ($r instanceof WP_Error) {
            return spectra_pro_annotate_partial_write($r, $written, $post_id);
        }
        if ($r === true) {
            $written[] = SPECTRA_PRO_POPUP_META_DISPLAY_INCLUSIONS;
        }
    }
    if (array_key_exists('display_exclusions', $input)) {
        $r = spectra_pro_write_bundle(
            $post_id,
            SPECTRA_PRO_POPUP_META_DISPLAY_EXCLUSIONS,
            $input['display_exclusions'],
        );
        if ($r instanceof WP_Error) {
            return spectra_pro_annotate_partial_write($r, $written, $post_id);
        }
        if ($r === true) {
            $written[] = SPECTRA_PRO_POPUP_META_DISPLAY_EXCLUSIONS;
        }
    }
    if (array_key_exists('trigger', $input)) {
        /** @var string $trigger_value validated by spectra_pro_validate_condition_inputs */
        $trigger_value = $input['trigger'];
        $r = spectra_pro_persist_meta($post_id, SPECTRA_PRO_POPUP_META_TRIGGER, $trigger_value);
        if ($r instanceof WP_Error) {
            return spectra_pro_annotate_partial_write($r, $written, $post_id);
        }
        if ($r === true) {
            $written[] = SPECTRA_PRO_POPUP_META_TRIGGER;
        }
    }
    if (array_key_exists('trigger_delay', $input)) {
        $delay = Free\spectra_coerce_int($input['trigger_delay'], minimum: 0);
        $r = spectra_pro_persist_meta($post_id, SPECTRA_PRO_POPUP_META_TRIGGER_DELAY, $delay);
        if ($r instanceof WP_Error) {
            return spectra_pro_annotate_partial_write($r, $written, $post_id);
        }
        if ($r === true) {
            $written[] = SPECTRA_PRO_POPUP_META_TRIGGER_DELAY;
        }
    }
    return $written;
}

/**
 * Coerce + persist an inclusions/exclusions bundle in one shot. Returns
 * the same three-valued result as `spectra_pro_persist_meta` (`true` /
 * `false` / `WP_Error`).
 */
function spectra_pro_write_bundle(int $post_id, string $meta_key, mixed $raw): bool|WP_Error
{
    $bundle = spectra_pro_coerce_condition_bundle($raw);
    if ($bundle === null) {
        // Human-friendly error name — strip the `spectra-popup-display-`
        // prefix and report `inclusions` / `exclusions`.
        $short = str_replace('spectra-popup-display-', replace: '', subject: $meta_key);
        return Free\sp_invalid_input(sprintf('display_%s must be an object', $short));
    }
    return spectra_pro_persist_meta($post_id, $meta_key, $bundle);
}

/**
 * Coerce a caller-supplied inclusions/exclusions bundle into the PHP array
 * shape WP's `update_post_meta` expects. MCP JSON `{}` arrives as
 * `stdClass` when framework decoders leave associative objects boxed;
 * `[]` arrives as an empty PHP array. Both are valid input — this helper
 * normalises them for storage. Returns `null` on any other input so the
 * caller can emit `sp_invalid_input`.
 *
 * @return array<array-key, mixed>|null
 */
function spectra_pro_coerce_condition_bundle(mixed $value): ?array
{
    if (is_array($value)) {
        return $value;
    }
    if ($value instanceof \stdClass) {
        return (array) $value;
    }
    return null;
}

/**
 * Persist one popup-conditions meta row, disambiguating WP's overloaded
 * `update_post_meta` return: `true` on a mutation, `false` when the new
 * value equals the current value OR when a `update_post_metadata` filter
 * short-circuits the write. Returns `true` when the caller should count
 * the key as "written", `false` when the value was already at target (no-
 * op — response body stays byte-stable), or a `WP_Error` when the write
 * genuinely failed.
 */
function spectra_pro_persist_meta(int $post_id, string $meta_key, mixed $value): bool|WP_Error
{
    // Read current before writing so we can tell "no-op because same value"
    // apart from "no-op because caching plugin blocked the write". WP's own
    // `update_metadata` serialises the new value via `maybe_serialize`
    // before comparing with the row's `meta_value` (which is TEXT — always
    // a string on read). We mirror that exact comparison so scalars
    // (`3` vs `'3'`) round-trip cleanly and objects/arrays compare via
    // their PHP-serialised form (WP's own equality basis).
    /** @var mixed $current */
    $current = get_post_meta($post_id, $meta_key, single: true);
    if (spectra_pro_meta_values_equal($current, $value)) {
        // Value already at target — WP would also return `false`, but
        // that's an idempotent no-op, not a failure.
        return false;
    }

    $result = update_post_meta($post_id, $meta_key, $value);
    if ($result === false) {
        return new WP_Error(
            'spectra_write_failed',
            /* translators: %s: meta key */
            sprintf(
                __(
                    'Failed to persist Spectra Pro meta `%s` (a caching layer or upstream filter may have blocked the write).',
                    domain: 'novamira-pro',
                ),
                $meta_key,
            ),
            ['status' => 500],
        );
    }
    return true;
}

/**
 * Whether two post-meta values compare equal after WP's own storage
 * normalisation. WP stores every postmeta cell as TEXT — scalars come
 * back as strings on read regardless of the type the caller wrote. For
 * scalars we compare via string cast (so int `3` and its stored form
 * `'3'` compare equal); for arrays / objects we use `maybe_serialize`
 * (WP's own equality basis for composite meta values, which arrays and
 * objects DO get serialised into on write).
 */
function spectra_pro_meta_values_equal(mixed $current, mixed $incoming): bool
{
    // stdClass on either side → normalise to PHP array so equal object
    // and array shapes compare identical.
    /** @var mixed $current_norm */
    $current_norm = is_object($current) ? get_object_vars($current) : $current;
    /** @var mixed $incoming_norm */
    $incoming_norm = is_object($incoming) ? get_object_vars($incoming) : $incoming;
    // Composite (array): compare via WP's own serialisation.
    if (is_array($current_norm) || is_array($incoming_norm)) {
        return maybe_serialize($current_norm) === maybe_serialize($incoming_norm);
    }
    // Scalar: string-cast comparison so `3` and `'3'` compare equal.
    return (string) $current_norm === (string) $incoming_norm;
}

/**
 * Pre-validate every submitted field before the first write. Guarantees the
 * write loop cannot commit fields 1..N-1 and then reject field N on a type
 * check — the schema layer already enforces this when the ability is called
 * through the abilities-API framework, but a direct PHP invocation or a
 * test harness can bypass the schema and reach the callback with a bad
 * type. Returns `null` when every field is well-formed, a `WP_Error`
 * (`spectra_invalid_input`) when any field is not.
 *
 * @param array<string, mixed> $input
 */
function spectra_pro_validate_condition_inputs(array $input): ?WP_Error
{
    if (array_key_exists('trigger', $input) && !is_string($input['trigger'])) {
        return Free\sp_invalid_input('trigger must be a string');
    }
    if (
        array_key_exists('display_inclusions', $input)
        && spectra_pro_coerce_condition_bundle($input['display_inclusions']) === null
    ) {
        return Free\sp_invalid_input('display_inclusions must be an object');
    }
    if (
        array_key_exists('display_exclusions', $input)
        && spectra_pro_coerce_condition_bundle($input['display_exclusions']) === null
    ) {
        return Free\sp_invalid_input('display_exclusions must be an object');
    }
    return null;
}

/**
 * Enrich a mid-sequence write failure with the list of meta keys that
 * DID successfully commit before the failure, and flush the post cache so
 * those committed rows are visible on the next read. Without this, the
 * caller sees only the terminal `WP_Error` and cannot tell that some
 * bundle rows were persisted while the trigger scalar (or whatever came
 * later) failed — a silent-partial-state bug the adversarial audit
 * surfaced. The partial list is exposed under
 * `$error->get_error_data()['partial_writes']`.
 *
 * @param list<string> $written
 */
function spectra_pro_annotate_partial_write(WP_Error $err, array $written, int $post_id): WP_Error
{
    if ($written !== []) {
        clean_post_cache($post_id);
    }
    /** @var mixed $data */
    $data = $err->get_error_data();
    if (!is_array($data)) {
        $data = ['status' => 500];
    }
    $data['partial_writes'] = $written;
    return new WP_Error($err->get_error_code(), $err->get_error_message(), $data);
}
