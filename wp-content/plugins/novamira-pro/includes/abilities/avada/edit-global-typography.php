<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-edit-global-typography', [
    'label' => __('Edit Avada Global Typography', domain: 'novamira-pro'),
    'description' => __(
        'Update a single AWB Global Typography preset by slug (`typography1`..`typographyN`). At least one field must accompany `slug`. Read-modify-write on `fusion_options.typography_sets` — only the target preset is mutated. `changed: false` on an idempotent no-op. Post-write flushes Fusion cache. Values shape maps to Avada storage keys: `family`→`font-family`, `size`→`font-size`, `weight`→`font-weight`, `lineHeight`→`line-height`, `letterSpacing`→`letter-spacing`, `textTransform`→`text-transform`.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string', 'pattern' => '^typography[0-9]+$'],
            'label' => ['type' => 'string'],
            'family' => ['type' => 'string'],
            'size' => ['type' => 'string'],
            'weight' => ['type' => 'string'],
            'lineHeight' => ['type' => 'string'],
            'letterSpacing' => ['type' => 'string'],
            'textTransform' => ['type' => 'string'],
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
            'family' => ['type' => 'string'],
            'size' => ['type' => 'string'],
            'weight' => ['type' => 'string'],
            'lineHeight' => ['type' => 'string'],
            'letterSpacing' => ['type' => 'string'],
            'textTransform' => ['type' => 'string'],
            'changed' => ['type' => 'boolean'],
        ],
        'required' => [
            'slug',
            'label',
            'family',
            'size',
            'weight',
            'lineHeight',
            'letterSpacing',
            'textTransform',
            'changed',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_edit_global_typography',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Modify one AWB Global Typography preset. Read-modify-write on fusion_options.typography_sets. Cache flush after.',
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
function avada_edit_global_typography(array $input): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }

    // Runtime slug validation. Schema enforces `^typography\d+$` at the
    // REST layer; the runtime check defends against direct PHP invocation
    // (audit A2 F2) that would otherwise let a caller inject arbitrary
    // keys into the typography_sets blob.
    if (
        !isset($input['slug'])
        || !is_string($input['slug'])
        || preg_match('/^typography[0-9]+$/', $input['slug']) !== 1
    ) {
        return av_invalid_input('slug must match the pattern typographyN');
    }
    $slug = $input['slug'];

    /** @var array<string, mixed>|null $mutated_entry_ref */
    $mutated_entry_ref = null;
    $changed = false;

    $write_error = av_with_fusion_lock('typography_sets', static function (mixed $current) use (
        $slug,
        $input,
        &$mutated_entry_ref,
        &$changed,
    ): mixed {
        /** @var array<string, array<string, mixed>> $typography */
        $typography = is_array($current) ? $current : [];

        $entry = $typography[$slug] ?? [];
        $before = $entry;

        $updated = avada_apply_typography_input($entry, $input);
        if ($updated instanceof WP_Error) {
            return $updated;
        }

        $mutated_entry_ref = $updated;
        $changed = maybe_serialize($before) !== maybe_serialize($updated);
        if (!$changed) {
            return $typography;
        }
        $typography[$slug] = $updated;
        return $typography;
    });

    if ($write_error instanceof WP_Error) {
        return $write_error;
    }

    if ($changed) {
        avada_flush_fusion_cache();
    }

    /** @var array<string, mixed> $mapped */
    $mapped = awb_typography_map($slug, is_array($mutated_entry_ref) ? $mutated_entry_ref : []);
    $mapped['changed'] = $changed;
    return $mapped;
}

/**
 * Merge the caller's typography fields into a preset entry with per-field
 * validation AND sanitization. Family / size / weight / lineHeight /
 * letterSpacing / textTransform all go through
 * `avada_sanitize_typography_field` so any character that could break out
 * of the CSS declaration context is rejected (audit A2 F1).
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function avada_apply_typography_input(array $entry, array $input): array|WP_Error
{
    $input_to_storage = [
        'label' => 'label',
        'family' => 'font-family',
        'size' => 'font-size',
        'weight' => 'font-weight',
        'lineHeight' => 'line-height',
        'letterSpacing' => 'letter-spacing',
        'textTransform' => 'text-transform',
    ];
    foreach ($input_to_storage as $api_key => $storage_key) {
        if (!array_key_exists($api_key, $input)) {
            continue;
        }
        if (!is_string($input[$api_key])) {
            return av_invalid_input($api_key . ' must be a string');
        }
        // Labels flow through sanitize_text_field only (they're free-form);
        // every other field is a CSS token and gets the stricter guard.
        $sanitised = match ($api_key) {
            'label' => avada_sanitize_label($input[$api_key]),
            default => avada_sanitize_typography_field($input[$api_key]),
        };
        if ($sanitised === null) {
            return av_invalid_input($api_key . ' contains characters that are not safe for CSS emission');
        }
        $entry[$storage_key] = $sanitised;
    }
    return $entry;
}
