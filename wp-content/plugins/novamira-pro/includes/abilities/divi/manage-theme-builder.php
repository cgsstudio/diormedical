<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;
use WP_Post;

/**
 * Abilities: discover assignment conditions and create, re-assign, and delete
 * Divi 5 Theme Builder templates (custom headers, footers, body layouts).
 *
 * A template is an `et_template` post that pairs header/body/footer layout posts
 * (`et_header_layout` / `et_body_layout` / `et_footer_layout`) with display
 * conditions (`_et_use_on` / `_et_exclude_from`, stored one condition per meta
 * row) and is linked to the live Theme Builder container (`et_theme_builder`)
 * via its `_et_template` meta. The layout posts are ordinary Divi posts — build
 * them with the normal content/module abilities. Reads stay in
 * novamira/divi-list-theme-builder-templates.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-list-theme-builder-conditions', [
    'label' => __('List Divi Theme Builder Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Lists the assignment conditions a Theme Builder template can be assigned to (use_on / exclude_from) — each row is an id (e.g. "singular:post_type:page:all", "homepage", "archive:post_type:post") and a human label. A "dynamic" id ending in ":" needs a specific object id appended (e.g. "singular:post_type:page:id:" + a page id). Use these ids with novamira/divi-create-theme-builder-template and novamira/divi-set-theme-builder-conditions.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'conditions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_theme_builder_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover valid use_on / exclude_from condition ids before assigning a Theme Builder template.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-create-theme-builder-template', [
    'label' => __('Create Divi Theme Builder Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a Theme Builder template with empty header/body/footer layouts and assigns where it applies, then returns the template_id and the new layout post ids. "areas" picks which layouts to create (any of "header", "body", "footer"). "use_on"/"exclude_from" are condition ids from novamira/divi-list-theme-builder-conditions. Build each returned layout id with the normal novamira/divi-set-content and divi-*-module abilities (pass the layout id as the post). Set "default": true for the site-wide fallback template; this demotes any previous default so exactly one remains. A non-default template requires at least one "use_on" condition. Condition writes fail closed if Divi does not expose its assignment catalog. It applies immediately on the front end.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'areas' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['header', 'body', 'footer']],
                'description' => 'Which layouts to create (one or more of header, body, footer).',
            ],
            'title' => ['type' => 'string', 'description' => 'Template name (optional).'],
            'use_on' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Condition ids the template applies to (from divi-list-theme-builder-conditions).',
            ],
            'exclude_from' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Condition ids to exclude.',
            ],
            'enabled' => ['type' => 'boolean', 'description' => 'Whether the template is active (default true).'],
            'default' => [
                'type' => 'boolean',
                'description' => 'Make this the site-wide default/fallback template (optional).',
            ],
        ],
        'required' => ['areas'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'layouts' => ['type' => 'object'],
            'note' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_create_theme_builder_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Scaffold a header/footer/body template, then fill each returned layout id with divi-set-content. Use divi-list-theme-builder-conditions for valid use_on ids.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/divi-set-theme-builder-conditions', [
    'label' => __('Set Divi Theme Builder Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Changes where an existing Theme Builder template applies and/or whether it is enabled. Identify it by "template_id" (from novamira/divi-list-theme-builder-templates). "use_on" and "exclude_from" each REPLACE the template\'s current condition list (ids from novamira/divi-list-theme-builder-conditions); "enabled" toggles it on/off. Condition writes fail closed if Divi does not expose its assignment catalog. Takes effect immediately.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'description' => 'Template id (from divi-list-theme-builder-templates).',
            ],
            'use_on' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'New condition ids the template applies to (replaces the current list).',
            ],
            'exclude_from' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'New condition ids to exclude (replaces the current list).',
            ],
            'enabled' => ['type' => 'boolean', 'description' => 'Enable/disable the template.'],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'use_on' => ['type' => 'array', 'items' => ['type' => 'string']],
            'exclude_from' => ['type' => 'array', 'items' => ['type' => 'string']],
            'enabled' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_set_theme_builder_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Re-assign or toggle an existing Theme Builder template. use_on/exclude_from replace the current lists.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-delete-theme-builder-template', [
    'label' => __('Delete Divi Theme Builder Template', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Theme Builder template by "template_id" (from novamira/divi-list-theme-builder-templates), unlinking it from the Theme Builder. By default its header/body/footer layout posts are deleted too; pass "delete_layouts": false to keep them.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer', 'description' => 'Template id to delete.'],
            'delete_layouts' => [
                'type' => 'boolean',
                'description' => 'Also delete the template\'s layout posts (default true).',
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_delete_theme_builder_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanently removes a Theme Builder template (and, by default, its layouts).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * The Theme Builder area => layout post type map.
 *
 * @return array<string, string>
 */
function divi_tb_layout_post_types(): array
{
    return ['header' => 'et_header_layout', 'body' => 'et_body_layout', 'footer' => 'et_footer_layout'];
}

/**
 * The Theme Builder template post type.
 */
function divi_tb_template_post_type(): string
{
    return defined('ET_THEME_BUILDER_TEMPLATE_POST_TYPE')
        ? (string) constant('ET_THEME_BUILDER_TEMPLATE_POST_TYPE')
        : 'et_template';
}

/**
 * A WP_Error when the Divi 5 Theme Builder API is unavailable, else null.
 */
function divi_tb_available_or_error(): ?WP_Error
{
    if (
        !function_exists('et_theme_builder_get_theme_builder_post_id')
        || !function_exists('et_theme_builder_get_theme_builder_templates')
        || !function_exists('et_theme_builder_get_template')
        || !function_exists('et_theme_builder_get_flat_template_settings_options')
    ) {
        return divi_error(
            'unavailable',
            message: 'The Divi 5 Theme Builder API is unavailable on this site.',
            status: 422,
        );
    }
    return null;
}

/**
 * Prevent Divi write helpers from terminating the request for missing access.
 */
function divi_tb_write_permission_error(): ?WP_Error
{
    return current_user_can('edit_others_posts')
        ? null
        : divi_error(
            'forbidden',
            message: 'Editing Divi Theme Builder templates requires permission to edit other posts.',
            status: 403,
        );
}

/**
 * The live Theme Builder container post id (created if missing).
 */
function divi_tb_container_id(): int
{
    return (int) et_theme_builder_get_theme_builder_post_id(live: true, create: true);
}

/**
 * Make Theme Builder writes authoritative + visible: drop the cached template-id
 * backup option (post meta then wins) and clear Divi's caches.
 */
function divi_tb_flush(int $container_id): void
{
    delete_option('et_tb_templates_backup_' . $container_id);
    if (function_exists('et_theme_builder_clear_wp_cache')) {
        et_theme_builder_clear_wp_cache(post_ids: 'all');
    }
}

/**
 * The set of valid assignment condition ids from Divi's catalog. Static keys
 * are the exact ids; entries ending in ":" are dynamic prefixes that need an
 * object id appended (e.g. "singular:post_type:page:id:" + a page id).
 *
 * @return array{exact: array<string, true>, dynamic: array<string, array<array-key, mixed>>}
 */
