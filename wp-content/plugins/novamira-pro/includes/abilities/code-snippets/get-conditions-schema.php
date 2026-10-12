<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-get-conditions-schema', [
    'label' => __('Get Conditions Schema', domain: 'novamira-pro'),
    'description' => __(
        'Pro-only reference for the Code Snippets conditions feature. A condition is itself a snippet of type=cond + scope=condition whose `code` field stores a JSON tree of rules; other snippets attach to a condition via their `condition_id`. This ability returns the catalog of supported subjects (siteArea, currentQuery, postType, userRole, currentDate, …), their operators (`equals` is implicit; date subjects also support `before` / `after` / `between`), and a worked JSON example so an agent can compose valid conditions without reading the plugin source. Returns `available: false` on a free install since conditions are Pro-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'available' => ['type' => 'boolean'],
            'subjects' => ['type' => 'array'],
            'operators' => ['type' => 'object'],
            'json_shape' => ['type' => 'string'],
            'example' => ['type' => 'object'],
            'workflow' => ['type' => 'string'],
            'error' => [
                'type' => 'string',
                'description' => 'Reason `available` is false (typically: Code Snippets Pro is not active on this install). Only present when `available: false`.',
            ],
        ],
        'required' => ['available'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_get_conditions_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call before composing a condition. The condition is stored as a snippet of type=cond / scope=condition whose `code` field is the JSON tree returned by this ability\'s `json_shape`. To gate another snippet on the condition, set the target snippet\'s `condition_id` to the condition\'s id via create-snippet or edit-snippet.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function code_snippets_get_conditions_schema(): array
{
    if (!cs_is_pro()) {
        return [
            'available' => false,
            'error' => 'Conditions are a Pro-only feature. Activate Code Snippets Pro to use them.',
        ];
    }

    return [
        'available' => true,
        'subjects' => cs_conditions_subject_catalog(),
        'operators' => [
            'default' => 'When no operator is set, the rule evaluates as "subject matches any of objects".',
            'date_subjects' => [
                'before' => 'Subject (a date) is before object[0].',
                'after' => 'Subject (a date) is after object[0].',
                'between' => 'Subject (a date) is between object[0] and object[1] (inclusive).',
            ],
            'negation' => [
                'not' => 'Negates the default match. `{subject, operator: "not", object: [...]}` matches when subject is NOT in objects.',
                'not in' => 'Alias of `not` for readability — same evaluation.',
                'false' => 'Forces the rule to evaluate false regardless of subject/object. Useful as a placeholder while composing complex trees.',
            ],
        ],
        'json_shape' => 'A condition is stored as JSON: a top-level ARRAY of GROUPS, where each group is an array of rules: `[[{rule}, {rule}, ...], [{rule}, ...], ...]`. The outer array is OR-joined (any group true → condition true). Each inner array is AND-joined (every rule must match). A single rule is the JSON object `{"subject": "...", "operator": "..."|null, "object": [...]}`. Important: do NOT wrap the outer array in a `{"rules": …}` object — the plugin\'s evaluator iterates the top level directly and will fatal if the outer level is an object.',
        'example' => [
            [
                ['subject' => 'siteArea', 'operator' => null, 'object' => ['frontend']],
                ['subject' => 'postType', 'operator' => null, 'object' => ['post', 'page']],
            ],
        ],
        'workflow' => 'Create the condition: code-snippets-create-snippet { type: "cond", scope: "condition", code: <JSON.stringify(groups)>, activate: true } → capture id, e.g. 42. Gate another snippet on it: code-snippets-edit-snippet { id: <target>, condition_id: 42 }. Detach with condition_id: 0.',
    ];
}

/**
 * Hardcoded catalog of supported condition subjects, mirroring the
 * `evaluate_condition_clause()` switch in the Pro plugin's
 * php/conditions.php. Stable across the 3.6–3.9.x range we support.
 * Split into category-grouped builders to stay under the Halstead bar
 * as more subjects are added in future versions.
 *
 * @return list<array<string, mixed>>
 */
function cs_conditions_subject_catalog(): array
{
    return array_merge(
        cs_conditions_subjects_site(),
        cs_conditions_subjects_content(),
        cs_conditions_subjects_user(),
        cs_conditions_subjects_datetime(),
    );
}

/**
 * @return list<array<string, mixed>>
 */
function cs_conditions_subjects_site(): array
{
    // NOTE: a `condition` subject exists in the plugin's switch statement
    // but is stubbed as `// TODO implement this in a non-recursive way; return false;`
    // (verified in code-snippets-pro/php/conditions.php on 3.9.6). Composing
    // a condition with `subject: condition` would silently evaluate false
    // regardless of the referenced snippet's value, so we deliberately omit
    // it from the catalogue until the upstream stub is replaced — surfacing
    // it would mislead agents into broken composite conditions (Round 2 R6).
    return [
        [
            'slug' => 'siteArea',
            'category' => 'site',
            'object' => 'string (global | admin | frontend)',
            'desc' => 'Matches the current request area.',
        ],
        [
            'slug' => 'currentQuery',
            'category' => 'site',
            'object' => 'string (home | frontpage | search | archive | 404 | single | page | postTypeArchive)',
            'desc' => 'Matches when the active query type is in the object list.',
        ],
        [
            'slug' => 'visitorLanguage',
            'category' => 'site',
            'object' => 'string (BCP-47 lang tag from Accept-Language)',
            'desc' => 'Matches visitor browser language.',
        ],
        [
            'slug' => 'currentTheme',
            'category' => 'site',
            'object' => 'string (theme stylesheet or template slug)',
            'desc' => 'Matches the active theme/parent.',
        ],
        [
            'slug' => 'activePlugin',
            'category' => 'site',
            'object' => 'string (plugin file path, e.g. woocommerce/woocommerce.php)',
            'desc' => 'Matches when at least one of the listed plugins is active.',
        ],
        ['slug' => 'debugEnabled', 'category' => 'site', 'object' => 'none', 'desc' => 'True when WP_DEBUG is on.'],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function cs_conditions_subjects_content(): array
{
    return [
        [
            'slug' => 'post',
            'category' => 'content',
            'object' => 'int|string (post id or slug)',
            'desc' => 'Current request is a single() of one of the listed posts.',
        ],
        [
            'slug' => 'page',
            'category' => 'content',
            'object' => 'int|string (page id or slug)',
            'desc' => 'Current request is is_page() of one of the listed pages.',
        ],
        [
            'slug' => 'postType',
            'category' => 'content',
            'object' => 'string (post_type slug)',
            'desc' => 'Current post is one of the listed post types.',
        ],
        [
            'slug' => 'tag',
            'category' => 'content',
            'object' => 'int|string (term id or slug)',
            'desc' => 'Current post has one of the listed tags.',
        ],
        [
            'slug' => 'category',
            'category' => 'content',
            'object' => 'int|string (term id or slug)',
            'desc' => 'Current post is in one of the listed categories.',
        ],
        [
            'slug' => 'author',
            'category' => 'content',
            'object' => 'int (user id)',
            'desc' => 'Current post is authored by one of the listed users.',
        ],
        [
            'slug' => 'postStatus',
            'category' => 'content',
            'object' => 'string (publish | draft | private | …)',
            'desc' => 'Current post has one of the listed statuses.',
        ],
        [
            'slug' => 'postParent',
            'category' => 'content',
            'object' => 'int (post id)',
            'desc' => 'Current post has one of the listed parents.',
        ],
        [
            'slug' => 'postPublished',
            'category' => 'content',
            'object' => 'ISO 8601 date string(s)',
            'desc' => 'Date conditions on the post publish date. Use with operator before/after/between.',
        ],
        [
            'slug' => 'postModified',
            'category' => 'content',
            'object' => 'ISO 8601 date string(s)',
            'desc' => 'Date conditions on the post modified date. Use with operator before/after/between.',
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function cs_conditions_subjects_user(): array
{
    return [
        [
            'slug' => 'authenticated',
            'category' => 'user',
            'object' => 'bool',
            'desc' => 'object[0]=true → require login. object[0]=false → require anonymous.',
        ],
        [
            'slug' => 'user',
            'category' => 'user',
            'object' => 'int (user id)',
            'desc' => 'Current user is one of the listed users.',
        ],
        [
            'slug' => 'userRole',
            'category' => 'user',
            'object' => 'string (role slug)',
            'desc' => 'Current user has at least one of the listed roles.',
        ],
        [
            'slug' => 'userCap',
            'category' => 'user',
            'object' => 'string (capability)',
            'desc' => 'Current user has at least one of the listed capabilities.',
        ],
        [
            'slug' => 'userLocale',
            'category' => 'user',
            'object' => 'string (locale, e.g. en_US)',
            'desc' => 'Current user locale matches.',
        ],
        [
            'slug' => 'userRegistered',
            'category' => 'user',
            'object' => 'ISO 8601 date string(s)',
            'desc' => 'Date conditions on user_registered. Use with operator before/after/between.',
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function cs_conditions_subjects_datetime(): array
{
    return [
        [
            'slug' => 'timeOfDay',
            'category' => 'datetime',
            'object' => 'ISO 8601 datetime string(s)',
            'desc' => 'Wall-clock time conditions. Use with operator before/after/between.',
        ],
        [
            'slug' => 'currentDate',
            'category' => 'datetime',
            'object' => 'ISO 8601 date string(s)',
            'desc' => 'Calendar-date conditions. Use with operator before/after/between.',
        ],
        [
            'slug' => 'dayOfWeek',
            'category' => 'datetime',
            'object' => 'string (Mon | Tue | Wed | Thu | Fri | Sat | Sun)',
            'desc' => 'Current weekday is in the list.',
        ],
    ];
}
