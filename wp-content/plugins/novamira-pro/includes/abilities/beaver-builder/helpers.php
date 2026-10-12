<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

const BB_DEFAULT_LIST_LIMIT = 50;

const BB_MAX_LIST_LIMIT = 500;

const BB_MAX_WRITE_NODES = 2000;

/**
 * Beaver Builder's raw-code module slug. The HTML module ("Display raw HTML
 * code.") is the only core module that embeds arbitrary markup/scripts; writes
 * gate it behind an explicit `allow_html_module` opt-in so an agent does not
 * reach for it as a layout/styling/content shortcut (Hard Rule #1).
 */
const BB_HTML_MODULE_SLUG = 'html';

/**
 * @param array<string, mixed> $data
 */
function bb_error(string $code, string $message, array $data = []): WP_Error
{
    return new WP_Error(code: $code, message: $message, data: $data);
}

/**
 * Whether a prepared node is a Beaver Builder HTML (raw-code) module. Module
 * nodes store their resolved slug in `settings.type`; by this point
 * `bb_node_from_input()` has already normalized the requested module to its
 * canonical base slug, so a direct compare against BB_HTML_MODULE_SLUG catches
 * both the `module` input and a `settings.type` input.
 */
function bb_is_html_module_node(object $node): bool
{
    if (bb_property_string(object: $node, property: 'type') !== 'module') {
        return false;
    }

    return bb_node_module_slug($node) === BB_HTML_MODULE_SLUG;
}

/**
 * The gate rejection returned when an HTML module would be written without the
 * explicit `allow_html_module` opt-in. `$node_ids` names the offending node(s)
 * so a bulk set-layout error points at the exact tree element to fix.
 *
 * @param list<string> $node_ids
 */
function bb_html_gate_error(array $node_ids = []): WP_Error
{
    $where = $node_ids === [] ? '' : sprintf(' Offending node id(s): %s.', implode(', ', $node_ids));

    return bb_error(
        code: 'beaver_builder_html_module_not_allowed',
        message: sprintf(
            'The Beaver Builder HTML module is gated. It embeds raw HTML/code and is ONLY for a real code snippet the user explicitly asked to embed — never for layout, structure, styling, CSS, a `<style>` block, or page content. Build with native modules (discover them with list-modules / get-module), or, if this is genuinely a requested code embed, retry with `allow_html_module: true`.%s',
            $where,
        ),
        data: ['status' => 422, 'module' => BB_HTML_MODULE_SLUG, 'node_ids' => $node_ids],
    );
}

/**
 * Collect the ids of every HTML module node in a prepared layout map.
 *
 * @param array<string, object> $data
 * @return list<string>
 */
function bb_html_module_node_ids(array $data): array
{
    $ids = [];
    foreach ($data as $node_id => $node) {
        if (!bb_is_html_module_node($node)) {
            continue;
        }
        $ids[] = $node_id;
    }
    return $ids;
}

/**
 * Ensure Beaver Builder modules are registered before discovery reads the catalog.
 */
function bb_ensure_modules_loaded(): void
{
    if (!class_exists('FLBuilderModel')) {
        return;
    }

    \FLBuilderModel::load_modules();
}

/**
 * @return list<string>
 */
function bb_supported_post_types(): array
{
    if (!class_exists('FLBuilderModel')) {
        return [];
    }

    $post_types = [];
    foreach (\FLBuilderModel::get_post_types() as $post_type) {
        if ($post_type === '') {
            continue;
        }
        $post_types[$post_type] = true;
    }
    return array_keys($post_types);
}

/**
 * @return list<string>
 */
function bb_enabled_modules(): array
{
    if (!class_exists('FLBuilderModel')) {
        return [];
    }

    $modules = [];
    foreach (\FLBuilderModel::get_enabled_modules() as $slug) {
        if ($slug === '') {
            continue;
        }
        $modules[$slug] = true;
    }
    return array_keys($modules);
}

/**
 * @return list<array{
 *     slug: string,
 *     module: string,
 *     name: string,
 *     category: string,
 *     group: string,
 *     description: string,
 *     enabled: bool,
 *     alias: bool
 * }>
 */
function bb_module_catalog(string $scope = 'enabled'): array
{
    if (!class_exists('FLBuilderModel')) {
        return [];
    }

    bb_ensure_modules_loaded();

    $include_disabled = $scope === 'all';

    $enabled = array_fill_keys(keys: bb_enabled_modules(), value: true);
    $rows = [];
    foreach (\FLBuilderModel::get_uncategorized_modules($include_disabled) as $module) {
        $base_slug = bb_property_string(object: $module, property: 'slug');
        $alias_slug = bb_property_string(object: $module, property: 'alias');
        $is_alias = bb_property_bool(object: $module, property: 'isAlias');
        $slug = $is_alias && $alias_slug !== '' ? $alias_slug : $base_slug;
        if ($slug === '') {
            continue;
        }

        $group = bb_property_list_string(object: $module, property: 'group');
        $rows[] = [
            'slug' => $slug,
            'module' => $base_slug,
            'name' => bb_property_string(object: $module, property: 'name'),
            'category' => bb_property_string(object: $module, property: 'category'),
            'group' => $group,
            'description' => bb_property_string(object: $module, property: 'description'),
            'enabled' => isset($enabled[$base_slug]) || isset($enabled[$slug]),
            'alias' => $is_alias,
        ];
    }

    usort($rows, static fn(array $a, array $b): int => strcasecmp(
        $a['name'] !== '' ? $a['name'] : $a['slug'],
        $b['name'] !== '' ? $b['name'] : $b['slug'],
    ));

    return $rows;
}

function bb_resolve_module_slug(string $slug, string $scope = 'enabled'): string
{
    if ($slug === '') {
        return '';
    }

    foreach (bb_module_catalog(scope: $scope) as $row) {
        if ($row['slug'] === $slug || $row['module'] === $slug) {
            return $row['module'];
        }
    }
    return '';
}

function bb_module_slug_known(string $slug, string $scope = 'enabled'): bool
{
    return bb_resolve_module_slug(slug: $slug, scope: $scope) !== '';
}

/**
 * The registered catalog row for a resolved base module slug, or null.
 *
 * @return array{
 *     slug: string,
 *     module: string,
 *     name: string,
 *     category: string,
 *     group: string,
 *     description: string,
 *     enabled: bool,
 *     alias: bool
 * }|null
 */
function bb_module_catalog_row(string $module, string $scope = 'all'): ?array
{
    foreach (bb_module_catalog(scope: $scope) as $row) {
        if ($row['module'] === $module || $row['slug'] === $module) {
            return $row;
        }
    }
    return null;
}

/**
 * The registered module instance for a base slug, or null. Beaver keeps these in
 * FLBuilderModel::$modules keyed by base slug after load_modules().
 */
