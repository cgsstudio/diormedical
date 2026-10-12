<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// list-template-conditions — read the catalogue for a CPT (no id required)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/breakdance-list-template-conditions', [
    'label' => __('List Breakdance Template Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Returns the catalogue of conditions a Breakdance template (or header/footer/popup) can use to scope visibility. The catalogue is CPT-specific: `breakdance_template` exposes the `single`, `archive`, `404`, `search` shape while `breakdance_header` / `_footer` / `_popup` additionally surface the "Everywhere" pseudo-rule. Each row is a `{slug, label, values, ...}` record sourced live from Breakdance\\Themeless\\getConditionsWithValuesForPostType, the same source the admin SPA uses. Use the `slug` field as the `ruleCategorySlug` / `ruleSlug` value when composing a payload for set-template-conditions. The response also includes `template_types`: the registered values valid for a template\'s `type` field (set via create-template / edit-template). Each is `{slug, label, category}`; for a single template targeting a custom post type the slug IS that post type\'s slug (e.g. `location`), alongside generic types like `all-singles`, archive types, `404`, `search`, `front_page`.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'enum' => [
                    'breakdance_template',
                    'breakdance_header',
                    'breakdance_footer',
                    'breakdance_popup',
                    'breakdance_block',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_list_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once per CPT before composing a set-template-conditions payload. The catalogue is dynamic, installed CPTs / taxonomies / WooCommerce all add rows.',
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
function breakdance_list_template_conditions(array $input): array
{
    if (!function_exists('Breakdance\\Themeless\\getConditionsWithValuesForPostType')) {
        return ['conditions' => [], 'total' => 0, 'template_types' => []];
    }
    bd_ensure_template_conditions_registered();
    $conditions = \Breakdance\Themeless\getConditionsWithValuesForPostType($input['post_type']);
    return [
        'conditions' => array_values($conditions),
        'total' => count($conditions),
        'template_types' => bd_registered_template_types(),
    ];
}

/**
 * The registered Breakdance "template type" slugs valid for a template's
 * `type` field, sourced live from the same registry the builder's SPA uses.
 * For a single template targeting a custom post type the slug IS the CPT slug
 * (e.g. `location`); the rest are generic (`all-singles`, archive types, `404`,
 * `search`, `front_page`, WooCommerce, …). Without this the valid `type` values
 * are undiscoverable through the API.
 *
 * @return list<array{slug: string, label: string, category: string}>
 */
function bd_registered_template_types(): array
{
    if (!class_exists('Breakdance\\Themeless\\ThemelessController')) {
        return [];
    }
    bd_ensure_template_conditions_registered();
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
 * Force Breakdance to populate its template-condition registry. The
 * builder registers conditions lazily — only inside its own SPA /
 * AJAX / template_include code paths — by firing the
 * `breakdance_register_template_types_and_conditions` action. Calling
 * `getConditionsWithValuesForPostType()` from REST without first
 * firing the action reads an empty registry and silently returns
 * `[]`. Fire it once per request; the action is idempotent.
 */
function bd_ensure_template_conditions_registered(): void
{
    static $fired = false;
    // @mago-expect analysis:impossible-condition
    if ($fired) {
        return;
    }
    $fired = true;
    do_action('breakdance_register_template_types_and_conditions');
}

// ---------------------------------------------------------------------------
// set-template-conditions — write the ruleGroups for an existing template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/breakdance-set-template-conditions', [
    'label' => __('Set Breakdance Template Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Writes the `ruleGroups` field onto an existing Breakdance template\'s `_breakdance_template_settings`. `ruleGroups` is a list of OR-groups: each top-level entry is itself a list of AND-rules. A rule is `{operand: "is"|"is_not", ruleCategorySlug?: string, ruleSlug?: string, ruleDynamic?: string, value?: string|string[]}`. The whole bucket is replaced wholesale: pass the COMPLETE list, or `[]` to clear it. Always validate rule slugs against list-template-conditions BEFORE calling this; unknown rules are stored verbatim and silently never match. Other settings (type, priority, triggers, fallback) are untouched; use edit-template for those.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_set_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Replaces the entire ruleGroups field. To CARVE EXCEPTIONS into a broad rule, use multiple rules within the SAME group (AND semantics, all must match); to UNION DIFFERENT pages/posts, use multiple groups (OR semantics, any group matches).',
            'readonly' => false,
            'destructive' => true,
            // Set semantically is idempotent (same payload → same final state),
            // but the WP abilities-api lib routes `destructive=true AND
            // idempotent=true` to HTTP DELETE, and its DELETE handler can't
            // decode an `input` JSON object — Round 11 B2 confirmed the
            // ability would be unreachable via the standard REST/MCP payload
            // pattern. Drop idempotent so the routing falls through to POST.
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_set_template_conditions(array $input): array|WP_Error
{
    $id = bd_template_id_arg($input);
    if ($id <= 0) {
        return bd_error('bd_invalid_input', __('id (or post_id) must be a positive integer.', domain: 'novamira-pro'));
    }
    // Route via bd_post_or_error so revisions / auto-drafts / trashed
    // posts are rejected uniformly (Round 11 B10 — bd_load_template
    // bypasses the auto-draft check). The allowed-post-types filter
    // restricts to template CPTs.
    $post = bd_post_or_error($id, bd_template_post_types());
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
    // Round 11 B6 — `_breakdance_template_settings` is a read-modify-write
    // postmeta; without a lock two concurrent edit-template /
    // set-template-conditions on the same id lose one another's patches
    // (~10% loss on parallel REST). Serialise per-post.
    return bd_with_lock('template_settings_' . $id, static function () use ($id, $rule_groups): array|WP_Error {
        $settings = bd_read_template_settings($id);
        $settings['ruleGroups'] = $rule_groups;
        $written = bd_write_template_settings($id, $settings);
        if ($written instanceof WP_Error) {
            return $written;
        }

        $fresh = bd_load_template($id);
        if ($fresh === null) {
            return ['success' => false, 'error' => 'Template saved but could not be re-loaded.'];
        }
        return ['success' => true, 'template' => bd_template_to_array($fresh)];
    });
}
