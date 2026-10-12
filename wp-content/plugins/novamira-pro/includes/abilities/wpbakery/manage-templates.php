<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Abilities: list, save, and delete WPBakery user templates.
 */

wp_register_ability('novamira/wpbakery-list-templates', [
    'label' => __('List WPBakery Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists saved WPBakery user templates. Optionally filter by category. Returns template_id, name, category, and a short shortcode preview for each template.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'description' => 'Filter by category name (exact match on the novamira_category field).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'preview' => ['type' => 'string'],
                    ],
                    'required' => ['template_id', 'name', 'category', 'preview'],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['templates', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns saved user templates. Use category to filter. Each entry includes template_id (use this with apply-template and delete-template), name, category, and a short preview of the saved shortcode.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/wpbakery-create-template', [
    'label' => __('Create WPBakery Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a reusable WPBakery template from a layout. Provide either (post_id + element_idx) to capture an element from a page, or a raw shortcode string. The template appears in the WPBakery editor under "My Templates" and can be re-applied with wpbakery-apply-template.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Display name for the template.',
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Custom category label (e.g. "Hero Sections"). Used for filtering via wpbakery-list-templates.',
                'default' => '',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to read the element from. Required when using element_idx.',
            ],
            'element_idx' => [
                'type' => 'integer',
                'description' => 'The _idx of the element to capture (from wpbakery-get-content). The element and all its children are saved.',
            ],
            'shortcode' => [
                'type' => 'string',
                'description' => 'Raw WPBakery shortcode string to save directly. Use instead of post_id + element_idx.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'template_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call wpbakery-get-content first to find the _idx of the element to save. Prefer post_id + element_idx over raw shortcode so the captured layout is always in sync with what is on the page.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/wpbakery-delete-template', [
    'label' => __('Delete WPBakery Template', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes a saved WPBakery template by its id (from wpbakery-list-templates).',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'string',
                'description' => 'The id of the template to delete (from wpbakery-list-templates).',
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'template_id' => ['type' => 'string'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use wpbakery-list-templates to find the template_id before deleting.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

function wpbakery_list_templates(array $input): array
{
    $category_filter = array_key_exists('category', $input) ? (string) $input['category'] : null;

    $all = wpb_get_templates();
    $result = [];

    foreach ($all as $id => $tpl) {
        $tpl_category = (string) ($tpl['novamira_category'] ?? '');
        if ($category_filter !== null && $tpl_category !== $category_filter) {
            continue;
        }

        $shortcode = (string) ($tpl['template'] ?? '');
        $template_id = (string) $id;
        $result[] = [
            'template_id' => $template_id,
            'name' => (string) ($tpl['name'] ?? $template_id),
            'category' => $tpl_category,
            'preview' => mb_substr(string: $shortcode, start: 0, length: 100),
        ];
    }

    return [
        'templates' => $result,
        'total' => count($result),
    ];
}

/**
 * @param array<string, mixed> $input
 */
function wpbakery_create_template(array $input): array
{
    $name = (string) ($input['name'] ?? '');
    $category = (string) ($input['category'] ?? '');

    $shortcode = wpb_resolve_template_shortcode($input);
    if (is_array($shortcode)) {
        return $shortcode;
    }

    $templates = wpb_get_templates();
    $template_id = sanitize_title($name) . '_' . wp_rand();

    $templates[$template_id] = [
        'name' => $name,
        'template' => $shortcode,
        'novamira_category' => $category,
    ];

    if (!wpb_save_templates($templates)) {
        return ['success' => false, 'error' => 'Failed to save template.'];
    }

    return [
        'success' => true,
        'template_id' => $template_id,
        'name' => $name,
        'category' => $category,
    ];
}

/**
 * Resolves the shortcode string from the input — either raw or from a page element.
 * Returns the shortcode string on success, or an error array on failure.
 *
 * @param array<string, mixed> $input
 * @return string|array<string, mixed>
 */
function wpb_resolve_template_shortcode(array $input): string|array
{
    if (array_key_exists('shortcode', $input) && (string) ($input['shortcode'] ?? '') !== '') {
        return (string) $input['shortcode'];
    }

    if (!array_key_exists('post_id', $input) || !array_key_exists('element_idx', $input)) {
        return ['success' => false, 'error' => 'Provide either shortcode or both post_id and element_idx.'];
    }

    $post_id = (int) $input['post_id'];
    $element_idx = (int) $input['element_idx'];

    $raw = wpb_get_post_content($post_id);
    if ($raw === null) {
        return ['success' => false, 'error' => sprintf('Post %d not found.', $post_id)];
    }

    $tree = wpb_parse_indexed_tree($raw);

    $node = wpb_find_node_by_idx($tree, $element_idx);
    if ($node === null) {
        return ['success' => false, 'error' => sprintf('Element with _idx %d not found.', $element_idx)];
    }

    return wpb_serialize_content([$node]);
}

function wpbakery_delete_template(array $input): array
{
    $template_id = (string) ($input['template_id'] ?? '');

    $templates = wpb_get_templates();
    if (!array_key_exists($template_id, $templates)) {
        return ['success' => false, 'error' => sprintf('Template "%s" not found.', $template_id)];
    }

    unset($templates[$template_id]);
    wpb_save_templates($templates);

    return [
        'success' => true,
        'template_id' => $template_id,
    ];
}