function bb_registered_module(string $module): ?object
{
    if (!class_exists('FLBuilderModel')) {
        return null;
    }

    bb_ensure_modules_loaded();
    $instance = \FLBuilderModel::$modules[$module] ?? null;
    return is_object($instance) ? $instance : null;
}

/**
 * The flat default settings (key => default value) for a module.
 *
 * @return array<string, mixed>
 */
function bb_module_default_settings(string $module): array
{
    if (!class_exists('FLBuilderModel') || !method_exists('FLBuilderModel', 'get_module_defaults')) {
        return [];
    }

    /** @var mixed $defaults */
    $defaults = \FLBuilderModel::get_module_defaults($module);
    if (is_object($defaults)) {
        $defaults = get_object_vars($defaults);
    }
    if (!is_array($defaults)) {
        return [];
    }

    $normalized = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($defaults as $key => $value) {
        $normalized[(string) $key] = $value;
    }
    return $normalized;
}

/**
 * Walk a registered module form (tabs -> sections -> fields) into a flat,
 * bounded list of typed field descriptors. Beaver forms are arrays shaped as
 * `[$tab_key => ['title' => ..., 'sections' => [$section_key => ['title' => ...,
 * 'fields' => [$field_key => ['type' => ..., 'label' => ..., ...]]]]]]`.
 *
 * Progressive disclosure: the optional `$filters` narrow the walk by field
 * `search` (key OR label substring, case-insensitive), `tab`, and `section`
 * (AND-combined). Filtering happens BEFORE the `$max_fields` cap, so `total`
 * and `truncated` reflect the post-filter match count, not the whole form.
 *
 * @param array{search?: string, tab?: string, section?: string} $filters
 * @return array{fields: list<array<string, mixed>>, total: int, truncated: bool}
 */
function bb_module_form_fields(
    object $module,
    int $max_fields,
    int $max_options,
    int $max_field_bytes,
    array $filters = [],
): array {
    $form = bb_object_property(object: $module, property: 'form');
    if (!is_array($form)) {
        return ['fields' => [], 'total' => 0, 'truncated' => false];
    }

    return bb_collect_form_fields(
        tabs: $form,
        max_fields: $max_fields,
        max_options: $max_options,
        max_field_bytes: $max_field_bytes,
        filters: $filters,
    );
}

/**
 * Walk a Beaver form tab-map (`tab_key => {title, sections}`) into the flat,
 * filtered, bounded field-row list shared by module schemas (get-module) and
 * the row/column settings forms (get-structure-schema). A module's `form` is
 * already a bare tab-map; a settings form wraps it as `{title, tabs:{...}}`, so
 * those callers pass the `tabs` sub-array.
 *
 * @param array<array-key, mixed> $tabs
 * @param array{search?: string, tab?: string, section?: string} $filters
 * @return array{fields: list<array<string, mixed>>, total: int, truncated: bool}
 */
function bb_collect_form_fields(
    array $tabs,
    int $max_fields,
    int $max_options,
    int $max_field_bytes,
    array $filters = [],
): array {
    $caps = ['options' => $max_options, 'bytes' => $max_field_bytes];
    $fields = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($tabs as $tab_key => $tab) {
        if (!is_array($tab)) {
            continue;
        }
        bb_collect_tab_fields(
            tab: $tab,
            location: ['tab' => (string) $tab_key, 'tab_label' => bb_array_string(array: $tab, key: 'title')],
            caps: $caps,
            filters: $filters,
            fields: $fields,
        );
    }

    $total = count($fields);
    $truncated = $total > $max_fields;
    if ($truncated) {
        $fields = array_slice(array: $fields, offset: 0, length: $max_fields);
    }

    return ['fields' => $fields, 'total' => $total, 'truncated' => $truncated];
}

/**
 * Walk a structural settings form (row or column) into the same field-row shape
 * get-module returns. Beaver keeps these in FLBuilderModel's settings forms
 * (`get_settings_form('row'|'col')`), wrapped as `{title, tabs:{...}}`.
 *
 * @param array{search?: string, tab?: string, section?: string} $filters
 * @return array{fields: list<array<string, mixed>>, total: int, truncated: bool}
 */
function bb_settings_form_fields(
    string $form_id,
    int $max_fields,
    int $max_options,
    int $max_field_bytes,
    array $filters = [],
): array {
    if (!class_exists('FLBuilderModel') || !method_exists('FLBuilderModel', 'get_settings_form')) {
        return ['fields' => [], 'total' => 0, 'truncated' => false];
    }
    /** @var mixed $form */
    $form = \FLBuilderModel::get_settings_form($form_id);
    $tabs = is_array($form) && isset($form['tabs']) && is_array($form['tabs']) ? $form['tabs'] : [];

    return bb_collect_form_fields(
        tabs: $tabs,
        max_fields: $max_fields,
        max_options: $max_options,
        max_field_bytes: $max_field_bytes,
        filters: $filters,
    );
}

/**
 * @param array<array-key, mixed> $tab
 * @param array{tab: string, tab_label: string} $location
 * @param array{options: int, bytes: int} $caps
 * @param array{search?: string, tab?: string, section?: string} $filters
 * @param list<array<string, mixed>> $fields
 */
function bb_collect_tab_fields(array $tab, array $location, array $caps, array $filters, array &$fields): void
{
    if (isset($filters['tab']) && $location['tab'] !== $filters['tab']) {
        return;
    }

    /** @var mixed $sections */
    $sections = $tab['sections'] ?? null;
    if (!is_array($sections)) {
        return;
    }

    // @mago-expect analysis:mixed-assignment
    foreach ($sections as $section_key => $section) {
        if (!is_array($section)) {
            continue;
        }
        if (isset($filters['section']) && (string) $section_key !== $filters['section']) {
            continue;
        }
        /** @var mixed $section_fields */
        $section_fields = $section['fields'] ?? null;
        if (!is_array($section_fields)) {
            continue;
        }
        $context = $location
        + [
            'section' => (string) $section_key,
            'section_label' => bb_array_string(array: $section, key: 'title'),
        ];
        // @mago-expect analysis:mixed-assignment
        foreach ($section_fields as $field_key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = (string) $field_key;
            if (!bb_module_field_matches_search(key: $key, field: $field, search: $filters['search'] ?? '')) {
                continue;
            }
            $fields[] = bb_module_form_field(key: $key, field: $field, location: $context, caps: $caps);
        }
    }
}

/**
 * Whether a field passes the case-insensitive `search` filter, matching on the
 * field key OR its label. An empty search matches every field.
 *
 * @param array<array-key, mixed> $field
 */
function bb_module_field_matches_search(string $key, array $field, string $search): bool
{
    if ($search === '') {
        return true;
    }

    $needle = strtolower($search);
    if (str_contains(strtolower($key), $needle)) {
        return true;
    }

    return str_contains(strtolower(bb_array_string(array: $field, key: 'label')), $needle);
}

