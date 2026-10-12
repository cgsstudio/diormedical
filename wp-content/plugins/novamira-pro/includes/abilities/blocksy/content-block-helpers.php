<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Content Blocks surface (Sprint 5).
 *
 * Model verified from Blocksy Companion Pro 2.1.52 source:
 *
 *  - Content Blocks are POSTS of the `ct_content_block` CPT
 *    (framework/premium/features/content-blocks.php). The CPT — and the
 *    whole feature — exists ONLY when `Blocksy\Premium` boots, which
 *    plugin.php gates on `blocksy_companion_can_use_premium_code() &&
 *    blocksy_companion_get_capabilities()->has_feature('base_pro')`: a
 *    free-plan install has no content-block surface at all.
 *  - `template_type` is a PLAIN post meta with one of nine values:
 *    hook | popup | 404 | header | footer | archive | single |
 *    nothing_found | maintenance (admin-ui.php:316-324).
 *  - EVERYTHING else lives in ONE serialized post meta,
 *    `blocksy_post_meta_options` (read through
 *    `blocksy_get_post_options()`, inc/meta-boxes.php): the display
 *    `conditions`, the hook position (`location`, `priority`,
 *    `custom_location`, `paragraphs_count`, `headings_count`,
 *    `cards_count`, `repeat_for_every_card`, `additional_locations`),
 *    the `is_hook_enabled` yes/no kill switch, `template_subtype`, and
 *    assorted design keys. The Companion's own mutation pattern is
 *    always read-whole-array → set key → `update_post_meta()` with the
 *    whole array (admin-ui.php:664-666 and the bulk enable/disable
 *    handlers) — so per-key REPLACE with sibling preservation is the
 *    UI-faithful write semantics, and one meta UPDATE is naturally
 *    atomic per block.
 *  - Conditions grammar (`Blocksy\ConditionsManager` +
 *    `ConditionsRulesResolver` + static/js/options/ConditionsManager*):
 *    a RECURSIVE DESCRIPTOR `{relation: OR|AND, conditions: [...]}`
 *    whose rows are `{type: include|exclude, rule: <id>, payload}` rule
 *    leaves or nested `{relation, conditions}` groups (Advanced Mode) —
 *    the customizer wraps even a plain list into the root-OR descriptor
 *    before its first save (ConditionsManager.js:54-59) and edits
 *    groups recursively with no depth guard of its own. This surface
 *    mirrors that: WRITE accepts a flat list (wrapped into the same
 *    root-OR descriptor the UI saves) or a full descriptor with nested
 *    groups, validated recursively; READ presents legacy flat storage
 *    (blocks the UI never touched) as a descriptor exactly like the
 *    matcher's normalize_rules() does, without rewriting storage.
 *    Excludes are evaluated WITHIN THEIR OWN GROUP's relation, and a
 *    nested group acts as an INCLUDE in its parent (condition_matches()
 *    puts type-less rows with `conditions` into the include set): root
 *    OR(include:everywhere, AND(exclude:everywhere)) still matches,
 *    because the direct include succeeds while the failing group merely
 *    contributes no include. EMPTY conditions show the block
 *    NOWHERE (condition_matches() returns false on empty conditions for
 *    the global purpose) except for maintenance / nothing_found blocks,
 *    which default to `everywhere` (content-blocks.php:815-826). The
 *    valid rule ids are install-dependent (registered CPTs, taxonomies,
 *    editable roles, WooCommerce presence, i18n plugins, the
 *    `blocksy:conditions:rules:custom` filter) and are resolved live
 *    from `ConditionsManager::get_all_rules()` with the same per-type
 *    filter the customizer UI requests (options/<type>.php +
 *    conditions/api.php).
 *  - Hook positions: `location` must be one of
 *    `Blocksy\HooksManager::get_all_hooks()` ids or `custom_hook` (then
 *    `custom_location` names the WordPress action), `priority` is the
 *    add_action priority (UI bounds 1..100), and the three count-based
 *    virtual locations (`blocksy:single:content:paragraphs-number`,
 *    `blocksy:single:content:headings-number`,
 *    `blocksy:loop:card:cards-number`) consume the respective *_count
 *    keys. `additional_locations` holds extra rows of the same shape;
 *    display_hooks() reads `location`/`priority`/`custom_location` (and
 *    the counts) from EVERY row unguarded, so rows persisted here are
 *    normalized to the full key set.
 */

/** The Content Blocks custom post type. */
const BLY_CB_POST_TYPE = 'ct_content_block';

/** The serialized options meta every content block stores its settings in. */
const BLY_CB_META_OPTIONS = 'blocksy_post_meta_options';

/** Maximum content blocks emitted by list-content-blocks. */
const BLY_CB_MAX_LIST = 100;

/** Maximum leaf condition rules accepted (and thus persisted) per block. */
const BLY_CB_MAX_CONDITIONS = 24;

/**
 * Maximum relation-group nesting depth (root descriptor = 1). A pure
 * ANTI-ABUSE / resource bound and nothing else: the UI renders and
 * edits nested groups recursively with no depth guard of its own
 * (ConditionsWithRelation.js:30-99), and the PHP matcher recurses
 * arbitrarily — no layer asserts a production limit. This bound exists
 * solely so hostile input cannot persist unbounded trees; everything
 * within it must round-trip.
 */
const BLY_CB_MAX_CONDITION_DEPTH = 10;

/** Maximum additional_locations rows accepted per block. */
const BLY_CB_MAX_ADDITIONAL_LOCATIONS = 10;

/** Maximum byte length for a content-block title on create. */
const BLY_CB_MAX_TITLE_BYTES = 200;

/** Maximum byte length for free-text payload strings (referer, url). */
const BLY_CB_MAX_PAYLOAD_TEXT_BYTES = 500;

/** Maximum byte length for a cookie-name payload. */
const BLY_CB_MAX_COOKIE_BYTES = 128;

/** Maximum byte length for language / date-time payload strings. */
const BLY_CB_MAX_SHORT_PAYLOAD_BYTES = 64;

/** Maximum condition rows named in a list-row summary before "+N more". */
const BLY_CB_MAX_SUMMARY_RULES = 4;

/** Maximum non-content-block option keys named by get-content-block. */
const BLY_CB_MAX_OTHER_KEYS = 40;

/** Maximum valid rule ids named in an unknown-rule error message. */
const BLY_CB_MAX_RULES_IN_ERROR = 40;

/** Priority bounds for hook locations — the customizer UI's ct-number bounds. */
const BLY_CB_MIN_PRIORITY = 1;

const BLY_CB_MAX_PRIORITY = 100;

/** Bound for the paragraphs/headings/cards count fields. */
const BLY_CB_MAX_COUNT = 1000;

/**
 * PHP 8.0-compatible array_is_list() (available natively since 8.1;
 * this codebase's floor is 8.0).
 *
 * @param array<array-key, mixed> $value
 */
function bly_cb_is_list(array $value): bool
{
    return $value === [] || array_keys($value) === range(0, count($value) - 1);
}

// ---------------------------------------------------------------------------
// Gate
// ---------------------------------------------------------------------------

/**
 * Content Blocks availability gate. The feature (including the
 * `ct_content_block` CPT itself) exists only while `Blocksy\Premium` is
 * booted, which the Companion gates on pro code presence AND the
 * `base_pro` feature for the live license plan — so a free plan or an
 * unlicensed Pro zip has no content-block surface, and every ability on
 * this file's surface refuses up front instead of touching rows that
 * would never render.
 */
