<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// list-template-conditions — read the catalogue for a CPT (no id required)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/oxygen-list-template-conditions', [
    'label' => __('List Oxygen Template Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Returns the catalogue of conditions an Oxygen template (or header/footer) can use to scope visibility. The catalogue is CPT-specific: `oxygen_template` exposes the `single`, `archive`, `404`, `search` shape while `oxygen_header` / `oxygen_footer` additionally surface the "Everywhere" pseudo-rule. Each row is a `{slug, label, values, ...}` record sourced live from `Breakdance\\Themeless\\getConditionsWithValuesForPostType`, the same source the admin SPA uses. For entity pickers (posts, authors, taxonomies, products), `values` is only the current capped preview, not an exhaustive allow-list; a valid id omitted from that preview is still accepted by set-template-conditions in the native array shape. Use the `slug` field as the `ruleCategorySlug` / `ruleSlug` value when composing a payload. The response also includes `template_types`: the registered values valid for a template\'s `type` field (set via create-template / edit-template). Each is `{slug, label, category}`; for a single template targeting a custom post type the slug IS that post type\'s slug, alongside generic types like `all-singles`, archive types, `404`, `search`, `front-page` (hyphenated — the engine registers `front-page`, not `front_page`).',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'enum' => [
                    'oxygen_template',
                    'oxygen_header',
                    'oxygen_footer',
                    'oxygen_block',
                ],
                'description' => 'CPT whose condition catalogue to read. Required.',
            ],
        ],
        'required' => ['post_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'conditions' => ['type' => 'array'],
            'total' => ['type' => 'integer'],
            'template_types' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Registered template-type values for the `type` field: each `{slug, label, category}`. For a single-CPT template the slug is the post type slug.',
            ],
        ],
        'required' => ['conditions', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_list_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once per CPT before composing a set-template-conditions payload. The catalogue is dynamic — installed CPTs / taxonomies / WooCommerce all add rows. Treat entity-picker values as a preview, not a closed schema.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_type: string} $input
 * @return array<string, mixed>
 */
function oxygen_list_template_conditions(array $input): array
{
    if (!function_exists('Breakdance\\Themeless\\getConditionsWithValuesForPostType')) {
        return ['conditions' => [], 'total' => 0, 'template_types' => []];
    }
    ox_ensure_template_conditions_registered();
    $conditions = \Breakdance\Themeless\getConditionsWithValuesForPostType($input['post_type']);
    return [
        'conditions' => array_values($conditions),
        'total' => count($conditions),
        'template_types' => ox_registered_template_types(),
    ];
}

/**
 * The registered "template type" slugs valid for a template's `type`
 * field, sourced live from the same registry the builder's SPA uses.
 * For a single template targeting a custom post type the slug IS the
 * CPT slug (e.g. `location`); the rest are generic. Without this the
 * valid `type` values are undiscoverable through the API.
 *
 * @return list<array{slug: string, label: string, category: string}>
 */
function ox_registered_template_types(): array
{
    if (!class_exists('Breakdance\\Themeless\\ThemelessController')) {
        return [];
    }
    ox_ensure_template_conditions_registered();
    $categories = \Breakdance\Themeless\ThemelessController::getInstance()->templateTypeCategories;

    $types = [];
    foreach ($categories as $category) {
        $label = $category['label'];
        foreach ($category['types'] as $type) {
            /** @var mixed $slug */
            $slug = $type['slug'] ?? null;
            if (!is_string($slug)) {
                continue;
            }
            /** @var mixed $type_label */
            $type_label = $type['label'] ?? null;
            $types[] = [
                'slug' => $slug,
                'label' => is_string($type_label) ? $type_label : $slug,
                'category' => $label,
            ];
        }
    }
    return $types;
}

/**
 * Validate a template `settings.type` against the live registry. Empty/absent
 * is allowed (an unscoped template). A non-empty unknown type is rejected: the
 * engine persists it verbatim and the template then never matches or renders.
 * Returns a WP_Error to bail with, or null when the type is acceptable.
 *
 * @param mixed $type
 */
function ox_validate_template_type(mixed $type): ?\WP_Error
{
    if ($type === null || $type === '') {
        return null;
    }
    if (!is_string($type)) {
        return ox_error('oxygen_invalid_input', __('`settings.type` must be a string.', domain: 'novamira-pro'));
    }
    $slugs = array_column(ox_registered_template_types(), 'slug');
    if (!in_array($type, $slugs, strict: true)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: the rejected template-type slug */
            __(
                '`settings.type` `%s` is not a registered Oxygen template type; discover valid values via list-template-conditions (`template_types`).',
                domain: 'novamira-pro',
            ),
            $type,
        ));
    }
    return null;
}

