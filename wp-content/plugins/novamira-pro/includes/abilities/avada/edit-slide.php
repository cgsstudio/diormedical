<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-edit-slide', [
    'label' => __('Edit Avada Fusion Slide', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge update on a Fusion Slider slide (`slide` post). Only the keys passed in `values` are written; siblings are preserved. Keys are validated against the writable-slide-keys whitelist (`avada_writable_slide_keys()`) — unknown keys reject the ENTIRE batch (all-or-nothing, no partial writes). Each value is sanitized against its declared type (string/url/color/bool/int/enum) before persistence — a value that cannot be normalised safely also rejects the entire batch. Post-write flushes the `avada` wp_cache group + fires `fusion_cache_clear`. Idempotent: same values → `changed: false`. Requires Fusion Core plugin active at min version.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'values' => [
                'type' => 'object',
                'description' => 'Map of `_fusion` postmeta keys to new values. Only keys listed in `avada_writable_slide_keys()` are accepted. See ability description for the type-per-key registry.',
                'minProperties' => 1,
                'additionalProperties' => true,
            ],
        ],
        'required' => ['id', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'changed' => ['type' => 'boolean'],
            'written' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys whose value differs from the pre-write bag.',
            ],
            'unchanged' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys whose incoming value equals the pre-write bag (no-op keys).',
            ],
            'meta' => [
                'type' => 'object',
                'description' => 'The full `_fusion` postmeta bag after commit (post-write snapshot).',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['id', 'changed', 'written', 'unchanged', 'meta'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_edit_slide',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial-merge write on slide `_fusion` postmeta. Unknown keys or unsafe values reject the whole batch. Cache flush after successful write.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function avada_edit_slide(array $input): array|WP_Error
{
    if (!fusion_core_available()) {
        return av_fusion_core_not_active();
    }

    $id = avada_coerce_int($input['id'] ?? 0, minimum: 0);
    if ($id <= 0) {
        return av_invalid_input('id must be a positive integer');
    }
    $post = av_post_or_error($id, [AVADA_CPT_SLIDE]);
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $values_input */
    $values_input = $input['values'] ?? null;
    if (!is_array($values_input) || $values_input === []) {
        return av_invalid_input('values must be a non-empty object');
    }

    $clean = avada_prevalidate_bag($values_input, avada_writable_slide_keys());
    if ($clean instanceof WP_Error) {
        return $clean;
    }

    // Wrap the read-modify-write in a per-post lock so two concurrent
    // editors of the same slide (touching disjoint keys) can't clobber
    // each other — audit A3 F-A. Same INSERT-based primitive as the
    // slider-term lock; TTL 30s; multisite-safe via blog_id namespacing.
    /** @var array{written: list<string>, unchanged: list<string>, meta: array<string, mixed>, error: WP_Error|null} $result_ref */
    $result_ref = ['written' => [], 'unchanged' => [], 'meta' => [], 'error' => null];

    av_with_slide_post_lock((int) $post->ID, static function () use ($post, $clean, &$result_ref): void {
        avada_commit_slide_bag((int) $post->ID, $clean, $result_ref);
    });

    if ($result_ref['error'] instanceof WP_Error) {
        return $result_ref['error'];
    }

    $changed = $result_ref['written'] !== [];
    if ($changed) {
        avada_flush_fusion_core_cache();
    }

    return [
        'id' => (int) $post->ID,
        'changed' => $changed,
        'written' => $result_ref['written'],
        'unchanged' => $result_ref['unchanged'],
        // Cast to `(object)` so an empty post-write bag JSON-serialises
        // as `{}` (output_schema declares `type: object`).
        'meta' => (object) $result_ref['meta'],
    ];
}

/**
 * Merge the sanitized bag into the current slide postmeta under the
 * caller-held post lock, persist, and populate `$result_ref`. Extracted
 * so the outer callback stays under complexity thresholds and mirrors
 * the shape of `avada_commit_slider_bag` in edit-slider-settings.
 *
 * @param array<string, mixed> $clean
 * @param array{written: list<string>, unchanged: list<string>, meta: array<string, mixed>, error: WP_Error|null} $result_ref
 */
function avada_commit_slide_bag(int $post_id, array $clean, array &$result_ref): void
{
    [$after, $written, $unchanged] = avada_partial_merge_bag(av_get_slide_meta($post_id), $clean);
    if ($written !== []) {
        $write_error = av_set_slide_meta($post_id, $after);
        if ($write_error instanceof WP_Error) {
            $result_ref['error'] = $write_error;
            return;
        }
    }
    $result_ref['written'] = $written;
    $result_ref['unchanged'] = $unchanged;
    // Re-read after the write so a downstream filter that mutated the
    // write-back value (rare) is reflected in the response.
    $result_ref['meta'] = av_get_slide_meta($post_id);
}