function bly_cb_gate(): ?WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }
    if (!blocksy_companion_available() || !class_exists('Blocksy\\Plugin')) {
        return bly_companion_inactive_error();
    }

    $premium = \Blocksy\Plugin::instance()->premium;
    if (!$premium instanceof \Blocksy\Premium || !$premium->content_blocks instanceof \Blocksy\ContentBlocks) {
        $plan = bly_companion_plan();
        return bly_error('bly_pro_required', sprintf(
            'Content Blocks are a Blocksy Companion Pro (base_pro) feature and this install does not boot it — plan "%s", pro code %s (companion.plan / companion.pro_code_present / companion.licensed from blocksy-check-setup). The ct_content_block surface does not exist here, so the call is refused.',
            $plan === '' ? 'unknown' : $plan,
            bly_companion_pro_code_present() ? 'present but not entitled' : 'not installed',
        ));
    }

    if (!post_type_exists(BLY_CB_POST_TYPE)) {
        return bly_error('bly_schema_unavailable', sprintf(
            'The %s post type is not registered although the Companion Pro premium layer is booted — this ability ran before init completed. Retry on a normal request.',
            BLY_CB_POST_TYPE,
        ));
    }

    return null;
}

// ---------------------------------------------------------------------------
// Template types + per-type condition filter
// ---------------------------------------------------------------------------

/**
 * The nine template types the Companion's create UI offers
 * (admin-ui.php:316-324).
 *
 * @return list<string>
 */
function bly_cb_template_types(): array
{
    return ['hook', 'popup', '404', 'header', 'footer', 'archive', 'single', 'nothing_found', 'maintenance'];
}

/**
 * The rule-catalog filter each template type's options file requests
 * (options/<type>.php `'filter'` keys; types without an explicit filter
 * — popup, 404, header, footer — use the manager default 'all', same as
 * conditions/api.php does for them).
 */
function bly_cb_filter_for_type(string $template_type): string
{
    return match ($template_type) {
        'hook' => 'content_block_hook',
        'archive', 'nothing_found' => 'archive',
        'single' => 'singular',
        'maintenance' => 'maintenance-mode',
        default => 'all',
    };
}

// ---------------------------------------------------------------------------
// Live rule catalog
// ---------------------------------------------------------------------------

/**
 * The valid condition-rule ids for one template type, resolved LIVE from
 * the Companion's own `ConditionsManager::get_all_rules()` with the
 * type's filter — exactly the catalog the customizer UI fetches — so
 * install-dependent ids (per-CPT singles/archives, per-taxonomy rules,
 * per-role user_role_* ids, WooCommerce rules, custom-filter rules) are
 * accepted precisely when the UI would offer them. The `user_role`
 * catalog entry is a picker whose stored ids are its `sub_ids`
 * (`user_role_<role>`, the only prefix the resolver matches), so the
 * bare parent id is dropped and the sub ids added. Value is
 * `true` for built-in rules and `'custom'` for rules injected through
 * the `blocksy:conditions:rules:custom` filter (their payload shape is
 * third-party-defined). Memoized per filter.
 *
 * `get_editable_roles()` (called by the user-auth section) lives in
 * wp-admin/includes/user.php, which REST/CLI requests do not load — the
 * preload keeps the catalog resolvable in MCP context.
 *
 * @return array<string, true|'custom'>|WP_Error
 */
function bly_cb_rule_catalog(string $template_type): array|WP_Error
{
    static $memo = [];
    /** @var array<string, array<string, true|'custom'>> $memo */
    $filter = bly_cb_filter_for_type($template_type);
    if (isset($memo[$filter])) {
        return $memo[$filter];
    }

    try {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $groups = (new \Blocksy\ConditionsManager())->get_all_rules([
            'filter' => $filter,
            'context' => 'frontend',
        ]);
    } catch (\Throwable $e) {
        error_log('novamira-pro blocksy: condition rule catalog load failed: ' . $e->getMessage());
        return bly_error(
            'bly_schema_unavailable',
            message: 'The Blocksy condition-rule catalog could not be loaded (details in the server error log), so condition validation cannot run. Nothing was written.',
        );
    }

    $catalog = [];
    foreach ($groups as $group) {
        if (!isset($group['rules']) || !is_array($group['rules'])) {
            continue;
        }
        bly_cb_collect_group_rules($catalog, $group['rules']);
    }

    if ($catalog === []) {
        return bly_error(
            'bly_schema_unavailable',
            message: 'The Blocksy condition-rule catalog resolved to zero rules — the Companion schema looks broken. Nothing was written.',
        );
    }

    $memo[$filter] = $catalog;
    return $catalog;
}

/**
 * Fold one catalog group's rules into the id map. A `sub_ids` entry
 * (user_role) contributes its sub ids instead of its own id.
 *
 * @param array<string, true|'custom'> $catalog
 * @param array<array-key, mixed> $rules
 * @param-out array<string, true|'custom'> $catalog
 */
function bly_cb_collect_group_rules(array &$catalog, array $rules): void
{
    /** @var mixed $rule */
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        if (isset($rule['sub_ids']) && is_array($rule['sub_ids'])) {
            /** @var mixed $sub */
            foreach ($rule['sub_ids'] as $sub) {
                /** @var mixed $sub_id */
                $sub_id = is_array($sub) ? $sub['id'] ?? null : null;
                if (!is_string($sub_id) || $sub_id === '') {
                    continue;
                }
                $catalog[$sub_id] = true;
            }
            continue;
        }
        if (!isset($rule['id']) || !is_string($rule['id']) || $rule['id'] === '') {
            continue;
        }
        $catalog[$rule['id']] = isset($rule['is_custom_rule']) && $rule['is_custom_rule'] === true ? 'custom' : true;
    }
}

/**
 * Bounded valid-rule listing for error messages: the catalog's ids,
 * capped with an explicit omission marker.
 *
 * @param array<string, true|'custom'> $catalog
 */
function bly_cb_rule_listing(array $catalog): string
{
    $ids = array_keys($catalog);
    $shown = array_slice($ids, offset: 0, length: BLY_CB_MAX_RULES_IN_ERROR);
    $suffix = count($ids) > BLY_CB_MAX_RULES_IN_ERROR
        ? sprintf(' (+%d more)', count($ids) - BLY_CB_MAX_RULES_IN_ERROR)
        : '';
    return implode(', ', $shown) . $suffix;
}

// ---------------------------------------------------------------------------
// Conditions validation
// ---------------------------------------------------------------------------

/**
 * Validate a full conditions VALUE against the live grammar for one
 * template type, returning the normalized DESCRIPTOR to persist:
 * `{relation: OR|AND, conditions: [...]}` — the exact shape the
 * customizer UI saves. ConditionsManager.js:54-59 wraps even a plain
 * stored list into `{relation: 'OR', conditions}` and every onChange
 * saves that descriptor, so all UI-touched blocks store it; the PHP
 * matcher's normalize_rules() (conditions-manager.php:386-399) accepts
 * both forms and treats a bare list as relation OR.
 *
 * Accepted input, mirroring that:
 *  - a JSON array of rows (the legacy/simple form) — wrapped into the
 *    OR descriptor exactly like the UI does before saving;
 *  - a full descriptor object, including nested relation groups
 *    (Advanced Mode): group rows `{relation: AND|OR, conditions}` are
 *    validated recursively, leaf rows `{type, rule, payload?}` against
 *    the live rule catalog.
 *
 * Depth is capped at BLY_CB_MAX_CONDITION_DEPTH and the total LEAF
 * rule count at BLY_CB_MAX_CONDITIONS. Strict on unknown keys and
 * malformed payloads throughout — hostile or hallucinated shapes never
 * reach the meta row.
 *
 * @return array{relation: string, conditions: list<array<string, mixed>>}|WP_Error
 */
