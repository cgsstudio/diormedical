<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

/**
 * Ability: Set Bricks supported post types.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-set-post-types', [
    'label' => __('Set Bricks Post Types', domain: 'novamira-pro'),
    'description' => __(
        'Sets the flat list of WordPress post type slugs enabled for Bricks editing. Use this instead of the broad bricks-set-settings ability when fixing Bricks setup.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_types' => [
                'type' => 'array',
                'description' => 'Flat list of registered post type slugs to enable for Bricks editing, e.g. ["page","post"].',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['post_types'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'saved' => ['type' => 'boolean'],
            'post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_set_post_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this narrow ability to enable Bricks for pages/posts. Pass post_types as a flat array of registered post type slugs, for example {"post_types":["page","post"]}. This ability updates only the global postTypes setting, preserves other Bricks global settings, validates the exact shape before save, and rolls back if Bricks cannot read the result.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_set_post_types(array $input): array|WP_Error
{
    /** @var mixed $post_types */
    $post_types = $input['post_types'];
    $validation = bricks_validate_post_types_setting($post_types);
    if (is_wp_error($validation)) {
        return $validation;
    }
    if (!is_array($post_types)) {
        return bricks_error('invalid_type', message: 'post_types must be an array.');
    }
    /** @var list<string> $post_types */
    $post_types = array_values($post_types);

    /** @var array<string, mixed>|false $previous */
    $previous = get_option(BRICKS_DB_GLOBAL_SETTINGS, []);

    $final = is_array($previous) ? $previous : [];
    $final['postTypes'] = $post_types;

    $health_check = bricks_write_global_settings_guarded($final, $previous);
    if ($health_check instanceof WP_Error) {
        return $health_check;
    }

    $result = [
        'saved' => true,
        'post_types' => $post_types,
    ];

    if ($post_types === []) {
        $result['warnings'] = ['Setting post_types to an empty array disables Bricks editing for all post types.'];
    }

    return $result;
}
