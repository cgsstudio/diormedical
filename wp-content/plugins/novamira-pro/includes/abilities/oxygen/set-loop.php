<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-set-loop', [
    'label' => __('Configure Oxygen Query Loop', domain: 'novamira-pro'),
    'description' => __(
        'Configures an existing loop element so it repeats a template once per query result. Handles the three native loop types, each with its OWN contract (mismatched inputs are rejected, not silently ignored): `OxygenElements\\PostsLoop` — a post query built from `post_types` + `posts_per_page` + ordering (written to `content.query.query`); `OxygenElements\\TermLoopBuilder` — a taxonomy-term query from `taxonomy` (required) + `hide_empty` + `posts_per_page` (term limit) + ordering (written to `content.query.taxonomy/hide_empty/limit/orderby/order`, NOT the post envelope); `OxygenElements\\DynamicDataLoop` — a `repeater_field` slug. The REPEATED ITEM (`content.repeated_block.global_block`) must be the id of an `oxygen_block` post holding the item subtree — the loop element has no child slot (its children are ignored). Build the item block first (create-template with `post_type: oxygen_block`, then set-content on it), then point the loop at it here. `posts_per_page` is clamped to 1..100. Add the loop element itself with add-element; use this to wire up its query.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post whose canvas holds the loop element.'],
            'element_id' => [
                'type' => 'integer',
                'description' => 'Id of the loop element to configure (from get-element-tree).',
            ],
            'item_block_id' => [
                'type' => 'integer',
                'description' => 'Id of the `oxygen_block` post to render once per query row (the item template). Required — the loop renders nothing without it.',
            ],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'PostsLoop only. Post type slugs to query. Default `["post"]`. Each must be a registered post type.',
            ],
            'repeater_field' => [
                'type' => 'string',
                'description' => 'DynamicDataLoop only. The slug of a repeater / flexible-content source to loop over (from list-dynamic-data-fields `repeater_sources`). Required when the element is a DynamicDataLoop; the loop then repeats the item block once per repeater row of the rendered post.',
            ],
            'taxonomy' => [
                'type' => 'string',
                'description' => 'TermLoopBuilder only (required). The taxonomy slug whose terms the loop repeats over, e.g. `category`, `post_tag`, or a custom taxonomy. Must be a registered taxonomy.',
            ],
            'hide_empty' => [
                'type' => 'boolean',
                'description' => 'TermLoopBuilder only. When true, terms with no assigned posts are excluded. Default false.',
            ],
            'posts_per_page' => [
                'type' => 'integer',
                'description' => 'How many items per page/term limit. PostsLoop default 8; TermLoopBuilder default 10. Clamped to 1..100.',
            ],
            'order_by' => [
                'type' => 'string',
                'description' => 'Ordering field — the valid set is TYPE-SPECIFIC. PostsLoop (post query): `date`, `title`, `menu_order`, `rand`, `modified`, `ID` (default `date`). TermLoopBuilder (term query): `name`, `slug`, `term_id`/`id`/`ID`, `count`, `description`, `parent`, `term_group`, `none` (default `name`); the POST values are rejected here because WP_Term_Query silently ignores them. Not used by DynamicDataLoop.',
            ],
            'order' => [
                'type' => 'string',
                'enum' => ['ASC', 'DESC'],
                'description' => 'Sort direction. PostsLoop default `DESC`; TermLoopBuilder default `ASC`.',
            ],
            'offset' => [
                'type' => 'integer',
                'description' => 'Number of items to skip. Default 0.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'item_block_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_set_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The repeated item is a SEPARATE oxygen_block, NOT the loop element\'s children (which Oxygen ignores). Create the block, put the item subtree in it, then reference it as item_block_id. Only PostsLoop / DynamicDataLoop / TermLoopBuilder elements accept this. Verify the result by rendering the host page.',
            'readonly' => false,
            // Destructive: it REPLACES the element's whole loop configuration
            // (content.query.query envelope + repeated-block binding) — any
            // hand-tuned query keys not re-suppliable through this contract are
            // overwritten. Semantically idempotent (same payload → same final
            // state), but the WP abilities-api lib routes `destructive=true AND
            // idempotent=true` to HTTP DELETE, whose handler cannot decode an
            // `input` JSON body — the ability would be unreachable via the
            // standard REST/MCP pattern. Drop idempotent so the routing falls
            // through to POST (same trade as set-template-conditions).
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Build the complete native wp_query control envelope for a `post_types`
 * custom query. Every key the engine's SSR reads WITHOUT a `??` default
 * (`postTypes`, `ignoreStickyPosts`, `ignoreCurrentPost`, `orderBy`, `order`,
 * and the `active`/`custom`/`text`/`php` envelope keys) MUST be present, or
 * the native renderer emits undefined-key warnings. See
 * `plugin/wp-query-control/query-control.php`.
 *
 * @param list<string> $post_types
 * @return array<string, mixed>
 */
function ox_build_post_types_query(
    array $post_types,
    int $per_page,
    string $order_by,
    string $order,
    int $offset,
): array {
    return [
        'active' => 'custom',
        'text' => '',
        'php' => '',
        'custom' => [
            'source' => 'post_types',
            'postTypes' => $post_types,
            'postsPerPage' => $per_page,
            'totalPosts' => null,
            'offset' => $offset,
            'ignoreStickyPosts' => true,
            'ignoreCurrentPost' => false,
            'metaQuery' => null,
            'date' => 'all',
            'beforeDate' => null,
            'afterDate' => null,
            'conditions' => [[]],
            'orderBy' => $order_by,
            'order' => $order,
            'acfField' => null,
            'metaboxField' => null,
        ],
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_set_loop(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    $element_id = ox_coerce_int($input['element_id'] ?? 0);
    $item_block_id = ox_coerce_int($input['item_block_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0 || $item_block_id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'post_id, element_id and item_block_id must all be positive integers.',
            domain: 'novamira-pro',
        ));
    }

    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $block_error = ox_loop_item_block_error($item_block_id);
    if ($block_error instanceof WP_Error) {
        return $block_error;
    }

    // Type-specific config (post query vs repeater field) is built inside
    // ox_apply_loop_to_element, once the element type is known. Run the whole
    // read-modify-write under the per-post tree lock — like every other canvas
    // mutation — so a concurrent add/edit/apply cannot be clobbered by the stale
    // tree this path reads.
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_apply_loop_to_element(
        $post_id,
        $element_id,
        $item_block_id,
        $input,
    ));
}

/**
 * Validate that `item_block_id` points at a live `oxygen_block` post (the
 * repeated item template). Returns a WP_Error to bail with, or null.
 */
function ox_loop_item_block_error(int $item_block_id): ?WP_Error
{
    /** @var mixed $block */
    $block = get_post($item_block_id);
    if ($block instanceof \WP_Post && $block->post_type === 'oxygen_block' && $block->post_status !== 'trash') {
        return null;
    }
    return ox_error('oxygen_not_found', sprintf(
        /* translators: %d: block id */
        __(
            'item_block_id %d is not an existing oxygen_block post. Create the item block first.',
            domain: 'novamira-pro',
        ),
        $item_block_id,
    ));
}

/**
 * Build the wp_query envelope from the ability input (defaults applied,
 * per-page clamped 1..100).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ox_loop_query_from_input(array $input): array
{
    $per_page = max(1, min(100, ox_coerce_int($input['posts_per_page'] ?? 8)));
    $order_by = isset($input['order_by']) && is_string($input['order_by']) ? $input['order_by'] : 'date';
    $order = isset($input['order']) && $input['order'] === 'ASC' ? 'ASC' : 'DESC';
    $offset = max(0, ox_coerce_int($input['offset'] ?? 0));
    return ox_build_post_types_query(ox_loop_post_types($input), $per_page, $order_by, $order, $offset);
}

/**
 * Read the tree, verify the target is a loop element, build the type-specific
 * content config (post query for PostsLoop, repeater field for
 * DynamicDataLoop), deep-merge it + the repeated-item block into the element's
 * `content` (preserving the other content keys), and write it back through the
 * observed-state path.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_apply_loop_to_element(int $post_id, int $element_id, int $item_block_id, array $input): array|WP_Error
{
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    $hit = ox_find_node($tree, $element_id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            /* translators: 1: element id, 2: post id */
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }
    /** @var array<string, mixed> $data */
    $data = isset($hit['node']['data']) && is_array($hit['node']['data']) ? $hit['node']['data'] : [];
    $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : '';
    if (!ox_element_is_loop($type)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: element id, 2: element type */
            __(
                'Element %1$d (`%2$s`) is not a query loop. Only loop elements (PostsLoop / DynamicDataLoop / TermLoopBuilder) can be configured with set-loop.',
                domain: 'novamira-pro',
            ),
            $element_id,
            $type,
        ));
    }
    $parent_id = $hit['path'] === [] ? null : (int) $hit['path'][count($hit['path']) - 1];
    if ($parent_id === null) {
        return ox_error('oxygen_invalid_input', __('Cannot configure the root as a loop.', domain: 'novamira-pro'));
    }
    $config = ox_build_loop_content_config($type, $input);
    if ($config instanceof WP_Error) {
        return $config;
    }

    $content = ox_merge_loop_content($data, $config, $item_block_id);
    $siblings = ox_siblings_of($tree, $parent_id);
    [$updated, $new_siblings] = ox_patch_element_in_siblings($siblings, $element_id, ['content' => $content]);
    if ($updated === null) {
        return ox_error('oxygen_not_found', __('Loop element vanished mid-update.', domain: 'novamira-pro'));
    }
    ox_set_children($tree, $parent_id, $new_siblings);
    ox_rebuild_lookup($tree);
    $write = ox_write_tree($post_id, $tree);
    if ($write instanceof WP_Error) {
        return $write;
    }
    return ['success' => true, 'element' => ox_element_to_array($updated, $parent_id)];
}