function bly_cb_validate_conditions(mixed $raw, string $template_type): array|WP_Error
{
    if ($raw instanceof \stdClass) {
        $raw = (array) $raw;
    }
    if (!is_array($raw)) {
        return bly_error(
            'bly_invalid_input',
            message: '`conditions` must be a JSON array of condition rows, or a {relation, conditions} descriptor (the shape the customizer saves). An empty array is valid and hides the block everywhere (except maintenance/nothing_found blocks, which then default to `everywhere`).',
        );
    }

    // The UI wrap (ConditionsManager.js:54-59), applied server-side so
    // both input forms persist identically.
    $descriptor = bly_cb_is_list($raw) ? ['relation' => 'OR', 'conditions' => $raw] : $raw;

    $catalog = bly_cb_rule_catalog($template_type);
    if ($catalog instanceof WP_Error) {
        return $catalog;
    }

    $validated = bly_cb_validate_condition_group(
        $descriptor,
        path: 'conditions',
        depth: 1,
        template_type: $template_type,
        catalog: $catalog,
    );
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $leaves = count(bly_cb_leaf_rules($validated['conditions']));
    if ($leaves > BLY_CB_MAX_CONDITIONS) {
        return bly_error('bly_invalid_input', sprintf(
            '`conditions` holds %d leaf rules; at most %d are accepted per content block.',
            $leaves,
            BLY_CB_MAX_CONDITIONS,
        ));
    }
    return $validated;
}

/**
 * Validate one relation group `{relation, conditions}` recursively.
 * The ROOT group may be empty (= no conditions); a NESTED group may
 * not — the UI dissolves a group when its last row is removed
 * (ConditionsWithRelation.js:74-83), so an empty nested group is a
 * shape the UI can neither produce nor round-trip.
 *
 * @param array<string, true|'custom'> $catalog
 * @return array{relation: string, conditions: list<array<string, mixed>>}|WP_Error
 */
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function bly_cb_validate_condition_group(
    mixed $group,
    string $path,
    int $depth,
    string $template_type,
    array $catalog,
): array|WP_Error {
    if ($group instanceof \stdClass) {
        $group = (array) $group;
    }
    if (!is_array($group)) {
        return bly_error('bly_invalid_input', sprintf('%s must be a {relation, conditions} group object.', $path));
    }
    if ($depth > BLY_CB_MAX_CONDITION_DEPTH) {
        return bly_error('bly_invalid_input', sprintf(
            '%s nests relation groups deeper than %d levels, this surface\'s anti-abuse resource bound (the customizer itself imposes no depth limit).',
            $path,
            BLY_CB_MAX_CONDITION_DEPTH,
        ));
    }

    $unknown = array_diff(array_keys($group), ['relation', 'conditions']);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s has unknown key(s) [%s]; a relation group accepts only `relation` and `conditions`.',
            $path,
            implode(', ', array_map(static fn(int|string $k): string => bly_ext_clean_text(
                (string) $k,
                BLY_EXT_MAX_NAME_BYTES,
            ), $unknown)),
        ));
    }

    /** @var mixed $relation */
    $relation = $group['relation'] ?? null;
    if ($relation !== 'OR' && $relation !== 'AND') {
        return bly_error('bly_invalid_input', sprintf('%s.relation must be "OR" or "AND".', $path));
    }

    /** @var mixed $rows */
    $rows = $group['conditions'] ?? null;
    if (!is_array($rows) || !bly_cb_is_list($rows)) {
        return bly_error('bly_invalid_input', sprintf('%s.conditions must be a JSON array of rows.', $path));
    }
    if ($depth > 1 && $rows === []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s.conditions is empty — the UI removes a relation group with its last rule; drop the group instead.',
            $path,
        ));
    }

    $normalized = [];
    /** @var mixed $row */
    foreach ($rows as $i => $row) {
        $row_path = sprintf('%s[%d]', $path, (int) $i);
        if ($row instanceof \stdClass) {
            $row = (array) $row;
        }
        if (is_array($row) && (isset($row['relation']) || isset($row['conditions']))) {
            if (isset($row['type']) || isset($row['rule']) || isset($row['payload'])) {
                return bly_error('bly_invalid_input', sprintf(
                    '%s mixes group keys (relation/conditions) with rule keys (type/rule/payload) — a row is either a relation group or a single rule.',
                    $row_path,
                ));
            }
            $sub = bly_cb_validate_condition_group($row, $row_path, $depth + 1, $template_type, $catalog);
            if ($sub instanceof WP_Error) {
                return $sub;
            }
            $normalized[] = $sub;
            continue;
        }
        $leaf = bly_cb_validate_condition_row($row, $row_path, $template_type, $catalog);
        if ($leaf instanceof WP_Error) {
            return $leaf;
        }
        $normalized[] = $leaf;
    }
    return ['relation' => $relation, 'conditions' => $normalized];
}

/**
 * Validate one condition row.
 *
 * @param array<string, true|'custom'> $catalog
 * @return array{type: string, rule: string, payload: array<string, mixed>|\stdClass}|WP_Error
 */
function bly_cb_validate_condition_row(mixed $row, string $path, string $template_type, array $catalog): array|WP_Error
{
    if (!is_array($row)) {
        return bly_error('bly_invalid_input', sprintf(
            '%s must be an object with `type`, `rule` and optional `payload`.',
            $path,
        ));
    }
    $unknown = array_diff(array_keys($row), ['type', 'rule', 'payload']);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s has unknown key(s) [%s]; only `type`, `rule` and `payload` are accepted.',
            $path,
            implode(', ', array_map(static fn(int|string $k): string => bly_ext_clean_text(
                (string) $k,
                BLY_EXT_MAX_NAME_BYTES,
            ), $unknown)),
        ));
    }

    /** @var mixed $type */
    $type = $row['type'] ?? null;
    if ($type !== 'include' && $type !== 'exclude') {
        return bly_error('bly_invalid_input', sprintf('%s.type must be "include" or "exclude".', $path));
    }

    /** @var mixed $rule */
    $rule = $row['rule'] ?? null;
    if (!is_string($rule) || $rule === '' || strlen($rule) > 128) {
        return bly_error('bly_invalid_input', sprintf(
            '%s.rule must be a non-empty string of at most 128 bytes.',
            $path,
        ));
    }
    if (!isset($catalog[$rule])) {
        return bly_error('bly_invalid_input', sprintf(
            '%s.rule "%s" is not a valid display-condition rule for template type "%s" on this install. Valid rules: %s.',
            $path,
            bly_ext_clean_text($rule, BLY_EXT_MAX_NAME_BYTES),
            $template_type,
            bly_cb_rule_listing($catalog),
        ));
    }

    /** @var mixed $payload */
    $payload = $row['payload'] ?? [];
    if ($payload instanceof \stdClass) {
        $payload = (array) $payload;
    }
    if (!is_array($payload)) {
        return bly_error('bly_invalid_input', sprintf('%s.payload must be an object when present.', $path));
    }

    $clean_payload = $catalog[$rule] === 'custom'
        ? bly_cb_validate_custom_payload($payload, $path)
        : bly_cb_validate_rule_payload($rule, $payload, $path);
    if ($clean_payload instanceof WP_Error) {
        return $clean_payload;
    }

    return ['type' => $type, 'rule' => $rule, 'payload' => $clean_payload];
}