/**
 * @param array<array-key, mixed> $field
 * @param array{tab: string, tab_label: string, section: string, section_label: string} $location
 * @param array{options: int, bytes: int} $caps
 * @return array<string, mixed>
 */
function bb_module_form_field(string $key, array $field, array $location, array $caps): array
{
    $row = [
        'key' => $key,
        'type' => bb_array_string(array: $field, key: 'type'),
        'label' => bb_array_string(array: $field, key: 'label'),
        'tab' => $location['tab'],
        'section' => $location['section'],
    ];
    if ($location['tab_label'] !== '') {
        $row['tab_label'] = $location['tab_label'];
    }
    if ($location['section_label'] !== '') {
        $row['section_label'] = $location['section_label'];
    }
    if (array_key_exists('default', $field)) {
        $row['default'] = bb_jsonable(value: $field['default'], max_field_bytes: $caps['bytes']);
    }
    if (isset($field['description']) && is_string($field['description']) && $field['description'] !== '') {
        $row['description'] = bb_jsonable(value: $field['description'], max_field_bytes: $caps['bytes']);
    }

    $options = bb_module_form_field_options(field: $field, max_options: $caps['options']);
    if ($options['options'] !== []) {
        $row['options'] = $options['options'];
        $row['options_total'] = $options['total'];
        if ($options['truncated']) {
            $row['options_truncated'] = true;
        }
    }

    return $row;
}

/**
 * Collect the selectable option keys for a field. Beaver options are usually a
 * flat `key => label` map; some fields (e.g. box `aspect_ratio`) group options
 * as `key => ['label' => ..., 'options' => [key => label]]`, which we flatten.
 *
 * @param array<array-key, mixed> $field
 * @return array{options: list<string>, total: int, truncated: bool}
 */
function bb_module_form_field_options(array $field, int $max_options): array
{
    /** @var mixed $raw */
    $raw = $field['options'] ?? null;
    if (!is_array($raw)) {
        return ['options' => [], 'total' => 0, 'truncated' => false];
    }

    $keys = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $key => $value) {
        if (is_array($value) && isset($value['options']) && is_array($value['options'])) {
            foreach (array_keys($value['options']) as $nested_key) {
                $keys[(string) $nested_key] = true;
            }
            continue;
        }
        $keys[(string) $key] = true;
    }

    $options = array_keys($keys);
    $total = count($options);
    $truncated = $total > $max_options;
    if ($truncated) {
        $options = array_slice(array: $options, offset: 0, length: $max_options);
    }

    return ['options' => $options, 'total' => $total, 'truncated' => $truncated];
}

/**
 * @param array<array-key, mixed> $array
 */
function bb_array_string(array $array, string $key): string
{
    /** @var mixed $value */
    $value = $array[$key] ?? null;
    return is_string($value) ? $value : '';
}

/**
 * Byte-bounded flat default-settings map for the response.
 *
 * @param array<string, mixed> $defaults
 * @return array{defaults: array<string, mixed>, total: int, truncated: bool}
 */
function bb_bounded_defaults(array $defaults, int $max_keys, int $max_field_bytes): array
{
    $total = count($defaults);
    $truncated = $total > $max_keys;
    if ($truncated) {
        $defaults = array_slice(array: $defaults, offset: 0, length: $max_keys, preserve_keys: true);
    }

    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($defaults as $key => $value) {
        $out[$key] = bb_jsonable(value: $value, max_field_bytes: $max_field_bytes);
    }

    return ['defaults' => $out, 'total' => $total, 'truncated' => $truncated];
}

function bb_object_property(object $object, string $property): array|bool|float|int|object|string|null
{
    $values = get_object_vars($object);
    if (!array_key_exists($property, $values)) {
        return null;
    }

    // @mago-expect analysis:mixed-assignment
    $value = $values[$property];
    if (is_scalar($value) || is_array($value) || is_object($value) || $value === null) {
        return $value;
    }
    return null;
}

function bb_property_string(object $object, string $property): string
{
    $value = bb_object_property(object: $object, property: $property);
    return is_scalar($value) ? (string) $value : '';
}

function bb_property_bool(object $object, string $property): bool
{
    $value = bb_object_property(object: $object, property: $property);
    return $value === true || $value === 1 || $value === '1';
}

function bb_property_list_string(object $object, string $property): string
{
    $value = bb_object_property(object: $object, property: $property);
    if (is_array($value)) {
        $parts = [];
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $part) {
            if (!is_string($part) || $part === '') {
                continue;
            }
            $parts[] = $part;
        }
        return implode(',', $parts);
    }

    return is_scalar($value) ? (string) $value : '';
}

/**
 * @param mixed $value
 */
function bb_bool_arg($value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true';
}

/**
 * @param array<string, mixed> $input
 */
function bb_int_arg(array $input, string $key, int $default, int $min, int $max): int
{
    /** @var mixed $raw */
    $raw = $input[$key] ?? null;
    $value = $default;
    if (is_int($raw)) {
        $value = $raw;
    }
    if (is_string($raw) && ctype_digit($raw)) {
        $value = (int) $raw;
    }

    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

/**
 * @param mixed $id
 */
function bb_post_or_error($id): WP_Post|WP_Error
{
    $post_id = null;
    if (is_int($id)) {
        $post_id = $id;
    }
    if (is_string($id) && ctype_digit($id)) {
        $post_id = (int) $id;
    }

    if ($post_id === null) {
        return bb_error(
            code: 'beaver_builder_invalid_post',
            message: 'Pass a numeric post id in `post` or `post_id`.',
            data: [
                'status' => 400,
            ],
        );
    }

    // @mago-expect analysis:mixed-assignment
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return bb_error(code: 'beaver_builder_post_not_found', message: 'The requested post does not exist.', data: [
            'status' => 404,
        ]);
    }
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft' || $post->post_status === 'trash') {
        return bb_error(
            code: 'beaver_builder_post_not_editable',
            message: 'Trash, revisions, and auto-drafts are not valid Beaver Builder layout targets.',
            data: ['status' => 422],
        );
    }

    return $post;
}

function bb_layout_enabled(int $post_id): bool
{
    /** @var mixed $enabled */
    $enabled = get_post_meta(post_id: $post_id, key: '_fl_builder_enabled', single: true);
    return $enabled === true || $enabled === 1 || $enabled === '1';
}

function bb_write_post_error(WP_Post $post): ?WP_Error
{
    $supported = bb_supported_post_types();
    if ($supported !== [] && !in_array($post->post_type, $supported, strict: true)) {
        return bb_error(
            code: 'beaver_builder_unsupported_post_type',
            message: sprintf('Post type `%s` is not enabled for Beaver Builder on this site.', $post->post_type),
            data: ['status' => 422, 'supported_post_types' => $supported],
        );
    }

    return null;
}

