<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-add-translated-url', [
    'label' => __('Add Weglot Translated URL Slug', domain: 'novamira-pro'),
    'description' => __(
        'Sets a translated URL slug for a destination language under the Weglot `custom_urls` cluster (e.g. "/about-us" → "/qui-sommes-nous" for fr). The destination language must already be configured (use weglot-add-destination-language first). Replaces any pre-existing slug for the same source page and returns previous_to_slug for rollback. IMPORTANT and different from every other write here: this pair takes effect on THIS site immediately, and it can NOT be published to the Weglot Cloud. Translated slugs are not part of the project settings — Weglot keeps them on a separate resource (/translations/slugs), which weglot-push-options-to-cloud does not carry. Because the settings payload has no custom_urls, the merge Weglot performs takes the value from the local store, so the site serves it right away. To have the slug on the Weglot project itself (for other installs, or to see it on the dashboard) edit it at dashboard.weglot.com — but only if the plan allows it: when weglot-check-setup reports supports_slugs=false the slug resource answers 403 and the project cannot hold slugs at all, so the pair stays local and there is nothing to do on the dashboard either.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'language' => [
                'type' => 'string',
                'minLength' => 2,
                'description' => 'ISO destination-language code the slug applies to.',
            ],
            'from_slug' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Original source-language URL slug (with or without leading slash).',
            ],
            'to_slug' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Translated slug to serve in the destination language.',
            ],
        ],
        'required' => ['language', 'from_slug', 'to_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'language' => ['type' => 'string'],
            'from_slug' => ['type' => 'string'],
            'to_slug' => ['type' => 'string'],
            'previous_to_slug' => ['type' => ['string', 'null']],
            'cloud_sync_pending' => [
                'type' => 'boolean',
                'description' => 'Always false, and it is not an oversight: nothing is awaiting publication because nothing CAN be published. Slugs live on a separate cloud resource the settings push does not carry, and the local value is already what this site serves. Verified against a live project: POSTing custom_urls to /projects/settings is accepted and ignored.',
            ],
        ],
        'required' => ['persisted', 'language', 'from_slug', 'to_slug', 'previous_to_slug', 'cloud_sync_pending'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_add_translated_url',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Takes effect on this site immediately — do NOT tell the user to run weglot-push-options-to-cloud for it, that push cannot carry slugs. The pair lives only in this WordPress install: the Weglot project does not learn about it, so another install of the same project will not have it, and it will not appear on dashboard.weglot.com. Check supports_slugs first — when it is false the plan has no slug feature at all, so do not send the user to the dashboard to finish the job. weglot-refresh-from-cloud can discard it. Returns previous_to_slug=null on a fresh pair, or the prior slug when replacing one.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{language: string, from_slug: string, to_slug: string} $input
 * @return array{persisted: bool, language: string, from_slug: string, to_slug: string, previous_to_slug: string|null, cloud_sync_pending: false}|WP_Error
 */
function weglot_add_translated_url(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $language = strtolower(trim($input['language']));
    $from = wg_normalize_slug($input['from_slug']);
    $to = wg_normalize_slug($input['to_slug']);
    if (strlen($language) < 2) {
        return wg_invalid_input('language must be at least 2 characters after trim.');
    }
    if ($from === '' || $to === '') {
        return wg_invalid_input(
            'from_slug and to_slug must be a SINGLE path segment, non-empty after sanitization. Weglot matches one segment at a time, so a value containing a slash ("blog/2024/post", or a trailing "blog/") could never match and is rejected here instead of being persisted as a rule that reports success and does nothing. Control chars, absolute URLs and parent-directory segments are rejected too.',
        );
    }

    return wg_write_options_delta(['custom_urls'], static function (array $options) use (
        $language,
        $from,
        $to,
    ): array|WP_Error {
        // Validate against the configuration IN FORCE, not the local option:
        // `languages` is cloud-managed and is not among this write's owned keys,
        // so it is not seeded into $options. Reading it from there rejected
        // every language on an install configured through weglot.com.
        $configured = wg_destination_codes_from_options(wg_options_resolved());
        if (!in_array($language, $configured, strict: true)) {
            return wg_invalid_input(sprintf(
                'Destination language "%s" is not currently configured. Add it first with weglot-add-destination-language.',
                $language,
            ));
        }

        // Read the RAW cluster so malformed entries persisted by other actors
        // (Weglot admin save, legacy migrations) are preserved untouched. The
        // normalized view is used only to look up the previous slug.
        $normalized = wg_custom_urls_from_options($options);
        $previous = $normalized[$language][$from] ?? null;

        // Collapse any case-variant entries for the same canonical language
        // into a single lowercase bucket so write does not produce duplicate
        // keys (e.g. `['IT' => [...], 'it' => [...]]`) that confuse later
        // case-insensitive reads.
        $raw = wg_custom_urls_raw($options);
        $consolidated = [];
        /** @var mixed $stored_map */
        foreach ($raw as $stored_lang => $stored_map) {
            if (!is_string($stored_lang) || strtolower($stored_lang) !== $language || !is_array($stored_map)) {
                continue;
            }
            // First-wins on conflicts: existing entries beat duplicate stale ones.
            $consolidated += $stored_map;
            unset($raw[$stored_lang]);
        }
        // Stored in Weglot's orientation (translated => original), replacing any
        // prior pair for the same original slug. See wg_custom_urls_store_entry.
        $consolidated = wg_custom_urls_store_entry($consolidated, original: $from, translated: $to);
        $raw[$language] = $consolidated;
        $options['custom_urls'] = $raw;

        return [
            $options,
            [
                'persisted' => true,
                'language' => $language,
                'from_slug' => $from,
                'to_slug' => $to,
                'previous_to_slug' => $previous,
                'cloud_sync_pending' => false,
            ],
        ];
    });
}

/**
 * Normalize a slug for LOOKUP: trim, reject control chars / absolute URLs /
 * path-traversal segments, then strip leading slashes. Returns '' for input
 * that could not name a stored key under any reading.
 *
 * Deliberately does NOT reject a multi-segment value. This is the shape of an
 * EXISTING key, not of a new one: a pair that some other actor put in the blob
 * with a slash in it has to stay nameable, or it can never be removed. The
 * write guard is wg_normalize_slug(), which is stricter on purpose.
 */
function wg_normalize_slug_lookup(string $slug): string
{
    $trimmed = trim($slug);
    if ($trimmed === '') {
        return '';
    }
    // Control chars (incl. NUL, CR, LF, TAB) — never part of a sane slug.
    if (preg_match(pattern: '/[\\x00-\\x1f\\x7f]/', subject: $trimmed) === 1) {
        return '';
    }
    // Absolute URLs / scheme-like prefixes — block to prevent open-redirect /
    // header-splitting downstream.
    if (preg_match(pattern: '#^[a-z][a-z0-9+.-]*:#i', subject: $trimmed) === 1) {
        return '';
    }
    $clean = ltrim(string: $trimmed, characters: '/');
    if ($clean === '') {
        return '';
    }
    // Reject any literal path-traversal segment.
    foreach (explode(separator: '/', string: $clean) as $segment) {
        if ($segment === '..') {
            return '';
        }
    }
    return $clean;
}

/**
 * Can a stored slug ever match at runtime? A slug must be ONE path segment:
 * Weglot splits the path on "/" and compares each piece with === against the
 * whole stored slug (weglot-php Util\Url ~:363, then :373 / :379 / :497), so by
 * construction no stored value containing a slash can equal a segment.
 * Confirmed by executing the real SDK class: "/blog/2024/post" came back
 * unrewritten while a single-segment pair rewrote correctly. A trailing slash is
 * the same case — "blog/" is just as dead.
 */
function wg_slug_is_matchable(string $slug): bool
{
    return $slug !== '' && !str_contains($slug, '/');
}

/**
 * Normalize a slug for WRITING: everything wg_normalize_slug_lookup() checks,
 * plus a refusal of anything that could never match. Returns '' for rejected
 * input so the caller uniformly trips the wg_invalid_input guard.
 *
 * Accepting an unmatchable slug persisted a pair that reported success and did
 * nothing. Use wg_normalize_slug_lookup() to NAME an existing pair — applying
 * this guard there left a foreign multi-segment entry listed, inert and
 * impossible to delete.
 */
function wg_normalize_slug(string $slug): string
{
    $clean = wg_normalize_slug_lookup($slug);
    return wg_slug_is_matchable($clean) ? $clean : '';
}