/**
 * Per-rule payload validation, mirroring exactly what
 * ConditionsRulesResolver reads for each rule (rules-resolver.php).
 * Rules the resolver matches without a payload accept only an empty
 * payload — silently persisting junk keys would fabricate meaning the
 * matcher never consults.
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, mixed>|WP_Error
 */
function bly_cb_validate_rule_payload(string $rule, array $payload, string $path): array|WP_Error
{
    // post-id rules: resolver compares intval(payload.post_id).
    if (in_array($rule, ['post_ids', 'page_ids', 'product_ids', 'custom_post_type_ids'], strict: true)) {
        return bly_cb_payload_int_key($payload, key: 'post_id', rule: $rule, path: $path);
    }

    // taxonomy-id rules: resolver compares payload.taxonomy_id (term_id).
    $tax_rules = [
        'taxonomy_ids',
        'product_taxonomy_ids',
        'post_with_taxonomy_ids',
        'product_with_taxonomy_ids',
        'card_post_with_taxonomy_ids',
        'card_product_with_taxonomy_ids',
    ];
    if (in_array($rule, $tax_rules, strict: true)) {
        return bly_cb_payload_int_key($payload, key: 'taxonomy_id', rule: $rule, path: $path);
    }

    if ($rule === 'user_post_author_id') {
        return bly_cb_validate_author_payload($payload, $path, $rule);
    }
    if ($rule === 'author') {
        // Optional payload.user_id narrows to one author archive.
        if ($payload === []) {
            return [];
        }
        return bly_cb_validate_author_payload($payload, $path, $rule);
    }

    if ($rule === 'current_language') {
        return bly_cb_payload_string_key(
            $payload,
            key: 'language',
            rule: $rule,
            path: $path,
            max_bytes: BLY_CB_MAX_SHORT_PAYLOAD_BYTES,
        );
    }
    if ($rule === 'request_referer') {
        return bly_cb_payload_string_key(
            $payload,
            key: 'referer',
            rule: $rule,
            path: $path,
            max_bytes: BLY_CB_MAX_PAYLOAD_TEXT_BYTES,
        );
    }
    if ($rule === 'request_url') {
        return bly_cb_payload_string_key(
            $payload,
            key: 'url',
            rule: $rule,
            path: $path,
            max_bytes: BLY_CB_MAX_PAYLOAD_TEXT_BYTES,
        );
    }
    if ($rule === 'request_cookie') {
        return bly_cb_payload_string_key(
            $payload,
            key: 'cookie',
            rule: $rule,
            path: $path,
            max_bytes: BLY_CB_MAX_COOKIE_BYTES,
        );
    }

    if ($rule === 'start_end_date') {
        return bly_cb_validate_date_range_payload($payload, $path);
    }
    if ($rule === 'schedule_date') {
        return bly_cb_validate_schedule_payload($payload, $path);
    }

    // Everything else (everywhere, singulars, archives, 404, search, blog,
    // front_page, woo_shop, single_*, all_*, post_categories, post_tags,
    // user_logged_in/out, user_role_*, post_type_*_..., bbpress_profile)
    // is payload-free.
    if ($payload !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "%s" takes no payload; remove the payload keys [%s].',
            $path,
            $rule,
            implode(', ', array_map(static fn(int|string $k): string => bly_ext_clean_text(
                (string) $k,
                BLY_EXT_MAX_NAME_BYTES,
            ), array_keys($payload))),
        ));
    }
    return [];
}

/**
 * Payload with exactly one required positive-integer key (post_id /
 * taxonomy_id). Numeric strings are accepted and normalized to int —
 * the resolver intval()s either way.
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, int>|WP_Error
 */
function bly_cb_payload_int_key(array $payload, string $key, string $rule, string $path): array|WP_Error
{
    $unknown = array_diff(array_keys($payload), [$key]);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "%s" accepts only the payload key `%s`.',
            $path,
            $rule,
            $key,
        ));
    }
    $int = bly_cb_coerce_int($payload[$key] ?? null);
    if ($int === null || $int < 1) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "%s" requires payload.%s as a positive integer id.',
            $path,
            $rule,
            $key,
        ));
    }
    return [$key => $int];
}

/**
 * Payload with exactly one required non-empty string key, byte-capped.
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, string>|WP_Error
 */
function bly_cb_payload_string_key(
    array $payload,
    string $key,
    string $rule,
    string $path,
    int $max_bytes,
): array|WP_Error {
    $unknown = array_diff(array_keys($payload), [$key]);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "%s" accepts only the payload key `%s`.',
            $path,
            $rule,
            $key,
        ));
    }
    /** @var mixed $value */
    $value = $payload[$key] ?? null;
    if (!is_string($value) || trim($value) === '' || strlen($value) > $max_bytes) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "%s" requires payload.%s as a non-empty string of at most %d bytes.',
            $path,
            $rule,
            $key,
            $max_bytes,
        ));
    }
    return [$key => $value];
}

/**
 * user_id payload for the author rules: a positive integer, or the
 * literal "current_user" where the resolver supports it
 * (user_post_author_id only).
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, int|string>|WP_Error
 */
function bly_cb_validate_author_payload(array $payload, string $path, string $rule): array|WP_Error
{
    $unknown = array_diff(array_keys($payload), ['user_id']);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf('%s: this rule accepts only the payload key `user_id`.', $path));
    }
    /** @var mixed $value */
    $value = $payload['user_id'] ?? null;
    $allows_current_user = $rule === 'user_post_author_id';
    if ($allows_current_user && $value === 'current_user') {
        return ['user_id' => 'current_user'];
    }
    $int = bly_cb_coerce_int($value);
    if ($int === null || $int < 1) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: payload.user_id must be a positive integer user id%s.',
            $path,
            $allows_current_user ? ' or the literal "current_user"' : '',
        ));
    }
    return ['user_id' => $int];
}

/**
 * Coerce an int or a purely-numeric string to int; null for anything
 * else. The customizer stores its ct-number values as strings, and the
 * Companion's resolvers intval() both — this mirrors that tolerance.
 */
function bly_cb_coerce_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
        return (int) $value;
    }
    return null;
}

/**
 * start_end_date payload: `start` and `end`, both real calendar
 * date/times (the resolver constructs `new DateTime($value,
 * wp_timezone())`; rollover inputs like 2026-02-30 parse with a
 * warning and are rejected here), and `end` must not be earlier than
 * `start` — the same rule the UI enforces (ExpireCondition.js:106-124
 * refuses an expiration date before the start date; equal is allowed).
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, string>|WP_Error
 */
function bly_cb_validate_date_range_payload(array $payload, string $path): array|WP_Error
{
    $unknown = array_diff(array_keys($payload), ['start', 'end']);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "start_end_date" accepts only the payload keys `start` and `end`.',
            $path,
        ));
    }
    $out = [];
    $parsed = [];
    foreach (['start', 'end'] as $key) {
        /** @var mixed $value */
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > BLY_CB_MAX_SHORT_PAYLOAD_BYTES) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: rule "start_end_date" requires payload.%s as a date/time string of at most %d bytes (e.g. "2026-08-14 09:00").',
                $path,
                $key,
                BLY_CB_MAX_SHORT_PAYLOAD_BYTES,
            ));
        }
        $moment = bly_cb_parse_datetime($value);
        if ($moment === null) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: payload.%s "%s" is not a real, parseable calendar date/time.',
                $path,
                $key,
                bly_ext_clean_text($value, BLY_EXT_MAX_NAME_BYTES),
            ));
        }
        $out[$key] = $value;
        $parsed[$key] = $moment;
    }
    if ($parsed['end'] < $parsed['start']) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: payload.end is earlier than payload.start — the expiration cannot precede the start (the customizer refuses this too).',
            $path,
        ));
    }
    return $out;
}