function divi_tb_condition_catalog(): array
{
    $exact = [];
    $dynamic = [];
    if (function_exists('et_theme_builder_get_flat_template_settings_options')) {
        /** @var mixed $option */
        foreach (et_theme_builder_get_flat_template_settings_options() as $id => $option) {
            if (!is_string($id)) {
                continue;
            }
            if (str_ends_with($id, ':')) {
                $dynamic[$id] = is_array($option) ? $option : [];
                continue;
            }
            $exact[$id] = true;
        }
    }
    return ['exact' => $exact, 'dynamic' => $dynamic];
}

/**
 * Whether a dynamic condition resolves to a concrete object Divi can select.
 *
 * @param array<array-key, mixed> $option
 */
function divi_tb_dynamic_condition_exists(string $condition, string $prefix, array $option): bool
{
    $suffix = substr($condition, strlen($prefix));
    if ($suffix === '') {
        return false;
    }

    // This is Divi's own child-option resolver. It applies the same post type,
    // taxonomy, user, and role lookup that populates the Theme Builder picker.
    if (function_exists('et_theme_builder_load_template_setting_options')) {
        /** @var mixed $resolved */
        $resolved = et_theme_builder_load_template_setting_options(setting_ids: [$condition]);
        return is_array($resolved) && isset($resolved[$condition]);
    }

    return divi_tb_fallback_dynamic_condition_exists($suffix, $option);
}

/**
 * Compatibility resolver for Divi builds without the child-option loader.
 *
 * @param array<array-key, mixed> $option
 */
function divi_tb_fallback_dynamic_condition_exists(string $suffix, array $option): bool
{
    $options = is_array($option['options'] ?? null) ? $option['options'] : [];
    $type = is_string($options['type'] ?? null) ? $options['type'] : '';
    $subtype = is_string($options['value'] ?? null) ? $options['value'] : '';
    if ($type === 'user_role') {
        return get_role(role: $suffix) !== null;
    }
    if (!ctype_digit($suffix) || (int) $suffix <= 0) {
        return false;
    }

    $object_id = (int) $suffix;
    return match ($type) {
        'post_type' => divi_tb_post_condition_object_exists($object_id, $subtype),
        'taxonomy' => divi_tb_term_condition_object_exists($object_id, $subtype),
        'user' => get_userdata(user_id: $object_id) !== false,
        default => false,
    };
}

function divi_tb_post_condition_object_exists(int $object_id, string $post_type): bool
{
    /** @var WP_Post|null $post */
    $post = get_post(post: $object_id);
    return $post instanceof WP_Post && $post->post_type === $post_type;
}

function divi_tb_term_condition_object_exists(int $object_id, string $taxonomy): bool
{
    $term = term_exists(term: $object_id, taxonomy: $taxonomy);
    return $term !== null && $term !== 0;
}

/**
 * Reject a condition list that names ids Divi's catalog does not know: a bogus
 * id is stored verbatim and then matches nothing, so the agent believes the
 * template is assigned while it silently never applies. A dynamic id is valid
 * when it extends one of the catalog's "…:" prefixes with a concrete object id.
 *
 * @param list<string> $conditions
 */
function divi_tb_validate_conditions(array $conditions, string $field): ?WP_Error
{
    if ($conditions === []) {
        return null;
    }
    $catalog = divi_tb_condition_catalog();
    if ($catalog['exact'] === [] && $catalog['dynamic'] === []) {
        return divi_error(
            'condition_catalog_unavailable',
            message: 'Divi did not provide its Theme Builder condition catalog, so the assignment was not written.',
            status: 500,
        );
    }

    foreach ($conditions as $condition) {
        $value = trim($condition);
        if ($value === '') {
            return divi_error(
                'invalid_condition',
                message: sprintf(
                    'The %s condition list contains an empty value. Use novamira/divi-list-theme-builder-conditions '
                    . 'and provide a listed condition id.',
                    $field,
                ),
                status: 422,
            );
        }
        if (isset($catalog['exact'][$value])) {
            continue;
        }
        $matching_prefix = '';
        $matching_option = [];
        foreach ($catalog['dynamic'] as $prefix => $option) {
            if (!str_starts_with($value, $prefix) || strlen($prefix) <= strlen($matching_prefix)) {
                continue;
            }
            $matching_prefix = $prefix;
            $matching_option = $option;
        }
        $matched =
            $matching_prefix !== '' && divi_tb_dynamic_condition_exists($value, $matching_prefix, $matching_option);
        if (!$matched) {
            return divi_error(
                'invalid_condition',
                message: sprintf(
                    'Invalid %s condition "%s". Use novamira/divi-list-theme-builder-conditions for valid ids. '
                    . 'A dynamic id ending in ":" must be followed by the id of an existing object selected by Divi.',
                    $field,
                    $value,
                ),
                status: 422,
            );
        }
    }
    return null;
}

/**
 * Trim and de-duplicate a validated condition list in storage order.
 *
 * @param list<string> $conditions
 * @return list<string>
 */
function divi_tb_normalize_conditions(array $conditions): array
{
    $normalized = [];
    foreach ($conditions as $condition) {
        $value = trim($condition);
        if ($value !== '' && !in_array($value, $normalized, strict: true)) {
            $normalized[] = $value;
        }
    }
    return $normalized;
}

/**
 * Replace a template's condition list (`_et_use_on` / `_et_exclude_from`),
 * stored one condition per meta row to match Divi's reader.
 *
 * @param list<string> $conditions
 */
function divi_tb_write_conditions(int $template_id, string $meta_key, array $conditions): bool
{
    delete_post_meta($template_id, $meta_key);
    $written = [];
    foreach ($conditions as $condition) {
        $value = trim($condition);
        if ($value === '') {
            continue;
        }
        if (add_post_meta($template_id, $meta_key, $value) === false) {
            return false;
        }
        $written[] = $value;
    }
    return get_post_meta(post_id: $template_id, key: $meta_key, single: false) === $written;
}

/**
 * Resolve and validate a template id, returning it or a WP_Error.
 *
 * @return int|WP_Error
 */
function divi_tb_template_or_error(?int $id)
{
    if ($id === null || $id <= 0) {
        return divi_error(
            'invalid_input',
            message: 'A numeric "template_id" is required (from novamira/divi-list-theme-builder-templates).',
        );
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post || $post->post_type !== divi_tb_template_post_type()) {
        return divi_error('not_found', message: sprintf('No Theme Builder template with id %d.', $id), status: 404);
    }
    return $id;
}

/**
 * Fail before Divi can interpret a non-published template as a new insert.
 */
function divi_tb_template_write_target_error(int $template_id): ?WP_Error
{
    if (
        $template_id > 0
        && get_post_type($template_id) === divi_tb_template_post_type()
        && get_post_status($template_id) === 'publish'
    ) {
        return null;
    }
    return divi_error(
        'template_not_published',
        message: sprintf(
            'Theme Builder template %d must be a published %s post before it can be changed; no write was attempted.',
            $template_id,
            divi_tb_template_post_type(),
        ),
        status: 409,
    );
}

/**
 * Validate the requested layout areas (header/body/footer), de-duplicated.
 *
 * @param array<string, mixed> $input
 * @return list<string>|WP_Error
 */