/**
 * Validate a `rule_groups` payload against the live condition catalogue for a
 * post type BEFORE writing. The native matcher (`plugin/themeless/request.php`)
 * keys each rule on `ruleSlug` + `operand` + `value`; a rule whose `ruleSlug`
 * is not a registered condition is persisted verbatim and silently never
 * matches, so the template never renders. Rejects unknown slugs, empty rules,
 * operands the condition does not support, a value whose SHAPE (scalar vs
 * multiselect array) mismatches the native callback, a value outside the fixed
 * set, a dynamic-data rule missing its `ruleDynamic` shortcode, and — on
 * `oxygen_template` with a concrete `settings.type` — a condition not offered
 * for that template type. Returns a WP_Error to bail with, or null when every
 * rule is recognised.
 *
 * @param array<array-key, mixed> $rule_groups
 * @param string $template_type the template's own `settings.type` ('' when unscoped)
 */
function ox_validate_rule_groups(array $rule_groups, string $post_type, string $template_type = ''): ?\WP_Error
{
    if (!function_exists('Breakdance\\Themeless\\getConditionsWithValuesForPostType')) {
        return null;
    }
    ox_ensure_template_conditions_registered();
    $catalogue = ox_condition_catalogue_by_slug($post_type);
    /** @var mixed $group */
    foreach ($rule_groups as $group_index => $group) {
        if (!is_array($group)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %d: group index */
                __('rule_groups[%d] must be a list of rules.', domain: 'novamira-pro'),
                (int) $group_index,
            ));
        }
        // An EMPTY group is a silent never-match, not a no-op: the native
        // matcher returns false for a group with zero rules
        // (`themeless/request.php` doesRuleGroupApply: `count($ruleGroup) < 1`
        // → false), and groups OR together, so `[[]]` alone means the template
        // NEVER renders — while `[]` (no groups at all) means it always
        // renders within its type. Rejecting beats normalising because the two
        // intents are opposites and we cannot guess which one was meant.
        if ($group === []) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %d: group index */
                __(
                    'rule_groups[%d] is an empty group. The engine evaluates an empty AND-group to false, so it NEVER matches — this is not "no conditions". Pass `rule_groups: []` (no groups) to match everywhere within the template\'s type, or put at least one rule in the group.',
                    domain: 'novamira-pro',
                ),
                (int) $group_index,
            ));
        }
        /** @var mixed $rule */
        foreach ($group as $rule_index => $rule) {
            $where = sprintf('rule_groups[%d][%d]', (int) $group_index, (int) $rule_index);
            $error = ox_validate_single_rule($rule, $catalogue, $post_type, $template_type, $where);
            if ($error !== null) {
                return $error;
            }
        }
    }
    return null;
}

/**
 * Rewrite each rule's `operand` to the exact catalogue spelling. The validator
 * is lenient (it accepts `is_not` / `IS NOT` for `is not`), but the native
 * matcher compares against the canonical operand string, so a lenient form must
 * be normalised to the real one before it is persisted or it never matches.
 * Rules whose slug/operand cannot be resolved are left untouched (they were
 * already validated). Pure — returns a new structure.
 *
 * @param array<array-key, mixed> $rule_groups
 * @return list<mixed>
 */
function ox_canonicalize_rule_groups(array $rule_groups, string $post_type): array
{
    if (!function_exists('Breakdance\\Themeless\\getConditionsWithValuesForPostType')) {
        return array_values($rule_groups);
    }
    ox_ensure_template_conditions_registered();
    $catalogue = ox_condition_catalogue_by_slug($post_type);
    $out = [];
    /** @var mixed $group */
    foreach ($rule_groups as $group) {
        if (!is_array($group)) {
            $out[] = $group;
            continue;
        }
        $new_group = [];
        /** @var mixed $rule */
        foreach ($group as $rule) {
            if (!is_array($rule)) {
                $new_group[] = $rule;
                continue;
            }
            $canonical = ox_canonicalize_rule_operand($rule, $catalogue);
            $new_group[] = ox_canonicalize_rule_value($canonical);
        }
        $out[] = $new_group;
    }
    return $out;
}

/**
 * Normalize Oxygen's native `{text,value}` multiselect rows to the canonical
 * scalar list consumed by request-time matching.
 *
 * @param array<array-key, mixed> $rule
 * @return array<array-key, mixed>
 */
function ox_canonicalize_rule_value(array $rule): array
{
    if (!isset($rule['value']) || !is_array($rule['value'])) {
        return $rule;
    }
    $normalized = [];
    /** @var mixed $item */
    foreach ($rule['value'] as $item) {
        $normalized[] = ox_canonical_rule_value_item($item);
    }
    $rule['value'] = $normalized;
    return $rule;
}

/** Normalize one native `{text,value}` option row, or preserve a scalar. */
function ox_canonical_rule_value_item(mixed $item): mixed
{
    if (!is_array($item) || !isset($item['value'])) {
        return $item;
    }
    /** @var mixed $value */
    $value = $item['value'];
    return is_string($value) || is_int($value) ? $value : $item;
}

/**
 * Replace a single rule's operand with the catalogue's canonical spelling whose
 * normalised form matches; leaves the rule unchanged when it can't resolve.
 *
 * @param array<array-key, mixed> $rule
 * @param array<string, array{operands: list<string>, values: list<string>|null, valueInputType: string|null, availableForType: list<string>|null, multiselect: bool, closedSet: bool}> $catalogue
 * @return array<array-key, mixed>
 */
