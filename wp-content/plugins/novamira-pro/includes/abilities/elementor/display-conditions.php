<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager;
use ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const DC_REGENERATE_QUERY_ARGS_HOOK = 'elementor/theme/conditions/cache/regenerate/query_args';

wp_register_ability('novamira/elementor-get-template-conditions', [
    'label' => __('Get Elementor Template Display Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Returns the display conditions saved on an Elementor Theme Builder template — the rules that decide where the template renders on the front end. Each condition is an include or exclude rule targeting a scope such as the entire site, all singular pages, a specific post type, a specific taxonomy, or a single post by ID. A template with no conditions renders nowhere. Only Elementor Pro Theme Builder templates (header, footer, single, archive, etc.) support display conditions; core-only template types like section, container, and page do not. When Elementor Pro is not active the ability reports that display conditions are unavailable.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => el_template_id_input_schema(),
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'type' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'conditions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => dc_condition_schema_properties(),
                    'required' => ['type', 'name'],
                    'additionalProperties' => false,
                ],
                'description' => 'Saved display conditions. Empty when no conditions are assigned.',
            ],
        ],
        'required' => ['id', 'type', 'title', 'status', 'conditions'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_get_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Read what display conditions a Theme Builder template has. A template without conditions renders nowhere even when published.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/elementor-set-template-conditions', [
    'label' => __('Set Elementor Template Display Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Replaces the display conditions on an Elementor Theme Builder template. Conditions control where the template renders on the front end. Each condition is an include or exclude rule. Pass an empty `conditions` array to remove all conditions. Elementor rebuilds its conditions cache during the save, and the result carries a cache_note in two distinct cases: when that rebuild left the template out, the conditions are saved but the template does not render anywhere until Elementor includes it; when the rebuild could not be observed, the conditions are saved but whether the template renders was not verified — it may well be rendering, so check the page before reporting a problem. Only Elementor Pro Theme Builder templates support display conditions; the ability is refused when Elementor Pro is not active or when the template is not a Theme Builder type.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Elementor library template post ID. Alias: post_id.',
            ],
            'post_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Alias for template_id.',
            ],
            'conditions' => [
                'type' => 'array',
                'description' => 'Display conditions to set. Each condition is an include or exclude rule. An empty array removes all conditions.',
                'items' => [
                    'type' => 'object',
                    'properties' => dc_condition_input_schema_properties(),
                    'required' => ['type', 'name'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['conditions'],
        'anyOf' => [
            ['required' => ['template_id']],
            ['required' => ['post_id']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'type' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'conditions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => dc_condition_schema_properties(),
                    'required' => ['type', 'name'],
                    'additionalProperties' => false,
                ],
            ],
            'status_note' => [
                'type' => 'string',
                'description' => 'Present when the template is not published, reminding that conditions only take effect on published templates.',
            ],
            'cache_note' => [
                'type' => 'string',
                'description' => 'Present in two distinct cases, which the text says apart: Elementor\'s conditions-cache rebuild left this template out, so the saved conditions do not render anywhere yet; or the rebuild could not be observed, so whether the template renders was not verified (it may be rendering normally).',
            ],
            'scope_note' => [
                'type' => 'string',
                'description' => 'Present when a bare include/general condition (no sub_name) targets the entire site.',
            ],
        ],
        'required' => ['id', 'type', 'title', 'status', 'conditions'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_set_template_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Set where a Theme Builder template renders. Pass conditions as structured rules — the accepted condition names and sub-names are site-specific and reported in an invalid-condition error. Publish the template for the conditions to take effect.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function dc_condition_schema_properties(): array
{
    return [
        'type' => [
            'type' => 'string',
            'enum' => ['include', 'exclude'],
            'description' => 'Whether this condition includes or excludes matching pages.',
        ],
        'name' => [
            'type' => 'string',
            'description' => 'Top-level condition name: general (entire site), singular, archive, or a registered condition name.',
        ],
        'sub_name' => [
            'type' => 'string',
            'description' => 'Sub-condition name narrowing the scope, such as a post type slug (post, page), a taxonomy slug (category, post_tag), or a nested sub-condition such as in_category. Empty when the condition applies to the whole group.',
        ],
        'sub_id' => [
            'type' => 'string',
            'description' => 'Value selecting a single item within the sub-condition — for built-in sub-conditions a post, term, or user ID as a numeric string. Empty when the condition applies to all items in the sub-condition.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function dc_condition_input_schema_properties(): array
{
    return [
        'type' => [
            'type' => 'string',
            'enum' => ['include', 'exclude'],
            'description' => 'Whether this condition includes or excludes matching pages.',
        ],
        'name' => [
            'type' => 'string',
            'minLength' => 1,
            'description' => 'Top-level condition name: general (entire site), singular (all singular pages), archive (all archives). Other registered condition names are accepted.',
        ],
        'sub_name' => [
            'type' => 'string',
            'description' => 'Sub-condition narrowing the scope: any registered condition below `name` in Elementor\'s condition tree, at any depth — e.g. post or page under singular, in_category (posts in a category) under singular, or a taxonomy slug under archive. Omit or pass empty to apply the condition to the whole group.',
        ],
        'sub_id' => [
            'type' => 'string',
            'description' => 'Selects a single item within the sub-condition. For sub-conditions that select a post, term, or user this is a positive integer ID as a string (e.g. "42"); non-numeric or zero values are rejected. Sub-conditions that select nothing (such as front_page) reject any sub_id, because Elementor would ignore it and match more than one item. Custom conditions registered by other plugins may accept other values; an invalid value error lists what is accepted. Omit or pass empty to apply to all items in the sub-condition group.',
        ],
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_get_template_conditions(array $input): array|WP_Error
{
    $post = el_template_or_error(el_template_id_arg($input));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $conditions_manager = dc_conditions_manager_or_error();
    if ($conditions_manager instanceof WP_Error) {
        return $conditions_manager;
    }

    $theme_document = dc_theme_document_or_error($post->ID);
    if ($theme_document instanceof WP_Error) {
        return $theme_document;
    }

    return [
        'id' => $post->ID,
        'type' => el_stored_template_type($post->ID),
        'title' => $post->post_title,
        'status' => $post->post_status,
        'conditions' => dc_read_conditions($post->ID),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_set_template_conditions(array $input): array|WP_Error
{
    $post = el_template_or_error(el_template_id_arg($input));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $conditions_manager = dc_conditions_manager_or_error();
    if ($conditions_manager instanceof WP_Error) {
        return $conditions_manager;
    }

    $theme_document = dc_theme_document_or_error($post->ID);
    if ($theme_document instanceof WP_Error) {
        return $theme_document;
    }

    /** @var list<array<string, mixed>> $conditions_input */
    $conditions_input = is_array($input['conditions'] ?? null) ? array_values($input['conditions']) : [];
    $validated = dc_validate_conditions($conditions_input, $conditions_manager);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $saved = dc_save_conditions($conditions_manager, $post->ID, $theme_document['location'], $validated);
    if ($saved instanceof WP_Error) {
        return $saved;
    }

    $result = [
        'id' => $post->ID,
        'type' => el_stored_template_type($post->ID),
        'title' => $post->post_title,
        'status' => $post->post_status,
        'conditions' => dc_read_conditions($post->ID),
    ];

    if ($post->post_status !== 'publish') {
        $result['status_note'] = 'This template is not published. Display conditions only take effect on published templates.';
    }

    if (dc_has_sitewide_include($result['conditions'])) {
        $result['scope_note'] = sprintf(
            'This %s targets the entire site via include/general. Exclude rules, if any, narrow the effective scope.',
            el_stored_template_type($post->ID),
        );
    }

    if ($saved !== null) {
        $result['cache_note'] = $saved;
    }

    return $result;
}

// --- Availability predicate ---
//
// Why class_exists is not an honest probe:
//   The ThemeBuilder\Module class is autoloadable BEFORE Pro's module registry
//   initializes. class_exists returns true at plugins_loaded, but the module
//   is not yet booted and the conditions manager is not yet obtainable.
//
// Why ElementorPro\Plugin::elementor() is wrong:
//   It returns the CORE Elementor singleton (Elementor\Plugin), whose
//   Modules_Manager does NOT contain 'theme-builder'. The Theme Builder
//   module lives in Pro's OWN registry: ElementorPro\Plugin::instance()->modules_manager.
//
// Why every link is checked before it is used:
//   Pro builds modules_manager inside its own elementor/init callback. Code
//   that runs earlier, including an elementor/init callback at a lower
//   priority, sees the Pro singleton with a null modules_manager, and
//   get_conditions_manager() returns false (not null) on a module without
//   components. Dereferencing either is a fatal error, so each link is
//   type-checked and a missing link becomes an actionable WP_Error.
//
// Why theme_builder_v2 is not a gate:
//   It selects which admin UI the Site Editor menu opens. With it off,
//   save_conditions still works, conditions persist, and templates render.
//
// Why Site Editor UI state is not a gate:
//   save_conditions() and the Theme Builder constructor do not depend on the
//   Site Editor UI, so the ability probes the conditions manager directly.
//
// Execution-time condition-type registry:
//   register_conditions() fires at wp_loaded priority 10. REST dispatch and
//   WP-CLI commands run after it, but an early programmatic call does not, so
//   an empty registry returns an explicit not-ready error rather than
//   rejecting every legal condition name.

/**
 * Walk Pro's Theme Builder chain (Pro singleton → modules manager → Theme
 * Builder module) one link at a time. Returns the module, or a WP_Error
 * naming the first link that is not there yet. Safe to call from any hook.
 */
function dc_theme_builder_module_or_error(): \ElementorPro\Modules\ThemeBuilder\Module|WP_Error
{
    if (!class_exists('ElementorPro\\Plugin')) {
        return el_write_target_error(
            code: 'elementor_pro_required',
            message: 'Elementor Pro with the Theme Builder module is required. Display conditions are a Theme Builder feature.',
            data: [],
            status: 409,
        );
    }

    // Pro's modules manager is built inside Pro's elementor/init callback. Before
    // that hook runs, instance() is not needed and the property cannot be set.
    $modules_manager = did_action('elementor/init') > 0
        ? \ElementorPro\Plugin::instance()->modules_manager ?? null
        : null;
    if (!$modules_manager instanceof \ElementorPro\Core\Modules_Manager) {
        return dc_not_ready_error('Elementor Pro has not finished initializing its modules yet');
    }

    /** @var mixed $module */
    $module = $modules_manager->get_modules('theme-builder');
    if (!$module instanceof \ElementorPro\Modules\ThemeBuilder\Module) {
        return el_write_target_error(
            code: 'elementor_theme_builder_unavailable',
            message: 'The Elementor Pro Theme Builder module is not loaded on this site, so display conditions are unavailable.',
            data: [],
            status: 409,
        );
    }

    foreach (['get_conditions_manager', 'get_document'] as $method) {
        if (!method_exists($module, $method)) {
            return dc_unavailable_error(sprintf('the Theme Builder module has no %s() method', $method));
        }
    }

    return $module;
}

/**
 * Continue the chain past the module: conditions manager → the methods this
 * file calls on it. Returns the manager, or a WP_Error for the missing link.
 */
function dc_conditions_manager_or_error(): Conditions_Manager|WP_Error
{
    $module = dc_theme_builder_module_or_error();
    if ($module instanceof WP_Error) {
        return $module;
    }

    /** @var mixed $manager */
    $manager = $module->get_conditions_manager();
    if (!$manager instanceof Conditions_Manager) {
        return dc_unavailable_error('the Theme Builder module has no conditions manager');
    }

    foreach (['get_condition', 'get_conditions_config', 'save_conditions', 'purge_post_from_cache'] as $method) {
        if (!method_exists($manager, $method)) {
            return dc_unavailable_error(sprintf('the conditions manager has no %s() method', $method));
        }
    }

    return $manager;
}

function dc_not_ready_error(string $reason): WP_Error
{
    return el_write_target_error(
        code: 'elementor_theme_builder_not_ready',
        message: sprintf(
            '%s, so display conditions cannot be read or written at this point in the request. Call the ability after WordPress has finished loading (after the init and wp_loaded hooks); REST, MCP, and WP-CLI requests already do.',
            $reason,
        ),
        data: [],
        status: 503,
    );
}

function dc_unavailable_error(string $reason): WP_Error
{
    return el_write_target_error(
        code: 'elementor_theme_builder_unavailable',
        message: sprintf(
            'The Elementor Theme Builder conditions API is not available on this site (%s), so display conditions cannot be read or written.',
            $reason,
        ),
        data: [],
        status: 409,
    );
}

/**
 * The conditions manager, or null when any link of the chain is missing.
 * Safe to call from any hook.
 */
function dc_conditions_manager(): ?Conditions_Manager
{
    $manager = dc_conditions_manager_or_error();
    return $manager instanceof WP_Error ? null : $manager;
}

/**
 * Whether the Theme Builder module is booted and the conditions manager
 * is obtainable. Safe to call from any hook.
 */
function dc_theme_builder_available(): bool
{
    return dc_conditions_manager() !== null;
}

/**
 * The Theme Builder document for a template together with its location
 * (the key Elementor's conditions cache files the template under).
 *
 * @return array{document: \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document, location: string}|WP_Error
 */
function dc_theme_document_or_error(int $post_id): array|WP_Error
{
    $theme_builder = dc_theme_builder_module_or_error();
    if ($theme_builder instanceof WP_Error) {
        return $theme_builder;
    }

    /** @var mixed $document */
    $document = $theme_builder->get_document($post_id);
    if (!$document instanceof \ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document) {
        $stored_type = el_stored_template_type($post_id);
        return el_write_target_error(
            code: 'elementor_not_theme_template',
            message: sprintf(
                'Template %d (type "%s") is not a Theme Builder template and does not support display conditions. Display conditions are available on Theme Builder types such as header, footer, single, archive, and similar types that control where a layout renders.',
                $post_id,
                $stored_type,
            ),
            data: ['template_id' => $post_id, 'stored_type' => $stored_type],
        );
    }

    /** @var mixed $location */
    $location = $document->get_location();
    if (!is_string($location) || $location === '') {
        $stored_type = el_stored_template_type($post_id);
        return el_write_target_error(
            code: 'elementor_no_location',
            message: sprintf(
                'Template %d (type "%s") does not have a Theme Builder location and cannot use display conditions.',
                $post_id,
                $stored_type,
            ),
            data: ['template_id' => $post_id, 'stored_type' => $stored_type],
        );
    }

    return ['document' => $document, 'location' => $location];
}

/**
 * Read display conditions from post meta and parse them into structured arrays.
 *
 * @return list<array{type: string, name: string, sub_name: string, sub_id: string}>
 */
function dc_read_conditions(int $post_id): array
{
    wp_cache_delete(key: $post_id, group: 'post_meta');
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, key: '_elementor_conditions', single: true);
    if (!is_array($raw)) {
        return [];
    }

    $parsed = [];
    /** @var mixed $condition */
    foreach ($raw as $condition) {
        if (!is_string($condition) || $condition === '') {
            continue;
        }
        $parts = array_pad(explode('/', $condition), length: 4, value: '');
        $parsed[] = [
            'type' => $parts[0],
            'name' => $parts[1],
            'sub_name' => $parts[2],
            'sub_id' => $parts[3],
        ];
    }
    return $parsed;
}

/**
 * Validate and normalize input conditions into the format save_conditions expects.
 *
 * @param list<array<string, mixed>> $conditions
 * @return list<array{type: string, name: string, sub_name: string, sub_id: string}>|WP_Error
 */
function dc_validate_conditions(array $conditions, Conditions_Manager $conditions_manager): array|WP_Error
{
    if ($conditions === []) {
        return [];
    }

    /** @var array<array-key, mixed> $config */
    $config = $conditions_manager->get_conditions_config();
    if ($config === []) {
        return el_write_target_error(
            code: 'elementor_conditions_not_ready',
            message: 'The condition-type registry is not yet populated. This is a timing issue — the request arrived before Elementor finished initializing its condition types (they register on wp_loaded). Retry the request after WordPress has finished loading.',
            data: [],
            status: 503,
        );
    }

    $validated = [];
    $errors = [];

    foreach ($conditions as $index => $condition) {
        $result = dc_validate_single_condition($condition, $index, $conditions_manager, $config);
        if (is_string($result)) {
            $errors[] = $result;
            continue;
        }
        $validated[] = $result;
    }

    if ($errors !== []) {
        return el_write_target_error(code: 'elementor_invalid_conditions', message: implode(' ', $errors), data: [
            'errors' => $errors,
        ]);
    }

    return $validated;
}

/**
 * @param array<string, mixed>|mixed $condition
 * @param array<array-key, mixed> $config
 * @return array{type: string, name: string, sub_name: string, sub_id: string}|string
 */
function dc_validate_single_condition(
    mixed $condition,
    int $index,
    Conditions_Manager $conditions_manager,
    array $config,
): array|string {
    if (!is_array($condition)) {
        return sprintf('Condition at index %d is not an object.', $index);
    }

    $type = isset($condition['type']) && is_string($condition['type']) ? $condition['type'] : '';
    if (!in_array($type, ['include', 'exclude'], strict: true)) {
        return sprintf(
            'Condition at index %d has an invalid type "%s". Must be "include" or "exclude".',
            $index,
            $type,
        );
    }

    $name = isset($condition['name']) && is_string($condition['name']) ? trim($condition['name']) : '';
    if ($name === '') {
        return sprintf('Condition at index %d has an empty name.', $index);
    }

    $condition_instance = $conditions_manager->get_condition($name);
    if ($condition_instance === false) {
        return dc_unregistered_name_error($index, $name, $config);
    }

    $sub_name = isset($condition['sub_name']) && is_string($condition['sub_name']) ? trim($condition['sub_name']) : '';
    $sub_id = isset($condition['sub_id']) && is_string($condition['sub_id']) ? trim($condition['sub_id']) : '';

    $sub_ctx = ['index' => $index, 'name' => $name, 'sub_name' => $sub_name, 'sub_id' => $sub_id];
    $sub_error = dc_validate_sub_condition($sub_ctx, $condition_instance, $conditions_manager, $config);
    if ($sub_error !== null) {
        return $sub_error;
    }

    return ['type' => $type, 'name' => $name, 'sub_name' => $sub_name, 'sub_id' => $sub_id];
}

/**
 * @param array<array-key, mixed> $config
 */
function dc_unregistered_name_error(int $index, string $name, array $config): string
{
    $registered = array_map('strval', array_keys($config));
    sort($registered);
    return sprintf(
        'Condition at index %d has an unregistered name "%s". Registered condition names on this site: %s.',
        $index,
        $name,
        implode(', ', $registered),
    );
}

/**
 * sub_name must be a registered condition anywhere below `name` in Elementor's
 * condition tree — the Site Editor offers nested entries such as in_category
 * under singular → post, and Elementor evaluates them. sub_id is then checked
 * against the value the selected sub-condition actually consumes.
 *
 * @param array{index: int, name: string, sub_name: string, sub_id: string} $ctx
 * @param array<array-key, mixed> $config
 */
function dc_validate_sub_condition(
    array $ctx,
    Condition_Base $condition_instance,
    Conditions_Manager $conditions_manager,
    array $config,
): ?string {
    if ($ctx['sub_id'] !== '' && $ctx['sub_name'] === '') {
        return sprintf('Condition at index %d has a sub_id without a sub_name.', $ctx['index']);
    }

    if ($ctx['sub_name'] === '') {
        return null;
    }

    $sub_names = dc_descendant_condition_names($condition_instance, $conditions_manager);
    if (!in_array($ctx['sub_name'], $sub_names, strict: true)) {
        return sprintf(
            'Condition at index %d has an unregistered sub_name "%s" under "%s". Sub-conditions available under "%s" (at any depth): %s.',
            $ctx['index'],
            $ctx['sub_name'],
            $ctx['name'],
            $ctx['name'],
            $sub_names !== [] ? implode(', ', $sub_names) : '(none)',
        );
    }

    if ($ctx['sub_id'] === '') {
        return null;
    }

    return dc_validate_sub_id($ctx, dc_sub_id_spec($config[$ctx['sub_name']] ?? null));
}

/**
 * Every registered condition name below $parent in Elementor's condition tree,
 * at any depth. Names a parent lists but the manager does not have are left
 * out: Elementor skips such rules at render time.
 *
 * @return list<string>
 */
function dc_descendant_condition_names(Condition_Base $parent, Conditions_Manager $conditions_manager): array
{
    $found = [];
    $queue = dc_flatten_sub_condition_names($parent->get_sub_conditions());
    while ($queue !== []) {
        $name = array_shift($queue);
        if (isset($found[$name])) {
            continue;
        }
        $child = $conditions_manager->get_condition($name);
        if ($child === false) {
            continue;
        }
        $found[$name] = true;
        array_push($queue, ...dc_flatten_sub_condition_names($child->get_sub_conditions()));
    }
    $names = array_map('strval', array_keys($found));
    sort($names);
    return $names;
}

/**
 * @param array<array-key, mixed> $sub_conditions
 * @return list<string>
 */
function dc_flatten_sub_condition_names(array $sub_conditions): array
{
    $names = [];
    // A condition lists sub-conditions either as names (int keys) or as
    // name => constructor args, the two shapes register_condition() accepts.
    /** @var mixed $val */
    foreach ($sub_conditions as $key => $val) {
        $name = match (true) {
            is_string($key) => $key,
            is_string($val) => $val,
            default => '',
        };
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return $names;
}

/**
 * What the selected sub-condition does with sub_id, read from the control
 * Elementor registers on it. Both Site Editor UIs render the sub_id input
 * from that control and hide it when the sub-condition registers none; at
 * render time the value is passed untyped to the sub-condition's check().
 *
 * - none:    no control, so check() ignores sub_id; any value would silently
 *            broaden a "single item" rule to the whole sub-condition.
 * - numeric: a query control on one of Pro's built-in objects (posts, terms,
 *            users, templates, attachments), whose ids are positive integers.
 * - choice:  a control with a fixed option list; the value is an option key.
 * - opaque:  anything else (a custom control, or a query control on a
 *            user-defined object) — any value except the "/" delimiter.
 *
 * @return array{kind: 'none'|'numeric'|'choice'|'opaque', choices: list<string>}
 */
function dc_sub_id_spec(mixed $sub_config): array
{
    $control = dc_sub_id_control($sub_config);
    if ($control === null) {
        return ['kind' => 'none', 'choices' => []];
    }

    $autocomplete = is_array($control['autocomplete'] ?? null) ? $control['autocomplete'] : [];
    $object = is_string($autocomplete['object'] ?? null) ? $autocomplete['object'] : '';
    // QueryControl\Module::QUERY_CONTROL_ID and its built-in QUERY_OBJECT_* values.
    $numeric_objects = ['post', 'tax', 'cpt_tax', 'author', 'user', 'library_template', 'attachment'];
    if (($control['type'] ?? null) === 'query' && in_array($object, $numeric_objects, strict: true)) {
        return ['kind' => 'numeric', 'choices' => []];
    }

    $choices = $autocomplete === [] && is_array($control['options'] ?? null)
        ? array_values(array_filter(
            array_map('strval', array_keys($control['options'])),
            static fn(string $key): bool => $key !== '',
        ))
        : [];

    return $choices !== [] ? ['kind' => 'choice', 'choices' => $choices] : ['kind' => 'opaque', 'choices' => []];
}

/**
 * The control a sub-condition registers for its sub_id, or null when it has
 * none. The Site Editor app reads the first control; the classic editor
 * applies each one to the sub_id field. Registered conditions carry one.
 *
 * @return array<array-key, mixed>|null
 */
function dc_sub_id_control(mixed $sub_config): ?array
{
    $controls = is_array($sub_config) && is_array($sub_config['controls'] ?? null) ? $sub_config['controls'] : [];
    /** @var mixed $control */
    $control = $controls !== [] ? reset($controls) : null;
    return is_array($control) ? $control : null;
}

/**
 * @param array{index: int, name: string, sub_name: string, sub_id: string} $ctx
 * @param array{kind: 'none'|'numeric'|'choice'|'opaque', choices: list<string>} $spec
 */
function dc_validate_sub_id(array $ctx, array $spec): ?string
{
    $prefix = sprintf(
        'Condition at index %d has sub_id "%s" for sub-condition "%s"',
        $ctx['index'],
        $ctx['sub_id'],
        $ctx['sub_name'],
    );

    if ($spec['kind'] === 'none') {
        return (
            $prefix
            . ', which does not take a sub_id. Elementor ignores the value for this sub-condition, so the rule would match everything the sub-condition matches rather than a single item. Omit sub_id.'
        );
    }

    if ($spec['kind'] === 'numeric' && !preg_match('/^[1-9]\d*$/', $ctx['sub_id'])) {
        return (
            $prefix
            . '. This sub-condition selects a post, term, or user, so sub_id must be a positive integer as a string (e.g. "42"). Non-numeric or zero values are rejected because the condition handler casts sub_id to int, and a zero value silently widens the condition to match the entire post type or taxonomy instead of a single item.'
        );
    }

    if ($spec['kind'] === 'choice' && !in_array($ctx['sub_id'], $spec['choices'], strict: true)) {
        return sprintf('%s. Accepted values for this sub-condition: %s.', $prefix, implode(', ', $spec['choices']));
    }

    if (str_contains($ctx['sub_id'], '/')) {
        return (
            $prefix . '. sub_id cannot contain "/", which Elementor uses to separate the parts of a stored condition.'
        );
    }

    return null;
}

/**
 * @param list<array{type: string, name: string, sub_name: string, sub_id: string}> $conditions
 */
function dc_has_sitewide_include(array $conditions): bool
{
    foreach ($conditions as $c) {
        if ($c['type'] === 'include' && $c['name'] === 'general' && $c['sub_name'] === '') {
            return true;
        }
    }
    return false;
}

/**
 * The stored form Elementor's save_conditions() produces for one condition.
 *
 * @param array{type: string, name: string, sub_name: string, sub_id: string} $condition
 */
function dc_condition_string(array $condition): string
{
    $parts = [$condition['type'], $condition['name'], $condition['sub_name'], $condition['sub_id']];
    return rtrim(implode('/', $parts), characters: '/');
}

/**
 * Save conditions through Elementor Pro's Conditions_Manager, which writes
 * the post meta and then rebuilds the conditions cache.
 *
 * Returns a WP_Error, or null plus an optional cache note (a string) that the
 * caller surfaces as `cache_note`.
 *
 * @param list<array{type: string, name: string, sub_name: string, sub_id: string}> $conditions
 */
function dc_save_conditions(
    Conditions_Manager $conditions_manager,
    int $post_id,
    string $location,
    array $conditions,
): WP_Error|string|null {
    $formatted = [];
    foreach ($conditions as $condition) {
        $formatted[] = [
            'type' => $condition['type'],
            'name' => $condition['name'],
            'sub_name' => $condition['sub_name'],
            'sub_id' => $condition['sub_id'],
        ];
    }

    // Only whether a rebuild ran during this save is recorded here — never
    // what it included. What it included is read afterwards from the cache
    // object the rebuild itself filled (dc_observed_rebuild_entry()).
    $rebuilds_before = did_filter(DC_REGENERATE_QUERY_ARGS_HOOK);
    $conditions_manager->save_conditions($post_id, $formatted);
    $rebuilt = did_filter(DC_REGENERATE_QUERY_ARGS_HOOK) > $rebuilds_before;

    $stored = dc_read_conditions($post_id);
    if ($stored !== $conditions) {
        return el_write_target_error(
            code: 'elementor_conditions_save_failed',
            message: sprintf(
                'Display conditions for template %d did not persist correctly: expected %d condition(s), read back %d with different content.',
                $post_id,
                count($conditions),
                count($stored),
            ),
            data: ['template_id' => $post_id, 'expected' => $conditions, 'stored' => $stored],
            status: 500,
        );
    }

    return dc_verify_conditions_cache(
        $post_id,
        $location,
        $rebuilt ? dc_observed_rebuild_entry($conditions_manager, $location, $post_id) : null,
        array_map('Novamira\\Pro\\Abilities\\Elementor\\dc_condition_string', $conditions),
    );
}

/**
 * What Elementor's cache rebuild actually built for this template, read from
 * the manager's own Conditions_Cache object that regenerate() just filled —
 * a fact, not a prediction: whatever query, filters, statuses, exclusions or
 * pagination the rebuild used are already reflected in it. Null when the
 * public accessors are not there.
 *
 * @return array{filed: bool, rules: mixed}|null
 */
function dc_observed_rebuild_entry(Conditions_Manager $conditions_manager, string $location, int $post_id): ?array
{
    if (!method_exists($conditions_manager, 'get_cache')) {
        return null;
    }
    /** @var mixed $cache */
    $cache = $conditions_manager->get_cache();
    if (!is_object($cache) || !method_exists($cache, 'get_by_location')) {
        return null;
    }
    /** @var mixed $templates */
    $templates = $cache->get_by_location($location);
    if (!is_array($templates)) {
        return null;
    }
    return ['filed' => array_key_exists($post_id, $templates), 'rules' => $templates[$post_id] ?? null];
}

/**
 * Verify the conditions cache the front end reads from the database.
 *
 * With an observed rebuild ($built): the stored cache must hold, for this
 * template, exactly what Elementor's rebuild built — the saved rules under
 * the template's location, or nothing when the rebuild left it out — and
 * nothing under any other location. That catches a cache write that did not
 * land (stale rule, or an entry the rebuild dropped) without re-deriving
 * which templates Elementor files.
 *
 * Without an observed rebuild ($built === null) only a narrower promise is
 * kept: an entry, if present, must be exactly the saved rules under the
 * template's location. Whether one should exist is not claimed, and the
 * result says so.
 *
 * Returns a WP_Error, a cache note for the result, or null.
 *
 * @param array{filed: bool, rules: mixed}|null $built
 * @param list<string> $expected
 */
function dc_verify_conditions_cache(
    int $post_id,
    string $location,
    ?array $built,
    array $expected,
): WP_Error|string|null {
    $cached = dc_stored_cache_entries($post_id);
    $exact = $expected === [] ? [] : [$location => $expected];

    if ($built === null) {
        if ($cached === [] || $cached === $exact) {
            return $expected === []
                ? null
                : 'Elementor did not report a conditions-cache rebuild for this save, so whether this template is in the cache the front end reads was not verified. Read the template back with novamira/elementor-get-template-conditions, and check the page it should render on.';
        }
        return dc_cache_error($post_id, $location, $exact, $cached, wanted_label: 'expected entry');
    }

    if ($built['filed'] && $built['rules'] !== $expected) {
        return el_write_target_error(
            code: 'elementor_conditions_cache_failed',
            message: sprintf(
                'Display conditions for template %d were saved to post meta, but Elementor rebuilt its conditions cache with different rules (%s) than the ones saved (%s), so the template would render with the wrong scope. Re-run the call; if it persists, re-save the conditions in the Elementor Site Editor.',
                $post_id,
                (string) wp_json_encode($built['rules']),
                (string) wp_json_encode($expected),
            ),
            data: [
                'template_id' => $post_id,
                'location' => $location,
                'expected' => $expected,
                'built' => $built['rules'],
            ],
            status: 500,
        );
    }

    $wanted = $built['filed'] ? $exact : [];
    if ($cached !== $wanted) {
        return dc_cache_error($post_id, $location, $wanted, $cached, wanted_label: 'entry Elementor built');
    }

    return !$built['filed'] && $expected !== []
        ? 'Elementor rebuilt its conditions cache without this template (its rebuild does not include the template, for example because of the template status or a filter on the rebuild), so these conditions are saved but the template does not render anywhere until Elementor includes it.'
        : null;
}

/**
 * This template's entries in the stored conditions cache, keyed by location.
 *
 * @return array<string, mixed>
 */
function dc_stored_cache_entries(int $post_id): array
{
    wp_cache_delete(key: 'elementor_pro_theme_builder_conditions', group: 'options');
    /** @var mixed $cache */
    $cache = get_option('elementor_pro_theme_builder_conditions', []);
    $cached = [];
    /** @var mixed $templates */
    foreach (is_array($cache) ? $cache : [] as $cache_location => $templates) {
        if (!is_array($templates) || !array_key_exists($post_id, $templates)) {
            continue;
        }
        $cached[(string) $cache_location] = $templates[$post_id];
    }
    return $cached;
}

/**
 * @param array<array-key, mixed> $wanted
 * @param array<string, mixed> $cached
 */
function dc_cache_error(int $post_id, string $location, array $wanted, array $cached, string $wanted_label): WP_Error
{
    return el_write_target_error(
        code: 'elementor_conditions_cache_failed',
        message: sprintf(
            'Display conditions for template %d were saved to post meta, but the conditions cache the front end reads does not match them, so the template may still render with its previous scope. %s: %s; stored cache: %s. Re-run the call; if it persists, re-save the conditions in the Elementor Site Editor.',
            $post_id,
            ucfirst($wanted_label),
            (string) wp_json_encode((object) $wanted),
            (string) wp_json_encode((object) $cached),
        ),
        data: ['template_id' => $post_id, 'location' => $location, 'expected' => $wanted, 'cached' => $cached],
        status: 500,
    );
}
