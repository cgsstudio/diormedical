<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-list-snippets', [
    'label' => __('List Code Snippets', domain: 'novamira-pro'),
    'description' => __(
        'Lists snippets with optional filters. Response is a compact summary per snippet — id, name, type, scope, tags, priority, active, network, modified — WITHOUT the `code` field so listings stay small. Call code-snippets-get-snippet with a specific id to read the executable code. Filters: `type` (php|html|css|js|cond), `scope`, `active` (bool), `tag` (substring match against the snippet\'s tag list), `search` (case-insensitive substring match against name, desc, and code). Pagination: `limit` (default 100, max 500 — values above the cap are REJECTED with ability_invalid_input, not silently clamped), `offset` (default 0, must be ≥0). `total` in the response always reflects the unpaginated match count so the agent knows whether to keep paging.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Restrict to a single snippet type. One of php | html | css | js | cond. Omit to include every type.',
            ],
            'scope' => [
                'type' => 'string',
                'description' => 'Restrict to a single scope (e.g. global, site-css). Call code-snippets-get-types-schema for the full list per type.',
            ],
            'active' => [
                'type' => 'boolean',
                'description' => 'When true return only active snippets; when false return only inactive. Omit to include both.',
            ],
            'tag' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against any tag on the snippet. Useful when an agent uses tags for its own bookkeeping ("nvp", "experimental", …).',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against name, desc, and code. Use when the user asks for a snippet by intent ("the one that handles email", "the redirect snippet") instead of by id or tag. NB: scanning the code body means snippets containing secrets (API keys, tokens) become discoverable by search — pass `search_in_code: false` to limit to name+desc when you are exploring on behalf of a user who didn\'t author the snippets.',
            ],
            'search_in_code' => [
                'type' => 'boolean',
                'description' => 'When false, `search` matches only name + desc (skipping the code body). Defaults to true for back-compat.',
                'default' => true,
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of snippet summaries to return. Default 100, hard cap 500.',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 100,
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Number of matching snippets to skip before returning records (for paging). Default 0.',
                'minimum' => 0,
                'default' => 0,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'snippets' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['snippets', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_list_snippets',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use filters (type, scope, active, tag) to keep the list focused. The summary omits `code` for size — fetch the body via code-snippets-get-snippet only for snippets you actually want to read or edit.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string, scope?: string, active?: bool, tag?: string, search?: string, search_in_code?: bool, limit?: int, offset?: int} $input
 * @return array{snippets: list<array<string, mixed>>, total: int}
 */
function code_snippets_list_snippets(array $input): array
{
    $filters = [
        'type' => $input['type'] ?? '',
        'scope' => $input['scope'] ?? '',
        'active' => array_key_exists('active', $input) ? $input['active'] : null,
        'tag' => isset($input['tag']) ? strtolower(trim($input['tag'])) : '',
        'search' => isset($input['search']) ? strtolower(trim($input['search'])) : '',
        'search_in_code' => $input['search_in_code'] ?? true,
    ];
    // The JSON-schema layer clamps min/max for us, but apply explicit
    // defensive caps here so a direct PHP caller still gets the documented
    // behaviour (default 100, hard 500).
    $limit = isset($input['limit']) ? max(1, min(500, $input['limit'])) : 100;
    $offset = isset($input['offset']) ? max(0, $input['offset']) : 0;

    $matched = [];
    foreach (\Code_Snippets\get_snippets() as $snippet) {
        if (!cs_list_match($snippet, $filters)) {
            continue;
        }
        $matched[] = $snippet;
    }

    $total = count($matched);
    $page = array_slice($matched, $offset, $limit);
    $out = [];
    foreach ($page as $snippet) {
        $out[] = cs_snippet_summary($snippet);
    }

    return ['snippets' => $out, 'total' => $total];
}

/**
 * Filter helper for list-snippets. Returns true when the snippet
 * survives every active filter. Bundled as one struct so each call site
 * gets a single keyword-style arg and the helper signature stays small
 * as we add filters (sub-agent CS4 asked for `search`).
 *
 * @param array{type: string, scope: string, active: ?bool, tag: string, search: string, search_in_code: bool} $filters
 */
function cs_list_match(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet, array $filters): bool
{
    if ($filters['type'] !== '' && $snippet->type !== $filters['type']) {
        return false;
    }
    if ($filters['scope'] !== '' && $snippet->scope !== $filters['scope']) {
        return false;
    }
    if ($filters['active'] !== null && $snippet->active !== $filters['active']) {
        return false;
    }
    if ($filters['tag'] !== '') {
        $haystack = strtolower(implode(',', $snippet->tags));
        if (!str_contains($haystack, $filters['tag'])) {
            return false;
        }
    }
    if ($filters['search'] !== '') {
        $parts = [$snippet->name, $snippet->desc];
        if ($filters['search_in_code']) {
            $parts[] = $snippet->code;
        }
        $haystack = strtolower(implode("\n", $parts));
        if (!str_contains($haystack, $filters['search'])) {
            return false;
        }
    }
    return true;
}
