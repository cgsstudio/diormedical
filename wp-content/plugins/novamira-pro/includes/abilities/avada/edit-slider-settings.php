<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-edit-slider-settings', [
    'label' => __('Edit Avada Fusion Slider Settings', domain: 'novamira-pro'),
    'description' => __(
        'Partial-merge update on a Fusion Slider `_fusion` term_meta bag — the dimensions, animation, autoplay, and navigation settings the slider ships with. Only the keys passed in `values` are written; siblings are preserved. Keys are validated against `avada_writable_slider_setting_keys()` — unknown keys reject the ENTIRE batch. Each value is sanitized against its declared type before persistence. The read-modify-write cycle is serialized against concurrent editors of the same slider (TOCTOU lock — two callers editing different subkeys of the same term will not clobber each other). Post-write flushes the `avada` wp_cache group + fires `fusion_cache_clear`. Requires Fusion Core plugin active at min version.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slider' => [
                'type' => ['string', 'integer'],
                'minLength' => 1,
                'minimum' => 1,
                'description' => 'Slider slug or numeric term id.',
            ],
            'values' => [
                'type' => 'object',
                'description' => 'Map of `_fusion` term_meta keys to new values. Only keys listed in `avada_writable_slider_setting_keys()` are accepted.',
                'minProperties' => 1,
                'additionalProperties' => true,
            ],
        ],
        'required' => ['slider', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'changed' => ['type' => 'boolean'],
            'written' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unchanged' => ['type' => 'array', 'items' => ['type' => 'string']],
            'settings' => [
                'type' => 'object',
                'description' => 'The full `_fusion` term_meta bag after commit.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['id', 'slug', 'name', 'changed', 'written', 'unchanged', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_edit_slider_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial-merge write on slider `_fusion` term_meta. Unknown keys or unsafe values reject the whole batch. TOCTOU lock + cache flush after successful write.',
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
function avada_edit_slider_settings(array $input): array|WP_Error
{
    if (!fusion_core_available()) {
        return av_fusion_core_not_active();
    }

    /** @var mixed $ref */
    $ref = $input['slider'] ?? null;
    if (!is_string($ref) && !is_int($ref)) {
        return av_invalid_input('slider must be a string slug or numeric term id');
    }
    $term = av_slider_term_or_error($ref);
    if ($term instanceof WP_Error) {
        return $term;
    }

    /** @var mixed $values_input */
    $values_input = $input['values'] ?? null;
    if (!is_array($values_input) || $values_input === []) {
        return av_invalid_input('values must be a non-empty object');
    }

    // Pre-validate + sanitize the entire batch OUTSIDE the lock so an
    // invalid batch fails fast without blocking concurrent writers.
    $clean = avada_prevalidate_bag($values_input, avada_writable_slider_setting_keys());
    if ($clean instanceof WP_Error) {
        return $clean;
    }

    /** @var array{written: list<string>, unchanged: list<string>, settings: array<string, mixed>, error: WP_Error|null} $result_ref */
    $result_ref = ['written' => [], 'unchanged' => [], 'settings' => [], 'error' => null];

    av_with_slide_page_term_lock((int) $term->term_id, static function () use ($term, $clean, &$result_ref): void {
        avada_commit_slider_bag((int) $term->term_id, $clean, $result_ref);
    });

    if ($result_ref['error'] instanceof WP_Error) {
        return $result_ref['error'];
    }

    $changed = $result_ref['written'] !== [];
    if ($changed) {
        avada_flush_fusion_core_cache();
    }

    return [
        'id' => (int) $term->term_id,
        'slug' => $term->slug,
        'name' => $term->name,
        'changed' => $changed,
        'written' => $result_ref['written'],
        'unchanged' => $result_ref['unchanged'],
        // Cast to `(object)` so an empty post-write bag JSON-serialises
        // as `{}` (output_schema declares `type: object`).
        'settings' => (object) $result_ref['settings'],
    ];
}

/**
 * Merge the sanitized bag into the current slider settings under the
 * caller-held term lock, persist, and populate `$result_ref` with the
 * outcome. Extracted from the ability callback so the outer function
 * stays under the cyclomatic-complexity + halstead thresholds.
 *
 * @param array<string, mixed> $clean
 * @param array{written: list<string>, unchanged: list<string>, settings: array<string, mixed>, error: WP_Error|null} $result_ref
 */
function avada_commit_slider_bag(int $term_id, array $clean, array &$result_ref): void
{
    [$after, $written, $unchanged] = avada_partial_merge_bag(av_get_slider_settings($term_id), $clean);
    if ($written !== []) {
        $write_error = av_set_slider_settings($term_id, $after);
        if ($write_error instanceof WP_Error) {
            $result_ref['error'] = $write_error;
            return;
        }
    }
    $result_ref['written'] = $written;
    $result_ref['unchanged'] = $unchanged;
    // Re-read after the lock write so a downstream mirror (e.g. Fusion
    // Library's per-request cache) that mutated the bag is reflected.
    $result_ref['settings'] = av_get_slider_settings($term_id);
}
