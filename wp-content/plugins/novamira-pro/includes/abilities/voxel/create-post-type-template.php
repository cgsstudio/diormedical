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
 * Ability: create a new, unbound template document for a Voxel post-type
 * template slot (single, card, archive, form).
 */

wp_register_ability('novamira/voxel-create-post-type-template', [
    'label' => __('Create Voxel Post Type Template', domain: 'novamira-pro'),
    'description' => __(
        'Create a new, empty template document of the right kind for a Voxel post-type template slot, using Voxel\'s own factories. For "single", "card" and "archive" this creates a published elementor_library template with the matching Elementor document type (single-post, card, archive) and Voxel\'s template library category; for "form" it creates a published WP page prepared for the Elementor editor. The new document is NOT bound to any post type — bind it with novamira/voxel-apply-post-type-template. Returns the new template_id. The layout itself is designed in Elementor afterwards; a freshly created template renders empty.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Title of the new template document. Voxel renames bound base templates to "<Post type>: <Slot>" during its periodic template sync, so this title is a working label.',
            ],
            'template_key' => [
                'type' => 'string',
                'enum' => ['single', 'card', 'archive', 'form'],
                'description' => 'The slot kind to create the document for (decides the Elementor document type, or a page for form).',
            ],
        ],
        'required' => ['title', 'template_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'template_key' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'wp_post_type' => [
                'type' => 'string',
                'description' => 'The WordPress post type of the created document: elementor_library (single/card/archive) or page (form).',
            ],
        ],
        'required' => ['template_id', 'template_key', 'title', 'wp_post_type'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_create_post_type_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an unbound, empty template document for a post-type slot; bind it with novamira/voxel-apply-post-type-template. Not idempotent: each call creates a new post. The layout is designed in Elementor afterwards.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_create_post_type_template(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $title = trim(is_string($input['title'] ?? null) ? $input['title'] : '');
    if ($title === '') {
        return vx_invalid(__('A non-empty title is required.', domain: 'novamira-pro'));
    }

    $template_key = ptt_template_key_or_error($input);
    if ($template_key instanceof WP_Error) {
        return $template_key;
    }

    // Use Voxel's own factories (template-utils.php): create_template() for the
    // elementor_library slots with the slot's document type, create_page() for
    // the form slot. Neither needs anything post-type-specific — binding is a
    // separate step (apply-post-type-template), so create stays agnostic.
    $doc = ptt_document_type($template_key);
    $new_id = $doc === null ? \Voxel\create_page($title) : \Voxel\create_template($title, $doc['type']);

    if ($new_id instanceof WP_Error) {
        return vx_operation_failed(sprintf(
            /* translators: %s underlying error message. */
            __('Could not create the template: %s', domain: 'novamira-pro'),
            $new_id->get_error_message(),
        ));
    }
    $template_id = absint($new_id);
    if ($template_id === 0) {
        return vx_operation_failed(__('Could not create the template.', domain: 'novamira-pro'));
    }

    /** @var mixed $stored_title */
    $stored_title = get_post_field('post_title', $template_id);

    return [
        'template_id' => $template_id,
        'template_key' => $template_key,
        'title' => is_string($stored_title) ? $stored_title : $title,
        'wp_post_type' => $doc === null ? 'page' : 'elementor_library',
    ];
}