function ox_canonicalize_rule_operand(array $rule, array $catalogue): array
{
    /** @var mixed $slug */
    $slug = $rule['ruleSlug'] ?? null;
    /** @var mixed $operand */
    $operand = $rule['operand'] ?? null;
    if (!is_string($slug) || !is_string($operand) || !isset($catalogue[$slug])) {
        return $rule;
    }
    $normalize = static fn(string $value): string => strtr(strtolower($value), [' ' => '', '_' => '']);
    $target = $normalize($operand);
    foreach ($catalogue[$slug]['operands'] as $canonical) {
        if ($normalize($canonical) !== $target) {
            continue;
        }
        $rule['operand'] = $canonical;
        return $rule;
    }
    return $rule;
}

/**
 * Map each registered condition slug to the full contract the validator needs,
 * for the given post type. Empty when the catalogue is unavailable. Per row:
 *  - `operands`: the operand strings the condition supports.
 *  - `values`: the flat list of currently advertised item values, or null for a
 *    free-form / value-less condition (post-id, search, 404, …). Some native
 *    entity pickers expose only a ten-item preview, so this is NOT automatically
 *    an exhaustive allow-list.
 *  - `valueInputType`: `'number'` / `'dropdown'` / null — a native UI hint that
 *    disambiguates scalar single-value conditions from multiselect ones.
 *  - `availableForType`: the template-type slugs the condition is offered for
 *    (`['ALL']` wildcard), or null when the condition declares none.
 *  - `multiselect`: whether the native callback consumes `value` as an ARRAY
 *    (does `in_array($x, $value)` — post-status/post-type/has-taxonomy/…). A
 *    scalar there is a native TypeError; an array where scalar is expected never
 *    matches. Derived from the operands (one-of/all-of/none-of) and the
 *    condition family and operands, with the preview shape only as a fallback
 *    for third-party conditions that do not publish an arity contract.
 *  - `closedSet`: whether `values` is known to be exhaustive and can therefore
 *    safely be used for membership validation. Dynamic post/author/taxonomy
 *    previews are deliberately open sets.
 * This is the same live source the admin SPA uses, but its entity pickers are
 * previews rather than schemas. Shape and membership are therefore separate.
 *
 * @return array<string, array{operands: list<string>, values: list<string>|null, valueInputType: string|null, availableForType: list<string>|null, multiselect: bool, closedSet: bool}>
 */
function ox_condition_catalogue_by_slug(string $post_type): array
{
    $catalogue = \Breakdance\Themeless\getConditionsWithValuesForPostType($post_type);
    $out = [];
    foreach ($catalogue as $row) {
        /** @var mixed $slug */
        $slug = $row['slug'] ?? null;
        if (!is_string($slug)) {
            continue;
        }
        $operands = ox_condition_string_list($row['operands'] ?? null);
        $values = ox_condition_allowed_values($row['values'] ?? null);
        $value_input = isset($row['valueInputType']) && is_string($row['valueInputType'])
            ? $row['valueInputType']
            : null;
        $available = ox_condition_string_list($row['availableForType'] ?? null);
        $out[$slug] = [
            'operands' => $operands,
            'values' => $values,
            'valueInputType' => $value_input,
            'availableForType' => $available === [] ? null : $available,
            'multiselect' => ox_condition_is_multiselect($slug, $operands, $values, $value_input),
            'closedSet' => ox_condition_is_closed_set($slug, $values, $value_input),
        ];
    }
    return $out;
}

/**
 * Coerce a mixed value into a flat list of strings (drops non-strings). Empty
 * list when the input is not an array.
 *
 * @param mixed $value
 * @return list<string>
 */