/**
 * Strict date/time parse: null for unparseable input AND for rollover
 * inputs (2026-02-30, 25:70) that DateTime "accepts" with a warning —
 * DateTime::getLastErrors() is the only way to see those.
 */
function bly_cb_parse_datetime(string $value): ?\DateTime
{
    try {
        $parsed = new \DateTime($value);
    } catch (\Throwable) {
        return null;
    }
    $errors = \DateTime::getLastErrors();
    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }
    return $parsed;
}

/**
 * schedule_date payload: optional `days` map (monday..sunday => bool)
 * and optional `time_start` + `time_end` pair in H:MM/HH:MM (the
 * resolver explodes on ":" and calls setTime()).
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, mixed>|WP_Error
 */
function bly_cb_validate_schedule_payload(array $payload, string $path): array|WP_Error
{
    $unknown = array_diff(array_keys($payload), ['days', 'time_start', 'time_end']);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '%s: rule "schedule_date" accepts only the payload keys `days`, `time_start` and `time_end`.',
            $path,
        ));
    }

    $out = [];
    if (array_key_exists('days', $payload)) {
        /** @var mixed $days */
        $days = $payload['days'];
        if ($days instanceof \stdClass) {
            $days = (array) $days;
        }
        $valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        if (!is_array($days)) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: payload.days must be an object mapping day names (monday..sunday) to booleans.',
                $path,
            ));
        }
        $clean_days = [];
        /** @var mixed $flag */
        foreach ($days as $day => $flag) {
            if (!is_string($day) || !in_array($day, $valid_days, strict: true) || !is_bool($flag)) {
                return bly_error('bly_invalid_input', sprintf(
                    '%s: payload.days must map only monday..sunday to booleans.',
                    $path,
                ));
            }
            $clean_days[$day] = $flag;
        }
        $out['days'] = $clean_days;
    }

    // The UI's ScheduleDate control saves time_start and time_end
    // INDEPENDENTLY (ScheduleDate.js:215-252), and the resolver only
    // applies the time window when both are set (rules-resolver.php:
    // 186-208) — a lone key is a legitimate customizer-authored state,
    // so each is validated on its own when present.
    foreach (['time_start', 'time_end'] as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }
        /** @var mixed $value */
        $value = $payload[$key];
        if (!is_string($value) || preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value) !== 1) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: payload.%s must be a 24h time as H:MM or HH:MM (00:00-23:59).',
                $path,
                $key,
            ));
        }
        $out[$key] = $value;
    }

    return $out;
}

/**
 * Payload for rules injected through `blocksy:conditions:rules:custom`:
 * the shape is third-party-defined, so scalar values are accepted but
 * bounded (at most 10 keys, key names <= 64 bytes, strings <= 500
 * bytes) — enough for the filter's `has_text_field`/`choices` contract
 * without opening an unbounded persistence channel.
 *
 * @param array<array-key, mixed> $payload
 * @return array<string, mixed>|WP_Error
 */
function bly_cb_validate_custom_payload(array $payload, string $path): array|WP_Error
{
    if (count($payload) > 10) {
        return bly_error('bly_invalid_input', sprintf('%s: a custom-rule payload accepts at most 10 keys.', $path));
    }
    $out = [];
    /** @var mixed $value */
    foreach ($payload as $key => $value) {
        if (!is_string($key) || $key === '' || strlen($key) > 64) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: custom-rule payload keys must be non-empty strings of at most 64 bytes.',
                $path,
            ));
        }
        if (!is_scalar($value)) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: custom-rule payload values must be scalar (string/number/boolean).',
                $path,
            ));
        }
        if (is_string($value) && strlen($value) > BLY_CB_MAX_PAYLOAD_TEXT_BYTES) {
            return bly_error('bly_invalid_input', sprintf(
                '%s: custom-rule payload string values are capped at %d bytes.',
                $path,
                BLY_CB_MAX_PAYLOAD_TEXT_BYTES,
            ));
        }
        $out[$key] = $value;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Position validation (template_type = hook)
// ---------------------------------------------------------------------------

/**
 * The valid `location` ids: every hook from the Companion's own
 * `HooksManager::get_all_hooks()` plus the `custom_hook` escape hatch —
 * exactly the choice list the customizer builds (options/hook.php).
 * Memoized per request.
 *
 * @return array<string, true>|WP_Error
 */
function bly_cb_location_catalog(): array|WP_Error
{
    static $memo = null;
    /** @var array<string, true>|null $memo */
    if (is_array($memo)) {
        return $memo;
    }

    try {
        $hooks = (new \Blocksy\HooksManager())->get_all_hooks();
    } catch (\Throwable $e) {
        error_log('novamira-pro blocksy: hooks catalog load failed: ' . $e->getMessage());
        return bly_error(
            'bly_schema_unavailable',
            message: 'The Blocksy hooks catalog could not be loaded (details in the server error log), so hook locations cannot be validated. Nothing was written.',
        );
    }

    $catalog = ['custom_hook' => true];
    /** @var mixed $hook */
    foreach ($hooks as $hook) {
        /** @var mixed $hook_id */
        $hook_id = is_array($hook) ? $hook['hook'] ?? null : null;
        if (!is_string($hook_id) || $hook_id === '') {
            continue;
        }
        $catalog[$hook_id] = true;
    }
    $memo = $catalog;
    return $catalog;
}

/**
 * The position keys a hook block stores in its options meta, with the
 * defaults display_hooks() itself falls back to.
 *
 * @return array{location: string, priority: int, custom_location: string, paragraphs_count: int, headings_count: int, cards_count: int, repeat_for_every_card: string}
 */
function bly_cb_position_defaults(): array
{
    return [
        'location' => '',
        'priority' => 10,
        'custom_location' => '',
        'paragraphs_count' => 5,
        'headings_count' => 5,
        'cards_count' => 3,
        'repeat_for_every_card' => 'no',
    ];
}

/**
 * Validate a (partial) position object. Returns ONLY the supplied
 * scalar keys, validated and normalized (`set`), plus the normalized
 * `additional_locations` list when the input provided one (null
 * otherwise — the caller keeps the stored list untouched). The
 * `$effective_base` (the block's current stored/effective position) is
 * used purely for cross-field validation of the MERGED view
 * (custom_hook needs a custom_location) — it is never persisted, so a
 * partial edit cannot materialize absent keys or re-type stored
 * values.
 *
 * @param array<string, int|string> $effective_base
 * @return array{set: array<string, int|string>, additional_locations: list<array<string, int|string>>|null}|WP_Error
 */
