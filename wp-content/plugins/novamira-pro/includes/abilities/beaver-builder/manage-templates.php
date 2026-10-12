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

wp_register_ability('novamira/beaver-builder-list-templates', [
    'label' => __('List Beaver Builder Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists reusable Beaver Builder user templates — full-page `layout` templates and saved `row`/`column`/`module` nodes. Each row gives the `post_id` (apply/delete target), `template_id` (for node templates), `name`, `type`, and `is_global`. Use this to find a template to apply-template into a page so you build once and reuse. Filter by `type`; `row`/`column`/`module` (saved nodes) require Beaver Builder\'s node-templates feature (not Lite).',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'enum' => ['all', 'layout', 'row', 'column', 'module'],
                'description' => 'Template type to list. Defaults to all.',
            ],
            'search' => ['type' => 'string', 'description' => 'Case-insensitive substring match on the template name.'],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Max rows. Defaults to 100.',
            ],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Rows to skip. Defaults to 0.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => ['type' => 'array', 'items' => ['type' => 'object']],
            'count' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The catalog of reusable templates. Pick a `post_id` (and note `type`/`is_global`) then apply-template it into a target page. Saved row/column/module nodes only exist when node templates are enabled (not Lite).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-create-template', [
    'label' => __('Create Beaver Builder Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a reusable Beaver Builder template from an existing page\'s content. `type=layout` saves the whole page; `type=row|column|module` saves a single node (pass its `node_id`) as a saved row/column/module. Set `global=true` to save a node as a GLOBAL template — every place it is applied stays linked, so editing the template propagates everywhere (node templates and global require the non-Lite node-templates feature). Returns the new template `post_id` / `template_id`. Apply it later with apply-template.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'source_post_id' => [
                'type' => 'integer',
                'description' => 'Post whose Beaver Builder layout is the source.',
            ],
            'post' => ['type' => 'integer', 'description' => 'Alias for source_post_id.'],
            'name' => ['type' => 'string', 'description' => 'Template name.'],
            'type' => [
                'type' => 'string',
                'enum' => ['layout', 'row', 'column', 'module'],
                'description' => 'layout = whole page; row/column/module = save one node (requires node_id).',
            ],
            'node_id' => [
                'type' => 'string',
                'description' => 'The node to save (required when type is row/column/module).',
            ],
            'global' => [
                'type' => 'boolean',
                'description' => 'Save a node template as global (linked instances). Defaults false.',
            ],
            'category' => ['type' => 'string', 'description' => 'Optional template category name.'],
        ],
        'required' => ['name', 'type'],
        'anyOf' => [['required' => ['source_post_id']], ['required' => ['post']]],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Build once, reuse. layout saves the whole page; row/column/module saves one node by id. global=true makes a node template propagate to every instance — use it for shared headers/CTAs the user wants to edit in one place.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-apply-template', [
    'label' => __('Apply Beaver Builder Template', domain: 'novamira-pro'),
    'description' => __(
        'Applies a saved template (from list-templates) into a target page. `scope=layout` replaces the page with a layout template (or `append=true` to add its rows after the existing content); `scope=node` inserts a saved row/column/module under `parent_id` at `position`. Node ids are regenerated on apply. Applying a GLOBAL node template inserts a linked instance (only the root node is copied; it renders from the template). Pass the template by its `post_id` from list-templates.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target_post_id' => ['type' => 'integer', 'description' => 'Page to apply the template into.'],
            'post' => ['type' => 'integer', 'description' => 'Alias for target_post_id.'],
            'template_post_id' => [
                'type' => 'integer',
                'description' => 'The template\'s post_id from list-templates.',
            ],
            'scope' => [
                'type' => 'string',
                'enum' => ['layout', 'node'],
                'description' => 'layout = whole-page template; node = saved row/column/module.',
            ],
            'append' => [
                'type' => 'boolean',
                'description' => 'layout scope only: append rows instead of replacing. Defaults false (replace).',
            ],
            'parent_id' => [
                'type' => ['string', 'null'],
                'description' => 'node scope: parent node id to insert under (null/omitted = top level).',
            ],
            'position' => ['type' => 'integer', 'description' => 'node scope: sibling position. Omitted appends.'],
        ],
        'required' => ['template_post_id', 'scope'],
        'anyOf' => [['required' => ['target_post_id']], ['required' => ['post']]],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_apply_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Insert a reusable template into a page. scope=layout with append=false REPLACES the page content (destructive); append=true or scope=node adds to it. Node ids are regenerated. After applying, re-read with get-layout if you need the new ids.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/beaver-builder-delete-template', [
    'label' => __('Delete Beaver Builder Template', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Beaver Builder user template by its `post_id` (from list-templates). By default it trashes the template (recoverable) and, for a global node template, first unlinks it from every page using it. Pass `force=true` to delete permanently.',
        domain: 'novamira-pro',
    ),
    'category' => 'beaver-builder',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_post_id' => ['type' => 'integer', 'description' => 'The template post_id from list-templates.'],
            'force' => [
                'type' => 'boolean',
                'description' => 'Permanently delete instead of trashing. Defaults false.',
            ],
        ],
        'required' => ['template_post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => ['type' => 'object'],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\BeaverBuilder\\beaver_builder_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes a saved template. Deleting a GLOBAL node template detaches it from every page that used it (those instances revert to plain content). force=true is permanent.',
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
function beaver_builder_list_templates(array $input): array|WP_Error
{
    if (!bb_templates_available()) {
        return bb_tpl_unavailable_error();
    }

    $type = bb_string_arg($input['type'] ?? 'all');
    $types = $type === '' || $type === 'all' ? ['layout', 'row', 'column', 'module'] : [$type];
    $search = strtolower(bb_string_arg($input['search'] ?? ''));
    $limit = bb_int_arg(input: $input, key: 'limit', default: 100, min: 1, max: 500);
    $offset = bb_int_arg(input: $input, key: 'offset', default: 0, min: 0, max: 1_000_000);

    $rows = [];
    foreach ($types as $template_type) {
        foreach (bb_tpl_user_templates($template_type) as $row) {
            if ($search !== '' && !str_contains(strtolower($row['name']), $search)) {
                continue;
            }
            $rows[] = $row;
        }
    }

    $total = count($rows);
    $page = array_slice(array: $rows, offset: $offset, length: $limit);
    return ['templates' => $page, 'count' => count($page), 'total' => $total];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_create_template(array $input): array|WP_Error
{
    $ctx = bb_tpl_save_context($input);
    if ($ctx instanceof WP_Error) {
        return $ctx;
    }
    if ($ctx['type'] === 'layout') {
        return bb_tpl_save_layout(post_id: $ctx['post_id'], name: $ctx['name'], category: $ctx['category']);
    }
    return bb_tpl_save_node(post_id: $ctx['post_id'], node_id: $ctx['node_id'], spec: [
        'name' => $ctx['name'],
        'type' => $ctx['type'],
        'kind' => $ctx['is_global'] ? 'global' : 'standard',
        'category' => $ctx['category'],
    ]);
}

/**
 * Validate and normalize the create-template inputs.
 *
 * @param array<string, mixed> $input
 * @return array{post_id: int, name: string, type: string, is_global: bool, category: string, node_id: string}|WP_Error
 */
function bb_tpl_save_context(array $input): array|WP_Error
{
    if (!bb_templates_available()) {
        return bb_tpl_unavailable_error();
    }
    /** @var mixed $source */
    $source = $input['source_post_id'] ?? $input['post'] ?? null;
    $post = bb_post_or_error($source);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $name = bb_tpl_str($input, key: 'name');
    $type = bb_tpl_str($input, key: 'type');
    if ($name === '' || !in_array($type, ['layout', 'row', 'column', 'module'], strict: true)) {
        return bb_error(
            code: 'beaver_builder_invalid_template',
            message: 'Pass a non-empty `name` and a `type` of layout, row, column, or module.',
            data: ['status' => 400],
        );
    }
    return [
        'post_id' => $post->ID,
        'name' => $name,
        'type' => $type,
        'is_global' => bb_bool_arg($input['global'] ?? false),
        'category' => bb_tpl_str($input, key: 'category'),
        'node_id' => bb_tpl_str($input, key: 'node_id'),
    ];
}

/**
 * @return array<string, mixed>
 */
function bb_tpl_save_layout(int $post_id, string $name, string $category): array
{
    $result = bb_tpl_with_post($post_id, static function () use ($name, $category): array {
        $settings = ['name' => $name];
        if ($category !== '') {
            $settings['category'] = $category;
        }
        return bb_tpl_normalize_row(\FLBuilderModel::save_user_template($settings));
    });
    return [
        'saved' => true,
        'type' => 'layout',
        'is_global' => false,
        'post_id' => bb_tpl_int($result['postId'] ?? null),
        'template_id' => bb_array_string(array: $result, key: 'id'),
        'name' => bb_array_string(array: $result, key: 'name'),
    ];
}

/**
 * @param array{name: string, type: string, kind: string, category: string} $spec
 * @return array<string, mixed>|WP_Error
 */
function bb_tpl_save_node(int $post_id, string $node_id, array $spec): array|WP_Error
{
    if (!bb_node_templates_available()) {
        return bb_tpl_node_unavailable_error();
    }
    if ($node_id === '') {
        return bb_error(
            code: 'beaver_builder_invalid_template',
            message: 'Pass the `node_id` of the row/column/module to save.',
            data: ['status' => 400],
        );
    }
    $name = $spec['name'];
    $kind = $spec['kind'];
    $category = $spec['category'];
    $result = bb_tpl_with_post($post_id, static function () use ($node_id, $name, $kind, $category): array {
        $settings = ['name' => $name, 'type' => $kind];
        if ($category !== '') {
            $settings['categories'] = [$category];
        }
        return bb_tpl_normalize_row(\FLBuilderModel::save_node_template($node_id, $settings));
    });
    // save_node_template returns the template_id (`id`) but not always `postId`;
    // resolve the post id from the template id so callers get an apply/delete handle.
    $template_id = bb_array_string(array: $result, key: 'id');
    $post_id_out = bb_tpl_int($result['postId'] ?? null);
    if ($post_id_out === 0 && $template_id !== '' && method_exists('FLBuilderModel', 'get_node_template_post_id')) {
        $post_id_out = bb_tpl_int(\FLBuilderModel::get_node_template_post_id($template_id));
    }
    return [
        'saved' => true,
        'type' => $spec['type'],
        'is_global' => $kind === 'global',
        'post_id' => $post_id_out,
        'template_id' => $template_id,
        'name' => bb_array_string(array: $result, key: 'name'),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_apply_template(array $input): array|WP_Error
{
    if (!bb_templates_available()) {
        return bb_tpl_unavailable_error();
    }

    /** @var mixed $target */
    $target = $input['target_post_id'] ?? $input['post'] ?? null;
    $post = bb_post_or_error($target);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $post_error = bb_write_post_error($post);
    if ($post_error instanceof WP_Error) {
        return $post_error;
    }

    $template = bb_tpl_post_or_error(bb_int_arg(
        input: $input,
        key: 'template_post_id',
        default: 0,
        min: 0,
        max: PHP_INT_MAX,
    ));
    if ($template instanceof WP_Error) {
        return $template;
    }

    $scope = bb_string_arg($input['scope'] ?? '');
    if ($scope === 'node') {
        return bb_tpl_apply_node(post: $post, template: $template, input: $input);
    }
    if ($scope === 'layout') {
        return bb_tpl_apply_layout(post: $post, template: $template, input: $input);
    }
    return bb_error(code: 'beaver_builder_invalid_template', message: 'Pass `scope` as layout or node.', data: [
        'status' => 400,
    ]);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bb_tpl_apply_node(WP_Post $post, WP_Post $template, array $input): array|WP_Error
{
    if (!bb_node_templates_available()) {
        return bb_tpl_node_unavailable_error();
    }
    $template_id = bb_string_arg(get_post_meta(post_id: $template->ID, key: '_fl_builder_template_id', single: true));
    if ($template_id === '') {
        return bb_error(
            code: 'beaver_builder_invalid_template',
            message: 'That template has no node-template id; it is probably a layout template — use scope=layout.',
            data: ['status' => 400],
        );
    }
    $parent = bb_nullable_string_arg($input['parent_id'] ?? null);
    $parent_id = $parent === '' ? null : $parent;
    $position = array_key_exists('position', $input)
        ? bb_int_arg(input: $input, key: 'position', default: 0, min: 0, max: 1_000_000)
        : null;

    bb_tpl_apply($post->ID, static function () use ($template_id, $parent_id, $position): void {
        \FLBuilderModel::apply_node_template($template_id, $parent_id, $position);
    });
    return bb_tpl_applied_response(post_id: $post->ID, template_id: $template->ID, scope: 'node');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function bb_tpl_apply_layout(WP_Post $post, WP_Post $template, array $input): array
{
    $append = bb_bool_arg($input['append'] ?? false);
    $template_post_id = $template->ID;
    bb_tpl_apply($post->ID, static function () use ($template_post_id, $append): void {
        \FLBuilderModel::apply_user_template($template_post_id, $append);
    });
    return bb_tpl_applied_response(post_id: $post->ID, template_id: $template->ID, scope: 'layout');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function beaver_builder_delete_template(array $input): array|WP_Error
{
    if (!bb_templates_available()) {
        return bb_tpl_unavailable_error();
    }

    $template = bb_tpl_post_or_error(bb_int_arg(
        input: $input,
        key: 'template_post_id',
        default: 0,
        min: 0,
        max: PHP_INT_MAX,
    ));
    if ($template instanceof WP_Error) {
        return $template;
    }

    if (bb_bool_arg($input['force'] ?? false)) {
        wp_delete_post($template->ID, force_delete: true);
        return ['deleted' => true, 'template_post_id' => $template->ID, 'mode' => 'force'];
    }

    $template_id = bb_string_arg(get_post_meta(post_id: $template->ID, key: '_fl_builder_template_id', single: true));
    $deleted_via_model = false;
    if ($template_id !== '' && method_exists('FLBuilderModel', 'delete_node_template')) {
        \FLBuilderModel::delete_node_template($template_id);
        $deleted_via_model = true;
    }
    if (!$deleted_via_model) {
        wp_trash_post($template->ID);
    }
    if (method_exists('FLBuilderModel', 'delete_node_template_asset_cache')) {
        \FLBuilderModel::delete_node_template_asset_cache();
    }
    return ['deleted' => true, 'template_post_id' => $template->ID, 'mode' => 'trash'];
}

// ---- helpers --------------------------------------------------------------

function bb_tpl_unavailable_error(): WP_Error
{
    return bb_error(
        code: 'beaver_builder_runtime_unavailable',
        message: 'Beaver Builder is not available on this site.',
        data: ['status' => 503],
    );
}

function bb_tpl_node_unavailable_error(): WP_Error
{
    return bb_error(
        code: 'beaver_builder_node_templates_unavailable',
        message: 'Saved row/column/module (node) templates are not available on this build/configuration.',
        data: ['status' => 422],
    );
}

function bb_tpl_post_or_error(int $post_id): WP_Post|WP_Error
{
    // @mago-expect analysis:mixed-assignment
    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post instanceof WP_Post || $post->post_type !== 'fl-builder-template') {
        return bb_error(
            code: 'beaver_builder_template_not_found',
            message: sprintf('No Beaver Builder template post with id `%d`.', $post_id),
            data: ['status' => 404],
        );
    }
    return $post;
}

/**
 * Beaver's template getters and apply/save methods all operate on the "current"
 * post id. Set it, run the callback, then always restore it.
 *
 * @param callable(): array<string, mixed> $fn
 * @return array<string, mixed>
 */
function bb_tpl_with_post(int $post_id, callable $fn): array
{
    \FLBuilderModel::set_post_id($post_id);
    try {
        return $fn();
    } finally {
        \FLBuilderModel::reset_post_id();
    }
}

/**
 * Apply recipe: in a non-builder (CLI/REST) request Beaver's apply methods read
 * the draft layer and write status-defaulted, so force the draft status, run the
 * apply, then publish the draft to the live layout. Always clean up.
 *
 * @param callable(): void $apply
 */
function bb_tpl_apply(int $post_id, callable $apply): void
{
    \FLBuilderModel::set_post_id($post_id);
    $force_draft = static fn(): string => 'draft';
    add_filter('fl_builder_node_status', $force_draft);
    try {
        $apply();
        \FLBuilderModel::save_layout(publish: true);
    } finally {
        remove_filter('fl_builder_node_status', $force_draft);
        \FLBuilderModel::reset_post_id();
        if (method_exists('FLBuilderModel', 'delete_asset_cache')) {
            \FLBuilderModel::delete_asset_cache($post_id);
        }
    }
}

/**
 * @return array<string, mixed>
 */
function bb_tpl_applied_response(int $post_id, int $template_id, string $scope): array
{
    return [
        'applied' => true,
        'target_post_id' => $post_id,
        'template_post_id' => $template_id,
        'scope' => $scope,
        'node_counts' => bb_layout_counts(bb_layout_data($post_id, status: 'published')),
    ];
}

/**
 * Normalize FLBuilderModel::get_user_templates() rows for one type.
 *
 * @return list<array{post_id: int, template_id: string, name: string, type: string, is_global: bool, link: string}>
 */
function bb_tpl_user_templates(string $type): array
{
    if (!method_exists('FLBuilderModel', 'get_user_templates')) {
        return [];
    }
    /** @var mixed $data */
    $data = \FLBuilderModel::get_user_templates($type);
    $templates = is_array($data) && isset($data['templates']) && is_array($data['templates']) ? $data['templates'] : [];

    $rows = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($templates as $template) {
        if (!is_array($template) && !is_object($template)) {
            continue;
        }
        $row = is_array($template) ? $template : get_object_vars($template);
        $content = bb_array_string(array: $row, key: 'content');
        $rows[] = [
            'post_id' => bb_tpl_int($row['postId'] ?? null),
            'template_id' => bb_array_string(array: $row, key: 'id'),
            'name' => bb_array_string(array: $row, key: 'name'),
            'type' => $content !== '' ? $content : $type,
            'is_global' => bb_tpl_truthy($row['isGlobal'] ?? null),
            'link' => bb_array_string(array: $row, key: 'link'),
        ];
    }
    return $rows;
}

/**
 * @param mixed $result
 * @return array<string, mixed>
 */
function bb_tpl_normalize_row($result): array
{
    if (is_object($result)) {
        $result = get_object_vars($result);
    }
    if (!is_array($result)) {
        return [];
    }
    $normalized = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($result as $key => $value) {
        $normalized[(string) $key] = $value;
    }
    return $normalized;
}

/**
 * @param mixed $value
 */
function bb_tpl_truthy($value): bool
{
    return $value === true || $value === 1 || $value === '1';
}

/**
 * @param array<string, mixed> $input
 */
function bb_tpl_str(array $input, string $key): string
{
    return bb_string_arg($input[$key] ?? '');
}

/**
 * @param mixed $value
 */
function bb_tpl_int($value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return (int) $value;
    }
    if (is_string($value) && is_numeric($value)) {
        return (int) $value;
    }
    return 0;
}