function ox_condition_string_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach ($value as $item) {
        if (!is_string($item)) {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * Whether a condition consumes its `value` as an ARRAY (multiselect). Oxygen
 * does not publish this in its condition schema. Derive it first from the native
 * condition family (important for empty/capped entity pickers), then from set
 * operands. The populated preview is only a compatibility fallback for
 * third-party conditions: it must never be the authority for known families.
 *
 * @param list<string>      $operands
 * @param list<string>|null $values
 */
function ox_condition_is_multiselect(string $slug, array $operands, ?array $values, ?string $value_input): bool
{
    if (
        str_starts_with($slug, 'post-dropdown-')
        || str_starts_with($slug, 'has-parent-')
        || in_array(
            $slug,
            [
                'post-status',
                'post-author',
                'post-type',
                'content-created-with',
                'author',
                'post-type-archive',
                'taxonomy',
                'product-is-taxonomy',
            ],
            strict: true,
        )
    ) {
        return true;
    }
    $normalize = static fn(string $value): string => strtr(strtolower($value), [' ' => '', '_' => '', '-' => '']);
    foreach ($operands as $operand) {
        if (in_array($normalize($operand), ['isoneof', 'isallof', 'isnoneof'], strict: true)) {
            return true;
        }
    }
    if (in_array($value_input, ['number', 'dropdown'], strict: true)) {
        return false;
    }
    return $values !== null;
}

/**
 * Whether the advertised values are an exhaustive enum. Oxygen resolves each
 * condition's `values` callback for the SPA, but post/author/term/product
 * pickers return only the first `TEMPLATE_POSTS_LIMIT` rows until the user
 * searches. Those lists prove discoverability, not invalidity of an omitted id.
 *
 * Keep membership validation deliberately conservative: scalar dropdowns and
 * the native finite multiselect families below are closed; an unknown/plugin
 * multiselect is shape-validated only because the registry provides no
 * `closedSet` marker.
 *
 * @param list<string>|null $values
 */
function ox_condition_is_closed_set(string $slug, ?array $values, ?string $value_input): bool
{
    if ($values === null) {
        return false;
    }
    if ($value_input === 'dropdown') {
        return true;
    }
    return in_array(
        $slug,
        [
            'day-of-week',
            'current-month',
            'browser-name',
            'operating-system-name',
            'breakpoints',
            'content-created-with',
            'user-role',
            'post-status',
            'post-type',
            'post-type-archive',
            'woocommerce-stock-status',
            'woocommerce-product-tax-status',
            'woocommerce-product-tax-class',
        ],
        strict: true,
    );
}

/**
 * Flatten a condition's `values` into the flat list of allowed item values, or
 * null when the condition is free-form / value-less. After
 * `getConditionsWithValuesForPostType`, `values` is `false` for free-form
 * conditions and otherwise a list of `{label, items: [{text, value}]}` groups.
 * An EMPTY set (no items — e.g. a dropdown with nothing to pick yet) also maps
 * to null so membership is not enforced against an empty catalogue, which would
 * false-reject every value.
 *
 * @param mixed $values
 * @return list<string>|null
 */
function ox_condition_allowed_values(mixed $values): ?array
{
    if (!is_array($values)) {
        return null;
    }
    $out = [];
    /** @var mixed $group */
    foreach ($values as $group) {
        if (!is_array($group) || !isset($group['items']) || !is_array($group['items'])) {
            continue;
        }
        /** @var mixed $item */
        foreach ($group['items'] as $item) {
            if (!is_array($item) || !isset($item['value']) || !(is_string($item['value']) || is_int($item['value']))) {
                continue;
            }
            $out[] = (string) $item['value'];
        }
    }
    return $out === [] ? null : $out;
}

/**
 * Validate one rule against the condition catalogue. Rejects (each matching a
 * native `doesRuleApply` failure mode that would otherwise be a silent
 * never-match): an EMPTY / slug-less rule (native `doesRuleApply([])` → false);
 * an unknown slug; a condition not offered for the template's own type
 * (`availableForType`, enforced conservatively — see below); a missing/invalid
 * operand; a `value` whose shape or membership the native callback rejects; and
 * a dynamic-data rule missing its `ruleDynamic` shortcode. Returns a WP_Error,
 * else null.
 *
 * @param mixed $rule
 * @param array<string, array{operands: list<string>, values: list<string>|null, valueInputType: string|null, availableForType: list<string>|null, multiselect: bool, closedSet: bool}> $catalogue
 * @param string $template_type the template's own `settings.type` ('' when unscoped)
 */
function ox_validate_single_rule(
    mixed $rule,
    array $catalogue,
    string $post_type,
    string $template_type,
    string $where,
): ?\WP_Error {
    if (!is_array($rule)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: rule path */
            __('%s must be an object.', domain: 'novamira-pro'),
            $where,
        ));
    }
    /** @var mixed $slug */
    $slug = $rule['ruleSlug'] ?? null;
    // A rule with no ruleSlug (an empty `{}` / `[]`) is NOT a no-op the matcher
    // tolerates: native `doesRuleApply` returns false for it, and since a group
    // ANDs its rules, one empty rule makes the whole group impossible. Reject it.
    if ($slug === null || $slug === '' || is_string($slug) && trim($slug) === '') {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: rule path */
            __(
                '%s has no `ruleSlug`. An empty rule never matches (it makes its whole AND-group impossible). Give every rule a registered condition slug, or remove it.',
                domain: 'novamira-pro',
            ),
            $where,
        ));
    }
    if (!is_string($slug) || !array_key_exists($slug, $catalogue)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: slug, 3: post type */
            __(
                '%1$s.ruleSlug `%2$s` is not a registered condition for `%3$s`; discover valid slugs via list-template-conditions.',
                domain: 'novamira-pro',
            ),
            $where,
            is_string($slug) ? $slug : '(non-string)',
            $post_type,
        ));
    }
    $row = $catalogue[$slug];
    $type_error = ox_rule_available_for_type($row, $post_type, $template_type, $slug, $where);
    if ($type_error instanceof \WP_Error) {
        return $type_error;
    }
    $operand_error = ox_validate_rule_operand($row['operands'], $rule, $slug, $where);
    if ($operand_error instanceof \WP_Error) {
        return $operand_error;
    }
    /** @var mixed $operand */
    $operand = $rule['operand'] ?? null;
    $dynamic_error = ox_validate_rule_dynamic($rule, $slug, $where);
    if ($dynamic_error instanceof \WP_Error) {
        return $dynamic_error;
    }
    return ox_validate_rule_value($rule, $row, is_string($operand) ? $operand : '', $slug, $where);
}