function bly_cb_validate_position(mixed $raw, array $effective_base): array|WP_Error
{
    if ($raw instanceof \stdClass) {
        $raw = (array) $raw;
    }
    if (!is_array($raw)) {
        return bly_error('bly_invalid_input', message: '`position` must be an object.');
    }

    $known = [
        'location',
        'priority',
        'custom_location',
        'paragraphs_count',
        'headings_count',
        'cards_count',
        'repeat_for_every_card',
        'additional_locations',
    ];
    $unknown = array_diff(array_keys($raw), $known);
    if ($unknown !== []) {
        return bly_error('bly_invalid_input', sprintf(
            '`position` has unknown key(s) [%s]; accepted keys: %s.',
            implode(', ', array_map(static fn(int|string $k): string => bly_ext_clean_text(
                (string) $k,
                BLY_EXT_MAX_NAME_BYTES,
            ), $unknown)),
            implode(', ', $known),
        ));
    }
    if ($raw === []) {
        return bly_error('bly_invalid_input', message: '`position` must provide at least one key to set.');
    }

    $set = bly_cb_validate_position_set($raw);
    if ($set instanceof WP_Error) {
        return $set;
    }

    $effective = array_merge($effective_base, $set);
    $effective_location = $effective['location'] ?? '';
    $effective_custom = $effective['custom_location'] ?? '';
    if ($effective_location === 'custom_hook' && (!is_string($effective_custom) || trim($effective_custom) === '')) {
        return bly_error(
            'bly_invalid_input',
            message: '`position.location` "custom_hook" requires a non-empty `position.custom_location` naming the WordPress action to render on (set it in the same call or have it already stored).',
        );
    }

    $additional = null;
    if (array_key_exists('additional_locations', $raw)) {
        $additional = bly_cb_validate_additional_locations($raw['additional_locations']);
        if ($additional instanceof WP_Error) {
            return $additional;
        }
    }

    return ['set' => $set, 'additional_locations' => $additional];
}

/**
 * Per-key validation of the seven scalar position keys — returns ONLY
 * the keys the input supplied, each normalized (repeat_for_every_card
 * bool -> yes/no). No defaults are injected and no absent key is
 * materialized here.
 *
 * @param array<array-key, mixed> $raw
 * @return array<string, int|string>|WP_Error
 */
// @mago-expect lint:cyclomatic-complexity
function bly_cb_validate_position_set(array $raw): array|WP_Error
{
    $set = [];

    if (array_key_exists('location', $raw)) {
        $catalog = bly_cb_location_catalog();
        if ($catalog instanceof WP_Error) {
            return $catalog;
        }
        /** @var mixed $location */
        $location = $raw['location'];
        if (!is_string($location) || !isset($catalog[$location]) && $location !== '') {
            return bly_error('bly_invalid_input', sprintf(
                '`position.location` "%s" is not a known Blocksy hook. Use one of the HooksManager ids (e.g. wp_body_open, blocksy:content:before, blocksy:footer:before), "custom_hook" with `custom_location`, or "" for none.',
                is_string($location) ? bly_ext_clean_text($location, BLY_EXT_MAX_NAME_BYTES) : gettype($location),
            ));
        }
        $set['location'] = $location;
    }

    if (array_key_exists('priority', $raw)) {
        /** @var mixed $priority */
        $priority = $raw['priority'];
        if (!is_int($priority) || $priority < BLY_CB_MIN_PRIORITY || $priority > BLY_CB_MAX_PRIORITY) {
            return bly_error('bly_invalid_input', sprintf(
                '`position.priority` must be an integer between %d and %d (the customizer bounds).',
                BLY_CB_MIN_PRIORITY,
                BLY_CB_MAX_PRIORITY,
            ));
        }
        $set['priority'] = $priority;
    }

    if (array_key_exists('custom_location', $raw)) {
        /** @var mixed $custom */
        $custom = $raw['custom_location'];
        if (
            !is_string($custom)
            || strlen($custom) > 128
            || $custom !== '' && preg_match('/^[A-Za-z0-9_:\\-\\.\\/]+$/', $custom) !== 1
        ) {
            return bly_error(
                'bly_invalid_input',
                message: '`position.custom_location` must be a WordPress action name (letters, digits, _ : - . /), at most 128 bytes.',
            );
        }
        $set['custom_location'] = $custom;
    }

    foreach (['paragraphs_count', 'headings_count', 'cards_count'] as $count_key) {
        if (!array_key_exists($count_key, $raw)) {
            continue;
        }
        /** @var mixed $count */
        $count = $raw[$count_key];
        if (!is_int($count) || $count < 1 || $count > BLY_CB_MAX_COUNT) {
            return bly_error('bly_invalid_input', sprintf(
                '`position.%s` must be an integer between 1 and %d.',
                $count_key,
                BLY_CB_MAX_COUNT,
            ));
        }
        $set[$count_key] = $count;
    }

    if (array_key_exists('repeat_for_every_card', $raw)) {
        /** @var mixed $repeat */
        $repeat = $raw['repeat_for_every_card'];
        if (!is_bool($repeat)) {
            return bly_error('bly_invalid_input', message: '`position.repeat_for_every_card` must be a boolean.');
        }
        $set['repeat_for_every_card'] = $repeat ? 'yes' : 'no';
    }

    return $set;
}

/**
 * Validate the additional_locations list: at most 10 rows, each with a
 * required `location` (+ `custom_location` when custom_hook) and
 * optional `priority` / counts / repeat, normalized to the FULL key set
 * — display_hooks() reads every key from every row unguarded.
 *
 * @return list<array<string, int|string>>|WP_Error
 */
