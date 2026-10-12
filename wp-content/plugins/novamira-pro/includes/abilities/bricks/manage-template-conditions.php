<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Abilities: Manage Bricks template conditions.
 *
 * Template conditions control where a Bricks template (header, footer, popup,
 * section, archive, search, error) renders. Without at least one matching
 * condition the template is effectively never applied — for headers/footers
 * this manifests as "the site has no header", the single most common silent
 * Bricks footgun.
 *
 * Storage: post meta `_bricks_template_settings` (BRICKS_DB_TEMPLATE_SETTINGS)
 * under the key `templateConditions` (plural). Each condition is an assoc
 * array with a `main` type plus type-specific fields — see
 * `novamira/bricks-list-template-condition-schema` for the full shape.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-template-conditions', [
    'label' => __('List Bricks Template Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Returns the conditions attached to a Bricks template. A template with an empty condition list never renders — for header/footer templates, this is the most common reason "the site has no header". Call novamira/bricks-list-template-condition-schema first to learn the condition shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'description' => 'Post ID of the bricks_template post. Use novamira/bricks-list-templates to discover ids.',
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'template_type' => [
                'type' => 'string',
                'description' => 'Bricks template type (header, footer, section, popup, archive, search, error, content).',
            ],
            'conditions' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Raw condition list as stored by Bricks. Empty when no conditions are set.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns an empty `conditions` array when none are set — that is the usual reason a header/footer template silently never renders. Inspect the shape of each returned condition with novamira/bricks-list-template-condition-schema before editing.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/bricks-set-template-conditions', [
    'label' => __('Set Bricks Template Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the full list of conditions on a Bricks template. Pass the complete desired set — this is a replace operation, not a merge. An empty list clears all conditions (the template will never render). Other template settings (categories, bundles, popup options, etc.) are preserved.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'description' => 'Post ID of the bricks_template post.',
            ],
            'conditions' => [
                'type' => 'array',
                'description' => 'The full list of conditions to apply. Each entry is an object whose shape matches novamira/bricks-list-template-condition-schema — `main` is required, the remaining keys depend on the main value (e.g. `postType` for main=postType, `ids` for main=ids, `archiveType` + optional `archivePostTypes`/`archiveTerms` for main=archiveType).',
                'items' => ['type' => 'object'],
            ],
        ],
        'required' => ['template_id', 'conditions'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'saved' => ['type' => 'boolean'],
            'conditions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal issues (fields with wrong shape, values ignored by Bricks at runtime).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_set_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Full replace — to add a condition, call novamira/bricks-list-template-conditions first, append to the returned list, and pass the full list back. Hard errors fire for missing/invalid `main`; suspicious but storable shapes produce `warnings` and still save. `hookName` / `hookPriority` only take effect on section templates; they are silently ignored on header/footer/popup/etc.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/bricks-list-template-condition-schema', [
    'label' => __('List Bricks Template Condition Schema', domain: 'novamira-pro'),
    'description' => __(
        'Describes the condition shape accepted by novamira/bricks-set-template-conditions. No inputs. Pass verbose=true to include a concrete example per main value. Use this before editing conditions — the field names are non-obvious (main=postType, not main=singular; terms use "taxonomy::term_id", not plain slugs).',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'verbose' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'If true, include a worked example for each main value.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'fields' => ['type' => 'object'],
            'examples' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_template_condition_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read this once before composing conditions. The `main` value determines which other fields are legal; fields for the wrong main are stored verbatim but ignored at render time.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Allowed values for every constrained field. Mirrors
 * `Bricks\Settings_Template::$controls['templateConditions']` — keep in sync
 * when Bricks adds or removes a main type.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_TEMPLATE_CONDITION_MAIN_VALUES = [
    'any',
    'frontpage',
    'postType',
    'archiveType',
    'search',
    'error',
    'terms',
    'ids',
];

/**
 * @var list<string>
 */
const NOVAMIRA_BRICKS_TEMPLATE_CONDITION_ARCHIVE_TYPES = ['any', 'postType', 'author', 'date', 'term'];

function bricks_list_template_conditions(array $input): array|WP_Error
{
    $template_id = (int) $input['template_id'];

    $check = tc_require_template($template_id);
    if ($check !== null) {
        return $check;
    }

    return [
        'template_id' => $template_id,
        'template_type' => bricks_template_type($template_id),
        'conditions' => tc_read_conditions($template_id),
    ];
}

function bricks_set_template_conditions(array $input): array|WP_Error
{
    $template_id = (int) $input['template_id'];

    $check = tc_require_template($template_id);
    if ($check !== null) {
        return $check;
    }

    /** @var list<mixed> $raw */
    $raw = is_array($input['conditions'] ?? null) ? $input['conditions'] : [];

    $normalized = bricks_normalize_template_conditions($raw);
    if (is_wp_error($normalized)) {
        return $normalized;
    }

    $template_type = bricks_template_type($template_id);
    $warnings = tc_collect_warnings($normalized['conditions'], $template_type);
    foreach ($normalized['warnings'] as $w) {
        $warnings[] = $w;
    }

    /** @var array<string, mixed>|false $raw_settings */
    $raw_settings = get_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, single: true);
    $settings = is_array($raw_settings) ? $raw_settings : [];

    if ($normalized['conditions'] === []) {
        unset($settings['templateConditions']);
    }
    if ($normalized['conditions'] !== []) {
        $settings['templateConditions'] = $normalized['conditions'];
    }

    if ($settings === []) {
        delete_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS);
    }
    if ($settings !== []) {
        update_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, $settings);
    }
    clean_post_cache($template_id);

    $result = [
        'template_id' => $template_id,
        'saved' => true,
        'conditions' => $normalized['conditions'],
    ];
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

function bricks_list_template_condition_schema(array $input): array
{
    $verbose = ($input['verbose'] ?? false) === true;

    $fields = [
        'main' => [
            'required' => true,
            'type' => 'string',
            'values' => NOVAMIRA_BRICKS_TEMPLATE_CONDITION_MAIN_VALUES,
            'description' => 'Top-level condition type. `any` = entire website. `frontpage` = the site front page. `postType` = every post of the listed post types. `archiveType` = archive pages (see archiveType subfield). `search` = search results page. `error` = 404 page. `terms` = posts assigned to the listed terms. `ids` = specific post ids.',
        ],
        'postType' => [
            'type' => 'array<string>',
            'applies_when' => 'main=postType',
            'description' => 'List of post type slugs (e.g. ["post","page","product"]). When main=postType and this is empty, the condition never matches.',
        ],
        'archiveType' => [
            'type' => 'array<string>',
            'values' => NOVAMIRA_BRICKS_TEMPLATE_CONDITION_ARCHIVE_TYPES,
            'applies_when' => 'main=archiveType',
            'description' => 'Which archive kinds to match. `any` covers every archive. `postType` covers post-type archives (use `archivePostTypes` to narrow). `term` covers taxonomy archives (use `archiveTerms` to narrow).',
        ],
        'archivePostTypes' => [
            'type' => 'array<string>',
            'applies_when' => 'main=archiveType and archiveType includes "postType"',
            'description' => 'Post type slugs whose archive pages match. Empty = all post type archives match.',
        ],
        'archiveTerms' => [
            'type' => 'array<string>',
            'applies_when' => 'main=archiveType and archiveType includes "term"',
            'description' => 'Term identifiers in "taxonomy::term_id" form, e.g. "category::12". Use "taxonomy::all" to match every term of a taxonomy. Empty = all term archives match.',
        ],
        'archiveTermsIncludeChildren' => [
            'type' => 'boolean',
            'applies_when' => 'main=archiveType and archiveType includes "term"',
            'description' => 'When true, child terms of `archiveTerms` also match.',
        ],
        'terms' => [
            'type' => 'array<string>',
            'applies_when' => 'main=terms',
            'description' => 'Term identifiers in "taxonomy::term_id" form. The template matches any post assigned at least one of these terms.',
        ],
        'ids' => [
            'type' => 'array<integer>',
            'applies_when' => 'main=ids',
            'description' => 'Specific post ids. Mixing post types is allowed.',
        ],
        'idsIncludeChildren' => [
            'type' => 'boolean',
            'applies_when' => 'main=ids',
            'description' => 'When true, descendants (children, grandchildren, …) of `ids` also match.',
        ],
        'hookName' => [
            'type' => 'string',
            'applies_when' => 'template_type=section',
            'description' => 'Optional WordPress action hook name (e.g. "wp_body_open", "bricks_before_footer"). Only section templates render through hooks; this field is silently ignored on other template types.',
        ],
        'hookPriority' => [
            'type' => 'integer',
            'applies_when' => 'template_type=section and hookName is set',
            'description' => 'Action priority (default 10).',
        ],
        'exclude' => [
            'type' => 'boolean',
            'description' => 'When true, this condition is an exclusion — posts matching it are excluded from the template target set.',
        ],
    ];

    $result = ['fields' => $fields];

    if ($verbose) {
        $result['examples'] = [
            ['main' => 'any'],
            ['main' => 'frontpage'],
            ['main' => 'postType', 'postType' => ['post']],
            [
                'main' => 'archiveType',
                'archiveType' => ['term'],
                'archiveTerms' => ['category::12'],
                'archiveTermsIncludeChildren' => true,
            ],
            ['main' => 'archiveType', 'archiveType' => ['postType'], 'archivePostTypes' => ['product']],
            ['main' => 'search'],
            ['main' => 'error'],
            ['main' => 'terms', 'terms' => ['product_cat::42']],
            ['main' => 'ids', 'ids' => [123, 456], 'idsIncludeChildren' => false],
            ['main' => 'any', 'hookName' => 'bricks_before_footer', 'hookPriority' => 20],
        ];
    }

    return $result;
}

// -- Helpers ----------------------------------------------------------------

function tc_require_template(int $template_id): ?WP_Error
{
    /** @var WP_Post|null $post */
    $post = get_post($template_id);
    if (!$post instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Template %d not found.', $template_id));
    }
    if ($post->post_type !== BRICKS_DB_TEMPLATE_SLUG) {
        return bricks_error('not_template', sprintf(
            'Post %d is a "%s" — template conditions only apply to "%s" posts. Use novamira/bricks-list-templates to find valid template ids.',
            $template_id,
            $post->post_type,
            BRICKS_DB_TEMPLATE_SLUG,
        ));
    }
    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function tc_read_conditions(int $template_id): array
{
    /** @var array<string, mixed>|false $settings */
    $settings = get_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, single: true);
    if (!is_array($settings) || !is_array($settings['templateConditions'] ?? null)) {
        return [];
    }

    /** @var list<array<string, mixed>> $out */
    $out = [];
    /** @var list<mixed> $conditions */
    $conditions = $settings['templateConditions'];
    /** @var mixed $condition */
    foreach ($conditions as $condition) {
        if (!is_array($condition)) {
            continue;
        }
        /** @var array<string, mixed> $condition */
        $out[] = $condition;
    }
    return $out;
}

/**
 * Hard-validates every condition: rejects the call on missing/invalid main.
 * Coerces small shape issues (numeric-string ids, scalar instead of list for
 * the multi-value fields) and records a warning for each coercion.
 *
 * Bricks 2.4 discards malformed scalar values in condition matching instead of
 * matching them, so every multi-value key (postType, archiveType, terms, ids,
 * …) must be persisted as an array. Shared across every ability that writes
 * conditions: template conditions here, template_settings on create-template,
 * and theme-style conditions (both run through Bricks' screen_conditions).
 *
 * @param list<mixed> $raw
 * @return array{conditions: list<array<string, mixed>>, warnings: list<string>}|WP_Error
 */
function bricks_normalize_template_conditions(array $raw): array|WP_Error
{
    /** @var list<array<string, mixed>> $conditions */
    $conditions = [];
    /** @var list<string> $warnings */
    $warnings = [];
    /** @var mixed $entry */
    foreach ($raw as $index => $entry) {
        if (!is_array($entry)) {
            return bricks_error('invalid_condition', sprintf('conditions[%d] must be an object.', $index));
        }
        $normalized = tc_normalize_single_condition($entry, $index, $warnings);
        if (is_wp_error($normalized)) {
            return $normalized;
        }
        $conditions[] = $normalized;
    }
    return ['conditions' => $conditions, 'warnings' => $warnings];
}

/**
 * Per-field coercers. The value is `array<string>`, `array<int>`, `bool`, a
 * non-empty string (`hookName`), or an integer (`hookPriority`). Fields not
 * present on the input are skipped.
 *
 * @var array<string, 'string_list'|'int_list'|'bool'|'string_nonempty'|'int_or_warn'>
 */
const NOVAMIRA_BRICKS_TEMPLATE_CONDITION_FIELDS = [
    'postType' => 'string_list',
    'archiveType' => 'string_list',
    'archivePostTypes' => 'string_list',
    'archiveTerms' => 'string_list',
    'archiveTermsIncludeChildren' => 'bool',
    'terms' => 'string_list',
    'ids' => 'int_list',
    'idsIncludeChildren' => 'bool',
    'hookName' => 'string_nonempty',
    'hookPriority' => 'int_or_warn',
    'exclude' => 'bool',
];

/**
 * @param array<array-key, mixed> $entry
 * @param list<string>            $warnings
 * @return array<string, mixed>|WP_Error
 */
function tc_normalize_single_condition(array $entry, int $index, array &$warnings): array|WP_Error
{
    /** @var mixed $main */
    $main = $entry['main'] ?? null;
    if (!is_string($main) || $main === '') {
        return bricks_error('invalid_condition', sprintf('conditions[%d].main is required.', $index));
    }
    if (!in_array($main, NOVAMIRA_BRICKS_TEMPLATE_CONDITION_MAIN_VALUES, strict: true)) {
        return bricks_error('invalid_condition', sprintf(
            'conditions[%d].main "%s" is not one of: %s.',
            $index,
            $main,
            implode(', ', NOVAMIRA_BRICKS_TEMPLATE_CONDITION_MAIN_VALUES),
        ));
    }

    /** @var array<string, mixed> $out */
    $out = ['main' => $main];
    foreach (NOVAMIRA_BRICKS_TEMPLATE_CONDITION_FIELDS as $field => $kind) {
        if (!array_key_exists($field, $entry)) {
            continue;
        }
        $label = sprintf('conditions[%d].%s', $index, $field);
        /** @var mixed $raw_value */
        $raw_value = $entry[$field];
        /** @var mixed $normalized */
        $normalized = tc_coerce_field($raw_value, $kind, $label, $warnings);
        if ($normalized !== null) {
            $out[$field] = $normalized;
        }
    }

    return $out;
}

/**
 * @param list<string> $warnings
 */
function tc_coerce_field(mixed $value, string $kind, string $label, array &$warnings): mixed
{
    if ($kind === 'string_list') {
        return tc_coerce_string_list($value, $label, $warnings);
    }
    if ($kind === 'int_list') {
        return tc_coerce_int_list($value, $label, $warnings);
    }
    if ($kind === 'bool') {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
    if ($kind === 'string_nonempty') {
        return is_string($value) && $value !== '' ? $value : null;
    }
    // $kind === 'int_or_warn'
    if (is_numeric($value)) {
        return (int) $value;
    }
    $warnings[] = sprintf(
        '%s must be numeric; ignored "%s".',
        $label,
        is_scalar($value) ? (string) $value : gettype($value),
    );
    return null;
}

/**
 * Accepts a list<string>. Coerces a plain string to a single-item list (with a
 * warning) to help agents that treat the repeater as a single-value field.
 *
 * @param list<string>           $warnings
 * @return list<string>
 */
function tc_coerce_string_list(mixed $value, string $label, array &$warnings): array
{
    if (is_array($value)) {
        return array_values(array_map(static fn(mixed $v): string => (string) $v, $value));
    }
    if (is_string($value)) {
        $warnings[] = sprintf('%s should be an array of strings; wrapped the single value in a list.', $label);
        return [$value];
    }
    $warnings[] = sprintf(
        '%s has wrong shape (%s); stored as empty list.',
        $label,
        is_scalar($value) ? (string) $value : gettype($value),
    );
    return [];
}

/**
 * @param list<string> $warnings
 * @return list<int>
 */
function tc_coerce_int_list(mixed $value, string $label, array &$warnings): array
{
    if (is_array($value)) {
        $out = [];
        /** @var mixed $v */
        foreach ($value as $v) {
            if (!is_numeric($v)) {
                $warnings[] = sprintf(
                    '%s contains non-numeric entry "%s"; skipped.',
                    $label,
                    is_scalar($v) ? (string) $v : gettype($v),
                );
                continue;
            }
            $out[] = (int) $v;
        }
        return $out;
    }
    if (is_numeric($value)) {
        $warnings[] = sprintf('%s should be an array of integers; wrapped the single value in a list.', $label);
        return [(int) $value];
    }
    $warnings[] = sprintf(
        '%s has wrong shape (%s); stored as empty list.',
        $label,
        is_scalar($value) ? (string) $value : gettype($value),
    );
    return [];
}

/**
 * Cross-field sanity checks: values that validate on their own but are
 * incompatible with the condition's `main`, or required secondary fields that
 * are missing. These produce warnings, not hard errors — Bricks stores any
 * shape; flag the oddities so the agent notices.
 *
 * @param list<array<string, mixed>> $conditions
 * @return list<string>
 */
function tc_collect_warnings(array $conditions, string $template_type): array
{
    $warnings = [];
    foreach ($conditions as $index => $condition) {
        /** @var string $main */
        $main = $condition['main'];

        foreach (tc_condition_cross_field_warnings($condition, $main, $index) as $w) {
            $warnings[] = $w;
        }

        if (array_key_exists('archiveType', $condition) && is_array($condition['archiveType'])) {
            $archive_types = array_values(array_map(
                static fn(mixed $v): string => (string) $v,
                $condition['archiveType'],
            ));
            $invalid = array_values(array_diff($archive_types, NOVAMIRA_BRICKS_TEMPLATE_CONDITION_ARCHIVE_TYPES));
            if ($invalid !== []) {
                $warnings[] = sprintf(
                    'conditions[%d].archiveType contains unknown value(s): %s. Valid: %s.',
                    $index,
                    implode(', ', $invalid),
                    implode(', ', NOVAMIRA_BRICKS_TEMPLATE_CONDITION_ARCHIVE_TYPES),
                );
            }
        }

        if (array_key_exists('hookName', $condition) && $template_type !== 'section') {
            $warnings[] = sprintf(
                'conditions[%d].hookName is set but template_type is "%s" — only section templates render through action hooks; the hook will be ignored.',
                $index,
                $template_type,
            );
        }
    }

    return $warnings;
}

/**
 * Required secondary field per `main` value. A condition with main=X but no X
 * values never matches anything at render time.
 *
 * @var array<string, string>
 */
const NOVAMIRA_BRICKS_TEMPLATE_CONDITION_REQUIRED = [
    'postType' => 'postType',
    'archiveType' => 'archiveType',
    'terms' => 'terms',
    'ids' => 'ids',
];

/**
 * Fields that apply to each `main`. Anything outside this list is stored by
 * Bricks but ignored at render time — flagging it keeps agents honest.
 *
 * @var array<string, list<string>>
 */
const NOVAMIRA_BRICKS_TEMPLATE_CONDITION_ALLOWED_FIELDS = [
    'any' => [],
    'frontpage' => [],
    'search' => [],
    'error' => [],
    'postType' => ['postType'],
    'archiveType' => ['archiveType', 'archivePostTypes', 'archiveTerms', 'archiveTermsIncludeChildren'],
    'terms' => ['terms'],
    'ids' => ['ids', 'idsIncludeChildren'],
];

/**
 * Fields checked when deciding whether a condition has "any other than main+common fields".
 * Common fields (`main`, `hookName`, `hookPriority`, `exclude`) are legal on
 * every main value, so they don't appear here.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_TEMPLATE_CONDITION_MAIN_SPECIFIC = [
    'postType',
    'archiveType',
    'archivePostTypes',
    'archiveTerms',
    'archiveTermsIncludeChildren',
    'terms',
    'ids',
    'idsIncludeChildren',
];

/**
 * @param array<string, mixed> $condition
 * @return list<string>
 */
function tc_condition_cross_field_warnings(array $condition, string $main, int $index): array
{
    $warnings = [];

    $required = NOVAMIRA_BRICKS_TEMPLATE_CONDITION_REQUIRED[$main] ?? null;
    if ($required !== null) {
        /** @var mixed $value */
        $value = $condition[$required] ?? null;
        $present = is_array($value) ? $value !== [] : $value !== null;
        if (!$present) {
            $warnings[] = sprintf(
                'conditions[%d] has main="%s" but no `%s` values — the condition will never match anything.',
                $index,
                $main,
                $required,
            );
        }
    }

    $allowed = NOVAMIRA_BRICKS_TEMPLATE_CONDITION_ALLOWED_FIELDS[$main] ?? [];
    foreach (NOVAMIRA_BRICKS_TEMPLATE_CONDITION_MAIN_SPECIFIC as $field) {
        if (!array_key_exists($field, $condition)) {
            continue;
        }
        if (in_array($field, $allowed, strict: true)) {
            continue;
        }
        $warnings[] = sprintf(
            'conditions[%d] has main="%s" — `%s` only applies to a different main and will be ignored at render time.',
            $index,
            $main,
            $field,
        );
    }

    if ($main === 'archiveType') {
        foreach (tc_archive_type_warnings($condition, $index) as $w) {
            $warnings[] = $w;
        }
    }

    return $warnings;
}

/**
 * @param array<string, mixed> $condition
 * @return list<string>
 */
function tc_archive_type_warnings(array $condition, int $index): array
{
    $warnings = [];
    /** @var list<string> $archive_types */
    $archive_types = is_array($condition['archiveType'] ?? null) ? $condition['archiveType'] : [];

    /** @var mixed $archive_post_types */
    $archive_post_types = $condition['archivePostTypes'] ?? null;
    $has_post_types = is_array($archive_post_types) && $archive_post_types !== [];
    if ($has_post_types && !in_array('postType', $archive_types, strict: true)) {
        $warnings[] = sprintf(
            'conditions[%d].archivePostTypes is set but archiveType does not include "postType" — the filter has no effect.',
            $index,
        );
    }

    $has_term_filter =
        array_key_exists('archiveTerms', $condition) || array_key_exists('archiveTermsIncludeChildren', $condition);
    if ($has_term_filter && !in_array('term', $archive_types, strict: true)) {
        $warnings[] = sprintf(
            'conditions[%d].archiveTerms / archiveTermsIncludeChildren set but archiveType does not include "term" — the filter has no effect.',
            $index,
        );
    }

    return $warnings;
}
