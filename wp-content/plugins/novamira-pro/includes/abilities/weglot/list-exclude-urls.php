<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-list-exclude-urls', [
    'label' => __('List Weglot URL Exclusions', domain: 'novamira-pro'),
    'description' => __(
        'Returns the URL-exclusion rules persisted in the local Weglot v3 cache: each rule has a match pattern, a `type` (one of Weglot\'s nine match modes: START_WITH, CONTAIN, IS_EXACTLY, MATCH_REGEX, their negations, …), and the optional list of language codes it applies to. Rules with an empty language list apply to every destination language. Output is compact: progressive disclosure clamps `limit` to a sane default; use weglot-get-settings + section="all" for the full options blob.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'default' => 200,
                'description' => 'Maximum entries returned; clamped to 500.',
            ],
            'language' => [
                'type' => 'string',
                'description' => 'Optional ISO code filter — return only rules that apply to this destination language (rules with an empty language list always match).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'config_source' => [
                'type' => 'string',
                'enum' => ['weglot', 'local_fallback'],
                'description' => 'Where the rules were read from. "local_fallback" means Weglot\'s resolver was unavailable, so an empty list means UNKNOWN, not "no exclusion rules".',
            ],
            'rules' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'index' => ['type' => 'integer'],
                        'type' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                        'languages' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'False for a legacy string-form entry: Weglot only applies rules stored as objects, so that one exists in the data but excludes nothing. Delete it by index and add it again to make it real.',
                        ],
                    ],
                    'required' => ['index', 'type', 'value', 'languages', 'active'],
                ],
            ],
        ],
        'required' => ['total', 'returned', 'truncated', 'config_source', 'rules'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_list_exclude_urls',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reads the exclusion rules in force, resolved through Weglot (rules configured on weglot.com are included). Rule `index` is the position in the resolved array and is the handle weglot-delete-exclude-url accepts. `type` is a Weglot RegexEnum value (START_WITH, CONTAIN, IS_EXACTLY, MATCH_REGEX, their negations) and is accepted back verbatim by weglot-add-exclude-url.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{limit?: int, language?: string} $input
 * @return array{total: int, returned: int, truncated: bool, config_source: 'weglot'|'local_fallback', rules: list<array{index: int, type: string, value: string, languages: list<string>, active: bool}>}
 */
function weglot_list_exclude_urls(array $input): array
{
    $limit = is_int($input['limit'] ?? null) ? (int) $input['limit'] : 200;
    $limit = max(1, min(500, $limit));
    // Stored rule languages are canonicalized to lowercase by
    // wg_extract_rule_languages — lowercase the filter input too so
    // `language=FR` matches rules persisted as `fr`.
    $language = isset($input['language']) ? strtolower(trim($input['language'])) : '';

    // Resolved, not raw: exclusion rules are cloud-managed and never present in
    // the local option, so reading raw reported "no rules" on every install
    // whose rules were configured on weglot.com. See wg_options_resolution().
    $resolution = wg_options_resolution();
    $options = $resolution['options'];
    /** @var mixed $rules_raw */
    // Canonical key is `excluded_paths` per Schema_Option_V3. `exclude_urls`
    // is the legacy alias the morphism rewrites from. Read both, canonical first.
    $rules_raw = $options['excluded_paths'] ?? $options['exclude_urls'] ?? [];
    // array_values aligns the index space with add/delete-exclude-url, which
    // also reindex via array_values, so the `index` field exposed here is
    // the same handle delete-exclude-url accepts.
    $rules = is_array($rules_raw) ? array_values($rules_raw) : [];

    $normalized = [];
    /** @var mixed $rule */
    foreach ($rules as $index => $rule) {
        $normalized[] = wg_normalize_exclude_rule((int) $index, $rule);
    }

    if ($language !== '') {
        $normalized = array_values(array_filter(
            $normalized,
            static fn(array $r): bool => $r['languages'] === [] || in_array($language, $r['languages'], strict: true),
        ));
    }

    $matched = count($normalized);
    if ($matched > $limit) {
        $normalized = array_slice($normalized, offset: 0, length: $limit);
    }

    return [
        'total' => count($rules),
        'returned' => count($normalized),
        'truncated' => $matched > count($normalized),
        'config_source' => $resolution['source'],
        'rules' => $normalized,
    ];
}

/**
 * Coerce one stored exclude-URL entry into the compact normalized shape.
 * Accepts both the legacy "string entry" form (just a path) and the v3 array
 * form ({type, value, languages}) — Weglot persists either across versions.
 *
 * @return array{index: int, type: string, value: string, languages: list<string>, active: bool}
 */
function wg_normalize_exclude_rule(int $index, mixed $rule): array
{
    if (is_string($rule)) {
        // Weglot SKIPS string entries entirely: get_exclude_urls only handles
        // `is_array($item)`, so a legacy string rule is never applied. It is
        // still listed — it exists in the data and delete-exclude-url can
        // remove it by index — but reporting it as if it were in force would
        // be a lie. CONTAIN is what the engine would use if it read it at all.
        return [
            'index' => $index,
            'type' => 'CONTAIN',
            'value' => $rule,
            'languages' => [],
            'active' => false,
        ];
    }
    if (!is_array($rule)) {
        // Unusable entry (neither string nor array): listed so the index space
        // stays aligned with delete-exclude-url, but never in force.
        return ['index' => $index, 'type' => 'CONTAIN', 'value' => '', 'languages' => [], 'active' => false];
    }

    /** @var mixed $value_raw */
    $value_raw = $rule['value'] ?? $rule['url'] ?? $rule['path'] ?? '';
    /** @var mixed $type_raw */
    $type_raw = $rule['type'] ?? null;

    $value = is_string($value_raw) ? $value_raw : '';
    // Canonicalize whatever is stored to a real RegexEnum value: native rules
    // (written by the Weglot UI) pass through verbatim, our own legacy
    // "path"/"regex" aliases resolve, so the `type` this reports is always a
    // value add-exclude-url will accept back — closing the round-trip.
    $type = wg_resolve_exclude_type(is_string($type_raw) ? $type_raw : null, $value);

    return [
        'index' => $index,
        'type' => $type,
        'value' => $value,
        'active' => true,
        // `excluded_languages` first: that is the key Weglot's own reader
        // prefers (Option_Service_Weglot::get_exclude_urls tries it before
        // falling back to `languages`). A rule written by the Weglot UI carries
        // only that one, so reading `languages` alone reported an empty scope —
        // i.e. "applies to every language" — for a rule that was in fact
        // targeted at specific ones.
        'languages' => wg_extract_rule_languages($rule['excluded_languages'] ?? $rule['languages'] ?? []),
    ];
}

/**
 * The nine URL-match modes Weglot's Regex engine understands (RegexEnum).
 * These are the ONLY values the matcher recognises: any other string falls
 * through to a preg_quote()d literal, which silently breaks regex rules and
 * degrades prefix rules to substring matches.
 *
 * @return list<string>
 */
function wg_exclude_rule_type_enum(): array
{
    return [
        'START_WITH',
        'NOT_START_WITH',
        'END_WITH',
        'NOT_END_WITH',
        'CONTAIN',
        'NOT_CONTAIN',
        'IS_EXACTLY',
        'NOT_IS_EXACTLY',
        'MATCH_REGEX',
    ];
}

/**
 * Resolve an incoming exclude-rule `type` to a canonical Weglot RegexEnum value
 * before it is persisted, so Weglot's engine matches the rule as intended.
 * Accepts the nine RegexEnum values verbatim (case-insensitive) plus two
 * ergonomic aliases: "regex" -> MATCH_REGEX, "path" -> START_WITH. Falls back
 * to inference when null/empty. This is the single choke point that keeps
 * add-exclude-url and the list/dedup round-trip speaking the engine's language.
 */
function wg_resolve_exclude_type(?string $type, string $value): string
{
    if ($type !== null && $type !== '') {
        $canonical = strtoupper($type);
        if (in_array($canonical, wg_exclude_rule_type_enum(), strict: true)) {
            return $canonical;
        }
        $alias = strtolower($type);
        if ($alias === 'regex') {
            return 'MATCH_REGEX';
        }
        if ($alias === 'path') {
            return 'START_WITH';
        }
    }
    // No type means CONTAIN, full stop — that is what the engine does:
    // `$regex_type = isset($item['type']) ? $item['type'] : RegexEnum::CONTAIN`
    // (Option_Service_Weglot::get_exclude_urls ~:1020). Inferring MATCH_REGEX
    // from a slash-wrapped value used to apply to rules WE DID NOT WRITE, so a
    // rule the runtime applies as CONTAIN was reported to the agent as a
    // regex. That is not a divergence of design: it is a field stating
    // something false about third-party data.
    return 'CONTAIN';
}

/**
 * Compile an add-exclude-url input into the {type, value} pair to persist.
 *
 * The "path" alias is special: it expresses "this section — its root and
 * everything under it" and compiles to a synthetic MATCH_REGEX so Weglot's
 * engine matches exactly that, never the "/en" -> "/enterprise" over-match a
 * raw START_WITH would cause. Any other input keeps its value verbatim and
 * only resolves the type. Returns WP_Error on an empty value.
 *
 * The normalization ORDER is load-bearing: it stops a "/" or "//" input from
 * compiling to `^(/|$)`, which would exclude the ENTIRE site.
 *   trim -> ensure leading "/" -> strip ALL trailing "/" -> empty collapses to "/".
 *
 * @return array{type: string, value: string}|WP_Error
 */
function wg_compile_exclude_input(?string $type, string $value): array|WP_Error
{
    if ($type !== null && strtolower($type) === 'path') {
        $normalized = trim($value);
        if ($normalized === '') {
            return wg_invalid_input('value must be a non-empty string.');
        }
        if ($normalized[0] !== '/') {
            $normalized = '/' . $normalized;
        }
        $normalized = rtrim($normalized, characters: '/');
        if ($normalized === '') {
            // "/", "//", "///" collapse to the site root ONLY — never the empty
            // pattern ^(/|$), which would exclude every URL on the site.
            $normalized = '/';
        }

        return ['type' => 'MATCH_REGEX', 'value' => '^' . preg_quote($normalized, delimiter: '~') . '(/|$)'];
    }

    // A slash-wrapped value with no explicit type is ambiguous, and both
    // readings produce a broken rule. Read as a regex it needs the delimiters
    // stripped; read literally it is a CONTAIN of "/^foo$/", which matches
    // nothing. Guessing has a worse failure mode than refusing: measured
    // against the real matcher, inferring MATCH_REGEX from "/blog/" turns a
    // sane CONTAIN of "/blog/" into a regex that also excludes "/blog" and
    // "/my-blogger-page" — an over-exclusion nobody asked for and nobody
    // notices. Ask the caller which one they meant.
    if (($type === null || $type === '') && wg_is_slash_wrapped($value)) {
        return wg_invalid_input(sprintf(
            'The value "%s" is wrapped in slashes, which is ambiguous: pass type="regex" to treat it as a Weglot regex (the /…/ delimiters are then removed, because Weglot adds its own), or type="CONTAIN" to match those slashes literally.',
            $value,
        ));
    }

    $resolved = wg_resolve_exclude_type($type, $value);

    // Strip the /…/ delimiters before persisting a regex. Weglot's matcher does
    // NOT treat them as delimiters: for MATCH_REGEX it takes the value verbatim
    // and wraps it in its own `~` (Util\Regex::getRegex + getPHPRegex), so a
    // stored "/^\/en(\/|$)/" compiles to ~/^\/en(\/|$)/~ — a literal slash
    // followed by a start-anchor, which can never match. Proven at runtime
    // against the real matcher: with the slashes the rule excluded nothing at
    // all; without them "/en" and "/en/page" are excluded and "/enterprise" is
    // not, which is what the caller asked for.
    //
    // Only for a value we are treating AS a regex: an explicit CONTAIN of
    // "/checkout/" is a literal path and must keep its slashes.
    if ($resolved === 'MATCH_REGEX' && wg_is_slash_wrapped($value)) {
        $value = substr($value, offset: 1, length: -1);
        if ($value === '') {
            return wg_invalid_input('A regex value cannot be empty once its /…/ delimiters are removed.');
        }
    }

    return ['type' => $resolved, 'value' => $value];
}

/**
 * Whether a value is wrapped in the /…/ delimiters people habitually write
 * around a regex. Needs at least one character between them.
 */
function wg_is_slash_wrapped(string $value): bool
{
    return strlen($value) > 2 && str_starts_with($value, '/') && str_ends_with($value, '/');
}

/**
 * Extract the list of language codes a rule applies to. Drops empty strings
 * and non-string entries.
 *
 * @return list<string>
 */
function wg_extract_rule_languages(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $lang */
    foreach ($raw as $lang) {
        if (!is_string($lang) || $lang === '') {
            continue;
        }
        // Canonicalize to lowercase so deduplication / filtering treats
        // "fr" and "FR" as the same scope.
        $out[] = strtolower($lang);
    }
    return $out;
}