function bly_cb_validate_additional_locations(mixed $raw): array|WP_Error
{
    if (!is_array($raw) || !bly_cb_is_list($raw)) {
        return bly_error(
            'bly_invalid_input',
            message: '`position.additional_locations` must be a JSON array of {location, priority?, ...} rows (empty array clears it).',
        );
    }
    if (count($raw) > BLY_CB_MAX_ADDITIONAL_LOCATIONS) {
        return bly_error('bly_invalid_input', sprintf(
            '`position.additional_locations` holds %d rows; at most %d are accepted.',
            count($raw),
            BLY_CB_MAX_ADDITIONAL_LOCATIONS,
        ));
    }

    $out = [];
    /** @var mixed $row */
    foreach ($raw as $i => $row) {
        if ($row instanceof \stdClass) {
            $row = (array) $row;
        }
        if (!is_array($row) || !array_key_exists('location', $row)) {
            return bly_error('bly_invalid_input', sprintf(
                'position.additional_locations[%d] must be an object with at least a `location` key.',
                (int) $i,
            ));
        }
        $known = [
            'location',
            'priority',
            'custom_location',
            'paragraphs_count',
            'headings_count',
            'cards_count',
            'repeat_for_every_card',
        ];
        $unknown = array_diff(array_keys($row), $known);
        if ($unknown !== []) {
            return bly_error('bly_invalid_input', sprintf(
                'position.additional_locations[%d] has unknown key(s) [%s]; accepted keys: %s.',
                (int) $i,
                implode(', ', array_map(static fn(int|string $k): string => bly_ext_clean_text(
                    (string) $k,
                    BLY_EXT_MAX_NAME_BYTES,
                ), $unknown)),
                implode(', ', $known),
            ));
        }
        $row_set = bly_cb_validate_position_set($row);
        if ($row_set instanceof WP_Error) {
            return bly_error('bly_invalid_input', sprintf(
                'position.additional_locations[%d]: %s',
                (int) $i,
                $row_set->get_error_message(),
            ));
        }
        // Rows here are authored whole by this surface, so they are
        // normalized to the FULL key set — display_hooks() reads every
        // key from every additional row unguarded.
        $full_row = array_merge(bly_cb_position_defaults(), $row_set);
        if ($full_row['location'] === '') {
            return bly_error('bly_invalid_input', sprintf(
                'position.additional_locations[%d].location must not be empty — omit the row instead.',
                (int) $i,
            ));
        }
        if ($full_row['location'] === 'custom_hook' && trim($full_row['custom_location']) === '') {
            return bly_error('bly_invalid_input', sprintf(
                'position.additional_locations[%d]: "custom_hook" requires a non-empty custom_location in the same row.',
                (int) $i,
            ));
        }
        $out[] = $full_row;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Post + options-meta access
// ---------------------------------------------------------------------------

/**
 * Resolve a user-supplied id to a ct_content_block post, or WP_Error.
 * Trashed blocks resolve too — get/list/delete must see them.
 */
function bly_cb_get_post(mixed $input_id): \WP_Post|WP_Error
{
    if (!is_int($input_id) || $input_id < 1) {
        return bly_error(
            'bly_invalid_input',
            message: '`id` is required and must be a positive integer content-block post id.',
        );
    }
    /** @var \WP_Post|null $post */
    $post = get_post($input_id);
    if (!$post instanceof \WP_Post || $post->post_status === 'auto-draft') {
        return bly_error('bly_not_found', sprintf(
            'No content block with id %d exists. Use blocksy-list-content-blocks to discover ids.',
            $input_id,
        ));
    }
    if ($post->post_type !== BLY_CB_POST_TYPE) {
        return bly_error('bly_not_found', sprintf(
            'Post %d exists but is of type "%s", not %s — it is not a content block.',
            $input_id,
            bly_ext_clean_text($post->post_type, BLY_EXT_MAX_NAME_BYTES),
            BLY_CB_POST_TYPE,
        ));
    }
    return $post;
}

/**
 * The block's stored options array, read fresh (meta cache dropped
 * first — a request that just waited on the row lock must not act on
 * its bootstrap-primed cache). Missing or malformed meta reads as [],
 * matching blocksy_get_post_options()' own normalization; that helper
 * itself is NOT used because it memoizes per request and would serve
 * pre-write values after our own writes.
 *
 * @return array<array-key, mixed>
 */
function bly_cb_read_atts(int $post_id): array
{
    wp_cache_delete($post_id, group: 'post_meta');
    /** @var array<array-key, mixed>|false $values */
    $values = get_post_meta($post_id, BLY_CB_META_OPTIONS);
    if (!is_array($values) || !isset($values[0]) || !is_array($values[0])) {
        return [];
    }
    return $values[0];
}

/**
 * The raw serialized bytes of the FIRST (lowest meta_id) options-meta
 * row, straight from the database — the verified-persist read.
 * `blocksy_get_post_options()` consumes exactly this row
 * (`get_post_meta($id, $key)` → `$values[0]`).
 */
function bly_cb_raw_atts_bytes(int $post_id): string|false
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
        $post_id,
        BLY_CB_META_OPTIONS,
    );
    /** @var mixed $raw */
    $raw = is_string($sql) ? $wpdb->get_var($sql) : null;
    return is_string($raw) ? $raw : false;
}

/**
 * Locked read-modify-write of one block's options meta. `$mutate`
 * receives the fresh stored atts and returns the new atts (or WP_Error
 * to abort with nothing written). The write is ONE `update_post_meta()`
 * of the whole array — atomic per block by construction — wp_slash'd so
 * quoted/backslashed payload strings survive core's unslashing, and
 * verified by re-reading the raw row bytes: on a verify miss nothing is
 * retried or rolled back (the single UPDATE either stuck or it did
 * not), the error says the row state honestly.
 *
 * Serialized under the same advisory-lock primitive as the theme_mods
 * writes, scoped to this block's meta row, so two concurrent
 * condition/position edits cannot interleave their read-modify-write
 * and silently drop each other's keys.
 *
 * The same meta key (`blocksy_post_meta_options`) holds the per-post
 * Blocksy options of ordinary posts/pages/CPTs, so the per-page options
 * write (page-options-helpers.php) goes through THIS function too — same
 * lock name for the same row, same persist discipline; `$subject` only
 * names the row in error messages ("content block 12" vs "post 12").
 *
 * @param callable(array<array-key, mixed>): (array<array-key, mixed>|WP_Error) $mutate
 * @return array{atts: array<array-key, mixed>, changed: bool}|WP_Error
 */
function bly_cb_locked_atts_edit(int $post_id, callable $mutate, string $subject = 'content block'): array|WP_Error
{
    return bly_with_option_row_lock(BLY_CB_META_OPTIONS . ':' . $post_id, static function (callable $lock_held) use (
        $post_id,
        $mutate,
        $subject,
    ): array|WP_Error {
        $prior = bly_cb_read_atts($post_id);
        if (!$lock_held()) {
            return bly_error('bly_lock_lost', sprintf(
                'The write lock for %s %d was lost while reading its options meta (database reconnect). Nothing was changed — retry.',
                $subject,
                $post_id,
            ));
        }

        $next = $mutate($prior);
        if ($next instanceof WP_Error) {
            return $next;
        }
        if ($next === $prior) {
            return ['atts' => $prior, 'changed' => false];
        }

        $expected = serialize($next);
        if (strlen($expected) > BLY_MAX_WRITE_BYTES) {
            return bly_error('bly_invalid_input', sprintf(
                'The resulting options meta would serialize to %d bytes, above the %d-byte write cap. Nothing was written.',
                strlen($expected),
                BLY_MAX_WRITE_BYTES,
            ));
        }

        if (!$lock_held()) {
            return bly_error('bly_lock_lost', sprintf(
                'The write lock for %s %d was lost before persisting (database reconnect). Nothing was written — retry.',
                $subject,
                $post_id,
            ));
        }
        update_post_meta($post_id, BLY_CB_META_OPTIONS, wp_slash($next));

        wp_cache_delete($post_id, group: 'post_meta');
        $raw = bly_cb_raw_atts_bytes($post_id);
        if (!$lock_held()) {
            return bly_error('bly_lock_lost', sprintf(
                'The database connection changed while persisting or verifying the options meta of %s %d, so the write may have run without the advisory lock and its outcome could not be verified. Re-read it (blocksy-get-content-block / blocksy-get-page-options) and re-issue the edit if needed — the edit is idempotent.',
                $subject,
                $post_id,
            ));
        }
        if ($raw !== $expected) {
            return bly_error('bly_write_failed', sprintf(
                'The options meta of %s %d does not hold the intended bytes after the write (a database failure or a meta filter blocked it). The single meta row was the only write attempted; re-read it to see its current state. %s',
                $subject,
                $post_id,
                $raw === false
                    ? 'The row is currently absent.'
                    : sprintf('The row currently holds %d bytes.', strlen($raw)),
            ));
        }

        return ['atts' => $next, 'changed' => true];
    });
}

// ---------------------------------------------------------------------------
// Read-model helpers (summaries, resolved fields)
// ---------------------------------------------------------------------------

/**
 * The stored conditions of a block presented in DESCRIPTOR form
 * `{relation, conditions}` — the shape the customizer UI saves and the
 * matcher's normalize_rules() produces. A legacy flat list (a block
 * the UI never touched) is wrapped under relation OR exactly like
 * normalize_rules() does; STORAGE IS NOT REWRITTEN by reads. Non-array
 * rows (corrupted meta) are dropped; nested group rows are returned
 * verbatim.
 *
 * @param array<array-key, mixed> $atts
 * @return array{relation: string, conditions: list<array<array-key, mixed>>}
 */
function bly_cb_stored_condition_descriptor(array $atts): array
{
    /** @var mixed $stored */
    $stored = $atts['conditions'] ?? [];
    if (!is_array($stored)) {
        return ['relation' => 'OR', 'conditions' => []];
    }

    $relation = 'OR';
    $rows = $stored;
    if (!bly_cb_is_list($stored)) {
        $relation = ($stored['relation'] ?? null) === 'AND' ? 'AND' : 'OR';
        /** @var mixed $inner */
        $inner = $stored['conditions'] ?? [];
        $rows = is_array($inner) ? $inner : [];
    }

    $clean = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $clean[] = $row;
    }
    return ['relation' => $relation, 'conditions' => $clean];
}

