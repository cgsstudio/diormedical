<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-edit-global-color', [
    'label' => __('Edit Avada Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Update a single AWB Global Color preset by slug (`color1`..`colorN`). At least one of `label` / `color` must accompany `slug`. `color` accepts any CSS color string the theme will echo verbatim (typical: `rgba(r,g,b,a)` or `#rrggbb`). Read-modify-write on `fusion_options.color_palette`: only the target slug is mutated, siblings preserved. `changed: false` on an idempotent no-op (same value stored). Post-write flushes `fusion_dynamic_css` + fires `fusion_cache_clear` so the front end serves fresh CSS.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'pattern' => '^color[0-9]+$'],
            'label' => ['type' => 'string'],
            'color' => ['type' => 'string', 'minLength' => 1],
        ],
        'required' => ['slug'],
        'minProperties' => 2,
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'changed' => ['type' => 'boolean'],
        ],
        'required' => ['slug', 'label', 'color', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_edit_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Modify one AWB Global Color preset. Read-modify-write on fusion_options.color_palette. Cache flush after.',
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
function avada_edit_global_color(array $input): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }

    // Runtime slug validation. Schema enforces `^color\d+$` at the REST
    // layer; the runtime check defends against direct PHP invocation
    // (audit A2 F2) that would otherwise let a caller inject arbitrary
    // keys like `__proto__` into the palette blob.
    if (!isset($input['slug']) || !is_string($input['slug']) || preg_match('/^color[0-9]+$/', $input['slug']) !== 1) {
        return av_invalid_input('slug must match the pattern colorN');
    }
    $slug = $input['slug'];

    // Serialise the read-modify-write cycle against concurrent writers
    // on the same option row (audit A2 F2 — TOCTOU race on the
    // color_palette blob). The lock scope is the ENTIRE `fusion_options`
    // row (via av_with_fusion_row_lock — see round-1 code-review MAJOR
    // fix at helpers.php); a concurrent edit-global-typography or
    // set-theme-options writer serialises against this one so their
    // whole-row commits don't clobber each other. Read is via the RAW
    // option row, not through `AWB_Global_Colors::get_palette()`, so a
    // filter injecting virtual entries on read does not get persisted
    // back to storage (audit A2 F3).
    /** @var array<string, mixed>|null $mutated_ref */
    $mutated_ref = null;
    $changed = false;

    $write_error = av_with_fusion_lock('color_palette', static function (mixed $current) use (
        $slug,
        $input,
        &$mutated_ref,
        &$changed,
    ): mixed {
        /** @var array<string, array<string, mixed>> $palette */
        $palette = is_array($current) ? $current : [];

        $entry = $palette[$slug] ?? ['label' => '', 'color' => ''];

        $updated = avada_apply_color_input($entry, $input);
        if ($updated instanceof WP_Error) {
            return $updated;
        }

        $mutated_ref = $updated;
        $changed = maybe_serialize($entry) !== maybe_serialize($updated);
        if (!$changed) {
            // Return the palette unchanged — the writer will detect the
            // no-op via its own serialised comparison and skip.
            return $palette;
        }
        $palette[$slug] = $updated;
        return $palette;
    });

    if ($write_error instanceof WP_Error) {
        return $write_error;
    }

    if ($changed) {
        avada_flush_fusion_cache();
    }

    return [
        'slug' => $slug,
        'label' => (string) ($mutated_ref['label'] ?? ''),
        'color' => (string) ($mutated_ref['color'] ?? ''),
        'changed' => $changed,
    ];
}

/**
 * Merge the caller's `label` / `color` into a color-palette entry with
 * per-field validation AND sanitization. Every user-facing string goes
 * through the sanitizers before persistence (audit A2 F1 — stored XSS
 * prevention): labels via `sanitize_text_field`, colors via the hex /
 * functional-CSS-color validator that rejects any character which could
 * break out of the CSS declaration context.
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function avada_apply_color_input(array $entry, array $input): array|WP_Error
{
    if (array_key_exists('label', $input)) {
        if (!is_string($input['label'])) {
            return av_invalid_input('label must be a string');
        }
        $entry['label'] = avada_sanitize_label($input['label']);
    }
    if (array_key_exists('color', $input)) {
        if (!is_string($input['color']) || $input['color'] === '') {
            return av_invalid_input('color must be a non-empty string');
        }
        $clean_color = avada_sanitize_color($input['color']);
        if ($clean_color === null) {
            return av_invalid_input('color is not a valid CSS color value');
        }
        $entry['color'] = $clean_color;
    }
    return $entry;
}
