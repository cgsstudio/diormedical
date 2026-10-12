<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: unassign one of a Voxel post type's base template slots (without
 * deleting the template).
 */

wp_register_ability('novamira/voxel-clear-post-type-template', [
    'label' => __('Clear Voxel Post Type Template', domain: 'novamira-pro'),
    'description' => __(
        'Unassign one of a Voxel post type\'s base template slots (single, card, archive or form), so no template is bound to it. The underlying template/page post is NOT deleted. The slot is stored as unset the way Voxel stores it (a null id). Returns the previous assignment so the change can be reverted. Note: while a slot is unset that view renders without its layout, and Voxel re-creates missing single/card/archive templates automatically when an admin opens the post type in the Voxel editor screen — so an unset for those slots lasts only until then.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => [
                'type' => 'string',
                'description' => 'The Voxel post type key, e.g. "places".',
            ],
            'template_key' => [
                'type' => 'string',
                'enum' => ['single', 'card', 'archive', 'form'],
                'description' => 'The template slot to unassign.',
            ],
        ],
        'required' => ['post_type', 'template_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string'],
            'template_key' => ['type' => 'string'],
            'cleared' => ['type' => 'boolean'],
            'previous_template_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['post_type', 'template_key', 'cleared', 'previous_template_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_clear_post_type_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Unassigns a post type template slot (does not delete the template post). Visible site change; returns the previous id to revert. Voxel auto-recreates missing single/card/archive templates when an admin opens the post type in the Voxel editor.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_clear_post_type_template(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $pt_key = is_string($input['post_type'] ?? null) ? $input['post_type'] : '';
    $template_key = ptt_template_key_or_error($input);
    if ($template_key instanceof WP_Error) {
        return $template_key;
    }

    return vx_with_lock('post_types', static function () use ($pt_key, $template_key): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $pt_key);
        if ($config instanceof WP_Error) {
            return $config;
        }

        $templates = ptt_read_templates($config);
        $previous = $templates[$template_key];
        // Voxel represents an unset slot as null (post-type-base-templates-
        // controller.php stores null on unbind; get_templates() reads any
        // non-numeric value back as null). The template post is kept.
        $templates[$template_key] = null;

        $config['templates'] = $templates;
        $all[$pt_key] = $config;
        vx_write_post_types_config($all);

        return [
            'post_type' => $pt_key,
            'template_key' => $template_key,
            'cleared' => true,
            'previous_template_id' => $previous,
        ];
    });
}