/**
 * Conservative `availableForType` gate. The native request-time matcher does NOT
 * enforce it (the SPA does, client-side), so we only reject when we can be
 * CERTAIN the combination is invalid — never risking a false-reject on a
 * legitimate one: only for `oxygen_template` (headers/footers use the SPA-only
 * `everywhere` pseudo-type our catalogue does not carry), only when the template
 * has a concrete `settings.type`, and only when the condition declares an
 * `availableForType` list that contains neither the wildcard `ALL` nor that
 * type. Returns a WP_Error, else null.
 *
 * @param array{availableForType: list<string>|null, ...} $row
 */
function ox_rule_available_for_type(
    array $row,
    string $post_type,
    string $template_type,
    string $slug,
    string $where,
): ?\WP_Error {
    $available = $row['availableForType'];
    if ($post_type !== 'oxygen_template' || $template_type === '' || $available === null || $available === []) {
        return null;
    }
    if (in_array('ALL', $available, strict: true) || in_array($template_type, $available, strict: true)) {
        return null;
    }
    return ox_error('oxygen_invalid_input', sprintf(
        /* translators: 1: rule path, 2: slug, 3: template type, 4: available types */
        __(
            '%1$s condition `%2$s` is not available for a template of type `%3$s` (it applies to: %4$s). Change the template type or pick a condition offered for it.',
            domain: 'novamira-pro',
        ),
        $where,
        $slug,
        $template_type,
        implode(', ', $available),
    ));
}

/**
 * A condition that defines operands REQUIRES a valid one; the native matcher
 * never matches a rule with a missing/unknown operand. Lenient forms
 * (`is_not` / `IS NOT`) are accepted here and canonicalised before the write.
 * Returns a WP_Error, else null.
 *
 * @param list<string> $operands
 * @param array<array-key, mixed> $rule
 */
function ox_validate_rule_operand(array $operands, array $rule, string $slug, string $where): ?\WP_Error
{
    if ($operands === []) {
        return null;
    }
    /** @var mixed $operand */
    $operand = $rule['operand'] ?? null;
    if (!is_string($operand) || $operand === '') {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: slug, 3: operand list */
            __('%1$s.operand is required for condition `%2$s`; valid operands: %3$s.', domain: 'novamira-pro'),
            $where,
            $slug,
            implode(', ', $operands),
        ));
    }
    $normalize = static fn(string $value): string => strtr(strtolower($value), [' ' => '', '_' => '']);
    $valid = array_map($normalize, $operands);
    if (!in_array($normalize($operand), $valid, strict: true)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: operand, 3: slug */
            __('%1$s.operand `%2$s` is not valid for condition `%3$s`.', domain: 'novamira-pro'),
            $where,
            $operand,
            $slug,
        ));
    }
    return null;
}

/**
 * The `dynamic-data` condition's native callback returns false unless a
 * non-empty `ruleDynamic` shortcode is present, regardless of operand — so a
 * dynamic-data rule without it silently never matches. Require it. Returns a
 * WP_Error, else null.
 *
 * @param array<array-key, mixed> $rule
 */
function ox_validate_rule_dynamic(array $rule, string $slug, string $where): ?\WP_Error
{
    if ($slug !== 'dynamic-data') {
        return null;
    }
    /** @var mixed $dynamic */
    $dynamic = $rule['ruleDynamic'] ?? null;
    $shortcode = ox_validate_dynamic_rule_envelope($dynamic, $where);
    if ($shortcode instanceof \WP_Error) {
        return $shortcode;
    }
    $attributes = ox_dynamic_rule_attributes($shortcode, $where);
    if ($attributes instanceof \WP_Error) {
        return $attributes;
    }
    return ox_validate_dynamic_rule_attributes($shortcode, $attributes, $where);
}

/** @return string|\WP_Error */
function ox_validate_dynamic_rule_envelope(mixed $dynamic, string $where): string|\WP_Error
{
    if (!is_string($dynamic) || trim($dynamic) === '') {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: rule path */
            __(
                '%s uses the `dynamic-data` condition, which needs a registered `[breakdance_dynamic …]` shortcode.',
                domain: 'novamira-pro',
            ),
            $where,
        ));
    }
    $shortcode = trim($dynamic);
    if (preg_match('/^\[breakdance_dynamic\b(?:[^\[\]]|\[.*?\])*\]$/', $shortcode) !== 1) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('%s.ruleDynamic must be exactly one `[breakdance_dynamic …]` shortcode.', domain: 'novamira-pro'),
            $where,
        ));
    }
    return $shortcode;
}

/** @return array<array-key, mixed>|\WP_Error */
function ox_dynamic_rule_attributes(string $shortcode, string $where): array|\WP_Error
{
    if (!function_exists('Breakdance\\DynamicData\\getAttributesFromShortcode')) {
        return ox_error('oxygen_invalid_input', __(
            'The Oxygen dynamic-shortcode parser is unavailable.',
            domain: 'novamira-pro',
        ));
    }
    /** @var mixed $attributes */
    $attributes = \Breakdance\DynamicData\getAttributesFromShortcode($shortcode);
    if (!is_array($attributes)) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('%s.ruleDynamic could not be parsed.', domain: 'novamira-pro'),
            $where,
        ));
    }
    return $attributes;
}

