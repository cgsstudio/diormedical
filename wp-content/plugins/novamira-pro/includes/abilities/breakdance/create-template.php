<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-create-template', [
    'label' => __('Create Breakdance Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Breakdance template post under one of the five CPTs. `post_type` is required and must be one of `breakdance_template`, `breakdance_header`, `breakdance_footer`, `breakdance_popup`, `breakdance_block`. `title` is required. Optional `settings` is the `_breakdance_template_settings` JSON object (leave it out and the post is created without conditions, use set-template-conditions later). Element-tree content (the builder canvas) is NOT created here. Pair this with the Sprint 3 element-tree abilities or open the template in the Breakdance editor.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Visible title in the admin list. Required.'],
            'post_type' => [
                'type' => 'string',
                'enum' => [
                    'breakdance_template',
                    'breakdance_header',
                    'breakdance_footer',
                    'breakdance_popup',
                    'breakdance_block',
                ],
                'description' => 'Which CPT to create under. Required.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Optional initial `_breakdance_template_settings` JSON. Shape: `{type?: string, parentId?: int, ruleGroups?: array, triggers?: array, priority?: int, fallback?: bool}`. `type` must be a registered template-type slug — discover the valid values via list-template-conditions (`template_types`); for a single template targeting a custom post type it is that post type\'s slug (e.g. `location`). Omit to create an empty template, then set conditions later via set-template-conditions.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish'],
                'description' => 'Post status. Breakdance forces template-like CPTs to `publish` via an internal `wp_insert_post_data` filter, so `draft` is not accepted here.',
                'default' => 'publish',
            ],
            'slug' => [
                'type' => 'string',
                'description' => 'URL slug for the template (WordPress-native alias for `post_name`). Omit to let WP derive it from the title.',
            ],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
        ],
        'required' => ['title', 'post_type'],
        'additionalProperties' => false,
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an empty template post. Pair with set-template-conditions to give the template visibility rules, and with the Sprint 3 element-tree abilities to populate the builder canvas. For popups, you also need `triggers` in the settings (see get-template on an existing popup for the shape).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function breakdance_create_template(array $input): array
{
    if (!isset($input['title']) || !is_string($input['title'])) {
        return ['success' => false, 'error' => '`title` is required and must be a non-empty string.'];
    }
    if (!isset($input['post_type']) || !is_string($input['post_type'])) {
        return ['success' => false, 'error' => '`post_type` is required.'];
    }
    $title = trim($input['title']);
    if ($title === '') {
        return ['success' => false, 'error' => '`title` is required and must be a non-empty string.'];
    }
    if (!in_array($input['post_type'], bd_template_post_types(), strict: true)) {
        return ['success' => false, 'error' => '`post_type` must be one of the five Breakdance template CPTs.'];
    }
    $insert = [
        'post_title' => $title,
        'post_type' => $input['post_type'],
        'post_status' => 'publish',
        'post_content' => '',
    ];
    /** @var mixed $slug_raw */
    $slug_raw = $input['post_name'] ?? $input['slug'] ?? null;
    if (is_string($slug_raw) && $slug_raw !== '') {
        $insert['post_name'] = $slug_raw;
    }
    $post_id = wp_insert_post($insert, wp_error: true);

    if ($post_id instanceof \WP_Error) {
        return ['success' => false, 'error' => 'wp_insert_post failed: ' . $post_id->get_error_message()];
    }

    /** @var array<string, mixed> $initial_settings */
    $initial_settings = isset($input['settings']) && is_array($input['settings']) ? $input['settings'] : [];
    bd_write_template_settings($post_id, $initial_settings);

    $created = bd_load_template($post_id);
    if ($created === null) {
        return ['success' => false, 'error' => 'Template created but could not be re-loaded.'];
    }
    return ['success' => true, 'template' => bd_template_to_array($created)];
}
