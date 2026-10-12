<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-list-translated-urls', [
    'label' => __('List Weglot Translated URL Slugs', domain: 'novamira-pro'),
    'description' => __(
        'Flattens the Weglot `custom_urls` v3 cluster (per-language slug overrides) into a list of `{language, from_slug, to_slug}` entries. The optional `language` input filters by ISO code. Output is compact and `limit`-clamped for progressive disclosure; use weglot-get-settings for raw cluster shapes.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'language' => [
                'type' => 'string',
                'description' => 'Optional ISO code filter — return only slugs configured for this destination language.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 200,
                'description' => 'Maximum entries returned; clamped to 500.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'matched' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'config_source' => [
                'type' => 'string',
                'enum' => ['weglot', 'local_fallback'],
                'description' => 'Where the slugs were read from. "local_fallback" means Weglot\'s resolver was unavailable, so an empty list means UNKNOWN, not "no slugs configured".',
            ],
            'entries' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'language' => ['type' => 'string'],
                        'from_slug' => ['type' => 'string'],
                        'to_slug' => ['type' => 'string'],
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'Whether Weglot can ever apply this pair. false means the stored from_slug spans more than one path segment, which Weglot\'s matcher compares segment by segment, so the pair is inert: the page is served untranslated. Such entries are never created by add-translated-url (it refuses them) but can arrive from the Weglot dashboard, a v2 to v3 migration or a cloud refresh. delete-translated-url removes them.',
                        ],
                    ],
                    'required' => ['language', 'from_slug', 'to_slug', 'active'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['total', 'matched', 'returned', 'truncated', 'config_source', 'entries'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_list_translated_urls',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Reports the stored slug pairs, resolved through Weglot, in the logical from → to direction (Weglot stores them the other way round; this ability normalizes). Check `active`: a pair with active=false is stored but can never apply, because its from_slug spans several path segments and Weglot matches one segment at a time. Do not report an inert pair as working; delete-translated-url can remove it. Changes made with add/delete-translated-url appear here immediately and are already in force on this site — no push is needed, and weglot-push-options-to-cloud cannot carry them. They stay local to this install: the Weglot project does not learn about them.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{language?: string, limit?: int} $input
 * @return array{total: int, matched: int, returned: int, truncated: bool, config_source: 'weglot'|'local_fallback', entries: list<array{language: string, from_slug: string, to_slug: string, active: bool}>}
 */
function weglot_list_translated_urls(array $input): array
{
    $limit = is_int($input['limit'] ?? null) ? (int) $input['limit'] : 200;
    $limit = max(1, min(500, $limit));
    $language_filter = isset($input['language']) ? strtolower(trim($input['language'])) : '';

    // Resolved so slugs Weglot merged in from the cloud are visible too; the
    // helper flips Weglot's storage orientation into from → to. See
    // wg_options_resolution() and wg_custom_urls_from_options().
    $resolution = wg_options_resolution();
    $map = wg_custom_urls_from_options($resolution['options']);

    $flat = [];
    foreach ($map as $language => $slug_map) {
        foreach ($slug_map as $from => $to) {
            // `active` mirrors what list-exclude-urls does for inert rules:
            // report what is stored, and say plainly when Weglot can never act
            // on it. See wg_slug_is_matchable().
            $flat[] = [
                'language' => $language,
                'from_slug' => $from,
                'to_slug' => $to,
                'active' => wg_slug_is_matchable($from),
            ];
        }
    }
    $total = count($flat);

    if ($language_filter !== '') {
        $flat = array_values(array_filter($flat, static fn(array $row): bool => $row['language'] === $language_filter));
    }
    $matched = count($flat);
    if ($matched > $limit) {
        $flat = array_slice($flat, offset: 0, length: $limit);
    }

    return [
        'total' => $total,
        'matched' => $matched,
        'returned' => count($flat),
        'truncated' => $matched > count($flat),
        'config_source' => $resolution['source'],
        'entries' => $flat,
    ];
}