function divi_tb_areas_or_error(array $input)
{
    $valid = array_keys(divi_tb_layout_post_types());
    $out = [];
    foreach (divi_string_list_arg($input, key: 'areas') as $area) {
        $value = trim($area);
        if (!in_array($value, $valid, strict: true)) {
            return divi_error('invalid_input', message: sprintf(
                'Unknown area "%s". Valid areas: header, body, footer.',
                $value,
            ));
        }
        if (!in_array($value, $out, strict: true)) {
            $out[] = $value;
        }
    }
    if ($out === []) {
        return divi_error('invalid_input', message: 'At least one of "areas" (header, body, footer) is required.');
    }
    return $out;
}

/**
 * Read an optional boolean input — the literal `true`, or null when absent (so
 * callers pick the default with `?? true` / `?? false`).
 *
 * @param array<string, mixed> $input
 */
function divi_tb_input_bool(array $input, string $key): ?bool
{
    if (!array_key_exists($key, $input)) {
        return null;
    }
    return $input[$key] === true;
}

/**
 * The canonical empty layout graph used by Divi's template store.
 *
 * @return array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}}
 */
function divi_tb_empty_layout_graph(): array
{
    return [
        'header' => ['id' => 0, 'enabled' => true],
        'body' => ['id' => 0, 'enabled' => true],
        'footer' => ['id' => 0, 'enabled' => true],
    ];
}

/**
 * Insert one layout through Divi, or reproduce Divi's registration fallback.
 *
 * @return int|WP_Error
 */
function divi_tb_insert_layout_post(string $post_type, string $title)
{
    $forbidden = divi_tb_write_permission_error();
    if ($forbidden instanceof WP_Error) {
        return $forbidden;
    }

    $post = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => '',
    ];
    if (function_exists('et_theme_builder_insert_layout')) {
        /** @var mixed $inserted */
        $inserted = et_theme_builder_insert_layout(options: $post);
        return is_int($inserted) && $inserted > 0
            ? $inserted
            : divi_error(
                'create_layout_failed',
                message: 'Divi could not insert the Theme Builder layout.',
                status: 500,
            );
    }

    $inserted = wp_insert_post($post, wp_error: true);
    if ($inserted instanceof WP_Error) {
        return divi_error('create_layout_failed', message: 'Could not insert the Theme Builder layout.', status: 500);
    }
    $terms = wp_set_object_terms(object_id: $inserted, terms: 'layout', taxonomy: 'layout_type', append: true);
    if ($terms instanceof WP_Error) {
        wp_delete_post($inserted, force_delete: true);
        return divi_error('create_layout_failed', message: 'Could not register the Theme Builder layout.', status: 500);
    }
    if (function_exists('et_builder_enable_for_post')) {
        et_builder_enable_for_post(post_id: $inserted);
    }
    return $inserted;
}

/**
 * @param list<int> $post_ids
 */
function divi_tb_delete_posts(array $post_ids): void
{
    foreach (array_unique($post_ids) as $post_id) {
        if ($post_id <= 0) {
            continue;
        }
        wp_delete_post($post_id, force_delete: true);
    }
}

/**
 * Create an empty layout post per requested area through Divi's insert API.
 *
 * @param list<string> $areas
 * @return array<string, int>|WP_Error
 */
function divi_tb_create_layouts(array $areas)
{
    $types = divi_tb_layout_post_types();
    $layouts = [];
    foreach ($areas as $area) {
        $inserted = divi_tb_insert_layout_post(post_type: $types[$area], title: ucfirst($area));
        if ($inserted instanceof WP_Error) {
            divi_tb_delete_posts(array_values($layouts));
            return divi_error(
                'create_layout_failed',
                message: sprintf('Could not create the Theme Builder %s layout.', $area),
                status: 500,
            );
        }

        $layouts[$area] = $inserted;
        divi_mark_builder_page($inserted);
        if (
            get_post_type($inserted) !== $types[$area]
            || get_post_status($inserted) !== 'publish'
            || !has_term(term: 'layout', taxonomy: 'layout_type', post: $inserted)
        ) {
            divi_tb_delete_posts(array_values($layouts));
            return divi_error(
                'create_layout_failed',
                message: sprintf('Divi did not register the Theme Builder %s layout.', $area),
                status: 500,
            );
        }
    }
    return $layouts;
}

/**
 * Persist a complete template through Divi, with a compatible raw fallback.
 *
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $template
 * @return int|WP_Error
 */
function divi_tb_store_template(int $container_id, array $template)
{
    $forbidden = divi_tb_write_permission_error();
    if ($forbidden instanceof WP_Error) {
        return $forbidden;
    }

    $requested_id = divi_tb_array_int(values: $template, key: 'id');
    if ($requested_id > 0) {
        $invalid_target = divi_tb_template_write_target_error($requested_id);
        if ($invalid_target instanceof WP_Error) {
            return $invalid_target;
        }
    }

    if (function_exists('et_theme_builder_store_template')) {
        /** @var mixed $stored */
        $stored = et_theme_builder_store_template(
            theme_builder_id: $container_id,
            template: $template,
            allow_default: true,
        );
        if (is_int($stored) && $stored > 0) {
            return $stored;
        }
        return divi_error('store_failed', message: 'Divi could not store the Theme Builder template.', status: 500);
    }

    $template_id = divi_tb_raw_template_post($template);
    if ($template_id instanceof WP_Error) {
        return $template_id;
    }

    update_post_meta(
        post_id: $template_id,
        meta_key: '_et_autogenerated_title',
        meta_value: $template['autogenerated_title'],
    );
    update_post_meta(post_id: $template_id, meta_key: '_et_default', meta_value: $template['default']);
    update_post_meta(post_id: $template_id, meta_key: '_et_enabled', meta_value: $template['enabled']);
    foreach ($template['layouts'] as $area => $layout) {
        update_post_meta(post_id: $template_id, meta_key: '_et_' . $area . '_layout_id', meta_value: $layout['id']);
        update_post_meta(
            post_id: $template_id,
            meta_key: '_et_' . $area . '_layout_enabled',
            meta_value: $layout['enabled'] ? '1' : '0',
        );
    }
    $use_on_written = divi_tb_write_conditions(
        template_id: $template_id,
        meta_key: '_et_use_on',
        conditions: $template['use_on'],
    );
    $exclude_written = divi_tb_write_conditions(
        template_id: $template_id,
        meta_key: '_et_exclude_from',
        conditions: $template['exclude_from'],
    );
    if (!$use_on_written || !$exclude_written) {
        if ($requested_id === 0) {
            wp_delete_post($template_id, force_delete: true);
        }
        return divi_error(
            'store_failed',
            message: 'Could not store the Theme Builder assignment conditions.',
            status: 500,
        );
    }
    return $template_id;
}

/**
 * Raw template-post fallback for Divi builds without the canonical store.
 *
 * @param array<array-key, mixed> $template
 * @return int|WP_Error
 */