/**
 * @param array<string, mixed> $input
 * @return list<string>
 */
function bb_target_statuses(array $input): array
{
    $raw = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'both';
    return match ($raw) {
        'published' => ['published'],
        'draft' => ['draft'],
        default => ['published', 'draft'],
    };
}

/**
 * @param mixed $value
 */
function bb_string_arg($value): string
{
    return is_string($value) ? $value : '';
}

/**
 * @param mixed $value
 */
function bb_nullable_string_arg($value): ?string
{
    if ($value === null) {
        return null;
    }
    return is_string($value) ? $value : null;
}

/**
 * @return array<string, object>
 */
function bb_layout_data(int $post_id, string $status): array
{
    if (!class_exists('FLBuilderModel')) {
        return [];
    }

    $data = [];
    foreach (\FLBuilderModel::get_layout_data($status, $post_id) as $node_id => $node) {
        if ($node_id === '') {
            continue;
        }
        $data[$node_id] = $node;
    }
    return $data;
}

/**
 * @return array<string, \stdClass>
 */
function bb_writable_layout_data(int $post_id, string $status): array
{
    $data = [];
    foreach (bb_layout_data(post_id: $post_id, status: $status) as $node_id => $node) {
        $data[$node_id] = bb_clone_node($node);
    }
    return $data;
}

/**
 * @return array<string, \stdClass>
 */
function bb_layout_data_for_write(int $post_id, string $status): array
{
    $data = bb_writable_layout_data(post_id: $post_id, status: $status);
    if ($status === 'draft' && $data === []) {
        return bb_writable_layout_data(post_id: $post_id, status: 'published');
    }
    return $data;
}

function bb_clone_node(object $node): \stdClass
{
    $clone = new \stdClass();
    // @mago-expect analysis:mixed-assignment
    foreach (get_object_vars($node) as $key => $value) {
        $property = (string) $key;
        // @mago-expect analysis:string-member-selector
        $clone->{$property} = bb_deep_clone_value($value);
    }

    if (!is_object(bb_object_property(object: $clone, property: 'settings'))) {
        $clone->settings = new \stdClass();
    }

    return $clone;
}

function bb_clone_settings(object $settings): \stdClass
{
    return bb_settings_from_object($settings);
}

/**
 * @param mixed $value
 * @return mixed
 */
function bb_deep_clone_value($value)
{
    if (is_object($value)) {
        $copy = new \stdClass();
        // @mago-expect analysis:mixed-assignment
        foreach (get_object_vars($value) as $key => $item) {
            $property = (string) $key;
            // @mago-expect analysis:string-member-selector
            $copy->{$property} = bb_deep_clone_value($item);
        }
        return $copy;
    }

    if (is_array($value)) {
        $copy = [];
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $key => $item) {
            $copy[$key] = bb_deep_clone_value($item);
        }
        return $copy;
    }

    return $value;
}

/**
 * @param array{id: string, type: string, parent: string, position: int, settings: \stdClass, global?: bool, dynamic?: bool} $parts
 */
function bb_node_from_parts(array $parts): \stdClass
{
    $node = new \stdClass();
    $node->node = $parts['id'];
    $node->type = $parts['type'];
    $node->parent = $parts['parent'] === '' ? null : $parts['parent'];
    $node->position = $parts['position'];
    $node->settings = bb_clone_settings($parts['settings']);
    $node->global = $parts['global'] ?? false;
    $node->dynamic = $parts['dynamic'] ?? false;
    return $node;
}

/**
 * @param mixed $value
 */
// @mago-expect lint:halstead
function bb_settings_from_input($value): \stdClass
{
    if ($value instanceof \stdClass) {
        return bb_clone_settings($value);
    }
    if (is_array($value)) {
        return (object) $value;
    }
    if (is_object($value)) {
        return bb_settings_from_object($value);
    }
    return new \stdClass();
}

function bb_settings_from_object(object $value): \stdClass
{
    $settings = new \stdClass();
    // @mago-expect analysis:mixed-assignment
    foreach (get_object_vars($value) as $key => $item) {
        $property = (string) $key;
        // @mago-expect analysis:string-member-selector
        $settings->{$property} = bb_deep_clone_value($item);
    }
    return $settings;
}

/**
 * @param array<string, mixed> $input
 */
function bb_node_from_input(array $input, ?string $forced_id = null): \stdClass|WP_Error
{
    $type = bb_node_type_from_input($input);
    if ($type === '') {
        return bb_error(
            code: 'beaver_builder_invalid_node_type',
            message: 'Node type must be one of row, column-group, column, or module.',
            data: ['status' => 400],
        );
    }

    $settings = bb_settings_from_input($input['settings'] ?? []);
    $module = bb_string_arg($input['module'] ?? '');
    if ($type === 'module') {
        $requested_module = $module !== '' ? $module : bb_property_string(object: $settings, property: 'type');
        $resolved_module = bb_resolve_module_slug(slug: $requested_module, scope: 'all');
        if ($resolved_module !== '') {
            $settings->type = $resolved_module;
        }
        if ($resolved_module === '' && $requested_module !== '') {
            $settings->type = $requested_module;
        }
    }

    return bb_node_from_parts([
        'id' => bb_node_id_from_input(input: $input, forced_id: $forced_id),
        'type' => $type,
        'parent' => bb_nullable_string_arg($input['parent'] ?? $input['parent_id'] ?? null) ?? '',
        'position' => bb_node_position_from_input($input),
        'settings' => $settings,
        // `global`/`dynamic` are NOT read from input: a node is only a real
        // global/dynamic instance when Beaver's template machinery also sets its
        // `template_id` linkage (see apply-template / create-template global).
        // A bare `global=true` is inert and breaks the editor's global UI, so we
        // never set it from a raw flag — they default false here.
    ]);
}

/**
 * @param array<string, mixed> $input
 */
function bb_node_id_from_input(array $input, ?string $forced_id): string
{
    $id = $forced_id ?? bb_string_arg($input['id'] ?? $input['node'] ?? '');
    return $id !== '' ? $id : \FLBuilderModel::generate_node_id();
}

/**
 * @param array<string, mixed> $input
 */
function bb_node_type_from_input(array $input): string
{
    $type = bb_string_arg($input['type'] ?? '');
    return in_array($type, ['row', 'column-group', 'column', 'module'], strict: true) ? $type : '';
}

/**
 * @param array<string, mixed> $input
 */
function bb_node_position_from_input(array $input): int
{
    return is_int($input['position'] ?? null) ? $input['position'] : -1;
}

/**
 * @param list<array<string, mixed>> $nodes
 * @return array<string, \stdClass>|WP_Error
 */
