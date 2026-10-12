<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

/**
 * Abilities: Bricks Dynamic Data — list / resolve.
 *
 * Exposes the Bricks dynamic-data subsystem (providers + tags) so agents can
 * discover which `{tokens}` exist before embedding them into page content,
 * and verify one against a specific post before committing. Bricks renders
 * unknown tokens as their literal text (no error), so the failure mode of
 * guessing tag names is a page that ships with `{acf:headlien}` printed on
 * it — these abilities exist to close that gap.
 *
 * Two slugs, one file. All
 * implementation helpers are prefixed `dd_` and private to the file.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_BRICKS_DD_COMPACT_CAP = 300;

/**
 * Common output filters documented by Bricks Builder. Surfaced in the verbose
 * list output (as a single flat list, not per-token) and in the resolve
 * ability instructions so agents know the filter vocabulary without having to
 * read the Bricks source. These are the global filters parsed in
 * `Bricks\Integrations\Dynamic_Data\Providers\Base::get_filters_from_args()`;
 * per-provider filters (e.g. ACF `:value` to return the id instead of the
 * label) are left out because they are not exposed through a registry.
 *
 * @var array<int, array{name: string, description: string}>
 */
const NOVAMIRA_BRICKS_DD_COMMON_FILTERS = [
    [
        'name' => 'raw',
        'description' => 'Do not parse the tag — render the literal `{tag}` text. Useful for escaping.',
    ],
    [
        'name' => 'plain',
        'description' => 'Strip HTML tags from the rendered value.',
    ],
    [
        'name' => 'format',
        'description' => 'Keep HTML formatting from the source value (default strips it).',
    ],
    [
        'name' => 'link',
        'description' => 'Wrap the rendered value in a hyperlink (for taxonomies / authors / posts with a permalink).',
    ],
    [
        'name' => 'url',
        'description' => 'Return the value\'s URL only (vs the full <a href> markup produced by :link).',
    ],
    [
        'name' => 'image',
        'description' => 'Render the value as an <img> tag (image fields / attachments).',
    ],
    [
        'name' => 'newTab',
        'description' => 'When combined with :link, open the link in a new tab.',
    ],
    [
        'name' => 'tel',
        'description' => 'Render the value as a `tel:` link (phone fields).',
    ],
    [
        'name' => 'value',
        'description' => 'Return the raw value instead of the UI label (applies to ACF choice / select / radio fields).',
    ],
    [
        'name' => 'is-array',
        'description' => 'Force array output to a JSON string so it is safe to embed inline.',
    ],
    [
        'name' => 'num_words:<n>',
        'description' => 'Truncate text output to `<n>` words (e.g. `{post_excerpt:20}`).',
    ],
    [
        'name' => 'format:<fmt>',
        'description' => 'For date tokens, pass a PHP date format (e.g. `{post_date:format:d/m/Y}`).',
    ],
    [
        'name' => 'size:<slug>',
        'description' => 'For image tokens, pass a registered WP image size (e.g. `{featured_image:size:medium}`).',
    ],
    [
        'name' => 'sep:<char>',
        'description' => 'For term tokens, custom separator between terms (e.g. `{post_terms_post_tag:sep:,}`).',
    ],
    [
        'name' => ' @fallback:\'...\'',
        'description' => 'Value to use when the tag resolves empty. Always put a space before `@`: `{acf_headline @fallback:\'Untitled\'}`. Without the space the outcome depends on the tag and the Bricks install — a text element can print the whole token as literal text or render nothing; bricks-resolve-dynamic-data reports what the page shows.',
    ],
    [
        'name' => ' @fallback-image:<id>',
        'description' => 'Attachment id or URL to fall back on when an image tag resolves empty. Put a space before `@` like @fallback — without it an image can render with a broken src.',
    ],
];

