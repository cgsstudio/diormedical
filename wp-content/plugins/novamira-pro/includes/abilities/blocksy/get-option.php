<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read one Blocksy customizer option with default resolution.
 */

wp_register_ability('novamira/blocksy-get-option', [
    'label' => __('Get Blocksy Option', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Blocksy customizer option by id (theme_mod name, e.g. `maxSiteWidth`, `rootTypography`, `header_placements`). Blocksy never persists defaults, so the response resolves the effective value: the stored value when present (has_stored_value=true), otherwise the default from the option schema in the theme\'s inc/options/ definitions. `storage` says where the value lives — `theme_mod` for almost every option, `option` for the few ids Blocksy declares with setting.type=option (WooCommerce settings re-exposed in its customizer, plus wp_page_for_privacy_policy): those are read from their own wp_options row via get_option(), exactly where the customizer saves them and blocksy-edit-option writes them (scalars come back in stored string form). Ids not present in the schema but stored in theme_mods (builder trees, third-party mods) are still returned with in_schema=false. When the option declares a finite choice set, the valid values are included.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Option id as listed by blocksy-list-options (the theme_mod name).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'in_schema' => [
                'type' => 'boolean',
                'description' => 'False when the id is a raw stored theme_mod with no definition in the option schema (no default/label/type available).',
            ],
            'type' => [
                'type' => ['string', 'null'],
                'description' => 'Blocksy control type (ct-slider, ct-color-picker, ct-typography, …).',
            ],
            'label' => ['type' => ['string', 'null']],
            'section' => ['type' => ['string', 'null']],
            'default' => [
                'type' => ['string', 'number', 'integer', 'boolean', 'object', 'array', 'null'],
                'description' => 'Schema default (any JSON type). Null when in_schema=false or the option declares no default.',
            ],
            'has_stored_value' => ['type' => 'boolean'],
            'storage' => [
                'type' => 'string',
                'enum' => ['theme_mod', 'option'],
                'description' => 'Where the value lives: `theme_mod` (the theme_mods row) or `option` (the id\'s own wp_options row, declared setting.type=option). blocksy-edit-option writes to the same place.',
            ],
            'value' => [
                'type' => ['string', 'number', 'integer', 'boolean', 'object', 'array', 'null'],
                'description' => 'Effective value: the stored theme_mod when has_stored_value=true, else the schema default. Oversized values (>16 KB JSON) are replaced with a marker string.',
            ],
            'choices' => [
                'type' => ['array', 'null'],
                'items' => ['type' => 'string'],
                'description' => 'Valid values when the option declares a finite choice set (capped at 60; a trailing marker element signals how many valid choices were omitted), else null. Pass these exact strings to blocksy-edit-option.',
            ],
            'choice_labels' => [
                'type' => ['object', 'null'],
                'description' => 'Human label per choice value, only for values whose label differs from the value (e.g. a nav menu name behind its term id). Null when no label adds information.',
            ],
        ],
        'required' => [
            'id',
            'in_schema',
            'type',
            'label',
            'section',
            'default',
            'has_stored_value',
            'storage',
            'value',
            'choices',
            'choice_labels',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_option',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Find ids with blocksy-list-options first. has_stored_value=false with a non-null value means the site runs on the schema default — the theme_mods row has no such key (Blocksy never stores defaults). Responsive values may be scalar or {desktop, tablet, mobile} maps; both are legitimate shapes for the same option.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id?: string} $input
 * @return array{
 *     id: string,
 *     in_schema: bool,
 *     type: string|null,
 *     label: string|null,
 *     section: string|null,
 *     default: mixed,
 *     has_stored_value: bool,
 *     storage: string,
 *     value: mixed,
 *     choices: list<string>|null,
 *     choice_labels: array<string, string>|\stdClass|null,
 * }|WP_Error
 */
function blocksy_get_option(array $input): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $id = isset($input['id']) ? trim($input['id']) : '';
    if ($id === '') {
        return bly_error('bly_invalid_input', message: '`id` is required and must be a non-empty string.');
    }

    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    $resolved = bly_resolve_option($id, $index);
    if ($resolved === null) {
        return bly_error('bly_not_found', sprintf(
            'Option "%s" is neither defined in the Blocksy option schema nor stored in %s. Use blocksy-list-options to discover valid ids.',
            $id,
            bly_mods_key(),
        ));
    }

    $choice_labels = null;
    $choices = bly_option_choices($id, $index, $choice_labels);
    return [
        'id' => $resolved['id'],
        'in_schema' => $resolved['in_schema'],
        'type' => $resolved['type'],
        'label' => $resolved['label'],
        'section' => $resolved['section'],
        'default' => bly_cap_value($resolved['default']),
        'has_stored_value' => $resolved['has_stored_value'],
        'storage' => $resolved['storage'],
        'value' => bly_cap_value($resolved['value']),
        'choices' => $choices,
        'choice_labels' => $choice_labels,
    ];
}

/**
 * The finite choice values an option declares — resolved through
 * bly_choice_vocabulary (the one resolution function gate 1 and the
 * in-lock re-check use too: assoc-map keys AND the blocksy_ordered_keys
 * row lists every ct-select uses; for dynamic sources like the menu
 * picker the declaration is evaluated at call time, so the vocabulary is
 * live — see bly_choice_source) —
 * capped at BLY_MAX_CHOICES with truncation SIGNALED through an explicit
 * trailing marker element, never silent (an agent seeing a bare list
 * would otherwise conclude the missing choices are invalid). Null when
 * the option has no usable choice set or is not in the schema. Fills
 * `$labels` with the informative choice_labels map for the emitted
 * values (null when none).
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @param array<string, string>|\stdClass|null $labels
 * @param-out array<string, string>|\stdClass|null $labels
 * @return list<string>|null
 */
function bly_option_choices(string $id, array $index, array|\stdClass|null &$labels = null): ?array
{
    $labels = null;
    if (!isset($index[$id])) {
        return null;
    }
    $typed = bly_choice_vocabulary($index[$id]);
    /** @var mixed $choices */
    $choices = $index[$id]['option']['choices'] ?? null;
    if ($typed === null || !is_array($choices)) {
        return null;
    }
    $values = array_map('strval', $typed);
    $out = array_slice($values, offset: 0, length: BLY_MAX_CHOICES);
    $emitted_labels = array_intersect_key(bly_choice_labels($choices), array_flip($out));
    if ($emitted_labels !== []) {
        // Object cast: numeric-string keys ("16") coerce to int array
        // keys, which would JSON-encode a sequential map as a bare array
        // and fail the `object` output type.
        $labels = (object) $emitted_labels;
    }
    if (count($values) > BLY_MAX_CHOICES) {
        $out[] = sprintf(
            '[+%d more choices omitted beyond the %d-choice cap — these ARE still valid values]',
            count($values) - BLY_MAX_CHOICES,
            BLY_MAX_CHOICES,
        );
    }
    return $out;
}