/**
 * Build the type-specific loop content config: `content.query.query` for a
 * post-query loop (PostsLoop / TermLoopBuilder), or `content.field` with the
 * validated repeater field slug for a DynamicDataLoop. Returns the content
 * sub-keys to merge, or a WP_Error when the DDL `repeater_field` is missing or
 * not a registered repeater source.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_build_loop_content_config(string $type, array $input): array|WP_Error
{
    // Reject inputs that belong to a DIFFERENT loop type, instead of silently
    // ignoring them (e.g. posts_per_page on a term loop that the native code
    // never reads).
    $irrelevant = ox_irrelevant_loop_input_error($type, $input);
    if ($irrelevant instanceof WP_Error) {
        return $irrelevant;
    }
    if (str_ends_with($type, '\\DynamicDataLoop')) {
        /** @var mixed $slug */
        $slug = $input['repeater_field'] ?? null;
        if (!is_string($slug) || $slug === '') {
            return ox_error('oxygen_invalid_input', __(
                'A DynamicDataLoop needs `repeater_field` — the slug of a repeater source from list-dynamic-data-fields (`repeater_sources`).',
                domain: 'novamira-pro',
            ));
        }
        if (!ox_is_repeater_source($slug)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %s: repeater field slug */
                __(
                    '`repeater_field` `%s` is not a registered repeater source. Discover valid slugs via list-dynamic-data-fields (`repeater_sources`).',
                    domain: 'novamira-pro',
                ),
                $slug,
            ));
        }
        return ['field' => ['repeater_field' => $slug]];
    }
    if (str_ends_with($type, '\\TermLoopBuilder')) {
        $term_error = ox_validate_loop_term_input($input);
        if ($term_error instanceof WP_Error) {
            return $term_error;
        }
        // Native getTermQueryArgs reads content.query.{taxonomy,hide_empty,limit,
        // orderby,order} directly — NOT the content.query.query post envelope.
        return ['query' => ox_loop_term_query_from_input($input)];
    }
    $query_error = ox_validate_loop_query_input($input);
    if ($query_error instanceof WP_Error) {
        return $query_error;
    }
    return ['query' => ['query' => ox_loop_query_from_input($input)]];
}