function bb_layout_data_from_input(array $nodes): array|WP_Error
{
    if (count($nodes) > BB_MAX_WRITE_NODES) {
        return bb_error(
            code: 'beaver_builder_layout_too_large',
            message: 'The layout exceeds the node write cap.',
            data: [
                'status' => 413,
                'max_nodes' => BB_MAX_WRITE_NODES,
            ],
        );
    }

    $data = [];
    foreach ($nodes as $input) {
        $node = bb_node_from_input($input);
        if ($node instanceof WP_Error) {
            return $node;
        }
        $node_id = bb_property_string(object: $node, property: 'node');
        if (isset($data[$node_id])) {
            return bb_error(
                code: 'beaver_builder_duplicate_node_id',
                message: sprintf('Duplicate Beaver Builder node id `%s`.', $node_id),
                data: ['status' => 400],
            );
        }
        $data[$node_id] = $node;
    }

    $validation = bb_validate_layout_data(data: $data);
    if ($validation instanceof WP_Error) {
        return $validation;
    }
    bb_reindex_layout_positions($data);
    bb_normalize_column_sizes($data);
    return $data;
}

/**
 * The numeric width (percent) a column node carries, or null when it has no
 * usable `size` (unset, empty string, or non-numeric). A column with no size
 * renders at `width: 0` in Beaver, so this drives bb_normalize_column_sizes().
 */
function bb_column_size_value(\stdClass $node): ?float
{
    $settings = bb_node_settings($node);
    if (!$settings instanceof \stdClass) {
        return null;
    }
    /** @var mixed $size */
    $size = $settings->size ?? null;
    if (is_int($size) || is_float($size)) {
        return (float) $size;
    }
    if (is_string($size)) {
        $trimmed = trim($size);
        if ($trimmed !== '' && is_numeric($trimmed)) {
            return (float) $trimmed;
        }
    }
    return null;
}

/**
 * Default column widths so no column renders collapsed. Beaver paints a column
 * with no `size` at `width: 0` (it disappears, and its modules wrap one word
 * per line), and our node abilities — unlike the Beaver editor — never set one.
 * For each column-group, the columns that lack an explicit size split the space
 * the explicit columns leave free (`100 - sum`) evenly; when the explicit
 * columns already fill the row, the unsized ones fall back to an equal `100/N`
 * share so they stay visible. Idempotent: a column that already carries a
 * numeric size is never touched, so re-running on an existing layout only ever
 * repairs collapsed columns.
 *
 * @param array<string, \stdClass> $data
 */
function bb_normalize_column_sizes(array &$data): void
{
    $columns_by_parent = [];
    foreach ($data as $node_id => $node) {
        if (bb_property_string(object: $node, property: 'type') !== 'column') {
            continue;
        }
        $columns_by_parent[bb_property_string(object: $node, property: 'parent')][] = $node_id;
    }

    foreach ($columns_by_parent as $node_ids) {
        $explicit_sum = 0.0;
        $unsized = [];
        foreach ($node_ids as $node_id) {
            $size = bb_column_size_value($data[$node_id]);
            if ($size === null) {
                $unsized[] = $node_id;
                continue;
            }
            $explicit_sum += $size;
        }
        if ($unsized === []) {
            continue;
        }
        $remaining = 100.0 - $explicit_sum;
        $share = $remaining > 0.0 ? $remaining / count($unsized) : 100.0 / count($node_ids);
        $share = round($share, precision: 4);
        foreach ($unsized as $node_id) {
            $settings = bb_node_settings($data[$node_id]) ?? new \stdClass();
            $settings->size = $share;
            $data[$node_id]->settings = $settings;
        }
    }
}

/**
 * @param array<string, \stdClass> $data
 * @param array<string, true> $preserved_modules
 */
function bb_validate_layout_data(array $data, array $preserved_modules = []): ?WP_Error
{
    foreach ($data as $node_id => $node) {
        if (bb_property_string(object: $node, property: 'node') !== $node_id || $node_id === '') {
            return bb_error(
                code: 'beaver_builder_invalid_node_id',
                message: 'Each node id must be non-empty and match its key.',
                data: [
                    'status' => 400,
                ],
            );
        }

        $parent = bb_property_string(object: $node, property: 'parent');
        if ($parent !== '' && !isset($data[$parent])) {
            return bb_error(
                code: 'beaver_builder_missing_parent',
                message: sprintf('Node `%s` references missing parent `%s`.', $node_id, $parent),
                data: ['status' => 400],
            );
        }

        $error = bb_validate_node_parent(data: $data, node: $node);
        if ($error instanceof WP_Error) {
            return $error;
        }

        if (bb_property_string(object: $node, property: 'type') === 'module') {
            $settings = bb_node_settings($node) ?? new \stdClass();
            $module = bb_property_string(object: $settings, property: 'type');
            $scope = isset($preserved_modules[$node_id]) ? 'all' : 'enabled';
            if ($module === '' || !bb_module_slug_known(slug: $module, scope: $scope)) {
                return bb_error(
                    code: 'beaver_builder_unknown_module',
                    message: sprintf('Module node `%s` uses unknown or disabled module `%s`.', $node_id, $module),
                    data: ['status' => 400],
                );
            }
        }

        if (bb_node_has_cycle(data: $data, node_id: $node_id)) {
            return bb_error(
                code: 'beaver_builder_parent_cycle',
                message: sprintf('Node `%s` creates a parent cycle.', $node_id),
                data: ['status' => 400],
            );
        }
    }
    return null;
}

/**
 * @param array<string, \stdClass> $data
 */
function bb_validate_node_parent(array $data, \stdClass $node): ?WP_Error
{
    $node_id = bb_property_string(object: $node, property: 'node');
    $node_type = bb_property_string(object: $node, property: 'type');
    $parent = bb_property_string(object: $node, property: 'parent');
    $parent_type = $parent !== '' && isset($data[$parent])
        ? bb_property_string(object: $data[$parent], property: 'type')
        : '';
    $parent_accepts_children = $parent !== '' && isset($data[$parent]) && bb_node_accepts_children($data[$parent]);
    $node_accepts_children = $node_type === 'module' && bb_node_accepts_children($node);
    $valid = match ($node_type) {
        'row' => $parent === '',
        'column-group' => $parent_type === 'row' || $parent_type === 'column',
        'column' => $parent_type === 'row' || $parent_type === 'column-group' || $parent_accepts_children,
        'module' => $parent_type === 'column'
            || $parent_accepts_children
            || ($parent_type === 'row' || $parent === '') && $node_accepts_children,
        default => false,
    };

    if ($valid) {
        return null;
    }

    return bb_error(
        code: 'beaver_builder_invalid_parent',
        message: sprintf('Node `%s` of type `%s` cannot use parent `%s`.', $node_id, $node_type, $parent),
        data: ['status' => 400],
    );
}

