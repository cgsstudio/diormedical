<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-add-exclude-url', [
    'label' => __('Add Weglot URL Exclusion', domain: 'novamira-pro'),
    'description' => __(
        'Appends a URL-exclusion rule to the Weglot `excluded_paths` v3 option. `value` is the path or regex Weglot matches against the requested URL. `type` is one of Weglot\'s nine match modes (START_WITH, NOT_START_WITH, END_WITH, NOT_END_WITH, CONTAIN, NOT_CONTAIN, IS_EXACTLY, NOT_IS_EXACTLY, MATCH_REGEX) or an alias — "regex"=MATCH_REGEX (write the pattern itself, e.g. "^/en(/|$)"; if you wrap it in /…/ the delimiters are stripped before persisting, because Weglot adds its own and would otherwise treat yours as literal characters, leaving a rule that matches nothing), "path"=section shortcut that compiles to a MATCH_REGEX matching the path, its root and everything under it ("/en" excludes /en and /en/* but NOT /enterprise; the response echoes the compiled rule). Omit it and a value with no slashes is CONTAIN (Weglot\'s own default); a slash-wrapped value with NO type is refused, because both readings of it are broken — pass "regex" or "CONTAIN" to say which you meant. `languages` is an optional ISO-code list — when empty the rule applies to every destination language. Duplicates (same type + value + languages) are rejected with weglot_invalid_input — but an inert legacy string-form entry never counts as a duplicate, so it cannot block a working rule. Returns the new total and the index assigned to the rule (matches the `index` exposed by weglot-list-exclude-urls).',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'value' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Path or regex to exclude. Paths typically start with "/"; regex patterns are wrapped in slashes ("/^foo$/").',
            ],
            'type' => [
                'type' => 'string',
                'enum' => [
                    'START_WITH',
                    'NOT_START_WITH',
                    'END_WITH',
                    'NOT_END_WITH',
                    'CONTAIN',
                    'NOT_CONTAIN',
                    'IS_EXACTLY',
                    'NOT_IS_EXACTLY',
                    'MATCH_REGEX',
                    'path',
                    'regex',
                ],
                'description' => 'How Weglot matches `value` against the URL path — one of the nine Weglot match modes (START_WITH, NOT_START_WITH, END_WITH, NOT_END_WITH, CONTAIN, NOT_CONTAIN, IS_EXACTLY, NOT_IS_EXACTLY, MATCH_REGEX), or an alias: "regex" = MATCH_REGEX (value is a literal Weglot regex like "^/en(/|$)"), "path" = section shortcut compiled to a synthetic MATCH_REGEX matching the path, its root and children ("/en" -> /en and /en/* but NOT /enterprise; the add response echoes the compiled type+value). If omitted: a value with no slashes is CONTAIN (Weglot\'s own default); a slash-wrapped value is REFUSED as ambiguous, since reading it as a regex needs the delimiters stripped while reading it literally matches nothing.',
            ],
            'languages' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional ISO codes to scope the rule to. Empty (default) applies to every destination language.',
            ],
        ],
        'required' => ['value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'index' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
            'rule' => [
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
                'description' => 'Always true: excluded_paths is part of the v3 options blob Weglot POSTs to the cloud on the next admin settings-page save. Until then, edge caches and the Weglot CDN may serve the prior exclusion set.',
            ],
        ],
        'required' => ['persisted', 'index', 'total', 'rule', 'cloud_sync_pending'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_add_exclude_url',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Appends, never replaces. Use weglot-delete-exclude-url with the returned `index` to remove. Languages outside the configured destination list are accepted (Weglot ignores them at runtime).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param  array{value: string, type?: string, languages?: list<string>} $input
 * @return array{persisted: bool, index: int, total: int, rule: array{index: int, type: string, value: string, languages: list<string>, active: bool}, cloud_sync_pending: true}|WP_Error
 */
function weglot_add_exclude_url(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $value = trim($input['value']);
    if ($value === '') {
        return wg_invalid_input('value must be a non-empty string.');
    }
    /** @var mixed $type_in */
    $type_in = $input['type'] ?? null;
    // Compile to a real Weglot RegexEnum type + final value BEFORE persisting.
    // A raw 'path'/'regex' alias would fall through Weglot's matcher to a
    // preg_quote()d literal (broken regex / substring prefix); the "path" alias
    // additionally compiles to a section+children MATCH_REGEX so "/en" never
    // over-excludes "/enterprise". See wg_compile_exclude_input().
    $compiled = wg_compile_exclude_input(is_string($type_in) ? $type_in : null, $value);
    if ($compiled instanceof WP_Error) {
        return $compiled;
    }
    $type = $compiled['type'];
    $value = $compiled['value'];
    $languages = wg_extract_rule_languages($input['languages'] ?? []);

    return wg_write_options_delta(['excluded_paths', 'exclude_urls'], static function (array $options) use (
        $type,
        $value,
        $languages,
    ): array|WP_Error {
        /** @var mixed $rules_raw */
        $rules_raw = $options['excluded_paths'] ?? $options['exclude_urls'] ?? [];
        $rules = is_array($rules_raw) ? array_values($rules_raw) : [];

        /** @var mixed $existing */
        foreach ($rules as $existing) {
            $normalized = wg_normalize_exclude_rule(0, $existing);
            // An inert entry is not a duplicate of a working one. Weglot's
            // reader handles only object rules, so a legacy string entry
            // excludes nothing — yet it used to collide here and the caller was
            // told the rule "already exists" while the path stayed translated.
            // Refusing to create a live rule because a dead one shadows it is
            // the same class of defect as reporting the dead one as active.
            if ($normalized['active'] === false) {
                continue;
            }
            if (
                $normalized['type'] === $type
                && $normalized['value'] === $value
                && $normalized['languages'] === $languages
            ) {
                return wg_invalid_input(sprintf(
                    'An exclusion rule with type="%s", value="%s" already exists.',
                    $type,
                    $value,
                ));
            }
        }

        // exclusion_behavior mirrors Weglot's own default and the shape its
        // admin UI persists; writing it explicitly keeps the rule complete.
        //
        // The language scope goes under `excluded_languages`, the name Weglot's
        // own reader tries FIRST (Option_Service_Weglot::get_exclude_urls falls
        // back to `languages` only when it is absent) and the only one the
        // dashboard writes. Both sides have a fallback, so writing the legacy
        // name still round-tripped — but it left our rules in a shape the
        // dashboard does not produce, and a rule that ended up carrying both
        // keys would resolve by a precedence we were not writing for.
        $new_rule = [
            'type' => $type,
            'value' => $value,
            'excluded_languages' => $languages,
            'exclusion_behavior' => 'NOT_TRANSLATED',
        ];
        $rules[] = $new_rule;
        $options['excluded_paths'] = $rules;
        // Clean the legacy alias if it still lingers, so a future read does
        // not surprise us with a desynced shadow copy.
        unset($options['exclude_urls']);

        $index = count($rules) - 1;
        return [
            $options,
            [
                'persisted' => true,
                'index' => $index,
                'total' => count($rules),
                'rule' => [
                    'index' => $index,
                    'type' => $type,
                    'value' => $value,
                    'languages' => $languages,
                    'active' => true,
                ],
                'cloud_sync_pending' => true,
            ],
        ];
    });
}