/**
 * Reject loop inputs that are meaningless for the target loop type, so a
 * caller learns their config was wrong instead of it being silently dropped.
 *
 * @param array<string, mixed> $input
 */
function ox_irrelevant_loop_input_error(string $type, array $input): ?WP_Error
{
    [$bad, $hint] = match (true) {
        str_ends_with($type, '\\DynamicDataLoop') => [
            ['post_types', 'taxonomy', 'hide_empty', 'posts_per_page', 'order_by', 'order', 'offset'],
            'a DynamicDataLoop (it only takes `repeater_field`)',
        ],
        str_ends_with($type, '\\TermLoopBuilder') => [
            ['post_types', 'offset', 'repeater_field'],
            'a TermLoopBuilder (use `taxonomy`, `hide_empty`, `posts_per_page`, `order_by`, `order`)',
        ],
        default => [
            ['taxonomy', 'hide_empty', 'repeater_field'],
            'a PostsLoop (use `post_types`, `posts_per_page`, `order_by`, `order`, `offset`)',
        ],
    };
    foreach ($bad as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: input key, 2: loop-type hint */
            __('`%1$s` is not a valid input for %2$s.', domain: 'novamira-pro'),
            $key,
            $hint,
        ));
    }
    return null;
}

/**
 * The `orderby` values `WP_Term_Query` actually understands. The TermLoopBuilder
 * passes `content.query.orderby` straight to `WP_Term_Query` with NO validation
 * (`subplugins/breakdance-elements/lib/term-builder.php`), so a POST orderby like
 * `date` / `title` / `menu_order` is silently coerced by core (it falls back to
 * ordering by term id/name) — the value is accepted but ignored. Restrict to the
 * real term set so a wrong ordering fails loudly instead of rendering wrong.
 *
 * @return list<string>
 */