/**
 * @param array<array-key, mixed> $attributes
 */
function ox_validate_dynamic_rule_attributes(string $shortcode, array $attributes, string $where): ?\WP_Error
{
    $field = isset($attributes['field']) && is_string($attributes['field']) ? $attributes['field'] : '';
    if ($field === '') {
        return ox_error('oxygen_invalid_input', sprintf(
            __('%s.ruleDynamic has no `field` attribute.', domain: 'novamira-pro'),
            $where,
        ));
    }
    $field_error = function_exists(__NAMESPACE__ . '\\ox_dd_validate_field') ? ox_dd_validate_field($field) : null;
    if ($field_error instanceof \WP_Error) {
        return $field_error;
    }
    $allowed = function_exists(__NAMESPACE__ . '\\ox_dd_field_attribute_keys')
        ? ox_dd_field_attribute_keys($field)
        : null;
    if ($allowed !== null) {
        $attribute_error = ox_validate_dynamic_rule_attribute_keys($attributes, $allowed, $field, $where);
        if ($attribute_error instanceof \WP_Error) {
            return $attribute_error;
        }
    }
    return ox_evaluate_dynamic_rule_shortcode($shortcode, $where);
}

/**
 * @param array<array-key, mixed> $attributes
 * @param list<string>            $allowed
 */
function ox_validate_dynamic_rule_attribute_keys(
    array $attributes,
    array $allowed,
    string $field,
    string $where,
): ?\WP_Error {
    /** @var mixed $value */
    foreach ($attributes as $key => $value) {
        if (!is_string($key) || in_array($key, ['field', 'params'], strict: true)) {
            continue;
        }
        if (in_array($key, $allowed, strict: true) && is_scalar($value)) {
            continue;
        }
        return ox_error('oxygen_invalid_input', sprintf(
            __('%1$s.ruleDynamic attribute `%2$s` is not valid for field `%3$s`.', domain: 'novamira-pro'),
            $where,
            $key,
            $field,
        ));
    }
    return null;
}

function ox_evaluate_dynamic_rule_shortcode(string $shortcode, string $where): ?\WP_Error
{
    if (!function_exists('Breakdance\\DynamicData\\getFieldDataFromShortcode')) {
        return null;
    }
    try {
        // @mago-expect analysis:mixed-assignment
        $field_data = \Breakdance\DynamicData\getFieldDataFromShortcode($shortcode);
        if ($field_data !== null) {
            return null;
        }
        return ox_error('oxygen_invalid_input', sprintf(
            __('%s.ruleDynamic does not resolve to a registered dynamic-data field.', domain: 'novamira-pro'),
            $where,
        ));
    } catch (\Throwable $e) {
        return ox_error('oxygen_invalid_input', sprintf(
            __('%1$s.ruleDynamic could not be evaluated: %2$s', domain: 'novamira-pro'),
            $where,
            $e->getMessage(),
        ));
    }
}

/**
 * Validate a rule's `value` against the native callback's contract:
 *  - Emptiness operands (`is empty` / `is not empty`) take no value → accepted.
 *  - Every OTHER operand REQUIRES the `value` key to be present: the native
 *    matcher (`request.php:220`) returns false when it is missing, so a rule
 *    without it silently never matches — even for free-form conditions (search).
 *  - MULTISELECT conditions (post-status, post-type, has-taxonomy, …) consume
 *    `value` as an ARRAY (`in_array($x, $value)`): a scalar is a native
 *    TypeError, so we require an array and validate each item against the fixed
 *    set (when the condition has one).
 *  - SCALAR conditions (post-id, comments-number, featured-image, dynamic-data)
 *    require a single scalar; an array never matches. Membership is enforced
 *    when the condition exposes a fixed value set (dropdown).
 * Membership compares as strings because item values may be numeric ids / JSON.
 * Returns a WP_Error, else null.
 *
 * @param array<array-key, mixed> $rule
 * @param array{values: list<string>|null, multiselect: bool, closedSet: bool, ...} $row
 */
function ox_validate_rule_value(array $rule, array $row, string $operand, string $slug, string $where): ?\WP_Error
{
    $norm_op = strtr(strtolower($operand), [' ' => '', '_' => '', '-' => '']);
    if (in_array($norm_op, ['isempty', 'isnotempty'], strict: true)) {
        return null;
    }
    if (!array_key_exists('value', $rule)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: slug */
            __(
                '%1$s needs a `value` for condition `%2$s` (only the `is empty` / `is not empty` operands omit it); without it the rule never matches.',
                domain: 'novamira-pro',
            ),
            $where,
            $slug,
        ));
    }
    /** @var mixed $value */
    $value = $rule['value'];
    $allowed = $row['closedSet'] ? $row['values'] : null;
    // A COMPARISON operand always takes a scalar, even on a condition classified
    // multiselect by its operand list. `current-date` is the trap: its operands
    // include `is one of` (→ multiselect) but its `is before` / `is after`
    // callbacks want a scalar date string (an array falls through to false). Every
    // native comparison callback is scalar, so route comparisons to the scalar
    // path regardless of the condition-level multiselect flag.
    $is_comparison = in_array($norm_op, ['isbefore', 'isafter', 'isgreaterthan', 'islessthan'], strict: true);
    if ($row['multiselect'] === true && !$is_comparison) {
        return ox_validate_multiselect_value($value, $allowed, $slug, $where);
    }
    return ox_validate_scalar_value($value, $allowed, $slug, $where);
}