function divi_tb_raw_template_post(array $template)
{
    $template_id = divi_tb_array_int(values: $template, key: 'id');
    $title = divi_tb_array_string(values: $template, key: 'title');
    if ($template_id > 0) {
        $updated = wp_update_post(['ID' => $template_id, 'post_title' => $title], wp_error: true);
        if ($updated instanceof WP_Error) {
            return divi_error(
                'store_failed',
                message: 'Could not update the Theme Builder template post.',
                status: 500,
            );
        }
        return $template_id;
    }

    $inserted = wp_insert_post([
        'post_type' => divi_tb_template_post_type(),
        'post_status' => 'publish',
        'post_title' => $title,
    ], wp_error: true);
    if ($inserted instanceof WP_Error) {
        return divi_error('store_failed', message: 'Could not create the Theme Builder template post.', status: 500);
    }
    return $inserted;
}

/**
 * Convert Divi's reader shape back to its complete store shape.
 *
 * @param array<array-key, mixed> $template
 * @return array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * }
 */
function divi_tb_template_to_store_shape(array $template): array
{
    $template_id = divi_tb_array_int(values: $template, key: 'id');

    return [
        'id' => $template_id,
        'title' => divi_tb_array_string(values: $template, key: 'title'),
        'autogenerated_title' => get_post_meta(post_id: $template_id, key: '_et_autogenerated_title', single: true)
        === '1'
            ? '1'
            : '0',
        'default' => divi_tb_array_bool(values: $template, key: 'default') ? '1' : '0',
        'enabled' => divi_tb_array_bool(values: $template, key: 'enabled') ? '1' : '0',
        'layouts' => [
            'header' => divi_tb_reader_layout(
                layouts: divi_tb_array_value(values: $template, key: 'layouts'),
                area: 'header',
            ),
            'body' => divi_tb_reader_layout(
                layouts: divi_tb_array_value(values: $template, key: 'layouts'),
                area: 'body',
            ),
            'footer' => divi_tb_reader_layout(
                layouts: divi_tb_array_value(values: $template, key: 'layouts'),
                area: 'footer',
            ),
        ],
        'use_on' => divi_tb_string_list_from_mixed(value: divi_tb_array_value(values: $template, key: 'use_on')),
        'exclude_from' => divi_tb_string_list_from_mixed(value: divi_tb_array_value(
            values: $template,
            key: 'exclude_from',
        )),
    ];
}

/**
 * @param array<array-key, mixed> $values
 */
function divi_tb_array_value(array $values, string $key): mixed
{
    return $values[$key] ?? null;
}

/**
 * @param array<array-key, mixed> $values
 */
function divi_tb_array_int(array $values, string $key): int
{
    if (is_int($values[$key] ?? null)) {
        return $values[$key];
    }
    return is_numeric($values[$key] ?? null) ? (int) $values[$key] : 0;
}

/**
 * @param array<array-key, mixed> $values
 */
function divi_tb_array_string(array $values, string $key): string
{
    return is_string($values[$key] ?? null) ? $values[$key] : '';
}

/**
 * @param array<array-key, mixed> $values
 */
function divi_tb_array_bool(array $values, string $key): bool
{
    return divi_tb_array_value(values: $values, key: $key) === true;
}

/**
 * @return list<string>
 */
function divi_tb_string_list_from_mixed(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $strings = [];
    /** @var mixed $item */
    foreach ($value as $item) {
        if (!is_string($item)) {
            continue;
        }
        $strings[] = $item;
    }
    return divi_tb_normalize_conditions($strings);
}

/**
 * @return array{id: int, enabled: bool}
 */
function divi_tb_reader_layout(mixed $layouts, string $area): array
{
    if (!is_array($layouts)) {
        return ['id' => 0, 'enabled' => false];
    }
    /** @var mixed $layout */
    $layout = divi_tb_array_value(values: $layouts, key: $area);
    if (!is_array($layout)) {
        return ['id' => 0, 'enabled' => false];
    }
    return [
        'id' => divi_tb_array_int(values: $layout, key: 'id'),
        'enabled' => divi_tb_array_bool(values: $layout, key: 'enabled'),
    ];
}

/**
 * Put requested layout ids into a complete canonical layout graph.
 *
 * @param array<string, int> $layouts
 * @return array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}}
 */
function divi_tb_layout_graph(array $layouts): array
{
    return [
        'header' => ['id' => $layouts['header'] ?? 0, 'enabled' => true],
        'body' => ['id' => $layouts['body'] ?? 0, 'enabled' => true],
        'footer' => ['id' => $layouts['footer'] ?? 0, 'enabled' => true],
    ];
}

/**
 * Live templates from Divi's own Theme Builder graph reader.
 *
 * @return list<array<array-key, mixed>>
 */
function divi_tb_live_templates(int $container_id): array
{
    /** @var mixed $templates */
    $templates = et_theme_builder_get_theme_builder_templates(true, $container_id);
    $out = [];
    if (!is_array($templates)) {
        return $out;
    }
    /** @var mixed $template */
    foreach ($templates as $template) {
        if (!is_array($template)) {
            continue;
        }
        $out[] = $template;
    }
    return $out;
}

/**
 * @return array<array-key, mixed>|null
 */
function divi_tb_find_live_template(int $container_id, int $template_id): ?array
{
    foreach (divi_tb_live_templates($container_id) as $template) {
        if ((int) ($template['id'] ?? 0) === $template_id) {
            return $template;
        }
    }
    return null;
}

/**
 * Template ids attached in post meta, without Divi's recovery-option override.
 *
 * @return list<int>
 */
function divi_tb_post_meta_template_ids(int $container_id): array
{
    /** @var mixed $rows */
    $rows = get_post_meta(post_id: $container_id, key: '_et_template', single: false);
    return divi_tb_template_ids_from_mixed($rows);
}

/**
 * @return list<int>
 */
function divi_tb_template_ids_from_mixed(mixed $rows): array
{
    if (!is_array($rows)) {
        return [];
    }
    $ids = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_numeric($row)) {
            continue;
        }
        $ids[] = (int) $row;
    }
    return array_values(array_unique($ids));
}

/**
 * Merge Divi's live recovery source into post meta, then make the union live.
 */
function divi_tb_prepare_live_graph(int $container_id): ?WP_Error
{
    $reader_ids = divi_tb_attached_template_ids($container_id);
    $meta_ids = divi_tb_post_meta_template_ids($container_id);
    foreach (array_diff($reader_ids, $meta_ids) as $template_id) {
        if (add_post_meta(post_id: $container_id, meta_key: '_et_template', meta_value: $template_id) === false) {
            return divi_error(
                'container_reconcile_failed',
                message: 'Could not reconcile the live Theme Builder template references.',
                status: 500,
            );
        }
    }
    $expected = array_values(array_unique(array_merge($reader_ids, $meta_ids)));
    sort($expected);
    divi_tb_flush($container_id);
    $attached = divi_tb_attached_template_ids($container_id);
    sort($attached);
    return $attached === $expected
        ? null
        : divi_error(
            'container_reconcile_failed',
            message: 'The live Theme Builder template references did not persist after reconciliation.',
            status: 500,
        );
}

/**
 * Attach a template id to the live Theme Builder container and check the write.
 */
