<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;
use WP_Post;

/**
 * Abilities: manage Etch components (reusable, synced wp_block patterns carrying
 * the etch_component_html_key meta). A component is { id, name, key, description,
 * properties, tree } — its tree is the same compact etch/* node format used by
 * get-content/set-content.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Build the full component response (compact tree, properties, lossy honesty).
 *
 * @return array<string, mixed>
 */
function etch_component_full(WP_Post $post): array
{
    /** @var mixed $key */
    $key = get_post_meta($post->ID, key: 'etch_component_html_key', single: true);
    /** @var mixed $properties */
    $properties = get_post_meta($post->ID, key: 'etch_component_properties', single: true);

    $blocks = parse_blocks($post->post_content);
    $count = 0;
    $tree = etch_blocks_to_tree($blocks, ETCH_MAX_TREE_NODES, $count);
    $warnings = [];
    if (etch_count_interleaved($blocks) > 0) {
        $warnings[] = __(
            'This component contains freeform HTML between child blocks that is not represented and would be lost if the tree is written back.',
            domain: 'novamira-pro',
        );
    }

    return [
        'id' => $post->ID,
        'name' => $post->post_title,
        'key' => is_string($key) ? $key : '',
        'description' => $post->post_excerpt,
        'properties' => is_array($properties) ? $properties : [],
        'node_count' => $count,
        'truncated' => etch_count_block_nodes($blocks) > $count,
        'lossy' => $warnings !== [],
        'warnings' => $warnings,
        'tree' => $tree,
    ];
}

/**
 * Validate an optional component `properties` value (an object/array within the
 * size cap).
 */
function etch_component_properties_or_error(mixed $properties): ?WP_Error
{
    if (!is_array($properties)) {
        return etch_error('etch_invalid_input', __('"properties" must be an object or array.', domain: 'novamira-pro'));
    }

    $encoded = wp_json_encode($properties, flags: 0, depth: ETCH_MAX_ATTRS_DEPTH);
    if (!is_string($encoded) || strlen($encoded) > ETCH_MAX_ATTRS_BYTES) {
        return etch_error('etch_properties_too_large', __(
            '"properties" is too large or too deeply nested to store safely.',
            domain: 'novamira-pro',
        ));
    }

    return null;
}

/**
 * Normalize a component `properties` map so the intuitive keyed shape
 * `{ propKey: { type: "string" } }` produces bindable props. Etch's resolver
 * requires each property to carry its own `key` and a `type` OBJECT
 * (`{ name: "string" }`), or it silently drops the property and `{props.<key>}`
 * resolves to nothing — so inject the `key` from the map key when missing and
 * wrap a bare-string `type` into `{ name: <type> }`.
 *
 * @param array<array-key, mixed> $properties
 * @return array<array-key, mixed>
 */
