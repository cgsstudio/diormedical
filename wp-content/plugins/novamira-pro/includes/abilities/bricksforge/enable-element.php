<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-enable-element', [
    'label' => __('Enable Bricksforge Custom Element', domain: 'novamira-pro'),
    'description' => __(
        'Activates one Bricksforge custom element (or element bundle — id 5 is the whole Pro Forms family) by numeric id or slug. Writes the upstream brf_activated_elements option preserving its array-of-objects shape and any per-element settings stored on other rows. The element registers in Bricks on the NEXT request — a bricks-list-elements call in this same conversation will already see it. Already-active elements are a no-op.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'The element id from bricksforge-list-custom-elements. Provide id or slug.',
            ],
            'slug' => [
                'type' => 'string',
                'description' => 'The element slug from bricksforge-list-custom-elements (e.g. "pro-forms"). Provide id or slug.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'changed' => ['type' => 'boolean'],
            'bricks_elements' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_enable_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Element ids are a separate numbering space from tool ids. After enabling, the bricks_elements names become buildable through the novamira/bricks-* abilities.',
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
function bricksforge_enable_element(array $input): array|WP_Error
{
    $id = brf_resolve_element_id($input);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $changed = brf_with_lock('elements', static function () use ($id): bool {
        $rows = brf_raw_element_rows();
        /** @var mixed $row */
        foreach ($rows as $row) {
            if (brf_row_id($row) === $id) {
                return false;
            }
        }
        $new_row = new \stdClass();
        $new_row->id = $id;
        $rows[] = $new_row;
        brf_update_option('brf_activated_elements', $rows);

        return true;
    });
    if ($changed instanceof WP_Error) {
        return $changed;
    }

    $element = brf_element_map()[$id];

    return [
        'id' => $id,
        'slug' => $element['slug'],
        'name' => $element['name'],
        'active' => true,
        'changed' => $changed,
        'bricks_elements' => $element['bricks_elements'],
    ];
}