function bb_node_accepts_children(object $node): bool
{
    if (bb_property_string(object: $node, property: 'type') !== 'module' || !class_exists('FLBuilderModel')) {
        return false;
    }

    bb_ensure_modules_loaded();
    /** @var mixed $module */
    $module = \FLBuilderModel::get_module($node);
    return is_object($module) && method_exists($module, 'accepts_children') && $module->accepts_children() === true;
}

/**
 * @param array<string, \stdClass> $data
 */
function bb_node_has_cycle(array $data, string $node_id): bool
{
    $seen = [];
    $parent = bb_property_string(object: $data[$node_id], property: 'parent');
    while ($parent !== '') {
        if ($parent === $node_id || isset($seen[$parent])) {
            return true;
        }
        $seen[$parent] = true;
        if (!isset($data[$parent])) {
            return false;
        }
        $parent = bb_property_string(object: $data[$parent], property: 'parent');
    }
    return false;
}

/**
 * @param array<string, \stdClass> $data
 * @return array<string, true>
 */
function bb_existing_module_ids(array $data): array
{
    $ids = [];
    foreach ($data as $node_id => $node) {
        if (bb_property_string(object: $node, property: 'type') !== 'module') {
            continue;
        }
        $ids[$node_id] = true;
    }
    return $ids;
}

/**
 * @param array<string, \stdClass> $data
 * @return array<string, \stdClass>
 */
function bb_clone_layout_data(array $data): array
{
    $copy = [];
    foreach ($data as $node_id => $node) {
        $copy[$node_id] = bb_clone_node($node);
    }
    return $copy;
}

/**
 * @param array<string, \stdClass> $data
 */
function bb_reindex_layout_positions(array &$data): void
{
    $groups = [];
    foreach ($data as $node_id => $node) {
        $parent = bb_property_string(object: $node, property: 'parent');
        $groups[$parent][] = $node_id;
    }

    foreach ($groups as $node_ids) {
        usort($node_ids, static function (string $a, string $b) use ($data): int {
            $position = bb_property_int(object: $data[$a], property: 'position') <=> bb_property_int(
                object: $data[$b],
                property: 'position',
            );
            return $position !== 0 ? $position : strcmp($a, $b);
        });
        $position = 0;
        foreach ($node_ids as $node_id) {
            $data[$node_id]->position = $position;
            $position++;
        }
    }
}

/**
 * Place a node at an explicit sibling slot with deterministic splice semantics.
 *
 * When the requested position is already occupied, the node must land *at* that
 * slot and push the incumbent forward — matching FLBuilderModel::reorder_node()
 * (array_splice). Simply assigning the same integer to both would leave a tie
 * that bb_reindex_layout_positions() then resolves by strcmp() of the node ids;
 * because generated ids are random, the new node would land before or after the
 * incumbent at random. We instead bump every same-parent sibling whose position
 * is >= the target up by one before assigning the target, so there is no tie.
 *
 * @param array<string, \stdClass> $data
 */
function bb_place_node_at_position(array &$data, string $node_id, int $position): void
{
    if ($position < 0) {
        $data[$node_id]->position = BB_MAX_WRITE_NODES;
        return;
    }

    $parent = bb_property_string(object: $data[$node_id], property: 'parent');
    foreach ($data as $sibling_id => $sibling) {
        if ($sibling_id === $node_id || bb_property_string(object: $sibling, property: 'parent') !== $parent) {
            continue;
        }
        if (bb_property_int(object: $sibling, property: 'position') >= $position) {
            $data[$sibling_id]->position = bb_property_int(object: $sibling, property: 'position') + 1;
        }
    }
    $data[$node_id]->position = $position;
}

/**
 * @param array<string, \stdClass> $data
 * @param array<string, true> $preserved_modules
 */
function bb_append_or_insert_node(
    array &$data,
    \stdClass $node,
    int $position,
    array $preserved_modules = [],
): ?WP_Error {
    $node_id = bb_property_string(object: $node, property: 'node');
    if (isset($data[$node_id])) {
        return bb_error(
            code: 'beaver_builder_duplicate_node_id',
            message: sprintf('Node `%s` already exists.', $node_id),
            data: ['status' => 409],
        );
    }
    $node->position = $position < 0 ? BB_MAX_WRITE_NODES : $position;
    $data[$node_id] = $node;
    $validation = bb_validate_layout_data(data: $data, preserved_modules: $preserved_modules);
    if ($validation instanceof WP_Error) {
        unset($data[$node_id]);
        return $validation;
    }
    bb_place_node_at_position(data: $data, node_id: $node_id, position: $position);
    bb_reindex_layout_positions($data);
    return null;
}

/**
 * @param array<string, \stdClass> $data
 * @return list<string>
 */
function bb_descendant_node_ids(array $data, string $node_id): array
{
    $ids = [];
    foreach ($data as $candidate_id => $node) {
        if (bb_property_string(object: $node, property: 'parent') !== $node_id) {
            continue;
        }
        $ids[] = $candidate_id;
        foreach (bb_descendant_node_ids(data: $data, node_id: $candidate_id) as $child_id) {
            $ids[] = $child_id;
        }
    }
    return $ids;
}

/**
 * @param array<string, \stdClass> $data
 * @param list<string> $statuses
 */
function bb_save_layout_data(int $post_id, array $data, array $statuses): void
{
    foreach ($statuses as $status) {
        \FLBuilderModel::update_layout_data($data, $status, $post_id);
    }
    update_post_meta(post_id: $post_id, meta_key: '_fl_builder_enabled', meta_value: true);
    \FLBuilderModel::delete_all_asset_cache($post_id);
    \FLBuilderModel::delete_node_template_asset_cache($post_id);
    clean_post_cache($post_id);
}

/**
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function bb_with_layout_lock(int $post_id, callable $fn): mixed
{
    $token = bb_acquire_layout_lock($post_id);
    if ($token instanceof WP_Error) {
        return $token;
    }

    try {
        return $fn();
    } finally {
        bb_release_layout_lock($token);
    }
}

function bb_acquire_layout_lock(int $post_id): string|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $lock_name = sprintf('nvp_bb_%d_%s', get_current_blog_id(), md5((string) $post_id));

    /** @var mixed $got */
    // @mago-expect analysis:possibly-invalid-argument
    $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    if ($got === '1' || $got === 1) {
        return 'sql:' . $lock_name;
    }
    if ($got === '0' || $got === 0) {
        return bb_error(
            code: 'beaver_builder_write_locked',
            message: 'Could not acquire the Beaver Builder layout lock. Retry the write.',
            data: ['status' => 409],
        );
    }

    return 'none:' . $lock_name;
}

