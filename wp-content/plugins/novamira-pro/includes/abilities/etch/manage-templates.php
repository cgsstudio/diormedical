<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Abilities: manage Etch's templates — WordPress block templates (the
 * wp_template post type, the FSE template system). A template renders for a
 * slot in the template hierarchy identified by its slug (e.g. "single",
 * "archive", "page", "front-page", "404"). The template's content is the same
 * Etch block tree as a page, so it is read/written with the existing
 * novamira/etch-get-content and novamira/etch-set-content using the template's
 * id — these abilities manage the template ENTITY (title, slug, theme).
 */

if (!defined('ABSPATH')) {
    exit();
}

const ETCH_TEMPLATE_POST_TYPE = 'wp_template';

const ETCH_TEMPLATE_TAXONOMY = 'wp_theme';

const ETCH_TEMPLATE_LIST_CAP = 500;

/**
 * Resolve a template post by id, gating the wp_template post type.
 *
 * @return WP_Post|WP_Error
 */
function etch_template_or_error(int $id): WP_Post|WP_Error
{
    $post = etch_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    if ($post->post_type !== ETCH_TEMPLATE_POST_TYPE) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %d: post ID. */
            __('Post %d is not an Etch template (wp_template).', domain: 'novamira-pro'),
            $id,
        ));
    }

    return $post;
}

/**
 * The theme slug a template is bound to (its wp_theme term), or ''.
 */
function etch_template_theme(int $id): string
{
    /** @var mixed $terms */
    $terms = wp_get_object_terms($id, ETCH_TEMPLATE_TAXONOMY, ['fields' => 'names']);
    if (!is_array($terms)) {
        return '';
    }
    /** @var mixed $first */
    $first = $terms[0] ?? '';

    return is_string($first) ? $first : '';
}

/**
 * A validated, non-empty template slug (post_name), or a WP_Error.
 *
 * @return string|WP_Error
 */
function etch_template_slug_or_error(mixed $slug): string|WP_Error
{
    if (!is_string($slug) || trim($slug) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "slug" is required.', domain: 'novamira-pro'));
    }

    $clean = sanitize_title($slug);
    if ($clean === '') {
        return etch_error('etch_invalid_input', __(
            'The "slug" did not resolve to a valid template slug.',
            domain: 'novamira-pro',
        ));
    }

    return $clean;
}

/**
 * Shape one template post for output.
 *
 * @return array{id: int, title: string, slug: string, theme: string, status: string}
 */
function etch_template_view(WP_Post $post): array
{
    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'theme' => etch_template_theme($post->ID),
        'status' => $post->post_status,
    ];
}

// ---------------------------------------------------------------------------
// list-templates
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-templates', [
    'label' => __('List Etch Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists the block templates (wp_template posts) on the site. Returns a compact entry per template { id, title, slug, theme, status }. The slug is the template-hierarchy slot it fills (e.g. "single", "archive", "page", "front-page", "404"). Read or write a template\'s content with novamira/etch-get-content / novamira/etch-set-content using its id.',
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
            'count' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'theme' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'These are wp_template posts (FSE/block templates). Only database templates are listed (theme-file templates are not). Edit content via novamira/etch-set-content with the template id.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, truncated: bool, templates: list<array{id: int, title: string, slug: string, theme: string, status: string}>}
 */
function etch_list_templates(): array
{
    $query = new WP_Query([
        'post_type' => ETCH_TEMPLATE_POST_TYPE,
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => ETCH_TEMPLATE_LIST_CAP,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => false,
    ]);

    $out = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $out[] = etch_template_view($post);
    }

    return [
        'count' => count($out),
        'truncated' => $query->found_posts > count($out),
        'templates' => $out,
    ];
}

