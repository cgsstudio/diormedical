<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/beaver-builder-list-global-colors', [
    'label' => __('List Beaver Builder Global Colors', domain: 'novamira-pro'),
    'description' => __(
        'Lists Beaver Builder Global Colors — the reusable color design tokens (Global Styles). Each token has a `uid`, a `label`, the `color` value (hex without `#`, or rgb/rgba), and the `css_var` it generates (e.g. `--fl-global-brand`). Prefer referencing a token over hardcoding a hex on every node; discover them here first and create-global-color when a needed token is missing. To bind a node setting to a token, write a connection on the node (`settings.connections.<field> = {object:"site", property:"global_color_<uid>", field:"<field>"}`) via edit-node/set-layout.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => new \stdClass(),
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'colors' => ['type' => 'array', 'items' => ['type' => 'object']],
            'count' => ['type' => 'integer'],
            'prefix' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_list_global_colors',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The reusable color tokens to PREFER over raw hex. Each row gives the `uid` (used to edit/delete and to bind a node via a connection) and the `css_var` it emits. If a needed brand/accent color is missing, create-global-color instead of hardcoding it on every node.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-create-global-color', [
    'label' => __('Create Beaver Builder Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Beaver Builder Global Color token. Pass a `label` (display name; also the basis for the generated CSS var) and a `color` (hex with or without `#`, or rgb/rgba). Returns the minted `uid` and the `css_var`. Use list-global-colors first to avoid duplicates.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => ['type' => 'string', 'description' => 'Display name, e.g. "Brand Primary".'],
            'color' => ['type' => 'string', 'description' => 'Hex (with or without `#`) or rgb()/rgba() value.'],
        ],
        'required' => ['label', 'color'],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_create_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Mint a reusable color token once, then reference it everywhere instead of repeating a hex. The `uid` in the response is what you bind a node to (via a site/global_color connection).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-edit-global-color', [
    'label' => __('Edit Beaver Builder Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Beaver Builder Global Color by `uid`. Pass `label` and/or `color` to change them; omitted fields are preserved. Changing a token updates every node bound to it. Find the `uid` with list-global-colors.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'uid' => ['type' => 'string', 'description' => 'The token uid from list-global-colors.'],
            'label' => ['type' => 'string', 'description' => 'New display name (optional).'],
            'color' => ['type' => 'string', 'description' => 'New hex/rgb/rgba value (optional).'],
        ],
        'required' => ['uid'],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_edit_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update by uid. Editing a token cascades to every node bound to it — that is the point of tokens.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-delete-global-color', [
    'label' => __('Delete Beaver Builder Global Color', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Beaver Builder Global Color by `uid`. Nodes that were bound to this token lose the binding and fall back to their stored plain value. Find the `uid` with list-global-colors.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'uid' => ['type' => 'string', 'description' => 'The token uid from list-global-colors.'],
        ],
        'required' => ['uid'],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_delete_global_color',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent. Any node bound to this token via a site/global_color connection loses the binding and reverts to its fallback value.',
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
function beaver_builder_list_global_colors(array $input): array|WP_Error
{
    if (!bb_global_styles_available()) {
        return bb_gs_unavailable_error();
    }
    $prefix = bb_gs_prefix();
    $colors = array_map(static fn(array $entry): array => bb_gs_color_row(
        entry: $entry,
        prefix: $prefix,
    ), bb_gs_color_entries());
    return ['colors' => $colors, 'count' => count($colors), 'prefix' => $prefix];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_create_global_color(array $input): array|WP_Error
{
    if (!bb_global_styles_available()) {
        return bb_gs_unavailable_error();
    }
    $label = bb_string_arg($input['label'] ?? '');
    $color = bb_gs_normalize_color(bb_string_arg($input['color'] ?? ''));
    if ($label === '' || $color === '') {
        return bb_error(
            code: 'beaver_builder_invalid_global_color',
            message: 'Pass a non-empty `label` and `color`.',
            data: ['status' => 400],
        );
    }

    $entries = bb_gs_color_entries();
    $entries[] = ['label' => $label, 'color' => $color];
    bb_gs_save_colors($entries);

    $prefix = bb_gs_prefix();
    $created = bb_gs_find_color(label: $label, color: $color);
    return [
        'created' => true,
        'color' => $created !== null
            ? bb_gs_color_row(entry: $created, prefix: $prefix)
            : [
                'uid' => '',
                'label' => $label,
                'color' => $color,
                'css_var' => bb_gs_css_var(label: $label, prefix: $prefix),
            ],
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_edit_global_color(array $input): array|WP_Error
{
    if (!bb_global_styles_available()) {
        return bb_gs_unavailable_error();
    }
    $uid = bb_string_arg($input['uid'] ?? '');
    if ($uid === '') {
        return bb_error(
            code: 'beaver_builder_invalid_global_color',
            message: 'Pass the `uid` of the color to edit.',
            data: ['status' => 400],
        );
    }

    $entries = bb_gs_color_entries();
    $found = false;
    foreach ($entries as $index => $entry) {
        if ($entry['uid'] !== $uid) {
            continue;
        }
        if (array_key_exists('label', $input)) {
            $entries[$index]['label'] = bb_string_arg($input['label']);
        }
        if (array_key_exists('color', $input)) {
            $normalized = bb_gs_normalize_color(bb_string_arg($input['color']));
            if ($normalized !== '') {
                $entries[$index]['color'] = $normalized;
            }
        }
        $found = true;
    }
    if (!$found) {
        return bb_error(
            code: 'beaver_builder_global_color_not_found',
            message: sprintf('No Global Color with uid `%s`.', $uid),
            data: ['status' => 404],
        );
    }

    bb_gs_save_colors($entries);
    $prefix = bb_gs_prefix();
    $row = bb_gs_find_color_by_uid($uid);
    return ['edited' => true, 'color' => $row !== null ? bb_gs_color_row(entry: $row, prefix: $prefix) : null];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_delete_global_color(array $input): array|WP_Error
{
    if (!bb_global_styles_available()) {
        return bb_gs_unavailable_error();
    }
    $uid = bb_string_arg($input['uid'] ?? '');
    if ($uid === '') {
        return bb_error(
            code: 'beaver_builder_invalid_global_color',
            message: 'Pass the `uid` of the color to delete.',
            data: ['status' => 400],
        );
    }

    $entries = bb_gs_color_entries();
    $kept = array_values(array_filter($entries, static fn(array $entry): bool => $entry['uid'] !== $uid));
    if (count($kept) === count($entries)) {
        return bb_error(
            code: 'beaver_builder_global_color_not_found',
            message: sprintf('No Global Color with uid `%s`.', $uid),
            data: ['status' => 404],
        );
    }

    bb_gs_save_colors($kept);
    return ['deleted' => true, 'uid' => $uid, 'remaining' => count($kept)];
}

// ---- helpers --------------------------------------------------------------

function bb_gs_unavailable_error(): WP_Error
{
    return bb_error(
        code: 'beaver_builder_global_styles_unavailable',
        message: 'Beaver Builder Global Styles is not available on this site.',
        data: ['status' => 503],
    );
}

function bb_gs_settings(): \stdClass
{
    /** @var mixed $settings */
    $settings = \FLBuilderGlobalStyles::get_settings(false);
    return $settings instanceof \stdClass ? $settings : new \stdClass();
}

function bb_gs_prefix(): string
{
    $prefix = bb_string_arg(bb_object_property(object: bb_gs_settings(), property: 'prefix'));
    return $prefix !== '' ? $prefix : 'fl-global';
}

/**
 * Beaver derives a color's CSS var name from its label: lowercase, `_`/space to
 * `-`, then strip everything but `[a-z0-9-]` (FLBuilderGlobalStyles::label_to_key).
 */
function bb_gs_color_key(string $label): string
{
    $key = str_replace(search: ['_', ' '], replace: '-', subject: strtolower($label));
    return preg_replace(pattern: '/[^a-z0-9-]/', replacement: '', subject: $key) ?? '';
}

function bb_gs_css_var(string $label, string $prefix): string
{
    return '--' . $prefix . '-' . bb_gs_color_key($label);
}

/**
 * The real color tokens, dropping Beaver's empty placeholder entries.
 *
 * @return list<array{label: string, color: string, uid: string}>
 */
function bb_gs_color_entries(): array
{
    $colors = bb_object_property(object: bb_gs_settings(), property: 'colors');
    if (!is_array($colors)) {
        return [];
    }
    $entries = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($colors as $color) {
        if (!is_array($color)) {
            continue;
        }
        $value = bb_array_string(array: $color, key: 'color');
        if ($value === '') {
            continue;
        }
        $entries[] = [
            'label' => bb_array_string(array: $color, key: 'label'),
            'color' => $value,
            'uid' => bb_array_string(array: $color, key: 'uid'),
        ];
    }
    return $entries;
}

/**
 * @param array{label: string, color: string, uid: string} $entry
 * @return array{uid: string, label: string, color: string, css_var: string}
 */
function bb_gs_color_row(array $entry, string $prefix): array
{
    return [
        'uid' => $entry['uid'],
        'label' => $entry['label'],
        'color' => $entry['color'],
        'css_var' => bb_gs_css_var(label: $entry['label'], prefix: $prefix),
    ];
}

/**
 * @return array{label: string, color: string, uid: string}|null
 */
function bb_gs_find_color(string $label, string $color): ?array
{
    $match = null;
    foreach (bb_gs_color_entries() as $entry) {
        if ($entry['label'] !== $label || $entry['color'] !== $color) {
            continue;
        }
        $match = $entry;
    }
    return $match;
}

/**
 * @return array{label: string, color: string, uid: string}|null
 */
function bb_gs_find_color_by_uid(string $uid): ?array
{
    foreach (bb_gs_color_entries() as $entry) {
        if ($entry['uid'] === $uid) {
            return $entry;
        }
    }
    return null;
}

function bb_gs_normalize_color(string $color): string
{
    $color = trim($color);
    if ($color === '') {
        return '';
    }
    if (preg_match('/^(rgb|rgba|hsl|hsla|var)\s*\(/i', $color) === 1) {
        return $color;
    }
    return ltrim(string: $color, characters: '#');
}

/**
 * @param list<array{label: string, color: string, uid?: string}> $entries
 */
function bb_gs_save_colors(array $entries): void
{
    \FLBuilderGlobalStyles::save_settings(['colors' => $entries]);
}