/**
 * Validate a multiselect condition's `value`: it MUST be an array (the native
 * callback does `in_array($x, $value)` — a scalar throws), and each item must be
 * a scalar in the fixed set when one exists.
 *
 * @param mixed $value
 * @param list<string>|null $allowed
 */
function ox_validate_multiselect_value(mixed $value, ?array $allowed, string $slug, string $where): ?\WP_Error
{
    if (!is_array($value)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: slug */
            __(
                '%1$s.value must be an ARRAY for the multiselect condition `%2$s` (the engine matches it with in_array); a single scalar is a native error. Wrap it, e.g. `["publish"]`.',
                domain: 'novamira-pro',
            ),
            $where,
            $slug,
        ));
    }
    /** @var mixed $item */
    foreach ($value as $item) {
        // @mago-expect analysis:mixed-assignment
        $canonical = ox_canonical_rule_value_item($item);
        if (!is_string($canonical) && !is_int($canonical)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: 1: rule path, 2: slug */
                __('%1$s.value items for condition `%2$s` must be scalar strings.', domain: 'novamira-pro'),
                $where,
                $slug,
            ));
        }
        if ($allowed !== null && !in_array((string) $canonical, $allowed, strict: true)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: 1: rule path, 2: value, 3: slug */
                __(
                    '%1$s.value `%2$s` is not one of the allowed values for condition `%3$s`; discover them via list-template-conditions.',
                    domain: 'novamira-pro',
                ),
                $where,
                (string) $canonical,
                $slug,
            ));
        }
    }
    return null;
}

/**
 * Validate a scalar condition's `value`: it must be a single scalar (an array
 * never matches), and — when the condition exposes a fixed set — a member of it.
 *
 * @param mixed $value
 * @param list<string>|null $allowed
 */
function ox_validate_scalar_value(mixed $value, ?array $allowed, string $slug, string $where): ?\WP_Error
{
    if (is_array($value)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: slug */
            __(
                '%1$s.value must be a single scalar for condition `%2$s`, not an array; an array never matches here.',
                domain: 'novamira-pro',
            ),
            $where,
            $slug,
        ));
    }
    if (!is_scalar($value)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: slug */
            __('%1$s.value must be a scalar for condition `%2$s`.', domain: 'novamira-pro'),
            $where,
            $slug,
        ));
    }
    if ($allowed !== null && !in_array((string) $value, $allowed, strict: true)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: rule path, 2: value, 3: slug */
            __(
                '%1$s.value `%2$s` is not one of the allowed values for condition `%3$s`; discover them via list-template-conditions.',
                domain: 'novamira-pro',
            ),
            $where,
            (string) $value,
            $slug,
        ));
    }
    return null;
}

/**
 * Force the underlying engine to populate its template-condition
 * registry. Conditions are registered lazily — only inside the builder's
 * own SPA / AJAX / template_include code paths — by firing the
 * `breakdance_register_template_types_and_conditions` action. Calling
 * `getConditionsWithValuesForPostType()` from REST without first firing
 * the action reads an empty registry and silently returns `[]`. Fire it
 * once per request; the action is idempotent.
 */
function ox_ensure_template_conditions_registered(): void
{
    static $fired = false;
    // @mago-expect analysis:impossible-condition
    if ($fired) {
        return;
    }
    $fired = true;
    if (function_exists('bdox_run_action')) {
        // Oxygen's native dispatcher fires both the historical `breakdance_*`
        // hook and its public `oxygen_*` alias. Calling do_action directly would
        // silently omit conditions registered by Oxygen-specific extensions.
        \bdox_run_action('breakdance_register_template_types_and_conditions');
        return;
    }
    do_action('breakdance_register_template_types_and_conditions');
}