// ---------------------------------------------------------------------------
// get-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-template', [
    'label' => __('Get Etch Template', domain: 'novamira-pro'),
    'description' => __(
        'Returns one block template by id: { id, title, slug, theme, status, node_count }. node_count is how many Etch nodes its content currently has. Read the full content tree with novamira/etch-get-content (same id).',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer', 'description' => 'The wp_template post id.'],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'theme' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'node_count' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Metadata only. Use novamira/etch-get-content for the template body.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, title: string, slug: string, theme: string, status: string, node_count: int}|WP_Error
 */
function etch_get_template(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_template_or_error((int) ($input['template_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $view = etch_template_view($post);
    $view['node_count'] = etch_count_block_nodes(parse_blocks($post->post_content));

    return $view;
}

// ---------------------------------------------------------------------------
// create-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-template', [
    'label' => __('Create Etch Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new block template (wp_template post). Requires title and slug (the template-hierarchy slot, e.g. "single", "archive", "page", "front-page", "404", "page-about"); theme defaults to the active theme. Optionally pass tree to set the initial content (same node format as novamira/etch-set-content). Returns the new id and slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Human-readable template title.'],
            'slug' => [
                'type' => 'string',
                'description' => 'Template-hierarchy slug (single, archive, page, front-page, 404, …).',
            ],
            'theme' => ['type' => 'string', 'description' => 'Theme slug to bind to. Defaults to the active theme.'],
            'tree' => [
                'type' => 'array',
                'description' => 'Optional initial content nodes (same shape as etch-set-content).',
                'items' => ['type' => 'object'],
            ],
        ],
        'required' => ['title', 'slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
            'node_count' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The slug determines where the template renders (the WP template hierarchy). Use a standard slug (single, archive, page, front-page, 404, search, index) or page-<slug> for a specific page. Set the body later (or via tree here) with novamira/etch-set-content.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, slug: string, node_count: int}|WP_Error
 */
function etch_create_template(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $title */
    $title = $input['title'] ?? '';
    if (!is_string($title) || trim($title) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "title" is required.', domain: 'novamira-pro'));
    }

    $slug = etch_template_slug_or_error($input['slug'] ?? null);
    if ($slug instanceof WP_Error) {
        return $slug;
    }

    $node_count = 0;
    $markup = '';
    if (isset($input['tree'])) {
        $markup = etch_tree_to_markup_or_error($input['tree'], $node_count);
        if ($markup instanceof WP_Error) {
            return $markup;
        }
    }

    /** @var mixed $theme */
    $theme = $input['theme'] ?? null;
    $theme_slug = is_string($theme) && $theme !== '' ? $theme : get_stylesheet();

    $id = wp_insert_post([
        'post_type' => ETCH_TEMPLATE_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => etch_clean_text($title),
        'post_name' => $slug,
        'post_content' => wp_slash($markup),
    ], wp_error: true);

    if ($id instanceof WP_Error) {
        return $id;
    }

    wp_set_object_terms($id, $theme_slug, taxonomy: ETCH_TEMPLATE_TAXONOMY, append: false);

    return ['id' => $id, 'slug' => $slug, 'node_count' => $node_count];
}

// ---------------------------------------------------------------------------
// edit-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-template', [
    'label' => __('Edit Etch Template', domain: 'novamira-pro'),
    'description' => __(
        'Edits a block template\'s metadata (partial). Provide template_id and any of title, slug, or theme. To change the template body use novamira/etch-set-content with the same id. Returns id and slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'theme' => ['type' => 'string'],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'slug' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial metadata update; the body is edited via novamira/etch-set-content. Changing the slug changes which template-hierarchy slot the template fills.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, slug: string}|WP_Error
 */
function etch_edit_template(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_template_or_error((int) ($input['template_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $update = ['ID' => $post->ID];

    if (isset($input['title'])) {
        /** @var mixed $title */
        $title = $input['title'];
        if (!is_string($title) || trim($title) === '') {
            return etch_error('etch_invalid_input', __('"title" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $update['post_title'] = sanitize_text_field($title);
    }

    $slug = $post->post_name;
    if (isset($input['slug'])) {
        $resolved = etch_template_slug_or_error($input['slug']);
        if ($resolved instanceof WP_Error) {
            return $resolved;
        }
        $slug = $resolved;
        $update['post_name'] = $slug;
    }

    if (count($update) > 1) {
        $result = wp_update_post($update, wp_error: true);
        if ($result instanceof WP_Error) {
            return $result;
        }
    }

    if (isset($input['theme'])) {
        /** @var mixed $theme */
        $theme = $input['theme'];
        if (!is_string($theme) || $theme === '') {
            return etch_error('etch_invalid_input', __('"theme" must be a non-empty string.', domain: 'novamira-pro'));
        }
        wp_set_object_terms($post->ID, $theme, taxonomy: ETCH_TEMPLATE_TAXONOMY, append: false);
    }

    return ['id' => $post->ID, 'slug' => $slug];
}

// ---------------------------------------------------------------------------
// delete-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-template', [
    'label' => __('Delete Etch Template', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a block template (wp_template post) by id. The site falls back to the theme\'s template for that slot. Returns id and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE: removes the database template; the slot falls back to the theme file template (if any).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, deleted: bool}|WP_Error
 */
function etch_delete_template(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $post = etch_template_or_error((int) ($input['template_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $deleted = wp_delete_post($post->ID, force_delete: true);
    if (!$deleted instanceof WP_Post) {
        return etch_error('etch_delete_failed', sprintf(
            /* translators: %d: template ID. */
            __('Template %d could not be deleted.', domain: 'novamira-pro'),
            $post->ID,
        ));
    }

    return ['id' => $post->ID, 'deleted' => true];
}

// ---------------------------------------------------------------------------
// duplicate-template
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-duplicate-template', [
    'label' => __('Duplicate Etch Template', domain: 'novamira-pro'),
    'description' => __(
        'Clones an existing template (a wp_template) in one call: copies its block content verbatim and re-attaches it to the same theme, under a new unique slug. Pass template_id (required), an optional name (defaults to "<source> (copy)") and an optional slug (auto-generated from the source slug if omitted). Returns the new template id, title, and slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer', 'description' => 'The source template (wp_template) post ID.'],
            'name' => ['type' => 'string', 'description' => 'Title for the copy. Defaults to "<source title> (copy)".'],
            'slug' => [
                'type' => 'string',
                'description' => 'Slug (post_name) for the copy. Auto-generated from the source slug if omitted.',
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_duplicate_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Faithful copy (content duplicated verbatim, so mixed/lossy bodies are preserved) re-attached to the source template\'s theme. The copy is a draft-quality starting point — it is NOT auto-bound to any condition; set its routing via edit-template.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{id: int, title: string, slug: string}|WP_Error
 */
function etch_duplicate_template(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }
    $post = etch_template_or_error((int) ($input['template_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $name */
    $name = $input['name'] ?? '';
    $title = is_string($name) && trim($name) !== '' ? etch_clean_text($name) : $post->post_title . ' (copy)';

    $base = $post->post_name . '-copy';
    if (isset($input['slug'])) {
        $resolved = etch_template_slug_or_error($input['slug']);
        if ($resolved instanceof WP_Error) {
            return $resolved;
        }
        $base = $resolved;
    }
    // Make the slug unique up front so wp_insert_post stores it verbatim.
    $slug = wp_unique_post_slug(
        $base,
        post_id: 0,
        post_status: 'publish',
        post_type: ETCH_TEMPLATE_POST_TYPE,
        post_parent: 0,
    );

    $theme = etch_template_theme($post->ID);

    $new_id = wp_insert_post([
        'post_type' => ETCH_TEMPLATE_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => wp_slash($post->post_content),
    ], wp_error: true);

    if ($new_id instanceof WP_Error) {
        return $new_id;
    }

    wp_set_object_terms($new_id, $theme, taxonomy: ETCH_TEMPLATE_TAXONOMY, append: false);

    return ['id' => (int) $new_id, 'title' => $title, 'slug' => $slug];
}