function etch_component_normalize_properties(array $properties): array
{
    $out = [];
    foreach (array_keys($properties) as $k) {
        /** @var mixed $v */
        $v = $properties[$k];
        if (!is_array($v)) {
            $out[$k] = $v;
            continue;
        }
        /** @var mixed $existing_key */
        $existing_key = $v['key'] ?? '';
        if (!is_string($existing_key) || $existing_key === '') {
            $v['key'] = (string) $k;
        }
        /** @var mixed $type */
        $type = $v['type'] ?? null;
        if (is_string($type) && $type !== '') {
            $v['type'] = ['name' => $type];
        }
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Generate a unique html key for a new component (Etch's editor generates one
 * client-side; the REST create only stores what it is given).
 */
function etch_component_generate_key(): string
{
    return 'c' . substr(md5(uniqid('etch_component', more_entropy: true)), offset: 0, length: 12);
}

/**
 * Build the wp_update_post/wp_insert_post data from the name/description/tree
 * inputs, or a WP_Error on invalid input. Split out of etch_edit_component to
 * keep it under the complexity threshold.
 *
 * @param array<array-key, mixed> $input
 * @return array{ID: int, post_title?: string, post_excerpt?: string, post_content?: string}|WP_Error
 */
function etch_component_post_data_or_error(array $input, int $post_id): array|WP_Error
{
    $post_data = ['ID' => $post_id];

    if (isset($input['name'])) {
        /** @var mixed $name */
        $name = $input['name'];
        if (!is_string($name) || trim($name) === '') {
            return etch_error('etch_invalid_input', __('"name" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $post_data['post_title'] = sanitize_text_field($name);
    }

    if (isset($input['description'])) {
        /** @var mixed $description */
        $description = $input['description'];
        $post_data['post_excerpt'] = sanitize_text_field(is_string($description) ? $description : '');
    }

    if (isset($input['tree'])) {
        $node_count = 0;
        $markup = etch_tree_to_markup_or_error($input['tree'], $node_count);
        if ($markup instanceof WP_Error) {
            return $markup;
        }
        $post_data['post_content'] = wp_slash($markup);
    }

    return $post_data;
}

// ---------------------------------------------------------------------------
// list-components
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-components', [
    'label' => __('List Etch Components', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Etch components (reusable synced patterns stored as wp_block posts). Returns a compact entry per component { id, name, key, description } — fetch the full tree and properties with novamira/etch-get-component.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer', 'description' => 'Number of components returned in this response.'],
            'total' => ['type' => 'integer', 'description' => 'Total components on the site (may exceed count).'],
            'truncated' => [
                'type' => 'boolean',
                'description' => 'True when there are more than the returned cap (500).',
            ],
            'components' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'key' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_components',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact list — no block trees. If truncated is true there are more than 500 components (total reports the real count). Use the id with novamira/etch-get-component for the full component (tree + properties), and reference a component from a page via an etch/component node whose attrs.ref is the component id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, total: int, truncated: bool, components: list<array{id: int, name: string, key: string, description: string}>}
 */
function etch_list_components(): array
{
    $query = new \WP_Query([
        'post_type' => 'wp_block',
        'post_status' => 'publish',
        'posts_per_page' => 500,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => false,
        'meta_query' => [['key' => 'etch_component_html_key', 'compare' => 'EXISTS']],
    ]);

    /** @var array<int, WP_Post> $posts */
    $posts = $query->posts;

    $components = [];
    foreach ($posts as $post) {
        /** @var mixed $key */
        $key = get_post_meta($post->ID, key: 'etch_component_html_key', single: true);
        $components[] = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'key' => is_string($key) ? $key : '',
            'description' => $post->post_excerpt,
        ];
    }

    $total = (int) $query->found_posts;

    return [
        'count' => count($components),
        'total' => $total,
        'truncated' => $total > count($components),
        'components' => $components,
    ];
}

// ---------------------------------------------------------------------------
// get-component
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-component', [
    'label' => __('Get Etch Component', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Etch component in full: id, name, key, description, properties (its declared props), and tree (the component\'s element tree in the compact etch/* node format). The tree is read like novamira/etch-get-content, including the lossy/warnings honesty signal.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => ['type' => 'integer', 'description' => 'The component (wp_block) post ID.'],
        ],
        'required' => ['component_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'properties' => ['type' => 'object'],
            'node_count' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'lossy' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'tree' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a component before editing it. Edit the returned tree and write it back with novamira/etch-edit-component. If lossy is true, do not rewrite the whole tree (see novamira/etch-get-content).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>|WP_Error
 */
function etch_get_component(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_component_or_error((int) ($input['component_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    return etch_component_full($post);
}

// ---------------------------------------------------------------------------
// create-component
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-component', [
    'label' => __('Create Etch Component', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Etch component (a published wp_block). Accepts name (required), tree (the component\'s element tree in the compact etch/* node format, same as etch-set-content), description, properties (an object of declared props), and key (auto-generated if omitted). Returns the new component id, name, and key. Reference it from a page via an etch/component node with attrs.ref = the returned id.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'The component name (post title).'],
            'tree' => [
                'type' => 'array',
                'description' => 'The component element tree (compact etch/* nodes).',
                'items' => ['type' => 'object'],
            ],
            'description' => ['type' => 'string'],
            'properties' => ['type' => 'object', 'description' => 'The component\'s declared props.'],
            'key' => ['type' => 'string', 'description' => 'The html key; auto-generated if omitted.'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Build the tree the same way as for novamira/etch-set-content. The key is what {component.key} and component lookups resolve to; let it auto-generate unless you need a specific one.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: int, name: string, key: string}|WP_Error
 */
function etch_create_component(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $name */
    $name = $input['name'] ?? '';
    if (!is_string($name) || trim($name) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "name" is required.', domain: 'novamira-pro'));
    }

    $node_count = 0;
    $markup = etch_tree_to_markup_or_error($input['tree'] ?? [], $node_count);
    if ($markup instanceof WP_Error) {
        return $markup;
    }

    /** @var mixed $properties */
    $properties = $input['properties'] ?? [];
    $properties_error = etch_component_properties_or_error($properties);
    if ($properties_error instanceof WP_Error) {
        return $properties_error;
    }

    /** @var mixed $description */
    $description = $input['description'] ?? '';
    /** @var mixed $key */
    $key = $input['key'] ?? '';
    $key = is_string($key) && $key !== '' ? $key : etch_component_generate_key();

    $post_id = wp_insert_post([
        'post_type' => 'wp_block',
        'post_status' => 'publish',
        'post_title' => sanitize_text_field($name),
        'post_excerpt' => sanitize_text_field(is_string($description) ? $description : ''),
        'post_content' => wp_slash($markup),
    ], wp_error: true);

    if ($post_id instanceof WP_Error) {
        return $post_id;
    }

    update_post_meta($post_id, meta_key: 'etch_component_html_key', meta_value: $key);
    update_post_meta(
        $post_id,
        meta_key: 'etch_component_properties',
        meta_value: etch_component_normalize_properties(is_array($properties) ? $properties : []),
    );

    return [
        'id' => (int) $post_id,
        'name' => sanitize_text_field($name),
        'key' => $key,
    ];
}

// ---------------------------------------------------------------------------
// edit-component
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-component', [
    'label' => __('Edit Etch Component', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Etch component in place (partial update). Provide component_id and any of name, tree (replaces the component\'s element tree), description, properties, key. Returns the component id, name, and key.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => ['type' => 'integer', 'description' => 'The component (wp_block) post ID.'],
            'name' => ['type' => 'string'],
            'tree' => [
                'type' => 'array',
                'description' => 'Replaces the component element tree (compact etch/* nodes).',
                'items' => ['type' => 'object'],
            ],
            'description' => ['type' => 'string'],
            'properties' => ['type' => 'object'],
            'key' => ['type' => 'string'],
        ],
        'required' => ['component_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: pass only the fields you change. Passing tree replaces the whole component body — read it first with novamira/etch-get-component if you mean to edit it.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{id: int, name: string, key: string}|WP_Error
 */
function etch_edit_component(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_component_or_error((int) ($input['component_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $post_data = etch_component_post_data_or_error($input, $post->ID);
    if ($post_data instanceof WP_Error) {
        return $post_data;
    }

    if (isset($input['properties'])) {
        $properties_error = etch_component_properties_or_error($input['properties']);
        if ($properties_error instanceof WP_Error) {
            return $properties_error;
        }
        update_post_meta(
            $post->ID,
            meta_key: 'etch_component_properties',
            meta_value: etch_component_normalize_properties(is_array($input['properties']) ? $input['properties'] : []),
        );
    }

    /** @var mixed $key_meta */
    $key_meta = get_post_meta($post->ID, key: 'etch_component_html_key', single: true);
    if (isset($input['key'])) {
        /** @var mixed $key */
        $key = $input['key'];
        if (!is_string($key) || $key === '') {
            return etch_error('etch_invalid_input', __('"key" must be a non-empty string.', domain: 'novamira-pro'));
        }
        update_post_meta($post->ID, meta_key: 'etch_component_html_key', meta_value: $key);
        $key_meta = $key;
    }

    // Skip the post write when only meta (properties/key) changed — $post_data
    // then holds just the ID, and writing it would needlessly bump post_modified.
    if (count($post_data) > 1) {
        $result = wp_update_post($post_data, wp_error: true);
        if ($result instanceof WP_Error) {
            return $result;
        }

        clean_post_cache($post->ID);
    }

    return [
        'id' => $post->ID,
        'name' => get_the_title($post->ID),
        'key' => is_string($key_meta) ? $key_meta : '',
    ];
}

// ---------------------------------------------------------------------------
// delete-component
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-component', [
    'label' => __('Delete Etch Component', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an Etch component (wp_block). Pages that reference it via an etch/component node will lose that component. Returns the id and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => ['type' => 'integer', 'description' => 'The component (wp_block) post ID.'],
        ],
        'required' => ['component_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE and permanent. Components referenced by pages (etch/component nodes) will break. Confirm the component is unused first.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: int, deleted: bool}|WP_Error
 */
function etch_delete_component(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_component_or_error((int) ($input['component_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $deleted = wp_delete_post($post->ID, force_delete: true);
    if ($deleted === false || $deleted === null) {
        return etch_error('etch_delete_failed', sprintf(
            /* translators: %d: component ID. */
            __('Failed to delete component %d.', domain: 'novamira-pro'),
            $post->ID,
        ));
    }

    return [
        'id' => $post->ID,
        'deleted' => true,
    ];
}

// ---------------------------------------------------------------------------
// duplicate-component
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-duplicate-component', [
    'label' => __('Duplicate Etch Component', domain: 'novamira-pro'),
    'description' => __(
        'Clones an existing Etch component (a wp_block) in one call: copies its element tree, description and declared properties verbatim under a fresh, unique html key, so the copy is a faithful starting point you can then edit. Pass component_id (required) and an optional name (defaults to "<source> (copy)"). Returns the new component id, name, and key.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => ['type' => 'integer', 'description' => 'The source component (wp_block) post ID.'],
            'name' => ['type' => 'string', 'description' => 'Name for the copy. Defaults to "<source name> (copy)".'],
        ],
        'required' => ['component_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_duplicate_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Faithful copy (content is duplicated verbatim, so even lossy/mixed component bodies are preserved). The copy gets a NEW unique html key; existing component instances keep referencing the original.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{id: int, name: string, key: string}|WP_Error
 */
function etch_duplicate_component(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }
    $post = etch_component_or_error((int) ($input['component_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $name */
    $name = $input['name'] ?? '';
    $title = is_string($name) && trim($name) !== '' ? sanitize_text_field($name) : $post->post_title . ' (copy)';

    $key = etch_component_generate_key();
    /** @var mixed $properties */
    $properties = get_post_meta($post->ID, key: 'etch_component_properties', single: true);

    $new_id = wp_insert_post([
        'post_type' => 'wp_block',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_excerpt' => $post->post_excerpt,
        'post_content' => wp_slash($post->post_content),
    ], wp_error: true);

    if ($new_id instanceof WP_Error) {
        return $new_id;
    }

    update_post_meta($new_id, meta_key: 'etch_component_html_key', meta_value: $key);
    update_post_meta(
        $new_id,
        meta_key: 'etch_component_properties',
        meta_value: is_array($properties) ? $properties : [],
    );

    return ['id' => (int) $new_id, 'name' => $title, 'key' => $key];
}
