<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-get-template', [
    'label' => __('Get Breakdance Template', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full Breakdance template payload for a single id: title, post_type, status, modified date, and the entire decoded `_breakdance_template_settings` JSON (parentId, type slug, ruleGroups, triggers, priority, fallback). Use this when you need the conditions / triggers in full. list-templates only carries summary counts.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Post id of one of the five Breakdance template CPTs.',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Alias of `id`, matching the element abilities. `id` wins when both are passed.',
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['id']],
            ['required' => ['post_id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string', 'description' => 'URL slug (WP `post_name`).'],
            'post_type' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'modified' => ['type' => 'string'],
            'settings' => ['type' => 'object'],
            'rule_groups' => ['type' => 'array'],
            'triggers' => ['type' => 'array'],
            'priority' => ['type' => 'integer'],
            'fallback' => ['type' => 'boolean'],
            'type' => ['type' => 'string'],
        ],
        'required' => ['id', 'title', 'post_type'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the integer id from list-templates. Use this to read the full rule_groups / triggers / settings before composing an edit-template patch.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id?: int, post_id?: int} $input
 * @return array<string, mixed>|WP_Error
 */
function breakdance_get_template(array $input): array|WP_Error
{
    $id = bd_template_id_arg($input);
    if ($id <= 0) {
        return bd_error('bd_invalid_input', __('id (or post_id) must be a positive integer.', domain: 'novamira-pro'));
    }
    $post = bd_load_template($id);
    if ($post === null) {
        return bd_error('bd_not_found', sprintf(
            __('Breakdance template with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return bd_template_to_array($post);
}