/**
 * Flatten a descriptor's rows to their LEAF rules (nested relation
 * groups contribute their leaves). Bounded a little beyond the
 * validation depth cap so corrupted deeper trees terminate instead of
 * recursing; rows past the bound are dropped.
 *
 * @param array<array-key, mixed> $rows
 * @return list<array<array-key, mixed>>
 */
function bly_cb_leaf_rules(array $rows, int $depth = 1): array
{
    if ($depth > (BLY_CB_MAX_CONDITION_DEPTH + 2)) {
        return [];
    }
    $leaves = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (isset($row['relation']) || isset($row['conditions'])) {
            /** @var mixed $inner */
            $inner = $row['conditions'] ?? [];
            if (is_array($inner)) {
                $leaves = array_merge($leaves, bly_cb_leaf_rules($inner, $depth + 1));
            }
            continue;
        }
        $leaves[] = $row;
    }
    return $leaves;
}

/**
 * Compact human summary of a block's LEAF condition rules (obtain them
 * via bly_cb_leaf_rules()): "include:front_page, exclude:post_ids(#12)
 * (+2 more)". Empty lists state their effective meaning, which differs
 * for maintenance / nothing_found (default `everywhere`) vs everything
 * else (hidden).
 *
 * @param list<array<array-key, mixed>> $conditions
 */
// @mago-expect lint:cyclomatic-complexity
function bly_cb_condition_summary(array $conditions, string $template_type): string
{
    if ($conditions === []) {
        if ($template_type === 'maintenance' || $template_type === 'nothing_found') {
            return '(none — defaults to include:everywhere for this template type)';
        }
        return '(none — matches nowhere)';
    }

    $parts = [];
    foreach (array_slice($conditions, offset: 0, length: BLY_CB_MAX_SUMMARY_RULES) as $row) {
        $type = isset($row['type']) && $row['type'] === 'exclude' ? 'exclude' : 'include';
        $rule = isset($row['rule']) && is_string($row['rule'])
            ? bly_ext_clean_text($row['rule'], BLY_EXT_MAX_NAME_BYTES)
            : '?';
        $ref = '';
        if (isset($row['payload']) && is_array($row['payload'])) {
            /** @var mixed $pid */
            $pid = $row['payload']['post_id'] ?? $row['payload']['taxonomy_id'] ?? null;
            if (is_int($pid) || is_string($pid) && $pid !== '') {
                $ref = sprintf('(#%s)', bly_ext_clean_text((string) $pid, budget: 20));
            }
        }
        $parts[] = $type . ':' . $rule . $ref;
    }
    $suffix = count($conditions) > BLY_CB_MAX_SUMMARY_RULES
        ? sprintf(' (+%d more)', count($conditions) - BLY_CB_MAX_SUMMARY_RULES)
        : '';
    return implode(', ', $parts) . $suffix;
}

/**
 * The stored position of a hook block resolved over the display
 * defaults, plus the stored additional_locations (raw rows dropped when
 * not arrays).
 *
 * @param array<array-key, mixed> $atts
 * @return array{location: string, priority: int, custom_location: string, paragraphs_count: int, headings_count: int, cards_count: int, repeat_for_every_card: string, additional_locations: list<array<array-key, mixed>>}
 */
function bly_cb_stored_position(array $atts): array
{
    $defaults = bly_cb_position_defaults();
    $out = $defaults;
    foreach (['location', 'custom_location', 'repeat_for_every_card'] as $string_key) {
        if (!isset($atts[$string_key]) || !is_string($atts[$string_key])) {
            continue;
        }
        // Stored scalars are attacker-influencable meta — byte-capped
        // (with a truncation marker) before they can reach any output.
        $out[$string_key] = bly_ext_clean_text($atts[$string_key], BLY_EXT_MAX_NAME_BYTES);
    }
    foreach (['priority', 'paragraphs_count', 'headings_count', 'cards_count'] as $int_key) {
        $stored = bly_cb_coerce_int($atts[$int_key] ?? null);
        if ($stored === null) {
            continue;
        }
        $out[$int_key] = $stored;
    }

    $additional = [];
    if (isset($atts['additional_locations']) && is_array($atts['additional_locations'])) {
        /** @var mixed $row */
        foreach ($atts['additional_locations'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $additional[] = $row;
        }
    }

    return [
        'location' => $out['location'],
        'priority' => $out['priority'],
        'custom_location' => $out['custom_location'],
        'paragraphs_count' => $out['paragraphs_count'],
        'headings_count' => $out['headings_count'],
        'cards_count' => $out['cards_count'],
        'repeat_for_every_card' => $out['repeat_for_every_card'],
        'additional_locations' => $additional,
    ];
}

/**
 * Validation-only EFFECTIVE view of the seven scalar position keys
 * (coerced ints, capped strings — same resolution as
 * bly_cb_stored_position, without the additional_locations list). Used
 * as the cross-field baseline for partial position edits; NEVER
 * persisted, so stored bytes are untouched by it.
 *
 * @param array<array-key, mixed> $atts
 * @return array<string, int|string>
 */
function bly_cb_position_effective_base(array $atts): array
{
    $stored = bly_cb_stored_position($atts);
    return [
        'location' => $stored['location'],
        'priority' => $stored['priority'],
        'custom_location' => $stored['custom_location'],
        'paragraphs_count' => $stored['paragraphs_count'],
        'headings_count' => $stored['headings_count'],
        'cards_count' => $stored['cards_count'],
        'repeat_for_every_card' => $stored['repeat_for_every_card'],
    ];
}

/**
 * One-line position summary for list rows: "wp_body_open@10 (+1 more)",
 * "custom_hook(my_action)@10", "(none)" — hook blocks only (other
 * template types render through their own template surface, not a
 * hook).
 *
 * @param array<array-key, mixed> $atts
 */
function bly_cb_position_summary(array $atts): string
{
    $position = bly_cb_stored_position($atts);
    $location = $position['location'];
    if ($location === '') {
        return '(none)';
    }
    $label = $location === 'custom_hook'
        ? sprintf('custom_hook(%s)', bly_ext_clean_text($position['custom_location'], BLY_EXT_MAX_NAME_BYTES))
        : bly_ext_clean_text($location, BLY_EXT_MAX_NAME_BYTES);
    $extra = count($position['additional_locations']);
    return sprintf('%s@%d%s', $label, $position['priority'], $extra > 0 ? sprintf(' (+%d more)', $extra) : '');
}

/**
 * The stored template_type meta of a block ('' when absent/malformed).
 */
function bly_cb_template_type(int $post_id): string
{
    /** @var mixed $type */
    $type = get_post_meta($post_id, key: 'template_type', single: true);
    return is_string($type) ? $type : '';
}

/**
 * Whether the block's kill switch is on: `is_hook_enabled` defaults to
 * 'yes' when absent (blocksy_default_akg), and once the key is set the
 * eligibility check treats anything !== 'yes' as disabled.
 *
 * @param array<array-key, mixed> $atts
 */
function bly_cb_is_enabled(array $atts): bool
{
    if (!array_key_exists('is_hook_enabled', $atts)) {
        return true;
    }
    return $atts['is_hook_enabled'] === 'yes';
}
