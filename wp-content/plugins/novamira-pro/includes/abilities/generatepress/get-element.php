<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-element', [
    'label' => __('Get GeneratePress Element', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single `gp_elements` post by id with every meta key the integration cares about decoded into a flat object: id, title, status, type, block_type, content, hook (+ custom_hook if hook="custom"), priority, execute_shortcodes, disable_site_header / _footer, display_conditions, exclude_conditions, user_conditions. The `_generate_hook_execute_php` meta is INTENTIONALLY OMITTED — exposing arbitrary PHP execution to AI agents is a code-injection vector we never want; a human site-owner sets it via the metabox UI. Returns WP_Error when the id does not exist or points at a non-gp_elements post.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Post id of the gp_elements entry (from generatepress-list-elements).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'block_type' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'hook' => ['type' => 'string'],
            'custom_hook' => ['type' => 'string'],
            'priority' => ['type' => 'integer'],
            'execute_shortcodes' => ['type' => 'boolean'],
            'disable_site_header' => ['type' => 'boolean'],
            'disable_site_footer' => ['type' => 'boolean'],
            'display_conditions' => ['type' => 'array'],
            'exclude_conditions' => ['type' => 'array'],
            'user_conditions' => ['type' => 'array'],
        ],
        'required' => ['id', 'title', 'type'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the integer id from generatepress-list-elements. Use this when you need to read the content body or the full conditions arrays — list-elements\' summary omits them for size.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: int} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_get_element(array $input): array|WP_Error
{
    $id = gp_coerce_int($input['id']);
    if ($id <= 0) {
        return gp_error('gp_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $post = gp_load_element($id);
    if ($post === null) {
        return gp_error('gp_not_found', sprintf(
            __('gp_elements post with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return gp_element_to_array($post);
}