function bb_release_layout_lock(string $token): void
{
    if (!str_starts_with($token, 'sql:')) {
        return;
    }

    $lock_name = substr(string: $token, offset: strlen('sql:'));
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
}

/**
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function bb_with_builder_post(int $post_id, callable $fn): mixed
{
    if (!class_exists('FLBuilderModel')) {
        return $fn();
    }

    \FLBuilderModel::set_post_id($post_id);
    try {
        return $fn();
    } finally {
        \FLBuilderModel::reset_post_id();
    }
}

/**
 * @param array<string, \stdClass> $data
 * @param array<string, true> $new_node_ids
 */
function bb_normalize_new_modules(array &$data, array $new_node_ids = []): ?WP_Error
{
    foreach ($data as $node_id => $node) {
        if ($new_node_ids !== [] && !isset($new_node_ids[$node_id])) {
            continue;
        }
        $error = bb_normalize_module_node(node: $node);
        if ($error instanceof WP_Error) {
            return $error;
        }
    }
    return null;
}

function bb_normalize_module_node(\stdClass $node, ?object $old_node = null): ?WP_Error
{
    if (bb_property_string(object: $node, property: 'type') !== 'module') {
        return null;
    }

    $settings = bb_node_settings($node) ?? new \stdClass();
    $module = bb_property_string(object: $settings, property: 'type');
    if ($module === '' || !bb_module_slug_known(slug: $module, scope: 'enabled')) {
        return bb_error(
            code: 'beaver_builder_unknown_module',
            message: sprintf(
                'Module node `%s` uses unknown or disabled module `%s`.',
                bb_property_string(object: $node, property: 'node'),
                $module,
            ),
            data: ['status' => 400],
        );
    }

    $settings = bb_merge_module_defaults(module: $module, settings: $settings);
    $node->settings = $settings;

    if ($old_node !== null) {
        bb_call_module_update_delete_hook(node: $old_node);
    }

    if (class_exists('FLBuilderModel')) {
        bb_ensure_modules_loaded();
        /** @var mixed $instance */
        $instance = \FLBuilderModel::get_module($node);
        if (is_object($instance) && method_exists($instance, 'update')) {
            /** @var mixed $updated */
            $updated = $instance->update($settings);
            if (is_object($updated)) {
                $node->settings = bb_settings_from_object($updated);
            }
        }
    }

    bb_set_module_version(node: $node, module: $module);
    return null;
}

function bb_merge_module_defaults(string $module, \stdClass $settings): \stdClass
{
    if (!class_exists('FLBuilderModel') || !method_exists('FLBuilderModel', 'get_module_defaults')) {
        return $settings;
    }

    /** @var mixed $defaults */
    $defaults = \FLBuilderModel::get_module_defaults($module);
    if (!is_object($defaults)) {
        return $settings;
    }

    return (object) array_merge(get_object_vars($defaults), get_object_vars($settings));
}

function bb_set_module_version(\stdClass $node, string $module): void
{
    if (property_exists($node, 'version') || !class_exists('FLBuilderModuleDeprecations')) {
        return;
    }

    /** @var mixed $version */
    $version = \FLBuilderModuleDeprecations::get_module_version($module);
    if (is_int($version)) {
        $node->version = $version;
    }
}

function bb_call_module_update_delete_hook(object $node): void
{
    bb_call_module_delete_hook(node: $node, mode: 'update');
}

function bb_call_module_remove_hook(object $node): void
{
    bb_call_module_delete_hook(node: $node, mode: 'remove');
}

function bb_call_module_delete_hook(object $node, string $mode): void
{
    if (bb_property_string(object: $node, property: 'type') !== 'module' || !class_exists('FLBuilderModel')) {
        return;
    }

    bb_ensure_modules_loaded();
    /** @var mixed $instance */
    $instance = \FLBuilderModel::get_module($node);
    if (!is_object($instance)) {
        return;
    }

    if (method_exists($instance, 'delete')) {
        $instance->delete();
    }
    if ($mode === 'remove' && method_exists($instance, 'remove')) {
        $instance->remove();
    }
}

/**
 * @param array<string, \stdClass> $old_data
 * @param array<string, \stdClass> $new_data
 */
function bb_call_module_lifecycle_hooks(int $post_id, array &$new_data, array $old_data): ?WP_Error
{
    return bb_with_builder_post(post_id: $post_id, fn: function () use (&$new_data, $old_data): ?WP_Error {
        return bb_call_module_lifecycle_hooks_for_post(new_data: $new_data, old_data: $old_data);
    });
}

/**
 * @param array<string, \stdClass> $old_data
 * @param array<string, \stdClass> $new_data
 */
function bb_call_module_lifecycle_hooks_for_post(array &$new_data, array $old_data): ?WP_Error
{
    foreach ($old_data as $node_id => $old_node) {
        if (isset($new_data[$node_id]) || bb_property_string(object: $old_node, property: 'type') !== 'module') {
            continue;
        }
        bb_call_module_remove_hook(node: $old_node);
    }

    foreach ($new_data as $node_id => $node) {
        $old_node = $old_data[$node_id] ?? null;
        $old_module = is_object($old_node) && bb_property_string(object: $old_node, property: 'type') === 'module'
            ? $old_node
            : null;
        $module = bb_node_module_slug($node);
        $old_module_slug = $old_module === null ? '' : bb_node_module_slug($old_module);
        if (
            $old_module !== null
            && $module !== ''
            && $module === $old_module_slug
            && !bb_module_slug_known(slug: $module, scope: 'enabled')
            && bb_module_slug_known(slug: $module, scope: 'all')
        ) {
            continue;
        }

        if (!bb_module_node_requires_lifecycle(node: $node, old_module: $old_module)) {
            continue;
        }

        $error = bb_normalize_module_node(node: $node, old_node: $old_module);
        if ($error instanceof WP_Error) {
            return $error;
        }
    }
    return null;
}

function bb_module_node_requires_lifecycle(object $node, ?object $old_module): bool
{
    if (bb_property_string(object: $node, property: 'type') !== 'module') {
        return false;
    }
    if ($old_module === null) {
        return true;
    }
    if (bb_node_module_slug($node) !== bb_node_module_slug($old_module)) {
        return true;
    }
    return bb_module_settings_fingerprint($node) !== bb_module_settings_fingerprint($old_module);
}

function bb_module_settings_fingerprint(object $node): string
{
    $settings = bb_node_settings($node);
    $encoded = wp_json_encode(bb_jsonable(value: $settings ?? new \stdClass(), max_field_bytes: 0));
    return is_string($encoded) ? $encoded : '';
}

function bb_node_module_slug(object $node): string
{
    if (bb_property_string(object: $node, property: 'type') !== 'module') {
        return '';
    }

    $settings = bb_node_settings($node);
    return $settings === null ? '' : bb_property_string(object: $settings, property: 'type');
}

