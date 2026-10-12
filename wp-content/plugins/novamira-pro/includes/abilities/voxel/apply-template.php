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
 * Ability: assign an existing template/page to a Voxel site-template area.
 */

wp_register_ability('novamira/voxel-apply-template', [
    'label' => __('Apply Voxel Template', domain: 'novamira-pro'),
    'description' => __(
        'Assign an existing template or page to a Voxel site-template area (e.g. set which template renders the header, footer, timeline, checkout, ...). Provide the area key (from novamira/voxel-list-templates) and the id of an EXISTING template/page. The target must exist and match the area\'s expected type (page vs template). Returns the previous assignment so the change can be reverted. This changes what renders on the site.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'area' => [
                'type' => 'string',
                'description' => 'The template area key, e.g. "header" (see list-templates).',
            ],
            'template_id' => ['type' => 'integer', 'description' => 'The id of an existing template/page to assign.'],
        ],
        'required' => ['area', 'template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'area' => ['type' => 'string'],
            'template_id' => ['type' => 'integer'],
            'previous_template_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['area', 'template_id', 'previous_template_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_apply_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Assigns an existing template to a site area (validated + locked). Visible site change; returns the previous id to revert.',
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
function voxel_apply_template(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $area = is_string($input['area'] ?? null) ? $input['area'] : '';
    $template = vx_find_base_template($area);
    if ($template === null) {
        return vx_not_found('template area', $area);
    }

    $new_id = vx_coerce_int($input['template_id'] ?? null);
    if ($new_id === null || $new_id <= 0) {
        return vx_invalid(__('A valid template_id is required.', domain: 'novamira-pro'));
    }

    // Validate the target exists and matches the area's expected type — exactly
    // the check Voxel's own admin performs before assigning.
    $type = is_string($template['type'] ?? null) ? $template['type'] : '';
    if ($type === 'page' && !\Voxel\page_exists($new_id)) {
        return vx_invalid(vx_template_target_error($new_id, expected: 'page', area: $area));
    }
    if ($type === 'template' && !\Voxel\template_exists($new_id)) {
        return vx_invalid(vx_template_target_error($new_id, expected: 'template', area: $area));
    }

    return vx_with_lock('templates', static function () use ($area, $new_id): array {
        $previous = vx_coerce_int(\Voxel\get('templates.' . $area));
        \Voxel\set('templates.' . $area, $new_id);
        return ['area' => $area, 'template_id' => $new_id, 'previous_template_id' => $previous];
    });
}

/**
 * Explain why a template target was refused.
 *
 * "does not exist" is wrong — and misleading — whenever the id is real but holds
 * the other kind of document, which is the common mistake: passing a template id
 * to a page area. Naming the type actually found sends the caller to the right
 * problem instead of hunting a missing post.
 */
function vx_template_target_error(int $id, string $expected, string $area): string
{
    $found = get_post_type($id);
    if ($found === false) {
        return sprintf(
            /* translators: 1: post id, 2: expected document type. */
            __(
                'No post with id %1$d exists, so it cannot be assigned as the %2$s for this area.',
                domain: 'novamira-pro',
            ),
            $id,
            $expected,
        );
    }
    return sprintf(
        /* translators: 1: area key, 2: expected document type, 3: post id, 4: actual post type. */
        __(
            'Area "%1$s" expects a %2$s, but post %3$d is a "%4$s". Use novamira/voxel-list-templates to see each area\'s expected type.',
            domain: 'novamira-pro',
        ),
        $area,
        $expected,
        $id,
        $found,
    );
}