function divi_tb_attach_template(int $container_id, int $template_id): bool
{
    $attached = divi_tb_attached_template_ids($container_id);
    if (!in_array($template_id, $attached, strict: true)) {
        if (add_post_meta(post_id: $container_id, meta_key: '_et_template', meta_value: $template_id) === false) {
            return false;
        }
        $attached = divi_tb_attached_template_ids($container_id);
    }
    return in_array($template_id, $attached, strict: true);
}

/**
 * @return list<int>
 */
function divi_tb_attached_template_ids(int $container_id): array
{
    if (function_exists('et_theme_builder_get_theme_builder_template_ids')) {
        /** @var mixed $rows */
        $rows = et_theme_builder_get_theme_builder_template_ids(live: true, theme_builder_id: $container_id);
        return divi_tb_template_ids_from_mixed($rows);
    }
    /** @var mixed $backup */
    $backup = get_option('et_tb_templates_backup_' . $container_id, default_value: false);
    return divi_tb_template_ids_from_mixed(
        $backup === false ? get_post_meta(post_id: $container_id, key: '_et_template', single: false) : $backup,
    );
}

/**
 * Remove posts created by a failed write and their container references.
 *
 * @param list<int> $template_ids
 * @param list<int> $layout_ids
 */
function divi_tb_rollback_created(int $container_id, array $template_ids, array $layout_ids): void
{
    foreach (array_unique($template_ids) as $template_id) {
        if ($template_id <= 0) {
            continue;
        }
        delete_post_meta(post_id: $container_id, meta_key: '_et_template', meta_value: $template_id);
        wp_delete_post($template_id, force_delete: true);
    }
    foreach (array_unique($layout_ids) as $layout_id) {
        if ($layout_id <= 0) {
            continue;
        }
        wp_delete_post($layout_id, force_delete: true);
    }
    divi_tb_flush($container_id);
}

/**
 * Whether a template returned by Divi matches the complete requested shape.
 *
 * @param array<array-key, mixed> $actual
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $expected
 */
function divi_tb_template_matches(array $actual, array $expected): bool
{
    return (
        divi_tb_template_flags_match($actual, $expected)
        && divi_tb_template_conditions_match($actual, $expected)
        && divi_tb_template_layouts_match($actual, $expected)
    );
}

/**
 * @param array<array-key, mixed> $actual
 * @param array<array-key, mixed> $expected
 */
function divi_tb_template_flags_match(array $actual, array $expected): bool
{
    return (
        divi_tb_array_int(values: $actual, key: 'id') === divi_tb_array_int(values: $expected, key: 'id')
        && divi_tb_array_bool(values: $actual, key: 'default')
        === (divi_tb_array_string(values: $expected, key: 'default') === '1')
        && divi_tb_array_bool(values: $actual, key: 'enabled')
        === (divi_tb_array_string(values: $expected, key: 'enabled') === '1')
    );
}

/**
 * @param array<array-key, mixed> $actual
 * @param array<array-key, mixed> $expected
 */
function divi_tb_template_conditions_match(array $actual, array $expected): bool
{
    return (
        divi_tb_string_list_from_mixed(value: divi_tb_array_value(
            values: $actual,
            key: 'use_on',
        )) === divi_tb_string_list_from_mixed(value: divi_tb_array_value(values: $expected, key: 'use_on'))
        && divi_tb_string_list_from_mixed(value: divi_tb_array_value(
            values: $actual,
            key: 'exclude_from',
        )) === divi_tb_string_list_from_mixed(value: divi_tb_array_value(values: $expected, key: 'exclude_from'))
    );
}

/**
 * @param array<array-key, mixed> $actual
 * @param array<array-key, mixed> $expected
 */
function divi_tb_template_layouts_match(array $actual, array $expected): bool
{
    foreach (array_keys(divi_tb_layout_post_types()) as $area) {
        $actual_layout = divi_tb_reader_layout(
            layouts: divi_tb_array_value(values: $actual, key: 'layouts'),
            area: $area,
        );
        $expected_layout = divi_tb_reader_layout(
            layouts: divi_tb_array_value(values: $expected, key: 'layouts'),
            area: $area,
        );
        if ($actual_layout !== $expected_layout) {
            return false;
        }
    }
    return true;
}

/**
 * Verify the live graph, direct template reader, and requested layouts.
 *
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $expected
 * @param list<string> $requested_areas
 */
function divi_tb_verify_template(int $container_id, array $expected, array $requested_areas): ?WP_Error
{
    $template_id = $expected['id'];
    $live = divi_tb_find_live_template($container_id, $template_id);

    /** @var mixed $direct */
    $direct = et_theme_builder_get_template(template_id: $template_id);
    if (
        $live === null
        || !is_array($direct)
        || !in_array($template_id, divi_tb_attached_template_ids($container_id), strict: true)
        || !divi_tb_template_matches($live, $expected)
        || !divi_tb_template_matches($direct, $expected)
    ) {
        return divi_error(
            'persistence_failed',
            message: 'The Theme Builder state read back after the write did not match the requested template.',
            status: 500,
        );
    }

    return divi_tb_verify_layouts($expected, $requested_areas);
}

/**
 * @param array<array-key, mixed> $expected
 * @param list<string> $requested_areas
 */
function divi_tb_verify_layouts(array $expected, array $requested_areas): ?WP_Error
{
    $types = divi_tb_layout_post_types();
    foreach ($requested_areas as $area) {
        $layout = divi_tb_reader_layout(layouts: divi_tb_array_value(values: $expected, key: 'layouts'), area: $area);
        $layout_id = $layout['id'];
        if (
            $layout_id <= 0
            || get_post_type($layout_id) !== $types[$area]
            || get_post_status($layout_id) !== 'publish'
            || !has_term(term: 'layout', taxonomy: 'layout_type', post: $layout_id)
            || get_post_meta(post_id: $layout_id, key: '_et_pb_use_builder', single: true) !== 'on'
            || get_post_meta(post_id: $layout_id, key: '_et_pb_use_divi_5', single: true) !== 'on'
            || get_post_meta(post_id: $layout_id, key: '_et_pb_show_page_creation', single: true) !== 'off'
        ) {
            return divi_error(
                'persistence_failed',
                message: sprintf('The Theme Builder %s layout did not retain its Divi registration.', $area),
                status: 500,
            );
        }
    }
    return null;
}

/**
 * Verify that one specific template is the only live default.
 */
function divi_tb_verify_single_default(int $container_id, int $template_id): ?WP_Error
{
    $default_ids = [];
    foreach (divi_tb_live_templates($container_id) as $candidate) {
        if (!divi_tb_array_bool(values: $candidate, key: 'default')) {
            continue;
        }
        $default_ids[] = divi_tb_array_int(values: $candidate, key: 'id');
    }
    return $default_ids === [$template_id]
        ? null
        : divi_error(
            'default_conflict',
            message: 'The Theme Builder did not retain exactly one default template.',
            status: 500,
        );
}

/**
 * Create and attach Divi's explicit empty default for a previously empty graph.
 *
 * @return int|WP_Error
 */