/**
 * @param array<string, \stdClass> $data
 * @param list<string> $statuses
 * @return array<string, mixed>
 */
function bb_write_response(int $post_id, array $data, array $statuses): array
{
    return [
        'success' => true,
        'post_id' => $post_id,
        'statuses' => $statuses,
        'builder_enabled' => bb_layout_enabled($post_id),
        'node_counts' => bb_layout_counts($data),
        'nodes' => bb_layout_nodes(
            data: $data,
            settings_mode: 'compact',
            max_nodes: BB_MAX_WRITE_NODES,
            max_field_bytes: 0,
        ),
        'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
    ];
}

/**
 * @param array<string, object> $data
 * @return array{rows: int, columns: int, modules: int, other: int, total: int}
 */
function bb_layout_counts(array $data): array
{
    $counts = ['rows' => 0, 'columns' => 0, 'modules' => 0, 'other' => 0, 'total' => 0];
    foreach ($data as $node) {
        $counts['total']++;
        $type = bb_property_string(object: $node, property: 'type');
        $key = match ($type) {
            'row' => 'rows',
            'column' => 'columns',
            'module' => 'modules',
            default => 'other',
        };
        $counts[$key]++;
    }
    return $counts;
}

/**
 * @param array<string, object> $data
 * @return list<array<string, mixed>>
 */
function bb_layout_nodes(array $data, string $settings_mode, int $max_nodes, int $max_field_bytes): array
{
    $children = [];
    foreach ($data as $node_id => $node) {
        $parent = bb_property_string(object: $node, property: 'parent');
        if ($parent === '') {
            continue;
        }
        if (!isset($children[$parent])) {
            $children[$parent] = [];
        }
        $children[$parent][] = $node_id;
    }
    foreach ($children as $parent => $node_ids) {
        $children[$parent] = bb_sort_node_ids_by_position(data: $data, node_ids: $node_ids);
    }

    $rows = [];
    $include_settings = $settings_mode === 'settings';
    foreach (bb_ordered_layout_node_ids(data: $data, children: $children) as $node_id) {
        if (count($rows) >= $max_nodes) {
            break;
        }
        $node = $data[$node_id];

        $settings = bb_node_settings($node);
        $row = [
            'id' => $node_id,
            'type' => bb_property_string(object: $node, property: 'type'),
            'parent' => bb_property_string(object: $node, property: 'parent'),
            'position' => bb_property_int(object: $node, property: 'position'),
            'children' => $children[$node_id] ?? [],
            'module' => $settings === null ? '' : bb_property_string(object: $settings, property: 'type'),
            'global' => bb_property_bool(object: $node, property: 'global'),
            'dynamic' => bb_property_bool(object: $node, property: 'dynamic'),
        ];

        if ($include_settings && $settings !== null) {
            $row['settings'] = bb_jsonable(value: $settings, max_field_bytes: $max_field_bytes);
        }

        if (property_exists($node, 'version')) {
            $row['version'] = bb_property_int(object: $node, property: 'version');
        }

        $rows[] = $row;
    }
    return $rows;
}

/**
 * @param array<string, object> $data
 * @param list<string> $node_ids
 * @return list<string>
 */
function bb_sort_node_ids_by_position(array $data, array $node_ids): array
{
    usort($node_ids, static function (string $a, string $b) use ($data): int {
        $a_node = $data[$a] ?? null;
        $b_node = $data[$b] ?? null;
        $a_position = $a_node instanceof \stdClass || is_object($a_node)
            ? bb_property_int(object: $a_node, property: 'position')
            : 0;
        $b_position = $b_node instanceof \stdClass || is_object($b_node)
            ? bb_property_int(object: $b_node, property: 'position')
            : 0;
        $position = $a_position <=> $b_position;
        return $position !== 0 ? $position : strcmp($a, $b);
    });
    return $node_ids;
}

/**
 * @param array<string, object> $data
 * @param array<string, list<string>> $children
 * @return list<string>
 */
function bb_ordered_layout_node_ids(array $data, array $children): array
{
    $roots = [];
    foreach ($data as $node_id => $node) {
        $parent = bb_property_string(object: $node, property: 'parent');
        if ($parent === '' || !isset($data[$parent])) {
            $roots[] = $node_id;
        }
    }
    $roots = bb_sort_node_ids_by_position(data: $data, node_ids: $roots);

    $ordered = [];
    $visited = [];
    foreach ($roots as $root_id) {
        bb_collect_layout_node_ids(node_id: $root_id, children: $children, ordered: $ordered, visited: $visited);
    }
    foreach (array_keys($data) as $node_id) {
        if (isset($visited[$node_id])) {
            continue;
        }
        bb_collect_layout_node_ids(node_id: $node_id, children: $children, ordered: $ordered, visited: $visited);
    }
    return $ordered;
}

/**
 * @param array<string, list<string>> $children
 * @param list<string> $ordered
 * @param array<string, true> $visited
 */
function bb_collect_layout_node_ids(string $node_id, array $children, array &$ordered, array &$visited): void
{
    if (isset($visited[$node_id])) {
        return;
    }

    $visited[$node_id] = true;
    $ordered[] = $node_id;
    foreach ($children[$node_id] ?? [] as $child_id) {
        bb_collect_layout_node_ids(node_id: $child_id, children: $children, ordered: $ordered, visited: $visited);
    }
}

function bb_property_int(object $object, string $property): int
{
    $value = bb_object_property(object: $object, property: $property);
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }
    return 0;
}

function bb_node_settings(object $node): ?\stdClass
{
    $settings = bb_object_property(object: $node, property: 'settings');
    if ($settings instanceof \stdClass) {
        return $settings;
    }
    return is_object($settings) ? bb_settings_from_object($settings) : null;
}

/**
 * @param mixed $value
 * @return array<array-key, mixed>|bool|float|int|string|null
 */
function bb_jsonable($value, int $max_field_bytes, int $depth = 0)
{
    if ($depth > 12) {
        return '[truncated:depth]';
    }

    if (is_string($value)) {
        if ($max_field_bytes > 0 && strlen($value) > $max_field_bytes) {
            $slice = function_exists('mb_strcut')
                ? mb_strcut(string: $value, start: 0, length: $max_field_bytes)
                : substr(string: $value, offset: 0, length: $max_field_bytes);
            return $slice . '...[truncated]';
        }
        return $value;
    }

    if (is_scalar($value) || $value === null) {
        return $value;
    }

    if (is_object($value)) {
        $value = get_object_vars($value);
    }

    if (is_array($value)) {
        $out = [];
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $key => $item) {
            $out[$key] = bb_jsonable(value: $item, max_field_bytes: $max_field_bytes, depth: $depth + 1);
        }
        return $out;
    }

    return null;
}
