<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-delete-exclude-url', [
    'label' => __('Delete Weglot URL Exclusion', domain: 'novamira-pro'),
    'description' => __(
        'Removes a URL-exclusion rule by its `index` (the position reported by weglot-list-exclude-urls). Indexes are re-numbered after each delete — fetch the list again before subsequent deletes in the same batch. Returns weglot_unknown_index when the index is out of range. After delete the remaining rules are stored contiguously (re-indexed).',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'index' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Zero-based index of the rule to remove, as reported by weglot-list-exclude-urls.',
            ],
        ],
        'required' => ['index'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'removed_index' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
            'removed_rule' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'integer'],
                    'type' => ['type' => 'string'],
                    'value' => ['type' => 'string'],
                    'languages' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'active' => ['type' => 'boolean'],
                ],
                'required' => ['index', 'type', 'value', 'languages', 'active'],
                'additionalProperties' => false,
            ],
            'cloud_sync_pending' => [
                'type' => 'boolean',
                'description' => 'Always true: the delete lives in the local options blob and reaches the cloud only on the next Weglot settings-page save.',
            ],
        ],
        'required' => ['persisted', 'removed_index', 'total', 'removed_rule', 'cloud_sync_pending'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_delete_exclude_url',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Indexes shift after each delete. To remove multiple rules in one workflow, either delete from highest index downward, OR re-fetch the list between each delete.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param  array{index: int} $input
 * @return array{persisted: bool, removed_index: int, total: int, removed_rule: array{index: int, type: string, value: string, languages: list<string>, active: bool}, cloud_sync_pending: true}|WP_Error
 */
function weglot_delete_exclude_url(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $index = (int) $input['index'];
    if ($index < 0) {
        return wg_invalid_input('index must be >= 0.');
    }

    return wg_write_options_delta(['excluded_paths', 'exclude_urls'], static function (array $options) use (
        $index,
    ): array|WP_Error {
        /** @var mixed $rules_raw */
        $rules_raw = $options['excluded_paths'] ?? $options['exclude_urls'] ?? [];
        $rules = is_array($rules_raw) ? array_values($rules_raw) : [];

        if (!array_key_exists($index, $rules)) {
            return new WP_Error(
                'weglot_unknown_index',
                sprintf('No exclusion rule at index %d (total=%d).', $index, count($rules)),
                ['status' => 404],
            );
        }

        $removed = wg_normalize_exclude_rule($index, $rules[$index]);
        array_splice(array: $rules, offset: $index, length: 1);
        $options['excluded_paths'] = $rules;
        // Clean the legacy alias if it still lingers, so a future read does
        // not surprise us with a desynced shadow copy.
        unset($options['exclude_urls']);

        return [
            $options,
            [
                'persisted' => true,
                'removed_index' => $index,
                'total' => count($rules),
                'removed_rule' => $removed,
                'cloud_sync_pending' => true,
            ],
        ];
    });
}
