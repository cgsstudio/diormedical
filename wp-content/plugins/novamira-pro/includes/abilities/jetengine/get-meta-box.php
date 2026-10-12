<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-get-meta-box', [
    'label' => __('Get JetEngine Meta Box', domain: 'novamira-pro'),
    'description' => __(
        'Return the full native JetEngine meta-box record by id, enriched with an editable flag and a source flag (db|php). The returned shape is the same one accepted by create-meta-box and edit-meta-box, so a round-trip is safe.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Meta box id (slug-like).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'args' => ['type' => 'object'],
            'meta_fields' => ['type' => 'array'],
            'editable' => ['type' => 'boolean'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
        ],
        'required' => ['id', 'name', 'args', 'meta_fields', 'editable', 'source'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_get_meta_box',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Build the normalised record returned by get-meta-box from a raw DB row.
 *
 * @param array<string, mixed> $row
 * @return array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>, editable: bool, source: 'db'|'php'}
 */
function jetengine_build_meta_box_record(string $id, array $row): array
{
    $source = jetengine_meta_box_source($id) ?? 'db';

    /** @var array<string, mixed> $args */
    $args = is_array($row['args'] ?? null) ? $row['args'] : [];

    /** @var list<array<string, mixed>> $meta_fields */
    $meta_fields = is_array($row['meta_fields'] ?? null) ? array_values($row['meta_fields']) : [];

    return [
        'id' => (string) ($row['id'] ?? $id),
        'name' => (string) ($args['name'] ?? $id),
        'args' => $args,
        'meta_fields' => $meta_fields,
        'editable' => $source === 'db',
        'source' => $source,
    ];
}

/**
 * @param array{id: string} $input
 * @return array{id: string, name: string, args: array<string, mixed>, meta_fields: list<array<string, mixed>>, editable: bool, source: 'db'|'php'}|WP_Error
 */
function jetengine_get_meta_box(array $input): array|WP_Error
{
    $guard = jetengine_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $input['id'];
    if ($id === '') {
        return new WP_Error('invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    $data = jet_engine()->meta_boxes->data ?? null;
    if ($data === null) {
        return new WP_Error('meta_box_not_found', __('Meta box not found.', domain: 'novamira-pro'));
    }

    $found = null;
    foreach ($data->get_items() as $row) {
        if ((string) ($row['id'] ?? '') !== $id) {
            continue;
        }

        $found = $row;
        break;
    }

    if ($found === null) {
        return new WP_Error('meta_box_not_found', __('Meta box not found.', domain: 'novamira-pro'));
    }

    return jetengine_build_meta_box_record($id, $found);
}
