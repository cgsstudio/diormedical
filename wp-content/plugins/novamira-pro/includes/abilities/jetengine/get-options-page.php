<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-get-options-page', [
    'label' => __('Get JetEngine Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Return the full native JetEngine options-page record by id, enriched with editable and source. The shape round-trips with create-options-page and edit-options-page.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'menu_name' => ['type' => 'string'],
            'menu_position' => ['type' => ['integer', 'null']],
            'parent' => ['type' => ['string', 'null']],
            'icon' => ['type' => ['string', 'null']],
            'capability' => ['type' => ['string', 'null']],
            'hide_field_names' => ['type' => 'boolean'],
            'meta_fields' => ['type' => 'array'],
            'editable' => ['type' => 'boolean'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
        ],
        'required' => ['id', 'name', 'menu_name', 'meta_fields', 'editable', 'source', 'hide_field_names'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_get_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * Unserialize the options-page `labels` column and extract name/menu_name.
 *
 * @param array<string, mixed> $row
 * @return array{name: string, menu_name: string}
 */
function op_extract_labels(string $id, array $row): array
{
    /** @var mixed $unserialized */
    $unserialized = maybe_unserialize((string) ($row['labels'] ?? ''));
    $labels = is_array($unserialized) ? $unserialized : [];
    $name = (string) ($labels['name'] ?? $id);
    return [
        'name' => $name,
        'menu_name' => (string) ($labels['menu_name'] ?? $name),
    ];
}

/**
 * Unserialize the options-page `args` column and extract scalar settings.
 *
 * @param array<string, mixed> $row
 * @return array{menu_position: int|null, parent: string|null, icon: string|null, capability: string|null, hide_field_names: bool}
 */
function op_extract_args(array $row): array
{
    /** @var mixed $unserialized */
    $unserialized = maybe_unserialize((string) ($row['args'] ?? ''));
    $args = is_array($unserialized) ? $unserialized : [];
    return [
        'menu_position' => isset($args['position']) && $args['position'] !== '' ? (int) $args['position'] : null,
        'parent' => isset($args['parent']) && $args['parent'] !== '' ? (string) $args['parent'] : null,
        'icon' => isset($args['icon']) && $args['icon'] !== '' ? (string) $args['icon'] : null,
        'capability' => isset($args['capability']) && $args['capability'] !== '' ? (string) $args['capability'] : null,
        'hide_field_names' => filter_var($args['hide_field_names'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
}

/**
 * Build the normalised record returned by get-options-page from a raw DB row.
 *
 * JetEngine stores `name`/`menu_name` inside the serialized `labels` column,
 * and `parent`, `icon`, `capability`, `hide_field_names` etc. inside the
 * serialized `args` column. `meta_fields` is also serialized.
 *
 * @param array<string, mixed> $row
 * @return array{id: string, name: string, menu_name: string, menu_position: int|null, parent: string|null, icon: string|null, capability: string|null, hide_field_names: bool, meta_fields: list<array<string, mixed>>, editable: bool, source: 'db'|'php'}
 */
function jetengine_build_options_page_record(string $id, array $row): array
{
    $source = jetengine_options_page_source($id) ?? 'db';
    $name_parts = op_extract_labels($id, $row);
    $arg_parts = op_extract_args($row);

    /** @var mixed $unserialized_fields */
    $unserialized_fields = maybe_unserialize((string) ($row['meta_fields'] ?? ''));
    /** @var list<array<string, mixed>> $meta_fields */
    $meta_fields = is_array($unserialized_fields) ? array_values($unserialized_fields) : [];

    return [
        // The agent-facing identifier is the slug, NOT the auto-increment
        // wp_jet_post_types.id column. Always echo back the slug the caller
        // passed so subsequent edit/delete calls round-trip cleanly.
        'id' => $id,
        'name' => $name_parts['name'],
        'menu_name' => $name_parts['menu_name'],
        'menu_position' => $arg_parts['menu_position'],
        'parent' => $arg_parts['parent'],
        'icon' => $arg_parts['icon'],
        'capability' => $arg_parts['capability'],
        'hide_field_names' => $arg_parts['hide_field_names'],
        'meta_fields' => $meta_fields,
        'editable' => $source === 'db',
        'source' => $source,
    ];
}

/**
 * @param array{id: string} $input
 * @return array{id: string, name: string, menu_name: string, menu_position: int|null, parent: string|null, icon: string|null, capability: string|null, hide_field_names: bool, meta_fields: list<array<string, mixed>>, editable: bool, source: 'db'|'php'}|WP_Error
 */
function jetengine_get_options_page(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $input['id'];
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $data = jet_engine()->options_pages->data ?? null;
    if ($data === null) {
        return new WP_Error('options_page_not_found', __('Options page not found.', domain: 'novamira-pro'));
    }

    $found = null;
    foreach ($data->get_items() as $row) {
        if ((string) ($row['slug'] ?? '') !== $id) {
            continue;
        }

        $found = $row;
        break;
    }

    if ($found === null) {
        return new WP_Error('options_page_not_found', __('Options page not found.', domain: 'novamira-pro'));
    }

    return jetengine_build_options_page_record($id, $found);
}
