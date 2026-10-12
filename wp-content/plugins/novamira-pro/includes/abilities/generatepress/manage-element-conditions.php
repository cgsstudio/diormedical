<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// list-element-conditions — read the catalogue (no element id required)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/generatepress-list-element-conditions', [
    'label' => __('List GeneratePress Element Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Returns the catalogue of conditions a GP Premium Element can use to scope visibility. Two buckets: `display_exclude` (used by both display_conditions and exclude_conditions on an element — same vocabulary) and `user` (used by user_conditions). Each entry is a `{group, label, locations: {<rule-key>: <label>}}` row sourced from GeneratePress_Conditions::get_conditions() and ::get_user_conditions() — the same classes the admin UI uses. Pass this catalogue to the agent before composing a payload for generatepress-set-element-conditions so the `rule` values it picks exist.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'bucket' => [
                'type' => 'string',
                'enum' => ['display_exclude', 'user', 'all'],
                'description' => 'Limit the response to one of the two condition buckets. Default `all` returns both.',
                'default' => 'all',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'display_exclude' => [
                'type' => 'array',
                'description' => 'Groups + locations valid for the display_conditions and exclude_conditions inputs of set-element-conditions.',
            ],
            'user' => [
                'type' => 'array',
                'description' => 'Groups + locations valid for the user_conditions input of set-element-conditions.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_list_element_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this once before set-element-conditions when you do not already know the rule keys. The catalogue depends on the active post types / plugins on the site — never hard-code a rule list, always read it live.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Load the admin-only dependency of GeneratePress_Conditions before use.
 *
 * GP Premium's GeneratePress_Conditions::get_user_conditions() calls
 * get_editable_roles() unguarded (elements/class-conditions.php:167 in GP
 * Premium 2.5.6). WordPress defines that function in
 * wp-admin/includes/user.php, loaded on admin screens — the only context GP
 * Premium itself uses the class in — but NOT on REST/MCP or cron requests,
 * where every conditions call used to fatal with "Call to undefined
 * function get_editable_roles()". get_conditions() (the display/exclude
 * catalogue) has no admin-only dependency, but the set path validates
 * against BOTH catalogues, so the require is needed on every entry point.
 */
function gp_load_conditions_runtime(): void
{
    if (!function_exists('get_editable_roles')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
}

/**
 * @param array{bucket?: string} $input
 * @return array<string, mixed>
 */
function generatepress_list_element_conditions(array $input): array
{
    $bucket = $input['bucket'] ?? 'all';

    gp_load_conditions_runtime();

    $display_exclude = class_exists('GeneratePress_Conditions')
        ? gp_reshape_conditions_catalog(\GeneratePress_Conditions::get_conditions())
        : [];
    $user = class_exists('GeneratePress_Conditions')
        ? gp_reshape_conditions_catalog(\GeneratePress_Conditions::get_user_conditions())
        : [];

    if ($bucket === 'display_exclude') {
        return ['display_exclude' => $display_exclude];
    }
    if ($bucket === 'user') {
        return ['user' => $user];
    }
    return ['display_exclude' => $display_exclude, 'user' => $user];
}

/**
 * Normalise GeneratePress_Conditions output (associative `<group> => {label, locations}`)
 * into the public ability shape (list of `{group, label, locations}` rows).
 *
 * @param array<string, mixed> $raw
 * @return list<array<string, mixed>>
 */
function gp_reshape_conditions_catalog(array $raw): array
{
    $out = [];
    /** @var mixed $group_data */
    foreach ($raw as $group_key => $group_data) {
        if (!is_array($group_data)) {
            continue;
        }
        // GeneratePress_Conditions::get_conditions() uses `locations`, while
        // ::get_user_conditions() uses `rules` for the same shape. Accept
        // either so the public catalogue is consistent across buckets.
        $locations_raw = [];
        if (isset($group_data['locations']) && is_array($group_data['locations'])) {
            $locations_raw = $group_data['locations'];
        }
        if ($locations_raw === [] && isset($group_data['rules']) && is_array($group_data['rules'])) {
            $locations_raw = $group_data['rules'];
        }
        $out[] = [
            'group' => $group_key,
            'label' => (string) ($group_data['label'] ?? $group_key),
            'locations' => array_map(static fn($v): string => (string) $v, $locations_raw),
        ];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// set-element-conditions — write the three condition buckets onto an element
// ---------------------------------------------------------------------------

wp_register_ability('novamira/generatepress-set-element-conditions', [
    'label' => __('Set GeneratePress Element Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Writes the display / exclude / user conditions onto an existing `gp_elements` post. Each condition is `{rule: "<group>:<key>", object?: "<id-or-empty>"}` — the storage shape the plugin\'s metabox uses (class-metabox.php:1900-1935). Pass the buckets you want to update; omitted buckets keep their current value. Pass `[]` (empty array) to clear a bucket. Always validate `rule` values against the catalogue from generatepress-list-element-conditions first — bad rules are stored as-is and silently never match.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the gp_elements post.'],
            'display_conditions' => [
                'type' => 'array',
                'description' => 'When this element should DISPLAY. OR-semantics across rows: an element renders if ANY row matches. Each row is `{rule, object?}` — `object` is the optional id/term needed by rules like `post:single` (specific post id) or `post:taxonomy:5` (specific term id).',
            ],
            'exclude_conditions' => [
                'type' => 'array',
                'description' => 'Where this element should NOT render even if display_conditions matched. Same shape as display_conditions. Use to carve exceptions out of a broader display rule (e.g. show on all pages EXCEPT id 42).',
            ],
            'user_conditions' => [
                'type' => 'array',
                'description' => 'Who sees this element. Rules from the `user` bucket of generatepress-list-element-conditions: logged-in/out, or specific roles. AND-semantics with display/exclude — the user must match AND the page must match.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Per-rule warnings. Currently: `unknown_rule:<rule>` for rules not in the live `list-element-conditions` catalogue (the write still happens — GP stores rules verbatim and silently skips unknown ones at render time).',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_set_element_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Validate the rule strings via generatepress-list-element-conditions BEFORE calling this — the plugin\'s evaluator silently skips unknown rules, so a typo means the element never appears with no error surfaced. Each bucket is replaced wholesale: pass the COMPLETE list of rows you want stored, or omit a bucket to leave it untouched.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_set_element_conditions(array $input): array|WP_Error
{
    $id = gp_coerce_int($input['id'] ?? 0);
    if ($id <= 0) {
        return gp_error('gp_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    if (gp_load_element($id) === null) {
        return gp_error('gp_not_found', sprintf(
            __('gp_elements post with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    $map = [
        'display_conditions' => '_generate_element_display_conditions',
        'exclude_conditions' => '_generate_element_exclude_conditions',
        'user_conditions' => '_generate_element_user_conditions',
    ];

    $known_rules = gp_collect_known_condition_rules();
    $warnings = [];

    $touched_any = false;
    foreach ($map as $field => $meta_key) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $touched_any = true;
        // Distinguish "clear bucket" (caller passed []) from "malformed
        // input" (caller passed a scalar or non-array). Without this, a
        // string / int / bool would normalise to [] and silently wipe
        // the bucket — agent thinks the patch landed.
        if (!is_array($input[$field])) {
            return [
                'success' => false,
                'error' => sprintf('%s must be an array of {rule, object?} rows. Pass [] to clear the bucket.', $field),
            ];
        }
        $normalised = gp_normalise_conditions_input($input[$field]);
        // Emit a warning for any rule that is NOT in the catalogue. GP
        // stores unknown rules verbatim and the evaluator silently skips
        // them — surface the typo to the agent instead of leaving it to
        // discover the element never renders.
        foreach ($normalised as $row) {
            if (in_array($row['rule'], $known_rules, strict: true)) {
                continue;
            }
            $warnings[] = 'unknown_rule:' . $row['rule'];
        }
        if ($normalised === []) {
            delete_post_meta(post_id: $id, meta_key: $meta_key);
            continue;
        }
        // GP Premium stores user_conditions as a FLAT list of rule strings
        // (`['general:logged_in', 'administrator']`) — the evaluator does
        // `array_intersect($roles, $user_info)` and treats nested tuples as
        // non-matches. display / exclude keep the `{rule, object}` tuple
        // shape. Flatten on the way to storage.
        $payload = $field === 'user_conditions'
            ? array_values(array_map(static fn(array $row): string => $row['rule'], $normalised))
            : $normalised;
        update_post_meta(post_id: $id, meta_key: $meta_key, meta_value: $payload);
    }

    if (!$touched_any) {
        return [
            'success' => false,
            'error' => 'At least one of display_conditions / exclude_conditions / user_conditions must be present in the input.',
        ];
    }

    $fresh = gp_load_element($id);
    if ($fresh === null) {
        return ['success' => false, 'error' => 'Element saved but could not be re-loaded.'];
    }
    $result = ['success' => true, 'element' => gp_element_to_array($fresh)];
    if ($warnings !== []) {
        $result['warnings'] = array_values(array_unique($warnings));
    }
    return $result;
}

/**
 * Build the flat set of rule strings currently exposed by the
 * `list-element-conditions` catalogue — used by set-element-conditions
 * to flag unknown rule values as warnings (instead of leaving the agent
 * to discover the element never renders because of a typo).
 *
 * @return list<string>
 */
function gp_collect_known_condition_rules(): array
{
    if (!class_exists('GeneratePress_Conditions')) {
        return [];
    }
    gp_load_conditions_runtime();
    $out = [];
    /** @var mixed $group_data */
    foreach (\GeneratePress_Conditions::get_conditions() as $group_data) {
        if (!is_array($group_data)) {
            continue;
        }
        $locations = isset($group_data['locations']) && is_array($group_data['locations'])
            ? $group_data['locations']
            : [];
        foreach (array_keys($locations) as $rule) {
            $out[] = (string) $rule;
        }
    }
    /** @var mixed $group_data */
    foreach (\GeneratePress_Conditions::get_user_conditions() as $group_data) {
        if (!is_array($group_data)) {
            continue;
        }
        $rules = isset($group_data['rules']) && is_array($group_data['rules']) ? $group_data['rules'] : [];
        foreach (array_keys($rules) as $rule) {
            $out[] = (string) $rule;
        }
    }
    return $out;
}
