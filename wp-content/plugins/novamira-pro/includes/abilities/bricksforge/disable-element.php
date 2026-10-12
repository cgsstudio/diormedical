<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-disable-element', [
    'label' => __('Disable Bricksforge Custom Element', domain: 'novamira-pro'),
    'description' => __(
        'Deactivates one Bricksforge custom element (or element bundle) by numeric id or slug. Removes the element row from the upstream brf_activated_elements option, preserving every other row verbatim. WARNING: per-element settings stored on the removed row (e.g. Pro Forms captcha/Stripe keys on id 5, Font Awesome kit id on id 1) are removed with it — the same behaviour as deactivating in the Bricksforge UI. Existing pages keep the element in their saved Bricks tree but it stops rendering until re-enabled.',
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
                'description' => 'The element slug from bricksforge-list-custom-elements. Provide id or slug.',
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
            'settings_removed' => [
                'type' => 'boolean',
                'description' => 'True when the removed row carried per-element settings (they are gone with the row).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_disable_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Ask before disabling an element that is used on live pages — its instances stop rendering. settings_removed=true means keys/settings stored on that element row were dropped, like the Bricksforge UI does.',
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
function bricksforge_disable_element(array $input): array|WP_Error
{
    $id = brf_resolve_element_id($input);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $result = brf_with_lock('elements', static function () use ($id): array {
        $rows = brf_raw_element_rows();
        $kept = [];
        $changed = false;
        $settings_removed = false;
        /** @var mixed $row */
        foreach ($rows as $row) {
            if (brf_row_id($row) !== $id) {
                /** @var mixed */
                $kept[] = $row;
                continue;
            }
            $changed = true;
            $row_keys = [];
            if (is_object($row)) {
                $row_keys = array_keys(get_object_vars($row));
            }
            if (is_array($row)) {
                $row_keys = array_keys($row);
            }
            if (array_diff($row_keys, ['id']) !== []) {
                $settings_removed = true;
            }
        }

        if ($changed) {
            brf_update_option('brf_activated_elements', $kept);
        }

        return ['changed' => $changed, 'settings_removed' => $settings_removed];
    });
    if ($result instanceof WP_Error) {
        return $result;
    }
    $changed = $result['changed'];
    $settings_removed = $result['settings_removed'];

    $element = brf_element_map()[$id];

    return [
        'id' => $id,
        'slug' => $element['slug'],
        'name' => $element['name'],
        // Element 13 keeps auto-activating while the API Query Builder tool
        // (18) is on, regardless of the registry row.
        'active' => $id === 13 && brf_tool_active(18),
        'changed' => $changed,
        'settings_removed' => $settings_removed,
    ];
}