wp_register_ability('novamira/bricks-list-dynamic-data', [
    'label' => __('List Bricks Dynamic Data', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricks dynamic-data tokens registered in this environment, grouped by provider (wp / acf / metabox / pods / toolset / woo / jetengine / cmb2). Each entry is the exact `{token}` you paste into a Bricks setting. Use `provider` or `search` to narrow the output — an ACF-heavy site can expose hundreds of tags, so the default compact form caps at 300 and hints when to filter. `verbose: true` adds group/fieldType/deprecated flags and a single flat list of common output filters (`:format:d/m/Y`, `:size:medium`, ` @fallback:\'...\'` — always put a space before an `@` argument). Bricks renders unknown tokens as their literal `{text}` — sanity-check with `bricks-resolve-dynamic-data` before embedding.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'provider' => [
                'type' => 'string',
                'description' => 'Filter to a single provider key (e.g. "wp", "acf", "query-loop"). Unknown providers produce an empty list — use the `providers` map in the response to discover valid keys.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Case-insensitive substring match against the tag name OR label. Example: "date" matches `{post_date}` and any field labelled "Publication Date".',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'When set, ACF tags are filtered to field groups attached to that post. No-op for providers that do not gate tags by post (wp / query-loop).',
            ],
            'verbose' => [
                'type' => 'boolean',
                'description' => 'Progressive disclosure. Default (false): compact `{tag, label}` per entry. true: adds `group`, `field_type`, `deprecated`, plus a top-level `filters` list of common output filters.',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'providers' => [
                'type' => 'array',
                'description' => 'List of provider buckets, each with its own `tokens` sub-array. Present on success.',
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Tokens returned after filtering and the 300-token cap.',
            ],
            'total_available' => [
                'type' => 'integer',
                'description' => 'Tokens that matched the filters before the compact cap. When greater than `total`, the output was truncated — pass `provider` or `search` to narrow.',
            ],
            'truncated' => [
                'type' => 'boolean',
                'description' => 'True when the 300-token cap was hit.',
            ],
            'note' => [
                'type' => 'string',
                'description' => 'Guidance message when the output is truncated or when nothing matched.',
            ],
            'filters' => [
                'type' => 'array',
                'description' => 'Only returned in verbose mode. Common Bricks output-filter vocabulary agents can append to any tag.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_dynamic_data',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

wp_register_ability('novamira/bricks-resolve-dynamic-data', [
    'label' => __('Resolve Bricks Dynamic Data', domain: 'novamira-pro'),
    'description' => __(
        'Renders a single Bricks dynamic-data tag against a specific post and returns what Bricks produced for it in `rendered` — a string in `text`/`link` context; in `image`/`media` context usually an array, but the literal `{tag}` string when Bricks does not recognise the token; and null where Bricks returned no value at all — so read `empty` and `unregistered` rather than assuming a shape. Use it to verify a tag works before embedding it into saved content — Bricks leaves unknown tags as their literal `{text}`, so a successful resolve with a non-empty value is the real signal the tag is valid. In `text` context the tag is rendered the way a text/heading element renders it, and in `link` context the way a dynamic link renders its href, so a token the page would print literally comes back literally here too (`unregistered: true`). `image` context renders it the way an image element reads it (the first entry is an attachment id or URL), and `media` context the way an audio element reads it (the `url` of the first entry). When the token has an `@` argument with no space before it, the response adds a `note` saying to write it with the space. The `context` argument matches Bricks\' own render contexts: `text` for strings, `link` for URLs, `image` for attachment ids/URLs, `media` for attachment arrays. For bulk rendering use the Bricks builder, not this ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'tag' => [
                'type' => 'string',
                'description' => 'The dynamic-data token to resolve, with or without curly braces. Filters are allowed (e.g. `{post_title:upper}`, `{post_date:format:d/m/Y}`, `{acf_headline @fallback:\'Untitled\'}` — keep the space before `@fallback`: without it a text element can print the token literally or render nothing).',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'The post id that provides the rendering context. Required because most providers (wp, acf, metabox, …) resolve tokens relative to the current post.',
            ],
            'context' => [
                'type' => 'string',
                'enum' => ['text', 'link', 'image', 'media'],
                'description' => 'Render context. Defaults to "text". Use "link" for URL props, "image" for attachment-id props, "media" when you need an array of attachments.',
                'default' => 'text',
            ],
        ],
        'required' => ['tag', 'post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'tag' => ['type' => 'string'],
            'rendered' => [
                'type' => ['string', 'array', 'null'],
                'description' => 'The rendered value — a string in `text`/`link` context; in `image`/`media` context an array (image: attachment ids or URLs; media: entries carrying a `url`), or the literal `{tag}` string when Bricks did not recognise the token; `null` when Bricks returned no value.',
            ],
            'empty' => [
                'type' => 'boolean',
                'description' => 'True when the rendered value is null, empty string, or empty array. Also true when Bricks echoed the literal `{tag}` back (see `unregistered`).',
            ],
            'unregistered' => [
                'type' => 'boolean',
                'description' => 'True when Bricks returned the literal `{tag}`: the token is not registered on this site, or it is written in a form Bricks does not recognise as a tag (see `note`). What the page does with it depends on the context: a text element prints it as text, a dynamic link uses it as its href, an image element takes its first character as the image source (a broken image), and an audio element renders nothing.',
            ],
            'note' => [
                'type' => 'string',
                'description' => 'Present only when the token has an `@` argument with no space before it (e.g. `{post_title@fallback:\'x\'}`). Depending on the tag, Bricks prints such a token literally, renders nothing, or renders a changed value; write it with the space and resolve again.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_resolve_dynamic_data',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * Guard that ensures the Bricks dynamic-data runtime is available. Returns
 * null when the Providers class is loaded; a WP_Error otherwise.
 */
function dd_runtime_guard(): ?WP_Error
{
    if (!class_exists('Bricks\\Integrations\\Dynamic_Data\\Providers')) {
        return bricks_error(
            'not_available',
            message: 'Bricks dynamic-data subsystem is not loaded. Ensure Bricks is active and this request runs after init:10001.',
        );
    }
    return null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_list_dynamic_data(array $input = []): array|WP_Error
{
    $guard = dd_runtime_guard();
    return $guard ?? dd_list($input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_resolve_dynamic_data(array $input): array|WP_Error
{
    $guard = dd_runtime_guard();
    return $guard ?? dd_resolve($input);
}

/**
 * List all registered Bricks dynamic-data tags, optionally filtered by
 * provider, search term, and post context. Output is grouped by provider and
 * capped to keep the response bounded on ACF-heavy sites.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function dd_list(array $input): array
{
    $filters = dd_parse_list_filters($input);

    $raw_tags = \Bricks\Integrations\Dynamic_Data\Providers::get_dynamic_tags_list();

    $collected = dd_collect_buckets($raw_tags, $filters);

    $providers = array_values($collected['buckets']);
    usort($providers, static fn(array $a, array $b): int => strcmp((string) $a['key'], (string) $b['key']));

    $response = [
        'success' => true,
        'providers' => $providers,
        'total' => $collected['returned'],
        'total_available' => $collected['matched'],
        'truncated' => $collected['truncated'],
    ];

    if ($filters['verbose']) {
        $response['filters'] = NOVAMIRA_BRICKS_DD_COMMON_FILTERS;
    }

    $note = dd_select_note($collected, $filters);
    if ($note !== '') {
        $response['note'] = $note;
    }

    return $response;
}

/**
 * Normalise the raw input into the filter struct the collector consumes.
 * Keeps `dd_list` focused on orchestration by pulling all the input-shape
 * coercion (strings, ints, bool flag, precomputed ACF allow-list) into one
 * place.
 *
 * @param array<string, mixed> $input
 * @return array{provider: string, search_raw: string, search: string, verbose: bool, acf_allowed: array<string, bool>|null}
 */
function dd_parse_list_filters(array $input): array
{
    $search_raw = is_string($input['search'] ?? null) ? $input['search'] : '';
    $post_id = is_numeric($input['post_id'] ?? null) ? (int) $input['post_id'] : 0;

    return [
        'provider' => is_string($input['provider'] ?? null) ? $input['provider'] : '',
        'search_raw' => $search_raw,
        'search' => $search_raw === '' ? '' : strtolower($search_raw),
        'verbose' => ($input['verbose'] ?? false) === true,
        'acf_allowed' => $post_id > 0 ? dd_acf_allowed_tag_names($post_id) : null,
    ];
}

/**
 * Resolve the provider key for a Bricks dynamic tag.
 *
 * Bricks' own tags carry an explicit `provider` (e.g. "wp", "acf"), but some
 * integrations register tags with only a `group` label and no `provider` —
 * most notably JetEngine, whose tags ({je_current_object_field}, {je_macros},
 * …) would otherwise fall into an empty bucket and be invisible to a
 * `provider: "jetengine"` filter. Fall back to the token-name prefix for the
 * known providers that omit the field.
 *
 * @param array<string, mixed> $tag_entry
 */
function dd_provider_key(array $tag_entry): string
{
    $provider = (string) ($tag_entry['provider'] ?? '');
    if ($provider !== '') {
        return $provider;
    }

    if (str_starts_with((string) ($tag_entry['name'] ?? ''), '{je_')) {
        return 'jetengine';
    }

    return '';
}

/**
 * Walk the raw Bricks tag registry, apply the filters, and accumulate tags
 * into provider buckets with the compact-cap applied. Returns the bucket map
 * plus the pre-cap `matched` count and a `truncated` flag so the caller can
 * surface both to the user.
 *
 * @param list<array<string, mixed>>                                                                                        $raw_tags
 * @param array{provider: string, search: string, verbose: bool, acf_allowed: array<string, bool>|null, search_raw: string} $filters
 * @return array{buckets: array<string, array<string, mixed>>, matched: int, returned: int, truncated: bool}
 */
function dd_collect_buckets(array $raw_tags, array $filters): array
{
    /** @var array<string, array<string, mixed>> $buckets */
    $buckets = [];
    $matched = 0;
    $returned = 0;
    $truncated = false;

    foreach ($raw_tags as $tag_entry) {
        if (!dd_tag_passes_filters($tag_entry, $filters)) {
            continue;
        }

        $matched++;
        if ($returned >= NOVAMIRA_BRICKS_DD_COMPACT_CAP) {
            $truncated = true;
            continue;
        }

        $token = $filters['verbose'] ? dd_token_verbose($tag_entry) : dd_token_compact($tag_entry);
        $buckets = dd_push_into_bucket($buckets, dd_provider_key($tag_entry), $token);
        $returned++;
    }

    return [
        'buckets' => $buckets,
        'matched' => $matched,
        'returned' => $returned,
        'truncated' => $truncated,
    ];
}

/**
 * Evaluate the provider / search / ACF-post filter triad against a single
 * tag. Empty-name rows are rejected up front so downstream callers can treat
 * `name` as non-empty.
 *
 * @param array<string, mixed>                                                                                              $tag_entry
 * @param array{provider: string, search: string, acf_allowed: array<string, bool>|null, verbose: bool, search_raw: string} $filters
 */
function dd_tag_passes_filters(array $tag_entry, array $filters): bool
{
    $provider_key = dd_provider_key($tag_entry);
    $name = (string) ($tag_entry['name'] ?? '');
    $label = (string) ($tag_entry['label'] ?? '');

    if ($name === '') {
        return false;
    }
    if ($filters['provider'] !== '' && $provider_key !== $filters['provider']) {
        return false;
    }
    if (!dd_matches_search($name, $label, $filters['search'])) {
        return false;
    }
    return $filters['acf_allowed'] === null
    || $provider_key !== 'acf'
    || array_key_exists($name, $filters['acf_allowed']);
}

/**
 * Insert a pre-shaped token into its provider bucket, creating the bucket
 * on first encounter. Takes the already-built token so the caller decides
 * compact-vs-verbose shape and this helper only owns bucket plumbing.
 *
 * @param array<string, array<string, mixed>> $buckets
 * @param array<string, mixed>                $token
 * @return array<string, array<string, mixed>>
 */
function dd_push_into_bucket(array $buckets, string $provider_key, array $token): array
{
    $bucket = $buckets[$provider_key] ?? [
        'key' => $provider_key,
        'label' => dd_provider_label($provider_key),
        'tokens' => [],
    ];

    /** @var list<array<string, mixed>> $tokens */
    $tokens = $bucket['tokens'];
    $tokens[] = $token;
    $bucket['tokens'] = $tokens;
    $buckets[$provider_key] = $bucket;

    return $buckets;
}

/**
 * Pick the advisory message (truncated / empty / none) for a collection
 * outcome. Kept in its own helper so `dd_list` stays an assembly function
 * and the message-selection logic lives in one place.
 *
 * @param array{buckets: array<string, array<string, mixed>>, matched: int, returned: int, truncated: bool}       $collected
 * @param array{provider: string, search_raw: string, search: string, verbose: bool, acf_allowed: array<string, bool>|null} $filters
 */
function dd_select_note(array $collected, array $filters): string
{
    if ($collected['truncated']) {
        return dd_note_truncated($collected['matched']);
    }
    if ($collected['matched'] === 0) {
        return dd_note_empty($filters['provider'], $filters['search_raw']);
    }
    return '';
}

/**
 * Decide whether a given tag matches the search filter. Empty search matches
 * everything. Comparison is case-insensitive against the tag name and label.
 */
function dd_matches_search(string $name, string $label, string $lowercased_search): bool
{
    if ($lowercased_search === '') {
        return true;
    }
    return str_contains(strtolower($name), $lowercased_search) || str_contains(strtolower($label), $lowercased_search);
}

/**
 * Compact-mode token shape. Just the `{tag}` to paste and the human label,
 * to keep the default payload small on sites with hundreds of ACF fields.
 *
 * @param array<string, mixed> $tag_entry
 * @return array<string, mixed>
 */
function dd_token_compact(array $tag_entry): array
{
    return [
        'tag' => (string) ($tag_entry['name'] ?? ''),
        'label' => (string) ($tag_entry['label'] ?? ''),
    ];
}

/**
 * Verbose-mode token shape. Adds group / field_type / deprecated on top of
 * the compact shape. Kept separate from `dd_token_compact` because the
 * linter flags bool-flag parameters.
 *
 * @param array<string, mixed> $tag_entry
 * @return array<string, mixed>
 */
function dd_token_verbose(array $tag_entry): array
{
    $token = dd_token_compact($tag_entry);
    $token['group'] = (string) ($tag_entry['group'] ?? '');

    if (array_key_exists('fieldType', $tag_entry) && $tag_entry['fieldType'] !== null) {
        $token['field_type'] = (string) $tag_entry['fieldType'];
    }
    if (($tag_entry['deprecated'] ?? null) !== null) {
        $token['deprecated'] = true;
    }

    return $token;
}

/**
 * Human-readable label for a provider key. The registered provider instance
 * doesn't advertise a label, so we keep this short lookup — one line per
 * provider class in the Bricks source — rather than inferring from the key.
 */
function dd_provider_label(string $key): string
{
    return match ($key) {
        'wp' => 'WordPress',
        'acf' => 'Advanced Custom Fields',
        'metabox' => 'Meta Box',
        'pods' => 'Pods',
        'toolset' => 'Toolset',
        'cmb2' => 'CMB2',
        'jetengine' => 'JetEngine',
        'woo' => 'WooCommerce',
        '' => '(unknown)',
        default => $key,
    };
}

/**
 * Advisory when the compact cap truncated the output. Surfaces the pre-cap
 * count so the caller can see how much they missed, and reminds them of
 * the two filters that narrow the result.
 */
function dd_note_truncated(int $matched): string
{
    return sprintf(
        'Output capped at %d tokens (of %d matching). Narrow with provider="acf" or search="fieldname".',
        NOVAMIRA_BRICKS_DD_COMPACT_CAP,
        $matched,
    );
}

/**
 * Advisory when the filters produced zero matches. Echoes the filters that
 * were applied so the caller sees what they typed, and nudges them toward
 * running without filters first.
 */
function dd_note_empty(string $provider_filter, string $search_raw): string
{
    $bits = [];
    if ($provider_filter !== '') {
        $bits[] = sprintf('provider="%s"', $provider_filter);
    }
    if ($search_raw !== '') {
        $bits[] = sprintf('search="%s"', $search_raw);
    }
    $suffix = $bits === [] ? '' : ' with ' . implode(' + ', $bits);
    return (
        'No tokens matched the filters'
        . $suffix
        . '. Drop filters to see the full list, or call without arguments to discover provider keys.'
    );
}

/**
 * Build a lookup of ACF tag names (in `{acf_field-name}` form) that are
 * attached to the given post's field groups. Returned as a map keyed by the
 * full tag token so the list filter can check membership in O(1). Returns
 * null — not an empty array — when ACF or the post is unavailable so the
 * caller treats it as "no ACF filter" instead of "ACF allows nothing".
 *
 * @return array<string, bool>|null
 */
function dd_acf_allowed_tag_names(int $post_id): ?array
{
    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        return null;
    }
    if ($post_id <= 0 || get_post($post_id) === null) {
        return null;
    }

    $groups = acf_get_field_groups(['post_id' => $post_id]);
    if ($groups === []) {
        return [];
    }

    $allowed = [];
    foreach ($groups as $group) {
        $key = is_string($group['key'] ?? null) ? $group['key'] : '';
        if ($key === '') {
            continue;
        }
        $fields = acf_get_fields($key);
        if (!is_array($fields)) {
            continue;
        }
        dd_collect_acf_field_tokens($fields, $allowed);
    }

    return $allowed;
}

/**
 * Recursively walk an ACF field tree and collect every `{acf_*}` tag token
 * the provider would generate from those field names. Mirrors the naming
 * convention in `Provider_Acf::register_tags()` — tag name is `acf_` + field
 * name — without importing the provider class; repeater/group sub-fields are
 * registered too and are included here for completeness.
 *
 * @param list<array<string, mixed>>   $fields
 * @param array<string, bool>          $allowed  Accumulator, mutated in place.
 */
function dd_collect_acf_field_tokens(array $fields, array &$allowed): void
{
    foreach ($fields as $field) {
        $name = is_string($field['name'] ?? null) ? $field['name'] : '';
        if ($name !== '') {
            $allowed['{acf_' . $name . '}'] = true;
        }

        /** @var list<array<string, mixed>>|null $sub_fields */
        $sub_fields = is_array($field['sub_fields'] ?? null) ? $field['sub_fields'] : null;
        if ($sub_fields !== null) {
            dd_collect_acf_field_tokens($sub_fields, $allowed);
        }
    }
}

/**
 * Resolve a single tag against a post. Delegates to the Bricks entry point the
 * frontend uses for the same context (see dd_render()) so filters, fallbacks,
 * and tag recognition are identical to what the page produces. Detects the
 * "unregistered token" case — Bricks echoes back the literal `{tag}` instead
 * of erroring — so the caller can distinguish "empty value" from "typo".
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dd_resolve(array $input): array|WP_Error
{
    $tag_raw = is_string($input['tag'] ?? null) ? trim($input['tag']) : '';
    if ($tag_raw === '') {
        return bricks_error('invalid_input', message: 'Parameter "tag" is required.');
    }

    $post_id = is_numeric($input['post_id'] ?? null) ? (int) $input['post_id'] : 0;
    if ($post_id <= 0) {
        return bricks_error(
            'invalid_input',
            message: 'Parameter "post_id" is required and must be a positive integer.',
        );
    }
    if (get_post($post_id) === null) {
        return bricks_error('not_found', sprintf('Post %d does not exist on this site.', $post_id));
    }

    $context = dd_normalize_context($input['context'] ?? null);
    $normalized_tag = dd_wrap_tag($tag_raw);

    /** @var mixed $rendered */
    $rendered = dd_render($normalized_tag, $post_id, $context);

    $unregistered = is_string($rendered) && $rendered === $normalized_tag;
    $empty = $unregistered || dd_is_empty_value($rendered);

    $result = [
        'success' => true,
        'tag' => $normalized_tag,
        'rendered' => $rendered,
        'empty' => $empty,
        'unregistered' => $unregistered,
    ];
    if (preg_match('/[^\s{]@[A-Za-z][A-Za-z-]*:/', $normalized_tag) === 1) {
        $result['note'] = 'This token has an `@` argument with no space before it. Depending on the tag, Bricks prints such a token literally, renders nothing, or renders a changed value. Write it with a space before `@` (e.g. `{post_title @fallback:\'x\'}`) and resolve again.';
    }

    return $result;
}

/**
 * Render a tag the way the frontend does for that context.
 *
 * Text/heading elements and dynamic links go through
 * `Providers::render_content()` (via `bricks_render_dynamic_data()`), which
 * first scans the text for tags it recognises and leaves anything else as
 * literal text. The element markup then gets a page-level pass through the
 * `bricks/frontend/render_data` filter; a link value is already escaped into
 * its `href` by then, so a literal token can come out of that pass changed.
 * Image elements call `Providers::render_tag()` directly, which skips the
 * scan. Calling `render_tag()` for text made this ability report a value for
 * tokens the page prints literally wherever the two paths disagree, so text
 * and link follow the element path when Bricks has `render_content()`.
 */
function dd_render(string $tag, int $post_id, string $context): mixed
{
    $providers = \Bricks\Integrations\Dynamic_Data\Providers::class;
    if ($context !== 'text' && $context !== 'link' || !method_exists($providers, 'render_content')) {
        /** @var mixed */
        return $providers::render_tag($tag, $post_id, $context);
    }

    /** @var mixed $value */
    $value = $providers::render_content($tag, $post_id, $context);
    if (!is_string($value) || $value === '') {
        return $value;
    }
    // Frontend::render_data() passes the area as a third argument.
    $area = 'content';
    if ($context === 'text') {
        return (string) apply_filters('bricks/frontend/render_data', $value, get_post($post_id), $area);
    }

    // Link: the element escapes the value into `href` (esc_url for a valid
    // URL, esc_attr otherwise) before the page-level pass; report the
    // decoded attribute value a browser would follow.
    $attribute = filter_var($value, FILTER_VALIDATE_URL) !== false ? esc_url($value) : esc_attr($value);
    $rendered = (string) apply_filters('bricks/frontend/render_data', $attribute, get_post($post_id), $area);

    return html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5);
}

/**
 * Normalise the caller-provided context to one of Bricks' supported values.
 * Silent default ("text") matches Bricks' own default so a missing context
 * never causes the resolve path to behave differently from the frontend.
 */
function dd_normalize_context(mixed $raw): string
{
    if (!is_string($raw)) {
        return 'text';
    }
    return match ($raw) {
        'link', 'image', 'media', 'text' => $raw,
        default => 'text',
    };
}

/**
 * Ensure the tag is wrapped in curly braces. Bricks' `render_tag()` accepts
 * either form but internally treats the braced form as the canonical shape
 * for nested-tag detection, so we normalise here.
 */
function dd_wrap_tag(string $tag): string
{
    if ($tag === '' || $tag[0] === '{' && substr($tag, -1) === '}') {
        return $tag;
    }
    return '{' . $tag . '}';
}

/**
 * "Empty" for the resolve response: null, empty string, or empty array.
 * Matches the convention used by Bricks' own `handle_fallback`.
 */
function dd_is_empty_value(mixed $value): bool
{
    if ($value === null) {
        return true;
    }
    if (is_string($value)) {
        return $value === '';
    }
    if (is_array($value)) {
        return $value === [];
    }
    return false;
}
