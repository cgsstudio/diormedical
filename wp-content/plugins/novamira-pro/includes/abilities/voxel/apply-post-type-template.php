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
 * Ability: assign an existing template/page to one of a Voxel post type's base
 * template slots (single, card, archive, form).
 */

wp_register_ability('novamira/voxel-apply-post-type-template', [
    'label' => __('Apply Voxel Post Type Template', domain: 'novamira-pro'),
    'description' => __(
        'Assign an existing template to one of a Voxel post type\'s base template slots: "single" (the listing page), "card" (the preview card used in search results and grids), "archive" (the archive/search page) or "form" (the front-end create/edit page). single/card/archive take an existing elementor_library template; form takes an existing WP page. The template\'s Elementor document type is corrected to match the slot, exactly as Voxel\'s own admin does on assignment. Create an unbound template first with novamira/voxel-create-post-type-template. Returns the previous assignment so the change can be reverted. This changes what renders for that post type on the live site.',
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
                'description' => 'The template slot to assign.',
            ],
            'template_id' => [
                'type' => 'integer',
                'description' => 'Id of an existing elementor_library template (single/card/archive) or WP page (form).',
            ],
        ],
        'required' => ['post_type', 'template_key', 'template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_type' => ['type' => 'string'],
            'template_key' => ['type' => 'string'],
            'template_id' => ['type' => 'integer'],
            'previous_template_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['post_type', 'template_key', 'template_id', 'previous_template_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_apply_post_type_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Binds an existing template to a post type slot (validated + locked) and fixes its Elementor document type like Voxel\'s admin. Visible site change; returns the previous id to revert. single/card/archive need an elementor_library id, form needs a page id.',
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
function voxel_apply_post_type_template(array $input): array|WP_Error
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

    $template_id = vx_coerce_int($input['template_id'] ?? null);
    if ($template_id === null || $template_id <= 0) {
        return vx_invalid(__('A valid template_id is required.', domain: 'novamira-pro'));
    }

    // Validate the target exists and matches the slot's expected document kind
    // — the same checks Voxel's own admin performs before assigning
    // (post-type-base-templates-controller.php, update_base_template_id).
    $target_ok = $template_key === 'form' ? \Voxel\page_exists($template_id) : \Voxel\template_exists($template_id);
    if (!$target_ok) {
        return ptt_target_error($template_id, $template_key, expected: $template_key === 'form' ? 'page' : 'template');
    }

    return vx_with_lock('post_types', static function () use ($pt_key, $template_key, $template_id): array|WP_Error {
        $all = vx_read_post_types_config();
        $config = vx_post_type_config_or_error($all, $pt_key);
        if ($config instanceof WP_Error) {
            return $config;
        }

        $templates = ptt_read_templates($config);
        $previous = $templates[$template_key];
        $templates[$template_key] = $template_id;

        // Correct the document type meta/term (+ theme-builder location) so
        // Elementor opens and renders the template as the slot expects —
        // Voxel's admin applies the same fixup on every assignment.
        ptt_fix_document_type($template_id, $template_key);

        $config['templates'] = $templates;
        $all[$pt_key] = $config;
        vx_write_post_types_config($all);

        return [
            'post_type' => $pt_key,
            'template_key' => $template_key,
            'template_id' => $template_id,
            'previous_template_id' => $previous,
        ];
    });
}