function ox_term_orderby_values(): array
{
    return [
        'name',
        'slug',
        'term_id',
        'id',
        'ID',
        'term_group',
        'description',
        'parent',
        'count',
        'none',
    ];
}

/** @return list<string> */
function ox_post_orderby_values(): array
{
    return ['date', 'title', 'menu_order', 'rand', 'modified', 'ID'];
}

/**
 * Validate the TermLoopBuilder inputs: `taxonomy` is required and must be a
 * registered taxonomy; `order_by`, when present, must be a term ordering
 * `WP_Term_Query` supports (NOT the POST set). Returns a WP_Error, or null.
 *
 * @param array<string, mixed> $input
 */
function ox_validate_loop_term_input(array $input): ?WP_Error
{
    /** @var mixed $taxonomy */
    $taxonomy = $input['taxonomy'] ?? null;
    if (!is_string($taxonomy) || $taxonomy === '') {
        return ox_error('oxygen_invalid_input', __(
            'A TermLoopBuilder needs `taxonomy` — the slug of the taxonomy to loop over (e.g. `category`, `post_tag`).',
            domain: 'novamira-pro',
        ));
    }
    if (!taxonomy_exists($taxonomy)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: taxonomy slug */
            __('`taxonomy` `%s` is not a registered taxonomy.', domain: 'novamira-pro'),
            $taxonomy,
        ));
    }
    if (isset($input['order_by']) && is_string($input['order_by'])) {
        $allowed = ox_term_orderby_values();
        if (!in_array($input['order_by'], $allowed, strict: true)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: 1: rejected order_by, 2: allowed term orderings */
                __(
                    '`order_by` `%1$s` is not a valid term ordering for a TermLoopBuilder (that is the POST set, which WP_Term_Query silently ignores). Use one of: %2$s. Default is `name`.',
                    domain: 'novamira-pro',
                ),
                $input['order_by'],
                implode(', ', $allowed),
            ));
        }
    }
    return null;
}