function divi_tb_bootstrap_default(int $container_id)
{
    $payload = divi_tb_default_template_payload();
    $template_id = divi_tb_store_template($container_id, $payload);
    if ($template_id instanceof WP_Error) {
        return $template_id;
    }
    if (!divi_tb_attach_template($container_id, $template_id)) {
        wp_delete_post($template_id, force_delete: true);
        return divi_error(
            'create_default_failed',
            message: 'Could not attach the initial default template to the live Theme Builder.',
            status: 500,
        );
    }
    return $template_id;
}

/**
 * Divi's explicit empty default-template shape.
 *
 * @return array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * }
 */
function divi_tb_default_template_payload(): array
{
    return [
        'id' => 0,
        'title' => '',
        'autogenerated_title' => '1',
        'default' => '1',
        'enabled' => '1',
        'layouts' => divi_tb_empty_layout_graph(),
        'use_on' => [],
        'exclude_from' => [],
    ];
}

/**
 * Add the outcome of an automatic restoration to a write error.
 */
function divi_tb_error_with_rollback(WP_Error $error, string $outcome): WP_Error
{
    $suffix = match ($outcome) {
        'restored' => ' The previous values were restored.',
        'failed' => ' Restoring the previous values also failed.',
        default => '',
    };
    $message = $error->get_error_message() . $suffix;
    /** @var mixed $data */
    $data = $error->get_error_data();
    $details = is_array($data) ? $data : [];
    $details['rollback'] = $outcome;
    return new WP_Error((string) $error->get_error_code(), $message, $details);
}

/**
 * Restore only default flags changed during deterministic demotion.
 *
 * @param list<array{id: int, default: string}> $snapshots
 */
function divi_tb_restore_default_flags(int $container_id, array $snapshots): string
{
    if ($snapshots === []) {
        return 'none';
    }
    $outcome = 'restored';
    foreach ($snapshots as $snapshot) {
        $invalid_target = divi_tb_template_write_target_error($snapshot['id']);
        if ($invalid_target instanceof WP_Error) {
            $outcome = 'failed';
            continue;
        }
        update_post_meta(post_id: $snapshot['id'], meta_key: '_et_default', meta_value: $snapshot['default']);
        if (get_post_meta(post_id: $snapshot['id'], key: '_et_default', single: true) !== $snapshot['default']) {
            $outcome = 'failed';
        }
    }
    divi_tb_flush($container_id);
    return $outcome;
}

/**
 * Demote every attached default except the newly created one.
 *
 * @return list<array{id: int, default: string}>|WP_Error Default-meta snapshots for rollback.
 */
function divi_tb_demote_other_defaults(int $container_id, int $template_id)
{
    $snapshots = [];
    foreach (divi_tb_live_templates($container_id) as $template) {
        if (
            divi_tb_array_int(values: $template, key: 'id') === $template_id
            || !divi_tb_array_bool(values: $template, key: 'default')
        ) {
            continue;
        }
        $candidate_id = divi_tb_array_int(values: $template, key: 'id');
        $invalid_target = divi_tb_template_write_target_error($candidate_id);
        if ($invalid_target instanceof WP_Error) {
            return divi_tb_error_with_rollback($invalid_target, outcome: 'none');
        }
        $snapshots[] = ['id' => $candidate_id, 'default' => '1'];
    }

    $applied = [];
    foreach ($snapshots as $snapshot) {
        $applied[] = $snapshot;
        update_post_meta(post_id: $snapshot['id'], meta_key: '_et_default', meta_value: '0');
        if (get_post_meta(post_id: $snapshot['id'], key: '_et_default', single: true) !== '0') {
            $rollback = divi_tb_restore_default_flags($container_id, $applied);
            return divi_tb_error_with_rollback(
                divi_error(
                    'default_conflict',
                    message: 'Could not demote the existing Theme Builder default template.',
                    status: 500,
                ),
                $rollback,
            );
        }
    }
    return $snapshots;
}

/**
 * @return array<string, mixed>
 */
function divi_list_theme_builder_conditions(array $input): array
{
    if (!function_exists('et_theme_builder_get_flat_template_settings_options')) {
        return ['conditions' => [], 'total' => 0];
    }

    $rows = [];
    /** @var mixed $option */
    foreach (et_theme_builder_get_flat_template_settings_options() as $id => $option) {
        if (!is_string($id) || !is_array($option)) {
            continue;
        }
        $rows[] = [
            'id' => $id,
            'label' => is_string($option['label'] ?? null) ? $option['label'] : $id,
            'dynamic' => str_ends_with($id, ':'),
        ];
    }
    return ['conditions' => $rows, 'total' => count($rows)];
}

/**
 * Validate one condition input before normalization can discard empty values.
 *
 * @param array<string, mixed> $input
 * @return list<string>|WP_Error
 */
function divi_tb_condition_input_or_error(array $input, string $field)
{
    $conditions = divi_string_list_arg($input, key: $field);
    $invalid = divi_tb_validate_conditions($conditions, $field);
    return $invalid instanceof WP_Error ? $invalid : divi_tb_normalize_conditions($conditions);
}

/**
 * @param array<string, mixed> $input
 * @return array{use_on: list<string>, exclude_from: list<string>}|WP_Error
 */