// ---------------------------------------------------------------------------
// set-template-conditions — write the ruleGroups for an existing template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/oxygen-set-template-conditions', [
    'label' => __('Set Oxygen Template Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Writes the `ruleGroups` field onto an existing Oxygen template\'s `_oxygen_template_settings`. `ruleGroups` is a list of OR-groups: each top-level entry is itself a list of AND-rules. A rule is `{ruleSlug: string (required), operand: string, ruleDynamic?: string, value?: string|string[]}` (operands are the exact catalogue strings from list-template-conditions, e.g. `is`, `is not`, `is one of`; a lenient `is_not` is auto-canonicalised to `is not`). Every rule is validated against the live catalogue for the template\'s post type BEFORE writing, mirroring the native matcher: (1) `ruleSlug` is required and must be registered (an empty rule makes its whole AND-group impossible); (2) MULTISELECT conditions (post-status, post-type, has-taxonomy, …) need `value` as an ARRAY like `["publish"]` — native `{text,value}` item objects are canonicalised to their scalar `value`; (3) `value` is required for every operand except `is empty`/`is not empty`, and must be a member of the condition\'s fixed set when it has one; (4) the `dynamic-data` condition needs exactly one registered `[breakdance_dynamic ...]` shortcode; (5) on a typed `oxygen_template` a condition not offered for that type (its `availableForType`) is rejected; (6) the `custom-php` condition is GATED — the engine eval()s its `value` as PHP, so a rule using it is rejected unless you pass `allow_code_elements: true` after the user explicitly confirms. The whole bucket is replaced wholesale: pass the COMPLETE list, or `[]` to clear it. Other settings are untouched; use edit-template for the writable closed settings schema.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the template post.'],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Alias of `id`, matching the element abilities. `id` wins when both are passed.',
            ],
            'rule_groups' => [
                'type' => 'array',
                'description' => 'List of OR-groups (each a list of AND-rules). Pass `[]` to clear all conditions.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for the `custom-php` condition, whose `value` the engine eval()s as PHP on every matching request — equivalent to writing a PhpCode element. Set true ONLY after the user has explicitly confirmed they want executable code — never set it on your own.',
            ],
        ],
        'required' => ['rule_groups'],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['id']],
            ['required' => ['post_id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'template' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_set_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces the entire ruleGroups field. To CARVE EXCEPTIONS into a broad rule, use multiple rules within the SAME group (AND semantics, all must match); to UNION DIFFERENT pages/posts, use multiple groups (OR semantics, any group matches).',
            'readonly' => false,
            'destructive' => true,
            // Semantically idempotent (same payload → same final state),
            // but the WP abilities-api lib routes `destructive=true AND
            // idempotent=true` to HTTP DELETE, whose handler cannot decode
            // an `input` JSON body — the ability would be unreachable via
            // the standard REST/MCP pattern. Drop idempotent so the routing
            // falls through to POST.
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_set_template_conditions(array $input): array|WP_Error
{
    $id = ox_template_id_arg($input);
    if ($id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'id (or post_id) must be a positive integer.',
            domain: 'novamira-pro',
        ));
    }
    // Route via ox_post_or_error so revisions / auto-drafts / trashed
    // posts are rejected uniformly. The allowed-post-types filter
    // restricts to template CPTs.
    $post = ox_post_or_error($id, ox_template_post_types());
    if ($post instanceof WP_Error) {
        return $post;
    }
    if (!is_array($input['rule_groups'])) {
        return [
            'success' => false,
            'error' => '`rule_groups` must be an array of groups. Pass [] to clear all conditions.',
        ];
    }

    $rule_groups = $input['rule_groups'];
    // `custom-php` declares `supports: ['element_display', 'templating']`
    // (engine custom-php.php:15), so a TEMPLATE rule group can carry PHP the
    // engine eval()s at request time exactly like an element display condition.
    // Same gate, same flag, same double-approval requirement.
    $payloads = ox_collect_executable_payloads($rule_groups, path: 'rule_groups');
    if ($payloads !== [] && !ox_coerce_bool($input['allow_code_elements'] ?? false)) {
        return ox_code_payload_block($payloads);
    }
    // Validate every rule against the live condition catalogue BEFORE writing,
    // so an unknown ruleSlug/operand/value fails loudly instead of persisting a
    // template that silently never matches. The template's own `settings.type`
    // gates the conservative `availableForType` check.
    $existing_settings = ox_read_template_settings($id);
    $template_type = isset($existing_settings['type']) && is_string($existing_settings['type'])
        ? $existing_settings['type']
        : '';
    $rule_error = ox_validate_rule_groups($rule_groups, $post->post_type, $template_type);
    if ($rule_error instanceof WP_Error) {
        return $rule_error;
    }
    // Canonicalise each operand to the exact catalogue spelling BEFORE writing.
    // The validator accepts lenient forms (`is_not`, `IS NOT`), but the native
    // matcher compares against the canonical `is not` (with a space), so an
    // un-canonicalised operand would persist and silently never match.
    $rule_groups = ox_canonicalize_rule_groups($rule_groups, $post->post_type);
    // `_oxygen_template_settings` is a read-modify-write postmeta;
    // without a lock two concurrent edit-template / set-template-conditions
    // on the same id lose one another's patches under parallel REST.
    // Serialise per-post.
    return ox_with_lock('template_settings_' . $id, static function () use ($id, $rule_groups): array|WP_Error {
        $settings = ox_read_template_settings($id);
        $settings['ruleGroups'] = $rule_groups;
        $written = ox_write_template_settings($id, $settings);
        if ($written instanceof WP_Error) {
            return $written;
        }

        $fresh = ox_load_template($id);
        if ($fresh === null) {
            return ['success' => false, 'error' => 'Template saved but could not be re-loaded.'];
        }
        return ['success' => true, 'template' => ox_template_to_array($fresh)];
    });
}
