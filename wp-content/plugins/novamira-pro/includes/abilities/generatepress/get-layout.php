<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-layout', [
    'label' => __('Get GeneratePress Layout', domain: 'novamira-pro'),
    'description' => __(
        'Resolves the layout decisions GeneratePress would make for the current request context (or for a specific post if `post_id` is passed). Returns sidebar layout, navigation location, footer-widgets count, and the flexbox-engine flag. Use this when reasoning about where to place hook elements or block elements — the values reflect the cascade of theme settings + per-post overrides + filters, which is hard to compute by hand. Read-only — does NOT mutate state even when `post_id` is supplied.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Optional post id to resolve layout for. When omitted, uses the global $post / current query (request context). Pass a specific id when authoring a layout decision for that post.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'sidebar_layout' => [
                'type' => 'string',
                'description' => 'One of: right-sidebar | left-sidebar | no-sidebar | both-sidebars | both-left | both-right.',
            ],
            'nav_location' => [
                'type' => 'string',
                'description' => 'One of: nav-above-header | nav-below-header | nav-left-sidebar | nav-right-sidebar | nav-float-right | nav-float-left | nav-aligned-right | nav-aligned-left | nav-aligned-center | hidden.',
            ],
            'footer_widgets' => ['type' => 'integer', 'description' => 'Number of footer widget columns (0 to 5).'],
            'is_flexbox' => [
                'type' => 'boolean',
                'description' => 'true on modern installs (GP 3.0+ default). Some older theme settings only work with the float fallback.',
            ],
        ],
        'required' => ['sidebar_layout', 'nav_location', 'footer_widgets', 'is_flexbox'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_layout',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass `post_id` when the user has named a specific post — the layout resolution can change per-post via per-post layout metabox. Omit it when you need the site-wide default.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int} $input
 * @return array<string, mixed>
 */
function generatepress_get_layout(array $input): array
{
    $post_id = $input['post_id'] ?? 0;

    if ($post_id === 0) {
        return gp_resolve_layout();
    }

    return gp_resolve_layout_for_post($post_id);
}

/**
 * Snapshot of the layout functions for the current global $post / query.
 *
 * @return array<string, mixed>
 */
function gp_resolve_layout(): array
{
    // GP's generate_get_footer_widgets() returns the stored option, which
    // can come back as a numeric string ('3'). The output_schema declares
    // integer — coerce so the contract holds.
    return [
        'sidebar_layout' => function_exists('generate_get_layout') ? generate_get_layout() : '',
        'nav_location' => function_exists('generate_get_navigation_location') ? generate_get_navigation_location() : '',
        'footer_widgets' => function_exists('generate_get_footer_widgets') ? (int) generate_get_footer_widgets() : 0,
        'is_flexbox' => function_exists('generate_is_using_flexbox') ? generate_is_using_flexbox() : false,
    ];
}

/**
 * Temporarily swap the global $post to the requested id, snapshot the
 * layout, then restore. Used by get-layout when the caller passes
 * `post_id` to ask "what would the layout be for THIS post".
 *
 * @return array<string, mixed>
 */
function gp_resolve_layout_for_post(int $post_id): array
{
    global $post;
    $previous = $post instanceof \WP_Post ? $post : null;
    /** @var \WP_Post|array<int, mixed>|null $loaded */
    $loaded = get_post($post_id);
    if ($loaded instanceof \WP_Post) {
        $post = $loaded;
        setup_postdata($loaded);
    }

    // try/finally so a third-party filter throwing inside gp_resolve_layout()
    // (e.g. generate_sidebar_layout) doesn't leave the global $post pointing
    // at the temporary swap. The description promises read-only — keep it.
    try {
        $out = gp_resolve_layout();
    } finally {
        $post = $previous;
        gp_restore_postdata($previous);
    }
    return $out;
}

/**
 * Restore the post-data global pointers after a temporary swap. When the
 * caller had no $post (no global "current post") wp_reset_postdata is
 * the right wipe; otherwise setup_postdata rebuilds the original frame.
 */
function gp_restore_postdata(?\WP_Post $previous): void
{
    if ($previous instanceof \WP_Post) {
        setup_postdata($previous);
        return;
    }
    wp_reset_postdata();
}
