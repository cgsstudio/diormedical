<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-get-taxonomy', [
    'label' => __('Get Meta Box Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full settings payload for a single custom taxonomy registered via MB Custom Post Type, identified by slug. The payload mirrors the MB CPT internal format (slug, labels, hierarchical, object_type, public, etc.) and can be used as a reference for metabox-edit-taxonomy.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'The taxonomy slug.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'source' => ['type' => 'string', 'const' => 'db'],
            'db_post_id' => ['type' => 'integer'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['slug', 'title', 'source', 'db_post_id', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_get_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetch the full settings for one MB Custom Post Type taxonomy entry. The settings object is the raw payload (slug, labels, hierarchical, object_type, public, show_in_rest, rewrite, etc.). Use as reference or feed into metabox-edit-taxonomy.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_get_taxonomy(array $args): array|WP_Error
{
    $guard = mb_cpt_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $args['slug'];
    if ($slug === '') {
        return new WP_Error('metabox_invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $post = mb_find_cpt_post($slug, post_type: 'mb-taxonomy');
    if ($post === null) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: taxonomy slug. */
            __('Taxonomy "%s" not found.', domain: 'novamira-pro'),
            $slug,
        ));
    }

    $settings = mb_decode_cpt_post_settings($post);
    if (is_array($settings)) {
        // Present the post-type association under the agent-facing `object_type`
        // name, sourced from MB's real storage key `types` (see
        // metabox-create-taxonomy). A legacy taxonomy still carrying only the
        // ignored `object_type` key reads as [] here — honest, since MB attaches
        // it to nothing until it is re-saved through metabox-edit-taxonomy.
        /** @var mixed $types_raw */
        $types_raw = $settings['types'] ?? [];
        $settings['object_type'] = is_array($types_raw)
            ? array_values(array_filter($types_raw, callback: 'is_string'))
            : [];
        unset($settings['types']);
    }

    return [
        'slug' => $slug,
        'title' => $post->post_title,
        'source' => 'db',
        'db_post_id' => $post->ID,
        'settings' => $settings ?? [],
    ];
}