function divi_tb_create_conditions_or_error(array $input)
{
    $use_on = divi_tb_condition_input_or_error(input: $input, field: 'use_on');
    if ($use_on instanceof WP_Error) {
        return $use_on;
    }
    $exclude_from = divi_tb_condition_input_or_error(input: $input, field: 'exclude_from');
    if ($exclude_from instanceof WP_Error) {
        return $exclude_from;
    }
    if ($use_on !== [] || (divi_tb_input_bool($input, key: 'default') ?? false)) {
        return ['use_on' => $use_on, 'exclude_from' => $exclude_from];
    }
    return divi_error(
        'inert_template',
        message: 'A non-default Theme Builder template requires at least one "use_on" condition. '
        . 'Choose one with novamira/divi-list-theme-builder-conditions or set "default": true.',
        status: 422,
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, list<string>>|WP_Error
 */
function divi_tb_changed_conditions_or_error(array $input)
{
    $changed = [];
    foreach (['use_on', 'exclude_from'] as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $conditions = divi_tb_condition_input_or_error($input, $field);
        if ($conditions instanceof WP_Error) {
            return $conditions;
        }
        $changed[$field] = $conditions;
    }
    return $changed;
}

/**
 * Initialize and verify the explicit default when the live graph is empty.
 *
 * @return int|WP_Error Zero means no bootstrap was needed.
 */
function divi_tb_initialize_default(int $container_id, string $requested_default)
{
    if ($requested_default === '1' || divi_tb_live_templates($container_id) !== []) {
        return 0;
    }
    $template_id = divi_tb_bootstrap_default($container_id);
    if ($template_id instanceof WP_Error) {
        return $template_id;
    }
    divi_tb_flush($container_id);
    $expected = divi_tb_default_template_payload();
    $expected['id'] = $template_id;
    $verified = divi_tb_verify_template($container_id, $expected, []);
    if ($verified === null) {
        $verified = divi_tb_verify_single_default($container_id, $template_id);
    }
    if ($verified instanceof WP_Error) {
        divi_tb_rollback_created($container_id, [$template_id], []);
        return $verified;
    }
    return $template_id;
}

/**
 * @param array<string, int> $layouts
 * @param array{use_on: list<string>, exclude_from: list<string>} $conditions
 * @return array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * }
 */
function divi_tb_new_template_payload(
    string $title,
    string $default,
    string $enabled,
    array $layouts,
    array $conditions,
): array {
    return [
        'id' => 0,
        'title' => $title,
        'autogenerated_title' => $title === '' ? '1' : '0',
        'default' => $default,
        'enabled' => $enabled,
        'layouts' => divi_tb_layout_graph($layouts),
        'use_on' => $conditions['use_on'],
        'exclude_from' => $conditions['exclude_from'],
    ];
}

/**
 * Store, attach, reconcile defaults, and verify one new template.
 *
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $payload
 * @param list<string> $areas
 * @return int|WP_Error
 */
function divi_tb_persist_new_template(int $container_id, array $payload, array $areas)
{
    $template_id = divi_tb_store_template($container_id, $payload);
    if ($template_id instanceof WP_Error) {
        return $template_id;
    }
    $payload['id'] = $template_id;
    if (!divi_tb_attach_template($container_id, $template_id)) {
        divi_tb_rollback_created($container_id, [$template_id], []);
        return divi_error(
            'create_attach_failed',
            message: 'Could not attach the template to the live Theme Builder container.',
            status: 500,
        );
    }
    divi_tb_flush($container_id);

    $demoted = [];
    if ($payload['default'] === '1') {
        $demoted = divi_tb_demote_other_defaults($container_id, $template_id);
        if ($demoted instanceof WP_Error) {
            divi_tb_rollback_created($container_id, [$template_id], []);
            return $demoted;
        }
    }
    divi_tb_flush($container_id);

    $verified = divi_tb_verify_template($container_id, $payload, $areas);
    if ($verified === null && $payload['default'] === '1') {
        $verified = divi_tb_verify_single_default($container_id, $template_id);
    }
    if ($verified instanceof WP_Error) {
        $rollback = divi_tb_restore_default_flags($container_id, $demoted);
        divi_tb_rollback_created($container_id, [$template_id], []);
        return divi_tb_error_with_rollback($verified, $rollback);
    }
    return $template_id;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_create_theme_builder_template(array $input)
{
    $unavailable = divi_tb_available_or_error();
    if ($unavailable instanceof WP_Error) {
        return $unavailable;
    }
    $forbidden = divi_tb_write_permission_error();
    if ($forbidden instanceof WP_Error) {
        return $forbidden;
    }

    $areas = divi_tb_areas_or_error($input);
    if ($areas instanceof WP_Error) {
        return $areas;
    }

    $conditions = divi_tb_create_conditions_or_error($input);
    if ($conditions instanceof WP_Error) {
        return $conditions;
    }

    $container_id = divi_tb_container_id();
    if ($container_id <= 0) {
        return divi_error('create_failed', message: 'Could not open the live Theme Builder container.', status: 500);
    }
    $unprepared = divi_tb_prepare_live_graph($container_id);
    if ($unprepared instanceof WP_Error) {
        return $unprepared;
    }

    $title = trim(divi_string_arg($input, key: 'title'));
    $layouts = divi_tb_create_layouts($areas);
    if ($layouts instanceof WP_Error) {
        return $layouts;
    }

    $default = divi_tb_input_bool($input, key: 'default') ?? false ? '1' : '0';
    $bootstrap_default_id = divi_tb_initialize_default($container_id, $default);
    if ($bootstrap_default_id instanceof WP_Error) {
        divi_tb_rollback_created($container_id, [], array_values($layouts));
        return $bootstrap_default_id;
    }

    $enabled = divi_tb_input_bool($input, key: 'enabled') ?? true ? '1' : '0';
    $payload = divi_tb_new_template_payload($title, $default, $enabled, $layouts, $conditions);
    $template_id = divi_tb_persist_new_template($container_id, $payload, $areas);
    if ($template_id instanceof WP_Error) {
        divi_tb_rollback_created($container_id, [$bootstrap_default_id], array_values($layouts));
        return $template_id;
    }
    if ($bootstrap_default_id > 0) {
        $bootstrap_verified = divi_tb_verify_single_default($container_id, $bootstrap_default_id);
        if ($bootstrap_verified instanceof WP_Error) {
            divi_tb_rollback_created($container_id, [$template_id, $bootstrap_default_id], array_values($layouts));
            return $bootstrap_verified;
        }
    }

    return [
        'template_id' => $template_id,
        'layouts' => $layouts,
        'note' => 'Build each layout id with novamira/divi-set-content (pass the layout id as the post).',
    ];
}

/**
 * Apply requested assignment fields to a complete template snapshot.
 *
 * @param array<string, mixed> $input
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $snapshot
 * @param array<string, list<string>> $conditions
 * @return array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * }|WP_Error
 */
function divi_tb_assignment_payload_or_error(array $input, array $snapshot, array $conditions)
{
    $payload = $snapshot;
    if (isset($conditions['use_on'])) {
        $payload['use_on'] = $conditions['use_on'];
    }
    if (isset($conditions['exclude_from'])) {
        $payload['exclude_from'] = $conditions['exclude_from'];
    }
    if (array_key_exists('enabled', $input)) {
        $payload['enabled'] = divi_tb_input_bool($input, key: 'enabled') ?? true ? '1' : '0';
    }
    if (!array_key_exists('use_on', $input) || $payload['use_on'] !== [] || $payload['default'] === '1') {
        return $payload;
    }
    return divi_error(
        'inert_template',
        message: 'A non-default Theme Builder template requires at least one "use_on" condition. '
        . 'Choose one with novamira/divi-list-theme-builder-conditions.',
        status: 422,
    );
}

/**
 * Assignment fields present in one set request.
 *
 * @param array<string, mixed> $input
 * @return list<string>
 */
function divi_tb_changed_assignment_fields(array $input): array
{
    $fields = [];
    foreach (['use_on', 'exclude_from', 'enabled'] as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $fields[] = $field;
    }
    return $fields;
}

/**
 * Whether the owned assignment metas match one complete template payload.
 *
 * @param array<array-key, mixed> $payload
 * @param list<string> $fields
 */
function divi_tb_assignment_metas_match(int $template_id, array $payload, array $fields): bool
{
    foreach ($fields as $field) {
        if ($field === 'enabled') {
            if (
                get_post_meta(post_id: $template_id, key: '_et_enabled', single: true) !== divi_tb_array_string(
                    values: $payload,
                    key: 'enabled',
                )
            ) {
                return false;
            }
            continue;
        }
        $stored = divi_tb_string_list_from_mixed(get_post_meta(
            post_id: $template_id,
            key: '_et_' . $field,
            single: false,
        ));
        if ($stored !== divi_tb_string_list_from_mixed(divi_tb_array_value(values: $payload, key: $field))) {
            return false;
        }
    }
    return true;
}

/**
 * Write only the assignment metas owned by this ability.
 *
 * @param array<array-key, mixed> $payload
 * @param list<string> $fields
 */
function divi_tb_write_assignment_fields(int $template_id, array $payload, array $fields): ?WP_Error
{
    $invalid_target = divi_tb_template_write_target_error($template_id);
    if ($invalid_target instanceof WP_Error) {
        return $invalid_target;
    }
    foreach ($fields as $field) {
        if ($field === 'enabled') {
            update_post_meta(
                post_id: $template_id,
                meta_key: '_et_enabled',
                meta_value: divi_tb_array_string(values: $payload, key: 'enabled'),
            );
            continue;
        }
        $conditions = divi_tb_string_list_from_mixed(divi_tb_array_value(values: $payload, key: $field));
        if (!divi_tb_write_conditions($template_id, '_et_' . $field, $conditions)) {
            return divi_error(
                'assignment_write_failed',
                message: sprintf('Could not write the Theme Builder %s values.', $field),
                status: 500,
            );
        }
    }
    return divi_tb_assignment_metas_match($template_id, $payload, $fields)
        ? null
        : divi_error(
            'assignment_write_failed',
            message: 'The Theme Builder assignment metas did not retain the requested values.',
            status: 500,
        );
}

/**
 * Restore only assignment fields that this ability attempted to change.
 *
 * @param array<array-key, mixed> $snapshot
 * @param list<string> $fields
 */
function divi_tb_restore_assignment_fields(int $container_id, array $snapshot, array $fields): string
{
    $template_id = divi_tb_array_int(values: $snapshot, key: 'id');
    $restored = divi_tb_write_assignment_fields($template_id, $snapshot, $fields);
    divi_tb_flush($container_id);
    return $restored instanceof WP_Error ? 'failed' : 'restored';
}

/**
 * Persist and verify an assignment edit without rewriting layout bindings.
 *
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $snapshot
 * @param array{
 *   id: int,
 *   title: string,
 *   autogenerated_title: string,
 *   default: string,
 *   enabled: string,
 *   layouts: array{header: array{id: int, enabled: bool}, body: array{id: int, enabled: bool}, footer: array{id: int, enabled: bool}},
 *   use_on: list<string>,
 *   exclude_from: list<string>
 * } $payload
 * @param list<string> $fields
 * @return array<array-key, mixed>|WP_Error
 */
function divi_tb_persist_assignment(int $container_id, array $snapshot, array $payload, array $fields)
{
    $template_id = divi_tb_array_int(values: $snapshot, key: 'id');
    $written = divi_tb_write_assignment_fields($template_id, $payload, $fields);
    if ($written instanceof WP_Error) {
        $rollback = divi_tb_restore_assignment_fields($container_id, $snapshot, $fields);
        return divi_tb_error_with_rollback($written, $rollback);
    }

    divi_tb_flush($container_id);
    $verified = divi_tb_verify_template($container_id, $payload, []);
    if ($verified instanceof WP_Error) {
        $rollback = divi_tb_restore_assignment_fields($container_id, $snapshot, $fields);
        return divi_tb_error_with_rollback($verified, $rollback);
    }

    /** @var mixed $read */
    $read = et_theme_builder_get_template(template_id: $template_id);
    if (is_array($read)) {
        return $read;
    }
    $rollback = divi_tb_restore_assignment_fields($container_id, $snapshot, $fields);
    return divi_tb_error_with_rollback(
        divi_error('persistence_failed', message: 'Divi could not read the updated template.', status: 500),
        $rollback,
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_set_theme_builder_conditions(array $input)
{
    $unavailable = divi_tb_available_or_error();
    if ($unavailable instanceof WP_Error) {
        return $unavailable;
    }
    $forbidden = divi_tb_write_permission_error();
    if ($forbidden instanceof WP_Error) {
        return $forbidden;
    }

    $template_id = divi_tb_template_or_error(divi_int_arg_or_null($input, key: 'template_id'));
    if ($template_id instanceof WP_Error) {
        return $template_id;
    }
    $invalid_target = divi_tb_template_write_target_error($template_id);
    if ($invalid_target instanceof WP_Error) {
        return $invalid_target;
    }

    $conditions = divi_tb_changed_conditions_or_error($input);
    if ($conditions instanceof WP_Error) {
        return $conditions;
    }

    if ($conditions === [] && !array_key_exists('enabled', $input)) {
        return divi_error('invalid_input', message: 'Provide "use_on", "exclude_from", and/or "enabled" to change.');
    }

    $container_id = divi_tb_container_id();
    $unprepared = divi_tb_prepare_live_graph($container_id);
    if ($unprepared instanceof WP_Error) {
        return $unprepared;
    }
    $current = divi_tb_find_live_template($container_id, $template_id);
    if ($current === null) {
        return divi_error(
            'not_attached',
            message: sprintf('Theme Builder template %d is not attached to the live Theme Builder.', $template_id),
            status: 404,
        );
    }

    $snapshot = divi_tb_template_to_store_shape($current);
    $payload = divi_tb_assignment_payload_or_error($input, $snapshot, $conditions);
    if ($payload instanceof WP_Error) {
        return $payload;
    }

    $fields = divi_tb_changed_assignment_fields($input);
    $read = divi_tb_persist_assignment($container_id, $snapshot, $payload, $fields);
    if ($read instanceof WP_Error) {
        return $read;
    }

    return [
        'template_id' => $template_id,
        'use_on' => divi_tb_string_list_from_mixed(value: divi_tb_array_value(values: $read, key: 'use_on')),
        'exclude_from' => divi_tb_string_list_from_mixed(value: divi_tb_array_value(
            values: $read,
            key: 'exclude_from',
        )),
        'enabled' => divi_tb_array_bool(values: $read, key: 'enabled'),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_delete_theme_builder_template(array $input)
{
    $unavailable = divi_tb_available_or_error();
    if ($unavailable instanceof WP_Error) {
        return $unavailable;
    }

    $template_id = divi_tb_template_or_error(divi_int_arg_or_null($input, key: 'template_id'));
    if ($template_id instanceof WP_Error) {
        return $template_id;
    }

    $layout_types = divi_tb_layout_post_types();
    $layout_ids = [];
    foreach ($layout_types as $area => $expected_type) {
        $layout_id = (int) get_post_meta($template_id, '_et_' . $area . '_layout_id', single: true);
        // Only delete a post that is actually a layout of the expected type: a
        // corrupted/misused meta pointing at a regular page must never cause
        // that page to be force-deleted.
        if ($layout_id > 0 && get_post_type($layout_id) === $expected_type) {
            $layout_ids[] = $layout_id;
        }
    }

    $container_id = divi_tb_container_id();
    $unprepared = divi_tb_prepare_live_graph($container_id);
    if ($unprepared instanceof WP_Error) {
        return $unprepared;
    }
    delete_post_meta($container_id, meta_key: '_et_template', meta_value: $template_id);
    wp_delete_post($template_id, force_delete: true);

    if (divi_tb_input_bool($input, key: 'delete_layouts') ?? true) {
        foreach ($layout_ids as $layout_id) {
            wp_delete_post($layout_id, force_delete: true);
        }
    }

    divi_tb_flush($container_id);

    return ['template_id' => $template_id, 'deleted' => true];
}
