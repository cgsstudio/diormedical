<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-set-page-overrides', [
    'label' => __('Set OceanWP Per-Page Overrides', domain: 'novamira-pro'),
    'description' => __(
        'Writes the agent-curated allowlist of `ocean_*` postmeta override keys on a single post. Pass `overrides` as a map of meta-key → value; unknown keys are rejected without aborting the rest. Pre-validates every value against the allowlist BEFORE any persistence (no partial state mutation on rejection). Calls `clean_post_cache` after each successful write so subsequent reads return the new value. To REMOVE an override (revert to global default), pass `null` as the value — the post meta key is deleted via `delete_post_meta`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post id (any public CPT). Trash / auto-draft / inherit are rejected.',
                'minimum' => 1,
            ],
            'overrides' => [
                'type' => 'object',
                'description' => 'Map of meta-key (e.g. `ocean_post_layout`) to new value. Passing `null` as the value DELETES the override and reverts the post to the global default.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['post_id', 'overrides'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'persisted' => ['type' => 'boolean'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unchanged' => ['type' => 'array', 'items' => ['type' => 'string']],
            'deleted' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys removed via null (post reverted to global default).',
            ],
            'rejected' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'reason'],
                    'additionalProperties' => false,
                ],
            ],
            'values' => [
                'type' => 'object',
                'description' => 'The post-write value of every accepted meta key. Entries removed via null are NOT in this map.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['post_id', 'persisted', 'changed', 'unchanged', 'deleted', 'rejected', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_set_page_overrides',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Per-post override writer. Allowlist-enforced — unknown keys go to `rejected[]` without aborting. Pass `null` to DELETE an override. For meta keys outside the allowlist use `novamira/execute-php` with `update_post_meta`.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, overrides?: mixed} $input
 * @return array{
 *   post_id: int,
 *   persisted: bool,
 *   changed: list<string>,
 *   unchanged: list<string>,
 *   deleted: list<string>,
 *   rejected: list<array{name: string, reason: string}>,
 *   values: array<string, mixed>|\stdClass
 * }|WP_Error
 */
function oceanwp_set_page_overrides(array $input): array|WP_Error
{
    if (!\Novamira\Pro\Abilities\OceanWP\oceanwp_theme_available()) {
        return ow_not_active();
    }
    $post_id = ow_coerce_int_or_null($input['post_id'] ?? null);
    if ($post_id === null) {
        return ow_invalid_input(__('Input `post_id` is required (positive integer).', domain: 'novamira-pro'));
    }
    $post = ow_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    // is_array guard against scalar `overrides: "foo"` — without it the
    // value would fall through to `ow_classify_page_override_inputs()` and
    // raise TypeError. Audit round 2 M#4.
    if (!isset($input['overrides']) || !is_array($input['overrides']) || $input['overrides'] === []) {
        return ow_invalid_input(__(
            'Input `overrides` must be a non-empty object mapping meta-key to new value.',
            domain: 'novamira-pro',
        ));
    }

    $allowlist = ow_page_overrides_allowlist();
    $classified = ow_classify_page_override_inputs($input['overrides'], $allowlist);
    $candidates = $classified['candidates'];
    $to_delete = $classified['to_delete'];
    $rejected = $classified['rejected'];

    if ($candidates === [] && $to_delete === []) {
        return [
            'post_id' => $post->ID,
            'persisted' => false,
            'changed' => [],
            'unchanged' => [],
            'deleted' => [],
            'rejected' => $rejected,
            // Empty PHP array JSON-encodes as `[]`; the schema declares
            // `values: type:object`, so we emit `{}` on the wire when the
            // map is empty. See audit round 3 F2.
            'values' => ow_object_or_map([]),
        ];
    }

    [$changed, $unchanged, $values] = ow_persist_page_override_candidates($post->ID, $candidates);
    $deleted = ow_persist_page_override_deletions($post->ID, $to_delete);

    if ($changed !== [] || $deleted !== []) {
        // Postmeta writes don't fire save_post; clean_post_cache flushes the
        // post-object cache so subsequent reads see the new meta state.
        clean_post_cache($post->ID);
    }

    return [
        'post_id' => $post->ID,
        'persisted' => $changed !== [] || $deleted !== [],
        'changed' => $changed,
        'unchanged' => $unchanged,
        'deleted' => $deleted,
        'rejected' => $rejected,
        // On the documented null=delete workflow every input is a
        // deletion, so `$candidates === []` and `ow_persist_page_override_candidates()`
        // returns an empty `$values` map. The schema declares
        // `values: type:object`, so we emit `{}` on the wire when the
        // map is empty. See audit round 3 F2.
        'values' => ow_object_or_map($values),
    ];
}

/**
 * Persist validated page-override candidates, tracking which writes actually
 * changed state (`changed[]`) vs. matched the existing value (`unchanged[]`).
 *
 * @param array<string, bool|int|float|string> $candidates
 * @return array{0: list<string>, 1: list<string>, 2: array<string, bool|int|float|string>}
 */
function ow_persist_page_override_candidates(int $post_id, array $candidates): array
{
    $changed = [];
    $unchanged = [];
    $values = [];
    foreach ($candidates as $name => $normalized) {
        // The persisted value stays full-fidelity; only the echoed copy in
        // `values[]` is capped at OW_FIELD_BYTES_DEFAULT so a long string
        // (e.g. ocean_quote_format) doesn't balloon the agent's context
        // window. Audit round 2 M#5.
        $values[$name] = is_string($normalized) ? ow_truncate_field($normalized, OW_FIELD_BYTES_DEFAULT) : $normalized;
        /** @var mixed $current */
        $current = get_post_meta($post_id, $name, single: true);
        if ($current === $normalized || (string) $current === (string) $normalized) {
            $unchanged[] = $name;
            continue;
        }
        update_post_meta($post_id, $name, $normalized);
        $changed[] = $name;
    }
    return [$changed, $unchanged, $values];
}

/**
 * Delete page-override meta keys that the caller asked to clear (`value: null`).
 * Only emits a `deleted` entry when the meta key actually existed pre-delete
 * — so a no-op delete on an already-unset post does not lie about state
 * change.
 *
 * @param list<string> $to_delete
 * @return list<string>
 */
function ow_persist_page_override_deletions(int $post_id, array $to_delete): array
{
    $deleted = [];
    foreach ($to_delete as $name) {
        if (!metadata_exists('post', $post_id, $name)) {
            continue;
        }
        delete_post_meta($post_id, $name);
        $deleted[] = $name;
    }
    return $deleted;
}
