<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use Weglot\Client\Api\LanguageEntry;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-list-languages', [
    'label' => __('List Weglot Supported Languages', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full catalog of languages Weglot supports (110+), each with its ISO code, English name, native name, and RTL flag. Use this catalog to look up the code expected by weglot-add-destination-language and weglot-set-original-language. The `query` input filters by code prefix or name substring; `limit` clamps the response size for progressive disclosure.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Optional case-insensitive substring filter applied to the code, English name, or native name.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 250,
                'description' => 'Maximum entries returned; clamped to 500. Defaults to 250 (enough for the full catalog today).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer', 'description' => 'Total catalog size before query filter and limit.'],
            'matched' => [
                'type' => 'integer',
                'description' => 'Entries that matched the query filter (before limit). Equals total when query is empty.',
            ],
            'returned' => [
                'type' => 'integer',
                'description' => 'Entries actually returned (matched, clamped by limit).',
            ],
            'truncated' => ['type' => 'boolean', 'description' => 'True when limit clipped the matched set.'],
            'languages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string'],
                        'english_name' => ['type' => 'string'],
                        'native_name' => ['type' => 'string'],
                        'rtl' => ['type' => 'boolean'],
                    ],
                    'required' => ['code', 'english_name', 'native_name', 'rtl'],
                ],
            ],
        ],
        'required' => ['total', 'matched', 'returned', 'truncated', 'languages'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_list_languages',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Static catalog from the bundled Weglot SDK — does not call the cloud and is safe to call at any time. Use `query` to narrow down ("ja", "spanish", "ar") before assuming a code; codes are 2-letter ISO 639-1 with regional variants ("pt-br", "zh-tw").',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{query?: string, limit?: int} $input
 * @return array{total: int, matched: int, returned: int, truncated: bool, languages: list<array{code: string, english_name: string, native_name: string, rtl: bool}>}
 */
function weglot_list_languages(array $input): array
{
    $query = isset($input['query']) ? strtolower(trim($input['query'])) : '';
    $limit = is_int($input['limit'] ?? null) ? (int) $input['limit'] : 250;
    $limit = max(1, min(500, $limit));

    $entries = wg_collect_language_entries();
    $total = count($entries);

    if ($query !== '') {
        $entries = array_values(array_filter(
            $entries,
            static fn(array $entry): bool => (
                str_contains(strtolower($entry['code']), $query)
                || str_contains(strtolower($entry['english_name']), $query)
                || str_contains(strtolower($entry['native_name']), $query)
            ),
        ));
    }

    $matched = count($entries);
    if ($matched > $limit) {
        $entries = array_slice($entries, offset: 0, length: $limit);
    }

    return [
        'total' => $total,
        'matched' => $matched,
        'returned' => count($entries),
        'truncated' => $matched > count($entries),
        'languages' => $entries,
    ];
}

/**
 * Pull the full language catalog from the Weglot SDK. Returns a normalized
 * compact shape — we always present `code`, `english_name`, `native_name`,
 * `rtl` even when the SDK omits any of them.
 *
 * @return list<array{code: string, english_name: string, native_name: string, rtl: bool}>
 */
function wg_collect_language_entries(): array
{
    if (!function_exists('weglot_get_languages_available')) {
        return [];
    }

    /** @var mixed $collection */
    $collection = \weglot_get_languages_available();
    if (!is_iterable($collection)) {
        return [];
    }

    $out = [];
    /** @var mixed $entry */
    foreach ($collection as $entry) {
        if (!$entry instanceof LanguageEntry) {
            continue;
        }
        $code = $entry->getInternalCode();
        if ($code === '') {
            continue;
        }
        $out[] = [
            'code' => $code,
            'english_name' => $entry->getEnglishName(),
            'native_name' => $entry->getLocalName(),
            'rtl' => $entry->isRtl(),
        ];
    }
    usort($out, static fn(array $a, array $b): int => strcmp($a['code'], $b['code']));
    return $out;
}
