<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-delete-translated-url', [
    'label' => __('Delete Weglot Translated URL Slug', domain: 'novamira-pro'),
    'description' => __(
        'Removes a translated URL slug entry from the Weglot `custom_urls` cluster, identified by `language` + `from_slug`. Returns weglot_unknown_slug when no entry matches. If removing the entry leaves an empty per-language map the language key is dropped to keep the persisted blob tidy. IMPORTANT: the deletion takes effect on THIS site immediately and can NOT be published to the Weglot Cloud — slugs live on a separate resource (/translations/slugs) that the settings push does not carry. If the slug also exists on the Weglot project, remove it at dashboard.weglot.com — possible only on a plan with the slug feature: when weglot-check-setup reports supports_slugs=false the project cannot hold slugs at all, so there is nothing to remove there.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'language' => [
                'type' => 'string',
                'minLength' => 2,
                'description' => 'ISO destination-language code.',
            ],
            'from_slug' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Original source-language URL slug (with or without leading slash).',
            ],
        ],
        'required' => ['language', 'from_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'language' => ['type' => 'string'],
            'from_slug' => ['type' => 'string'],
            'removed_to_slug' => ['type' => 'string'],
            'cloud_sync_pending' => [
                'type' => 'boolean',
                'description' => 'Always false: nothing is awaiting publication because slugs cannot be published from here. The deletion is already in force on this site.',
            ],
        ],
        'required' => ['persisted', 'language', 'from_slug', 'removed_to_slug', 'cloud_sync_pending'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_delete_translated_url',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pair (language, from_slug) must be present in the configured custom_urls. Use weglot-list-translated-urls to discover the pairs.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{language: string, from_slug: string} $input
 * @return array{persisted: bool, language: string, from_slug: string, removed_to_slug: string, cloud_sync_pending: false}|WP_Error
 */
function weglot_delete_translated_url(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $language = strtolower(trim($input['language']));
    // LOOKUP normalization, not the write guard: a stored key is allowed to be
    // something we would refuse to create. The write guard rejects a
    // multi-segment slug because it can never match — but applying it here made
    // a foreign multi-segment pair permanently undeletable while
    // list-translated-urls kept reporting it. Removing a pair that does nothing
    // is exactly what a caller should be able to do.
    $from = wg_normalize_slug_lookup($input['from_slug']);
    if (strlen($language) < 2) {
        return wg_invalid_input('language must be at least 2 characters after trim.');
    }
    if ($from === '') {
        return wg_invalid_input(
            'from_slug must be non-empty after sanitization. Control chars, absolute URLs and parent-directory segments are rejected.',
        );
    }

    return wg_write_options_delta(['custom_urls'], static function (array $options) use (
        $language,
        $from,
    ): array|WP_Error {
        $normalized = wg_custom_urls_from_options($options);

        if (!isset($normalized[$language][$from])) {
            return new WP_Error(
                'weglot_unknown_slug',
                sprintf('No translated slug for language="%s", from_slug="%s".', $language, $from),
                ['status' => 404],
            );
        }

        $removed = $normalized[$language][$from];

        // Work on the RAW cluster so malformed entries from other actors are
        // preserved. The normalized view located the entry; we now patch the
        // raw structure: drop matching pairs in both the canonical lowercase
        // form AND any mixed-case variant that produced the same canonical
        // entry.
        //
        // The match is on the stored VALUE, not the key: Weglot stores this
        // cluster as translated => original, so the from_slug being deleted
        // sits in the value position. See wg_custom_urls_forget_entry.
        $raw = wg_custom_urls_raw($options);
        /** @var mixed $stored_map */
        foreach ($raw as $stored_lang => $stored_map) {
            if (!is_string($stored_lang) || strtolower($stored_lang) !== $language || !is_array($stored_map)) {
                continue;
            }
            [$stored_map, $dropped] = wg_custom_urls_forget_entry($stored_map, original: $from);
            if ($dropped === 0) {
                continue;
            }
            if ($stored_map === []) {
                unset($raw[$stored_lang]);
                continue;
            }
            $raw[$stored_lang] = $stored_map;
        }
        $options['custom_urls'] = $raw;

        return [
            $options,
            [
                'persisted' => true,
                'language' => $language,
                'from_slug' => $from,
                'removed_to_slug' => $removed,
                'cloud_sync_pending' => false,
            ],
        ];
    });
}