/**
 * Build the native term-query args a TermLoopBuilder reads at
 * `content.query.{taxonomy,hide_empty,limit,orderby,order}`. `posts_per_page`
 * is reused as the term `limit` (clamped 1..100); ordering defaults suit terms
 * (`name` / `ASC`).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ox_loop_term_query_from_input(array $input): array
{
    $limit = max(1, min(100, ox_coerce_int($input['posts_per_page'] ?? 10)));
    $order_by = isset($input['order_by']) && is_string($input['order_by']) ? $input['order_by'] : 'name';
    $order = isset($input['order']) && $input['order'] === 'DESC' ? 'DESC' : 'ASC';
    return [
        'taxonomy' => (string) $input['taxonomy'],
        'hide_empty' => ox_coerce_bool($input['hide_empty'] ?? false),
        'limit' => $limit,
        'orderby' => $order_by,
        'order' => $order,
        'load_terms_by_query' => false,
    ];
}

/**
 * Whether a slug is a registered repeater / flexible-content source (a valid
 * DynamicDataLoop field), per the engine's own getFieldsByReturnType().
 */
function ox_is_repeater_source(string $slug): bool
{
    if (!class_exists('Breakdance\\DynamicData\\DynamicDataController')) {
        return false;
    }
    $controller = \Breakdance\DynamicData\DynamicDataController::getInstance();
    if (!method_exists($controller, 'getFieldsByReturnType')) {
        return false;
    }
    foreach (['repeater', 'flexible_content'] as $return_type) {
        /** @var iterable<object> $fields */
        $fields = $controller->getFieldsByReturnType($return_type);
        foreach ($fields as $field) {
            if (method_exists($field, 'slug') && $field->slug() === $slug) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Deep-merge the type-specific loop config and the repeated-item block id into
 * an element's existing `content`, preserving its other content keys (tag,
 * pagination, …). Returns the new `content` object.
 *
 * @param array<string, mixed> $data element data node ({type, properties})
 * @param array<string, mixed> $config content sub-keys to merge (query or field)
 * @return array<string, mixed>
 */
function ox_merge_loop_content(array $data, array $config, int $item_block_id): array
{
    /** @var array<string, mixed> $props */
    $props = isset($data['properties']) && is_array($data['properties']) ? $data['properties'] : [];
    /** @var array<string, mixed> $content */
    $content = isset($props['content']) && is_array($props['content']) ? $props['content'] : [];
    /** @var mixed $value */
    foreach ($config as $key => $value) {
        $content[$key] = $value;
    }
    /** @var array<string, mixed> $repeated */
    $repeated = isset($content['repeated_block']) && is_array($content['repeated_block'])
        ? $content['repeated_block']
        : [];
    $repeated['global_block'] = $item_block_id;
    $content['repeated_block'] = $repeated;
    return $content;
}

/**
 * Validate the query inputs (post types exist, ordering shape). Returns a
 * WP_Error to bail with, or null.
 *
 * @param array<string, mixed> $input
 */
function ox_validate_loop_query_input(array $input): ?WP_Error
{
    foreach (ox_loop_post_types($input) as $slug) {
        if (!post_type_exists($slug)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %s: post type slug */
                __('post_types contains `%s`, which is not a registered post type.', domain: 'novamira-pro'),
                $slug,
            ));
        }
    }
    if (isset($input['order']) && !in_array($input['order'], ['ASC', 'DESC'], strict: true)) {
        return ox_error('oxygen_invalid_input', __('order must be `ASC` or `DESC`.', domain: 'novamira-pro'));
    }
    if (isset($input['order_by'])) {
        if (!is_string($input['order_by']) || !in_array($input['order_by'], ox_post_orderby_values(), strict: true)) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('PostsLoop order_by must be one of: %s.', domain: 'novamira-pro'),
                implode(', ', ox_post_orderby_values()),
            ));
        }
    }
    return null;
}

/**
 * Resolve the requested post types, defaulting to `['post']`.
 *
 * @param array<string, mixed> $input
 * @return list<string>
 */
function ox_loop_post_types(array $input): array
{
    /** @var mixed $raw */
    $raw = $input['post_types'] ?? null;
    if (!is_array($raw) || $raw === []) {
        return ['post'];
    }
    $out = [];
    /** @var mixed $slug */
    foreach ($raw as $slug) {
        if (!is_string($slug) || $slug === '') {
            continue;
        }
        $out[] = $slug;
    }
    return $out === [] ? ['post'] : $out;
}
