<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

/**
 * Ability: List items belonging to a pod (paginated, filterable).
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_PODS_LIST_ITEMS_DEFAULT_LIMIT = 20;

const NOVAMIRA_PODS_LIST_ITEMS_MAX_LIMIT = 100;

wp_register_ability('novamira/pods-list-items', [
    'label' => __('List Pods Items', domain: 'novamira-pro'),
    'description' => __(
        'Lists items in a pod, paginated. Returns each item as `{id, fields}`. Pass `fields` to project specific keys (default: every field on the pod) — useful when items have a wysiwyg / large blob you do not need in the listing.',
        domain: 'novamira-pro',
    ),
    'category' => 'pods',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pod' => ['type' => 'string'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => NOVAMIRA_PODS_LIST_ITEMS_MAX_LIMIT,
                'default' => NOVAMIRA_PODS_LIST_ITEMS_DEFAULT_LIMIT,
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'orderby' => [
                'type' => 'string',
                'description' => 'SQL `ORDER BY` clause. Examples: `t.id DESC`, `t.post_title ASC`. Defaults to Pods\' default ordering.',
            ],
            'where' => [
                'type' => 'string',
                'description' => 'SQL `WHERE` clause to filter items (raw SQL — caller is responsible for safety, the permission_callback restricts to admins).',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Search text matched against searchable fields.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Field names to include for each item. Omit to return every field.',
            ],
        ],
        'required' => ['pod'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'fields' => ['type' => 'object'],
                    ],
                ],
            ],
            'total' => ['type' => 'integer', 'description' => 'Items in this page.'],
            'total_found' => [
                'type' => 'integer',
                'description' => 'Total items matching the query (independent of `limit`).',
            ],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Pods\\pods_list_items',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default `limit` is 20, capped at 100. For pods with thousands of items, paginate with `offset`. The `where` clause is raw SQL — write it in terms of the pod\'s storage table (`t.<column>` for ACT, `t.post_title` etc. for post-type pods).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_list_items(array $input): array
{
    $pod_name = pods_input_string($input, key: 'pod');
    if ($pod_name === null) {
        return ['error' => '`pod` is required.'];
    }

    $pod = pods($pod_name);
    if ($pod === false || !$pod->valid()) {
        return ['error' => sprintf('Pod "%s" not found or not accessible.', $pod_name)];
    }

    $find_params = pods_build_list_items_find_params($input);
    $fields_filter = pods_resolve_fields_filter($input);

    // `find()` runs raw SQL (the optional `where` clause is the agent's
    // verbatim SQL); a malformed clause raises a `Throwable` from
    // PodsData. Catch it and surface as a clean ability error so an
    // invalid filter does not bring the request down with a 500.
    try {
        $pod->find($find_params);
    } catch (\Throwable $e) {
        return ['error' => 'Invalid query: ' . $e->getMessage()];
    }

    $items = [];
    while ($pod->fetch()) {
        $id = (int) $pod->id();
        /** @var mixed $exported */
        $exported = $pod->export($fields_filter, $id);
        $items[] = [
            'id' => $id,
            'fields' => is_array($exported) ? (object) $exported : (object) [],
        ];
    }

    $total_found = method_exists($pod, 'total_found') ? (int) $pod->total_found() : count($items);

    return [
        'items' => $items,
        'total' => count($items),
        'total_found' => $total_found,
    ];
}

/**
 * Build the `Pods::find()` params from the ability input. Pulled out so the
 * main callback stays under the cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_build_list_items_find_params(array $input): array
{
    $limit_raw = pods_input_int($input, key: 'limit') ?? NOVAMIRA_PODS_LIST_ITEMS_DEFAULT_LIMIT;
    $limit = max(1, min(NOVAMIRA_PODS_LIST_ITEMS_MAX_LIMIT, $limit_raw));
    $offset = max(0, pods_input_int($input, key: 'offset') ?? 0);

    $params = ['limit' => $limit, 'offset' => $offset];

    foreach (['orderby', 'where'] as $string_key) {
        $value = pods_input_string($input, key: $string_key);
        if ($value !== null) {
            $params[$string_key] = $value;
        }
    }

    $search = pods_input_string($input, key: 'search');
    if ($search !== null) {
        $params['search'] = true;
        $params['search_query'] = $search;
    }
    return $params;
}

/**
 * Resolve the optional `fields` projection input into a clean list of field
 * names — or null when omitted (Pods then exports every field).
 *
 * @param array<string, mixed> $input
 * @return list<string>|null
 */
function pods_resolve_fields_filter(array $input): ?array
{
    /** @var mixed $raw */
    $raw = $input['fields'] ?? null;
    if (!is_array($raw)) {
        return null;
    }
    $filtered = array_values(array_filter($raw, callback: 'is_string'));
    return $filtered === [] ? null : $filtered;
}
